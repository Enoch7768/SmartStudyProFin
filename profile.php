<?php
require_once 'auth.php';
require_login();

$user = current_user();
$db = auth_db();
ensure_bookings_columns($db);

$uploadError = '';
$uploadSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar_file'])) {
    $file = $_FILES['avatar_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $fileMime = mime_content_type($file['tmp_name']);
        
        if (in_array($fileMime, $allowedTypes)) {
            $uploadDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $avatarPath = 'uploads/avatars/' . $filename;
                
                $stmt = $db->prepare("UPDATE users SET avatar = :avatar WHERE id = :id");
                $stmt->execute([':avatar' => $avatarPath, ':id' => $user['id']]);
                
                $_SESSION['user']['avatar'] = $avatarPath;
                $user['avatar'] = $avatarPath;
                $uploadSuccess = 'Avatar updated successfully!';
            } else {
                $uploadError = 'Failed to save the uploaded file.';
            }
        } else {
            $uploadError = 'Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.';
        }
    } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadError = 'Error uploading file. Maximum allowed size is ' . ini_get('upload_max_filesize');
    }
}

$stmt = $db->prepare("
    SELECT * FROM bookings
    WHERE paid = 1 AND (account_id = :account_id OR email = :email)
    ORDER BY created_at DESC
");
$stmt->execute([':account_id' => $user['id'], ':email' => $user['email']]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$courses = [];
$otherItems = [];
foreach ($purchases as $item) {
    if (stripos($item['service'], 'Course') !== false) {
        $courses[] = $item;
    } else {
        $otherItems[] = $item;
    }
}

$initials = strtoupper(substr(trim($user['name']), 0, 1) ?: 'U');

$phone   = '+256 704 416250';
$email   = 'smartstudypro36@gmail.com';
$address = 'Kampala, Uganda';

$seoTitle       = 'My Profile | SmartStudyPro';
$seoDescription = 'Access your enrolled courses, purchases, and account details on SmartStudyPro.';
$siteUrl        = "https://smartstudypro.com";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta name="author" content="SmartStudyPro">

  <link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="icon">
  <link href="Smart_Study_Logo_Fin-removebg-preview.png" rel="apple-touch-icon">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <style>
    :root {
      --ssp-navy: #0C086B;
      --ssp-navy-dark: #070443;
      --ssp-orange: #FF7A00;
      --ssp-orange-hover: #E06B00;
      --ssp-bg-soft: #F8FAFC;
      --ssp-text-main: #1E293B;
      --ssp-text-muted: #64748B;
      --ssp-card-bg: #FFFFFF;
      --ssp-border-color: #E2E8F0;
    }

    /* Dark Mode Theme Engine (Supports class, data-bs-theme, and data-theme) */
    body.dark-theme, 
    [data-bs-theme="dark"] body, 
    html[data-theme="dark"] body, 
    body[data-theme="dark"] {
      --ssp-bg-soft: #0F172A;
      --ssp-text-main: #F1F5F9;
      --ssp-text-muted: #94A3B8;
      --ssp-card-bg: #1E293B;
      --ssp-border-color: #334155;
      background-color: #0F172A !important;
      color: #F1F5F9 !important;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--ssp-text-main);
      background-color: var(--ssp-bg-soft);
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    h1, h2, h3, h4, h5, .brand-font {
      font-family: 'Outfit', sans-serif;
    }

    .ssp-header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(12px);
      border-bottom: 2px solid rgba(12, 8, 107, 0.08);
      transition: all 0.3s ease;
    }

    body.dark-theme .ssp-header, 
    [data-bs-theme="dark"] .ssp-header,
    html[data-theme="dark"] .ssp-header {
      background: rgba(15, 23, 42, 0.95);
      border-bottom-color: rgba(255, 255, 255, 0.1);
    }

    .navmenu ul {
      list-style: none !important;
      margin: 0 !important;
      padding: 0 !important;
      display: flex !important;
      align-items: center !important;
      gap: 24px;
    }

    .navmenu ul li {
      list-style: none !important;
      margin: 0 !important;
      padding: 0 !important;
    }

    .navmenu ul li a {
      color: var(--ssp-navy);
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none !important;
      display: inline-block;
      white-space: nowrap;
      transition: color 0.2s ease;
    }

    body.dark-theme .navmenu ul li a, 
    [data-bs-theme="dark"] .navmenu ul li a,
    html[data-theme="dark"] .navmenu ul li a {
      color: #F1F5F9;
    }

    .navmenu ul li a:hover,
    .navmenu ul li a.active {
      color: var(--ssp-orange) !important;
      font-weight: 700;
    }

    .dropdown-toggle-no-caret::after {
      display: none !important;
    }

    .dropdown-menu {
      background-color: var(--ssp-card-bg);
      border-color: var(--ssp-border-color);
    }

    .dropdown-menu .dropdown-item {
      color: var(--ssp-text-main);
    }

    .dropdown-menu .dropdown-item:hover {
      background-color: var(--ssp-bg-soft);
      color: var(--ssp-orange);
    }

    body.dark-theme .dropdown-menu, 
    [data-bs-theme="dark"] .dropdown-menu,
    html[data-theme="dark"] .dropdown-menu {
      background-color: #1E293B;
      border-color: #334155;
      color: #F1F5F9;
    }

    body.dark-theme .dropdown-item, 
    [data-bs-theme="dark"] .dropdown-item,
    html[data-theme="dark"] .dropdown-item {
      color: #F1F5F9;
    }

    body.dark-theme .dropdown-item:hover, 
    [data-bs-theme="dark"] .dropdown-item:hover,
    html[data-theme="dark"] .dropdown-item:hover {
      background-color: #334155;
      color: var(--ssp-orange);
    }

    .btn-ssp-primary {
      background-color: var(--ssp-orange);
      color: #FFFFFF;
      font-weight: 700;
      border-radius: 10px;
      padding: 10px 24px;
      border: none;
      box-shadow: 0 4px 14px rgba(255, 122, 0, 0.35);
      transition: all 0.25s ease;
    }

    .btn-ssp-primary:hover {
      background-color: var(--ssp-orange-hover);
      color: #FFFFFF;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 122, 0, 0.45);
    }

    .profile-hero {
      background: linear-gradient(135deg, var(--ssp-navy) 0%, var(--ssp-navy-dark) 100%);
      border-radius: 20px;
      color: #FFFFFF;
      padding: 40px;
      box-shadow: 0 10px 30px rgba(12, 8, 107, 0.15);
    }

    .avatar-wrapper {
      position: relative;
      cursor: pointer;
    }

    .avatar-circle {
      width: 90px; 
      height: 90px; 
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
      display: flex; 
      align-items: center; 
      justify-content: center;
      font-size: 2.2rem; 
      font-weight: 700; 
      color: #FFFFFF;
      border: 3px solid rgba(255,255,255,0.3);
      overflow: hidden;
      position: relative;
    }

    .avatar-circle img { 
      width: 100%; 
      height: 100%; 
      object-fit: cover; 
    }

    .avatar-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.2s ease;
      font-size: 1.2rem;
      color: #FFFFFF;
    }

    .avatar-wrapper:hover .avatar-overlay {
      opacity: 1;
    }

    .ssp-stat-card {
      background: var(--ssp-card-bg); 
      border-radius: 16px; 
      padding: 24px;
      border: 1px solid var(--ssp-border-color);
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
      text-align: center;
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .ssp-item-card {
      background: var(--ssp-card-bg); 
      border-radius: 16px; 
      padding: 20px 24px;
      border: 1px solid var(--ssp-border-color);
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
      display: flex; 
      justify-content: space-between; 
      align-items: center;
      margin-bottom: 16px;
      transition: all 0.25s ease;
    }

    .ssp-item-card:hover {
      border-color: rgba(255, 122, 0, 0.3);
      transform: translateY(-2px);
    }

    .empty-state { 
      text-align: center; 
      padding: 50px 20px; 
      color: var(--ssp-text-muted);
      background: var(--ssp-card-bg);
      border-radius: 16px;
      border: 1px solid var(--ssp-border-color);
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .ssp-heading-title {
      color: var(--ssp-navy);
    }

    body.dark-theme .ssp-heading-title, 
    [data-bs-theme="dark"] .ssp-heading-title,
    html[data-theme="dark"] .ssp-heading-title {
      color: #F1F5F9 !important;
    }

    body.dark-theme .text-muted, 
    [data-bs-theme="dark"] .text-muted,
    html[data-theme="dark"] .text-muted {
      color: var(--ssp-text-muted) !important;
    }

    .ssp-footer {
      background-color: var(--ssp-navy-dark);
      color: #94A3B8;
    }

    .ssp-footer-brand {
      color: #FFFFFF;
      font-size: 1.5rem;
      font-weight: 800;
    }

    .ssp-footer-brand span {
      color: var(--ssp-orange);
    }

    .social-icon-btn {
      width: 38px;
      height: 38px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.08);
      color: #FFFFFF;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .social-icon-btn:hover {
      background: var(--ssp-orange);
      color: #FFFFFF;
    }
  </style>
</head>

<body class="profile-page">

 <?php include 'nav.php'; ?>

  <main class="container py-5">

    <?php if (!empty($uploadError)): ?>
      <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <?= htmlspecialchars($uploadError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($uploadSuccess)): ?>
      <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <?= htmlspecialchars($uploadSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="profile-hero d-flex align-items-center flex-wrap gap-4 mb-5" data-aos="fade-up">
      <form id="avatarForm" action="profile.php" method="POST" enctype="multipart/form-data" class="m-0 p-0">
        <input type="file" id="avatarFileInput" name="avatar_file" accept="image/*" class="d-none" onchange="document.getElementById('avatarForm').submit();">
      </form>
      
      <div class="avatar-wrapper" onclick="document.getElementById('avatarFileInput').click();" title="Click to upload custom avatar">
        <div class="avatar-circle" id="avatarContainer">
          <?php if (!empty($user['avatar'])): ?>
            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" referrerpolicy="no-referrer" onerror="this.remove(); document.getElementById('avatarContainer').textContent='<?= htmlspecialchars($initials) ?>';">
          <?php else: ?>
            <?= htmlspecialchars($initials) ?>
          <?php endif; ?>
        </div>
        <div class="avatar-overlay rounded-circle">
          <i class="bi bi-camera-fill"></i>
        </div>
      </div>

      <div class="flex-grow-1">
        <h2 class="fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h2>
        <p class="mb-0 opacity-75"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($user['email']) ?></p>
        <?php if (!empty($user['google_id'])): ?>
          <span class="badge bg-white text-dark mt-2 fw-semibold"><i class="bi bi-google me-1" style="color: #4285F4;"></i> Connected with Google</span>
        <?php endif; ?>
      </div>
      <a href="logout.php" class="btn btn-outline-light rounded-pill px-4 fw-semibold text-decoration-none">Log Out</a>
    </div>

    <div class="row g-4 mb-5" data-aos="fade-up" data-aos-delay="100">
      <div class="col-md-4">
        <div class="ssp-stat-card">
          <h3 class="fw-bold mb-1" style="color: var(--ssp-orange);"><?= count($courses) ?></h3>
          <p class="text-muted mb-0 fw-semibold">Courses Enrolled</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="ssp-stat-card">
          <h3 class="fw-bold mb-1" style="color: var(--ssp-orange);"><?= count($otherItems) ?></h3>
          <p class="text-muted mb-0 fw-semibold">Other Purchases</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="ssp-stat-card">
          <h3 class="fw-bold mb-1 ssp-heading-title"><?= count($purchases) ?></h3>
          <p class="text-muted mb-0 fw-semibold">Total Orders</p>
        </div>
      </div>
    </div>

    <div data-aos="fade-up" data-aos-delay="200">
      <h4 class="fw-bold mb-3 ssp-heading-title">My Enrolled Courses</h4>
      <?php if (empty($courses)): ?>
        <div class="empty-state">
          <i class="bi bi-mortarboard" style="font-size: 3rem; opacity: 0.4;"></i>
          <p class="mt-3 mb-3">You haven't enrolled in any courses yet.</p>
          <a href="courses.php" class="btn-ssp-primary text-decoration-none px-4 py-2 d-inline-block">Browse Courses</a>
        </div>
      <?php else: ?>
        <?php foreach ($courses as $c): ?>
          <div class="ssp-item-card flex-wrap gap-3">
            <div>
              <h5 class="fw-bold mb-1 ssp-heading-title"><?= htmlspecialchars($c['service']) ?></h5>
              <small class="text-muted"><i class="bi bi-calendar-check me-1"></i>Enrolled on <?= htmlspecialchars(date('M d, Y', strtotime($c['created_at']))) ?></small>
            </div>
            <a href="study.php?course_id=<?= (int) $c['id'] ?>" class="btn-ssp-primary text-decoration-none px-4 py-2">
              <i class="bi bi-play-circle-fill me-1"></i> Continue Learning
            </a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($otherItems)): ?>
      <div class="mt-5" data-aos="fade-up" data-aos-delay="300">
        <h4 class="fw-bold mb-3 ssp-heading-title">Other Purchases</h4>
        <?php foreach ($otherItems as $o): ?>
          <div class="ssp-item-card flex-wrap gap-3">
            <div>
              <h5 class="fw-bold mb-1 ssp-heading-title"><?= htmlspecialchars($o['service']) ?></h5>
              <small class="text-muted"><i class="bi bi-clock-history me-1"></i>Purchased on <?= htmlspecialchars(date('M d, Y', strtotime($o['created_at']))) ?></small>
            </div>
            <?php if (!empty($o['file_path'])): ?>
              <a href="download.php?file=<?= urlencode($o['file_path']) ?>" class="btn btn-dark rounded-3 px-4 py-2 text-decoration-none">
                <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download File
              </a>
            <?php else: ?>
              <span class="badge bg-white text-secondary border px-3 py-2 rounded-2"><i class="bi bi-box-seam me-1"></i> Physical / Service Item</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>

  <footer id="footer" class="ssp-footer pt-5 pb-3">
    <div class="container footer-top mb-4">
      <div class="row gy-4">
        
        <div class="col-lg-4 col-md-6">
          <a href="index.php" class="ssp-footer-brand text-decoration-none mb-3 d-inline-block">
            SmartStudy<span>Pro</span>
          </a>
          <p class="small text-light opacity-75 mb-3">Study Made Simple, Success Made Sure.</p>
          
          <div class="small mb-3">
            <p class="mb-1"><i class="bi bi-geo-alt-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($address) ?></p>
            <p class="mb-1"><i class="bi bi-telephone-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($phone) ?></p>
            <p class="mb-1"><i class="bi bi-envelope-fill me-2" style="color: var(--ssp-orange);"></i><?= htmlspecialchars($email) ?></p>
          </div>

          <div class="d-flex gap-2">
            <a href="#" class="social-icon-btn"><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-facebook"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-instagram"></i></a>
            <a href="#" class="social-icon-btn"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>

        <div class="col-lg-3 col-md-3">
          <h5 class="text-white fw-bold mb-3">Quick Navigation</h5>
          <ul class="list-unstyled small">
            <li class="mb-2"><a href="index.php" class="text-decoration-none text-light opacity-75">Home</a></li>
            <li class="mb-2"><a href="about.php" class="text-decoration-none text-light opacity-75">About Us</a></li>
            <li class="mb-2"><a href="courses.php" class="text-decoration-none text-light opacity-75">Courses</a></li>
            <li class="mb-2"><a href="products.php" class="text-decoration-none text-light opacity-75">Products & Materials</a></li>
            <li class="mb-2"><a href="contact.php" class="text-decoration-none text-light opacity-75">Contact Us</a></li>
          </ul>
        </div>

        <div class="col-lg-5 col-md-3">
          <h5 class="text-white fw-bold mb-3">Core Educational Offerings</h5>
          <ul class="list-unstyled small opacity-75">
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Private & Customized Tutoring</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Holiday Package & Guided Learning</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Homework & Assignment Support</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Science Project & STEM Innovation</li>
            <li class="mb-2"><i class="bi bi-chevron-right text-warning me-1"></i> Applied Computer & ICT Lessons</li>
          </ul>
        </div>

      </div>
    </div>

    <div class="container text-center border-top border-secondary pt-3 mt-3 opacity-75 small">
      <p class="mb-0">&copy; <?= date('Y') ?> <strong>SmartStudyPro</strong>. All Rights Reserved. Empowering Education in Uganda.</p>
    </div>
  </footer>

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center text-decoration-none"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/js/main.js"></script>

</body>
</html>