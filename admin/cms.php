<?php

declare(strict_types=1);

$application = require dirname(__DIR__) . '/bootstrap/app.php';

use App\Cms\ContentRepository;
use App\Cms\MediaManager;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;
use App\Identity\LegalAcceptanceService;

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
    'products' => ['Products', ['title'=>'Product name','slug'=>'URL slug','description_html'=>'Description','price_ugx'=>'Legacy base price (UGX)','read_price_ugx'=>'Read / view price (UGX)','download_price_ugx'=>'Download price (UGX)','stock_quantity'=>'Stock quantity (blank = unlimited)','category'=>'Category','image_media_id'=>'Product image','product_type'=>'Product type','product_file_media_id'=>'Download file','status'=>'Status','sort_order'=>'Display order']]
];
$labels = ['courses'=>'Courses','chapters'=>'Chapters','lessons'=>'Lessons','questions'=>'Quiz questions','products'=>'Products','pages'=>'Pages','settings'=>'Contact details','media'=>'Media library','enrolments'=>'Student enrolments'];
$section = (string) ($_GET['section'] ?? 'courses');
if (!isset($labels[$section])) $section = 'courses';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'login') {
        if (Auth::attempt($pdo, (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            $candidate = Auth::user($pdo);
            if ($candidate && Auth::hasRole($pdo, (int) $candidate['id'], 'administrator')) {
                if (!(new LegalAcceptanceService($pdo))->hasCurrentAcceptance((int) $candidate['id'])) {
                    $_SESSION['legal_redirect'] = 'admin/cms.php';
                    Security::redirect('../legal-consent.php');
                }
                Security::redirect('cms.php');
            }
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
            if ($action === 'set_enrolment') {
                $enrolmentId = filter_var($_POST['enrolment_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
                $targetStatus = (string) ($_POST['status'] ?? '');
                if ($enrolmentId < 1 || !in_array($targetStatus, ['active', 'revoked'], true)) throw new RuntimeException('Choose a valid enrolment action.');
                $update = $pdo->prepare("UPDATE course_enrolments SET status = :status, updated_at = :updated_at WHERE id = :id AND status IN ('active', 'revoked')");
                $update->execute(['status' => $targetStatus, 'updated_at' => gmdate('Y-m-d H:i:s'), 'id' => $enrolmentId]);
                if ($update->rowCount() !== 1) throw new RuntimeException('This enrolment could not be updated. Completed enrolments are kept as records.');
                Security::redirect('cms.php?section=enrolments&saved=1');
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
                $files = [];
                if (isset($_FILES['files']) && is_array($_FILES['files']['name'] ?? null)) {
                    foreach ($_FILES['files']['name'] as $index => $name) {
                        $files[] = [
                            'name' => $name,
                            'type' => $_FILES['files']['type'][$index] ?? '',
                            'tmp_name' => $_FILES['files']['tmp_name'][$index] ?? '',
                            'error' => $_FILES['files']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                            'size' => $_FILES['files']['size'][$index] ?? 0
                        ];
                    }
                } elseif (isset($_FILES['file'])) {
                    $files[] = $_FILES['file'];
                }
                if (!$files || count($files) > 20) throw new RuntimeException('Choose between 1 and 20 files per upload.');
                $uploaded = 0;
                foreach ($files as $file) {
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    $media->upload($file, (int) $candidate['id'], (string) ($_POST['alt_text'] ?? ''), (string) ($_POST['visibility'] ?? 'private'));
                    $uploaded++;
                }
                if ($uploaded < 1) throw new RuntimeException('Choose at least one file to upload.');
                Security::redirect('cms.php?section=media&uploaded=' . $uploaded);
            }
            if ($action === 'media_update') {
                $media->updateMetadata((int) ($_POST['media_id'] ?? 0), (string) ($_POST['alt_text'] ?? ''), (string) ($_POST['visibility'] ?? 'private'));
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
if ($isAdmin && !(new LegalAcceptanceService($pdo))->hasCurrentAcceptance((int) $user['id'])) {
    $_SESSION['legal_redirect'] = 'admin/cms.php';
    Security::redirect('../legal-consent.php');
}
if (!$isAdmin):
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin sign in · SmartStudyPro</title><link rel="icon" href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.ico'), ENT_QUOTES, 'UTF-8') ?>"><script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/theme.js'), ENT_QUOTES, 'UTF-8') ?>"></script><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/admin-cms-login.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>">
</head><body class="ssp-cms-login"><button type="button" id="cms-theme-toggle" class="theme-toggle" aria-pressed="false">Dark mode</button><main class="card"><div class="brand">SmartStudy<span>Pro</span></div><h1>Admin sign in</h1><p class="muted">Manage your learning content and website information.</p><?php if ($error !== ''): ?><div class="error"><?= Security::escape($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="login"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" required><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Sign in to CMS</button></form></main></body></html>
<?php exit; endif; ?>
<?php

if (isset($_GET['saved'])) $notice = 'Your changes have been saved.';
if (isset($_GET['uploaded'])) $notice = 'Uploaded ' . max(0, (int) $_GET['uploaded']) . ' file(s) successfully.';
if (isset($_GET['deleted'])) $notice = 'The content item has been deleted.';
$items = isset($definitions[$section]) ? $repo->all($section) : [];
$enrolments = [];
if ($section === 'enrolments') {
    $enrolments = $pdo->query("SELECT e.id, e.status, e.source, e.enrolled_at, e.completed_at, u.name AS student_name, u.email AS student_email, c.title AS course_title FROM course_enrolments e INNER JOIN users u ON u.id = e.user_id INNER JOIN cms_courses c ON c.id = e.course_id ORDER BY e.enrolled_at DESC, e.id DESC LIMIT 200")->fetchAll();
}
$edit = isset($definitions[$section], $_GET['edit']) ? $repo->find($section, (int) $_GET['edit']) : null;
$allMedia = $media->all();
$courses = $repo->all('courses');
$chapters = $repo->all('chapters');
$lessons = $repo->all('lessons');
$settings = $repo->settings();
$pages = ['home'=>$repo->page('home'),'about'=>$repo->page('about')];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Content manager · SmartStudyPro</title><link rel="icon" href="<?= htmlspecialchars(\App\Core\Url::to('/favicon.ico'), ENT_QUOTES, 'UTF-8') ?>"><script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/theme.js'), ENT_QUOTES, 'UTF-8') ?>"></script><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/admin-cms.css'), ENT_QUOTES, 'UTF-8') ?>"><link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Url::to('/assets/css/device-responsive.css'), ENT_QUOTES, 'UTF-8') ?>"></head><body class="ssp-cms-admin">
<header class="top"><div class="brand">SmartStudy<span>Pro</span> <span class="cms-brand-label">CONTENT MANAGER</span></div><div class="top-actions"><button type="button" id="cms-theme-toggle" class="logout" aria-pressed="false" data-theme-endpoint="../theme-preference.php" data-csrf="<?= Security::escape(Security::csrfToken()) ?>">Dark mode</button><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="logout"><button class="logout">Sign out · <?= Security::escape($user['name']) ?></button></form></div></header>
<div class="layout"><aside><div class="side-label">Manage website</div><nav class="side"><?php foreach ($labels as $key=>$label): ?><a class="<?= $section===$key?'active':'' ?>" href="?section=<?= Security::escape($key) ?>"><?= Security::escape($label) ?></a><?php endforeach; ?><a href="orders.php">Orders and payments</a><a href="analytics.php">Learning analytics</a><a href="../admin/meetings">Course delivery &amp; meetings</a><a href="../admin/messages">Contact inbox</a></nav></aside>
<main class="main"><div class="heading"><div><h1><?= Security::escape($labels[$section]) ?></h1><div class="muted">Manage SmartStudyPro website content.</div></div><?php if (isset($definitions[$section])): ?><?php if ($section === 'products'): ?><a class="action" href="product-assets.php">Manage product files &amp; images</a><?php endif; ?><a class="primary" href="?section=<?= Security::escape($section) ?>&new=1">+ Add new</a><?php endif; ?></div>
<?php if ($notice ?? false): ?><div class="notice"><?= Security::escape($notice) ?></div><?php endif; ?><?php if ($error!==''): ?><div class="notice is-error"><?= Security::escape($error) ?></div><?php endif; ?>
<?php if (isset($definitions[$section])): ?><div class="grid"><section class="panel"><h2><?= Security::escape($definitions[$section][0]) ?> list</h2><div class="table-wrap"><table><thead><tr><th>Item</th><th>Status / Details</th><th>Actions</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><strong><?= Security::escape($item['title']??$item['question']??'Untitled') ?></strong><br><small class="muted">#<?= (int)$item['id'] ?></small></td><td><?= Security::escape($item['status']??$item['category']??$item['correct_answer']??'') ?></td><td><a class="action" href="?section=<?= Security::escape($section) ?>&edit=<?= (int)$item['id'] ?>">Edit</a><form method="post" class="cms-inline-form" onsubmit="return confirm('Delete this item? Related child content may also be deleted.')"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="<?= Security::escape($section) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="danger">Delete</button></form></td></tr><?php endforeach; ?><?php if (!$items): ?><tr><td colspan="3" class="muted">No content yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if ($edit || isset($_GET['new'])): ?><section class="panel"><h2><?= $edit?'Edit item':'Create item' ?></h2><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="type" value="<?= Security::escape($section) ?>"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : '' ?>"><?php foreach ($definitions[$section][1] as $field=>$label): $value=$edit[$field]??''; ?><div class="field"><label><?= Security::escape($label) ?></label>
<?php if (in_array($field,['course_id','chapter_id','lesson_id','image_media_id','video_media_id','product_file_media_id','resource_media_id'],true)): $options=match($field){'course_id'=>$courses,'chapter_id'=>$chapters,'lesson_id'=>$lessons,default=>$allMedia}; if($field==='image_media_id') $options=array_values(array_filter($options,static fn($asset)=>$asset['visibility']==='public' && in_array($asset['mime_type'],['image/jpeg','image/png','image/webp','image/gif'],true))); if($field==='resource_media_id') $options=array_values(array_filter($options,static fn($asset)=>$asset['mime_type']==='application/pdf' && $asset['visibility']==='private')); if($field==='product_file_media_id') $options=array_values(array_filter($options,static fn($asset)=>$asset['mime_type']==='application/pdf' && $asset['visibility']==='private')); if($field==='video_media_id') $options=array_values(array_filter($options,static fn($asset)=>$asset['mime_type']==='video/mp4' && $asset['visibility']==='private')); ?><select name="<?= Security::escape($field) ?>"><option value="">Select…</option><?php foreach($options as $option): ?><option value="<?= (int)$option['id'] ?>" <?= (string)$value===(string)$option['id']?'selected':'' ?>><?= Security::escape($option['title']??$option['original_name']??('Item #'.$option['id'])) ?></option><?php endforeach; ?></select>
<?php elseif($field==='status'): ?><select name="status"><?php foreach(['draft','published','archived'] as $status): ?><option value="<?= $status ?>" <?= $value===$status?'selected':'' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select>
<?php elseif($field==='product_type'): ?><select name="product_type"><option value="physical" <?= $value==='physical'?'selected':'' ?>>Physical</option><option value="online" <?= $value==='online'?'selected':'' ?>>Online</option></select>
<?php elseif(in_array($field,['description_html','content_html','seo_description'],true)): ?><textarea name="<?= Security::escape($field) ?>"><?= Security::escape($value) ?></textarea>
<?php elseif($field==='options_json'): $opts=json_decode((string)$value,true); ?><textarea name="options_json"><?= Security::escape(is_array($opts)?implode("\n",$opts):$value) ?></textarea><small>Enter one answer option per line.</small>
<?php else: ?><input name="<?= Security::escape($field) ?>" value="<?= Security::escape($value) ?>" <?= in_array($field,['price_ugx','read_price_ugx','download_price_ugx','stock_quantity','sort_order','quiz_time_limit_minutes','quiz_pass_mark'],true)?'type="number" min="0"':'type="text"' ?> <?= in_array($field,['title','question'],true)?'required':'' ?> <?= $field==='quiz_time_limit_minutes'?'max="180"':($field==='quiz_pass_mark'?'min="1" max="100"':'') ?>><?php endif; ?></div><?php endforeach; ?><div class="form-actions"><button class="primary">Save changes</button><a class="cancel" href="?section=<?= Security::escape($section) ?>">Cancel</a></div></form></section><?php endif; ?></div>
<?php elseif($section==='enrolments'): ?><section class="panel"><h2>Student enrolments</h2><p class="muted">Review course access. Revoking an enrolment blocks lessons and private resources immediately. Completed records remain unchanged.</p><div class="table-wrap"><table><thead><tr><th>Student</th><th>Course</th><th>Enrolled</th><th>Source</th><th>Status</th><th>Access</th></tr></thead><tbody><?php foreach($enrolments as $enrolment): ?><tr><td><strong><?= Security::escape($enrolment['student_name']) ?></strong><br><small class="muted"><?= Security::escape($enrolment['student_email']) ?></small></td><td><?= Security::escape($enrolment['course_title']) ?></td><td><?= Security::escape(date('d M Y', strtotime($enrolment['enrolled_at']))) ?></td><td><?= Security::escape(ucfirst($enrolment['source'])) ?></td><td><?= Security::escape(ucfirst($enrolment['status'])) ?></td><td><?php if ($enrolment['status'] !== 'completed'): ?><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="set_enrolment"><input type="hidden" name="enrolment_id" value="<?= (int)$enrolment['id'] ?>"><input type="hidden" name="status" value="<?= $enrolment['status']==='revoked'?'active':'revoked' ?>"><button class="<?= $enrolment['status']==='revoked'?'primary':'danger' ?>" type="submit"><?= $enrolment['status']==='revoked'?'Restore access':'Revoke access' ?></button></form><?php else: ?><span class="muted">Completed</span><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$enrolments): ?><tr><td colspan="6" class="muted">No enrolments yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif($section==='pages'): ?><div class="page-grid"><?php foreach($pages as $key=>$page): ?><section class="panel"><h2><?= $key==='home'?'Home page':'About page' ?></h2><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="save_page"><input type="hidden" name="page_key" value="<?= Security::escape($key) ?>"><?php foreach(['title'=>'Main title','subtitle'=>'Subtitle','body_html'=>'Page content'] as $field=>$label): ?><div class="field"><label><?= Security::escape($label) ?></label><?php if($field==='body_html'): ?><textarea name="<?= $field ?>"><?= Security::escape($page[$field]??'') ?></textarea><?php else: ?><input name="<?= $field ?>" value="<?= Security::escape($page[$field]??'') ?>"><?php endif; ?></div><?php endforeach; ?><div class="field"><label>Hero / page image</label><select name="hero_media_id"><option value="">Use the built-in page image</option><?php foreach($allMedia as $asset): if($asset['visibility']!=='public'||!in_array($asset['mime_type'],['image/jpeg','image/png','image/webp','image/gif'],true)) continue; ?><option value="<?= (int)$asset['id'] ?>" <?= (string)($page['hero_media_id']??'')===(string)$asset['id']?'selected':'' ?>><?= Security::escape($asset['original_name']) ?> · #<?= (int)$asset['id'] ?></option><?php endforeach; ?></select><small>Choose an image uploaded with Public image visibility.</small></div><div class="field"><label>Status</label><select name="status"><option value="published">Published</option><option value="draft" <?= ($page['status']??'')==='draft'?'selected':'' ?>>Draft</option></select></div><button class="primary">Save page</button></form></section><?php endforeach; ?></div>
<?php elseif($section==='settings'): ?><section class="panel cms-settings-panel"><h2>Website settings</h2><form method="post"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="save_settings"><?php foreach(['contact.phone'=>'Phone number','contact.email'=>'Email address','contact.address'=>'Address'] as $key=>$label): ?><div class="field"><label><?= Security::escape($label) ?></label><input name="<?= Security::escape($key) ?>" value="<?= Security::escape($settings[$key]??'') ?>"></div><?php endforeach; ?><div class="field"><label for="site-favicon-media-id">Site favicon</label><select id="site-favicon-media-id" name="site.favicon_media_id"><option value="">Use the default SmartStudyPro logo</option><?php foreach($allMedia as $asset): if($asset['visibility']!=='public'||!in_array($asset['mime_type'],['image/jpeg','image/png','image/webp','image/gif'],true)) continue; ?><option value="<?= (int)$asset['id'] ?>" <?= (string)($settings['site.favicon_media_id']??'')===(string)$asset['id']?'selected':'' ?>><?= Security::escape($asset['original_name']) ?> · #<?= (int)$asset['id'] ?></option><?php endforeach; ?></select><small>Only public images can be used as the site favicon. Upload an image in the Media library and set its visibility to Public image first.</small></div><button class="primary">Save website settings</button></form></section>
<?php else: ?>
<section class="media-workspace" id="media-library">
<div class="media-summary">
<div class="media-stat"><span>Total assets</span><strong><?= count($allMedia) ?></strong></div>
<div class="media-stat"><span>Images</span><strong><?= count(array_filter($allMedia, static fn($asset) => str_starts_with($asset['mime_type'], 'image/'))) ?></strong></div>
<div class="media-stat"><span>Private files</span><strong><?= count(array_filter($allMedia, static fn($asset) => $asset['visibility'] === 'private')) ?></strong></div>
<div class="media-stat"><span>Storage tracked</span><strong><?= number_format(array_sum(array_column($allMedia, 'file_size')) / 1048576, 1) ?> MB</strong></div>
</div>
<div class="media-toolbar panel">
<div class="media-search"><label for="media-search">Search library</label><input id="media-search" type="search" placeholder="Search by filename, type or media ID…" autocomplete="off"></div>
<div><label for="media-type-filter">File type</label><select id="media-type-filter"><option value="all">All files</option><option value="image">Images</option><option value="document">Documents</option><option value="video">Videos</option><option value="audio">Audio</option></select></div>
<div><label for="media-visibility-filter">Visibility</label><select id="media-visibility-filter"><option value="all">All visibility</option><option value="public">Public</option><option value="private">Private</option></select></div>
<div><label for="media-sort">Sort by</label><select id="media-sort"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="name">Name A–Z</option><option value="largest">Largest first</option></select></div>
<div class="media-count" id="media-count" aria-live="polite"><?= count($allMedia) ?> assets</div>
</div>
<div class="media-content">
<section class="panel media-upload-panel">
<div class="media-panel-heading"><div><h2>Upload files</h2><p class="muted">Add images, documents, audio and video to your library.</p></div><span class="media-upload-mark"><i class="bi bi-cloud-arrow-up"></i></span></div>
<form method="post" enctype="multipart/form-data" id="media-upload-form">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="upload">
<label class="media-dropzone" id="media-dropzone" for="media-files"><i class="bi bi-cloud-arrow-up"></i><strong>Drop files here or browse</strong><span>Upload up to 20 files at once · 50 MB per file</span><small>Images · PDF · Office documents · Text · Audio · MP4 video</small></label>
<input class="media-file-input" type="file" id="media-files" name="files[]" multiple required accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/rtf,text/rtf,application/vnd.oasis.opendocument.text,video/mp4,audio/mpeg">
<div id="media-selected-files" class="media-selected-files" aria-live="polite"></div>
<div class="field"><label for="media-alt-text">Alternative text (for images)</label><input id="media-alt-text" name="alt_text" maxlength="240" placeholder="Describe the image for accessibility"></div>
<div class="field"><label for="media-visibility">Default visibility</label><select id="media-visibility" name="visibility"><option value="private">Private · restricted access</option><option value="public">Public · images only</option></select></div>
<button class="primary media-upload-button" type="submit"><i class="bi bi-upload"></i> Upload selected files</button>
<p class="muted media-upload-note">Files are stored outside the public web root. Public visibility is accepted only for supported images.</p>
</form>
</section>
<section class="panel media-assets-panel">
<div class="media-panel-heading"><div><h2>Your media</h2><p class="muted">Select an asset to preview it or edit its details.</p></div><div class="media-view-toggle" role="group" aria-label="Media layout"><button type="button" class="active" data-media-view="grid" aria-pressed="true" title="Grid view"><i class="bi bi-grid-3x3-gap"></i></button><button type="button" data-media-view="list" aria-pressed="false" title="List view"><i class="bi bi-list-ul"></i></button></div></div>
<div class="media-empty-state" id="media-empty" <?= $allMedia ? 'hidden' : '' ?>><i class="bi bi-images"></i><strong>Your library is ready</strong><span>Upload your first image or document to see it here.</span></div>
<div class="media-empty-state" id="media-no-results" hidden><i class="bi bi-search"></i><strong>No matching files</strong><span>Try another search or change your filters.</span></div>
<div class="media-grid" id="media-grid">
<?php foreach ($allMedia as $asset):
$mime = (string) $asset['mime_type'];
$kind = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : (str_starts_with($mime, 'audio/') ? 'audio' : 'document'));
$previewable = in_array($mime, ['image/jpeg','image/png','image/webp','image/gif','application/pdf','text/plain','text/csv','audio/mpeg','video/mp4'], true);
$previewUrl = 'media-preview.php?id=' . (int) $asset['id'];
?>
<article class="media-item" data-media-item data-id="<?= (int) $asset['id'] ?>" data-name="<?= Security::escape(strtolower($asset['original_name'])) ?>" data-mime="<?= Security::escape($mime) ?>" data-kind="<?= $kind ?>" data-visibility="<?= Security::escape($asset['visibility']) ?>" data-size="<?= (int) $asset['file_size'] ?>" data-created="<?= Security::escape($asset['created_at']) ?>" data-previewable="<?= $previewable ? '1' : '0' ?>">
<button class="media-preview-trigger" type="button" data-media-preview data-preview-url="<?= Security::escape($previewUrl) ?>" data-name="<?= Security::escape($asset['original_name']) ?>" data-mime="<?= Security::escape($mime) ?>" data-id="<?= (int) $asset['id'] ?>" data-size="<?= (int) $asset['file_size'] ?>" data-alt="<?= Security::escape($asset['alt_text']) ?>" data-visibility="<?= Security::escape($asset['visibility']) ?>" data-uploader="<?= Security::escape($asset['uploader_name'] ?? 'Administrator') ?>" data-created="<?= Security::escape($asset['created_at']) ?>">
<span class="media-thumbnail">
<?php if (str_starts_with($mime, 'image/')): ?><img src="<?= Security::escape($previewUrl) ?>" alt="<?= Security::escape($asset['alt_text'] ?: $asset['original_name']) ?>" loading="lazy"><?php else: ?><span class="media-file-icon"><i class="bi <?= $kind==='video'?'bi-file-earmark-play':($kind==='audio'?'bi-file-earmark-music':($mime==='application/pdf'?'bi-file-earmark-pdf':'bi-file-earmark-text')) ?>"></i><small><?= Security::escape(strtoupper(pathinfo($asset['original_name'], PATHINFO_EXTENSION) ?: 'FILE')) ?></small></span><?php endif; ?>
<span class="media-preview-hint"><i class="bi bi-eye"></i> Preview</span>
</span>
<span class="media-asset-info"><strong title="<?= Security::escape($asset['original_name']) ?>"><?= Security::escape($asset['original_name']) ?></strong><small><?= Security::escape($mime) ?></small><span><?= number_format((int) $asset['file_size'] / 1024, 1) ?> KB <span class="media-dot">·</span> #<?= (int) $asset['id'] ?></span></span>
</button>
<div class="media-item-footer"><span class="media-visibility <?= $asset['visibility']==='public'?'is-public':'is-private' ?>"><?= $asset['visibility']==='public'?'Public':'Private' ?></span><span><?= Security::escape(date('d M Y', strtotime($asset['created_at']))) ?></span><button type="button" class="media-edit-button" data-media-edit aria-label="Edit <?= Security::escape($asset['original_name']) ?>"><i class="bi bi-pencil"></i></button></div>
</article>
<?php endforeach; ?>
</div>
</section>
</div>
</section>
<dialog class="media-dialog" id="media-dialog" aria-labelledby="media-dialog-title"><div class="media-dialog-header"><div><span class="media-dialog-kicker">MEDIA PREVIEW</span><h2 id="media-dialog-title">Asset details</h2></div><button type="button" class="media-dialog-close" data-media-close aria-label="Close preview"><i class="bi bi-x-lg"></i></button></div><div class="media-dialog-preview" id="media-dialog-preview"></div><div class="media-dialog-meta" id="media-dialog-meta"></div><form method="post" class="media-edit-form" id="media-edit-form"><input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>"><input type="hidden" name="action" value="media_update"><input type="hidden" name="media_id" id="media-edit-id"><div class="field"><label for="media-edit-alt">Alternative text</label><input id="media-edit-alt" name="alt_text" maxlength="240"></div><div class="field"><label for="media-edit-visibility">Visibility</label><select id="media-edit-visibility" name="visibility"><option value="private">Private · restricted access</option><option value="public">Public · images only</option></select></div><div class="media-dialog-actions"><a class="cancel" id="media-open-file" href="#" target="_blank" rel="noopener">Open file</a><button class="primary" type="submit">Save details</button></div></form></dialog>
<script src="<?= htmlspecialchars(\App\Core\Url::to('/assets/js/media-library.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
</main></div></body></html>