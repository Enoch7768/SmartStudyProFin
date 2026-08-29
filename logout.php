<?php
require_once 'auth.php';
logout_user();
header("Location: index.php");
exit;