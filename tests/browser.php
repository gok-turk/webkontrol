<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/webkontrol-browser-'.bin2hex(random_bytes(8));putenv('MONITOR_DATA_DIR='.$dir);
require __DIR__.'/../src/browser.php';
function verifyBrowser(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try {
    importSites("https://first.example\nhttps://second.example");enableBrowser();
    verifyBrowser(browserEnabled(),'Harici tarayıcı modu açılır');
    verifyBrowser(strlen(trim((string)file_get_contents($dir.'/browser-token')))===64,'Bağımsız API anahtarı oluşturulur');
    verifyBrowser(is_file($dir.'/.htaccess'),'Veri dizini Apache erişimine kapatılır');
    verifyBrowser(!checkSite(1,true),'Tarayıcı modunda eski yerel kontrol devre dışıdır');
    $jobs=claimBrowserJobs();verifyBrowser(count($jobs)===2 && count(claimBrowserJobs())===0,'İşler tek kez ve süreli kilitle atanır');
    $result=['id'=>1,'job'=>$jobs[0]['job'],'state'=>'up','status'=>200,'latency'=>1500,'error'=>null];
    try{acceptBrowserResult($result+['screenshot'=>'invalid']);throw new LogicException('Geçersiz resim kabul edildi');}catch(InvalidArgumentException $e){echo 'PASS Geçersiz resim reddedilir'.PHP_EOL;}
    acceptBrowserResult($result);
    $row=db()->query('SELECT * FROM sites WHERE id=1')->fetch();verifyBrowser($row['state']==='up' && $row['check_source']==='browser' && (int)$row['next_check']-(int)$row['checked_at']===86400,'Tarayıcı sonucu ve 24 saatlik program kaydedilir');
    try{acceptBrowserResult($result);throw new LogicException('Tekrar kabul edildi');}catch(DomainException $e){echo 'PASS Tekrarlanan sonuç reddedilir'.PHP_EOL;}
    db()->exec('UPDATE sites SET lease_until=0 WHERE id=2');$renewed=claimBrowserJobs()[0];
    try{acceptBrowserResult(['id'=>2,'job'=>$jobs[1]['job'],'state'=>'down','status'=>0,'latency'=>10,'error'=>'Eski iş']);throw new LogicException('Eski iş kabul edildi');}catch(DomainException $e){echo 'PASS Eski iş yeni işin sonucunu ezemez'.PHP_EOL;}
    acceptBrowserResult(['id'=>2,'job'=>$renewed['job'],'state'=>'down','status'=>503,'latency'=>10,'error'=>'HTTP 503']);
    verifyBrowser((int)db()->query('SELECT next_check-checked_at FROM sites WHERE id=2')->fetchColumn()===300,'Tarayıcı hatası 5 dakika sonra yeniden planlanır');
    verifyBrowser((int)db()->query('SELECT COUNT(*) FROM checks')->fetchColumn()===2,'Geçersiz ve tekrar istekleri geçmişe yazılmaz');
}finally{
    foreach(glob($dir.'/thumbnails/*')?:[] as $file)unlink($file);if(is_dir($dir.'/thumbnails'))rmdir($dir.'/thumbnails');
    foreach(glob($dir.'/{*,.[!.]*}',GLOB_BRACE)?:[] as $file)if(is_file($file))unlink($file);if(is_dir($dir))rmdir($dir);
}
