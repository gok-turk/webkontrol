<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../src/app.php';
foreach (db()->query('SELECT id FROM sites WHERE next_check <= '.time())->fetchAll() as $s) {
    try { if(checkSite((int)$s['id'])) echo 'Kontrol edildi: '.$s['id'].PHP_EOL; }
    catch(Throwable $e) {fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
}
