<?php
declare(strict_types=1);
require __DIR__.'/../src/app.php';
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off']);
$sessions=(getenv('MONITOR_DATA_DIR') ?: ROOT.'/var').'/sessions';
if(!is_dir($sessions) && !mkdir($sessions,0700,true))throw new RuntimeException('Oturum dizini oluşturulamadı.');
session_save_path($sessions);
session_start();
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https:; style-src 'self'; form-action 'self'; frame-ancestors 'none'");
$password=getenv('MONITOR_PASSWORD');
if (!$password) {http_response_code(503);echo 'Başlatmadan önce MONITOR_PASSWORD ortam değişkenini tanımlayın.';exit;}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32)); $error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) {http_response_code(403);exit('Geçersiz oturum.');}
    try {
        $action=$_POST['action']??'';
        if ($action==='login') {
            if(!hash_equals($password,(string)($_POST['password']??''))) {usleep(500000);throw new RuntimeException('Parola hatalı.');}
            session_regenerate_id(true);$_SESSION['auth']=hash('sha256',$password);
        } elseif (isset($_SESSION['auth']) && hash_equals(hash('sha256',$password),$_SESSION['auth'])) {
            if ($action==='view') {
                $view=(string)($_POST['view']??'');
                if(!in_array($view,['list','grid'],true))throw new InvalidArgumentException('Geçersiz görünüm.');
                $_SESSION['site_view']=$view;
            }
            elseif ($action==='logout') {$_SESSION=[];session_destroy();}
            elseif ($action==='add' || $action==='bulk') {
                $minutes=filter_var($_POST['minutes']??1440,FILTER_VALIDATE_INT);
                if(!$minutes || $minutes<1 || $minutes>1440)throw new InvalidArgumentException('Aralık 1–1440 dakika olmalı.');
                $result=importSites((string)($_POST[$action==='bulk'?'urls':'url']??''),$minutes);
                $_SESSION['notice']=$result['added'].' site eklendi, '.$result['duplicates'].' tekrar atlandı.'.($result['errors']?' Hatalar: '.implode(' / ',$result['errors']):'');
            } elseif ($action==='interval') {
                $minutes=filter_var($_POST['minutes']??'',FILTER_VALIDATE_INT);
                if(!$minutes || $minutes<1 || $minutes>1440)throw new InvalidArgumentException('Aralık 1–1440 dakika olmalı.');
                db()->prepare('UPDATE sites SET interval_seconds=?,next_check=0 WHERE id=?')->execute([$minutes*60,(int)($_POST['id']??0)]);
            } elseif ($action==='delete') {
                $id=(int)($_POST['id']??0);db()->prepare('DELETE FROM sites WHERE id=?')->execute([$id]);
                if(is_file(thumbnailFile($id)))unlink(thumbnailFile($id));
            }
            elseif ($action==='check') checkSite((int)($_POST['id']??0),true);
        } else throw new RuntimeException('Oturum açın.');
        header('Location: ./'.($action==='view'?'#sites':''));exit;
    } catch(Throwable $ex) {$error=$ex instanceof PDOException?databaseError($ex):$ex->getMessage();}
}
$auth=isset($_SESSION['auth']) && hash_equals(hash('sha256',$password),$_SESSION['auth']);
if (isset($_GET['thumbnail'])) {
    if(!$auth){http_response_code(403);exit;}
    $id=(int)$_GET['thumbnail'];$q=db()->prepare('SELECT id FROM sites WHERE id=?');$q->execute([$id]);
    $file=thumbnailFile($id);
    if(!$q->fetch() || !is_file($file)){http_response_code(404);exit;}
    $info=@getimagesize($file);if(!$info){http_response_code(404);exit;}
    header('Content-Type: '.$info['mime']);header('Cache-Control: private, max-age=3600');readfile($file);exit;
}
$view=($_SESSION['site_view']??'list')==='grid'?'grid':'list';
$notice=$_SESSION['notice']??null;unset($_SESSION['notice']);
function csrf(): void {echo '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';}
function dateLabel(?int $t): string {return $t?gmdate('d.m.Y H:i:s',$t).' UTC':'Henüz kontrol edilmedi';}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>WebKontrol · Site izleme</title><link rel="stylesheet" href="style.css?v=2"></head><body><main>
<header><div><span class="eyebrow">SİTELERİNİZİN NABZI</span><h1>WebKontrol<span>●</span></h1><p>Sitelerinizi tek yerden takip edin.</p></div><?php if($auth):?><form method="post"><?php csrf();?><button name="action" value="logout" class="secondary">Çıkış yap</button></form><?php endif;?></header>
<?php if($notice):?><p class="notice" role="status"><?=e($notice)?></p><?php endif;?>
<?php if($error):?><p class="error" role="alert"><?=e($error)?></p><?php endif;?>
<?php if(!$auth):?><section class="login"><h2>Panelinize giriş yapın</h2><form method="post"><?php csrf();?><label>Yönetici parolası<input type="password" name="password" required autocomplete="current-password"></label><button name="action" value="login">Giriş yap</button></form></section>
<?php else:
$sites=db()->query("SELECT s.*, (SELECT ROUND(100.0*SUM(state='up')/COUNT(*),2) FROM checks c WHERE c.site_id=s.id AND c.checked_at>=strftime('%s','now')-2592000) AS uptime FROM sites s ORDER BY s.id DESC")->fetchAll();
$up=count(array_filter($sites,fn($s)=>$s['state']==='up'));$down=count(array_filter($sites,fn($s)=>$s['state']==='down'));
?><section class="stats"><div><span>İzlenen site</span><strong><?=count($sites)?></strong></div><div><span>Erişilebilir</span><strong class="green"><?=$up?></strong></div><div><span>Kesinti / hata</span><strong class="red"><?=$down?></strong></div></section>
<section class="add"><h2>Site ekle</h2><form method="post" class="grid single-add"><?php csrf();?><input type="hidden" name="action" value="add"><label>Domain veya URL<input name="url" placeholder="www.ornek.com veya https://ornek.com" required></label><label>Kontrol aralığı (dakika)<input type="number" name="minutes" value="1440" min="1" max="1440" required></label><button>Site ekle</button></form>
<details class="bulk"<?= $error && ($_POST['action']??'')==='bulk' ? ' open' : '' ?>><summary>Toplu domain ekle</summary><form method="post"><?php csrf();?><input type="hidden" name="action" value="bulk"><label>Her satıra bir domain veya URL (en fazla 1000)<textarea name="urls" rows="8" placeholder="ornek.com&#10;www.ikincisite.com&#10;https://ucuncusite.com" required><?=e($_POST['urls']??'')?></textarea></label><input type="hidden" name="minutes" value="1440"><button>Toplu ekle · 24 saat</button></form></details>
<p class="hint">Site adı domain üzerinden, başındaki www. kaldırılarak oluşturulur. Varsayılan kontrol aralığı 24 saattir. Kontroller ve otomatik küçük resimler için cron gereklidir. Küçük resim hizmeti: thum.io; site adresleri bu servise gönderilir.</p></section>
<div class="section-title" id="sites"><h2>Siteleriniz</h2><form method="post" class="view-switch" aria-label="Site görünümü"><?php csrf();?><input type="hidden" name="action" value="view"><button type="submit" name="view" value="list" aria-pressed="<?=$view==='list'?'true':'false'?>">Liste</button><button type="submit" name="view" value="grid" aria-pressed="<?=$view==='grid'?'true':'false'?>">İkili grid</button></form><span>Son 30 gün · Kontrol örneklerine göre erişilebilirlik</span></div>
<?php if(!$sites):?><section class="empty"><h2>İlk sitenizi ekleyin</h2><p>Kontrol sonuçları ve erişilebilirlik geçmişi burada görünecek.</p></section><?php endif;?>
<div class="sites sites-<?=e($view)?>">
<?php foreach($sites as $s):?><article class="site"><div class="preview"><?php if($s['thumbnail_at'] && is_file(thumbnailFile((int)$s['id']))):?><img src="?thumbnail=<?=e($s['id'])?>&amp;v=<?=e($s['thumbnail_at'])?>" alt="<?=e($s['name'])?> ekran görüntüsü" loading="lazy"><?php else:?><span>WWW</span><?php endif;?></div><div class="details"><?php if($s['thumbnail_error']):?><p class="hint">Küçük resim: <?=e($s['thumbnail_error'])?> (1 saat sonra yeniden denenecek.)</p><?php endif;?><?php if($s['thumbnail_at']):?><p class="hint">Küçük resim güncellendi: <?=e(dateLabel((int)$s['thumbnail_at']))?></p><?php endif;?><div class="site-title"><h3><a href="<?=e($s['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($s['name'])?></a></h3><span class="badge <?=e($s['state']??'pending')?>"><?=match($s['state']){'up'=>'Erişilebilir','down'=>'Kesinti / hata',default=>'Bekliyor'}?></span></div><p class="url"><?=e($s['url'])?></p><div class="metrics"><span>Erişilebilirlik <b><?= $s['uptime']!==null?e($s['uptime']).'%':'—'?></b></span><span>Yanıt <b><?=$s['latency']!==null?e($s['latency']).' ms':'—'?></b></span><span>HTTP <b><?=e($s['status']?:'—')?></b></span><span>Aralık <b><?=e($s['interval_seconds']/60)?> dk</b></span></div><p class="hint">Son kontrol: <?=e(dateLabel($s['checked_at']?(int)$s['checked_at']:null))?></p><?php if($s['error']):?><p class="error"><?=e($s['error'])?></p><?php endif;?><details><summary>Son 20 kontrol</summary><div class="history"><?php $q=db()->prepare('SELECT * FROM checks WHERE site_id=? ORDER BY id DESC LIMIT 20');$q->execute([$s['id']]);foreach($q as $c):?><p><?=e(dateLabel((int)$c['checked_at']))?> · <?=e($c['state']==='up'?'Açık':'Kesinti / hata')?> · HTTP <?=e($c['status'])?> · <?=e($c['latency'])?> ms <?=e($c['error'])?></p><?php endforeach;?></div></details></div><div class="actions"><form method="post"><?php csrf();?><input type="hidden" name="id" value="<?=e($s['id'])?>"><label>Aralık (dk)<input type="number" name="minutes" min="1" max="1440" value="<?=e($s['interval_seconds']/60)?>" required></label><button name="action" value="interval" class="secondary">Aralığı kaydet</button></form><form method="post"><?php csrf();?><input type="hidden" name="id" value="<?=e($s['id'])?>"><button name="action" value="check">Şimdi kontrol et</button></form><form method="post"><?php csrf();?><input type="hidden" name="id" value="<?=e($s['id'])?>"><button class="secondary danger" name="action" value="delete">Siteyi sil</button></form></div></article><?php endforeach;?>
</div>
<footer>WebKontrol · Sonuçlar bu sunucunun erişimini gösterir. HTTP 200–399 açık kabul edilir. Geçmiş 90 gün saklanır.</footer>
<?php endif;?></main></body></html>
