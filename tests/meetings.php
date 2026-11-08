<?php
declare(strict_types=1);
if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"BLOCKED PDO SQLite is not installed.\n");exit(2);}
require dirname(__DIR__).'/bootstrap/app.php';
use App\Cms\ContentRepository;use App\Core\Database;use App\Core\Migrator;use App\Learning\MeetingPlannerService;use App\AI\MeetingCompanionService;
$dir=sys_get_temp_dir().'/ssp-meeting-test-'.bin2hex(random_bytes(6));mkdir($dir,0700,true);
try{
 $pdo=Database::connect($dir.'/test.sqlite');$ran=Migrator::run($pdo,dirname(__DIR__).'/database/migrations');
 if(!in_array('013_meeting_planner',$ran,true)||!in_array('014_meeting_reminders',$ran,true))throw new RuntimeException('Meeting planner or reminder migration missing.');
 if(Migrator::run($pdo,dirname(__DIR__).'/database/migrations')!==[])throw new RuntimeException('Meeting migrations are not idempotent.');
 $now=gmdate('Y-m-d H:i:s');$q=$pdo->prepare("INSERT INTO users(name,email,password_hash,status,created_at,updated_at) VALUES(:n,:e,:p,'active',:t,:t)");
 $q->execute(['n'=>'Admin','e'=>'admin@test.local','p'=>password_hash('a-long-password',PASSWORD_DEFAULT),'t'=>$now]);$admin=(int)$pdo->lastInsertId();
 $q->execute(['n'=>'Learner','e'=>'learner@test.local','p'=>password_hash('another-long-password',PASSWORD_DEFAULT),'t'=>$now]);$learner=(int)$pdo->lastInsertId();
 $repo=new ContentRepository($pdo);$course=$repo->save('courses',null,['title'=>'Physics','description_html'=>'','content_html'=>'','price_ugx'=>'0','category'=>'Science','status'=>'published','sort_order'=>'1']);
 $pdo->prepare("INSERT INTO course_enrolments(user_id,course_id,status,source,enrolled_at,updated_at) VALUES(:u,:c,'active','free',:n,:n)")->execute(['u'=>$learner,'c'=>$course,'n'=>$now]);
 $planner=new MeetingPlannerService($pdo);$off=$planner->saveOffering($course,'live_online','Live Physics','',0,1,true);
 $publicCourse=(new App\Cms\PublicContent($pdo))->course($course);if(count($publicCourse['Offerings']??[])!==1||$publicCourse['Offerings'][0]['mode']!=='live_online')throw new RuntimeException('Published course delivery options were not exposed in the public catalogue.');
 $session=$planner->createSession($admin,$off,['title'=>'Forces workshop','provider'=>'google_meet','timezone'=>'Africa/Kampala','starts_at'=>'2027-01-10T10:00','ends_at'=>'2027-01-10T11:00','join_url'=>'https://meet.google.com/abc-defg-hij','host_url'=>'https://meet.google.com/abc-defg-hij','capacity'=>'1']);
 $sessions=$planner->sessionsForLearner($learner);if(count($sessions)!==1||$sessions[0]['join_url']!=='')throw new RuntimeException('Meeting link was exposed before booking.');
 $planner->book($learner,$session);$reminderTime=new DateTimeImmutable('2027-01-09T07:00:00+00:00');if($planner->dispatchDueReminders($reminderTime)!==1||$planner->dispatchDueReminders($reminderTime)!==0)throw new RuntimeException('24-hour reminders were not idempotent.');$sessions=$planner->sessionsForLearner($learner);if($sessions[0]['join_url']!=='https://meet.google.com/abc-defg-hij')throw new RuntimeException('Confirmed booking did not unlock the learner join link.');
 $duplicate=false;try{$planner->book($learner,$session);}catch(RuntimeException){$duplicate=true;}if(!$duplicate)throw new RuntimeException('Duplicate booking was accepted.');
 $planner->recordAttendance($admin,$session,$learner,'present','Verified in session.');
 if(($planner->roster($session)[0]['attendance_status']??'')!=='present')throw new RuntimeException('Attendance record was not saved.');if(count($planner->attendanceHistory($learner))!==1)throw new RuntimeException('Learner attendance history was not available.');
 $off2=$planner->saveOffering($course,'live_online','Second cohort','',0,1,true);
 $session2=$planner->createSession($admin,$off2,['title'=>'Second workshop','provider'=>'zoom','timezone'=>'Africa/Kampala','starts_at'=>'2027-01-11T10:00','ends_at'=>'2027-01-11T11:00','join_url'=>'https://zoom.us/j/123456789','host_url'=>'https://zoom.us/j/123456789','capacity'=>'1']);
 $planner->rescheduleSession($admin,$session2,'2027-01-13T10:00','2027-01-13T11:00','Africa/Kampala');if(($planner->sessionsForLearner($learner)[1]['status']??'')!=='rescheduled')throw new RuntimeException('Session rescheduling did not update its status.');$planner->setSessionStatus($admin,$session2,'completed');$planner->setSessionStatus($admin,$session2,'archived');
 $badLink=false;try{$planner->createSession($admin,$off2,['title'=>'Bad link','provider'=>'zoom','timezone'=>'Africa/Kampala','starts_at'=>'2027-01-12T10:00','ends_at'=>'2027-01-12T11:00','join_url'=>'https://evil.example/','host_url'=>'https://zoom.us/j/123']);}catch(RuntimeException){$badLink=true;}if(!$badLink)throw new RuntimeException('Untrusted meeting provider link was accepted.');
 $disabled=new MeetingCompanionService($pdo,'','llama-3.3-70b-versatile',6000,1000,20,30000,false);$disabledRejected=false;try{$disabled->generate($admin,$session,'Draft agenda','','');}catch(RuntimeException){$disabledRejected=true;}if(!$disabledRejected)throw new RuntimeException('AI kill switch did not disable the meeting companion.');
 echo "PASS meeting planner migration and idempotency\nPASS live offering and provider link validation\nPASS enrolled learner booking and protected join link\nPASS duplicate booking protection\nPASS attendance and roster\nPASS Groq meeting companion kill switch\n";
}finally{foreach(glob($dir.'/*')?:[] as $f)if(is_file($f))unlink($f);@rmdir($dir);}
