<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/webkontrol-features-'.bin2hex(random_bytes(8));mkdir($dir,0700);
putenv('MONITOR_DATA_DIR='.$dir);
// Simulate an existing installation with a saved custom interval.
$old=new PDO('sqlite:'.$dir.'/monitor.sqlite');
$old->exec("CREATE TABLE sites (id INTEGER PRIMARY KEY,name TEXT NOT NULL,url TEXT NOT NULL UNIQUE,interval_seconds INTEGER NOT NULL,next_check INTEGER NOT NULL DEFAULT 0,checked_at INTEGER,state TEXT,status INTEGER,latency INTEGER,error TEXT,thumbnail TEXT,lease_until INTEGER NOT NULL DEFAULT 0); INSERT INTO sites(name,url,interval_seconds) VALUES('Mevcut','https://existing.example/',300)");$old=null;
require __DIR__.'/../src/app.php';
function verify(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try {
    $db=db();verify((int)$db->query('SELECT interval_seconds FROM sites WHERE id=1')->fetchColumn()===300,'Yükseltme mevcut kayıt ve aralığı korur');
    verify(in_array('thumbnail_next',array_column($db->query('PRAGMA table_info(sites)')->fetchAll(),'name'),true),'Mevcut SQLite otomatik güncellenir');
    $lines=[];for($i=0;$i<100;$i++)$lines[]='www.site'.$i.'.example';
    $r=importSites(implode("\n",$lines));verify($r['added']===100,'100 domain toplu eklenir');
    verify($db->query('SELECT name FROM sites WHERE id=2')->fetchColumn()==='site0.example','Site adı www olmadan oluşur');
    verify($db->query('SELECT url FROM sites WHERE id=2')->fetchColumn()==='https://www.site0.example/','www istek adresinde korunur');
    verify((int)$db->query('SELECT COUNT(*) FROM sites WHERE interval_seconds=86400')->fetchColumn()===100,'Yeni siteler 24 saat aralığı kullanır');
    $r=importSites("www.site0.example\nfile:///etc/passwd\nhttps://new.example");verify($r['added']===1 && $r['duplicates']===1 && count($r['errors'])===1,'Tekrar ve hatalı satırlar ayrılır');
    verify(siteInput('EXAMPLE.COM')['url']==='https://example.com/','Şemasız domain normalize edilir');
    try{importSites(str_repeat("example.com\n",1001));throw new LogicException('Sınır uygulanmadı');}catch(InvalidArgumentException $e){echo 'PASS Toplu ekleme sınırı'.PHP_EOL;}
    $db->exec("UPDATE sites SET next_check=9999999999; UPDATE sites SET state='down' WHERE id IN (1,2)");
    verify(queueChecks(true)===2,'Yalnızca hatalı siteler kontrol kuyruğuna alınır');
    verify((int)$db->query('SELECT COUNT(*) FROM sites WHERE next_check=0')->fetchColumn()===2,'Sağlıklı ve bekleyen sitelerin programı korunur');
    $db->exec("UPDATE sites SET state='up' WHERE id=2");
    verify(queueChecks(false)===102,'Tüm siteler filtrelerden bağımsız kuyruğa alınır');
    verify((int)$db->query('SELECT COUNT(*) FROM sites WHERE next_check<>0')->fetchColumn()===0,'Toplu kontrol tüm sitelerin zamanını öne alır');
    $endpoint=getenv('MONITOR_SCREENSHOT_ENDPOINT');
    if($endpoint) {
        $db->exec("INSERT INTO sites(name,url,interval_seconds) VALUES('Görsel','https://1.1.1.1/',86400)");$id=(int)$db->lastInsertId();
        verify(refreshThumbnail($id),'İlk ekran görüntüsü işi çalışır');
        verify(is_file(thumbnailFile($id)),'Servis resmi yerel diske kaydedilir');
        $s=$db->query('SELECT * FROM sites WHERE id='.$id)->fetch();
        verify(!$s['thumbnail_error'] && $s['thumbnail_next']-$s['thumbnail_at']===86400,'Başarılı resim 24 saat sonra yenilenir');
        verify(!refreshThumbnail($id),'24 saat dolmadan resim tekrar istenmez');
        $db->exec('UPDATE sites SET thumbnail_next=0,thumbnail_lease='.(time()+90).' WHERE id='.$id);
        verify(!refreshThumbnail($id),'Resim işi kilide uyar');
        $db->exec('UPDATE sites SET thumbnail_next=0,thumbnail_lease=0 WHERE id='.$id);
        putenv('MONITOR_SCREENSHOT_ENDPOINT='.$endpoint.'invalid/');refreshThumbnail($id);
        $s=$db->query('SELECT * FROM sites WHERE id='.$id)->fetch();
        verify($s['thumbnail_error']!==null && $s['thumbnail_next']>=time()+3590,'Bozuk resim reddedilir ve 1 saat sonra tekrar denenir');
        verify(is_file(thumbnailFile($id)),'Servis hatasında önceki resim korunur');
    } else echo 'UNRUN Ekran görüntüsü HTTP testleri için MONITOR_SCREENSHOT_ENDPOINT fixture gerekli'.PHP_EOL;
} finally {
    foreach(glob($dir.'/thumbnails/*')?:[] as $file)unlink($file);
    if(is_dir($dir.'/thumbnails'))rmdir($dir.'/thumbnails');
    foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir);
}
