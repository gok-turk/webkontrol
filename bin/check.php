<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../src/app.php';
$db=db();
$lock=fopen(dataDir().'/cron.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB))exit;
// Bound each invocation; later runs continue the queue, including large imports.
$deadline=microtime(true)+50;$failed=false;
try {
    $jobs=$db->query('SELECT id,next_check AS due,0 AS screenshot FROM sites WHERE next_check<='.time().' UNION ALL SELECT id,thumbnail_next AS due,1 AS screenshot FROM sites WHERE thumbnail_next<='.time().' ORDER BY due,id,screenshot')->fetchAll();
    foreach($jobs as $job) {
        if(microtime(true)>$deadline)break;
        try {
            $worked=$job['screenshot']?refreshThumbnail((int)$job['id']):checkSite((int)$job['id']);
            if($worked)echo ($job['screenshot']?'Küçük resim işlendi: ':'Kontrol edildi: ').$job['id'].PHP_EOL;
        } catch(Throwable $e) {$failed=true;fwrite(STDERR,$e->getMessage().PHP_EOL);}
    }
} finally {flock($lock,LOCK_UN);fclose($lock);}
exit($failed?1:0);
