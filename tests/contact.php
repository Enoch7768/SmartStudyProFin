<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Contact\ContactMessageService;
use App\Core\Database;
use App\Core\Migrator;
$dir=sys_get_temp_dir().'/ssp-contact-test-'.bin2hex(random_bytes(6));mkdir($dir,0700,true);
try{
 $pdo=Database::connect($dir.'/contact.sqlite');$m=Migrator::run($pdo,dirname(__DIR__).'/database/migrations');if(!in_array('015_contact_messages',$m,true))throw new RuntimeException('Contact inbox migration missing.');
 $service=new ContactMessageService($pdo);$ip=hash('sha256','test-ip');$ids=[];
 for($i=0;$i<5;$i++)$ids[]=$service->submit('Test User','test@example.test','Question '.$i,'Please help with this question.',$ip);
 $limited=false;try{$service->submit('Test User','test@example.test','Too many','This should be rate limited.',$ip);}catch(RuntimeException){$limited=true;}if(!$limited)throw new RuntimeException('Contact form rate limit was not enforced.');
 $invalid=false;try{$service->submit('Test User','not-an-email','Bad','Invalid email.',$ip);}catch(RuntimeException){$invalid=true;}if(!$invalid)throw new RuntimeException('Invalid contact email was accepted.');
 if(count($service->inbox())!==5)throw new RuntimeException('Contact inbox did not return submitted messages.');
 $service->setStatus($ids[0],'read');if(($service->inbox()[0]['status']??'')!=='new')throw new RuntimeException('Unread messages should remain prioritized in the inbox.');
 $service->setStatus($ids[0],'archived');$q=$pdo->prepare('SELECT status FROM contact_messages WHERE id=:id');$q->execute(['id'=>$ids[0]]);if($q->fetchColumn()!=='archived')throw new RuntimeException('Message archive state was not saved.');
 echo "PASS private contact message storage\nPASS input validation and per-IP rate limiting\nPASS administrator inbox status workflow\n";
}finally{foreach(glob($dir.'/*')?:[] as $f)if(is_file($f))unlink($f);@rmdir($dir);}
