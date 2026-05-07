<?php
/**
 * Backup Class - Database Backup & Restore
 *
 * Handles backup and restore of SQLite database
 */

class Backup {
    private $db;
    private $backupDir;

    public function __construct(Database $db, $backupDir = null) {
        $this->db = $db;
        $this->backupDir = $backupDir ?? DATA_DIR . '/backups';

        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Create database backup
     */
    public function backup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = 'backup_' . $timestamp . '.db';
            $backupPath = $this->backupDir . '/' . $filename;

            // Use SQLite backup API
            $sourceDb = new PDO('sqlite:' . DATABASE_PATH);
            $backupDb = new PDO('sqlite:' . $backupPath);

            // Copy database
            $sourceDb->exec('VACUUM INTO "' . $backupPath . '"');

            // Verify backup
            if (!file_exists($backupPath)) {
                return ['success' => false, 'message' => 'Backup creation failed'];
            }

            $filesize = filesize($backupPath) / 1024 / 1024; // Convert to MB

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'filename' => $filename,
                'path' => $backupPath,
                'size' => round($filesize, 2) . ' MB',
                'timestamp' => $timestamp
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Backup error: ' . $e->getMessage()];
        }
    }

    /**
     * Get list of backups
     */
    public function listBackups() {
        try {
            $backups = [];

            if (!is_dir($this->backupDir)) {
                return [];
            }

            $files = scandir($this->backupDir, SCANDIR_SORT_DESCENDING);

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filepath = $this->backupDir . '/' . $file;

                if (is_file($filepath) && pathinfo($file, PATHINFO_EXTENSION) === 'db') {
                    $backups[] = [
                        'filename' => $file,
                        'path' => $filepath,
                        'size' => round(filesize($filepath) / 1024 / 1024, 2) . ' MB',
                        'created' => date('Y-m-d H:i:s', filectime($filepath)),
                        'modified' => date('Y-m-d H:i:s', filemtime($filepath))
                    ];
                }
            }

            return $backups;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Download backup file
     */
    public function downloadBackup($filename) {
        try {
            // Sanitize filename
            $filename = basename($filename);
            $filepath = $this->backupDir . '/' . $filename;

            // Validate file exists and is in backup directory
            if (!file_exists($filepath) || !is_file($filepath)) {
                return ['success' => false, 'message' => 'Backup file not found'];
            }

            // Verify file is actually a database backup
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'db') {
                return ['success' => false, 'message' => 'Invalid file type'];
            }

            // Send file to browser
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));

            readfile($filepath);
            exit();
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Download error: ' . $e->getMessage()];
        }
    }

    /**
     * Delete backup
     */
    public function deleteBackup($filename) {
        try {
            $filename = basename($filename);
            $filepath = $this->backupDir . '/' . $filename;

            if (!file_exists($filepath)) {
                return ['success' => false, 'message' => 'Backup file not found'];
            }

            if (!unlink($filepath)) {
                return ['success' => false, 'message' => 'Failed to delete backup'];
            }

            return ['success' => true, 'message' => 'Backup deleted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Restore from backup (ADMIN ONLY - DESTRUCTIVE)
     */
    public function restoreBackup($filename) {
        try {
            $filename = basename($filename);
            $backupPath = $this->backupDir . '/' . $filename;

            // Validate backup exists
            if (!file_exists($backupPath)) {
                return ['success' => false, 'message' => 'Backup file not found'];
            }

            // Create safety backup of current database
            $safetyBackup = $this->backup();
            if (!$safetyBackup['success']) {
                return ['success' => false, 'message' => 'Failed to create safety backup'];
            }

            // Close current connection
            // Copy backup to main database
            if (!copy($backupPath, DATABASE_PATH)) {
                return ['success' => false, 'message' => 'Failed to restore database'];
            }

            return [
                'success' => true,
                'message' => 'Database restored successfully',
                'safety_backup' => $safetyBackup['filename']
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Restore error: ' . $e->getMessage()];
        }
    }

    /**
     * Get backup directory size
     */
    public function getBackupDirectorySize() {
        try {
            $size = 0;

            if (!is_dir($this->backupDir)) {
                return 0;
            }

            $files = scandir($this->backupDir);

            foreach ($files as $file) {
                $path = $this->backupDir . '/' . $file;
                if (is_file($path)) {
                    $size += filesize($path);
                }
            }

            return round($size / 1024 / 1024, 2); // Return in MB
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Automatic backup scheduling
     */
    public function shouldPerformAutoBackup() {
        try {
            $lastBackupFile = $this->getLatestBackup();

            if (!$lastBackupFile) {
                return true; // Never backed up
            }

            // Get creation time
            $lastBackupTime = filemtime($lastBackupFile['path']);
            $timeSinceBackup = time() - $lastBackupTime;

            // Backup if more than 24 hours have passed
            return $timeSinceBackup > (24 * 3600);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get latest backup
     */
    private function getLatestBackup() {
        $backups = $this->listBackups();
        return !empty($backups) ? $backups[0] : null;
    }
}
?>
