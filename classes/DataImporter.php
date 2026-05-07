<?php
/**
 * DataImporter Class - Handle file uploads and data import
 *
 * Reads Excel/CSV files, validates data, applies VAT calculations, and imports to database
 */

class DataImporter {
    private $db;
    private $userId;

    public function __construct(Database $db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
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
            'missing_fields' => 0,
            'duplicates' => 0,
            'other' => 0
        ];
        $errors = [];

        try {
            $this->db->beginTransaction();

            foreach ($records as $record) {
                // Skip if required fields missing
                // Skip if required fields missing
                if (empty($record['Amount']) || empty($record['Sales Tax Code'])) {
                    $skipped++;
                    $details['missing_fields']++;
                    continue;
                }

                // Check if record already exists (Smarter check: include item and amount)
                $existingRecord = $this->db->fetch(
                    "SELECT id FROM sales WHERE invoice_number = ? AND customer_name = ? AND item_description = ? AND qb_amount = ?",
                    [
                        $record['Num'] ?? '', 
                        $record['Name'] ?? '', 
                        $record['Item'] ?? '', 
                        floatval($record['Amount'] ?? 0)
                    ]
                );

                if ($existingRecord) {
                    $skipped++;
                    $details['duplicates']++;
                    continue;
                }

                // Calculate VAT values
                $amount = floatval($record['Amount']);
                $taxCode = trim($record['Sales Tax Code']);

                $calcResult = $this->calculateVAT($amount, $taxCode);

                // Insert record
                $stmt = $this->db->execute(
                    "INSERT INTO sales (
                        invoice_type, invoice_date, invoice_number, customer_name,
                        item_description, tax_code, quantity, qb_amount,
                        base_value, vat_component, total_amount, product_category
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $record['Type'] ?? 'Invoice',
                        $record['Date'] ?? date('Y-m-d'),
                        $record['Num'] ?? '',
                        $record['Name'] ?? '',
                        $record['Item'] ?? '',
                        $taxCode,
                        floatval($record['Qty'] ?? 1),
                        $amount,
                        $calcResult['base'],
                        $calcResult['vat'],
                        $calcResult['total'],
                        $this->categorizeProduct($record['Item'] ?? '')
                    ]
                );

                $imported++;
            }

            // Log import
            $this->logImport($fileName, $imported, $skipped);
            $this->db->commit();

            return [
                'success' => true,
                'message' => "Import complete: $imported records imported, $skipped skipped",
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
     * Calculate VAT based on tax code
     * Taxable Sales = exclusive (add VAT)
     * Non-Taxable Sales = inclusive (extract VAT)
     */
    private function calculateVAT($amount, $taxCode) {
        if ($taxCode === 'Taxable Sales') {
            // Exclusive: amount is base value
            $base = $amount;
            $vat = $base * VAT_RATE;
            $total = $base + $vat;
        } elseif ($taxCode === 'Non-Taxable Sales') {
            // Inclusive: amount is total
            $total = $amount;
            $base = $total / (1 + VAT_RATE);
            $vat = $total - $base;
        } else {
            // Default: treat as exclusive
            $base = $amount;
            $vat = $base * VAT_RATE;
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
}
?>
