<?php

if (isset($_GET['file'])) {
    $filePath = $_GET['file'];

    $baseDir = realpath(__DIR__ . "/cms/storage/uploads");

    $candidate = $baseDir . DIRECTORY_SEPARATOR . ltrim(str_replace('\\', '/', $filePath), '/');
    $fullPath = realpath($candidate);

    $isSafe = $baseDir !== false
        && $fullPath !== false
        && strncmp($fullPath, $baseDir . DIRECTORY_SEPARATOR, strlen($baseDir) + 1) === 0;

    $fileName = basename($filePath);

    if ($isSafe && is_file($fullPath)) {

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fullPath));
        
        flush(); 
        readfile($fullPath);
        exit;
    } else {
        echo "<div style='font-family:sans-serif; text-align:center; padding-top:50px;'>";
        echo "<h2 style='color:#e74c3c;'>Download Unavailable</h2>";
        echo "<p>The file could not be found on the server. Please contact support.</p>";
        echo "<a href='cart.php'>Return to Cart</a>";
        echo "</div>";
    }
} else {
    header("Location: cart.php");
    exit;
}