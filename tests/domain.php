<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/webkontrol-domain-'.bin2hex(random_bytes(8));putenv('MONITOR_DATA_DIR='.$dir);
require __DIR__.'/../src/browser.php';
function verify(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try {
 importSites('example.com');enableBrowser();$job=claimBrowserJobs()[0];
 verify($job['domain_lookup']===true,'Yeni domain sorgusu işaretlenir');
 $result=['id'=>1,'job'=>$job['job'],'state'=>'down','status'=>503,'latency'=>10,'error'=>null,'domain'=>['expires'=>1893456000,'error'=>null]];
 $invalid=$result;$invalid['domain']['expires']='2030-01-01';$rejected=false;
 try{acceptBrowserResult($invalid);}catch(InvalidArgumentException $e){$rejected=true;}
 verify($rejected && (int)db()->query('SELECT COUNT(*) FROM checks')->fetchColumn()===0,'Geçersiz domain sonucu hiçbir ölçüm yazmadan reddedilir');
 acceptBrowserResult($result);acceptBrowserResult($result);
 $s=db()->query('SELECT * FROM sites WHERE id=1')->fetch();
 verify((int)$s['domain_expires']===1893456000 && (int)$s['domain_checked']>0,'Domain sonucu kaydedilir');
 verify((int)db()->query('SELECT COUNT(*) FROM checks')->fetchColumn()===1,'Tekrarlanan sonuç geçmişi çoğaltmaz');
 queueChecks();verify(claimBrowserJobs()[0]['domain_lookup']===false,'Domain günlük önbelleği kullanılır');
 saveDomainResult(1,['expires'=>0,'error'=>'Servis erişilemiyor']);
 verify((int)db()->query('SELECT domain_expires FROM sites')->fetchColumn()===1893456000,'Geçici hatada son bilinen tarih korunur');
 verify(str_contains(domainExpiryLabel($s,1893369600),'1 gün kaldı'),'Kalan gün gösterilir');
 verify(str_contains(domainExpiryLabel($s,1893542400),'Süresi doldu'),'Süresi geçen domain belirtilir');
 verify(str_contains(failureReason(null,503),'sunucu'),'HTTP hatası açıklanır');
 verify(str_contains(failureReason('net::ERR_NAME_NOT_RESOLVED'),'DNS'),'DNS hatası açıklanır');
 verify(str_contains(failureReason('net::ERR_CERT_DATE_INVALID'),'SSL'),'Sertifika hatası açıklanır');
 verify(domainExpiry(['events'=>[['eventAction'=>'registration','eventDate'=>'2030-01-01']]])===0,'Kayıt tarihi bitiş tarihi sayılmaz');
}finally{foreach(is_dir($dir)?scandir($dir):[] as $name){$file=$dir.'/'.$name;if(is_file($file))unlink($file);}if(is_dir($dir))rmdir($dir);}
