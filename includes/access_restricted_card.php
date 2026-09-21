<?php
/**
 * Shared Access Restricted Template (403 Forbidden)
 * Rendered when a user does not have RBAC permission to run a specific report.
 */
$user = $this->getCurrentUser();
$reportName = $reportName ?? 'Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Restricted | Active Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.3">
    <style>
        .restricted-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 120px);
            padding: 24px;
        }
        .restricted-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 40px;
            max-width: 540px;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }
        .restricted-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #fee2e2;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px auto;
        }
        .restricted-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .restricted-desc {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .restricted-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn-restricted {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-restricted-primary {
            background: #2563eb;
            color: #ffffff;
        }
        .btn-restricted-primary:hover {
            background: #1d4ed8;
        }
        .btn-restricted-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-restricted-secondary:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php require_once __DIR__ . '/header.php'; ?>

            <div class="content-body">
                <div class="restricted-wrapper">
                    <div class="restricted-card">
                        <div class="restricted-icon-wrap">
                            <i class="icon-shield-alert"></i>
                        </div>
                        <h1 class="restricted-title">Access Restricted</h1>
                        <p class="restricted-desc">
                            You do not have permission to view or run the <strong><?= htmlspecialchars($reportName); ?></strong> report.
                            <br><br>
                            Your user account is restricted under company RBAC policies. Please contact an administrator to request access.
                        </p>
                        <div class="restricted-actions">
                            <a href="reports.php" class="btn-restricted btn-restricted-primary">Return to Invoices</a>
                            <a href="index.php" class="btn-restricted btn-restricted-secondary">Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
