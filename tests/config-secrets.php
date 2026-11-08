<?php
declare(strict_types=1);
use App\Core\Config;
$root=dirname(__DIR__);$storage=sys_get_temp_dir().'/ssp-config-test-'.bin2hex(random_bytes(6));
mkdir($storage,0700,true);$privateFile=$storage.'/.env';
$keys=['APP_KEY','GEMINI_API_KEY','GROQ_API_KEY','GOOGLE_CLIENT_SECRET','PAYPAL_SECRET','DPO_COMPANY_TOKEN'];
try{
 foreach($keys as $key)putenv($key);
 $values=['APP_KEY'=>str_repeat('a',64),'GEMINI_API_KEY'=>'gemini-private-test','GROQ_API_KEY'=>'groq-private-test','GOOGLE_CLIENT_SECRET'=>'google-private-test','PAYPAL_SECRET'=>'paypal-private-test','DPO_COMPANY_TOKEN'=>'dpo-private-test'];
 $lines=[];foreach($values as $key=>$value)$lines[]=$key.'='.$value;
 file_put_contents($privateFile,implode("\n",$lines)."\n",LOCK_EX);chmod($privateFile,0600);
 putenv('APP_STORAGE_PATH='.$storage);putenv('DB_PATH='.$storage.'/test.sqlite');$_SERVER['DOCUMENT_ROOT']=$root;
 require $root.'/bootstrap/app.php';
 foreach(['app.key'=>'APP_KEY','ai.gemini_api_key'=>'GEMINI_API_KEY','ai.groq_api_key'=>'GROQ_API_KEY','google.client_secret'=>'GOOGLE_CLIENT_SECRET','payments.paypal_secret'=>'PAYPAL_SECRET','payments.dpo_company_token'=>'DPO_COMPANY_TOKEN'] as $config=>$key){
  if(Config::get($config)!==$values[$key])throw new RuntimeException('Private storage secret was not loaded: '.$key);
 }
 echo "PASS application and provider secrets load from private storage\n";
}finally{
 foreach($keys as $key)putenv($key);
 putenv('APP_STORAGE_PATH');putenv('DB_PATH');
 if(is_file($privateFile))unlink($privateFile);if(is_dir($storage))rmdir($storage);
}
