<?php
// 1. Load the initialization
require_once 'cms-init.php';

header('Content-Type: text/html; charset=utf-8');


// Temporary test: Fetch ALL chapters regardless of name
$chapters = cockpit('content')->items('Chapters', [
    'sort'   => ['Order' => 1]
]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cockpit CMS Connection Test</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; background: #f4f4f4; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .status { font-weight: bold; padding: 5px 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        pre { background: #272822; color: #f8f8f2; padding: 15px; overflow: auto; border-radius: 5px; }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
    </style>
</head>
<body>

        <h1>SmartStudyPro CMS Connection Diagnostic</h1>
    <p>Current Date: <?= date('M d, Y') ?></p>
    <div class="card">
        <h2>1. Global Helper Check</h2>
        <?php if (function_exists('cockpit')): ?>
            <span class="status success">SUCCESS:</span> The <code>cockpit()</code> function is registered and ready.
        <?php else: ?>
            <span class="status error">FAILED:</span> The <code>cockpit()</code> function is missing. Check your <code>cms-init.php</code>.
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>2. Database & Module Check</h2>
        <?php 
        try {
            $content = cockpit('content');
            if ($content) {
                echo '<span class="status success">SUCCESS:</span> Content module loaded successfully.';
            }
        } catch (Exception $e) {
            echo '<span class="status error">ERROR:</span> ' . $e->getMessage();
        }
        ?>
    </div>

    <div class="card">
        <h2>3. Data Retrieval (Models)</h2>
        <?php
        $models = ['HomePage', 'Courses', 'AboutPage', 'ContactDetails','Products','Lessons','Quizzes'];
        
        foreach ($models as $m) {
            echo "<strong>Testing Model [$m]: </strong>";
            try {
                // Check if it's a singleton (item) or collection (items)
                $data = ($m == 'Courses') ? cockpit('content')->items($m) : cockpit('content')->item($m);
                
                if ($data) {
                    echo '<span class="status success">FOUND DATA</span><br>';
                    echo '<details><summary>View Raw Data</summary><pre>' . print_r($data, true) . '</pre></details><br>';
                } else {
                    echo '<span class="status error">MODEL EXISTS BUT NO DATA PUBLISHED</span><br>';
                }
            } catch (Exception $e) {
                echo '<span class="status error">ERROR: ' . $e->getMessage() . '</span><br>';
            }
        }
        ?>
    </div>

    <div class="card">
        <h2>4. Directory Paths</h2>
        <ul>
            <li><strong>CMS Path:</strong> <code><?= realpath('./cms') ?></code></li>
            <li><strong>Uploads Dir:</strong> <code><?= realpath('./cms/storage/uploads') ?></code></li>
        </ul>
    </div>

</body>
</html>