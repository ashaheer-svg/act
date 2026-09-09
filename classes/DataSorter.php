<?php
/**
 * DataSorter - High-Performance Local Deterministic Classification & Sorting Engine
 *
 * Transforms raw QuickBooks invoice sales rows into:
 * 1. Cleaned Commercial Product Catalog (invoice_items)
 * 2. Discrete Hardware Asset Registry with Serial Numbers & Warranties (hardware_assets)
 * 3. Software Licenses & Maintenance Agreements (MA/AMC) (software_subscriptions)
 *
 * Operates deterministically with zero external API calls in < 2ms per invoice.
 */

declare(strict_types=1);

class DataSorter {
    private Database $db;
    private ?array $mappingRules = null;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Load product mapping rules from database (cached for execution)
     */
    public function getMappingRules(): array {
        if ($this->mappingRules === null) {
            try {
                $this->mappingRules = $this->db->fetchAll("
                    SELECT pattern, match_type, master_sku, canonical_name, brand, commercial_type, default_vat_treatment, priority
                    FROM product_mappings
                    WHERE pattern IS NOT NULL AND pattern != ''
                    ORDER BY priority ASC, id ASC
                ");
            } catch (Exception $e) {
                $this->mappingRules = [];
            }
        }
        return $this->mappingRules;
    }

    /**
     * Sort an invoice deterministically from its raw lines
     *
     * @param string $invoiceNumber
     * @return array Extracted structured entities
     */
    public function sortInvoice(string $invoiceNumber): array {
        $lines = $this->db->fetchAll("
            SELECT id, invoice_type, invoice_date, invoice_number, customer_name,
                   item_description, tax_code, quantity, total_amount, base_value,
                   vat_component, applied_tax_rate, vat_treatment, product_category
            FROM sales
            WHERE invoice_number = ?
            ORDER BY id ASC
        ", [$invoiceNumber]);

        if (empty($lines)) {
            throw new Exception("Invoice #$invoiceNumber not found.");
        }

        $invHeader = $lines[0];
        $invDate = $invHeader['invoice_date'];
        $customer = $invHeader['customer_name'];

        return $this->parseInvoiceLines($lines, $invNumber = $invoiceNumber, $customer, $invDate);
    }

    /**
     * Parse array of raw sales lines for an invoice
     */
    public function parseInvoiceLines(array $lines, string $invNum, string $customer, string $invDate): array {
        $products = [];
        $totalGross = 0.0;
        foreach ($lines as $l) {
            $totalGross += floatval($l['total_amount']);
        }

        // 1. Filter out QuickBooks "Item" placeholders and separate commercial items from zero-dollar metadata lines
        $cleanLines = [];
        foreach ($lines as $l) {
            $desc = trim($l['item_description'] ?? '');
            $amt = floatval($l['total_amount']);

            // Ignore meaningless QB placeholder rows
            if ($amt == 0 && (strcasecmp($desc, 'Item') === 0 || strcasecmp($desc, 'Opening balance') === 0 || empty($desc))) {
                continue;
            }

            // Ignore bank remit notes
            if ($amt == 0 && (stripos($desc, 'Please Remit to') !== false || stripos($desc, 'Account no') !== false || stripos($desc, 'Beneficiary') !== false)) {
                continue;
            }

            $cleanLines[] = $l;
        }

        // 2. Identify Commercial Line Items and their associated child metadata lines
        $commercialGroups = [];
        $currentGroup = null;

        foreach ($cleanLines as $l) {
            $amt = floatval($l['total_amount']);
            $desc = trim($l['item_description'] ?? '');
            $isParentLevyOrDiscount = $currentGroup !== null && preg_match('/(?:SSCL\s*[\d\.]*%|discount|rebate)/i', $currentGroup['parent']['item_description'] ?? '');

            if ($amt != 0.0 || $isParentLevyOrDiscount) {
                // Any non-zero line OR any line following a tax levy / discount is a distinct commercial group
                if ($currentGroup !== null) {
                    $commercialGroups[] = $currentGroup;
                }
                $currentGroup = [
                    'parent' => $l,
                    'child_lines' => [],
                    'levy_amount' => 0.0
                ];
            } else {
                // Zero-dollar metadata line (serials, warranties, notes, or bundled sub-items)
                if ($currentGroup !== null) {
                    $currentGroup['child_lines'][] = $l;
                } else {
                    // Preceding zero-dollar line before first commercial item (e.g. invoice header note)
                    $currentGroup = [
                        'parent' => $l,
                        'child_lines' => [],
                        'levy_amount' => 0.0
                    ];
                }
            }
        }
        if ($currentGroup !== null) {
            $commercialGroups[] = $currentGroup;
        }

        // 3. Process each commercial group
        foreach ($commercialGroups as $group) {
            $parent = $group['parent'];
            $children = $group['child_lines'];
            $parentDesc = trim($parent['item_description'] ?? '');
            $qty = max(1.0, floatval($parent['quantity']));
            $amt = floatval($parent['total_amount']);
            $rawLineIds = [$parent['id']];
            foreach ($children as $c) {
                $rawLineIds[] = $c['id'];
            }

            // Combine text from parent and children for pattern extraction
            $allText = $parentDesc;
            foreach ($children as $c) {
                $allText .= "\n" . trim($c['item_description'] ?? '');
            }

            // A. Classify Product Type
            $classification = $this->classifyProduct($parentDesc, $allText, $amt);

            // B. Extract Serial Numbers
            $serials = $this->extractSerials($parentDesc, $children, $allText);

            // C. Extract Warranty Details
            $warranty = $this->extractWarranty($allText, $invDate, $classification['type']);

            // D. Extract Subscription / Maintenance Agreement Details
            $subscription = null;
            if (in_array($classification['type'], ['SOFTWARE', 'MAINTENANCE'])) {
                $subscription = $this->extractSubscription($allText, $parentDesc, $invDate, $amt, $qty, $classification['type']);
            }

            // E. Clean Canonical Product Name
            $cleanName = !empty($classification['canonical_name']) 
                ? $classification['canonical_name'] 
                : $this->cleanProductName($parentDesc, $classification['brand']);

            $baseAmt = floatval($parent['base_value'] ?? 0);
            $vatAmt = floatval($parent['vat_component'] ?? 0);
            $totalAmt = floatval($parent['total_amount'] ?? 0);
            $vatTreat = $parent['vat_treatment'] ?? 'VAT_INCLUSIVE';

            $products[] = [
                'product_type' => $classification['type'],
                'product_name' => $cleanName,
                'brand' => $classification['brand'],
                'model_sku' => $classification['model_sku'],
                'quantity' => $qty,
                'unit_price' => $qty > 0 ? round(($baseAmt > 0 ? $baseAmt : $totalAmt) / $qty, 2) : ($baseAmt > 0 ? $baseAmt : $totalAmt),
                'base_value' => $baseAmt,
                'vat_component' => $vatAmt,
                'total_amount' => $totalAmt,
                'vat_treatment' => $vatTreat,
                'raw_line_ids' => $rawLineIds,
                'serials' => $serials,
                'warranty' => $warranty,
                'subscription' => $subscription
            ];
        }

        return [
            'invoice_number' => $invNum,
            'customer_name' => $customer,
            'invoice_date' => $invDate,
            'total_gross' => $totalGross,
            'products' => $products
        ];
    }

    /**
     * Classify product type, brand, and model
     */
    private function classifyProduct(string $parentDesc, string $allText, float $amount): array {
        $lower = strtolower($allText);
        $pLower = strtolower($parentDesc);

        // 0. Statutory Levy (SSCL)
        if (preg_match('/SSCL\s*[\d\.]*%/i', $pLower)) {
            return [
                'type' => 'TAX_LEVY',
                'brand' => 'Statutory Levy',
                'model_sku' => 'SSCL'
            ];
        }

        // 0. Commercial Discount
        if (preg_match('/(?:discount|rebate)/i', $pLower)) {
            return [
                'type' => 'DISCOUNT',
                'brand' => 'Commercial Adjustment',
                'model_sku' => 'DISCOUNT'
            ];
        }

        // 0.1 Check Custom Product Mapping Rules First (Highest Priority)
        $rules = $this->getMappingRules();
        foreach ($rules as $r) {
            $pattern = trim($r['pattern']);
            $matchType = strtoupper(trim($r['match_type'] ?? 'CONTAINS'));
            $commercialType = strtoupper(trim($r['commercial_type'] ?? 'OUTRIGHT_SALE'));
            $matched = false;

            if ($matchType === 'EXACT') {
                $matched = (strcasecmp($parentDesc, $pattern) === 0);
            } elseif ($matchType === 'REGEX') {
                $matched = @preg_match('/' . str_replace('/', '\/', $pattern) . '/i', $parentDesc)
                    || ($commercialType !== 'OUTRIGHT_SALE' && @preg_match('/' . str_replace('/', '\/', $pattern) . '/i', $allText));
            } else { // CONTAINS or Wildcard e.g. "DS3018xs*Rent*" or "DS3018xs%Rent%"
                if (strpos($pattern, '*') !== false || strpos($pattern, '%') !== false) {
                    $regex = str_replace(['\*', '\%'], ['.*', '.*'], preg_quote($pattern, '/'));
                    $matched = @preg_match('/' . $regex . '/i', $parentDesc)
                        || ($commercialType !== 'OUTRIGHT_SALE' && @preg_match('/' . $regex . '/i', $allText));
                } else {
                    $matched = (stripos($parentDesc, $pattern) !== false)
                        || ($commercialType !== 'OUTRIGHT_SALE' && stripos($allText, $pattern) !== false);
                }
            }

            if ($matched) {
                return [
                    'type' => $commercialType,
                    'brand' => !empty($r['brand']) ? trim($r['brand']) : $this->detectBrand($allText),
                    'model_sku' => !empty($r['master_sku']) ? trim($r['master_sku']) : $this->detectModelSku($parentDesc),
                    'canonical_name' => !empty($r['canonical_name']) ? trim($r['canonical_name']) : null
                ];
            }
        }

        // 0.2 Rental / Lease / Hardware-as-a-Service (Automated Heuristics)
        if (preg_match('/(?:rentable\s*charges?|rental|on\s*rent\b|\brent\b|\blease\b|equipment\s*hire)/i', $pLower)) {
            return [
                'type' => 'RENTAL',
                'brand' => $this->detectBrand($allText),
                'model_sku' => $this->detectModelSku($parentDesc)
            ];
        }

        // 1. Maintenance Agreement (MA)
        if (preg_match('/(?:maintenance\s+agreement|maintenance\s+contract|annual\s+service\s+agreement|extended\s+warranty\s+agreement|\bMA\b|hardware\s+maintenance)/i', $pLower)) {
            return [
                'type' => 'MAINTENANCE',
                'brand' => $this->detectBrand($allText),
                'model_sku' => 'MA'
            ];
        }

        // 2. Software / SaaS
        if (preg_match('/(?:acronis|eset|endpoint|antivirus|license|licence|subscription|synalyze|mailstore|office\s*365|microsoft|saas)/i', $pLower)) {
            return [
                'type' => 'SOFTWARE',
                'brand' => $this->detectBrand($allText),
                'model_sku' => $this->detectModelSku($parentDesc)
            ];
        }

        // 3. Hardware (NAS, HDD, Switches, RAM, Server, etc.)
        if (preg_match('/(?:synology|qnap|diskstation|rackstation|\bnas\b|hard\s*drive|hdd|ssd|seagate|ironwolf|barracuda|skyhawk|toshiba|western\s*digital|\bwd\b|bdcom|switch|draytek|router|vigor|innodisk|\bram\b|ecc|memory|transceiver|rail\s*kit)/i', $lower)) {
            return [
                'type' => 'HARDWARE',
                'brand' => $this->detectBrand($allText),
                'model_sku' => $this->detectModelSku($parentDesc)
            ];
        }

        // 4. Services (Configuration, Installation, Hosting, Support)
        if (preg_match('/(?:configuration|installation|support|troubleshooting|service\s*charge|delivery|labor|labour|remote\s*support|hosting|domain|storage\s*space|bandwidth|cpanel|ip\s*charge)/i', $pLower)) {
            return [
                'type' => 'SERVICE',
                'brand' => 'Active Solutions',
                'model_sku' => 'SERVICE'
            ];
        }

        // 5. Default Hardware / Accessory
        if (preg_match('/(?:cable|cord|adapter|patch|power)/i', $pLower)) {
            return [
                'type' => 'ACCESSORY',
                'brand' => $this->detectBrand($allText),
                'model_sku' => ''
            ];
        }

        return [
            'type' => ($amount > 0 ? 'HARDWARE' : 'SERVICE'),
            'brand' => $this->detectBrand($allText),
            'model_sku' => ''
        ];
    }

    /**
     * Detect Brand Name
     */
    private function detectBrand(string $text): string {
        $brands = [
            'Synology' => '/synology/i',
            'Seagate' => '/seagate|ironwolf|barracuda|skyhawk/i',
            'Toshiba' => '/toshiba/i',
            'Western Digital' => '/western\s*digital|\bwd\b/i',
            'BDCOM' => '/bdcom/i',
            'DrayTek' => '/draytek|vigor/i',
            'Acronis' => '/acronis/i',
            'ESET' => '/eset/i',
            'Innodisk' => '/innodisk/i',
            'Microsoft' => '/microsoft|office\s*365/i',
            'MailStore' => '/mailstore/i',
            'QNAP' => '/qnap/i',
            'Redstor' => '/redstor/i'
        ];

        foreach ($brands as $name => $pattern) {
            if (preg_match($pattern, $text)) {
                return $name;
            }
        }
        return 'Other';
    }

    /**
     * Detect Model / SKU from description
     */
    private function detectModelSku(string $desc): string {
        // Look for Synology models: DS923+, DS225+, RS1221RP+, etc.
        if (preg_match('/\b(DS\d{3,4}[a-z\+]*|RS\d{3,4}[a-z\+]*|FS\d{3,4}[a-z\+]*)\b/i', $desc, $m)) {
            return strtoupper($m[1]);
        }
        // Look for BDCOM models: S1500-8P2G, S1000-4P2F, etc.
        if (preg_match('/\b(S\d{4}-[\dA-Z]+)\b/i', $desc, $m)) {
            return strtoupper($m[1]);
        }
        // Look for DrayTek models: Vigor 2962, etc.
        if (preg_match('/\b(Vigor\s*\d{4}[A-Z]*)\b/i', $desc, $m)) {
            return $m[1];
        }
        // Look for HDD sizes: 4TB, 8TB, 16TB, etc.
        if (preg_match('/\b(\d+\s*(?:TB|GB))\b/i', $desc, $m)) {
            return strtoupper(str_replace(' ', '', $m[1]));
        }
        return '';
    }

    /**
     * Extract Serial Numbers from parent description and child lines
     */
    private function extractSerials(string $parentDesc, array $children, string $allText): array {
        $serials = [];

        // 1. Search in parent description (after S/N, SN, Serial, etc.)
        $serials = array_merge($serials, $this->parseSerialsFromText($parentDesc));

        // 2. Search in child lines
        foreach ($children as $c) {
            $desc = trim($c['item_description'] ?? '');
            if (empty($desc)) continue;

            // If line starts with or contains S/N
            if (preg_match('/(?:S\/N|SN|Serial)\s*[:\-\s]/i', $desc)) {
                $serials = array_merge($serials, $this->parseSerialsFromText($desc));
            } elseif (preg_match('/^[A-Z0-9\-\s\t,]+$/i', $desc) && !preg_match('/(?:warranty|expiry|license|item|year|month|unit|remit)/i', $desc)) {
                // Dedicated raw serial list line (e.g. ZA1A99LK, ZA1A9LMG...)
                $serials = array_merge($serials, $this->parseSerialsFromText($desc));
            }
        }

        // Clean, normalize and deduplicate serials
        $cleaned = [];
        foreach ($serials as $s) {
            $s = trim(strtoupper($s));
            // Filter invalid noise words
            if (strlen($s) >= 6 && strlen($s) <= 35 && !preg_match('/^(?:WARRANTY|SYNOLOGY|SEAGATE|DRIVE|INVOICE|DATES|MONTHS|YEARS|LICENSE|SWITCH|DESKTOP|ACTIVE|HARDWARE|PLEASE|ACCOUNT)$/i', $s)) {
                $cleaned[$s] = $s;
            }
        }

        return array_values($cleaned);
    }

    /**
     * Parse serial numbers from a text block
     */
    private function parseSerialsFromText(string $text): array {
        $found = [];

        // Pattern 1: Explicit "S/N : SERIAL1, SERIAL2 / SERIAL3..."
        if (preg_match('/(?:S\/N|SN|Serial(?: Number)?)\s*[:\-\s]\s*([^\r\n\/]+(?:\r?\n[^\r\n\/]+)*)/i', $text, $m)) {
            $rawChunk = $m[1];
            // Split by commas, tabs, newlines, or multiple spaces
            $parts = preg_split('/[\r\n\t,]+|\s{2,}/', $rawChunk);
            foreach ($parts as $p) {
                $p = trim($p);
                // Strip trailing notes or dates
                $p = preg_replace('/\s+.*$/', '', $p);
                if (preg_match('/^[A-Z0-9\-]{6,30}$/i', $p)) {
                    $found[] = $p;
                }
            }
        }

        // Pattern 2: Multi-line serial list without prefix (e.g. tab-separated hard drive serials)
        $lines = preg_split('/[\r\n]+/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            // Check if this line looks like one or more serial tokens
            if (preg_match_all('/\b([A-Z0-9]{8,25})\b/', $line, $matches)) {
                foreach ($matches[1] as $token) {
                    if (!preg_match('/^(?:SYNOLOGY|SEAGATE|TOSHIBA|DISKSTATION|RACKSTATION|IRONWOLF|BARRACUDA|WARRANTY|SUBSCRIPTION|AGREEMENT|MAINTENANCE)$/i', $token)) {
                        // Exclude model numbers e.g. RS1221RP, DS923, DS225, S1500-8P
                        if (preg_match('/^(?:DS\d{3,4}|RS\d{3,4}|FS\d{3,4}|S\d{4})/i', $token)) {
                            continue;
                        }
                        // Check if token has at least one digit and one letter
                        if (preg_match('/[0-9]/', $token) && preg_match('/[A-Z]/i', $token)) {
                            $found[] = $token;
                        }
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Extract Warranty details and calculate dates
     */
    private function extractWarranty(string $text, string $invDate, string $productType): array {
        $months = 12; // default
        $startDate = $invDate;
        $expiryDate = null;
        $clauseText = '';

        // 1. Check for explicit date ranges: e.g. "04th June 2021 to 03rd June 2022" or "(10/07/2026-09/07/2029)"
        if (preg_match('/(\d{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+\d{4})\s+(?:to|-)\s+(\d{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+\d{4})/i', $text, $m)) {
            $t1 = strtotime($m[1]);
            $t2 = strtotime($m[2]);
            if ($t1 && $t2) {
                $startDate = date('Y-m-d', $t1);
                $expiryDate = date('Y-m-d', $t2);
                $months = max(1, (int)round(($t2 - $t1) / (30.44 * 86400)));
                $clauseText = $m[0];
            }
        } elseif (preg_match('/\(?(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\s*(?:to|-)\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\)?/i', $text, $m)) {
            $d1 = str_replace('/', '-', $m[1]);
            $d2 = str_replace('/', '-', $m[2]);
            $t1 = strtotime($d1);
            $t2 = strtotime($d2);
            if ($t1 && $t2) {
                $startDate = date('Y-m-d', $t1);
                $expiryDate = date('Y-m-d', $t2);
                $months = max(1, (int)round(($t2 - $t1) / (30.44 * 86400)));
                $clauseText = $m[0];
            }
        } elseif (preg_match('/Expiry\s*[:\-]\s*(\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})/i', $text, $m)) {
            $expiryDate = date('Y-m-d', strtotime(str_replace('/', '-', $m[1])));
            $t1 = strtotime($startDate);
            $t2 = strtotime($expiryDate);
            if ($t2 > $t1) {
                $months = max(1, (int)round(($t2 - $t1) / (30.44 * 86400)));
            }
            $clauseText = $m[0];
        }

        // 2. Check for duration in years/months: e.g. "Warranty 03 Years", "Warranty 3 Years", "warranty 01 Year", "36 Months"
        if (!$expiryDate) {
            if (preg_match('/(?:warranty|agreement|guarantee)[^\d\n\r]*0?(\d+)\s*(?:Years?|Yrs?|Year)/i', $text, $m)) {
                $years = intval($m[1]);
                $months = $years * 12;
                $clauseText = $m[0];
            } elseif (preg_match('/0?(\d+)\s*(?:Years?|Yrs?|Year)\s*(?:on|warranty)/i', $text, $m)) {
                $years = intval($m[1]);
                $months = $years * 12;
                $clauseText = $m[0];
            } elseif (preg_match('/0?(\d+)\s*Months?\s*(?:warranty|agreement)/i', $text, $m)) {
                $months = intval($m[1]);
                $clauseText = $m[0];
            } elseif ($productType === 'HARDWARE') {
                // Standard default for Synology NAS / HDDs if unstated
                $months = 36;
                $clauseText = 'Standard Manufacturer Warranty (36M default)';
            }

            $expiryDate = date('Y-m-d', strtotime("+$months months", strtotime($startDate)));
        }

        return [
            'type' => ($months > 36 ? 'Extended' : 'Standard'),
            'duration_months' => $months,
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
            'notes' => trim($clauseText)
        ];
    }

    /**
     * Extract Subscription & Maintenance Agreement (MA) parameters
     */
    private function extractSubscription(string $allText, string $parentDesc, string $invDate, float $amt, float $qty, string $pType): array {
        $subStart = $invDate;
        $termMonths = 12;
        $subEnd = null;

        // Check for explicit date ranges
        if (preg_match('/(\d{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+\d{4})\s+(?:to|-)\s+(\d{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+\d{4})/i', $allText, $m)) {
            $t1 = strtotime($m[1]);
            $t2 = strtotime($m[2]);
            if ($t1 && $t2) {
                $subStart = date('Y-m-d', $t1);
                $subEnd = date('Y-m-d', $t2);
                $termMonths = max(1, (int)round(($t2 - $t1) / (30.44 * 86400)));
            }
        } elseif (preg_match('/\(?(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\s*(?:to|-)\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\)?/i', $allText, $m)) {
            $d1 = str_replace('/', '-', $m[1]);
            $d2 = str_replace('/', '-', $m[2]);
            $t1 = strtotime($d1);
            $t2 = strtotime($d2);
            if ($t1 && $t2) {
                $subStart = date('Y-m-d', $t1);
                $subEnd = date('Y-m-d', $t2);
                $termMonths = max(1, (int)round(($t2 - $t1) / (30.44 * 86400)));
            }
        }

        if (!$subEnd) {
            // Check term length
            if (preg_match('/(\d+)\s*(?:Years?|Year|Yrs?)/i', $allText, $m)) {
                $termMonths = intval($m[1]) * 12;
            } elseif (preg_match('/(\d+)\s*Months?/i', $allText, $m)) {
                $termMonths = intval($m[1]);
            }
            $subEnd = date('Y-m-d', strtotime("+$termMonths months", strtotime($subStart)));
        }

        // License Seats or Covered Units
        $seats = (int)$qty;
        if (preg_match('/(\d+)\s*(?:Seats?|Computers?|Users?|PCs?|Hosts?)/i', $allText, $m)) {
            $seats = intval($m[1]);
        }

        $softwareTitle = $parentDesc;
        if ($pType === 'MAINTENANCE') {
            $softwareTitle = 'Maintenance Agreement (' . $this->detectBrand($allText) . ')';
        }

        return [
            'software_name' => $softwareTitle,
            'edition_tier' => ($pType === 'MAINTENANCE' ? 'Maintenance Agreement (MA)' : 'Standard'),
            'license_seats' => max(1, $seats),
            'period_start_date' => $subStart,
            'period_end_date' => $subEnd,
            'term_months' => $termMonths,
            'renewal_opportunity_value' => $amt
        ];
    }

    /**
     * Clean Product Name by removing serials and noise
     */
    private function cleanProductName(string $rawDesc, string $brand): string {
        $clean = $rawDesc;

        // If it's a Maintenance Agreement, keep title intact
        if (preg_match('/(?:maintenance\s+agreement|maintenance\s+contract|annual\s+service\s+agreement|extended\s+warranty\s+agreement)/i', $clean, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]));
        }

        // Strip S/N clause (e.g. "\nS/N 2110..." or "/ S/N : ...")
        $clean = preg_replace('/(?:[\r\n\/]+\s*)?(?:S\/N|SN|Serial)\s*[:\-\s].*$/is', '', $clean);

        // Strip warranty clause if embedded in parenthesis e.g. "(warranty 01 Year)"
        $clean = preg_replace('/\((?:warranty|agreement)[^\)]*\)/is', '', $clean);

        // Strip multiple newlines/tabs
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        if (empty($clean) || strlen($clean) < 3) {
            $clean = "$brand Product";
        }
        return $clean;
    }

    /**
     * Persist sorted entities into operational registries
     */
    public function persistSortedData(array $sorted): array {
        $invNum = $sorted['invoice_number'];
        $customer = $sorted['customer_name'];
        $date = $sorted['invoice_date'];
        $products = $sorted['products'];

        $this->db->beginTransaction();
        try {
            // Delete existing records for this invoice
            $oldItems = $this->db->fetchAll("SELECT id FROM invoice_items WHERE invoice_number = ?", [$invNum]);
            foreach ($oldItems as $oi) {
                $this->db->execute("DELETE FROM hardware_assets WHERE invoice_item_id = ?", [$oi['id']]);
                $this->db->execute("DELETE FROM software_subscriptions WHERE invoice_item_id = ?", [$oi['id']]);
            }
            $this->db->execute("DELETE FROM invoice_items WHERE invoice_number = ?", [$invNum]);

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
                    warranty_status, parent_serial_number, notes, is_rental
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insertSubStmt = $this->db->getConnection()->prepare("
                INSERT INTO software_subscriptions (
                    invoice_number, invoice_item_id, customer_name, software_name,
                    edition_tier, license_seats, period_start_date, period_end_date,
                    term_months, renewal_status, renewal_opportunity_value
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $itemsCreated = 0;
            $hardwareCreated = 0;
            $subsCreated = 0;
            $today = date('Y-m-d');

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
                $pType = $p['product_type'];
                $pName = $p['product_name'];
                $brand = $p['brand'];
                $modelSku = $p['model_sku'];
                $qty = $p['quantity'];
                $unitPrice = $p['unit_price'];
                $baseVal = isset($p['base_value']) ? floatval($p['base_value']) : 0.0;
                $vatVal = isset($p['vat_component']) ? floatval($p['vat_component']) : 0.0;
                $finalLineTotal = isset($p['total_amount']) ? floatval($p['total_amount']) : 0.0;
                $treatment = $p['vat_treatment'] ?? ($isVatRegistered == 1 ? 'PLUS_VAT' : 'VAT_INCLUSIVE');

                // Fallback only if product did not carry pre-computed financial fields
                if ($baseVal == 0 && $vatVal == 0 && $finalLineTotal > 0) {
                    $taxRule = $this->db->getTaxRuleForInvoice($invNum, $date);
                    $rate = $taxRule['rate'];
                    if ($rate <= 0) {
                        $treatment = 'VAT_EXEMPT';
                        $baseVal = $finalLineTotal;
                        $vatVal = 0.00;
                    } elseif ($isVatRegistered == 1) {
                        $treatment = 'PLUS_VAT';
                        $baseVal = $finalLineTotal;
                        $vatVal = round($finalLineTotal * $rate, 2);
                        $finalLineTotal = round($baseVal + $vatVal, 2);
                    } else {
                        $treatment = 'VAT_INCLUSIVE';
                        $baseVal = round($finalLineTotal / (1 + $rate), 2);
                        $vatVal = round($finalLineTotal - $baseVal, 2);
                    }
                }

                $rawLineIds = json_encode($p['raw_line_ids']);
                $confidence = 95; // Deterministic high confidence

                $insertItemStmt->execute([
                    $invNum, $customer, $date, $pType,
                    $pName, $brand, $qty, $unitPrice,
                    $baseVal, $vatVal, $finalLineTotal, $rawLineIds,
                    $confidence, $treatment
                ]);
                $itemId = (int)$this->db->lastInsertId();
                $itemsCreated++;

                // 1. Hardware Assets persistence (with multi-unit serial splitting and Rental fleet tagging)
                if (!in_array($pType, ['TAX_LEVY', 'DISCOUNT']) && ($pType === 'HARDWARE' || $pType === 'RENTAL' || !empty($p['serials']))) {
                    $isRental = ($pType === 'RENTAL') ? 1 : 0;
                    $serials = $p['serials'];
                    $wMonths = $p['warranty']['duration_months'] ?? ($isRental ? 1 : 12);
                    $wStart = $p['warranty']['start_date'] ?? $date;
                    $wExpiry = $p['warranty']['expiry_date'] ?? date('Y-m-d', strtotime("+$wMonths months", strtotime($wStart)));
                    $wNotes = $p['warranty']['notes'] ?? '';
                    if ($isRental && empty($wNotes)) {
                        $wNotes = "Rental Deployment - " . $pName;
                    }
                    $wType = $p['warranty']['type'] ?? ($isRental ? 'Rental/Lease' : 'Standard');

                    $status = 'ACTIVE';
                    if ($wExpiry < $today) {
                        $status = 'EXPIRED';
                    } else {
                        $days = (strtotime($wExpiry) - strtotime($today)) / 86400;
                        if ($days <= 30) $status = 'EXPIRING_30D';
                        elseif ($days <= 60) $status = 'EXPIRING_60D';
                        elseif ($days <= 90) $status = 'EXPIRING_90D';
                    }

                    if (!empty($serials)) {
                        foreach ($serials as $sn) {
                            $insertAssetStmt->execute([
                                $invNum, $itemId, $customer, $pName,
                                $brand, $modelSku, $sn, $wType,
                                $wMonths, $wStart, $wExpiry,
                                $status, null, $wNotes, $isRental
                            ]);
                            $hardwareCreated++;
                        }
                    } elseif ($pType === 'HARDWARE' || $pType === 'RENTAL') {
                        // Single or multi unit without serials
                        for ($u = 1; $u <= min(10, (int)$qty); $u++) {
                            $insertAssetStmt->execute([
                                $invNum, $itemId, $customer, $pName,
                                $brand, $modelSku, 'UNASSIGNED', $wType,
                                $wMonths, $wStart, $wExpiry,
                                $status, null, $wNotes, $isRental
                            ]);
                            $hardwareCreated++;
                        }
                    }
                }

                // 2. Software Subscriptions & Maintenance Agreements (MA)
                if ($p['subscription'] !== null) {
                    $sub = $p['subscription'];
                    $subEnd = $sub['period_end_date'];
                    $subStatus = 'ACTIVE';
                    if ($subEnd < $today) $subStatus = 'EXPIRED';
                    elseif ((strtotime($subEnd) - strtotime($today)) / 86400 <= 60) $subStatus = 'DUE_SOON';

                    $insertSubStmt->execute([
                        $invNum, $itemId, $customer, $sub['software_name'],
                        $sub['edition_tier'], $sub['license_seats'], $sub['period_start_date'],
                        $subEnd, $sub['term_months'], $subStatus, $sub['renewal_opportunity_value']
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
}
