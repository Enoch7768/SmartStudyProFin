<?php

require_once 'cms-init.php';
require_once 'auth.php';
require_login(); 

$user = current_user();

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$course_name = '';
$chapters = [];
$error_message = '';
$active_lesson_data = null; 

try {
    $db_file = __DIR__ . "/database/bookings.db";
    if (!file_exists($db_file)) throw new Exception("Database missing.");

    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ensure_bookings_columns($db);

    $stmt = $db->prepare("SELECT service FROM bookings WHERE id = ? AND paid = 1 AND (account_id = ? OR email = ?)");
    $stmt->execute([$course_id, $user['id'], $user['email']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $error_message = "Access Denied: Payment not verified.";
    } else {
        $course_name = trim($booking['service']);

        if (function_exists('cockpit')) {
            $sample = cockpit('content')->items('Chapters', ['limit' => 1]);
            $c_field = 'COURSE TITLE'; 
            if (!empty($sample)) {
                foreach ($sample[0] as $k => $v) {
                    if (is_string($v) && stripos($course_name, substr($v, 0, 5)) !== false) { $c_field = $k; break; }
                }
            }

            $variations = array_unique([$course_name, strtoupper($course_name), strtolower($course_name), ucwords(strtolower($course_name))]);
            foreach ($variations as $v_name) {
                $chapters = cockpit('content')->items('Chapters', ['filter' => [$c_field => $v_name], 'sort' => ['CHAPTER ID' => 1]]);
                if (!empty($chapters)) break;
            }
        }
    }
} catch (Exception $e) {
    error_log("study.php error: " . $e->getMessage());
    $error_message = "Access Denied: Payment not verified.";
}

$active_video_path = isset($_GET['v']) ? $_GET['v'] : null;

$seoTitle = $course_name ? 'Study Portal - ' . htmlspecialchars($course_name) . ' | SmartStudyPro' : 'Study Portal | SmartStudyPro';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $seoTitle ?></title>

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

  <script>
    // Inline Theme Init to prevent white flash before DOM renders
    (function() {
      const savedTheme = localStorage.getItem('ssp-theme');
      if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark-theme');
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>

  <style>
    :root {
      --ssp-navy: #0C086B;
      --ssp-navy-dark: #070443;
      --ssp-orange: #FF7A00;
      --ssp-orange-hover: #E06B00;
      --ssp-bg-soft: #F8FAFC;
      --ssp-text-main: #1E293B;
      --ssp-text-muted: #64748B;
      --ssp-sidebar-bg: #FFFFFF;
      --ssp-border-color: #E2E8F0;
      --ssp-card-bg: #FFFFFF;
    }

    /* Dark Mode Theme Engine */
    html.dark-theme,
    body.dark-theme, 
    [data-bs-theme="dark"] body, 
    html[data-theme="dark"] body, 
    body[data-theme="dark"] {
      --ssp-bg-soft: #0F172A;
      --ssp-text-main: #F1F5F9;
      --ssp-text-muted: #94A3B8;
      --ssp-sidebar-bg: #1E293B;
      --ssp-border-color: #334155;
      --ssp-card-bg: #1E293B;
      background-color: #0F172A !important;
      color: #F1F5F9 !important;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--ssp-text-main);
      background-color: var(--ssp-bg-soft);
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    h1, h2, h3, h4, h5, h6, .brand-font {
      font-family: 'Outfit', sans-serif;
    }

    .ssp-header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(12px);
      border-bottom: 2px solid rgba(12, 8, 107, 0.08);
      height: 70px;
      transition: all 0.3s ease;
    }

    html.dark-theme .ssp-header,
    body.dark-theme .ssp-header, 
    [data-bs-theme="dark"] .ssp-header,
    html[data-theme="dark"] .ssp-header {
      background: rgba(15, 23, 42, 0.95);
      border-bottom-color: rgba(255, 255, 255, 0.1);
    }

    html.dark-theme .brand-font,
    body.dark-theme .brand-font, 
    [data-bs-theme="dark"] .brand-font,
    html[data-theme="dark"] .brand-font {
      color: #F1F5F9 !important;
    }

    /* Theme Switcher Toggle Style */
    .theme-toggle-btn {
      background: transparent;
      border: none;
      font-size: 1.25rem;
      cursor: pointer;
      color: var(--ssp-navy);
      padding: 4px 8px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s ease, transform 0.2s ease;
    }

    .theme-toggle-btn:hover {
      color: var(--ssp-orange);
      transform: scale(1.1);
    }

    html.dark-theme .theme-toggle-btn,
    body.dark-theme .theme-toggle-btn,
    [data-bs-theme="dark"] .theme-toggle-btn,
    html[data-theme="dark"] .theme-toggle-btn {
      color: #F1F5F9;
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

    html.dark-theme .dropdown-menu,
    body.dark-theme .dropdown-menu, 
    [data-bs-theme="dark"] .dropdown-menu,
    html[data-theme="dark"] .dropdown-menu {
      background-color: #1E293B;
      border-color: #334155;
      color: #F1F5F9;
    }

    html.dark-theme .dropdown-item,
    body.dark-theme .dropdown-item, 
    [data-bs-theme="dark"] .dropdown-item,
    html[data-theme="dark"] .dropdown-item {
      color: #F1F5F9;
    }

    html.dark-theme .dropdown-item:hover,
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

    .study-layout {
      display: flex;
      min-height: calc(100vh - 70px);
    }

    .sidebar {
      width: 340px;
      background: var(--ssp-sidebar-bg);
      border-right: 1px solid var(--ssp-border-color);
      height: calc(100vh - 70px);
      position: sticky;
      top: 70px;
      overflow-y: auto;
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .main-video-area {
      flex: 1;
      padding: 35px;
      background: var(--ssp-bg-soft);
      transition: background-color 0.3s ease;
    }

    .chapter-label {
      background: rgba(12, 8, 107, 0.04);
      padding: 14px 20px;
      font-weight: 700;
      font-family: 'Outfit', sans-serif;
      border-bottom: 1px solid var(--ssp-border-color);
      color: var(--ssp-navy);
      font-size: 0.95rem;
    }

    html.dark-theme .chapter-label,
    body.dark-theme .chapter-label, 
    [data-bs-theme="dark"] .chapter-label,
    html[data-theme="dark"] .chapter-label {
      background: rgba(255, 255, 255, 0.05);
      color: #F1F5F9 !important;
    }

    .lesson-link {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      color: var(--ssp-text-main);
      text-decoration: none !important;
      border-bottom: 1px solid var(--ssp-border-color);
      font-size: 0.9rem;
      font-weight: 500;
      border-left: 4px solid transparent;
      transition: all 0.2s ease;
    }

    html.dark-theme .lesson-link,
    body.dark-theme .lesson-link,
    [data-bs-theme="dark"] .lesson-link,
    html[data-theme="dark"] .lesson-link {
      color: #CBD5E1;
    }

    .lesson-link:hover {
      background: rgba(255, 122, 0, 0.05);
      color: var(--ssp-orange);
    }

    .lesson-link.active {
      background: rgba(255, 122, 0, 0.1);
      color: var(--ssp-orange);
      font-weight: 700;
      border-left: 4px solid var(--ssp-orange);
    }

    .video-container {
      background: #000;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(12, 8, 107, 0.12);
    }

    .lesson-notes-card {
      background: var(--ssp-card-bg);
      border: 1px solid var(--ssp-border-color) !important;
      color: var(--ssp-text-main);
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .lesson-notes-card h4 {
      color: var(--ssp-navy);
    }

    html.dark-theme .lesson-notes-card h4,
    body.dark-theme .lesson-notes-card h4, 
    [data-bs-theme="dark"] .lesson-notes-card h4,
    html[data-theme="dark"] .lesson-notes-card h4 {
      color: #F1F5F9 !important;
    }

    html.dark-theme .lesson-notes-card .text-secondary,
    body.dark-theme .lesson-notes-card .text-secondary, 
    [data-bs-theme="dark"] .lesson-notes-card .text-secondary,
    html[data-theme="dark"] .lesson-notes-card .text-secondary {
      color: #94A3B8 !important;
    }

    .quiz-card {
      background: var(--ssp-card-bg);
      border-radius: 14px;
      border: 1px solid var(--ssp-border-color);
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
      margin-bottom: 20px;
      color: var(--ssp-text-main);
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .quiz-card p {
      color: var(--ssp-navy);
    }

    html.dark-theme .quiz-card p,
    body.dark-theme .quiz-card p, 
    [data-bs-theme="dark"] .quiz-card p,
    html[data-theme="dark"] .quiz-card p {
      color: #F1F5F9 !important;
    }

    html.dark-theme .quiz-section h4,
    body.dark-theme .quiz-section h4, 
    [data-bs-theme="dark"] .quiz-section h4,
    html[data-theme="dark"] .quiz-section h4 {
      color: #F1F5F9 !important;
    }

    html.dark-theme .user-icon-link,
    body.dark-theme .user-icon-link, 
    [data-bs-theme="dark"] .user-icon-link,
    html[data-theme="dark"] .user-icon-link {
      color: #F1F5F9 !important;
    }

    .form-check-input:checked {
      background-color: var(--ssp-orange);
      border-color: var(--ssp-orange);
    }

    @media (max-width: 991px) {
      .study-layout {
        flex-direction: column;
      }
      .sidebar {
        width: 100%;
        height: auto;
        position: static;
      }
      .main-video-area {
        padding: 20px 15px;
      }
    }
  </style>
</head>
<body class="study-portal-page">

  <header class="ssp-header d-flex align-items-center justify-content-between px-3 px-md-4 sticky-top">
    <div class="d-flex align-items-center">
      <a href="index.php" class="me-3">
        <img src="Smart_Study_Logo_Fin-removebg-preview.png" alt="SmartStudyPro Logo" height="42">
      </a>
      <h5 class="mb-0 d-none d-md-block fw-bold ms-2 brand-font" style="color: var(--ssp-navy);">
        <?= htmlspecialchars($course_name) ?>
      </h5>
    </div>

    <div class="d-flex align-items-center gap-3">
      <!-- Dark Mode Trigger Icon -->
      <button type="button" class="theme-toggle-btn" id="themeToggleBtn" title="Toggle Dark/Light Mode">
        <i class="bi bi-moon-stars-fill" id="themeToggleIcon"></i>
      </button>

      <a href="courses.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-none d-sm-inline-block">
        <i class="bi bi-arrow-left me-1"></i> Back to Courses
      </a>

      <div class="dropdown">
        <a href="#" class="fs-5 text-decoration-none dropdown-toggle-no-caret user-icon-link" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Account" style="color: var(--ssp-navy);">
          <i class="bi bi-person-circle"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2" aria-labelledby="userMenuDropdown">
          <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2" style="color: var(--ssp-navy);"></i>My Profile</a></li>
          <li><a class="dropdown-item py-2" href="cart.php"><i class="bi bi-bag me-2" style="color: var(--ssp-navy);"></i>My Cart</a></li>
          <li><hr class="dropdown-divider"></li>
        </ul>
      </div>
    </div>
  </header>

  <?php if ($error_message): ?>
    <div class="container py-5">
      <div class="card shadow-sm border-0 rounded-4 p-5 text-center mx-auto lesson-notes-card" style="max-width: 550px;">
        <i class="bi bi-shield-lock-fill text-danger mb-3" style="font-size: 3.5rem;"></i>
        <h3 class="fw-bold mb-2 brand-font"><?= htmlspecialchars($error_message) ?></h3>
        <p class="text-muted mb-4">You must have an active and verified purchase to access this study portal.</p>
        <a href="courses.php" class="btn-ssp-primary text-decoration-none d-inline-block">Browse Available Courses</a>
      </div>
    </div>
  <?php else: ?>
    <div class="study-layout">
      
      <nav class="sidebar">
        <div class="p-3 border-bottom d-md-none bg-light">
          <h6 class="fw-bold mb-0 brand-font"><?= htmlspecialchars($course_name) ?></h6>
        </div>

        <?php foreach ($chapters as $chapter): ?>
          <div class="chapter-label">
            <i class="bi bi-journal-bookmark-fill me-2" style="color: var(--ssp-orange);"></i> 
            <?= htmlspecialchars($chapter['Title'] ?? $chapter['title'] ?? 'Section') ?>
          </div>
          <?php 
            $lessons = cockpit('content')->items('Lessons', ['filter' => ['Chapter' => $chapter['_id']], 'sort' => ['Order' => 1]]);
            foreach ($lessons as $lesson): 
              $l_title = 'Untitled Lesson';
              if (!empty($lesson['Title'])) $l_title = $lesson['Title'];
              elseif (!empty($lesson['title'])) $l_title = $lesson['title'];
              else {
                  foreach($lesson as $k => $v) {
                      if (is_string($v) && strlen($v) > 2 && !in_array($k, ['_id', 'Chapter', 'VIDEO FILE'])) {
                          $l_title = $v; break;
                      }
                  }
              }

              $video_asset = !empty($lesson['VIDEO FILE']) ? $lesson['VIDEO FILE'] : null;
              $l_video_path = '';
              if ($video_asset) {
                  $l_video_path = is_array($video_asset) ? ltrim($video_asset['path'], '/') : ltrim($video_asset, '/');
              }

              $is_active = ($active_video_path && ltrim($active_video_path, '/') === $l_video_path);
              if ($is_active) { $active_lesson_data = $lesson; }
          ?>
            <a href="?course_id=<?= $course_id ?>&v=<?= urlencode($l_video_path) ?>" class="lesson-link <?= $is_active ? 'active' : '' ?>">
              <i class="bi bi-play-circle-fill me-2 text-muted"></i> <span><?= htmlspecialchars($l_title) ?></span>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>

      <main class="main-video-area">
        <?php if ($active_video_path && $active_lesson_data): ?>
          <div class="video-container mb-4">
            <?php 
                $token = sign_video_token($active_video_path, $user['id'], 6 * 3600); 
                $final_url = "stream.php?path=" . rawurlencode($active_video_path)
                           . "&exp=" . $token['exp']
                           . "&sig=" . $token['sig'];
            ?>
            <video id="mainPlayer" controls autoplay controlsList="nodownload noremoteplayback" disablePictureInPicture oncontextmenu="return false;" style="width: 100%; display: block; max-height: 72vh;">
                <source src="<?= htmlspecialchars($final_url) ?>" type="video/mp4">
            </video>
          </div>

          <div class="p-4 rounded-4 shadow-sm mb-4 lesson-notes-card">
              <h4 class="fw-bold mb-3">
                <?= htmlspecialchars($active_lesson_data['Title '] ?? $active_lesson_data['Title'] ?? 'Lesson Details') ?>
              </h4>
              <div class="text-secondary" style="line-height: 1.7;">
                  <?= $active_lesson_data['Content'] ?? $active_lesson_data['content'] ?? 'No notes available for this lesson.' ?>
              </div>
          </div>

          <?php 
            $quizzes = cockpit('content')->items('Quizzes', ['filter' => ['Lesson' => $active_lesson_data['_id']]]);
            if (!empty($quizzes)): 
          ?>
          <div class="quiz-section mt-5">
            <h4 class="fw-bold mb-4" style="color: var(--ssp-navy);">
              <i class="bi bi-patch-check-fill me-2" style="color: var(--ssp-orange);"></i>Knowledge Check
            </h4>
            <form id="quizForm">
                <?php foreach ($quizzes as $index => $q): ?>
                <div class="quiz-card p-4" data-quiz-id="<?= htmlspecialchars($q['_id']) ?>">
                    <p class="fw-bold mb-3"><?= ($index + 1) ?>. <?= htmlspecialchars($q['Question']) ?></p>
                    <?php 
                      $options = explode(',', $q['Options']); 
                      foreach ($options as $opt): $opt = trim($opt);
                    ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="q_<?= $q['_id'] ?>" value="<?= htmlspecialchars($opt) ?>" id="opt_<?= md5($opt.$q['_id']) ?>">
                        <label class="form-check-label" for="opt_<?= md5($opt.$q['_id']) ?>"><?= htmlspecialchars($opt) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <button type="button" onclick="gradeQuiz()" class="btn-ssp-primary px-5 py-2.5">Grade My Answers</button>
            </form>
            <div id="quizFeedback" class="mt-4 d-none alert rounded-3"></div>
          </div>
          <?php endif; ?>

        <?php else: ?>
          <div class="text-center py-5 my-5">
            <i class="bi bi-play-btn text-muted opacity-25" style="font-size: 6rem;"></i>
            <h3 class="mt-4 fw-bold brand-font" style="color: var(--ssp-navy);">Ready to Learn?</h3>
            <p class="text-muted">Select a lesson from the sidebar menu to begin watching your course content.</p>
          </div>
        <?php endif; ?>
      </main>

    </div>
  <?php endif; ?>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    // Theme Switcher Logic
    const themeBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeToggleIcon');

    function updateThemeUI(isDark) {
      if (isDark) {
        document.documentElement.classList.add('dark-theme');
        document.body.classList.add('dark-theme');
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        document.documentElement.setAttribute('data-theme', 'dark');
        if (themeIcon) {
          themeIcon.className = 'bi bi-sun-fill';
          themeIcon.style.color = '#FF7A00';
        }
      } else {
        document.documentElement.classList.remove('dark-theme');
        document.body.classList.remove('dark-theme');
        document.documentElement.removeAttribute('data-bs-theme');
        document.documentElement.removeAttribute('data-theme');
        if (themeIcon) {
          themeIcon.className = 'bi bi-moon-stars-fill';
          themeIcon.style.color = '';
        }
      }
    }

    // Initialize UI State
    const isCurrentlyDark = document.documentElement.classList.contains('dark-theme');
    updateThemeUI(isCurrentlyDark);

    if (themeBtn) {
      themeBtn.addEventListener('click', () => {
        const isDarkNow = document.documentElement.classList.contains('dark-theme');
        const nextState = !isDarkNow;
        localStorage.setItem('ssp-theme', nextState ? 'dark' : 'light');
        updateThemeUI(nextState);
      });
    }

    // Quiz Handler
    function gradeQuiz() {
        const answers = {};
        document.querySelectorAll('.quiz-card').forEach(card => {
            const quizId = card.dataset.quizId;
            const selected = card.querySelector('input[type="radio"]:checked');
            if (selected) answers[quizId] = selected.value;
        });

        if (Object.keys(answers).length === 0) {
            alert("Please answer at least one question.");
            return;
        }

        fetch('grade-quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ answers })
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            const fb = document.getElementById('quizFeedback');
            fb.className = "mt-4 alert rounded-3 " + (data.score === data.total ? "alert-success" : "alert-warning");
            fb.innerHTML = `<strong>Result: ${data.score}/${data.total}</strong> — ` +
                           (data.score === data.total ? "Perfect score! Outstanding job!" : "Review the video material and try again.");
            fb.classList.remove('d-none');
            fb.scrollIntoView({ behavior: 'smooth' });
        })
        .catch(() => alert('Could not grade your answers right now. Please try again.'));
    }
  </script>
</body>
</html>