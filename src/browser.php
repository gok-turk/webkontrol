<?php
declare(strict_types=1);
require_once __DIR__.'/app.php';
function enableBrowser(): void {
    db();
    $guard=dataDir().'/.htaccess';
    if(!is_file($guard)){ $handle=fopen($guard,'x');if(!$handle)throw new RuntimeException('Veri dizini koruması oluşturulamadı.');fwrite($handle,"Require all denied\n");fclose($handle);}
    $file=dataDir().'/browser-token';
    if(!is_file($file)) {
        $handle=fopen($file,'x');if(!$handle)throw new RuntimeException('Tarayıcı anahtarı oluşturulamadı.');
        chmod($file,0600);fwrite($handle,bin2hex(random_bytes(32)));fclose($handle);
    }
    if(file_put_contents(dataDir().'/browser-enabled','1',LOCK_EX)===false)throw new RuntimeException('Tarayıcı modu etkinleştirilemedi.');
    queueChecks();
}
function claimBrowserJobs(int $limit=6): array {
    $db=db();$now=time();$jobs=[];
    $rows=$db->query('SELECT * FROM sites WHERE next_check<='.$now.' AND lease_until<='.$now.' ORDER BY next_check,id LIMIT '.max(1,min(6,$limit)))->fetchAll();
    foreach($rows as $site) {
        $nonce=bin2hex(random_bytes(24));
        $q=$db->prepare('UPDATE sites SET lease_until=?,browser_job=? WHERE id=? AND lease_until<=? AND next_check<=?');
        $q->execute([$now+600,$nonce,$site['id'],$now,$now]);
        if($q->rowCount())$jobs[]=['id'=>(int)$site['id'],'url'=>$site['url'],'job'=>$nonce,'screenshot'=>($site['check_source']??'server')!=='browser' || !empty($site['thumbnail_error']) || (int)$site['thumbnail_at']<=$now-86400 || !is_file(thumbnailFile((int)$site['id']))];
    }
    return $jobs;
}
function acceptBrowserResult(array $data): void {
    $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);$job=$data['job']??'';
    $status=filter_var($data['status']??null,FILTER_VALIDATE_INT);$latency=filter_var($data['latency']??null,FILTER_VALIDATE_INT);
    $state=$data['state']??'';$error=$data['error']??null;
    if(!$id || !is_string($job) || !preg_match('/^[a-f0-9]{48}$/D',$job) || !in_array($state,['up','down'],true) || $status===false || $status<0 || $status>599 || $latency===false || $latency<0 || $latency>120000 || ($error!==null && (!is_string($error) || strlen($error)>2000)))throw new InvalidArgumentException('Geçersiz sonuç.');
    if($state==='up' && ($status<200 || $status>=400 || $error!==null))throw new InvalidArgumentException('Başarılı sonuç HTTP durumuyla uyuşmuyor.');
    $image=null;
    if(isset($data['screenshot'])) {
        if(!is_string($data['screenshot']) || strlen($data['screenshot'])>2800000)throw new InvalidArgumentException('Resim boyutu geçersiz.');
        $image=base64_decode($data['screenshot'],true);$info=$image===false?false:@getimagesizefromstring($image);
        if(!$info || $info['mime']!=='image/jpeg' || strlen($image)>2000000)throw new InvalidArgumentException('Geçersiz JPEG resmi.');
    }
    $db=db();$db->beginTransaction();$tmp=null;
    try {
        $q=$db->prepare('SELECT * FROM sites WHERE id=? AND browser_job=? AND lease_until>?');$q->execute([$id,$job,time()]);$site=$q->fetch();
        if(!$site)throw new DomainException('İş tamamlanmış, süresi dolmuş veya silinmiş.');
        $at=time();$next=$at+($state==='down'?min(300,(int)$site['interval_seconds']):(int)$site['interval_seconds']);
        $db->prepare('INSERT INTO checks(site_id,checked_at,state,status,latency,error) VALUES(?,?,?,?,?,?)')->execute([$id,$at,$state,$status,$latency,$error]);
        $db->prepare("UPDATE sites SET checked_at=?,state=?,status=?,latency=?,error=?,next_check=?,lease_until=0,browser_job=NULL,check_source='browser' WHERE id=?")->execute([$at,$state,$status,$latency,$error,$next,$id]);
        if($image!==null) {
            $dir=dirname(thumbnailFile($id));if(!is_dir($dir) && !mkdir($dir,0700,true))throw new RuntimeException('Resim dizini oluşturulamadı.');
            $tmp=tempnam($dir,'browser-');if(!$tmp || file_put_contents($tmp,$image)===false || !rename($tmp,thumbnailFile($id)))throw new RuntimeException('Resim kaydedilemedi.');
            $db->prepare('UPDATE sites SET thumbnail_at=?,thumbnail_next=?,thumbnail_error=NULL WHERE id=?')->execute([$at,$at+86400,$id]);
        } elseif(!empty($data['screenshot_error']) && is_string($data['screenshot_error'])) {
            $db->prepare('UPDATE sites SET thumbnail_error=? WHERE id=?')->execute([substr($data['screenshot_error'],0,500),$id]);
        }
        $db->prepare('DELETE FROM checks WHERE checked_at<?')->execute([$at-90*86400]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($tmp && is_file($tmp))unlink($tmp);throw $e;}
}
