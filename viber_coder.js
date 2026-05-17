document.addEventListener('DOMContentLoaded', () => {
    // === ELEMENTI PRINCIPALI ===
    const urlParams = new URLSearchParams(window.location.search);
    const clientId = urlParams.get('client_id') || '';
    const projectId = urlParams.get('project_id') || '';

    window.chatMemory = [];
    const taskOutput = document.getElementById('taskOutput');
    const codeOutput = document.getElementById('codeOutput');
    const artifactFrame = document.getElementById('artifactFrame');
    const decomposeBtn = document.getElementById('decomposeBtn');
    const executeBtn = document.getElementById('executeBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    const deployBtn = document.getElementById('deployBtn');
    const deployFeedback = document.getElementById('deploy-feedback');
    const modelSelect = document.getElementById('modelSelect');
    const statusBadge = document.getElementById('status-badge');
    const autoExecuteCheck = document.getElementById('autoExecute');
    
    const memSaved = localStorage.getItem('vivacity_chatMemory');
    if (memSaved) chatMemory = JSON.parse(memSaved);

    if (autoExecuteCheck) {
        autoExecuteCheck.checked = localStorage.getItem('vivacity_autoExecute') === 'true';
        autoExecuteCheck.addEventListener('change', () => {
            localStorage.setItem('vivacity_autoExecute', autoExecuteCheck.checked);
        });
    }

    const delay = ms => new Promise(r => setTimeout(r, ms));
    
    // === gestione modale editing tasks ===
    const editTasksIcon = document.getElementById('editTasksIcon');
    const editTasksModal = document.getElementById('editTasksModal');
    const tasksEditor = document.getElementById('tasksEditor');
    const saveTasksBtn = document.getElementById('saveTasks');
    const cancelTasksBtn = document.getElementById('cancelTasks');
    
    let totalTokensSession = 0; 
    let currentFinalHtml = "";

    function setStatus(status) {
        if (statusBadge) statusBadge.textContent = status.toUpperCase();
    }

    // === Funzione generica per chiamate API ===
    async function callAPI(action, data = {}) {
        try {
            const apiKey = Core.getApiKey();
            const model = modelSelect.value || 'gpt-4o-mini';
            const username = localStorage.getItem('sync_username') || 'Anonymous';
            
            if (!apiKey) {
                alert('⚠️ No API key set. Please set it in the dashboard.');
                return {};
            }
            
            setStatus('processing');
            const res = await fetch('api/viber_coder_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, apiKey, model, username, ...data })
            });
            const json = await res.json();
            setStatus('idle');
            
            if (json.tokens) totalTokensSession += Number(json.tokens || 0);
            
            return json;
        } catch (err) {
            console.error('API error:', err);
            setStatus('error');
            return {};
        }
    }

    /* === 1️⃣ DECOMPOSE === */
    decomposeBtn.addEventListener('click', async () => {
        const prompt = document.getElementById('userPrompt').value.trim();
        if (!prompt) return alert('Please enter a request!');
        
        chatMemory.push({
            prompt,
            fragments: [],
            htmlFinal: "",
            timestamp: Date.now()
        });
        
        const previousContext = (chatMemory.length > 1)
            ? chatMemory[chatMemory.length - 2].htmlFinal || ""
            : "";
        
        taskOutput.textContent = '⏳ Decomposing prompt...';
        
        const data = await callAPI('decompose', { prompt, previousContext });
        
        if (data.tasks?.length) {
            taskOutput.textContent = 
                `✅ ${data.tasks.length} tasks found (tokens used: ${data.tokens || 0})\n` +
                JSON.stringify(data.tasks, null, 2);
            codeOutput.textContent = '';
            artifactFrame.srcdoc = '';
            
            executeBtn.disabled = false;

            if (autoExecuteCheck && autoExecuteCheck.checked) {
                setTimeout(() => executeBtn.click(), 500);
            }
        } else {
            taskOutput.textContent = '❌ No tasks found.';
        }
    });

    /* === 2️⃣ EXECUTE PROGRESSIVELY === */
    executeBtn.addEventListener('click', async () => {
        const bundle = await callAPI('get_tasks');
        if (!bundle?.tasks) {
            taskOutput.textContent = '❌ No tasks. Decompose first.';
            return;
        }
        
        const steps = bundle.tasks;
        const fragments = [];
        taskOutput.textContent = `🚀 Executing ${steps.length} tasks...\n`;
        
        for (let i = 0; i < steps.length; i++) {
            const t = steps[i];
            taskOutput.textContent += `\n🧩 Step ${i + 1}/${steps.length}: ${t.task}`;
            
            const res = await callAPI('execute_single_task', {
                taskData: t,
                taskId: t.id,
                previousFragments: fragments
            });
            
            let snippet = (res.html || '').trim();
            taskOutput.textContent += `\n ↳ tokens used: ${res.tokens || 0}`;
            
            snippet = Core.cleanResponse(snippet);
            
            const m = snippet.match(/<!DOCTYPE html[\s\S]*<\/html>/i);
            if (m) snippet = m[0];
            
            fragments.push({ id: t.id, task: t.task, code: snippet });
            if (chatMemory.length) {
                chatMemory[chatMemory.length - 1].fragments = [...fragments];
            }
            
            codeOutput.textContent = snippet;
            artifactFrame.srcdoc = snippet;
            await delay(600);
        }
        
        // === ASSEMBLY ===
        taskOutput.textContent += '\n🧩 Assembling final HTML...';
        const bodyParts = fragments.map(f => {
            let code = f.code;
            code = code.replace(/<!DOCTYPE[^>]*>/gi, '');
            code = code.replace(/<\/?html[^>]*>/gi, '');
            code = code.replace(/<\/?head[^>]*>[\s\S]*?<\/head>/gi, '');
            code = code.replace(/<\/?body[^>]*>/gi, '');
            return `<!-- Task ${f.id}: ${f.task} -->\n${code.trim()}`;
        }).join('\n\n');
        
        const assembleRes = await callAPI('assemble_final', {
            context: bundle.prompt,
            fragments,
            assembledBody: bodyParts
        });
        
        if (assembleRes.html) {
            artifactFrame.srcdoc = assembleRes.html;
            codeOutput.textContent = assembleRes.html;
            currentFinalHtml = assembleRes.html;
            
            if (chatMemory.length) {
                chatMemory[chatMemory.length - 1].htmlFinal = assembleRes.html;
            }
            
            localStorage.setItem('vivacity_chatMemory', JSON.stringify(chatMemory));
            
            taskOutput.textContent += 
                `\n✅ Final page ready. (tokens this step: ${assembleRes.tokens || 0})` +
                `\n\n🔢 TOTAL TOKENS USED IN SESSION: ${totalTokensSession}`;
        } else {
            taskOutput.textContent += '\n❌ Assembly error.';
        }
    });

    /* === 3️⃣ DOWNLOAD & DEPLOY === */
    downloadBtn.onclick = () => {
        if (!currentFinalHtml) return alert("Nothing to download yet!");
        Core.downloadFile(currentFinalHtml, `vibe_project_${Date.now()}.html`);
    };

    deployBtn.onclick = async () => {
        if (!currentFinalHtml) return alert("Nothing to deploy yet!");
        
        deployBtn.disabled = true;
        deployFeedback.innerHTML = '<div class="preloader active">Deploying...</div>';
        setStatus('deploying');

        const res = await Core.deployProject(currentFinalHtml, '<?php // Vibe Coder Backend ?>', clientId, projectId);
        
        setStatus('idle');
        deployBtn.disabled = false;
        
        if (res.success) {
            deployFeedback.innerHTML = `
                <div class="success-message">
                    🚀 Deploy completato con successo!
                    <br>
                    <a href="${res.url}" target="_blank" class="deploy-link">Apri App</a>
                </div>`;
            window.open(res.url, '_blank');
        } else {
            deployFeedback.innerHTML = `<div class="error-message">Deployment failed: ${res.message}</div>`;
        }
    };

    // === 4️⃣ MODALE EDITING TASKS ===
    if (editTasksIcon) {
        editTasksIcon.onclick = async () => {
            const res = await callAPI('get_tasks');
            if (!res?.tasks) return alert('No tasks found.');
            tasksEditor.value = JSON.stringify(res.tasks, null, 2);
            editTasksModal.style.display = 'flex';
        };
    }

    if (cancelTasksBtn) {
        cancelTasksBtn.onclick = () => (editTasksModal.style.display = 'none');
    }

    saveTasksBtn.onclick = async () => {
        let newTasks;
        try {
            newTasks = JSON.parse(tasksEditor.value);
        } catch (err) {
            return alert('Invalid JSON!');
        }
        
        const res = await callAPI('save_tasks', { tasks: newTasks });
        if (res.success) {
            alert('Tasks updated successfully.');
            editTasksModal.style.display = 'none';
            taskOutput.textContent = 
                `✅ ${newTasks.length} tasks updated:\n` +
                JSON.stringify(newTasks, null, 2);
        } else {
            alert('Error saving tasks.');
        }
    };

});
