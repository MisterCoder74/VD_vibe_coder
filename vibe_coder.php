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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Vivacity Vibe Coder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@300;400;500;700&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <div class="noise"></div>

    <div class="container">
        <div class="page-header">
            <div class="icon-badge">⚡</div>
            <div class="page-header-text">
                <h1 style="font-size: 1.8rem;">Vivacity <em>Vibe Coder</em></h1>
                <p style="color: var(--text-dim);">Task-based AI Code Generator</p>
            </div>
            <div style="margin-left: auto; display: flex; align-items: center; gap: 15px;">
                <div class="credit-badge" id="vibeCredits">--- credits</div>
                <div style="background: var(--bg-card); padding: 5px 12px; border-radius: 20px; border: 1px solid var(--border); display: flex; align-items: center; gap: 10px;">
                    <span>🤖</span>
                    <select id="modelSelect" style="background: transparent; border: none; padding: 0; color: #fff; cursor: pointer;">
                        <option value="gpt-4o-mini">gpt-4o-mini</option>
                        <option value="gpt-4.1-nano">gpt-4.1-nano</option>
                        <option value="gpt-4.1-mini">gpt-4.1-mini</option>
                        <option value="gpt-4o">gpt-4o</option>
                    </select>
                </div>
                <span class="badge" id="status-badge">IDLE</span>
                <a href="dashboard.php" class="btn">Back</a>
            </div>
        </div>

        <hr class="divider" />

        <div class="viber-main">
            <div class="request-card" style="background: var(--bg-card); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
                <label style="display: block; margin-bottom: 10px; font-weight: 600; color: var(--text-dim);">PROJECT BRIEF</label>
                <textarea id="userPrompt" placeholder="Describe your micro-app (e.g., 'A modern Pomodoro timer with dark mode')..." style="width: 100%; min-height: 100px; margin-bottom: 15px;"></textarea>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button id="decomposeBtn" class="primary" onclick="VibeCoder.decompose()">🧩 Decompose</button>
                    <button id="executeBtn" onclick="VibeCoder.execute()">🚀 Execute</button>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="autoExecute" checked>
                        <span style="font-size: 0.9rem;">Auto-Execute</span>
                    </label>
                </div>
            </div>

            <div class="viber-container">
                <div class="viber-left-panel">
                    <div class="task-list">
                        <div class="task-list-header">
                            <span style="font-weight: 600; color: var(--text-dim);">TASKS</span>
                            <button class="icon-btn" onclick="VibeCoder.editTasks()">✏️</button>
                        </div>
                        <div id="taskOutput">Tasks will appear here...</div>
                    </div>

                    <div id="codeSection">
                        <div class="task-list-header">
                            <span style="font-weight: 600; color: var(--text-dim);">GENERATED CODE</span>
                        </div>
                        <div id="codeOutput">Code will be generated here...</div>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button id="downloadBtn" onclick="VibeCoder.download()" disabled>📥 Download</button>
                            <button id="deployBtn" onclick="VibeCoder.deploy()" disabled>☁️ Deploy</button>
                        </div>
                    </div>
                </div>

                <div class="viber-right-panel">
                    <iframe id="artifactFrame" src="about:blank"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Edit Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">Edit Tasks (JSON)</div>
            <textarea id="taskJsonArea" style="width: 100%; height: 400px; font-family: var(--font-mono);"></textarea>
            <div class="modal-footer">
                <button onclick="VibeCoder.closeTaskModal()">Cancel</button>
                <button class="primary" onclick="VibeCoder.saveTasks()">Save Tasks</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        const CLIENT_ID = "<?php echo $_SESSION['client_id']; ?>";
        document.addEventListener('DOMContentLoaded', () => {
            VibeCoder.init();
        });
    </script>
</body>
</html>
