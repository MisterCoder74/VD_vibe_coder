<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? null;

$usersFile = __DIR__ . '/users.json';

// --- Helpers ---

function getUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return [];
    return json_decode(file_get_contents($usersFile), true) ?: [];
}

function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

function getClientData($clientId) {
    $path = __DIR__ . "/users/$clientId/data.json";
    if (!file_exists($path)) return ["api_key" => "", "microapps" => []];
    return json_decode(file_get_contents($path), true) ?: ["api_key" => "", "microapps" => []];
}

function saveClientData($clientId, $data) {
    $dir = __DIR__ . "/users/$clientId";
    if (!file_exists($dir)) mkdir($dir, 0755, true);
    file_put_contents("$dir/data.json", json_encode($data, JSON_PRETTY_PRINT));
}

function updateCredits($clientId, $tokensUsed) {
    $users = getUsers();
    foreach ($users as &$user) {
        if ($user['client_id'] === $clientId) {
            $user['used_credits'] += $tokensUsed;
            break;
        }
    }
    saveUsers($users);
}

function openai_call($payload, $key) {
    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $key"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }
    curl_close($ch);
    return json_decode($resp, true);
}

function generateId($length = 8) {
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', mt_rand(1, 10))), 1, $length);
}

// --- Auth Check for protected actions ---
$protectedActions = ['get_credits', 'save_api_key', 'decompose', 'execute_single_task', 'assemble_final', 'save_tasks', 'get_microapps', 'delete_microapp', 'deploy', 'download_microapp_zip'];
if (in_array($action, $protectedActions)) {
    if (!isset($_SESSION['client_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

$clientId = $_SESSION['client_id'] ?? null;

switch ($action) {
    case 'register':
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!$username || !$email || !$password) {
            echo json_encode(['success' => false, 'error' => 'Missing fields']);
            exit;
        }

        $users = getUsers();
        foreach ($users as $u) {
            if ($u['username'] === $username || $u['email'] === $email) {
                echo json_encode(['success' => false, 'error' => 'User already exists']);
                exit;
            }
        }

        $newClientId = generateId();
        $newUser = [
            "username" => $username,
            "email" => $email,
            "password" => $password, // In production use password_hash
            "client_id" => $newClientId,
            "total_credits" => 10000,
            "used_credits" => 0,
            "created" => date('Y-m-d H:i:s')
        ];

        $users[] = $newUser;
        saveUsers($users);

        mkdir(__DIR__ . "/users/$newClientId/microapps", 0755, true);
        saveClientData($newClientId, ["api_key" => "", "microapps" => []]);

        $_SESSION['client_id'] = $newClientId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;

        echo json_encode(['success' => true]);
        break;

    case 'login':
        $login = trim($input['login'] ?? '');
        $password = $input['password'] ?? '';

        $users = getUsers();
        foreach ($users as $u) {
            if (($u['username'] === $login || $u['email'] === $login) && $u['password'] === $password) {
                $_SESSION['client_id'] = $u['client_id'];
                $_SESSION['username'] = $u['username'];
                $_SESSION['email'] = $u['email'];
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        break;

    case 'get_credits':
        $users = getUsers();
        foreach ($users as $u) {
            if ($u['client_id'] === $clientId) {
                echo json_encode([
                    'total' => $u['total_credits'],
                    'used' => $u['used_credits'],
                    'remaining' => $u['total_credits'] - $u['used_credits']
                ]);
                exit;
            }
        }
        break;

    case 'save_api_key':
        $apiKey = $input['api_key'] ?? '';
        $data = getClientData($clientId);
        $data['api_key'] = $apiKey;
        saveClientData($clientId, $data);
        echo json_encode(['success' => true]);
        break;

    case 'decompose':
        $userPrompt = trim($input['prompt'] ?? '');
        $previousContext = trim($input['previousContext'] ?? '');
        $model = $input['model'] ?? 'gpt-4o-mini';
        $clientData = getClientData($clientId);
        $apiKey = $clientData['api_key'];

        if (!$apiKey) { echo json_encode(['error'=>'Missing API Key. Setup it in Dashboard.']); exit; }

        $contextInstruction = $previousContext ? "The user previously generated some code. Modify/extend it." : "Start fresh.";

        $systemPrompt = "You are an assistant that decomposes a web development request into sequential micro-tasks.
        $contextInstruction
        Break into max 6 concise tasks. Respond only in JSON array format: [{\"id\":1,\"task\":\"...\"}].";

        $payload = [
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $userPrompt]
            ],
            "response_format" => ["type" => "json_object"]
        ];

        $resp = openai_call($payload, $apiKey);
        if (isset($resp['error'])) { echo json_encode($resp); exit; }
        
        $respData = json_decode($resp['choices'][0]['message']['content'] ?? '[]', true);
        $tasks = $respData['tasks'] ?? $respData;
        $totalTokens = $resp['usage']['total_tokens'] ?? 0;

        updateCredits($clientId, $totalTokens);
        file_put_contents(__DIR__ . "/users/$clientId/tasks.json", json_encode(['prompt' => $userPrompt, 'tasks' => $tasks], JSON_PRETTY_PRINT));
        
        echo json_encode(['tasks' => $tasks, 'tokens' => $totalTokens]);
        break;

    case 'execute_single_task':
        $clientData = getClientData($clientId);
        $apiKey = $clientData['api_key'];
        $model = $input['model'] ?? 'gpt-4o-mini';
        
        $tasksFile = __DIR__ . "/users/$clientId/tasks.json";
        if (!file_exists($tasksFile)) { echo json_encode(['error'=>'No tasks found']); exit; }
        
        $bundle = json_decode(file_get_contents($tasksFile), true);
        $context = $bundle['prompt'] ?? '';
        $tData = $input['taskData'] ?? [];
        $task = $tData['task'] ?? '';
        $taskId = $input['taskId'] ?? 0;
        $previousFragments = $input['previousFragments'] ?? [];

        $prevContext = !empty($previousFragments) ? "\n\nFragments already generated:\n" . json_encode($previousFragments) : "";

        $sys = "You are developing a webpage for: \"$context\". Subtask: \"$task\".
        Generate only HTML/CSS/JS/PHP code fragment. No explanations. No markdown.
        $prevContext";

        $resp = openai_call([
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => $sys],
                ["role" => "user", "content" => "Generate code. No markdown."]
            ]
        ], $apiKey);

        if (isset($resp['error'])) { echo json_encode($resp); exit; }

        $raw = trim($resp['choices'][0]['message']['content'] ?? '');
        $totalTokens = $resp['usage']['total_tokens'] ?? 0;
        updateCredits($clientId, $totalTokens);

        echo json_encode(['taskId' => $taskId, 'task' => $task, 'html' => $raw, 'tokens' => $totalTokens]);
        break;

    case 'assemble_final':
        $clientData = getClientData($clientId);
        $apiKey = $clientData['api_key'];
        $model = $input['model'] ?? 'gpt-4o-mini';
        $context = $input['context'] ?? '';
        $assembledBody = $input['assembledBody'] ?? '';

        $prompt = "Assemble final HTML page for: \"$context\".
        Body content (DO NOT CHANGE):
        $assembledBody
        
        Tasks:
        1. Merge all <style> into one in <head>.
        2. Merge all <script> before </body>.
        3. Ensure <footer> is last.
        4. Wrap in <!DOCTYPE html> structure.
        Return only valid HTML/CSS/JS/PHP. No markdown.";

        $resp = openai_call([
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => "You are an expert developer."],
                ["role" => "user", "content" => $prompt]
            ]
        ], $apiKey);

        if (isset($resp['error'])) { echo json_encode($resp); exit; }

        $raw = $resp['choices'][0]['message']['content'] ?? '';
        if (preg_match('/<!DOCTYPE html[\s\S]*<\/html>/i', $raw, $m)) {
            $html = $m[0];
        } else {
            $html = $raw;
        }
        $totalTokens = $resp['usage']['total_tokens'] ?? 0;
        updateCredits($clientId, $totalTokens);

        echo json_encode(['html' => $html, 'tokens' => $totalTokens]);
        break;

    case 'get_tasks':
        $tasksFile = __DIR__ . "/users/$clientId/tasks.json";
        if (file_exists($tasksFile)) {
            echo file_get_contents($tasksFile);
        } else {
            echo json_encode(['error' => 'No tasks found']);
        }
        break;

    case 'save_tasks':
        $newTasks = $input['tasks'] ?? [];
        $tasksFile = __DIR__ . "/users/$clientId/tasks.json";
        $bundle = file_exists($tasksFile) ? json_decode(file_get_contents($tasksFile), true) : [];
        $bundle['tasks'] = $newTasks;
        file_put_contents($tasksFile, json_encode($bundle, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;

    case 'get_microapps':
        $data = getClientData($clientId);
        echo json_encode(['microapps' => $data['microapps']]);
        break;

    case 'delete_microapp':
        $appId = $input['app_id'] ?? '';
        if (!$appId) { echo json_encode(['success' => false]); exit; }
        
        $appDir = __DIR__ . "/users/$clientId/microapps/$appId";
        if (file_exists($appDir)) {
            // Simple recursive delete
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($appDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir($appDir);
        }

        $data = getClientData($clientId);
        $data['microapps'] = array_values(array_filter($data['microapps'], function($app) use ($appId) {
            return $app['id'] !== $appId;
        }));
        saveClientData($clientId, $data);
        echo json_encode(['success' => true]);
        break;

    case 'deploy':
        $frontend = $input['frontend'] ?? '';
        $backend = $input['backend'] ?? '';
        $appName = $input['name'] ?? 'Untitled App';
        
        $appId = generateId();
        $appDir = __DIR__ . "/users/$clientId/microapps/$appId";
        mkdir($appDir, 0755, true);
        
        file_put_contents("$appDir/index.html", $frontend);
        file_put_contents("$appDir/backend.php", $backend);
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $url = $protocol . $host . dirname($_SERVER['PHP_SELF']) . "/users/$clientId/microapps/$appId/index.html";
        
        $data = getClientData($clientId);
        $data['microapps'][] = [
            'id' => $appId,
            'name' => $appName,
            'url' => $url,
            'created' => date('Y-m-d H:i:s')
        ];
        saveClientData($clientId, $data);
        
        echo json_encode(['success' => true, 'url' => $url]);
        break;

    case 'download_microapp_zip':
        $appId = $input['app_id'] ?? '';
        $appDir = __DIR__ . "/users/$clientId/microapps/$appId";
        
        if (!file_exists($appDir)) {
            echo json_encode(['success' => false, 'error' => 'App not found']);
            exit;
        }

        $zipName = "app_$appId.zip";
        $zipPath = __DIR__ . "/users/$clientId/$zipName";
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            if (file_exists("$appDir/index.html")) $zip->addFile("$appDir/index.html", "index.html");
            if (file_exists("$appDir/backend.php")) $zip->addFile("$appDir/backend.php", "backend.php");
            $zip->close();
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $zipUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . "/users/$clientId/$zipName";
            
            echo json_encode(['success' => true, 'url' => $zipUrl]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not create ZIP']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action: ' . $action]);
}
