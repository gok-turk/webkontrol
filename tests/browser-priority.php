<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/webkontrol-priority-'.bin2hex(random_bytes(8));putenv('MONITOR_DATA_DIR='.$dir);
require __DIR__.'/../src/browser.php';
function priorityCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try {
    importSites("first.example\nfailed.example\nnew.example\nnew-failed.example");enableBrowser();
    db()->exec("UPDATE sites SET state='up' WHERE id=1; UPDATE sites SET state='down' WHERE id IN (2,4)");
    $jobs=claimBrowserJobs(2);
    priorityCheck(array_column($jobs,'id')===[4,2],'Kuyruktaki hatalı siteler önce, panel sırasına göre kontrol edilir');
    acceptBrowserResult(['id'=>4,'job'=>$jobs[0]['job'],'state'=>'up','status'=>200,'latency'=>10,'error'=>null]);
    priorityCheck(claimBrowserJobs(1)[0]['id']===3,'Sonuçlanan site tekrar alınmaz; sonraki bekleyen site işlenir');
    priorityCheck((int)db()->query("SELECT COUNT(*) FROM sites WHERE check_source='browser'")->fetchColumn()===1,'İlerleme sayısı yalnızca gerçek tarayıcı sonucu gelen siteleri sayar');
}finally{foreach(glob($dir.'/{*,.[!.]*}',GLOB_BRACE)?:[] as $file)if(is_file($file))unlink($file);if(is_dir($dir))rmdir($dir);}
