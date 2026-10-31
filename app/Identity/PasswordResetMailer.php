<?php

declare(strict_types=1);

namespace App\Identity;

use App\Core\Config;

final class PasswordResetMailer
{
    public static function send(string $email, string $name, string $token): bool
    {
        $base = (string) Config::get('app.url');
        if ($base === '') return false;
        $url = $base . '/reset.php?token=' . rawurlencode($token);
        $from = (string) Config::get('mail.from', 'no-reply@smartstudypro.com');
        $subject = 'Reset your SmartStudyPro password';
        $message = "Hello " . $name . ",\n\nUse the secure link below to reset your SmartStudyPro password. The link expires in 30 minutes and can only be used once.\n\n" . $url . "\n\nIf you did not request this, you can ignore this email.\n";
        $headers = "From: SmartStudyPro <" . $from . ">\r\nContent-Type: text/plain; charset=UTF-8";
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && filter_var($from, FILTER_VALIDATE_EMAIL) !== false && mail($email, $subject, $message, $headers);
    }
}
