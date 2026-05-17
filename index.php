<?php
session_start();
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vivacity Vibe Coder - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@300;400;500;700&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="noise"></div>
    <div class="container">
        <div class="auth-container">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="font-size: 2rem;">Vivacity <em>Vibe Coder</em></h1>
            </div>
            <div class="auth-tabs">
                <div class="auth-tab active" onclick="switchTab('login')">Login</div>
                <div class="auth-tab" onclick="switchTab('register')">Register</div>
            </div>

            <form id="loginForm" class="auth-form active" onsubmit="handleLogin(event)">
                <input type="text" id="loginUser" placeholder="Username or Email" required>
                <input type="password" id="loginPass" placeholder="Password" required>
                <button type="submit" class="primary">Login</button>
            </form>

            <form id="registerForm" class="auth-form" onsubmit="handleRegister(event)">
                <input type="text" id="regUser" placeholder="Username" required>
                <input type="email" id="regEmail" placeholder="Email" required>
                <input type="password" id="regPass" placeholder="Password" required>
                <button type="submit" class="primary">Register</button>
            </form>
            
            <div id="authMessage" style="margin-top: 20px; color: #ff4d4d; text-align: center;"></div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function switchTab(tab) {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            
            if (tab === 'login') {
                document.querySelector('.auth-tab:nth-child(1)').classList.add('active');
                document.getElementById('loginForm').classList.add('active');
            } else {
                document.querySelector('.auth-tab:nth-child(2)').classList.add('active');
                document.getElementById('registerForm').classList.add('active');
            }
        }

        async function handleLogin(e) {
            e.preventDefault();
            const res = await API.call('login', {
                login: document.getElementById('loginUser').value,
                password: document.getElementById('loginPass').value
            });
            if (res.success) {
                window.location.href = 'dashboard.php';
            } else {
                document.getElementById('authMessage').innerText = res.error;
            }
        }

        async function handleRegister(e) {
            e.preventDefault();
            const res = await API.call('register', {
                username: document.getElementById('regUser').value,
                email: document.getElementById('regEmail').value,
                password: document.getElementById('regPass').value
            });
            if (res.success) {
                window.location.href = 'dashboard.php';
            } else {
                document.getElementById('authMessage').innerText = res.error;
            }
        }
    </script>
</body>
</html>
