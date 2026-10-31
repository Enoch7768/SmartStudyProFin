<?php

declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
exit('The legacy file download endpoint has been retired. Secure product downloads will be available through the verified purchase library.');
