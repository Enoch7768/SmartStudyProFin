<?php

declare(strict_types=1);

$application = require dirname(__DIR__) . '/bootstrap/app.php';

use App\Cms\ContentRepository;
use App\Cms\MediaManager;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, private');

$pdo = Database::connect();
$repo = new ContentRepository($pdo);
$media = new MediaManager($pdo, (string) Config::get('storage.path'));
$definitions = [
    'courses' => ['Courses', ['title'=>'Course title','slug'=>'URL slug','description_html'=>'Short description','content_html'=>'Full course content','price_ugx'=>'Price (UGX)','category'=>'Category','image_media_id'=>'Course image','seo_title'=>'SEO title','seo_description'=>'SEO description','status'=>'Status','sort_order'=>'Display order']],
    'chapters' => ['Chapters', ['course_id'=>'Course','title'=>'Chapter title','sort_order'=>'Display order']],
    'lessons' => ['Lessons', ['chapter_id'=>'Chapter','title'=>'Lesson title','content_html'=>'Lesson content','video_url'=>'Video URL','video_media_id'=>'Video file','resource_media_id'=>'PDF / lesson resource','resource_title'=>'Resource display title','quiz_time_limit_minutes'=>'Quiz time limit (minutes; 0 = untimed)','quiz_pass_mark'=>'Quiz pass mark (%)','sort_order'=>'Display order','status'=>'Status']],
    'questions' => ['Quiz questions', ['lesson_id'=>'Lesson','content'=>'Lesson description','question'=>'Question','options_json'=>'Options (one per line)','correct_answer'=>'Correct answer','sort_order'=>'Display order']],
    'products' => ['Products', ['title'=>'Product name','slug'=>'URL slug','description_html'=>'Description','price_ugx'=>'Price (UGX)','category'=>'Category','image_media_id'=>'Product image','product_type'=>'Product type','product_file_media_id'=>'Download file','status'=>'Status','sort_order'=>'Display order']]
];
$labels = ['courses'=>'Courses','chapters'=>'Chapters','lessons'=>'Lessons','questions'=>'Quiz questions','products'=>'Products','pages'=>'Pages','settings'=>'Contact details','media'=>'Media library'];
$section = (string) ($_GET['section'] ?? 'courses');
if (!isset($labels[$section])) $section = 'courses';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'login') {
        if (Auth::attempt($pdo, (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            $candidate = Auth::user($pdo);
            if ($candidate && Auth::hasRole($pdo, (int) $candidate['id'], 'administrator')) Security::redirect('cms.php');
            Auth::logout();
            $error = 'This account does not have administrator access.';
        } else {
            $error = 'The email or password is incorrect, or sign-in is temporarily limited.';
        }
    } else {
        $candidate = Auth::user($pdo);
        if (!$candidate || !Auth::hasRole($pdo, (int) $candidate['id'], 'administrator') || !Auth::hasPermission($pdo, (int) $candidate['id'], 'manage_content')) {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'logout') {
                Auth::logout();
                Security::redirect('cms.php');
            }
            if ($action === 'save') {
                $type = (string) ($_POST['type'] ?? '');
                if (!isset($definitions[$type])) throw new RuntimeException('Unsupported content type.');
                $data = $_POST;
                if ($type === 'questions') $data['options_json'] = preg_split('/\r\n|\r|\n/', (string) ($_POST['options_json'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                $id = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
                $repo->save($type, $id, $data);
                Security::redirect('cms.php?section=' . rawurlencode($type) . '&saved=1');
            }
            if ($action === 'delete') {
                $type = (string) ($_POST['type'] ?? '');
                if (!isset($definitions[$type])) throw new RuntimeException('Unsupported content type.');
                $repo->delete($type, (int) ($_POST['id'] ?? 0));
                Security::redirect('cms.php?section=' . rawurlencode($type) . '&deleted=1');
            }
            if ($action === 'save_page') {
                $repo->savePage((string) ($_POST['page_key'] ?? ''), $_POST);
                Security::redirect('cms.php?section=pages&saved=1');
            }
            if ($action === 'save_settings') {
                $repo->saveSettings($_POST);
                Security::redirect('cms.php?section=settings&saved=1');
            }
            if ($action === 'upload') {
                $repo->settings();
                $media->upload($_FILES['file'] ?? [], (int) $candidate['id'], (string) ($_POST['alt_text'] ?? ''), (string) ($_POST['visibility'] ?? 'private'));
                Security::redirect('cms.php?section=media&saved=1');
            }
            throw new RuntimeException('Unknown action.');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$user = Auth::user($pdo);
$isAdmin = $user && Auth::hasRole($pdo, (int) $user['id'], 'administrator') && Auth::hasPermission($pdo, (int) $user['id'], 'manage_content');
if (!$isAdmin):
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin sign in · SmartStudyPro</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><style>
:root{--navy:#0C086B;--orange:#FF7A00;--ink:#182033;--muted:#667085;--line:#e7e9f0}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#f5f6fb;color:var(--ink);font-family:"Plus Jakarta Sans",sans-serif}.card{width:min(100%,430px);background:#fff;border:1px solid var(--line);border-radius:22px;padding:38px;box-shadow:0 22px 60px rgba(7,4,67,.1)}.brand{font:800 25px Outfit,sans-serif;color:var(--navy)}.brand span{color:var(--orange)}h1{font:700 27px Outfit,sans-serif;margin:30px 0 7px}.muted{color:var(--muted);font-size:13px;line-height:1.7;margin:0 0 24px}label{display:block;font-size:13px;font-weight:700;margin:16px 0 7px}input{width:100%;height:48px;border:1px solid var(--line);border-radius:10px;padding:0 13px;font:inherit}button{width:100%;border:0;border-radius:10px;background:var(--orange);color:#fff;padding:14px;font:700 14px "Plus Jakarta Sans",sans-serif;margin-top:22px;cursor:pointer}.error{padding:12px;border-radius:9px;background:#fff1f0;color:#a12c24;font-size:13px}
</style></head><body><main class="card"><div class="brand">SmartStudy<span>Pro</span></div><h1>Admin sign in</h1><p class="muted">Manage your learning content and website information.</p><?php if ($error !== ''): ?><div class="error"><?= Security::escape($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="login"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" required><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Sign in to CMS</button></form></main></body></html>
<?php exit; endif; ?>
<?php

if (isset($_GET['saved'])) $notice = 'Your changes have been saved.';
if (isset($_GET['deleted'])) $notice = 'The content item has been deleted.';
$items = isset($definitions[$section]) ? $repo->all($section) : [];
$edit = isset($definitions[$section], $_GET['edit']) ? $repo->find($section, (int) $_GET['edit']) : null;
$allMedia = $media->all();
$courses = $repo->all('courses');
$chapters = $repo->all('chapters');
$lessons = $repo->all('lessons');
$settings = $repo->settings();
$pages = ['home'=>$repo->page('home'),'about'=>$repo->page('about')];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Content manager · SmartStudyPro</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><style>
:root{--navy:#0C086B;--deep:#070443;--orange:#FF7A00;--ink:#182033;--muted:#667085;--line:#e7e9f0;--bg:#f5f6fb}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px "Plus Jakarta Sans",sans-serif}.top{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 28px}.brand{font:800 22px Outfit,sans-serif;color:var(--navy)}.brand span{color:var(--orange)}.logout{background:none;border:1px solid var(--line);border-radius:9px;padding:9px 14px;color:var(--ink);cursor:pointer}.layout{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:calc(100vh - 72px)}aside{background:var(--deep);padding:25px 14px}.side-label{color:#aeb4e3;font-size:10px;letter-spacing:1.2px;text-transform:uppercase;padding:8px 12px}.side a{display:block;color:#e4e7ff;text-decoration:none;padding:11px 12px;border-radius:9px;margin:3px 0;font-weight:600}.side a.active,.side a:hover{background:rgba(255,255,255,.13);color:#fff}.main{padding:32px;min-width:0}.heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}.heading h1{font:700 28px Outfit,sans-serif;margin:0}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(300px,.7fr);gap:20px;align-items:start}.panel{background:#fff;border:1px solid var(--line);border-radius:15px;padding:20px;min-width:0}.panel h2{font:700 18px Outfit,sans-serif;margin:0 0 17px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:500px}th,td{text-align:left;padding:12px 10px;border-bottom:1px solid #edf0f5;font-size:12px;vertical-align:middle}th{color:var(--muted);font-weight:700}td strong{font-size:13px}.action{color:var(--navy);font-weight:700;text-decoration:none;margin-right:10px}.danger{color:#b42318;background:none;border:0;font:700 12px "Plus Jakarta Sans",sans-serif;cursor:pointer;padding:0}.primary{display:inline-block;background:var(--orange);color:white;border:0;border-radius:9px;padding:11px 15px;font-weight:700;text-decoration:none;cursor:pointer}.field{margin:13px 0}.field label{display:block;font-weight:700;font-size:12px;margin-bottom:7px}.field input,.field textarea,.field select{width:100%;border:1px solid #dfe3ec;border-radius:9px;padding:10px 11px;font:13px "Plus Jakarta Sans",sans-serif;background:#fff;color:var(--ink)}.field textarea{min-height:86px;resize:vertical}.form-actions{display:flex;gap:10px;align-items:center}.cancel{color:var(--muted);text-decoration:none;font-weight:700}.notice{padding:12px 14px;border-radius:10px;background:#eaf8f0;color:#176b43;margin-bottom:18px}.page-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}.media-item{border:1px solid var(--line);border-radius:10px;padding:12px;overflow-wrap:anywhere}.media-item small{color:var(--muted)}@media(max-width:900px){.layout{grid-template-columns:1fr}aside{padding:12px;overflow:auto}.side{display:flex;gap:5px;overflow:auto}.side-label{display:none}.side a{white-space:nowrap}.main{padding:20px}.grid{grid-template-columns:1fr}}@media(max-width:560px){.top{padding:0 16px}.main{padding:14px}.heading{align-items:flex-start;flex-direction:column}.page-grid{grid-template-columns:1fr}}
</style></head><body>
<header class="top"><div class="brand">SmartStudy<span>Pro</span> <span style="color:#8b90a6;font:600 12px 'Plus Jakarta Sans',sans-serif">CONTENT MANAGER</span></div><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="logout"><button class="logout">Sign out · <?= Security::escape($user['name']) ?></button></form></header>
<div class="layout"><aside><div class="side-label">Manage website</div><nav class="side"><?php foreach ($labels as $key=>$label): ?><a class="<?= $section===$key?'active':'' ?>" href="?section=<?= Security::escape($key) ?>"><?= Security::escape($label) ?></a><?php endforeach; ?><a href="orders.php">Orders and payments</a><a href="analytics.php">Learning analytics</a></nav></aside>
<main class="main"><div class="heading"><div><h1><?= Security::escape($labels[$section]) ?></h1><div class="muted">Manage SmartStudyPro website content.</div></div><?php if (isset($definitions[$section])): ?><a class="primary" href="?section=<?= Security::escape($section) ?>&new=1">+ Add new</a><?php endif; ?></div>
<?php if ($notice ?? false): ?><div class="notice"><?= Security::escape($notice) ?></div><?php endif; ?><?php if ($error!==''): ?><div class="notice" style="background:#fff1f0;color:#a12c24"><?= Security::escape($error) ?></div><?php endif; ?>
<?php if (isset($definitions[$section])): ?><div class="grid"><section class="panel"><h2><?= Security::escape($definitions[$section][0]) ?> list</h2><div class="table-wrap"><table><thead><tr><th>Item</th><th>Status / Details</th><th>Actions</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><strong><?= Security::escape($item['title']??$item['question']??'Untitled') ?></strong><br><small class="muted">#<?= (int)$item['id'] ?></small></td><td><?= Security::escape($item['status']??$item['category']??$item['correct_answer']??'') ?></td><td><a class="action" href="?section=<?= Security::escape($section) ?>&edit=<?= (int)$item['id'] ?>">Edit</a><form method="post" style="display:inline" onsubmit="return confirm('Delete this item? Related child content may also be deleted.')"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="<?= Security::escape($section) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="danger">Delete</button></form></td></tr><?php endforeach; ?><?php if (!$items): ?><tr><td colspan="3" class="muted">No content yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if ($edit || isset($_GET['new'])): ?><section class="panel"><h2><?= $edit?'Edit item':'Create item' ?></h2><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="type" value="<?= Security::escape($section) ?>"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : '' ?>"><?php foreach ($definitions[$section][1] as $field=>$label): $value=$edit[$field]??''; ?><div class="field"><label><?= Security::escape($label) ?></label>
<?php if (in_array($field,['course_id','chapter_id','lesson_id','image_media_id','video_media_id','product_file_media_id','resource_media_id'],true)): $options=match($field){'course_id'=>$courses,'chapter_id'=>$chapters,'lesson_id'=>$lessons,default=>$allMedia}; if($field==='resource_media_id') $options=array_values(array_filter($options,static fn($asset)=>$asset['mime_type']==='application/pdf')); if($field==='product_file_media_id') $options=array_values(array_filter($options,static fn($asset)=>$asset['mime_type']==='application/pdf' && $asset['visibility']==='private')); if($field==='video_media_id') $options=array_values(array_filter($options,static fn($asset)=>$asset['mime_type']==='video/mp4')); ?><select name="<?= Security::escape($field) ?>"><option value="">Select…</option><?php foreach($options as $option): ?><option value="<?= (int)$option['id'] ?>" <?= (string)$value===(string)$option['id']?'selected':'' ?>><?= Security::escape($option['title']??$option['original_name']??('Item #'.$option['id'])) ?></option><?php endforeach; ?></select>
<?php elseif($field==='status'): ?><select name="status"><?php foreach(['draft','published','archived'] as $status): ?><option value="<?= $status ?>" <?= $value===$status?'selected':'' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select>
<?php elseif($field==='product_type'): ?><select name="product_type"><option value="physical" <?= $value==='physical'?'selected':'' ?>>Physical</option><option value="online" <?= $value==='online'?'selected':'' ?>>Online</option></select>
<?php elseif(in_array($field,['description_html','content_html','seo_description'],true)): ?><textarea name="<?= Security::escape($field) ?>"><?= Security::escape($value) ?></textarea>
<?php elseif($field==='options_json'): $opts=json_decode((string)$value,true); ?><textarea name="options_json"><?= Security::escape(is_array($opts)?implode("\n",$opts):$value) ?></textarea><small>Enter one answer option per line.</small>
<?php else: ?><input name="<?= Security::escape($field) ?>" value="<?= Security::escape($value) ?>" <?= in_array($field,['price_ugx','sort_order','quiz_time_limit_minutes','quiz_pass_mark'],true)?'type="number" min="0"':'type="text"' ?> <?= in_array($field,['title','question'],true)?'required':'' ?> <?= $field==='quiz_time_limit_minutes'?'max="180"':($field==='quiz_pass_mark'?'min="1" max="100"':'') ?>><?php endif; ?></div><?php endforeach; ?><div class="form-actions"><button class="primary">Save changes</button><a class="cancel" href="?section=<?= Security::escape($section) ?>">Cancel</a></div></form></section><?php endif; ?></div>
<?php elseif($section==='pages'): ?><div class="page-grid"><?php foreach($pages as $key=>$page): ?><section class="panel"><h2><?= $key==='home'?'Home page':'About page' ?></h2><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="save_page"><input type="hidden" name="page_key" value="<?= Security::escape($key) ?>"><?php foreach(['title'=>'Main title','subtitle'=>'Subtitle','body_html'=>'Page content'] as $field=>$label): ?><div class="field"><label><?= Security::escape($label) ?></label><?php if($field==='body_html'): ?><textarea name="<?= $field ?>"><?= Security::escape($page[$field]??'') ?></textarea><?php else: ?><input name="<?= $field ?>" value="<?= Security::escape($page[$field]??'') ?>"><?php endif; ?></div><?php endforeach; ?><div class="field"><label>Hero / page image</label><select name="hero_media_id"><option value="">Use the built-in page image</option><?php foreach($allMedia as $asset): if(!str_starts_with($asset['mime_type'],'image/')) continue; ?><option value="<?= (int)$asset['id'] ?>" <?= (string)($page['hero_media_id']??'')===(string)$asset['id']?'selected':'' ?>><?= Security::escape($asset['original_name']) ?> · #<?= (int)$asset['id'] ?></option><?php endforeach; ?></select><small>Choose an image uploaded with Public image visibility.</small></div><div class="field"><label>Status</label><select name="status"><option value="published">Published</option><option value="draft" <?= ($page['status']??'')==='draft'?'selected':'' ?>>Draft</option></select></div><button class="primary">Save page</button></form></section><?php endforeach; ?></div>
<?php elseif($section==='settings'): ?><section class="panel" style="max-width:650px"><h2>Website contact details</h2><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="save_settings"><?php foreach(['contact.phone'=>'Phone number','contact.email'=>'Email address','contact.address'=>'Address'] as $key=>$label): ?><div class="field"><label><?= Security::escape($label) ?></label><input name="<?= Security::escape($key) ?>" value="<?= Security::escape($settings[$key]??'') ?>"></div><?php endforeach; ?><button class="primary">Save contact details</button></form></section>
<?php else: ?><div class="grid"><section class="panel"><h2>Upload media</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="upload"><div class="field"><label>Choose file</label><input type="file" name="file" required accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,audio/mpeg"></div><div class="field"><label>Alternative text</label><input name="alt_text" maxlength="240"></div><div class="field"><label>Visibility</label><select name="visibility"><option value="private">Private</option><option value="public">Public image</option></select></div><button class="primary">Upload file</button><p class="muted">Maximum 50 MB. Public visibility is allowed only for images.</p></form></section><section class="panel"><h2>Media library</h2><div class="media-grid"><?php foreach($allMedia as $asset): ?><div class="media-item"><strong><?= Security::escape($asset['original_name']) ?></strong><br><small><?= Security::escape($asset['mime_type']) ?> · <?= number_format((int)$asset['file_size']/1024,1) ?> KB · <?= Security::escape($asset['visibility']) ?></small><br><small>Media ID: <?= (int)$asset['id'] ?></small></div><?php endforeach; ?><?php if(!$allMedia): ?><p class="muted">No uploaded files yet.</p><?php endif; ?></div></section></div><?php endif; ?>
</main></div></body></html>