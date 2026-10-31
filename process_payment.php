<?php

declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
exit('This legacy payment endpoint has been retired. Use the verified checkout flow.');
