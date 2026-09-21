<?php
/**
 * Settings Module: AI Entity Extractor & Subscription Intelligence
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$auth->requireAdmin();

// Handle POST actions for AI Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_ai_settings') {
        try {
            $provider = $_POST['ai_provider'] ?? 'gemini';
            $apiKeyInput = trim($_POST['gemini_api_key'] ?? '');
            $model = trim($_POST['ai_model'] ?? 'gemini-2.5-flash');
            $customEndpoint = trim($_POST['ai_custom_endpoint'] ?? '');

            $db->setSetting('ai_provider', $provider);
            if (!empty($apiKeyInput) && strpos($apiKeyInput, '••••') === false) {
                $db->setSetting('gemini_api_key', $apiKeyInput);
            }
            $db->setSetting('ai_model', $model);
            $db->setSetting('ai_custom_endpoint', $customEndpoint);

            $message = 'AI Entity Extractor settings updated successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'AI_SETTINGS_UPDATED', "Updated AI settings (Provider: $provider, Model: $model)");
        } catch (Exception $e) {
            $message = 'Error saving AI settings: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch AI settings
$aiProvider = $db->getSetting('ai_provider', 'gemini');
$geminiApiKey = $db->getSetting('gemini_api_key', '');
$aiModel = $db->getSetting('ai_model', 'gemini-2.5-flash');
$aiCustomEndpoint = $db->getSetting('ai_custom_endpoint', '');
?>

<!-- AI Entity Extractor & Subscription Intelligence Card -->
<div class="card" style="border-left: 4px solid #8b5cf6;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
        <div>
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                AI Entity Extractor &amp; Subscription Intelligence
                <span style="font-size: 11px; font-weight: 700; color: #6d28d9; background: #ede9fe; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">Pluggable Architecture</span>
            </h2>
            <p style="color: var(--text-muted); font-size: 13px; margin-top: 5px; margin-bottom: 0;">
                Extracts hardware serial numbers, warranty lifecycles, and software/SaaS recurring subscription periods from multi-line QuickBooks invoice text into normalized operational registries.
            </p>
        </div>
        <div>
            <?php if (!empty($geminiApiKey)): ?>
            <span style="font-size: 12px; font-weight: 700; color: #166534; background: #dcfce7; padding: 6px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                <span>●</span> AI Key Configured
            </span>
            <?php else: ?>
            <span style="font-size: 12px; font-weight: 700; color: #9a3412; background: #ffedd5; padding: 6px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                <span>○</span> API Key Required
            </span>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 22px; margin-top: 15px;">
        <input type="hidden" name="action" value="update_ai_settings">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group" style="margin: 0;">
                <label style="font-weight: 600; font-size: 13px;">AI Engine Provider</label>
                <select name="ai_provider" class="form-control" style="font-size: 13px;">
                    <option value="gemini" <?php echo $aiProvider === 'gemini' ? 'selected' : ''; ?>>Google Gemini API (Recommended - Fast &amp; JSON Mode)</option>
                    <option value="openai" <?php echo $aiProvider === 'openai' ? 'selected' : ''; ?>>OpenAI / DeepSeek (OpenAI-compatible API)</option>
                    <option value="custom" <?php echo $aiProvider === 'custom' ? 'selected' : ''; ?>>Custom Endpoint (Local Ollama / vLLM)</option>
                </select>
                <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Default is Google Gemini for high-speed structured entity extraction.</small>
            </div>

            <div class="form-group" style="margin: 0;">
                <label style="font-weight: 600; font-size: 13px;">Model Identifier</label>
                <input type="text" name="ai_model" class="form-control" value="<?php echo htmlspecialchars($aiModel); ?>" placeholder="gemini-2.5-flash" style="font-size: 13px; font-family: monospace;">
                <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Recommended: <code>gemini-2.5-flash</code>, <code>gemini-2.5-pro</code>, <code>gpt-4o-mini</code>.</small>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: 600; font-size: 13px;">Google Gemini API Secret Key</label>
            <div style="display: flex; gap: 10px;">
                <input type="password" name="gemini_api_key" id="geminiApiKeyInput" class="form-control" value="<?php echo !empty($geminiApiKey) ? '••••••••••••••••••••••••' : ''; ?>" placeholder="Paste your Gemini API key (AQ.Ab... / AIzaSy...)" style="font-size: 13px; font-family: monospace;">
                <button type="button" class="btn" style="background: white; border: 1px solid #cbd5e1; color: var(--text-main); font-size: 12px;" onclick="const el = document.getElementById('geminiApiKeyInput'); el.type = el.type === 'password' ? 'text' : 'password'; this.textContent = el.type === 'password' ? 'Show' : 'Hide';">Show</button>
            </div>
            <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Get your key from <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color: #7c3aed; text-decoration: underline;">Google AI Studio</a>.</small>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: 600; font-size: 13px;">Custom / Local API Endpoint (Optional)</label>
            <input type="url" name="ai_custom_endpoint" class="form-control" value="<?php echo htmlspecialchars($aiCustomEndpoint); ?>" placeholder="http://localhost:11434/v1 (Leave blank for official Google/OpenAI cloud)" style="font-size: 13px; font-family: monospace;">
            <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Only required if running local Ollama, vLLM, or self-hosted LLM endpoints.</small>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <button type="submit" class="btn btn-primary" style="background: #7c3aed; border-color: #6d28d9; padding: 10px 24px;">Save AI Configuration</button>
        </div>
    </form>
</div>
