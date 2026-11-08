<?php
declare(strict_types=1);
namespace App\Contact;
use PDO;
use RuntimeException;
use Throwable;
final class ContactMessageService {
 public function __construct(private readonly PDO $pdo) {}
 public function submit(string $name,string $email,string $subject,string $message,string $ipHash): int {
  $name=trim($name);$email=trim($email);$subject=trim($subject);$message=trim($message);
  if($name===''||strlen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||$subject===''||strlen($subject)>160||$message===''||strlen($message)>5000||preg_match('/[\r\n]/',$email))throw new RuntimeException('Check your name, email, subject and message. Messages are limited to 5,000 characters.');
  if(!preg_match('/^[a-f0-9]{64}$/i',$ipHash))throw new RuntimeException('The message could not be submitted. Please try again.');
  $this->pdo->exec('BEGIN IMMEDIATE');
  try{
   $cutoff=gmdate('Y-m-d H:i:s',time()-900);$count=$this->pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip_hash=:ip AND created_at>=:cutoff');$count->execute(['ip'=>$ipHash,'cutoff'=>$cutoff]);
   if((int)$count->fetchColumn()>=5)throw new RuntimeException('You have sent several messages recently. Please wait a little before trying again.');
   $now=gmdate('Y-m-d H:i:s');$insert=$this->pdo->prepare("INSERT INTO contact_messages(name,email,subject,message,status,ip_hash,created_at,updated_at) VALUES(:name,:email,:subject,:message,'new',:ip,:created,:updated)");
   $insert->execute(['name'=>$name,'email'=>$email,'subject'=>$subject,'message'=>$message,'ip'=>$ipHash,'created'=>$now,'updated'=>$now]);$id=(int)$this->pdo->lastInsertId();$this->pdo->exec('COMMIT');return $id;
  }catch(Throwable $e){try{$this->pdo->exec('ROLLBACK');}catch(Throwable){}throw $e;}
 }
 public function inbox(int $limit=150): array {$limit=max(1,min(200,$limit));return $this->pdo->query("SELECT id,name,email,subject,message,status,created_at,updated_at FROM contact_messages ORDER BY CASE status WHEN 'new' THEN 0 WHEN 'read' THEN 1 ELSE 2 END,created_at DESC,id DESC LIMIT ".$limit)->fetchAll();}
 public function setStatus(int $id,string $status): void {
  if($id<1||!in_array($status,['new','read','archived'],true))throw new RuntimeException('Choose a valid message status.');
  $q=$this->pdo->prepare('UPDATE contact_messages SET status=:status,updated_at=:now WHERE id=:id');$q->execute(['status'=>$status,'now'=>gmdate('Y-m-d H:i:s'),'id'=>$id]);if($q->rowCount()!==1)throw new RuntimeException('The message could not be updated.');
 }
}
