/**
 * Vivacity Vibe Coder - Consolidated Client Logic
 */

const API = {
    call: async (action, data = {}) => {
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...data })
            });
            const json = await res.json();
            if (json.error === 'Unauthorized') {
                window.location.href = 'index.php';
            }
            return json;
        } catch (err) {
            console.error('API Error:', err);
            return { success: false, error: 'Network error or server unavailable' };
        }
    }
};

const Dashboard = {
    loadMicroapps: async () => {
        const res = await API.call('get_microapps');
        const list = document.getElementById('microappsList');
        if (!list) return;
        
        if (res.microapps && res.microapps.length > 0) {
            list.innerHTML = res.microapps.map(app => `
                <tr>
                    <td>${app.name}</td>
                    <td>${app.created}</td>
                    <td class="actions-cell">
                        <a href="${app.url}" target="_blank" class="icon-btn" title="View">👁️</a>
                        <button onclick="Dashboard.deleteMicroapp('${app.id}')" class="icon-btn" title="Delete">🗑️</button>
                        <button onclick="Dashboard.downloadZip('${app.id}')" class="icon-btn" title="Download ZIP">📥</button>
                    </td>
                </tr>
            `).join('');
        } else {
            list.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-dim);">No microapps yet. Create your first one!</td></tr>';
        }
    },

    deleteMicroapp: async (appId) => {
        if (!confirm('Are you sure you want to delete this microapp? This cannot be undone.')) return;
        const res = await API.call('delete_microapp', { app_id: appId });
        if (res.success) {
            Dashboard.loadMicroapps();
        } else {
            alert('Error deleting microapp');
        }
    },

    downloadZip: async (appId) => {
        const res = await API.call('download_microapp_zip', { app_id: appId });
        if (res.success && res.url) {
            const a = document.createElement('a');
            a.href = res.url;
            a.download = `app_${appId}.zip`;
            a.click();
        } else {
            alert('Error generating ZIP: ' + (res.error || 'Unknown error'));
        }
    },

    updateCreditsDisplay: async () => {
        const res = await API.call('get_credits');
        const display = document.getElementById('creditsDisplay');
        const vibeDisplay = document.getElementById('vibeCredits');
        if (res.total !== undefined) {
            const text = `Credits: ${res.remaining} / ${res.total}`;
            if (display) display.innerText = text;
            if (vibeDisplay) vibeDisplay.innerText = text;
            return res.remaining;
        }
        return 0;
    },

    openSetup: () => {
        document.getElementById('setupModal').style.display = 'flex';
    },

    closeSetup: () => {
        document.getElementById('setupModal').style.display = 'none';
    },

    saveApiKey: async () => {
        const key = document.getElementById('apiKeyInput').value;
        const res = await API.call('save_api_key', { api_key: key });
        if (res.success) {
            Dashboard.closeSetup();
            alert('API Key saved successfully.');
        } else {
            alert('Error saving API Key.');
        }
    }
};

const VibeCoder = {
    state: {
        chatMemory: [],
        totalTokensSession: 0,
        currentFinalHtml: ""
    },

    init: () => {
        const memSaved = localStorage.getItem('vivacity_chatMemory');
        if (memSaved) VibeCoder.state.chatMemory = JSON.parse(memSaved);
        
        const autoExecuteCheck = document.getElementById('autoExecute');
        if (autoExecuteCheck) {
            autoExecuteCheck.checked = localStorage.getItem('vivacity_autoExecute') === 'true';
            autoExecuteCheck.addEventListener('change', () => {
                localStorage.setItem('vivacity_autoExecute', autoExecuteCheck.checked);
            });
        }
        
        VibeCoder.updateCredits();
    },

    setStatus: (status) => {
        const badge = document.getElementById('status-badge');
        if (badge) badge.textContent = status.toUpperCase();
    },

    updateCredits: () => Dashboard.updateCreditsDisplay(),

    decompose: async () => {
        const credits = await VibeCoder.updateCredits();
        if (credits <= 0) {
            alert('⚠️ Out of credits! Please contact admin.');
            return;
        }

        const prompt = document.getElementById('userPrompt').value.trim();
        if (!prompt) return alert('Please enter a request!');

        VibeCoder.setStatus('processing');
        const previousContext = (VibeCoder.state.chatMemory.length > 0)
            ? VibeCoder.state.chatMemory[VibeCoder.state.chatMemory.length - 1].htmlFinal || ""
            : "";

        const taskOutput = document.getElementById('taskOutput');
        taskOutput.textContent = '⏳ Decomposing prompt...';

        const res = await API.call('decompose', { 
            prompt, 
            previousContext, 
            model: document.getElementById('modelSelect').value 
        });

        VibeCoder.setStatus('idle');
        if (res.error) {
            taskOutput.textContent = '❌ Error: ' + res.error;
            return;
        }

        if (res.tasks && res.tasks.length > 0) {
            VibeCoder.state.chatMemory.push({
                prompt,
                fragments: [],
                htmlFinal: "",
                timestamp: Date.now()
            });

            taskOutput.textContent = `✅ ${res.tasks.length} tasks found.\n` + JSON.stringify(res.tasks, null, 2);
            document.getElementById('codeOutput').textContent = '';
            document.getElementById('artifactFrame').srcdoc = '';
            
            VibeCoder.updateCredits();

            if (document.getElementById('autoExecute').checked) {
                setTimeout(() => VibeCoder.execute(), 500);
            }
        } else {
            taskOutput.textContent = '❌ No tasks found.';
        }
    },

    execute: async () => {
        const bundle = await API.call('get_tasks');
        if (!bundle || !bundle.tasks) {
            alert('No tasks found. Decompose first.');
            return;
        }

        const steps = bundle.tasks;
        const fragments = [];
        const taskOutput = document.getElementById('taskOutput');
        const codeOutput = document.getElementById('codeOutput');
        const artifactFrame = document.getElementById('artifactFrame');
        const model = document.getElementById('modelSelect').value;

        taskOutput.textContent = `🚀 Executing ${steps.length} tasks...\n`;
        
        for (let i = 0; i < steps.length; i++) {
            const t = steps[i];
            taskOutput.textContent += `\n🧩 Step ${i + 1}/${steps.length}: ${t.task}`;
            VibeCoder.setStatus('processing');

            const res = await API.call('execute_single_task', {
                taskData: t,
                taskId: t.id,
                previousFragments: fragments,
                model: model
            });

            VibeCoder.setStatus('idle');
            if (res.error) {
                taskOutput.textContent += `\n❌ Error: ${res.error}`;
                return;
            }

            let snippet = (res.html || '').trim();
            // Clean markdown if any
            snippet = snippet.replace(/```[a-zA-Z]*\n?/g, '').replace(/```/g, '').trim();
            
            const m = snippet.match(/<!DOCTYPE html[\s\S]*<\/html>/i);
            if (m) snippet = m[0];
            
            fragments.push({ id: t.id, task: t.task, code: snippet });
            
            codeOutput.textContent = snippet;
            artifactFrame.srcdoc = snippet;
            VibeCoder.updateCredits();
            await new Promise(r => setTimeout(r, 600));
        }

        // Assembly
        taskOutput.textContent += '\n🧩 Assembling final HTML...';
        VibeCoder.setStatus('processing');

        const bodyParts = fragments.map(f => {
            let code = f.code;
            code = code.replace(/<!DOCTYPE[^>]*>/gi, '');
            code = code.replace(/<\/?html[^>]*>/gi, '');
            code = code.replace(/<\/?head[^>]*>[\s\S]*?<\/head>/gi, '');
            code = code.replace(/<\/?body[^>]*>/gi, '');
            return `<!-- Task ${f.id}: ${f.task} -->\n${code.trim()}`;
        }).join('\n\n');

        const assembleRes = await API.call('assemble_final', {
            context: bundle.prompt,
            assembledBody: bodyParts,
            model: model
        });

        VibeCoder.setStatus('idle');
        if (assembleRes.html) {
            artifactFrame.srcdoc = assembleRes.html;
            codeOutput.textContent = assembleRes.html;
            VibeCoder.state.currentFinalHtml = assembleRes.html;
            
            if (VibeCoder.state.chatMemory.length) {
                VibeCoder.state.chatMemory[VibeCoder.state.chatMemory.length - 1].htmlFinal = assembleRes.html;
            }
            localStorage.setItem('vivacity_chatMemory', JSON.stringify(VibeCoder.state.chatMemory));
            
            taskOutput.textContent += '\n✅ Final page ready.';
            document.getElementById('downloadBtn').disabled = false;
            document.getElementById('deployBtn').disabled = false;
            VibeCoder.updateCredits();
        } else {
            taskOutput.textContent += '\n❌ Assembly error: ' + (assembleRes.error || 'Unknown error');
        }
    },

    download: () => {
        if (!VibeCoder.state.currentFinalHtml) return;
        const blob = new Blob([VibeCoder.state.currentFinalHtml], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `vibe_project_${Date.now()}.html`;
        a.click();
        URL.revokeObjectURL(url);
    },

    deploy: async () => {
        if (!VibeCoder.state.currentFinalHtml) return;
        
        const name = prompt("Enter a name for your microapp:", "My AI App") || "My AI App";
        VibeCoder.setStatus('deploying');
        
        const res = await API.call('deploy', {
            frontend: VibeCoder.state.currentFinalHtml,
            backend: '<?php // Vibe Coder Backend ?>',
            name: name
        });
        
        VibeCoder.setStatus('idle');
        if (res.success) {
            alert('🚀 Deploy successful! Opening your app...');
            window.open(res.url, '_blank');
        } else {
            alert('❌ Deployment failed: ' + (res.error || 'Unknown error'));
        }
    },

    editTasks: async () => {
        const res = await API.call('get_tasks');
        if (!res || !res.tasks) return alert('No tasks found.');
        document.getElementById('taskJsonArea').value = JSON.stringify(res.tasks, null, 2);
        document.getElementById('taskModal').style.display = 'flex';
    },

    closeTaskModal: () => {
        document.getElementById('taskModal').style.display = 'none';
    },

    saveTasks: async () => {
        let newTasks;
        try {
            newTasks = JSON.parse(document.getElementById('taskJsonArea').value);
        } catch (err) {
            return alert('Invalid JSON!');
        }
        
        const res = await API.call('save_tasks', { tasks: newTasks });
        if (res.success) {
            VibeCoder.closeTaskModal();
            document.getElementById('taskOutput').textContent = `✅ ${newTasks.length} tasks updated.\n` + JSON.stringify(newTasks, null, 2);
        } else {
            alert('Error saving tasks.');
        }
    }
};
