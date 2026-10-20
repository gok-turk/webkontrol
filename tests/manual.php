<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/webkontrol-manual-'.bin2hex(random_bytes(8));putenv('MONITOR_DATA_DIR='.$dir);
require __DIR__.'/../src/browser.php';
function manualCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
function row(int $id):array{$q=db()->prepare('SELECT * FROM sites WHERE id=?');$q->execute([$id]);return $q->fetch();}
try {
 importSites("http://127.0.0.1\nhttp://127.0.0.2",5);
 db()->exec("UPDATE sites SET state='down',status=200,error='Otomatik hata'; INSERT INTO checks(site_id,checked_at,state,status,latency,error) VALUES(1,".time().",'down',200,100,'Otomatik hata')");
 manuallyConfirmUp(1);$s=row(1);
 manualCheck($s['state']==='down' && $s['error']==='Otomatik hata' && effectiveSiteState($s)==='up','Manuel açık görünümü otomatik sonucu değiştirmez');
 manualCheck((int)$s['manual_until']-(int)$s['manual_at']===86400 && $s['next_check']===$s['manual_until'],'Kontrol 24 saat ertelenir, kısa site aralığına bağlı değildir');
 manualCheck((int)db()->query('SELECT COUNT(*) FROM checks')->fetchColumn()===1,'Otomatik geçmiş ve uptime örnekleri korunur');
 manualCheck(!checkSite(1,true),'Yerel zorlanmış kontrol aktif manuel doğrulamayı ezmez');
 manualCheck(effectiveSiteState($s,(int)$s['manual_until'])==='down','24 saat dolunca otomatik durum geçerli olur');
 manualCheck(queueChecks(true)===1 && (int)row(1)['manual_until']>time(),'Hatalıları toplu kontrol aktif manuel kaydı atlar');
 clearManualConfirmation(1);manualCheck((int)row(1)['manual_until']===0 && (int)row(1)['next_check']===0,'Manuel doğrulama kaldırılınca site kuyruğa döner');
 enableBrowser();$jobs=claimBrowserJobs();$job=array_values(array_filter($jobs,fn($j)=>$j['id']===1))[0];
 manuallyConfirmUp(1);
 acceptBrowserResult(['id'=>1,'job'=>$job['job'],'state'=>'down','status'=>503,'latency'=>10,'error'=>'Eski iş']);
 manualCheck(effectiveSiteState(row(1))==='up' && (int)db()->query('SELECT COUNT(*) FROM checks')->fetchColumn()===1,'Devam eden eski tarayıcı işi manuel doğrulamayı ezemez');
 manualCheck(count(claimBrowserJobs())===0,'Tarayıcı aktif manuel kaydı almaz');
 db()->exec('UPDATE sites SET manual_until='.(time()-1).',next_check=0 WHERE id=1');
 $new=claimBrowserJobs();manualCheck(count($new)===1 && $new[0]['id']===1,'Süresi dolan manuel kayıt yeniden tarayıcı kontrolüne alınır');
 acceptBrowserResult(['id'=>1,'job'=>$new[0]['job'],'state'=>'up','status'=>200,'latency'=>10,'error'=>null]);
 manualCheck(row(1)['state']==='up' && row(1)['check_source']==='browser','Sonraki otomatik sonuç normal şekilde kaydedilir');
 manuallyConfirmUp(1);queueChecks(false);manualCheck((int)row(1)['manual_until']===0,'Tümünü kontrol et açık isteği manuel doğrulamayı erken kaldırır');
}finally{foreach(glob($dir.'/{*,.[!.]*}',GLOB_BRACE)?:[] as $file)if(is_file($file))unlink($file);if(is_dir($dir))rmdir($dir);}
