<?php
declare(strict_types=1);
if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"BLOCKED PDO SQLite is not installed.\n");exit(2);}
require dirname(__DIR__).'/bootstrap/app.php';
use App\Cms\ContentRepository;
use App\Core\Database;
use App\Core\Migrator;
use App\Learning\InstructorWorkspaceService;
use App\Learning\MeetingPlannerService;
$dir=sys_get_temp_dir().'/ssp-instructor-test-'.bin2hex(random_bytes(6));mkdir($dir,0700,true);
try{
 $pdo=Database::connect($dir.'/test.sqlite');Migrator::run($pdo,dirname(__DIR__).'/database/migrations');
 $now=gmdate('Y-m-d H:i:s');$userInsert=$pdo->prepare("INSERT INTO users(name,email,password_hash,status,created_at,updated_at) VALUES(:name,:email,:hash,'active',:now,:now)");
 foreach([['Instructor','instructor@workspace.test'],['Other instructor','other@workspace.test'],['Learner','learner@workspace.test']] as [$name,$email]){$userInsert->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash('a-long-test-password',PASSWORD_DEFAULT),'now'=>$now]);$ids[$email]=(int)$pdo->lastInsertId();}
 $instructor=$ids['instructor@workspace.test'];$other=$ids['other@workspace.test'];$learner=$ids['learner@workspace.test'];
 $content=new ContentRepository($pdo);$course=$content->save('courses',null,['title'=>'Biology','description_html'=>'','content_html'=>'','price_ugx'=>'0','category'=>'Science','status'=>'published','sort_order'=>'1']);
 $planner=new MeetingPlannerService($pdo);$offering=$planner->saveOffering($course,'live_online','Biology cohort','',0,10,true);$planner->grantInstructorRole(1,$instructor);$planner->grantInstructorRole(1,$other);$planner->assignInstructor(1,$offering,$instructor);
 $pdo->prepare("INSERT INTO course_offering_enrolments(user_id,offering_id,source,status,created_at,updated_at) VALUES(:user,:offering,'manual','active',:now,:now)")->execute(['user'=>$learner,'offering'=>$offering,'now'=>$now]);
 $session=$planner->createSession($instructor,$offering,['title'=>'Cell biology','provider'=>'google_meet','timezone'=>'Africa/Kampala','starts_at'=>'2027-03-10T10:00','ends_at'=>'2027-03-10T11:00','join_url'=>'https://meet.google.com/abc-defg-hij','host_url'=>'https://meet.google.com/abc-defg-hij','capacity'=>'10']);
 $planner->book($learner,$session);
 $workspace=new InstructorWorkspaceService($pdo);
 if(count($workspace->offerings($instructor))!==1||count($workspace->sessions($instructor))!==1||count($workspace->roster($instructor,$session))!==1)throw new RuntimeException('Assigned instructor could not see the correct offering, session and roster.');
 if($workspace->offerings($other)!==[]||$workspace->sessions($other)!==[])throw new RuntimeException('An unassigned instructor could see another instructor\'s cohort.');
 foreach([
  static fn()=>$workspace->roster($other,$session),
  static fn()=>$workspace->reschedule($other,$session,'2027-03-11T10:00','2027-03-11T11:00','Africa/Kampala'),
  static fn()=>$workspace->cancel($other,$session),
  static fn()=>$workspace->complete($other,$session),
  static fn()=>$workspace->recordAttendance($other,$session,$learner,'present','Should be denied.')
 ] as $attempt){$denied=false;try{$attempt();}catch(RuntimeException){$denied=true;}if(!$denied)throw new RuntimeException('An unassigned instructor was allowed to access or change another cohort.');}
 $createDenied=false;try{$workspace->createSession($other,$offering,['title'=>'Unauthorized','provider'=>'google_meet','timezone'=>'Africa/Kampala','starts_at'=>'2027-03-12T10:00','ends_at'=>'2027-03-12T11:00','join_url'=>'https://meet.google.com/abc-defg-hij','host_url'=>'https://meet.google.com/abc-defg-hij']);}catch(RuntimeException){$createDenied=true;}if(!$createDenied)throw new RuntimeException('An unassigned instructor created a session for another cohort.');
 $otherOffering=$planner->saveOffering($course,'live_online','Unassigned cohort','',0,10,true);$offeringDenied=false;try{$workspace->createSession($instructor,$otherOffering,['title'=>'Unauthorized','provider'=>'google_meet','timezone'=>'Africa/Kampala','starts_at'=>'2027-03-13T10:00','ends_at'=>'2027-03-13T11:00','join_url'=>'https://meet.google.com/abc-defg-hij','host_url'=>'https://meet.google.com/abc-defg-hij']);}catch(RuntimeException){$offeringDenied=true;}if(!$offeringDenied)throw new RuntimeException('An instructor created a session for an unassigned offering.');
 echo "PASS instructor workspace assignment scoping and read access\nPASS instructor mutation ownership checks\nPASS instructor session creation restricted to assigned offerings\n";
}finally{foreach(glob($dir.'/*')?:[] as $file)@unlink($file);@rmdir($dir);}
