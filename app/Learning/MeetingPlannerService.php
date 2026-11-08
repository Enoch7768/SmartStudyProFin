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
 public function instructors(): array {
  return $this->pdo->query("SELECT u.id,u.name,u.email FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='instructor' AND u.status='active' ORDER BY u.name")->fetchAll();
 }
 public function assignedInstructors(int $offeringId): array {
  $q=$this->pdo->prepare("SELECT u.id,u.name,u.email,oi.assigned_at FROM course_offering_instructors oi JOIN users u ON u.id=oi.user_id WHERE oi.offering_id=:offering ORDER BY u.name");$q->execute(['offering'=>$offeringId]);return $q->fetchAll();
 }
 public function assignInstructor(int $actor,int $offeringId,int $instructorId): void {
  $q=$this->pdo->prepare("SELECT 1 FROM course_offerings WHERE id=:id");$q->execute(['id'=>$offeringId]);if(!$q->fetchColumn())throw new RuntimeException('Course offering not found.');
  $q=$this->pdo->prepare("SELECT 1 FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=:user AND u.status='active' AND r.slug='instructor'");$q->execute(['user'=>$instructorId]);if(!$q->fetchColumn())throw new RuntimeException('Select an active account with the instructor role.');
  $now=gmdate('Y-m-d H:i:s');$q=$this->pdo->prepare("INSERT OR IGNORE INTO course_offering_instructors(offering_id,user_id,assigned_by,assigned_at) VALUES(:offering,:user,:actor,:now)");$q->execute(['offering'=>$offeringId,'user'=>$instructorId,'actor'=>$actor,'now'=>$now]);
  if($q->rowCount()===1)$this->pdo->prepare("INSERT INTO course_offering_audit_events(offering_id,actor_user_id,subject_user_id,event_type,details_json,created_at) VALUES(:offering,:actor,:learner,'instructor.assigned','{}',:now)")->execute(['offering'=>$offeringId,'actor'=>$actor,'learner'=>$instructorId,'now'=>$now]);
 }
 public function unassignInstructor(int $actor,int $offeringId,int $instructorId): void {
  $q=$this->pdo->prepare("DELETE FROM course_offering_instructors WHERE offering_id=:offering AND user_id=:user");$q->execute(['offering'=>$offeringId,'user'=>$instructorId]);
  if($q->rowCount()===1)$this->pdo->prepare("INSERT INTO course_offering_audit_events(offering_id,actor_user_id,subject_user_id,event_type,details_json,created_at) VALUES(:offering,:actor,:learner,'instructor.unassigned','{}',:now)")->execute(['offering'=>$offeringId,'actor'=>$actor,'learner'=>$instructorId,'now'=>gmdate('Y-m-d H:i:s')]);
 }
 public function updateOffering(int $offeringId,string $title,string $description,int $price,?int $capacity,bool $published): void {
  if(trim($title)===''||strlen($title)>160||$price<0||$price>1000000000||($capacity!==null&&$capacity<1))throw new RuntimeException('Check the offering name, price and capacity.');
  $q=$this->pdo->prepare("SELECT is_published FROM course_offerings WHERE id=:id");$q->execute(['id'=>$offeringId]);$current=$q->fetch();if(!$current)throw new RuntimeException('Course offering not found.');
  $counts=$this->pdo->prepare("SELECT COUNT(*) FROM course_offering_enrolments WHERE offering_id=:id AND status IN('active','completed')");$counts->execute(['id'=>$offeringId]);$enrolled=(int)$counts->fetchColumn();
  if($capacity!==null&&$capacity<$enrolled)throw new RuntimeException('Capacity cannot be lower than the number of active and completed learners.');
  if(!$published&&$enrolled>0)throw new RuntimeException('An offering with enrolled learners cannot be unpublished. Revoke access or complete the cohort first.');
  $this->pdo->prepare('UPDATE course_offerings SET title=:title,description=:description,price_ugx=:price,capacity=:capacity,is_published=:published,updated_at=:now WHERE id=:id')->execute(['title'=>trim($title),'description'=>trim($description),'price'=>$price,'capacity'=>$capacity,'published'=>$published?1:0,'now'=>gmdate('Y-m-d H:i:s'),'id'=>$offeringId]);
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
  $capacity=($data['capacity']??'')!==''?max(1,(int)$data['capacity']):($offering['capacity']===null?null:(int)$offering['capacity']);
  $startUtc=$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');$endUtc=$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  $conflict=$this->pdo->prepare("SELECT 1 FROM course_sessions WHERE created_by=:actor AND status IN('scheduled','rescheduled') AND starts_at_utc<:end AND ends_at_utc>:start LIMIT 1");
  $conflict->execute(['actor'=>$actor,'end'=>$endUtc,'start'=>$startUtc]);if($conflict->fetchColumn())throw new RuntimeException('This host already has another session scheduled during that time.');
  $now=gmdate('Y-m-d H:i:s');
  $q=$this->pdo->prepare("INSERT INTO course_sessions(offering_id,title,agenda,provider,provider_meeting_id,join_url,host_url,venue_name,venue_address,venue_notes,starts_at_utc,ends_at_utc,timezone,capacity,status,created_by,created_at,updated_at) VALUES(:offering,:title,:agenda,:provider,:meeting,:join,:host,:venue,:address,:notes,:start,:end,:tz,:capacity,'scheduled',:actor,:now,:now)");
  $q->execute(['offering'=>$offeringId,'title'=>trim((string)$data['title']),'agenda'=>trim((string)($data['agenda']??'')),'provider'=>$provider,'meeting'=>trim((string)($data['provider_meeting_id']??'')),'join'=>$join,'host'=>$host,'venue'=>trim((string)($data['venue_name']??'')),'address'=>trim((string)($data['venue_address']??'')),'notes'=>trim((string)($data['venue_notes']??'')),'start'=>$startUtc,'end'=>$endUtc,'tz'=>$tz,'capacity'=>$capacity,'actor'=>$actor,'now'=>$now]);
  $id=(int)$this->pdo->lastInsertId();$this->audit($id,$actor,'session.created',['provider'=>$provider,'mode'=>$mode]);
  $enrolled=$this->pdo->prepare("SELECT DISTINCT user_id FROM course_offering_enrolments WHERE offering_id=:offering AND status IN('active','completed')");
  $enrolled->execute(['offering'=>$offeringId]);$notice=$this->pdo->prepare("INSERT OR IGNORE INTO notifications(user_id,event_key,type,title,message,target_url,created_at) VALUES(:user,:event,'course',:title,:message,:url,:now)");
  foreach($enrolled->fetchAll(PDO::FETCH_COLUMN) as $learner)$notice->execute(['user'=>(int)$learner,'event'=>'session-created-'.$id,'title'=>'New course session scheduled','message'=>trim((string)$data['title']).' has been scheduled. Open My Sessions for details.','url'=>'/meetings','now'=>$now]);
  return $id;
 }
 public function offeringEnrolments(int $offeringId): array {
  $q=$this->pdo->prepare("SELECT oe.user_id,oe.status,oe.source,oe.created_at,oe.completed_at,u.name,u.email,(SELECT COUNT(*) FROM session_enrolments se JOIN course_sessions s ON s.id=se.session_id WHERE s.offering_id=oe.offering_id AND se.user_id=oe.user_id AND se.status='confirmed') AS booked_sessions,(SELECT COUNT(*) FROM session_attendance a JOIN course_sessions s ON s.id=a.session_id WHERE s.offering_id=oe.offering_id AND a.user_id=oe.user_id AND a.status IN('present','late','excused')) AS attended_sessions FROM course_offering_enrolments oe JOIN users u ON u.id=oe.user_id WHERE oe.offering_id=:offering ORDER BY CASE oe.status WHEN 'active' THEN 0 WHEN 'completed' THEN 1 ELSE 2 END,u.name");
  $q->execute(['offering'=>$offeringId]);return $q->fetchAll();
 }
 public function revokeOfferingEnrolment(int $actor,int $offeringId,int $userId): void {
  $this->pdo->exec('BEGIN IMMEDIATE');
  try {
   $q=$this->pdo->prepare("SELECT oe.status,oe.source,o.title FROM course_offering_enrolments oe JOIN course_offerings o ON o.id=oe.offering_id WHERE oe.offering_id=:offering AND oe.user_id=:user");
   $q->execute(['offering'=>$offeringId,'user'=>$userId]);$row=$q->fetch();
   if(!$row||!in_array($row['status'],['active','completed'],true))throw new RuntimeException('This learner does not have active cohort access.');
   if($row['source']==='payment')throw new RuntimeException('Paid cohort access must be revoked through the refund workflow.');
   $now=gmdate('Y-m-d H:i:s');
   $this->pdo->prepare("UPDATE course_offering_enrolments SET status='revoked',updated_at=:now WHERE offering_id=:offering AND user_id=:user")->execute(['now'=>$now,'offering'=>$offeringId,'user'=>$userId]);
   $this->pdo->prepare("UPDATE session_enrolments SET status='cancelled',updated_at=:now WHERE user_id=:user AND status='confirmed' AND session_id IN(SELECT id FROM course_sessions WHERE offering_id=:offering)")->execute(['now'=>$now,'user'=>$userId,'offering'=>$offeringId]);
   $this->pdo->prepare('DELETE FROM offering_certificates WHERE user_id=:user AND offering_id=:offering')->execute(['user'=>$userId,'offering'=>$offeringId]);
   $details=json_encode(['source'=>$row['source'],'status_before'=>$row['status']],JSON_THROW_ON_ERROR);
   $this->pdo->prepare("INSERT INTO course_offering_audit_events(offering_id,actor_user_id,subject_user_id,event_type,details_json,created_at) VALUES(:offering,:actor,:learner,'enrolment.revoked',:details,:now)")->execute(['offering'=>$offeringId,'actor'=>$actor,'learner'=>$userId,'details'=>$details,'now'=>$now]);
   $notice=$this->pdo->prepare("INSERT OR IGNORE INTO notifications(user_id,event_key,type,title,message,target_url,created_at) VALUES(:user,:event,'course','Course cohort access updated',:message,'/courses',:now)");
   $notice->execute(['user'=>$userId,'event'=>'offering-revoked-'.$offeringId.'-'.$now,'message'=>'Your access to '.$row['title'].' has been withdrawn. Contact the course administrator if you believe this is a mistake.','now'=>$now]);
   $this->pdo->exec('COMMIT');
  }catch(Throwable $e){try{$this->pdo->exec('ROLLBACK');}catch(Throwable){}throw $e;}
 }
 public function completeOfferingEnrolment(int $actor,int $offeringId,int $userId): void {
  $this->pdo->exec('BEGIN IMMEDIATE');
  try {
   $q=$this->pdo->prepare("SELECT oe.status,o.mode,o.title,c.title AS course_title FROM course_offering_enrolments oe JOIN course_offerings o ON o.id=oe.offering_id JOIN cms_courses c ON c.id=o.course_id WHERE oe.offering_id=:offering AND oe.user_id=:user");
   $q->execute(['offering'=>$offeringId,'user'=>$userId]);$enrolment=$q->fetch();
   if(!$enrolment||$enrolment['status']!=='active'||!in_array($enrolment['mode'],['live_online','in_person'],true))throw new RuntimeException('Only active live or in-person enrolments can be completed.');
   $sessions=$this->pdo->prepare("SELECT COUNT(*) FROM course_sessions WHERE offering_id=:offering AND status IN('scheduled','rescheduled','draft')");
   $sessions->execute(['offering'=>$offeringId]);if((int)$sessions->fetchColumn()>0)throw new RuntimeException('Finish, cancel or archive every scheduled session before completing this cohort enrolment.');
   $completed=$this->pdo->prepare("SELECT COUNT(*) FROM course_sessions WHERE offering_id=:offering AND status IN('completed','archived')");
   $completed->execute(['offering'=>$offeringId]);$required=(int)$completed->fetchColumn();
   if($required<1)throw new RuntimeException('At least one completed session is required before course completion.');
   $attendance=$this->pdo->prepare("SELECT COUNT(*) FROM course_sessions s JOIN session_enrolments e ON e.session_id=s.id AND e.user_id=:user AND e.status='confirmed' JOIN session_attendance a ON a.session_id=s.id AND a.user_id=e.user_id AND a.status IN('present','late','excused') WHERE s.offering_id=:offering AND s.status IN('completed','archived')");
   $attendance->execute(['user'=>$userId,'offering'=>$offeringId]);if((int)$attendance->fetchColumn()!==$required)throw new RuntimeException('Attendance must be recorded as present, late or excused for every completed session before issuing completion.');
   $now=gmdate('Y-m-d H:i:s');
   $update=$this->pdo->prepare("UPDATE course_offering_enrolments SET status='completed',completed_at=:now,updated_at=:now WHERE offering_id=:offering AND user_id=:user AND status='active'");
   $update->execute(['now'=>$now,'offering'=>$offeringId,'user'=>$userId]);if($update->rowCount()!==1)throw new RuntimeException('This enrolment has already changed.');
   $this->pdo->prepare("INSERT INTO course_offering_audit_events(offering_id,actor_user_id,subject_user_id,event_type,details_json,created_at) VALUES(:offering,:actor,:learner,'enrolment.completed',:details,:now)")->execute(['offering'=>$offeringId,'actor'=>$actor,'learner'=>$userId,'details'=>json_encode(['mode'=>$enrolment['mode']],JSON_THROW_ON_ERROR),'now'=>$now]);
   $notice=$this->pdo->prepare("INSERT OR IGNORE INTO notifications(user_id,event_key,type,title,message,target_url,created_at) VALUES(:user,:event,'course','Course cohort completed',:message,'/certificates',:now)");
   $notice->execute(['user'=>$userId,'event'=>'offering-completed-'.$offeringId,'message'=>'You completed '.$enrolment['course_title'].' · '.$enrolment['title'].'. Your certificate will be available in Certificates.','now'=>$now]);
   $this->pdo->exec('COMMIT');
  }catch(Throwable $e){try{$this->pdo->exec('ROLLBACK');}catch(Throwable){}throw $e;}
 }
 public function sessionsForLearner(int $user): array {
  $q=$this->pdo->prepare("SELECT s.id,s.title,s.agenda,s.provider,s.join_url,s.venue_name,s.venue_address,s.venue_notes,s.starts_at_utc,s.ends_at_utc,s.timezone,s.capacity,s.status,o.mode,o.title AS offering_title,c.title AS course_title,e.status AS booking_status,(SELECT COUNT(*) FROM session_enrolments x WHERE x.session_id=s.id AND x.status='confirmed') AS booked_count FROM course_sessions s JOIN course_offerings o ON o.id=s.offering_id JOIN cms_courses c ON c.id=o.course_id JOIN course_offering_enrolments oe ON oe.offering_id=o.id AND oe.user_id=:user AND oe.status IN('active','completed') LEFT JOIN session_enrolments e ON e.session_id=s.id AND e.user_id=:booking WHERE o.is_published=1 AND s.status IN('scheduled','rescheduled','cancelled','completed','archived') ORDER BY s.starts_at_utc DESC");
  $q->execute(['user'=>$user,'booking'=>$user]);$rows=$q->fetchAll();foreach($rows as &$row)if($row['provider']!=='in_person'&&(!in_array($row['status'],['scheduled','rescheduled'],true)||$row['booking_status']!=='confirmed'))$row['join_url']='';return $rows;
 }
 public function book(int $user,int $session): void {
  $this->pdo->exec('BEGIN IMMEDIATE');try{$q=$this->pdo->prepare("SELECT s.capacity,s.starts_at_utc FROM course_sessions s JOIN course_offerings o ON o.id=s.offering_id JOIN course_offering_enrolments e ON e.offering_id=o.id AND e.user_id=:user AND e.status IN('active','completed') WHERE s.id=:session AND s.status IN('scheduled','rescheduled') AND o.is_published=1");$q->execute(['user'=>$user,'session'=>$session]);$row=$q->fetch();if(!$row)throw new RuntimeException('Session is not available to your account.');if($row['starts_at_utc']<=gmdate('Y-m-d H:i:s'))throw new RuntimeException('Booking has closed because this session has already started.');
   $q=$this->pdo->prepare("SELECT status FROM session_enrolments WHERE session_id=:s AND user_id=:u");$q->execute(['s'=>$session,'u'=>$user]);if($q->fetchColumn()==='confirmed')throw new RuntimeException('You already booked this session.');
   $q=$this->pdo->prepare("SELECT COUNT(*) FROM session_enrolments WHERE session_id=:s AND status='confirmed'");$q->execute(['s'=>$session]);if($row['capacity']!==null&&(int)$q->fetchColumn()>=(int)$row['capacity'])throw new RuntimeException('This session is full.');
   $now=gmdate('Y-m-d H:i:s');$q=$this->pdo->prepare("INSERT INTO session_enrolments(session_id,user_id,status,booked_at,updated_at) VALUES(:s,:u,'confirmed',:n,:n) ON CONFLICT(session_id,user_id) DO UPDATE SET status='confirmed',updated_at=excluded.updated_at");$q->execute(['s'=>$session,'u'=>$user,'n'=>$now]);$notice=$this->pdo->prepare("INSERT OR IGNORE INTO notifications(user_id,event_key,type,title,message,target_url,created_at) VALUES(:user,:event,'course','Session booking confirmed','Your place is confirmed. Open My Sessions for the schedule and protected meeting link.','/meetings',:now)");$notice->execute(['user'=>$user,'event'=>'session-booked-'.$session,'now'=>$now]);$this->pdo->exec('COMMIT');
  }catch(Throwable $e){try{$this->pdo->exec('ROLLBACK');}catch(Throwable){}throw $e;}
 }
 public function roster(int $session): array {$q=$this->pdo->prepare("SELECT e.user_id,u.name,u.email,e.status,a.status AS attendance_status,a.reason FROM session_enrolments e JOIN users u ON u.id=e.user_id LEFT JOIN session_attendance a ON a.session_id=e.session_id AND a.user_id=e.user_id WHERE e.session_id=:s ORDER BY u.name");$q->execute(['s'=>$session]);return $q->fetchAll();}
 public function recordAttendance(int $actor,int $session,int $user,string $status,string $reason=''): void {
  if(!in_array($status,['present','late','absent','excused','pending'],true)||trim($reason)==='')throw new RuntimeException('Choose a valid attendance status and provide a reason for the attendance record or correction.');
  $sessionState=$this->pdo->prepare("SELECT status,ends_at_utc FROM course_sessions WHERE id=:session");$sessionState->execute(['session'=>$session]);$state=$sessionState->fetch();if(!$state||!in_array($state['status'],['completed','archived'],true)||$state['ends_at_utc']>gmdate('Y-m-d H:i:s'))throw new RuntimeException('Attendance can only be recorded after a session has finished.');
  $q=$this->pdo->prepare("SELECT 1 FROM session_enrolments WHERE session_id=:s AND user_id=:u AND status='confirmed'");$q->execute(['s'=>$session,'u'=>$user]);if(!$q->fetchColumn())throw new RuntimeException('Learner is not booked for this session.');
  $now=gmdate('Y-m-d H:i:s');$q=$this->pdo->prepare('INSERT INTO session_attendance(session_id,user_id,status,recorded_by,reason,recorded_at) VALUES(:s,:u,:status,:actor,:reason,:now) ON CONFLICT(session_id,user_id) DO UPDATE SET status=excluded.status,recorded_by=excluded.recorded_by,reason=excluded.reason,recorded_at=excluded.recorded_at');$q->execute(['s'=>$session,'u'=>$user,'status'=>$status,'actor'=>$actor,'reason'=>trim($reason),'now'=>$now]);$this->audit($session,$actor,'attendance.updated',['user_id'=>$user,'status'=>$status,'reason'=>trim($reason)]);
 }
 public function cancelSession(int $actor,int $session): void {
  $q=$this->pdo->prepare("UPDATE course_sessions SET status='cancelled',updated_at=:now WHERE id=:id AND status IN('draft','scheduled','rescheduled')");$now=gmdate('Y-m-d H:i:s');$q->execute(['now'=>$now,'id'=>$session]);if(!$q->rowCount())throw new RuntimeException('Session cannot be cancelled.');
  $this->audit($session,$actor,'session.cancelled',[]);
  $learners=$this->pdo->prepare("SELECT user_id FROM session_enrolments WHERE session_id=:session AND status='confirmed'");$learners->execute(['session'=>$session]);
  $notice=$this->pdo->prepare("INSERT OR IGNORE INTO notifications(user_id,event_key,type,title,message,target_url,created_at) VALUES(:user,:event,'course','Course session cancelled','A course session you booked has been cancelled. Check My Sessions for updates.','/meetings',:now)");
  foreach($learners->fetchAll(PDO::FETCH_COLUMN) as $learner)$notice->execute(['user'=>(int)$learner,'event'=>'session-cancelled-'.$session,'now'=>$now]);
 }
 public function rescheduleSession(int $actor,int $session,string $startsAt,string $endsAt,string $timezone): void {
  try{$zone=new DateTimeZone($timezone);$start=(new DateTimeImmutable($startsAt,$zone))->setTimezone(new DateTimeZone('UTC'));$end=(new DateTimeImmutable($endsAt,$zone))->setTimezone(new DateTimeZone('UTC'));}catch(Throwable){throw new RuntimeException('Enter valid dates and an IANA timezone.');}
  if($end<=$start||$end->getTimestamp()-$start->getTimestamp()>86400)throw new RuntimeException('Session end must be after start and within 24 hours.');
  $q=$this->pdo->prepare("SELECT created_by FROM course_sessions WHERE id=:id AND status IN('scheduled','rescheduled')");$q->execute(['id'=>$session]);$row=$q->fetch();if(!$row)throw new RuntimeException('Only scheduled sessions can be rescheduled.');
  $startUtc=$start->format('Y-m-d H:i:s');$endUtc=$end->format('Y-m-d H:i:s');
  $conflict=$this->pdo->prepare("SELECT 1 FROM course_sessions WHERE id<>:id AND created_by=:actor AND status IN('scheduled','rescheduled') AND starts_at_utc<:end AND ends_at_utc>:start LIMIT 1");$conflict->execute(['id'=>$session,'actor'=>(int)$row['created_by'],'end'=>$endUtc,'start'=>$startUtc]);if($conflict->fetchColumn())throw new RuntimeException('This host already has another session scheduled during that time.');
  $now=gmdate('Y-m-d H:i:s');$update=$this->pdo->prepare("UPDATE course_sessions SET starts_at_utc=:start,ends_at_utc=:end,timezone=:timezone,status='rescheduled',updated_at=:now WHERE id=:id");$update->execute(['start'=>$startUtc,'end'=>$endUtc,'timezone'=>$timezone,'now'=>$now,'id'=>$session]);
  $this->audit($session,$actor,'session.rescheduled',['starts_at_utc'=>$startUtc,'ends_at_utc'=>$endUtc,'timezone'=>$timezone]);
  $learners=$this->pdo->prepare("SELECT user_id FROM session_enrolments WHERE session_id=:session AND status='confirmed'");$learners->execute(['session'=>$session]);
  $notice=$this->pdo->prepare("INSERT OR IGNORE INTO notifications(user_id,event_key,type,title,message,target_url,created_at) VALUES(:user,:event,'course','Course session rescheduled','A session you booked has a new date or time. Check My Sessions for the updated schedule.','/meetings',:now)");
  foreach($learners->fetchAll(PDO::FETCH_COLUMN) as $learner)$notice->execute(['user'=>(int)$learner,'event'=>'session-rescheduled-'.$session.'-'.$now,'now'=>$now]);
 }
 public function setSessionStatus(int $actor,int $session,string $status): void {
  if(!in_array($status,['completed','archived'],true))throw new RuntimeException('Invalid session status.');
  if($status==='completed'){$end=$this->pdo->prepare("SELECT ends_at_utc FROM course_sessions WHERE id=:id AND status IN('scheduled','rescheduled')");$end->execute(['id'=>$session]);$endsAt=$end->fetchColumn();if($endsAt===false||$endsAt>gmdate('Y-m-d H:i:s'))throw new RuntimeException('A session can only be marked completed after its scheduled end time.');}
  $allowedPrevious=$status==='archived'?"'completed'":"'scheduled','rescheduled','completed'";
  $q=$this->pdo->prepare("UPDATE course_sessions SET status=:status,updated_at=:now WHERE id=:id AND status IN(".$allowedPrevious.")");$now=gmdate('Y-m-d H:i:s');$q->execute(['status'=>$status,'now'=>$now,'id'=>$session]);if(!$q->rowCount())throw new RuntimeException($status==='archived'?'Only a completed session can be archived.':'Session status cannot be changed.');
  $this->audit($session,$actor,'session.'.$status,[]);
 }
 public function dispatchDueReminders(?DateTimeImmutable $now=null): int {
  $now=($now??new DateTimeImmutable('now',new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));$stamp=$now->format('Y-m-d H:i:s');$delivered=0;
  $windows=[['24h','+23 hours 45 minutes','+24 hours'],['1h','+45 minutes','+1 hour']];
  foreach($windows as [$type,$lower,$upper]){
   $lowerTime=$now->modify($lower)->format('Y-m-d H:i:s');$upperTime=$now->modify($upper)->format('Y-m-d H:i:s');
   $q=$this->pdo->prepare("SELECT s.id AS session_id,s.title,e.user_id FROM course_sessions s JOIN session_enrolments e ON e.session_id=s.id AND e.status='confirmed' WHERE s.status IN('scheduled','rescheduled') AND s.starts_at_utc>:lower AND s.starts_at_utc<=:upper");
   $q->execute(['lower'=>$lowerTime,'upper'=>$upperTime]);
   foreach($q->fetchAll() as $row){
    $insert=$this->pdo->prepare('INSERT OR IGNORE INTO meeting_reminders(session_id,user_id,reminder_type,sent_at) VALUES(:session,:user,:type,:sent)');$insert->execute(['session'=>(int)$row['session_id'],'user'=>(int)$row['user_id'],'type'=>$type,'sent'=>$stamp]);if($insert->rowCount()!==1)continue;
    $event='meeting-reminder-'.$row['session_id'].'-'.$row['user_id'].'-'.$type;$notice=$this->pdo->prepare("INSERT OR IGNORE INTO notifications(user_id,event_key,type,title,message,target_url,created_at) VALUES(:user,:event,'course',:title,:message,'/meetings',:now)");
    $notice->execute(['user'=>(int)$row['user_id'],'event'=>$event,'title'=>$type==='24h'?'Course session tomorrow':'Course session starting soon','message'=>trim((string)$row['title']).($type==='24h'?' starts in about 24 hours.':' starts in about one hour.').' Open My Sessions for details.','now'=>$stamp]);$delivered+=$notice->rowCount();
   }
  }
  return $delivered;
 }
 public function attendanceHistory(int $user): array {
  $q=$this->pdo->prepare("SELECT s.title,s.starts_at_utc,s.timezone,c.title AS course_title,a.status,a.reason,a.recorded_at FROM session_attendance a JOIN course_sessions s ON s.id=a.session_id JOIN course_offerings o ON o.id=s.offering_id JOIN cms_courses c ON c.id=o.course_id WHERE a.user_id=:user ORDER BY s.starts_at_utc DESC");$q->execute(['user'=>$user]);return $q->fetchAll();
 }
 public function audit(int $session,int $actor,string $event,array $details): void {$q=$this->pdo->prepare('INSERT INTO meeting_audit_events(session_id,actor_user_id,event_type,details_json,created_at) VALUES(:s,:a,:e,:d,:n)');$q->execute(['s'=>$session,'a'=>$actor,'e'=>$event,'d'=>json_encode($details,JSON_THROW_ON_ERROR),'n'=>gmdate('Y-m-d H:i:s')]);}
}
