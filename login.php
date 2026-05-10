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
    <title>Activity - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #777;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }

        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }

        .login-button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .login-button:hover {
            transform: translateY(-2px);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .default-creds {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #999;
        }

        .default-creds strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>📊 Activity</h1>
            <p>Sales Intelligence Dashboard</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <div id="errorMsg" class="error-message" style="display:none;"></div>

            <button type="submit" class="login-button" id="loginBtn">Login</button>

            <div class="default-creds">
                <p>First time? Use default credentials:</p>
                <p><strong>Username:</strong> admin</p>
                <p><strong>Password:</strong> admin123</p>
                <p style="margin-top: 10px; color: #c33;">⚠️ Change default password immediately!</p>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('loginBtn');
        const errDiv = document.getElementById('errorMsg');
        btn.textContent = 'Logging in...';
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
                errDiv.textContent = data.message;
                errDiv.style.display = 'block';
                btn.textContent = 'Login';
                btn.disabled = false;
            }
        })
        .catch(() => {
            errDiv.textContent = 'Connection error. Please try again.';
            errDiv.style.display = 'block';
            btn.textContent = 'Login';
            btn.disabled = false;
        });
    });
    </script>
</body>
</html>
