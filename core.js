/**
 * VivacityGPT Core JS
 * Centralized logic for API Key, API calls, and common UI utilities.
 */

const Core = {
    // API KEY MANAGEMENT
    getApiKey: () => {
        return localStorage.getItem('openaikey') || '';
    },
    setApiKey: (key) => {
        localStorage.setItem('openaikey', key);
    },
    
    // GITHUB TOKEN MANAGEMENT
    getGitHubToken: () => {
        return localStorage.getItem('githubtoken') || '';
    },
    setGitHubToken: (token) => {
        localStorage.setItem('githubtoken', token);
    },
    
    // API CALLS
    callAI: async (messages, model = 'gpt-4o-mini', options = {}) => {
        const apiKey = Core.getApiKey();
        if (!apiKey) {
            throw new Error('Missing API Key. Please set it in the dashboard.');
        }

        const username = localStorage.getItem('sync_username') || 'Anonymous';

        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                api_key: apiKey,
                model: model,
                messages: messages,
                username: username,
                max_tokens: options.max_tokens || 8000,
                temperature: options.temperature || 0.3
            })
        });

        if (!response.ok) {
            let errorMsg = 'API Error';
            try {
                const error = await response.json();
                errorMsg = error.error?.message || error.error || 'API Error';
            } catch(e) {}
            throw new Error(errorMsg);
        }

        return await response.json();
    },

    rebuildImage: async (prompt, options = {}) => {
        const apiKey = Core.getApiKey();
        if (!apiKey) {
            throw new Error('Missing API Key. Please set it in the dashboard.');
        }

        const username = localStorage.getItem('sync_username') || 'Anonymous';

        const response = await fetch('api/image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                api_key: apiKey,
                prompt: prompt,
                username: username,
                model: options.model || 'gpt-image-1',
                size: options.size || '1024x1024',
                quality: options.quality || 'medium',
                //response_format: options.response_format || 'url'
            })
        });

        if (!response.ok) {
            let errorMsg = 'Image Generation Error';
            try {
                const error = await response.json();
                errorMsg = error.error?.message || error.error || 'Image Generation Error';
            } catch(e) {}
            throw new Error(errorMsg);
        }

        return await response.json();
    },

    // UTILITIES
    cleanResponse: (text) => {
        if (!text) return "";
        // Remove markdown code blocks with language identifiers
        let clean = text.replace(/```[a-zA-Z]*\n?/g, '');
        // Remove trailing code block markers
        clean = clean.replace(/```/g, '');
        return clean.trim();
    },

    // DOWNLOAD & DEPLOY
    downloadFile: (content, filename, type = 'text/html') => {
        const blob = new Blob([content], { type });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    },

    downloadZip: async (files, projectName = 'project') => {
        // files should be an array of { name: 'file.html', content: '...' }
        const username = localStorage.getItem('sync_username') || 'Anonymous';
        const formData = new FormData();
        formData.append('project_name', projectName);
        formData.append('files', JSON.stringify(files));
        formData.append('username', username);

        try {
            const response = await fetch('api/download.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/zip')) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `${projectName}.zip`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                } else {
                    // Fallback for when ZipArchive is not available
                    const result = await response.json();
                    if (result.files) {
                        result.files.forEach(f => Core.downloadFile(f.content, f.name));
                    }
                }
            } else {
                throw new Error('Failed to generate ZIP file.');
            }
        } catch (error) {
            console.error('ZIP Error:', error);
            alert('Error generating ZIP: ' + error.message);
        }
    },

    deployProject: async (frontendCode, backendCode, clientId = '', projectId = '') => {
        try {
            const username = localStorage.getItem('sync_username') || 'Anonymous';
            const response = await fetch('deploy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    frontend: frontendCode,
                    backend: backendCode,
                    username: username,
                    client_id: clientId,
                    project_id: projectId
                })
            });

            return await response.json();
        } catch (error) {
            console.error('Deploy Error:', error);
            return { success: false, message: error.message };
        }
    },

    // UI UTILS
    getFormattedTime: () => {
        const t = new Date();
        return `${t.getHours().toString().padStart(2, '0')}:${t.getMinutes().toString().padStart(2, '0')}`;
    }
};

// Common UI Initialization
document.addEventListener('DOMContentLoaded', () => {
    const getDashboardUrl = () => {
        const role = localStorage.getItem('sync_role');
        const user = localStorage.getItem('sync_username');
        let dashUrl = role === 'management' ? '../management/dashboard.html' : 'dashboard.html';
        const params = new URLSearchParams();
        if (user) params.set('u', user);
        if (role) params.set('r', role);
        const paramsString = params.toString();
        return dashUrl + (paramsString ? '?' + paramsString : '');
    };

    // Add "Back to Dashboard" button if it's not the dashboard itself
    const isDashboard = window.location.pathname.endsWith('dashboard.html');
    if (!isDashboard) {
        const header = document.querySelector('.page-header');
        if (header) {
            let backBtn = document.querySelector('.btn-back-dashboard');
            if (!backBtn) {
                backBtn = document.createElement('a');
                backBtn.className = 'btn btn-back-dashboard';
                backBtn.style.marginLeft = 'auto';
                backBtn.innerHTML = '🏠 Dashboard';
                header.appendChild(backBtn);
            }
            // Always update the href to ensure it has the correct parameters
            backBtn.href = getDashboardUrl();
        }
    }
});