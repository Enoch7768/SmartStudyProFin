<?php

date_default_timezone_set('Africa/Kampala');
$fallback_url = "http://localhost/schoolprojectt/SmartStudyProV2.3/SmartStudyProV2/";

try {
    $cockpit_path = __DIR__ . '/cms/bootstrap.php';

    if (file_exists($cockpit_path)) {
        require_once $cockpit_path;
    } else {
        header("Location: " . $fallback_url);
        exit();
    }
} catch (Exception $e) {
    header("Location: " . $fallback_url);
    exit();
}

function is_cms_connected() {
    return function_exists('cockpit');
}

$cockpit = Cockpit::instance();

if (!function_exists('cockpit')) {
    function cockpit($module = null) {
        if ($module) {
            return Cockpit::instance()->module($module);
        }
        return Cockpit::instance();
    }
}