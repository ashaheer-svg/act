<?php
/**
 * DataImporter Class - Handle file uploads and data import
 *
 * Reads Excel/CSV files, validates data, applies VAT calculations, and imports to database
 */

class DataImporter {
    private $db;
    private $userId;
    private $vatRate;
    private $currency;

    public function __construct(Database $db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
        
        // Load dynamic settings
        $this->vatRate = floatval($this->db->getSetting('vat_rate', '0.18'));
        $this->currency = $this->db->getSetting('currency_symbol', 'LKR ');
    }

    /**
     * Process uploaded file
     */
    public function processUpload($file) {
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return $validation;
        }

        // Read file
        $data = $this->readFile($file['tmp_name'], $file['name']);
        if (!$data['success']) {
            return $data;
        }

        // Import data
        return $this->importData($data['records'], $file['name']);
    }

    /**
     * Process Ledger Upload (QuickBooks Style Grouped CSV)
     */
    public function processLedgerUpload($file) {
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return $validation;
        }

        return $this->importLedgerData($file['tmp_name'], $file['name']);
    }

    /**
     * Specialized parser for Grouped Ledger CSV
     */
    private function importLedgerData($filePath, $fileName) {
        $imported = 0;
        $settled = 0;
        $currentCustomer = '';
        $pendingInvoices = []; // Buffer for current customer's invoices
        
        // Default indices in case header detection fails
        $idx = [
            'type' => 4,
            'date' => 6,
            'num' => 8,
            'amount' => 28
        ];

        try {
            $this->db->beginTransaction();
            $this->db->clearPayments();

            if (($handle = fopen($filePath, "r")) !== FALSE) {
                $rowNum = 0;
                while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                    $rowNum++;

                    // Try to detect indices from header row (usually contains 'Type' and 'Amount')
                    if ($rowNum === 1 || in_array('Type', $data)) {
                        foreach ($data as $i => $val) {
                            $val = trim($val);
                            if ($val === 'Type') $idx['type'] = $i;
                            if ($val === 'Date') $idx['date'] = $i;
                            if ($val === 'Num') $idx['num'] = $i;
                            if ($val === 'Amount') $idx['amount'] = $i;
                        }
                        if ($rowNum === 1) continue;
                    }

                    $customerHeader = trim($data[1] ?? '');
                    $type = trim($data[$idx['type']] ?? '');
                    
                    // Identify Customer Header Row (Name in col 1, Type empty)
                    if (!empty($customerHeader) && empty($type) && strpos($customerHeader, 'Total ') === false) {
                        $currentCustomer = $customerHeader;
                        $lastInvoice = null;
                        continue;
                    }

                    if (empty($currentCustomer)) continue;

                    $dateStr = trim($data[$idx['date']] ?? '');
                    $num = trim($data[$idx['num']] ?? '');
                    $amountStr = trim($data[$idx['amount']] ?? '0');
                    
                    // Robust numerical cleaning
                    $amount = abs(floatval(str_replace(['"', ',', ' '], '', $amountStr)));

                    if (strcasecmp($type, 'Invoice') === 0) {
                        $type = 'Invoice'; // Standardize
                        // Store in pending buffer for this customer
                        if (!isset($pendingInvoices[$currentCustomer])) {
                            $pendingInvoices[$currentCustomer] = [];
                        }
                        $pendingInvoices[$currentCustomer][] = [
                            'num' => $num,
                            'date' => $dateStr,
                            'amount' => $amount
                        ];
                    } else if (strcasecmp($type, 'Payment') === 0 || strcasecmp($type, 'Credit Memo') === 0) {
                        $type = strcasecmp($type, 'Payment') === 0 ? 'Payment' : 'Credit Memo'; // Standardize
                        if ($amount > 0) {
                            $this->db->addPayment($currentCustomer, $dateStr, $num, $amount);
                            $imported++;

                        // Try to match against pending invoices for this customer
                        if (!empty($pendingInvoices[$currentCustomer])) {
                            $matched = false;
                            
                            // 1. Try match by Invoice Number (common for Credit Memos)
                            foreach ($pendingInvoices[$currentCustomer] as $idx_p => $pInv) {
                                if (!empty($num) && !empty($pInv['num']) && strcasecmp($pInv['num'], $num) === 0) {
                                    $this->settleInvoice($pInv, $dateStr, $currentCustomer);
                                    unset($pendingInvoices[$currentCustomer][$idx_p]);
                                    $settled++;
                                    $matched = true;
                                    break;
                                }
                            }

                            // 2. Try exact 1:1 match by Amount
                            if (!$matched) {
                                foreach ($pendingInvoices[$currentCustomer] as $idx_p => $pInv) {
                                    if (abs($pInv['amount'] - $amount) < 1.0) {
                                        $this->settleInvoice($pInv, $dateStr, $currentCustomer);
                                        unset($pendingInvoices[$currentCustomer][$idx_p]);
                                        $settled++;
                                        $matched = true;
                                        break;
                                    }
                                }
                            }

                            // 3. Try matching against the SUM of all pending
                            if (!$matched) {
                                $sumPending = 0;
                                foreach ($pendingInvoices[$currentCustomer] as $pInv) $sumPending += $pInv['amount'];
                                
                                if (abs($sumPending - $amount) < 2.0) {
                                    foreach ($pendingInvoices[$currentCustomer] as $pInv) {
                                        $this->settleInvoice($pInv, $dateStr, $currentCustomer);
                                        $settled++;
                                    }
                                    $pendingInvoices[$currentCustomer] = [];
                                    $matched = true;
                                }
                            }
                        }
                    }
                }
            }
            fclose($handle);
        }

            $this->logImport($fileName, $imported, 0);
            $this->db->commit();

            return [
                'success' => true,
                'message' => "Ledger import complete: $imported payments recorded, $settled invoices settled.",
                'imported' => $imported,
                'settled' => $settled
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Ledger import error: ' . $e->getMessage()
            ];
        }
    }

    private function settleInvoice($inv, $payDateStr, $customer) {
        $invDate = strtotime($this->formatDate($inv['date']));
        $payDate = strtotime($this->formatDate($payDateStr));
        
        if ($invDate && $payDate) {
            $diff = round(($payDate - $invDate) / (60 * 60 * 24));
            if ($diff < 0) $diff = 0;

            $sql = "UPDATE sales SET paid_date = ?, days_to_pay = ? 
                    WHERE invoice_number = ? AND customer_name = ?";
            $this->db->execute($sql, [
                $this->formatDate($payDateStr),
                $diff,
                $inv['num'],
                $customer
            ]);
            return true;
        }
        return false;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check if file exists
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'No file uploaded'];
        }

        // Check file size
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            return ['valid' => false, 'message' => 'File size exceeds maximum limit'];
        }

        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            return ['valid' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)];
        }

        return ['valid' => true];
    }

    /**
     * Read Excel/CSV file
     */
    private function readFile($filePath, $fileName) {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        try {
            if ($ext === 'csv') {
                return $this->readCSV($filePath);
            } else {
                return $this->readExcel($filePath);
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error reading file: ' . $e->getMessage()];
        }
    }

    /**
     * Read CSV file
     */
    private function readCSV($filePath) {
        $records = [];
        $row = 0;

        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            $headers = null;

            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if ($row == 0) {
                    $headers = $data;
                } else {
                    // Defensive check: ensure data matches header count
                    if (count($headers) === count($data)) {
                        $record = array_combine($headers, $data);
                        $records[] = $record;
                    }
                }
                $row++;
            }
            fclose($handle);
        }

        return ['success' => true, 'records' => $records];
    }

    /**
     * Read Excel file
     */
    private function readExcel($filePath) {
        // Use our standalone SimpleXLSX library
        require_once __DIR__ . '/SimpleXLSX.php';

        try {
            $xlsx = \Shuchkin\SimpleXLSX::parse($filePath);
            if (!$xlsx) {
                return ['success' => false, 'message' => 'Error parsing Excel: ' . \Shuchkin\SimpleXLSX::parseError()];
            }

            $rows = $xlsx->rows();
            $records = [];
            $headers = null;

            foreach ($rows as $data) {
                if ($headers === null) {
                    $headers = $data;
                } else {
                    // Defensive check: ensure data matches header count
                    if (count($headers) === count($data)) {
                        $record = array_combine($headers, $data);
                        $records[] = $record;
                    }
                }
            }

            return ['success' => true, 'records' => $records];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error reading Excel: ' . $e->getMessage()];
        }
    }

    /**
     * Import records to database with VAT calculation
     */
    private function importData($records, $fileName) {
        $imported = 0;
        $skipped = 0;
        $details = [
            'duplicates' => 0,
            'missing_fields' => 0,
            'duplicate_sets' => [],
            'skipped_rows' => []
        ];
        $errors = [];
        $rowIndex = 1; // Start after header row

        try {
            $this->db->beginTransaction();

            foreach ($records as $record) {
                $rowIndex++;

                // Skip if required fields missing
                if (empty($record['Amount']) || empty($record['Sales Tax Code'])) {
                    $skipped++;
                    $details['missing_fields']++;
                    $details['skipped_rows'][] = [
                        'row' => $rowIndex,
                        'num' => $record['Num'] ?? 'N/A',
                        'name' => $record['Name'] ?? 'N/A',
                        'item' => $record['Item'] ?? 'N/A',
                        'amount' => $record['Amount'] ?? 0,
                        'reason' => 'Missing required fields (Amount or Tax Code)'
                    ];
                    continue;
                }

                $rowIndex++;
                
                // Check if record already exists (Smarter check: include item and amount)
                $cleanAmount = floatval(str_replace(',', '', $record['Amount'] ?? 0));
                $existingRecord = $this->db->fetch(
                    "SELECT * FROM sales WHERE invoice_number = ? AND customer_name = ? AND item_description = ? AND qb_amount = ?",
                    [
                        $record['Num'] ?? '', 
                        $record['Name'] ?? '', 
                        $record['Item'] ?? '', 
                        $cleanAmount
                    ]
                );

                if ($existingRecord) {
                    $skipped++;
                    $details['duplicates']++;
                    $details['skipped_rows'][] = [
                        'row' => $rowIndex,
                        'num' => $record['Num'] ?? 'N/A',
                        'name' => $record['Name'] ?? 'N/A',
                        'item' => $record['Item'] ?? 'N/A',
                        'amount' => $record['Amount'] ?? 0,
                        'reason' => 'Duplicate record (same Invoice, Customer, Item, and Amount)'
                    ];
                    
                    // Capture duplicate set for auditing
                    $details['duplicate_sets'][] = [
                        'row' => $rowIndex,
                        'duplicate' => [
                            'num' => $record['Num'] ?? 'N/A',
                            'name' => $record['Name'] ?? 'N/A',
                            'item' => $record['Item'] ?? 'N/A',
                            'amount' => $record['Amount'] ?? 0
                        ],
                        'original' => [
                            'num' => $existingRecord['invoice_number'],
                            'name' => $existingRecord['customer_name'],
                            'item' => $existingRecord['item_description'],
                            'amount' => $existingRecord['qb_amount'],
                            'imported_at' => $existingRecord['imported_at']
                        ]
                    ];
                    continue;
                }

                // Calculate VAT values using dynamic sequence rules
                $invoiceDate = $this->formatDate($record['Date'] ?? '');
                $amount = floatval(str_replace(',', '', $record['Amount'] ?? 0));
                $invNum = trim($record['Num'] ?? '');
                $taxCode = trim($record['Sales Tax Code'] ?? '');
                $itemDesc = $record['Item'] ?? '';
                
                $rule = $this->db->getTaxRuleForInvoice($invNum, $invoiceDate);
                $rate = $rule['rate'];

                if ($rate <= 0 || $amount == 0) {
                    $base = $amount;
                    $vat = 0.00;
                    $total = $amount;
                    $appliedRate = 0.00;
                    $vatTreatment = 'VAT_EXEMPT';
                } else {
                    $isVatLine = (bool)preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $itemDesc);
                    if ($isVatLine) {
                        $base = 0.00;
                        $vat = $amount;
                        $total = $amount;
                        $appliedRate = $rate;
                        $vatTreatment = 'VAT_EXCLUSIVE_BREAKUP';
                    } else {
                        $base = round($amount / (1 + $rate), 2);
                        $vat = round($amount - $base, 2);
                        $total = $amount;
                        $appliedRate = $rate;
                        $vatTreatment = 'VAT_INCLUSIVE';
                    }
                }

                // Rationalization: Resolve category using mappings if source is empty
                $category = $record['Product Category'] ?? '';
                
                if (empty($category) || $category === $itemDesc) {
                    $mapping = $this->db->fetch("SELECT product_category FROM product_mappings WHERE item_description = ?", [$itemDesc]);
                    if ($mapping) {
                        $category = $mapping['product_category'];
                    } else if (empty($category)) {
                        $category = $itemDesc; // Fallback to item name if no mapping
                    }
                }

                // Insert record
                $stmt = $this->db->execute(
                    "INSERT INTO sales (
                        invoice_type, invoice_date, invoice_number, customer_name,
                        item_description, tax_code, quantity, qb_amount,
                        base_value, vat_component, applied_tax_rate, total_amount, 
                        product_category, sales_rep_code, vat_treatment
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $record['Type'] ?? 'Invoice',
                        $invoiceDate,
                        $invNum,
                        $record['Name'] ?? '',
                        $itemDesc,
                        $taxCode,
                        floatval(str_replace(',', '', $record['Qty'] ?? 1)),
                        $amount,
                        $base,
                        $vat,
                        $appliedRate,
                        $total,
                        $category,
                        $this->findRepCode($record),
                        $vatTreatment
                    ]
                );

                $imported++;
            }

            // Log import
            $this->logImport($fileName, $imported, $skipped);
            
            // NEW: Sync new customers to CRM profiles
            $this->db->syncCustomerProfiles();
            
            $this->db->commit();

            return [
                'success' => true,
                'message' => "Import complete: $imported records added. " . ($skipped > 0 ? "$skipped duplicates were identified and safely skipped." : "No duplicates found."),
                'imported' => $imported,
                'skipped' => $skipped,
                'details' => $details,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Import error: ' . $e->getMessage(),
                'imported' => $imported,
                'skipped' => $skipped
            ];
        }
    }

    /**
     * Normalize date to Y-m-d format
     */
    private function formatDate($dateStr) {
        if (empty($dateStr)) return date('Y-m-d');
        
        $timestamp = strtotime($dateStr);
        if ($timestamp === false) {
            // Handle Excel numeric date if necessary (though SimpleXLSX usually handles this)
            return date('Y-m-d');
        }
        
        return date('Y-m-d', $timestamp);
    }

    /**
     * Calculate VAT based on tax code
     * Taxable Sales = exclusive (add VAT)
     * Non-Taxable Sales = inclusive (extract VAT)
     */
    private function calculateVAT($amount, $taxCode, $rate = null) {
        $rate = ($rate !== null) ? $rate : $this->vatRate;

        if ($taxCode === 'Taxable Sales') {
            // Exclusive: amount is base value, add VAT on top
            $base = $amount;
            $vat = $base * $rate;
            $total = $base + $vat;
        } elseif ($taxCode === 'Non-Taxable Sales') {
            // Inclusive: amount is TOTAL (includes VAT), extract base
            $total = $amount;
            $base = $total / (1 + $rate);
            $vat = $total - $base;
        } else {
            // Default: treat as exclusive (standard for QB)
            $base = $amount;
            $vat = $base * $rate;
            $total = $base + $vat;
        }

        return [
            'base' => round($base, 2),
            'vat' => round($vat, 2),
            'total' => round($total, 2)
        ];
    }

    /**
     * Categorize product based on item description
     */
    private function categorizeProduct($item) {
        $item_lower = strtolower($item);

        if (strpos($item_lower, 'synology') !== false || strpos($item_lower, 'nas') !== false) {
            return 'Synology/NAS Storage';
        } elseif (strpos($item_lower, 'bdcom') !== false || strpos($item_lower, 'switch') !== false) {
            return 'Network Equipment';
        } elseif (strpos($item_lower, 'hard drive') !== false || strpos($item_lower, 'hdd') !== false) {
            return 'Storage Drives';
        } elseif (strpos($item_lower, 'service') !== false || strpos($item_lower, 'support') !== false) {
            return 'Services & Support';
        } elseif (strpos($item_lower, 'acronis') !== false) {
            return 'Software Licenses';
        } elseif (strpos($item_lower, 'hosting') !== false) {
            return 'Hosting Services';
        } else {
            return 'Other';
        }
    }

    /**
     * Log import action
     */
    private function logImport($fileName, $imported, $skipped) {
        $this->db->execute(
            "INSERT INTO import_logs (filename, records_imported, records_skipped, imported_by)
             VALUES (?, ?, ?, ?)",
            [$fileName, $imported, $skipped, $this->userId]
        );
    }

    /**
     * Get import history
     */
    public function getImportHistory() {
        return $this->db->fetchAll(
            "SELECT l.*, u.username FROM import_logs l
             LEFT JOIN users u ON l.imported_by = u.id
             ORDER BY l.import_date DESC LIMIT 50"
        );
    }
    /**
     * Find Sales Rep code using common header variations
     */
    private function findRepCode($record) {
        $possibleKeys = ['Rep', 'Sales Rep', 'Representative', 'Assigned To', 'Sales Person'];
        foreach ($possibleKeys as $key) {
            if (isset($record[$key]) && !empty(trim($record[$key]))) {
                return trim($record[$key]);
            }
        }
        return '';
    }
}
?>
