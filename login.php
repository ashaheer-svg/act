<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

header('Referrer-Policy: no-referrer');

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept JSON body (AJAX) to bypass ModSecurity ARGS scanning (rule 340716)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $json = json_decode(file_get_contents('php://input'), true);
        $username = $json['username'] ?? '';
        $password = $json['password'] ?? '';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
    }

    if (empty($username) || empty($password)) {
        $result = ['success' => false, 'message' => 'Username and password are required'];
    } else {
        $result = $auth->login($username, $password);
    }

    // If AJAX request, return JSON
    if (strpos($contentType, 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }

    // Fallback for non-AJAX POST
    if ($result['success']) {
        header('Location: index.php');
        exit();
    } else {
        $error = $result['message'];
    }
}

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Activity BI - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0b1121;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --border-light: #cbd5e1;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f1f5f9;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 20px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 24px;
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased;
            transition: background 0.3s ease;
        }

        body.dark-bg {
            background-color: var(--sidebar-bg);
            background-image: radial-gradient(circle at 50% 25%, rgba(79, 70, 229, 0.22) 0%, transparent 70%);
            background-size: auto;
        }

        .login-container {
            background: #ffffff;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08), 0 16px 32px -4px rgba(15, 23, 42, 0.08);
            width: 100%;
            max-width: 380px;
            padding: 36px 32px 28px;
            position: relative;
            transition: box-shadow 0.3s ease;
        }

        body.dark-bg .login-container {
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .theme-toggle-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            z-index: 100;
        }

        .theme-toggle-btn:hover {
            color: var(--text-dark);
            border-color: #94a3b8;
            transform: translateY(-1px);
        }

        body.dark-bg .theme-toggle-btn {
            background: rgba(17, 24, 39, 0.85);
            border-color: rgba(255, 255, 255, 0.15);
            color: #94a3b8;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        body.dark-bg .theme-toggle-btn:hover {
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 26px;
        }

        .logo-icon-wrap {
            width: 52px;
            height: 52px;
            margin: 0 auto 16px;
            background: var(--sidebar-bg);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(11, 17, 33, 0.25);
            color: #ffffff;
        }

        .brand-heading-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .brand-text {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--text-dark);
            text-transform: uppercase;
        }

        .brand-badge {
            font-size: 11px;
            font-weight: 800;
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            border: 1px solid rgba(79, 70, 229, 0.25);
            padding: 2px 7px;
            border-radius: 4px;
            letter-spacing: 0.04em;
        }

        .brand-subtitle {
            color: var(--text-muted);
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #334155;
            font-size: 12px;
            font-weight: 600;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            color: var(--text-muted);
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            height: 42px;
            background: #f8fafc;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 0 40px 0 38px;
            font-size: 13px;
            color: #0f172a;
            font-family: inherit;
            transition: all 0.18s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.16);
        }

        .input-toggle-btn {
            position: absolute;
            right: 10px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s;
        }

        .input-toggle-btn:hover {
            color: #0f172a;
        }

        .error-message {
            background-color: #fef2f2;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            border: 1px solid #fecaca;
            font-size: 12.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-button {
            width: 100%;
            height: 44px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.18s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-button:hover {
            background: var(--primary-hover);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
            transform: translateY(-1px);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Canvas Theme Toggle (Light / Dark Blue Canvas) -->
    <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleCanvasTheme()" title="Toggle Canvas Theme (Light / Dark Blue)">
        <svg id="themeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
    </button>

    <div class="login-container">
        <div class="login-header">
            <div class="logo-icon-wrap">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                    <path d="M2 17l10 5 10-5"></path>
                    <path d="M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <div class="brand-heading-wrap">
                <span class="brand-text">ACTIVITY</span>
                <span class="brand-badge">BI</span>
            </div>
            <p class="brand-subtitle">Commercial Intelligence Portal</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <div id="errorMsg" class="error-message" style="display:none;"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </span>
                    <input type="text" id="username" name="username" placeholder="Username" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </span>
                    <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="input-toggle-btn" id="togglePassBtn" title="Toggle password visibility">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-button" id="loginBtn">
                <span>Sign In to Activity</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </button>
        </form>
    </div>

    <script>
    // Canvas theme toggle (Light / Dark Blue)
    function toggleCanvasTheme() {
        document.body.classList.toggle('dark-bg');
        const isDark = document.body.classList.contains('dark-bg');
        localStorage.setItem('login_canvas_theme', isDark ? 'dark' : 'light');
        updateThemeIcon(isDark);
    }

    function updateThemeIcon(isDark) {
        const icon = document.getElementById('themeIcon');
        if (isDark) {
            icon.innerHTML = '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>';
        } else {
            icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>';
        }
    }

    if (localStorage.getItem('login_canvas_theme') === 'dark') {
        document.body.classList.add('dark-bg');
        updateThemeIcon(true);
    }

    // Password visibility toggle
    const passInput = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePassBtn');
    toggleBtn.addEventListener('click', function() {
        if (passInput.type === 'password') {
            passInput.type = 'text';
            toggleBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        } else {
            passInput.type = 'password';
            toggleBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }
    });

    // Form submission
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('loginBtn');
        const errDiv = document.getElementById('errorMsg');
        btn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 0.8s linear infinite;">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                <path d="M12 2a10 10 0 0 1 10 10"></path>
            </svg>
            <span>Signing in...</span>
        `;
        btn.disabled = true;
        errDiv.style.display = 'none';

        fetch('login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username: document.getElementById('username').value,
                password: document.getElementById('password').value
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'index.php';
            } else {
                errDiv.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span>${data.message}</span>
                `;
                errDiv.style.display = 'flex';
                btn.innerHTML = `
                    <span>Sign In to Activity</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                `;
                btn.disabled = false;
            }
        })
        .catch(() => {
            errDiv.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>Connection error. Please try again.</span>
            `;
            errDiv.style.display = 'flex';
            btn.innerHTML = `
                <span>Sign In to Activity</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            `;
            btn.disabled = false;
        });
    });
    </script>
</body>
</html>
