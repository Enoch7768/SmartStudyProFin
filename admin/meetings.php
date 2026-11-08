<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\AI\MeetingCompanionService;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;
use App\Core\Url;
use App\Learning\MeetingPlannerService;
$pdo=Database::connect();$user=Auth::user($pdo);
if(!$user||!Auth::hasRole($pdo,(int)$user['id'],'administrator')||!Auth::hasPermission($pdo,(int)$user['id'],'manage_content')){http_response_code(403);exit('Administrator access is required.');}
$service=new MeetingPlannerService($pdo);$error='';$success='';$aiDraft='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 if(!Security::verifyCsrf($_POST['_csrf']??null))$error='Your session expired. Refresh and try again.';
 else try{
  $action=(string)($_POST['action']??'');
  if($action==='offering'){$service->saveOffering((int)($_POST['course_id']??0),(string)($_POST['mode']??''),(string)($_POST['title']??''),(string)($_POST['description']??''),(int)($_POST['price_ugx']??0),($_POST['capacity']??'')===''?null:(int)$_POST['capacity'],isset($_POST['published']));$success='Course offering saved.';}
  elseif($action==='session'){$service->createSession((int)$user['id'],(int)($_POST['offering_id']??0),$_POST);$success='Session scheduled.';}
  elseif($action==='attendance'){$service->recordAttendance((int)$user['id'],(int)($_POST['session_id']??0),(int)($_POST['user_id']??0),(string)($_POST['status']??'pending'),(string)($_POST['reason']??''));$success='Attendance updated and audited.';}
  elseif($action==='cancel'){$service->cancelSession((int)$user['id'],(int)($_POST['session_id']??0));$success='Session cancelled.';}
  elseif($action==='companion'){$ai=new MeetingCompanionService($pdo,(string)Config::get('ai.groq_api_key',''),(string)Config::get('ai.groq_model','llama-3.3-70b-versatile'),(int)Config::get('ai.groq_input_budget',6000),(int)Config::get('ai.groq_output_budget',1200),(int)Config::get('ai.groq_daily_requests',20),(int)Config::get('ai.groq_daily_tokens',30000),(bool)Config::get('ai.groq_enabled',true));$draft=$ai->generate((int)$user['id'],($_POST['session_id']??'')===''?null:(int)$_POST['session_id'],(string)($_POST['task']??''),(string)($_POST['notes']??''),(string)($_POST['course_context']??''));$aiDraft=$draft['answer'];}
  else throw new RuntimeException('Unknown meeting planner action.');
 }catch(Throwable $e){$error=$e->getMessage();}
}
$courses=$service->courses();$offerings=$service->offerings();
$sessions=$pdo->query("SELECT s.*,o.title AS offering_title,o.mode,c.title AS course_title FROM course_sessions s JOIN course_offerings o ON o.id=s.offering_id JOIN cms_courses c ON c.id=o.course_id ORDER BY s.starts_at_utc DESC LIMIT 100")->fetchAll();
$csrf=Security::csrfToken();$esc=static fn($v)=>Security::escape((string)$v);
?>
<!doctype html><html lang="en"><head><base href="<?= $esc(Url::to('/')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Meeting planner · SmartStudyPro</title><link rel="stylesheet" href="<?= $esc(Url::to('/assets/vendor/bootstrap/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= $esc(Url::to('/assets/css/device-responsive.css')) ?>"><style>body{background:#f5f6fb;color:#182033;font-family:system-ui,sans-serif}.wrap{max-width:1180px;margin:auto;padding:32px 18px}.panel{background:white;border:1px solid #e3e7ef;border-radius:16px;padding:22px;margin:18px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.field label{font-size:13px;font-weight:650;margin-bottom:6px}.field input,.field select,.field textarea{width:100%;padding:10px;border:1px solid #dce1ea;border-radius:9px}.btn-main{background:#ff7a00;color:white;border:0;border-radius:9px;padding:11px 16px;font-weight:700}.muted{color:#687386;font-size:13px}.session{border-top:1px solid #edf0f5;padding:16px 0}.alert{padding:12px;border-radius:9px;background:#fff1e8}</style></head><body>
<?php include dirname(__DIR__).'/nav.php'; ?><main class="wrap"><a href="<?= $esc(Url::to('/admin/cms.php')) ?>">← Administration</a><h1>Course delivery & meeting planner</h1><p class="muted">Manage self-paced, live online and in-person offerings. Learner join links are protected; host links remain administrator-only.</p>
<?php if($error):?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?= $esc($success) ?></div><?php endif;?>
<section class="panel"><h2>Create a course offering</h2><form method="post"><input type="hidden" name="_csrf" value="<?= $esc($csrf) ?>"><input type="hidden" name="action" value="offering"><div class="grid">
<div class="field"><label>Course</label><select name="course_id" required><?php foreach($courses as $c):?><option value="<?= (int)$c['id'] ?>"><?= $esc($c['title']) ?></option><?php endforeach;?></select></div>
<div class="field"><label>Delivery mode</label><select name="mode"><option value="self_paced">Self-paced in SmartStudyPro</option><option value="live_online">Live online (Zoom / Meet)</option><option value="in_person">In-person</option></select></div>
<div class="field"><label>Offering name</label><input name="title" required maxlength="160" placeholder="e.g. Evening live cohort"></div>
<div class="field"><label>Price (UGX)</label><input type="number" name="price_ugx" min="0" value="0"></div>
<div class="field"><label>Capacity (optional)</label><input type="number" name="capacity" min="1"></div>
<div class="field"><label>Description</label><input name="description"></div>
</div><label><input type="checkbox" name="published" value="1"> Publish offering</label><p><button class="btn-main">Save offering</button></p></form></section>
<section class="panel"><h2>Schedule a live or in-person session</h2><form method="post"><input type="hidden" name="_csrf" value="<?= $esc($csrf) ?>"><input type="hidden" name="action" value="session"><div class="grid">
<div class="field"><label>Offering</label><select name="offering_id" required><?php foreach($offerings as $o): if($o['mode']==='self_paced')continue;?><option value="<?= (int)$o['id'] ?>"><?= $esc($o['course_title'].' · '.$o['title'].' ('.$o['mode'].')') ?></option><?php endforeach;?></select></div>
<div class="field"><label>Session title</label><input name="title" required></div>
<div class="field"><label>Provider</label><select name="provider"><option value="zoom">Zoom</option><option value="google_meet">Google Meet</option><option value="in_person">In person</option></select></div>
<div class="field"><label>Timezone (IANA)</label><input name="timezone" value="Africa/Kampala" required></div>
<div class="field"><label>Starts (local time)</label><input type="datetime-local" name="starts_at" required></div><div class="field"><label>Ends (local time)</label><input type="datetime-local" name="ends_at" required></div>
<div class="field"><label>Capacity override</label><input type="number" name="capacity" min="1"></div>
<div class="field"><label>Provider meeting ID (optional)</label><input name="provider_meeting_id"></div>
<div class="field"><label>Learner join URL</label><input type="url" name="join_url" placeholder="https://..."></div>
<div class="field"><label>Host URL (private)</label><input type="url" name="host_url" placeholder="https://..."></div>
<div class="field"><label>Venue name</label><input name="venue_name"></div><div class="field"><label>Venue address</label><input name="venue_address"></div>
<div class="field"><label>Venue notes</label><input name="venue_notes"></div></div><div class="field"><label>Agenda</label><textarea name="agenda" rows="3"></textarea></div><p><button class="btn-main">Schedule session</button></p></form></section>
<section class="panel"><h2>Meeting companion · Groq</h2><p class="muted">Creates instructor-reviewed drafts only. It does not join, record, listen to or transcribe calls.</p><form method="post"><input type="hidden" name="_csrf" value="<?= $esc($csrf) ?>"><input type="hidden" name="action" value="companion"><div class="field"><label>Related session (optional)</label><select name="session_id"><option value="">General course preparation</option><?php foreach($sessions as $s):?><option value="<?= (int)$s['id'] ?>"><?= $esc($s['course_title'].' · '.$s['title']) ?></option><?php endforeach;?></select></div><div class="field"><label>What should it prepare?</label><input name="task" required maxlength="4000" placeholder="Create a 60-minute agenda and discussion questions"></div><div class="field"><label>Instructor notes</label><textarea name="notes" rows="3"></textarea></div><div class="field"><label>Course context</label><textarea name="course_context" rows="3"></textarea></div><p><button class="btn-main">Generate draft</button></p></form><?php if($aiDraft!==''):?><h3>Draft — review before sharing</h3><pre style="white-space:pre-wrap"><?= $esc($aiDraft) ?></pre><?php endif;?></section>
<section class="panel"><h2>Scheduled sessions & attendance</h2><?php foreach($sessions as $s):?><article class="session"><div class="d-flex justify-content-between flex-wrap gap-2"><div><strong><?= $esc($s['course_title'].' · '.$s['title']) ?></strong><div class="muted"><?= $esc($s['mode'].' · '.$s['provider'].' · '.$s['starts_at_utc'].' UTC · '.$s['timezone'].' · '.$s['status']) ?></div></div><?php if(in_array($s['status'],['scheduled','rescheduled'],true)):?><form method="post"><input type="hidden" name="_csrf" value="<?= $esc($csrf) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>"><button class="btn btn-outline-danger btn-sm">Cancel</button></form><?php endif;?></div>
<?php foreach($service->roster((int)$s['id']) as $person):?><form method="post" class="d-flex flex-wrap gap-2 align-items-center mt-2"><input type="hidden" name="_csrf" value="<?= $esc($csrf) ?>"><input type="hidden" name="action" value="attendance"><input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="user_id" value="<?= (int)$person['user_id'] ?>"><span><?= $esc($person['name']) ?></span><select name="status"><option>pending</option><option>present</option><option>late</option><option>absent</option><option>excused</option></select><input name="reason" placeholder="Correction reason" style="max-width:220px"><button class="btn btn-sm btn-outline-primary">Save attendance</button></form><?php endforeach;?></article><?php endforeach;?></section></main></body></html>