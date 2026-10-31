<?php

declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
exit('The legacy video stream has been retired. Open lesson videos from the SmartStudyPro learning portal.');
