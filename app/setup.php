<?php
require __DIR__ . '/bootstrap.php';

use App\Services\UserService;
use App\Database\Migrator;

// Ensure database tables exist
try {
    $migrator = new Migrator($config['mysql']);
    $migrator->ensure();
} catch (Exception $e) {
    // Tables might already exist, continue
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        $userService = new UserService($config['mysql']);
        
        // Check if any admin users exist
        $users = $userService->listUsers();
        $adminExists = false;
        foreach ($users as $user) {
            if ($user['role'] === 'admin') {
                $adminExists = true;
                break;
            }
        }
        
        if ($adminExists) {
            $message = 'Admin user already exists';
        } else {
            // Create default admin
            $adminData = [
                'username' => 'admin',
                'email' => 'admin@localhost',
                'password' => 'admin123',
                'full_name' => 'System Administrator',
                'role' => 'admin',
                'is_active' => true
            ];
            
            $result = $userService->createUser($adminData);
            $message = 'Admin user created successfully! Username: admin, Password: admin123';
            $success = true;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin - Admin Panel</title>
    <link rel="stylesheet" href="assets/css/enterprise-design-system.css">
    <style>
        body {
            background: linear-gradient(135deg, #304d6a, #2c4357);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .setup-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .setup-logo {
            height: 60px;
            margin-bottom: 20px;
        }
        .setup-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin: 0 0 10px 0;
        }
        .setup-description {
            color: #666;
            margin-bottom: 30px;
        }
        .setup-button {
            width: 100%;
            padding: 12px;
            background: #304d6a;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .setup-button:hover {
            background: #2c4357;
        }
        .setup-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid;
        }
        .message.success {
            background: #efe;
            color: #3c3;
            border-color: #cfc;
        }
        .message.error {
            background: #fee;
            color: #c33;
            border-color: #fcc;
        }
        .login-link {
            display: inline-block;
            margin-top: 20px;
            color: #304d6a;
            text-decoration: none;
        }
        .login-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <img src="assets/img/logo.png" alt="Logo" class="setup-logo">
        <h1 class="setup-title">Admin Setup</h1>
        <p class="setup-description">Create the default administrator account for your admin panel.</p>
        
        <?php if ($message): ?>
            <div class="message <?= $success ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
        <form method="POST" id="setup-form">
            <button type="submit" class="setup-button" id="setup-btn">Create Admin Account</button>
        </form>
        <?php else: ?>
            <a href="login.php" class="login-link">→ Go to Login Page</a>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('setup-form')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('setup-btn');
            btn.disabled = true;
            btn.textContent = 'Creating...';
        });
    </script>
</body>
</html>