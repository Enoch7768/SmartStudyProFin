<?php
declare(strict_types=1);
namespace App\Learning;
use PDO;
use RuntimeException;
final class InstructorWorkspaceService {
 public function __construct(private readonly PDO $pdo) {}
 public function offerings(int $instructor): array {
  $q=$this->pdo->prepare("SELECT o.id,o.title,o.mode,o.description,o.price_ugx,o.capacity,c.title AS course_title,(SELECT COUNT(*) FROM course_offering_enrolments e WHERE e.offering_id=o.id AND e.status IN('active','completed')) AS learners FROM course_offering_instructors i JOIN course_offerings o ON o.id=i.offering_id JOIN cms_courses c ON c.id=o.course_id WHERE i.user_id=:user ORDER BY c.title,o.title");
  $q->execute(['user'=>$instructor]);return $q->fetchAll();
 }
 public function sessions(int $instructor): array {
  $q=$this->pdo->prepare("SELECT s.*,o.title AS offering_title,o.mode,c.title AS course_title,(SELECT COUNT(*) FROM session_enrolments e WHERE e.session_id=s.id AND e.status='confirmed') AS booked_count FROM course_sessions s JOIN course_offering_instructors i ON i.offering_id=s.offering_id JOIN course_offerings o ON o.id=s.offering_id JOIN cms_courses c ON c.id=o.course_id WHERE i.user_id=:user ORDER BY s.starts_at_utc DESC LIMIT 100");
  $q->execute(['user'=>$instructor]);return $q->fetchAll();
 }
 public function roster(int $instructor,int $session): array {
  $this->assertSession($instructor,$session);
  $q=$this->pdo->prepare("SELECT e.user_id,u.name,u.email,e.status,a.status AS attendance_status,a.reason FROM session_enrolments e JOIN users u ON u.id=e.user_id LEFT JOIN session_attendance a ON a.session_id=e.session_id AND a.user_id=e.user_id WHERE e.session_id=:session AND e.status='confirmed' ORDER BY u.name");
  $q->execute(['session'=>$session]);return $q->fetchAll();
 }
 public function createSession(int $instructor,int $offering,array $data): int {
  $q=$this->pdo->prepare("SELECT 1 FROM course_offering_instructors WHERE offering_id=:offering AND user_id=:user");
  $q->execute(['offering'=>$offering,'user'=>$instructor]);if(!$q->fetchColumn())throw new RuntimeException('You are not assigned to this course offering.');
  return (new MeetingPlannerService($this->pdo))->createSession($instructor,$offering,$data);
 }
 public function recordAttendance(int $instructor,int $session,int $learner,string $status,string $reason): void {
  $this->assertSession($instructor,$session);
  (new MeetingPlannerService($this->pdo))->recordAttendance($instructor,$session,$learner,$status,$reason);
 }
 public function reschedule(int $instructor,int $session,string $start,string $end,string $timezone): void {
  $this->assertSession($instructor,$session);
  (new MeetingPlannerService($this->pdo))->rescheduleSession($instructor,$session,$start,$end,$timezone);
 }
 public function cancel(int $instructor,int $session): void {
  $this->assertSession($instructor,$session);
  (new MeetingPlannerService($this->pdo))->cancelSession($instructor,$session);
 }
 public function complete(int $instructor,int $session): void {
  $this->assertSession($instructor,$session);
  (new MeetingPlannerService($this->pdo))->setSessionStatus($instructor,$session,'completed');
 }
 public function assertSession(int $instructor,int $session): void {
  $q=$this->pdo->prepare("SELECT 1 FROM course_sessions s JOIN course_offering_instructors i ON i.offering_id=s.offering_id WHERE s.id=:session AND i.user_id=:user LIMIT 1");
  $q->execute(['session'=>$session,'user'=>$instructor]);if(!$q->fetchColumn())throw new RuntimeException('This session is outside your assigned course offerings.');
 }
}
