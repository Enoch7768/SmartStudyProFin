<?php
declare(strict_types=1);
namespace App\Learning;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;
final class MeetingPlannerService {
 public function __construct(private readonly PDO $pdo) {}
 public function courses(): array { return $this->pdo->query("SELECT id,title FROM cms_courses WHERE status IN('published','draft') ORDER BY title")->fetchAll(); }
 public function offerings(): array { return $this->pdo->query("SELECT o.*,c.title AS course_title FROM course_offerings o JOIN cms_courses c ON c.id=o.course_id ORDER BY o.id DESC")->fetchAll(); }
 public function saveOffering(int $courseId,string $mode,string $title,string $description,int $price,?int $capacity,bool $published): int {
  if(!in_array($mode,['self_paced','live_online','in_person'],true)||trim($title)===''||strlen($title)>160||$price<0||$price>1000000000||($capacity!==null&&$capacity<1)) throw new RuntimeException('Check the course offering details.');
  $q=$this->pdo->prepare("SELECT 1 FROM cms_courses WHERE id=:id AND status IN('published','draft')");$q->execute(['id'=>$courseId]);if(!$q->fetchColumn())throw new RuntimeException('Course not found.');
  $now=gmdate('Y-m-d H:i:s');$q=$this->pdo->prepare('INSERT INTO course_offerings(course_id,mode,title,description,price_ugx,capacity,is_published,created_at,updated_at) VALUES(:course,:mode,:title,:description,:price,:capacity,:published,:now,:now)');
  $q->execute(['course'=>$courseId,'mode'=>$mode,'title'=>trim($title),'description'=>trim($description),'price'=>$price,'capacity'=>$capacity,'published'=>$published?1:0,'now'=>$now]);return (int)$this->pdo->lastInsertId();
 }
 public function createSession(int $actor,int $offeringId,array $data): int {
  $q=$this->pdo->prepare('SELECT mode,capacity FROM course_offerings WHERE id=:id');$q->execute(['id'=>$offeringId]);$offering=$q->fetch();if(!$offering)throw new RuntimeException('Offering not found.');
  $mode=$offering['mode'];$provider=(string)($data['provider']??'none');$tz=(string)($data['timezone']??'');
  try{$zone=new DateTimeZone($tz);$start=new DateTimeImmutable((string)($data['starts_at']??''),$zone);$end=new DateTimeImmutable((string)($data['ends_at']??''),$zone);}catch(Throwable){throw new RuntimeException('Enter valid dates and an IANA timezone.');}
  if($end<=$start||$end->getTimestamp()-$start->getTimestamp()>86400)throw new RuntimeException('Session end must be after start and within 24 hours.');
  if(trim((string)($data['title']??''))==='')throw new RuntimeException('Enter a session title.');
  if($mode==='live_online'&&!in_array($provider,['zoom','google_meet'],true))throw new RuntimeException('Select Zoom or Google Meet.');
  if($mode==='in_person'&&$provider!=='in_person')throw new RuntimeException('Select in-person delivery.');
  $join=trim((string)($data['join_url']??''));$host=trim((string)($data['host_url']??''));
  if($mode==='live_online')foreach([$join,$host] as $url){$p=parse_url($url);$h=strtolower((string)($p['host']??''));$ok=$provider==='zoom'?($h==='zoom.us'||str_ends_with($h,'.zoom.us')):$h==='meet.google.com';if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||!$ok||isset($p['user'])||isset($p['pass']))throw new RuntimeException('Use a valid HTTPS link for the selected meeting provider.');}
  if($mode==='in_person'&&trim((string)($data['venue_name']??''))==='')throw new RuntimeException('Enter the venue name.');
  if($mode==='in_person'){$join='';$host='';}
  $capacity=($data['capacity']??'')!==''?max(1,(int)$data['capacity']):($offering['capacity']===null?null:(int)$offering['capacity']);$now=gmdate('Y-m-d H:i:s');
  $q=$this->pdo->prepare("INSERT INTO course_sessions(offering_id,title,agenda,provider,provider_meeting_id,join_url,host_url,venue_name,venue_address,venue_notes,starts_at_utc,ends_at_utc,timezone,capacity,status,created_by,created_at,updated_at) VALUES(:offering,:title,:agenda,:provider,:meeting,:join,:host,:venue,:address,:notes,:start,:end,:tz,:capacity,'scheduled',:actor,:now,:now)");
  $q->execute(['offering'=>$offeringId,'title'=>trim((string)$data['title']),'agenda'=>trim((string)($data['agenda']??'')),'provider'=>$provider,'meeting'=>trim((string)($data['provider_meeting_id']??'')),'join'=>$join,'host'=>$host,'venue'=>trim((string)($data['venue_name']??'')),'address'=>trim((string)($data['venue_address']??'')),'notes'=>trim((string)($data['venue_notes']??'')),'start'=>$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'end'=>$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'tz'=>$tz,'capacity'=>$capacity,'actor'=>$actor,'now'=>$now]);
  $id=(int)$this->pdo->lastInsertId();$this->audit($id,$actor,'session.created',['provider'=>$provider,'mode'=>$mode]);return $id;
 }
 public function sessionsForLearner(int $user): array {
  $q=$this->pdo->prepare("SELECT s.id,s.title,s.agenda,s.provider,s.join_url,s.venue_name,s.venue_address,s.venue_notes,s.starts_at_utc,s.ends_at_utc,s.timezone,s.capacity,s.status,o.mode,o.title AS offering_title,c.title AS course_title,e.status AS booking_status,(SELECT COUNT(*) FROM session_enrolments x WHERE x.session_id=s.id AND x.status='confirmed') AS booked_count FROM course_sessions s JOIN course_offerings o ON o.id=s.offering_id JOIN cms_courses c ON c.id=o.course_id JOIN course_enrolments ce ON ce.course_id=c.id AND ce.user_id=:user AND ce.status IN('active','completed') LEFT JOIN session_enrolments e ON e.session_id=s.id AND e.user_id=:booking WHERE o.is_published=1 AND s.status IN('scheduled','rescheduled') ORDER BY s.starts_at_utc");
  $q->execute(['user'=>$user,'booking'=>$user]);$rows=$q->fetchAll();foreach($rows as &$row)if($row['provider']!=='in_person'&&$row['booking_status']!=='confirmed')$row['join_url']='';return $rows;
 }
 public function book(int $user,int $session): void {
  $this->pdo->exec('BEGIN IMMEDIATE');try{$q=$this->pdo->prepare("SELECT s.capacity FROM course_sessions s JOIN course_offerings o ON o.id=s.offering_id JOIN course_enrolments e ON e.course_id=o.course_id AND e.user_id=:user AND e.status IN('active','completed') WHERE s.id=:session AND s.status IN('scheduled','rescheduled') AND o.is_published=1");$q->execute(['user'=>$user,'session'=>$session]);$row=$q->fetch();if(!$row)throw new RuntimeException('Session is not available to your account.');
   $q=$this->pdo->prepare("SELECT status FROM session_enrolments WHERE session_id=:s AND user_id=:u");$q->execute(['s'=>$session,'u'=>$user]);if($q->fetchColumn()==='confirmed')throw new RuntimeException('You already booked this session.');
   $q=$this->pdo->prepare("SELECT COUNT(*) FROM session_enrolments WHERE session_id=:s AND status='confirmed'");$q->execute(['s'=>$session]);if($row['capacity']!==null&&(int)$q->fetchColumn()>=(int)$row['capacity'])throw new RuntimeException('This session is full.');
   $now=gmdate('Y-m-d H:i:s');$q=$this->pdo->prepare("INSERT INTO session_enrolments(session_id,user_id,status,booked_at,updated_at) VALUES(:s,:u,'confirmed',:n,:n) ON CONFLICT(session_id,user_id) DO UPDATE SET status='confirmed',updated_at=excluded.updated_at");$q->execute(['s'=>$session,'u'=>$user,'n'=>$now]);$this->pdo->commit();
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 public function roster(int $session): array {$q=$this->pdo->prepare("SELECT e.user_id,u.name,u.email,e.status,a.status AS attendance_status,a.reason FROM session_enrolments e JOIN users u ON u.id=e.user_id LEFT JOIN session_attendance a ON a.session_id=e.session_id AND a.user_id=e.user_id WHERE e.session_id=:s ORDER BY u.name");$q->execute(['s'=>$session]);return $q->fetchAll();}
 public function recordAttendance(int $actor,int $session,int $user,string $status,string $reason=''): void {
  if(!in_array($status,['present','late','absent','excused','pending'],true))throw new RuntimeException('Invalid attendance status.');$q=$this->pdo->prepare("SELECT 1 FROM session_enrolments WHERE session_id=:s AND user_id=:u AND status='confirmed'");$q->execute(['s'=>$session,'u'=>$user]);if(!$q->fetchColumn())throw new RuntimeException('Learner is not booked for this session.');
  $now=gmdate('Y-m-d H:i:s');$q=$this->pdo->prepare('INSERT INTO session_attendance(session_id,user_id,status,recorded_by,reason,recorded_at) VALUES(:s,:u,:status,:actor,:reason,:now) ON CONFLICT(session_id,user_id) DO UPDATE SET status=excluded.status,recorded_by=excluded.recorded_by,reason=excluded.reason,recorded_at=excluded.recorded_at');$q->execute(['s'=>$session,'u'=>$user,'status'=>$status,'actor'=>$actor,'reason'=>trim($reason),'now'=>$now]);$this->audit($session,$actor,'attendance.updated',['user_id'=>$user,'status'=>$status,'reason'=>trim($reason)]);
 }
 public function cancelSession(int $actor,int $session): void {$q=$this->pdo->prepare("UPDATE course_sessions SET status='cancelled',updated_at=:now WHERE id=:id AND status IN('draft','scheduled','rescheduled')");$q->execute(['now'=>gmdate('Y-m-d H:i:s'),'id'=>$session]);if(!$q->rowCount())throw new RuntimeException('Session cannot be cancelled.');$this->audit($session,$actor,'session.cancelled',[]);}
 public function audit(int $session,int $actor,string $event,array $details): void {$q=$this->pdo->prepare('INSERT INTO meeting_audit_events(session_id,actor_user_id,event_type,details_json,created_at) VALUES(:s,:a,:e,:d,:n)');$q->execute(['s'=>$session,'a'=>$actor,'e'=>$event,'d'=>json_encode($details,JSON_THROW_ON_ERROR),'n'=>gmdate('Y-m-d H:i:s')]);}
}
