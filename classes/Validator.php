<?php
/**
 * Validator Class - Data Validation & Business Rules
 *
 * Validates imported data and enforces business rules
 */

class Validator {
    public static $errors = [];

    /**
     * Validate sales record
     */
    public static function validateSalesRecord($record) {
        self::$errors = [];

        // Required fields
        if (empty($record['Amount'])) {
            self::$errors[] = 'Amount is required';
        }

        if (empty($record['Sales Tax Code'])) {
            self::$errors[] = 'Sales Tax Code is required';
        }

        if (empty($record['Date'])) {
            self::$errors[] = 'Date is required';
        } else if (!self::isValidDate($record['Date'])) {
            self::$errors[] = 'Invalid date format: ' . $record['Date'];
        }

        // Validate amount
        if (!empty($record['Amount'])) {
            if (!is_numeric($record['Amount'])) {
                self::$errors[] = 'Amount must be a number';
            } elseif ($record['Amount'] < 0) {
                self::$errors[] = 'Amount cannot be negative';
            } elseif ($record['Amount'] > 999999999) {
                self::$errors[] = 'Amount exceeds maximum limit';
            }
        }

        // Validate tax code
        if (!empty($record['Sales Tax Code'])) {
            $validCodes = ['Taxable Sales', 'Non-Taxable Sales'];
            if (!in_array($record['Sales Tax Code'], $validCodes)) {
                self::$errors[] = 'Invalid Tax Code. Must be: ' . implode(' or ', $validCodes);
            }
        }

        // Validate quantity
        if (!empty($record['Qty'])) {
            if (!is_numeric($record['Qty']) || $record['Qty'] < 0) {
                self::$errors[] = 'Quantity must be a positive number';
            }
        }

        return empty(self::$errors);
    }

    /**
     * Validate user input for security
     */
    public static function sanitizeInput($input, $type = 'text') {
        if ($input === null) {
            return null;
        }

        // Convert to string
        $input = (string)$input;

        // Trim whitespace
        $input = trim($input);

        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : null;

            case 'number':
                return is_numeric($input) ? $input : null;

            case 'date':
                return self::isValidDate($input) ? $input : null;

            case 'text':
                // Remove potentially harmful characters
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

            case 'username':
                // Allow only alphanumeric, underscore, hyphen
                return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $input) ? $input : null;

            case 'filename':
                // Remove path traversal attempts
                $input = basename($input);
                return preg_match('/^[a-zA-Z0-9._-]+$/', $input) ? $input : null;

            default:
                return $input;
        }
    }

    /**
     * Validate date format
     */
    public static function isValidDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain lowercase letters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain uppercase letters';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain numbers';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get validation errors
     */
    public static function getErrors() {
        return self::$errors;
    }

    /**
     * Clear validation errors
     */
    public static function clearErrors() {
        self::$errors = [];
    }

    /**
     * Validate file upload
     */
    public static function validateFileUpload($file) {
        $errors = [];

        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            $errors[] = 'No file uploaded';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size (max 5MB)
        if ($file['size'] > 5242880) {
            $errors[] = 'File size exceeds 5MB limit';
        }

        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['xlsx', 'xls', 'csv'];
        if (!in_array($ext, $allowedExts)) {
            $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedExts);
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'text/csv',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Invalid file format (MIME type check failed)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate customer name
     */
    public static function validateCustomerName($name) {
        if (empty($name)) {
            return ['valid' => false, 'message' => 'Customer name is required'];
        }

        if (strlen($name) > 255) {
            return ['valid' => false, 'message' => 'Customer name too long'];
        }

        return ['valid' => true];
    }

    /**
     * Validate VAT rate
     */
    public static function validateVATRate($rate) {
        if (!is_numeric($rate)) {
            return ['valid' => false, 'message' => 'VAT rate must be a number'];
        }

        $rate = floatval($rate);
        if ($rate < 0 || $rate > 1) {
            return ['valid' => false, 'message' => 'VAT rate must be between 0 and 1'];
        }

        return ['valid' => true, 'rate' => $rate];
    }

    /**
     * Get validation error message for display
     */
    public static function getErrorMessage() {
        if (empty(self::$errors)) {
            return '';
        }

        return implode('<br>', self::$errors);
    }
}
?>
