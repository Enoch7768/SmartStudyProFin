<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

use App\AI\AssistantService;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;

$pdo = Database::connect();
$user = Auth::user($pdo);
if (!$user) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Please sign in to use the learning assistant.']);
        exit;
    }
    Security::redirect('login.php?redirect=assistant.php');
}
$service = new AssistantService($pdo, (string) Config::get('ai.gemini_api_key', ''), (string) Config::get('ai.gemini_model', 'gemini-2.5-flash'));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!Security::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(419);
        echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
        exit;
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request.']);
        exit;
    }
    try {
        $result = $service->ask((int) $user['id'], (string) ($payload['message'] ?? ''), max(0, (int) ($payload['conversation_id'] ?? 0)));
        echo json_encode(['success' => true] + $result, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        http_response_code(str_contains($message, 'limit') ? 429 : 422);
        echo json_encode(['error' => $message]);
    }
    exit;
}

$conversationId = max(0, (int) ($_GET['conversation_id'] ?? 0));
$history = $conversationId > 0 ? $service->history((int) $user['id'], $conversationId) : [];
$conversations = $service->conversations((int) $user['id']);
$csrf = Security::csrfToken();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>AI Learning Assistant | SmartStudyPro</title>
<link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"><link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet"><link href="assets/css/main.css" rel="stylesheet">
<style>
:root{--navy:#0C086B;--deep:#070443;--orange:#FF7A00;--bg:#F5F6FB;--card:#fff;--ink:#1E293B;--muted:#64748B;--line:#E2E8F0}body{background:var(--bg);color:var(--ink);font-family:"Plus Jakarta Sans",sans-serif}h1,h2,h3{font-family:Outfit,sans-serif}.assistant-shell{display:grid;grid-template-columns:270px minmax(0,1fr);gap:20px;min-height:620px}.assistant-panel,.chat-panel{background:var(--card);border:1px solid var(--line);border-radius:16px}.assistant-panel{padding:18px}.chat-panel{display:flex;flex-direction:column;min-height:620px;overflow:hidden}.conversation-link{display:block;padding:10px 12px;border-radius:9px;color:var(--ink);text-decoration:none;font-size:13px;margin:4px 0}.conversation-link.active,.conversation-link:hover{background:#fff1e5;color:#9c4700}.messages{flex:1;padding:24px;overflow:auto;display:flex;flex-direction:column;gap:16px}.message{max-width:85%;padding:14px 16px;border-radius:14px;line-height:1.7;white-space:pre-wrap;overflow-wrap:anywhere}.message.user{align-self:flex-end;background:var(--navy);color:#fff;border-bottom-right-radius:4px}.message.assistant{align-self:flex-start;background:#f0f1f8;color:var(--ink);border-bottom-left-radius:4px}.sources{font-size:12px;margin-top:10px;border-top:1px solid rgba(100,116,139,.2);padding-top:8px}.sources a{color:var(--navy);font-weight:700;text-decoration:none}.composer{border-top:1px solid var(--line);padding:18px;display:flex;gap:10px;align-items:flex-end}.composer textarea{flex:1;resize:vertical;min-height:54px;max-height:150px;border:1px solid var(--line);border-radius:12px;padding:13px;font:14px "Plus Jakarta Sans",sans-serif}.send{background:var(--orange);border:0;color:#fff;border-radius:10px;padding:13px 18px;font-weight:700}.hint{font-size:12px;color:var(--muted)}.welcome{padding:28px;border-radius:16px;background:linear-gradient(120deg,var(--deep),var(--navy));color:#fff}.welcome p{color:rgba(255,255,255,.75)}@media(max-width:800px){.assistant-shell{grid-template-columns:1fr}.assistant-panel{order:2}.chat-panel{min-height:540px}.messages{padding:16px}.message{max-width:96%}.composer{padding:12px}}
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main id="main-content" class="container py-5">
<section class="welcome mb-4"><div class="small fw-bold text-uppercase mb-2" style="color:#ffb36b;letter-spacing:1px">Your course companion</div><h1 class="h2 mb-2">Learn it. Understand it. Apply it.</h1><p class="mb-0">Ask questions about your enrolled courses. Answers are grounded in published lesson notes and include links to their sources.</p></section>
<div class="assistant-shell">
<aside class="assistant-panel"><a class="btn btn-outline-primary w-100 mb-3" href="assistant.php">+ New conversation</a><div class="small text-uppercase fw-bold mb-2" style="color:var(--muted)">Recent conversations</div><?php foreach ($conversations as $conversation): ?><a class="conversation-link <?= (int)$conversation['id']===$conversationId?'active':'' ?>" href="assistant.php?conversation_id=<?= (int)$conversation['id'] ?>"><?= Security::escape($conversation['title']) ?></a><?php endforeach; ?><?php if (!$conversations): ?><p class="hint">Your saved conversations will appear here.</p><?php endif; ?><hr><p class="hint mb-0">The assistant can only use lessons in courses you are enrolled in. It will tell you when your course material does not contain an answer.</p></aside>
<section class="chat-panel">
<div class="messages" id="messages" aria-live="polite">
<?php if (!$history): ?><div class="text-center my-auto"><div class="display-6 mb-2" style="color:var(--orange)"><i class="bi bi-stars"></i></div><h2 class="h4">What are you learning today?</h2><p class="text-secondary">Ask about a concept, lesson, or topic from your courses.</p></div><?php endif; ?>
<?php foreach ($history as $message): ?><article class="message <?= $message['role']==='user'?'user':'assistant' ?>"><div><?= Security::escape($message['content']) ?></div><?php if ($message['sources']): ?><div class="sources"><strong>Course sources</strong><?php foreach ($message['sources'] as $source): ?><div><a href="study.php?course_id=<?= (int)$source['course_id'] ?>&lesson_id=<?= (int)$source['lesson_id'] ?>"><?= Security::escape($source['course_title'].' · '.$source['chapter_title'].' · '.$source['lesson_title']) ?></a></div><?php endforeach; ?></div><?php endif; ?></article><?php endforeach; ?>
</div>
<form class="composer" id="composer"><textarea id="question" maxlength="4000" placeholder="Ask a question about your course material…" aria-label="Your question" required></textarea><button class="send" id="send" type="submit">Send <i class="bi bi-arrow-up"></i></button></form>
</section>
</div>
<p class="hint mt-3">AI responses can make mistakes. Check the linked lesson sources when studying important details.</p>
</main>
<script>
const form=document.getElementById('composer'),input=document.getElementById('question'),messages=document.getElementById('messages'),send=document.getElementById('send');
const csrf=<?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let conversationId=<?= $conversationId ?>;
function addMessage(role,content,sources=[]){const article=document.createElement('article');article.className='message '+role;const body=document.createElement('div');body.textContent=content;article.appendChild(body);if(sources.length){const box=document.createElement('div');box.className='sources';const heading=document.createElement('strong');heading.textContent='Course sources';box.appendChild(heading);sources.forEach(source=>{const line=document.createElement('div'),link=document.createElement('a');link.href='study.php?course_id='+encodeURIComponent(source.course_id)+'&lesson_id='+encodeURIComponent(source.lesson_id);link.textContent=source.course_title+' · '+source.chapter_title+' · '+source.lesson_title;line.appendChild(link);box.appendChild(line)});article.appendChild(box)}messages.appendChild(article);messages.scrollTop=messages.scrollHeight}
form.addEventListener('submit',async event=>{event.preventDefault();const message=input.value.trim();if(!message)return;input.value='';send.disabled=true;addMessage('user',message);const pending=document.createElement('article');pending.className='message assistant';pending.textContent='Working through your course material…';messages.appendChild(pending);messages.scrollTop=messages.scrollHeight;try{const response=await fetch('assistant.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({message,conversation_id:conversationId})});const data=await response.json();pending.remove();if(!response.ok)throw new Error(data.error||'The assistant could not respond.');conversationId=data.conversation_id;addMessage('assistant',data.answer,data.sources);if(location.search==='')history.replaceState(null,'','assistant.php?conversation_id='+conversationId)}catch(error){pending.remove();addMessage('assistant',error.message||'The assistant is temporarily unavailable.')}finally{send.disabled=false;input.focus()}});
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script><script src="assets/js/main.js"></script>
</body>
</html>
