<?php
/**
 * AiExtractor - Pluggable AI Entity Extraction & Normalization Engine
 *
 * Normalizes multi-line unstructured QuickBooks invoices into:
 * 1. Commercial Product Ledger (invoice_items)
 * 2. Hardware Unit Asset Registry with Serial Numbers & Warranties (hardware_assets)
 * 3. Software License & SaaS Recurring Renewal Ledger (software_subscriptions)
 *
 * Pluggable Providers:
 * - Google Gemini API (Default, JSON Mode)
 * - OpenAI / DeepSeek (OpenAI-compatible REST API)
 * - Custom / Local Ollama (Self-hosted models)
 */

class AiExtractor {
    private $db;
    private $provider;
    private $apiKey;
    private $model;
    private $customEndpoint;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->provider = $db->getSetting('ai_provider', 'gemini');
        $this->apiKey = $db->getSetting('gemini_api_key', '');
        $this->model = $db->getSetting('ai_model', 'gemini-3.6-flash');
        $this->customEndpoint = $db->getSetting('ai_custom_endpoint', '');
    }

    /**
     * Get configured AI settings
     */
    public function getSettings() {
        return [
            'provider' => $this->provider,
            'has_key' => !empty($this->apiKey),
            'model' => $this->model,
            'custom_endpoint' => $this->customEndpoint
        ];
    }

    /**
     * Test connection to the configured AI provider
     */
    public function testConnection() {
        if (empty($this->apiKey) && empty($this->customEndpoint)) {
            return ['success' => false, 'message' => 'No API Key or Custom Endpoint configured.'];
        }

        $testPrompt = "Reply with a valid JSON object strictly matching this schema: {\"status\": \"ok\", \"provider\": \"{$this->provider}\", \"model\": \"{$this->model}\"}";

        try {
            $response = $this->callProviderRaw($testPrompt);
            $json = json_decode($response, true);
            if ($json && isset($json['status'])) {
                return ['success' => true, 'message' => 'Connection successful! Model response received.', 'data' => $json];
            }
            return ['success' => true, 'message' => 'Response received from model.', 'raw' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Extract entities from an invoice by invoice number
     */
    public function extractInvoice($invoiceNumber) {
        $lines = $this->db->fetchAll("
            SELECT id, invoice_type, invoice_date, invoice_number, customer_name,
                   item_description, tax_code, quantity, total_amount, base_value,
                   vat_component, applied_tax_rate, vat_treatment, product_category
            FROM sales
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$invoiceNumber]);

        if (empty($lines)) {
            throw new Exception("Invoice #$invoiceNumber not found in database.");
        }

        $invHeader = $lines[0];
        $invoiceDate = $invHeader['invoice_date'];
        $customerName = $invHeader['customer_name'];

        // Build structured prompt for LLM
        $prompt = $this->buildInvoiceExtractionPrompt($lines);

        $startTime = microtime(true);
        $rawResponse = '';
        $status = 'SUCCESS';
        $confidence = 100;

        try {
            $rawResponse = $this->callProviderRaw($prompt);
            $extractedData = $this->parseJsonResponse($rawResponse);
        } catch (Exception $e) {
            $status = 'FAILED';
            $this->logExtraction($invoiceNumber, 0, 0, $status, 0, $e->getMessage());
            throw $e;
        }

        $duration = round(microtime(true) - $startTime, 2);

        // Perform mathematical & consistency validation guard
        $validation = $this->validateExtraction($lines, $extractedData);
        if (!$validation['valid']) {
            $status = 'VALIDATION_WARNING';
            $confidence = $validation['confidence'];
        }

        // Persist extracted records into relational registries
        $saveResult = $this->persistEntities($invoiceNumber, $customerName, $invoiceDate, $extractedData, $confidence);

        // Audit log
        $this->logExtraction($invoiceNumber, 0, 0, $status, $confidence, $rawResponse);

        return [
            'success' => true,
            'invoice_number' => $invoiceNumber,
            'duration_seconds' => $duration,
            'confidence' => $confidence,
            'status' => $status,
            'validation_notes' => $validation['notes'],
            'entities_created' => $saveResult
        ];
    }

    /**
     * Build the structured prompt for entity extraction
     */
    private function buildInvoiceExtractionPrompt(array $lines) {
        $first = $lines[0];
        $invNum = $first['invoice_number'];
        $invDate = $first['invoice_date'];
        $customer = $first['customer_name'];

        $lineItemsText = "";
        $totalGross = 0;
        foreach ($lines as $idx => $l) {
            $lineIndex = $idx + 1;
            $amt = floatval($l['total_amount']);
            $totalGross += $amt;
            $desc = str_replace(["\r\n", "\r", "\n"], " \\n ", trim($l['item_description'] ?? ''));
            $lineItemsText .= "Line #$lineIndex [ID: {$l['id']}]: Qty: {$l['quantity']} | Gross: {$amt} | Base: {$l['base_value']} | Tax: {$l['vat_treatment']} | Desc: {$desc}\n";
        }

        $systemInstruction = <<<PROMPT
You are an expert enterprise ERP & IT Asset Extraction AI.
Analyze the following QuickBooks invoice line items for Invoice #$invNum (Date: $invDate, Customer: "$customer").

Task:
Transform unstructured multi-line invoice text into structured Commercial Products, Hardware Assets (with Serial Numbers & Warranty), and Software Subscriptions (with recurring service periods).

Business Rules:
1. Multi-line Grouping: QuickBooks invoices often have a parent product line with amount, followed by zero-amount lines containing discrete Serial Numbers (S/N), HDD details, or Warranty clauses (e.g. "Warranty 03 Years on NAS & HDDs"). Group these related lines into a single coherent commercial product.
2. Hardware Product Types ('HARDWARE'): Identify NAS units, rackmount servers, hard drives, network switches (BDCOM, etc.), access points, RAM modules, and peripherals.
   - Extract discrete serial numbers into an array `serials`. If hard drive serials belong to a NAS unit, identify their serials.
   - Extract warranty duration (e.g., "3 Years", "2 Years", "1 Year"). Compute `duration_months`.
   - Calculate `start_date` (defaults to invoice date: $invDate) and `expiry_date` (start_date + duration_months).
3. Software Product Types ('SOFTWARE_LICENSE', 'SAAS_SUBSCRIPTION', 'MAINTENANCE_AGREEMENT'):
   - Software licenses (Acronis, ESET, Windows/Office, Cloud backup, MailStore, Synalyze).
   - Maintenance Agreements (marked as 'MA', 'Maintenance Agreement', 'AMC', 'Annual Service Agreement', 'Extended Warranty Agreement').
   - Extract subscription or contract period dates (e.g., "From 04th September 2026 to 03rd September 2027" or "25th May 2021 to 24th May 2022").
   - Extract number of seats, protected PCs, or covered units.
   - If an MA specifies covered hardware units (e.g. Synology NAS, HDDs) with serial numbers, extract those serials into `serials` so the hardware assets are registered with their extended warranty!
4. Services ('SERVICE_AMC'): Installation, configuration, troubleshooting, delivery, service charges.
5. Accessories & Cables ('ACCESSORY_OTHER'): Rails, patch cords, power cords, adapters.

Return ONLY a valid JSON object matching this EXACT schema:
{
  "products": [
    {
      "product_type": "HARDWARE | SOFTWARE_LICENSE | SAAS_SUBSCRIPTION | MAINTENANCE_AGREEMENT | SERVICE_AMC | ACCESSORY_OTHER",
      "product_name": "Clean Canonical Product Name (without embedded serials)",
      "brand": "Brand name (e.g. Synology, BDCOM, Seagate, Toshiba, Acronis, ESET, Microsoft)",
      "model_sku": "Model or SKU (e.g. DS225+, S1500-24T2S, RS826RP+, Ironwolf 4TB)",
      "quantity": 1,
      "unit_price": 132000.00,
      "total_amount": 132000.00,
      "raw_line_ids": [1, 2],
      "serials": ["SERIAL1", "SERIAL2"],
      "warranty": {
        "type": "Standard | Extended | Manufacturer",
        "duration_months": 36,
        "start_date": "YYYY-MM-DD",
        "expiry_date": "YYYY-MM-DD",
        "notes": "Original clause text"
      },
      "subscription": {
        "software_name": "Software title",
        "edition_tier": "Essentials / Advanced / etc.",
        "license_seats": 1,
        "period_start_date": "YYYY-MM-DD",
        "period_end_date": "YYYY-MM-DD",
        "term_months": 12,
        "renewal_opportunity_value": 0.00
      }
    }
  ]
}

Invoice Lines to analyze:
Invoice Total Gross: $totalGross
$lineItemsText
PROMPT;

        return $systemInstruction;
    }

    /**
     * Dispatch raw call to the configured provider
     */
    private function callProviderRaw($prompt) {
        if ($this->provider === 'gemini') {
            return $this->callGemini($prompt);
        } elseif ($this->provider === 'openai' || $this->provider === 'custom') {
            return $this->callOpenAiCompatible($prompt);
        } else {
            throw new Exception("Unsupported AI provider: {$this->provider}");
        }
    }

    /**
     * Google Gemini REST API Client with JSON mode
     */
    private function callGemini($prompt) {
        if (empty($this->apiKey)) {
            throw new Exception("Gemini API Key is not configured. Please set it in Settings -> AI Configuration.");
        }

        $model = !empty($this->model) ? $this->model : 'gemini-3.6-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($this->apiKey);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.1,
                'maxOutputTokens' => 4096
            ]
        ];

        $maxRetries = 3;
        $attempt = 0;
        $response = null;
        $httpCode = 0;
        $error = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            if (function_exists('curl_close')) {
                @curl_close($ch);
            }

            if ($httpCode === 429) {
                // Rate limit hit - backoff and retry
                $sleepSec = $attempt * 5;
                sleep($sleepSec);
                continue;
            }

            break;
        }

        if ($error) {
            throw new Exception("Gemini cURL error: " . $error);
        }

        if ($httpCode !== 200) {
            $errJson = json_decode($response, true);
            $msg = $errJson['error']['message'] ?? "HTTP Status $httpCode: $response";
            throw new Exception("Gemini API Error ($httpCode): $msg");
        }

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (empty($text)) {
            throw new Exception("Empty response received from Gemini API.");
        }

        return $text;
    }

    /**
     * OpenAI / DeepSeek / Local Ollama Compatible Client
     */
    private function callOpenAiCompatible($prompt) {
        $endpoint = !empty($this->customEndpoint) ? $this->customEndpoint : 'https://api.openai.com/v1';
        $url = rtrim($endpoint, '/') . '/chat/completions';
        $model = !empty($this->model) ? $this->model : 'gpt-4o-mini';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1
        ];

        $headers = [
            'Content-Type: application/json'
        ];
        if (!empty($this->apiKey)) {
            $headers[] = "Authorization: Bearer " . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        if (function_exists('curl_close')) {
            @curl_close($ch);
        }

        if ($error) {
            throw new Exception("AI cURL error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("AI Provider Error ($httpCode): $response");
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Parse raw JSON response
     */
    private function parseJsonResponse($raw) {
        $raw = trim($raw);
        // Strip markdown fences if present
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $raw, $m)) {
            $raw = $m[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $raw, $m)) {
            $raw = $m[1];
        }

        $decoded = json_decode($raw, true);
        if (!$decoded || !is_array($decoded)) {
            throw new Exception("Malformed JSON returned by AI: " . substr($raw, 0, 200));
        }

        return $decoded;
    }

    /**
     * Mathematical & Consistency Validation Guard
     */
    private function validateExtraction(array $rawLines, array $extracted) {
        $notes = [];
        $confidence = 100;

        $rawGross = 0;
        foreach ($rawLines as $l) {
            $rawGross += floatval($l['total_amount']);
        }

        $products = $extracted['products'] ?? [];
        $extractedGross = 0;
        foreach ($products as $p) {
            $extractedGross += floatval($p['total_amount'] ?? 0);

            // Serial count check
            $qty = intval($p['quantity'] ?? 1);
            $serials = $p['serials'] ?? [];
            if ($p['product_type'] === 'HARDWARE' && $qty > 0 && !empty($serials)) {
                if (count($serials) !== $qty) {
                    $notes[] = "Quantity ({$qty}) and serial count (" . count($serials) . ") mismatch for '{$p['product_name']}'.";
                    $confidence = min($confidence, 80);
                }
            }

            // Date chronology check
            if (!empty($p['warranty']['expiry_date']) && !empty($p['warranty']['start_date'])) {
                if ($p['warranty']['expiry_date'] < $p['warranty']['start_date']) {
                    $notes[] = "Warranty expiry date precedes start date for '{$p['product_name']}'.";
                    $confidence = min($confidence, 70);
                }
            }
        }

        // Financial variance check (allow small rounding variance <= 1.00)
        $diff = abs($rawGross - $extractedGross);
        if ($diff > 1.00 && $rawGross > 0) {
            $notes[] = "Total recognized amount ({$extractedGross}) differs from invoice gross ({$rawGross}) by " . round($diff, 2);
            $confidence = min($confidence, 85);
        }

        return [
            'valid' => empty($notes),
            'confidence' => $confidence,
            'notes' => $notes
        ];
    }

    /**
     * Persist extracted products into normalized operational registries
     */
    private function persistEntities($invNum, $customer, $date, array $data, $confidence) {
        $products = $data['products'] ?? [];
        $itemsCreated = 0;
        $hardwareCreated = 0;
        $subsCreated = 0;

        $this->db->beginTransaction();
        try {
            // Delete existing extracted entities for this invoice if re-extracting
            $oldItems = $this->db->fetchAll("SELECT id FROM invoice_items WHERE invoice_number = ?", [$invNum]);
            foreach ($oldItems as $oi) {
                $this->db->execute("DELETE FROM hardware_assets WHERE invoice_item_id = ?", [$oi['id']]);
                $this->db->execute("DELETE FROM software_subscriptions WHERE invoice_item_id = ?", [$oi['id']]);
            }
            $this->db->execute("DELETE FROM invoice_items WHERE invoice_number = ?", [$invNum]);

            // Insert new entities
            $insertItemStmt = $this->db->getConnection()->prepare("
                INSERT INTO invoice_items (
                    invoice_number, customer_name, invoice_date, product_type,
                    clean_product_name, brand_category, quantity, unit_price,
                    base_value, vat_component, total_amount, raw_line_ids,
                    confidence_score, vat_treatment
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insertAssetStmt = $this->db->getConnection()->prepare("
                INSERT INTO hardware_assets (
                    invoice_number, invoice_item_id, customer_name, product_name,
                    brand, model_sku, serial_number, warranty_type,
                    warranty_months, warranty_start_date, warranty_expiry_date,
                    warranty_status, parent_serial_number, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insertSubStmt = $this->db->getConnection()->prepare("
                INSERT INTO software_subscriptions (
                    invoice_number, invoice_item_id, customer_name, software_name,
                    edition_tier, license_seats, period_start_date, period_end_date,
                    term_months, renewal_status, renewal_opportunity_value
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // Determine if customer is VAT registered
            $isVatRegistered = 0;
            if (!empty($customer)) {
                $custProfile = $this->db->fetch("SELECT is_vat_registered FROM customer_profiles WHERE customer_name = ? LIMIT 1", [$customer]);
                if ($custProfile) {
                    $isVatRegistered = (int)($custProfile['is_vat_registered'] ?? 0);
                } else {
                    $custProfile = $this->db->fetch("SELECT is_vat_registered FROM customer_profiles WHERE customer_name = ? COLLATE NOCASE LIMIT 1", [trim($customer)]);
                    if ($custProfile) {
                        $isVatRegistered = (int)($custProfile['is_vat_registered'] ?? 0);
                    }
                }
            }

            foreach ($products as $p) {
                $pType = strtoupper($p['product_type'] ?? 'HARDWARE');
                $pName = trim($p['product_name'] ?? 'Product');
                $brand = trim($p['brand'] ?? '');
                $modelSku = trim($p['model_sku'] ?? '');
                $qty = floatval($p['quantity'] ?? 1);
                $unitPrice = floatval($p['unit_price'] ?? 0);
                $totalAmt = floatval($p['total_amount'] ?? ($qty * $unitPrice));

                // Calculate base and VAT using customer registration status and invoice's tax rate
                $taxRule = $this->db->getTaxRuleForInvoice($invNum, $date);
                $rate = $taxRule['rate'];
                if ($rate <= 0) {
                    $treatment = 'VAT_EXEMPT';
                    $baseVal = $totalAmt;
                    $vatVal = 0.00;
                    $finalLineTotal = $totalAmt;
                } elseif ($isVatRegistered == 1) {
                    $treatment = 'PLUS_VAT';
                    $baseVal = $totalAmt;
                    $vatVal = round($totalAmt * $rate, 2);
                    $finalLineTotal = round($baseVal + $vatVal, 2);
                } else {
                    $treatment = 'VAT_INCLUSIVE';
                    $finalLineTotal = $totalAmt;
                    $baseVal = round($totalAmt / (1 + $rate), 2);
                    $vatVal = round($finalLineTotal - $baseVal, 2);
                }

                $rawLineIds = json_encode($p['raw_line_ids'] ?? []);

                $insertItemStmt->execute([
                    $invNum, $customer, $date, $pType,
                    $pName, $brand, $qty, $unitPrice,
                    $baseVal, $vatVal, $finalLineTotal, $rawLineIds,
                    $confidence, $treatment
                ]);
                $itemId = $this->db->lastInsertId();
                $itemsCreated++;

                // 1. If Hardware, persist each serial number into hardware_assets
                $serials = $p['serials'] ?? [];
                $wType = $p['warranty']['type'] ?? 'Standard';
                $wMonths = intval($p['warranty']['duration_months'] ?? 12);
                $wStart = !empty($p['warranty']['start_date']) ? $p['warranty']['start_date'] : $date;
                $wExpiry = !empty($p['warranty']['expiry_date']) ? $p['warranty']['expiry_date'] : date('Y-m-d', strtotime("+$wMonths months", strtotime($wStart)));
                $wNotes = trim($p['warranty']['notes'] ?? '');

                // Determine warranty status
                $today = date('Y-m-d');
                $status = 'ACTIVE';
                if ($wExpiry < $today) {
                    $status = 'EXPIRED';
                } else {
                    $daysRemaining = (strtotime($wExpiry) - strtotime($today)) / 86400;
                    if ($daysRemaining <= 30) $status = 'EXPIRING_30D';
                    elseif ($daysRemaining <= 60) $status = 'EXPIRING_60D';
                    elseif ($daysRemaining <= 90) $status = 'EXPIRING_90D';
                }

                if (!empty($serials)) {
                    foreach ($serials as $sn) {
                        $sn = trim($sn);
                        if (empty($sn)) continue;
                        $insertAssetStmt->execute([
                            $invNum, $itemId, $customer, $pName,
                            $brand, $modelSku, $sn, $wType,
                            $wMonths, $wStart, $wExpiry,
                            $status, null, $wNotes
                        ]);
                        $hardwareCreated++;
                    }
                } elseif ($pType === 'HARDWARE') {
                    // Hardware record without captured serial (e.g. general unit or accessory)
                    $insertAssetStmt->execute([
                        $invNum, $itemId, $customer, $pName,
                        $brand, $modelSku, 'UNASSIGNED', $wType,
                        $wMonths, $wStart, $wExpiry,
                        $status, null, $wNotes
                    ]);
                    $hardwareCreated++;
                }

                // 2. If Software/SaaS Subscription or Maintenance Agreement (MA), persist into software_subscriptions
                if (in_array($pType, ['SOFTWARE_LICENSE', 'SAAS_SUBSCRIPTION', 'MAINTENANCE_AGREEMENT', 'SERVICE_AMC']) || !empty($p['subscription']['software_name'])) {
                    $sName = !empty($p['subscription']['software_name']) ? $p['subscription']['software_name'] : $pName;
                    $tier = $p['subscription']['edition_tier'] ?? ($pType === 'MAINTENANCE_AGREEMENT' ? 'Maintenance Agreement (MA)' : 'Standard');
                    $seats = intval($p['subscription']['license_seats'] ?? 1);
                    $subStart = !empty($p['subscription']['period_start_date']) ? $p['subscription']['period_start_date'] : $date;
                    $termMonths = intval($p['subscription']['term_months'] ?? 12);
                    $subEnd = !empty($p['subscription']['period_end_date']) ? $p['subscription']['period_end_date'] : date('Y-m-d', strtotime("+$termMonths months", strtotime($subStart)));
                    $oppVal = floatval($p['subscription']['renewal_opportunity_value'] ?? $totalAmt);

                    $renewalStatus = 'ACTIVE';
                    if ($subEnd < $today) $renewalStatus = 'EXPIRED';
                    elseif ((strtotime($subEnd) - strtotime($today)) / 86400 <= 60) $renewalStatus = 'DUE_SOON';

                    $insertSubStmt->execute([
                        $invNum, $itemId, $customer, $sName,
                        $tier, $seats, $subStart, $subEnd,
                        $termMonths, $renewalStatus, $oppVal
                    ]);
                    $subsCreated++;
                }
            }

            $this->db->commit();
            return [
                'items' => $itemsCreated,
                'hardware_assets' => $hardwareCreated,
                'subscriptions' => $subsCreated
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Audit log extraction
     */
    private function logExtraction($invNum, $promptTokens, $completionTokens, $status, $confidence, $rawResponse) {
        try {
            $this->db->execute("
                INSERT INTO ai_extraction_logs (
                    invoice_number, ai_provider, model_name, prompt_tokens,
                    completion_tokens, status, confidence_score, raw_response
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $invNum,
                $this->provider,
                $this->model,
                $promptTokens,
                $completionTokens,
                $status,
                $confidence,
                substr($rawResponse, 0, 4000)
            ]);
        } catch (Exception $e) {
            error_log("Failed to write AI log: " . $e->getMessage());
        }
    }
}
