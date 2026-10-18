<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/webkontrol-test-'.bin2hex(random_bytes(8));
putenv('MONITOR_DATA_DIR='.$dir);
require __DIR__.'/../src/app.php';
function expect(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);echo 'PASS '.$message.PHP_EOL;}
try {
    expect(redirectUrl('https://example.com/a/b','../c')==='https://example.com/c','Göreli yönlendirme çözülür');
    expect(redirectUrl('https://example.com/a?q=1','?q=2')==='https://example.com/a?q=2','Sorgu yönlendirmesi çözülür');
    $db=db();$db->prepare('INSERT INTO sites(name,url,interval_seconds) VALUES(?,?,?)')->execute(['Test','http://127.0.0.1',60]);$id=(int)$db->lastInsertId();
    expect(checkSite($id),'İlk kontrol çalışır');
    $s=$db->query('SELECT * FROM sites')->fetch();
    expect($s['state']==='down' && str_contains($s['error'],'genel internete'),'Özel ağ isteği engellenir ve kaydedilir');
    expect(!checkSite($id),'Zamanı gelmeyen kontrol atlanır');
    expect(checkSite($id,true),'Manuel kontrol aralığı beklemez');
    $db->exec('UPDATE sites SET lease_until='.(time()+120));
    expect(!checkSite($id,true),'Manuel kontrol de etkin kilide uyar');
    expect((int)$db->query('SELECT COUNT(*) FROM checks')->fetchColumn()===2,'İki gerçek kontrol geçmişe yazılır');
    $db->exec('DELETE FROM sites');expect((int)$db->query('SELECT COUNT(*) FROM checks')->fetchColumn()===0,'Silme geçmişi temizler');
    foreach(['file:///etc/passwd','https://user:pass@example.com'] as $bad){try{validateUrl($bad);throw new LogicException('Kabul edildi');}catch(InvalidArgumentException $e){echo 'PASS Geçersiz URL reddedilir'.PHP_EOL;}}
} finally {
    foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir);
}
