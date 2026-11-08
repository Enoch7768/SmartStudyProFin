<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap/app.php';
use App\Contact\ContactMessageService;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed.');}
if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(400);exit('Your session expired. Refresh the contact page and try again.');}
if(trim((string)($_POST['website']??''))!==''){Security::redirect('/contact?sent=1');}
$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$key=(string)Config::get('app.key','');$ipHash=$key!==''?hash_hmac('sha256',$ip,$key):hash('sha256',$ip);
try{
 (new ContactMessageService(Database::connect()))->submit((string)($_POST['name']??''),(string)($_POST['email']??''),(string)($_POST['subject']??''),(string)($_POST['message']??''),$ipHash);
 Security::redirect('/contact?sent=1');
}catch(RuntimeException $e){
 Security::redirect('/contact?error=1');
}catch(Throwable $e){
 error_log('Contact message submission failed: '.$e->getMessage());
 Security::redirect('/contact?error=1');
}
