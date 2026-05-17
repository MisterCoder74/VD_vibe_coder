<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$apiKey = $input['apiKey'] ?? '';
$model = $input['model'] ?? 'gpt-4o-mini';
$username = $input['username'] ?? 'Anonymous';
$action = $input['action'] ?? null;

// User-specific task file for session isolation
$usernameSafe = preg_replace('/[^a-zA-Z0-9]/', '_', $username);
$file = 'viber_tasks_' . $usernameSafe . '.json';
$limit = 6;

switch ($action) {

    /* === 1️⃣ DECOMPOSITION === */
    case 'decompose':
        $userPrompt = trim($input['prompt'] ?? '');
        $previousContext = trim($input['previousContext'] ?? '');
        if ($previousContext) {
            $contextInstruction = "The user previously generated the following HTML/CSS/JS/PHP code. Future tasks must modify or extend this existing code, not replace it:\n" . 
            substr($previousContext, 0, 4000);
        } else {
            $contextInstruction = "No prior code exists, start fresh.";
        }

        if (!$userPrompt) { echo json_encode(['error'=>'Missing prompt']); exit; }

        $today = date("l, F j Y");
        $systemPrompt = "Today is " . $today . ". You are an assistant that decomposes a web development request into sequential micro‑tasks.
        $contextInstruction
        Break the following request into a maximum of $limit concise tasks. MANDATORY: You will NEVER use markdown formatting.
        Respond only in JSON array format: [{\"id\":1,\"task\":\"...\"}].";

        $payload = [
            "model" => $model,
            "messages" => [
                ["role"=>"system","content"=>$systemPrompt],
                ["role"=>"user","content"=>$userPrompt]
            ],
            "response_format" => ["type"=>"json_object"]
        ];

        $resp = openai_call($payload, $apiKey);
        $respData = json_decode($resp['choices'][0]['message']['content'] ?? '[]', true);
        $tasks = $respData['tasks'] ?? $respData;
        $totalTokens = $resp['usage']['total_tokens'] ?? 0;

        file_put_contents($file, json_encode(['prompt'=>$userPrompt,'tasks'=>$tasks], JSON_PRETTY_PRINT));
        echo json_encode(['tasks'=>$tasks, 'tokens'=>$totalTokens]);
        break;


    /* === 2️⃣ GET TASKS === */
    case 'get_tasks':
        if (!file_exists($file)) { echo json_encode(['error'=>'No tasks file for this user']); exit; }
        echo file_get_contents($file);
        break;


    /* === 3️⃣ EXECUTE SINGLE TASK === */
    case 'execute_single_task':
        if (!file_exists($file)) { echo json_encode(['error'=>'No tasks file']); exit; }
        $bundle = json_decode(file_get_contents($file), true);
        $context = $bundle['prompt'] ?? '';
        $tData = $input['taskData'] ?? [];
        $task = $tData['task'] ?? '';
        $taskId = $input['taskId'] ?? 0;
        $previousFragments = $input['previousFragments'] ?? [];

        $previousContext = '';
        if (!empty($previousFragments)) {
            $previousContext = "\n\nFragments already generated:\n" . json_encode($previousFragments, JSON_PRETTY_PRINT) . 
            "\n\nDo NOT duplicate or re-create elements already present above. Only add what's missing. NEVER use markdown formatting";
        }

        $sys = "
        You are developing a webpage as part of project: \"$context\".
        Now execute this subtask: \"$task\".
        Generate only the HTML/CSS/JS and PHP (if needed) code for this fragment, without <!DOCTYPE> or <html> tags.
        Return pure code, no explanations. NEVER use markdown formatting.
        $previousContext
        ";

        $resp = openai_call([
            "model"=>$model,
            "messages"=>[
                ["role"=>"system","content"=>$sys],
                ["role"=>"user","content"=>"Generate the code for this subtask. NEVER use markdown formatting"]
            ]
        ], $apiKey);

        $raw = trim($resp['choices'][0]['message']['content'] ?? '');
        $totalTokens = $resp['usage']['total_tokens'] ?? 0;
        echo json_encode(['taskId'=>$taskId,'task'=>$task,'html'=>$raw,'tokens'=>$totalTokens]);
        break;


    /* === 4️⃣ FINAL ASSEMBLY === */
    case 'assemble_final':
        $context = $input['context'] ?? '';
        $fragments = $input['fragments'] ?? [];
        $assembledBody = $input['assembledBody'] ?? '';

        $prompt = "
        You are assembling a final HTML/CSS/JS and PHP (if needed) page for the project: \"$context\".
        
        The HTML body content is ALREADY written and MUST NOT be changed:
        --- START BODY ---
        $assembledBody
        --- END BODY ---
        
        Your ONLY job:
        1. Collect ALL <style> blocks from the fragments and merge them into one unified <style> in <head>, resolving conflicts.
        2. Collect ALL <script> blocks and merge them before </body>.
        3. Make sure that the <footer>, if present, is the last block. If it isn't, move it to the bottom.
        4. Wrap everything in a valid <!DOCTYPE html> structure.
        5. Do NOT rewrite, redesign, or invent any new HTML content except for the case in point 3.
        
        Return only valid HTML/CSS/JS and PHP (if needed) from <!DOCTYPE html> to </html> NEVER use markdown formatting.
        ";

        $resp = openai_call([
            "model"=>$model,
            "messages"=>[
                ["role"=>"system","content"=>"You are an expert full-stack developer."],
                ["role"=>"user","content"=>$prompt]
            ]
        ], $apiKey);

        $raw = $resp['choices'][0]['message']['content'] ?? '';
        if (preg_match('/<!DOCTYPE html[\s\S]*<\/html>/i',$raw,$m)) {
            $html = $m[0];
        } else {
            $html = $raw;
        }
        echo json_encode(['html'=>$html, 'tokens'=>$resp['usage']['total_tokens'] ?? 0]);
        break;

    /* === 📝 SAVE TASKS MANUALLY EDITED === */
    case 'save_tasks':
        $newTasks = $input['tasks'] ?? [];
        if (!is_array($newTasks)) { echo json_encode(['error'=>'Invalid data']); exit; }
        $bundle = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        $bundle['tasks'] = $newTasks;
        file_put_contents($file, json_encode($bundle, JSON_PRETTY_PRINT));
        echo json_encode(['success'=>true]);
        break;

    default:
        echo json_encode(['error'=>'Invalid action']);
}


/* === ⚙️ common function === */
function openai_call($payload,$key){
    $ch=curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>[
            "Content-Type: application/json",
            "Authorization: Bearer $key"
        ],
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload)
    ]);
    $resp=curl_exec($ch);
    if(curl_errno($ch)){$err=curl_error($ch);curl_close($ch);return['error'=>$err];}
    curl_close($ch);
    return json_decode($resp,true);
}
?>
