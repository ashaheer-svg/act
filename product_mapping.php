<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();

$user = $auth->getCurrentUser();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_bulk_mappings') {
        try {
            $mappings = $_POST['mappings'] ?? [];
            $count = 0;
            foreach ($mappings as $encodedItem => $category) {
                if (empty(trim($category))) continue;
                $itemDesc = base64_decode($encodedItem);
                
                if ($db->saveProductMapping($itemDesc, trim($category))) {
                    $count++;
                }
            }
            if ($count > 0) {
                $message = "$count product mappings saved successfully. Historical records updated.";
                $messageType = 'success';
                $db->logActivity($user['id'], 'BULK_PRODUCT_MAPPED', "Mapped $count items");
            } else {
                $message = "No new categories were entered.";
                $messageType = 'info';
            }
        } catch (Exception $e) {
            $message = 'Bulk Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_product_mapping') {
        try {
            $mappingId = $_POST['mapping_id'] ?? 0;
            $db->deleteProductMapping($mappingId);
            $message = 'Product mapping rule deleted';
            $messageType = 'success';
            $db->logActivity($user['id'], 'PRODUCT_MAPPING_DELETED', "Deleted mapping ID: $mappingId");
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$uncategorizedItems = $db->getUncategorizedItems();
$existingMappings = $db->getAllMappings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Mapping - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.2">
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>
        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search items...'; require_once 'includes/header.php'; ?>
            <div class="content-body">
                <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <div>
                            <h2>Product Category Rationalization</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Map uncategorized items to proper categories. Rules will apply to historical and future data.</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 40px;">
                        <div>
                            <h3 style="font-size: 16px; margin-bottom: 15px; color: var(--primary);">Items Missing Category</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="save_bulk_mappings">
                                <div style="max-height: 600px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 12px;">
                                    <table class="tax-table" style="margin-top: 0;">
                                        <thead style="position: sticky; top: 0; z-index: 10; background: white;">
                                            <tr>
                                                <th>Uncategorized Item Description</th>
                                                <th style="text-align: right;">Volume</th>
                                                <th style="width: 250px;">Assign Category</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($uncategorizedItems)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 40px 0;">Great! All your items are categorized.</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($uncategorizedItems as $item): ?>
                                                <tr>
                                                    <td style="font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($item['item_description']); ?></td>
                                                    <td style="text-align: right; color: var(--text-muted);"><?php echo $item['occurrence_count']; ?></td>
                                                    <td>
                                                        <input type="text" name="mappings[<?php echo base64_encode($item['item_description']); ?>]" class="form-control" placeholder="e.g. HDD:Internal" style="padding: 6px 10px; font-size: 12px;">
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($uncategorizedItems)): ?>
                                <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
                                    <button type="submit" class="btn btn-primary">Save All Mappings</button>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div>
                            <h3 style="font-size: 16px; margin-bottom: 15px; color: var(--secondary);">Existing Mapping Rules</h3>
                            <div style="max-height: 600px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 12px; background: #fcfcfc;">
                                <table class="tax-table" style="margin-top: 0;">
                                    <thead style="position: sticky; top: 0; z-index: 10; background: white;">
                                        <tr>
                                            <th>Item</th>
                                            <th>Category</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($existingMappings)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 40px 0;">No mapping rules defined yet.</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($existingMappings as $rule): ?>
                                            <tr>
                                                <td style="font-size: 11px;"><?php echo htmlspecialchars($rule['item_description']); ?></td>
                                                <td style="font-size: 11px; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($rule['product_category']); ?></td>
                                                <td style="text-align: right;">
                                                    <form method="POST" onsubmit="return confirm('Delete this rule?');">
                                                        <input type="hidden" name="action" value="delete_product_mapping">
                                                        <input type="hidden" name="mapping_id" value="<?php echo $rule['id']; ?>">
                                                        <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 14px;">🗑️</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php require_once 'includes/layout_js.php'; ?>
</body>
</html>
