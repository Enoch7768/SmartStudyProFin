<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found');}
require dirname(__DIR__).'/bootstrap/app.php';
use App\Core\Database;
use App\Learning\MeetingPlannerService;
try{$count=(new MeetingPlannerService(Database::connect()))->dispatchDueReminders();fwrite(STDOUT,'Meeting reminders delivered: '.$count.PHP_EOL);}catch(Throwable $e){fwrite(STDERR,'Meeting reminder job failed: '.$e->getMessage().PHP_EOL);exit(1);}
