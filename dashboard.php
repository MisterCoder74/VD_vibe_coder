<?php
session_start();
if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vivacity Vibe Coder - Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@300;400;500;700&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="noise"></div>
    <div class="container">
        <header class="dashboard-header">
            <div class="user-info">
                <div>
                    <h1 style="font-size: 1.5rem;">Vivacity <em>Vibe Coder</em></h1>
                    <p style="color: var(--text-dim); font-size: 0.9rem;">Welcome back, <strong><?php echo $_SESSION['username']; ?></strong></p>
                </div>
                <div id="creditsDisplay" class="credit-badge">Loading credits...</div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="Dashboard.openSetup()">⚙️ Setup</button>
                <a href="logout.php" class="btn">🚪 Logout</a>
            </div>
        </header>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>My Microapps</h2>
            <a href="vibe_coder.php" class="btn primary">+ Create Microapp</a>
        </div>

        <div id="microappsContainer">
            <table class="microapps-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="microappsList">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Setup Modal -->
    <div id="setupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Setup OpenAI API Key</div>
            <p style="margin-bottom: 20px; color: var(--text-dim);">Your key is stored securely on the server and used only for your requests.</p>
            <input type="text" id="apiKeyInput" placeholder="sk-..." style="width: 100%;">
            <div class="modal-footer">
                <button onclick="Dashboard.closeSetup()">Cancel</button>
                <button class="primary" onclick="Dashboard.saveApiKey()">Save Key</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            Dashboard.loadMicroapps();
            Dashboard.updateCreditsDisplay();
        });
    </script>
</body>
</html>
