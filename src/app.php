<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
function db(): PDO {
    static $db;
    if ($db) return $db;
    $dir = getenv('MONITOR_DATA_DIR') ?: ROOT . '/var';
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Veri dizini oluşturulamadı.');
    $db = new PDO('sqlite:' . $dir . '/monitor.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=10000; PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
    $db->exec('CREATE TABLE IF NOT EXISTS sites (id INTEGER PRIMARY KEY, name TEXT NOT NULL, url TEXT NOT NULL UNIQUE, interval_seconds INTEGER NOT NULL, next_check INTEGER NOT NULL DEFAULT 0, checked_at INTEGER, state TEXT, status INTEGER, latency INTEGER, error TEXT, thumbnail TEXT, lease_until INTEGER NOT NULL DEFAULT 0); CREATE TABLE IF NOT EXISTS checks (id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE, checked_at INTEGER NOT NULL, state TEXT NOT NULL, status INTEGER, latency INTEGER NOT NULL, error TEXT); CREATE INDEX IF NOT EXISTS checks_site_time ON checks(site_id, checked_at);');
    $columns=array_column($db->query('PRAGMA table_info(sites)')->fetchAll(),'name');
    foreach (['thumbnail_at','thumbnail_next','thumbnail_lease','thumbnail_error','browser_job','browser_last_job','check_source','manual_at','manual_until'] as $column) {
        if (!in_array($column,$columns,true)) {
            try {$db->exec('ALTER TABLE sites ADD COLUMN '.$column.(in_array($column,['thumbnail_error','browser_job','browser_last_job','check_source'],true)?' TEXT':' INTEGER NOT NULL DEFAULT 0'));}
            catch(PDOException $e) {if(!in_array($column,array_column($db->query('PRAGMA table_info(sites)')->fetchAll(),'name'),true))throw $e;}
        }
    }
    return $db;
}
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function validateUrl(string $url): string {
    $p = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower($p['scheme'] ?? ''), ['http','https'], true) || isset($p['user']) || isset($p['pass'])) throw new InvalidArgumentException('Geçerli bir http/https adresi girin.');
    return $url;
}
// Independent DNS-over-HTTPS fallback: preserve TLS and validate every returned IP.
function externalDns(string $host, float $deadline): array {
    $errors=[];
    foreach ([['cloudflare-dns.com','1.1.1.1','https://cloudflare-dns.com/dns-query'],['dns.google','8.8.8.8','https://dns.google/resolve']] as [$provider,$ip,$endpoint]) {
        foreach(['A','AAAA'] as $type) {
            $remaining=(int)floor($deadline-microtime(true));if($remaining<1)break 2;
            $bytes='';$ch=curl_init($endpoint.'?'.http_build_query(['name'=>$host,'type'=>$type]));
            curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>min(3,$remaining),CURLOPT_TIMEOUT=>min(4,$remaining),CURLOPT_HTTPHEADER=>['Accept: application/dns-json'],CURLOPT_RESOLVE=>[$provider.':443:'.$ip],CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$bytes){if(strlen($bytes)+strlen($chunk)>65536)return 0;$bytes.=$chunk;return strlen($chunk);}]);
            $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
            if($ok===false || $status!==200){$errors[]=$provider.': '.($error?:'HTTP '.$status);break;}
            $json=json_decode($bytes,true);
            if(!is_array($json) || !isset($json['Status'])){$errors[]=$provider.': geçersiz DNS yanıtı';break;}
            if((int)$json['Status']!==0){$errors[]=$provider.': DNS kodu '.(int)$json['Status'];break;}
            $ips=[];foreach($json['Answer']??[] as $answer)if(in_array((int)($answer['type']??0),[1,28],true) && filter_var($answer['data']??'',FILTER_VALIDATE_IP))$ips[]=$answer['data'];
            if($ips)return $ips;
        }
    }
    throw new RuntimeException('DNS çözümlenemedi. Yerel çözümleyici ve HTTPS DNS yedeği sonuç vermedi. '.implode(' / ',array_unique($errors)));
}
function publicAddresses(string $host, bool $externalOnly=false, ?float $deadline=null): array {
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
    else {
        $ips=[];
        if(!$externalOnly) {
            $records = array_merge(@dns_get_record($host, DNS_A) ?: [], @dns_get_record($host, DNS_AAAA) ?: []);
            $ips = array_values(array_filter(array_map(fn($r)=>$r['ip'] ?? $r['ipv6'] ?? null, $records)));
            if (!$ips) $ips = @gethostbynamel($host) ?: [];
        }
        if (!$ips) $ips=externalDns($host,min($deadline??microtime(true)+10,microtime(true)+10));
    }
    foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || str_starts_with(strtolower($ip),'::ffff:') || str_starts_with(strtolower($ip),'ff') || (filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) && (int)explode('.',$ip)[0]>=224)) throw new RuntimeException('Yalnızca genel internete açık adresler izlenebilir.');
    $ips=array_values(array_unique($ips));
    usort($ips,fn($a,$b)=>(int)str_contains($a,':')<=>(int)str_contains($b,':'));
    return $ips;
}
function redirectUrl(string $base, string $location): string {
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $location)) return validateUrl($location);
    $p=parse_url($base); $origin=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
    if (str_starts_with($location,'//')) return validateUrl($p['scheme'].':'.$location);
    $r=parse_url($location); if($r===false)throw new RuntimeException('Geçersiz yönlendirme.');
    $path=$r['path']??'';
    if($path==='') $path=$p['path']??'/';
    elseif($path[0]!=='/') $path=substr($p['path']??'/',0,strrpos($p['path']??'/','/')+1).$path;
    $parts=[];foreach(explode('/',$path) as $part){if($part==='..')array_pop($parts);elseif($part!=='.')$parts[]=$part;}
    $path=implode('/',$parts);if($path==='')$path='/';
    $query=isset($r['query'])?'?'.$r['query']:((!isset($r['path']) && isset($p['query']))?'?'.$p['query']:'');
    return validateUrl($origin.$path.$query);
}
function probe(string $url): array {
    $start = microtime(true); $status = 0; $error = null;
    try {
        for ($hop=0; $hop<6; $hop++) {
            validateUrl($url); $p=parse_url($url); $host=trim($p['host'], '[]');
            $port=$p['port'] ?? ($p['scheme']==='https' ? 443 : 80);
            if (!in_array($port,[80,443],true)) throw new RuntimeException('Yalnızca 80 ve 443 portları desteklenir.');
            $ips=publicAddresses($host,false,$start+75);$attempted=[];$attemptErrors=[];$ok=false;$truncated=false;$err='Bağlantı kurulamadı.';
            for($round=0;$round<2;$round++) {
            foreach(array_slice(array_diff($ips,$attempted),0,4) as $ip) {
            $attempted[]=$ip;
            $remaining=(int)floor(75-(microtime(true)-$start));
            if($remaining<1)throw new RuntimeException('Toplam kontrol süresi aşıldı.');
            $location=null; $bytes=0; $truncated=false;
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>min(15,$remaining),CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'WebKontrol/1.0',CURLOPT_RESOLVE=>[$host.':'.$port.':'.(str_contains($ip,':')?'['.$ip.']':$ip)],CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$location){if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);},CURLOPT_WRITEFUNCTION=>function($ch,$data)use(&$bytes,&$truncated){$bytes+=strlen($data); if($bytes>1048576){$truncated=true;return 0;}return strlen($data);}]);
            $ok=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch);$errno=curl_errno($ch); curl_close($ch);
            if($ok===false && !$truncated)$attemptErrors[]=$ip.' (cURL '.$errno.')';
            if ($ok!==false || $truncated)break;
            }
            if($ok!==false || $truncated || $round===1)break;
            try {$ips=publicAddresses($host,true,$start+75);}
            catch(Throwable $dnsError){$err.=' HTTPS DNS yedeği: '.$dnsError->getMessage();break;}
            }
            if ($ok===false && !$truncated) throw new RuntimeException('Bu sunucudan bağlantı kurulamadı: '.$err.' Denenen adresler: '.implode(', ',$attemptErrors));
            if ($status>=300 && $status<400 && $location) {
                if ($hop===5) throw new RuntimeException('Çok fazla yönlendirme.');
                $url=redirectUrl($url,$location);
                continue;
            }
            break;
        }
    } catch (Throwable $ex) { $error=$ex->getMessage(); }
    return ['state'=>!$error && $status>=200 && $status<400?'up':'down','status'=>$status,'latency'=>(int)round((microtime(true)-$start)*1000),'error'=>$error];
}
function checkSite(int $id, bool $force=false): bool {
    if(browserEnabled())return false;
    $db=db(); $now=time();
    // Atomic lease prevents cron and manual requests from checking the same site concurrently.
    $q=$db->prepare('UPDATE sites SET lease_until=? WHERE id=? AND lease_until<=? AND manual_until<=? AND (CAST(? AS INTEGER)=1 OR next_check<=?)');
    $q->execute([$now+120,$id,$now,$now,$force?1:0,$now]);
    if (!$q->rowCount()) return false;
    $q=$db->prepare('SELECT * FROM sites WHERE id=?');$q->execute([$id]);$s=$q->fetch();if(!$s)return false;
    $r=probe($s['url']);$at=time();
    if(browserEnabled()) {
        $db->prepare('UPDATE sites SET lease_until=0,next_check=0 WHERE id=? AND lease_until=? AND browser_job IS NULL')->execute([$id,$now+120]);
        return false;
    }
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT manual_until FROM sites WHERE id=?');$q->execute([$id]);
        if((int)$q->fetchColumn()>time()){$db->commit();return false;}
        $db->prepare('INSERT INTO checks(site_id,checked_at,state,status,latency,error) VALUES(?,?,?,?,?,?)')->execute([$id,$at,$r['state'],$r['status'],$r['latency'],$r['error']]);
        $db->prepare('UPDATE sites SET checked_at=?,state=?,status=?,latency=?,error=?,next_check=?,lease_until=0,check_source=\'server\' WHERE id=?')->execute([$at,$r['state'],$r['status'],$r['latency'],$r['error'],$at+($r['state']==='down'?min(300,(int)$s['interval_seconds']):(int)$s['interval_seconds']),$id]);
        $db->prepare('DELETE FROM checks WHERE checked_at < ?')->execute([$at-90*86400]);$db->commit();
    } catch(Throwable $e){$db->rollBack();throw $e;}
    return true;
}

function dataDir(): string { return getenv('MONITOR_DATA_DIR') ?: ROOT.'/var'; }
function siteInput(string $input): array {
    $url=trim($input);
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i',$url)) $url='https://'.$url;
    validateUrl($url); $p=parse_url($url);
    $host=strtolower($p['host']);
    $name=preg_replace('/^www\./i','',$host);
    $port=$p['port']??null;
    if ($port!==null && !in_array($port,[80,443],true))throw new InvalidArgumentException('Yalnızca 80 ve 443 portları desteklenir.');
    // Preserve www in the request URL; some sites require it. Names omit www.
    $url=strtolower($p['scheme']).'://'.$host.($port?':'.$port:'').($p['path']??'/').(isset($p['query'])?'?'.$p['query']:'');
    return ['name'=>$name,'url'=>$url];
}
function importSites(string $text, int $minutes=1440): array {
    $lines=preg_split('/\R/u',trim($text));
    $lines=array_values(array_filter(array_map('trim',$lines),fn($v)=>$v!==''));
    if (!$lines || count($lines)>1000)throw new InvalidArgumentException('Bir seferde 1–1000 satır ekleyin.');
    if($minutes<1 || $minutes>1440)throw new InvalidArgumentException('Aralık 1–1440 dakika olmalı.');
    $result=['added'=>0,'duplicates'=>0,'errors'=>[]];$db=db();$db->beginTransaction();
    try {
        $q=$db->prepare('INSERT OR IGNORE INTO sites(name,url,interval_seconds) VALUES(?,?,?)');
        foreach($lines as $i=>$line) {
            try {$site=siteInput($line);}
            catch(InvalidArgumentException $e){$result['errors'][]='Satır '.($i+1).': '.$e->getMessage();continue;}
            $q->execute([$site['name'],$site['url'],$minutes*60]);
            if($q->rowCount())$result['added']++;else $result['duplicates']++;
        }
        $db->commit();
    } catch(Throwable $e){$db->rollBack();throw $e;}
    return $result;
}
function thumbnailFile(int $id): string {return dataDir().'/thumbnails/'.$id.'.img';}
function refreshThumbnail(int $id): bool {
    $db=db();$now=time();
    $q=$db->prepare('UPDATE sites SET thumbnail_lease=? WHERE id=? AND thumbnail_next<=? AND thumbnail_lease<=?');
    $q->execute([$now+90,$id,$now,$now]);if(!$q->rowCount())return false;
    $q=$db->prepare('SELECT url FROM sites WHERE id=?');$q->execute([$id]);$url=$q->fetchColumn();if(!$url)return false;
    $file=thumbnailFile($id);$tmp=null;
    try {
        // A remote renderer keeps ordinary cPanel hosting free of browser dependencies.
        $endpoint=getenv('MONITOR_SCREENSHOT_ENDPOINT') ?: 'https://image.thum.io/get/width/600/crop/400/noanimate/';
        validateUrl($endpoint);
        if(parse_url($endpoint,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Ekran görüntüsü servisi HTTPS kullanmalı.');
        publicAddresses(trim((string)parse_url($url,PHP_URL_HOST),'[]'));
        $home=parse_url($url);$target=$home['scheme'].'://'.$home['host'].(isset($home['port'])?':'.$home['port']:'').'/';
        $bytes='';$ch=curl_init($endpoint.$target);
        curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>40,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'WebKontrol/1.1',CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$bytes){if(strlen($bytes)+strlen($chunk)>5*1024*1024)return 0;$bytes.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
        if($ok===false || $status!==200)throw new RuntimeException($err?:'Ekran görüntüsü servisi HTTP '.$status.' döndürdü.');
        $info=@getimagesizefromstring($bytes);
        if(!$info || !in_array($info['mime'],['image/png','image/jpeg','image/gif','image/webp'],true))throw new RuntimeException('Servis geçerli bir resim döndürmedi.');
        $dir=dirname($file);if(!is_dir($dir) && !mkdir($dir,0700,true))throw new RuntimeException('Resim dizini oluşturulamadı.');
        $tmp=tempnam($dir,'thumb-');if($tmp===false || file_put_contents($tmp,$bytes)===false || !rename($tmp,$file))throw new RuntimeException('Ekran görüntüsü kaydedilemedi.');
        $db->prepare('UPDATE sites SET thumbnail_at=?,thumbnail_next=?,thumbnail_error=NULL,thumbnail_lease=0 WHERE id=?')->execute([time(),time()+86400,$id]);
    } catch(Throwable $e) {
        if($tmp && is_file($tmp))unlink($tmp);
        $db->prepare('UPDATE sites SET thumbnail_error=?,thumbnail_next=?,thumbnail_lease=0 WHERE id=?')->execute([$e->getMessage(),time()+3600,$id]);
    }
    return true;
}

function databaseError(PDOException $e): string {
    $code=(int)($e->errorInfo[1]??0);
    if($code===8 || $code===14)return 'Veritabanına yazılamadı. var/ klasörü ve monitor.sqlite dosyası için PHP kullanıcısının yazma iznini kontrol edin. Veritabanı varsa WAL/SHM dosyaları için de klasöre yazma izni gerekir.';
    if($code===5 || $code===6)return 'Veritabanı başka bir işlem tarafından kullanılıyor. Birkaç saniye sonra yeniden deneyin.';
    if($code===13)return 'Sunucuda veritabanı için yeterli disk alanı yok.';
    return 'Veritabanı işlemi başarısız (SQLite kodu: '.$code.'): '.$e->getMessage();
}

function queueChecks(bool $onlyDown=false): int {
    $q=db()->prepare('UPDATE sites SET next_check=0,manual_until=0'.($onlyDown?" WHERE state='down' AND manual_until<=?":''));
    $q->execute($onlyDown?[time()]:[]);return $q->rowCount();
}
function manuallyConfirmUp(int $id): void {
    $now=time();
    // Invalidate any in-flight browser job so it cannot overwrite this decision.
    $q=db()->prepare('UPDATE sites SET manual_at=?,manual_until=?,next_check=?,browser_last_job=COALESCE(browser_job,browser_last_job),browser_job=NULL,lease_until=0 WHERE id=?');
    $q->execute([$now,$now+86400,$now+86400,$id]);
    if(!$q->rowCount())throw new InvalidArgumentException('Site bulunamadı.');
}
function clearManualConfirmation(int $id): void {
    db()->prepare('UPDATE sites SET manual_until=0,next_check=0 WHERE id=?')->execute([$id]);
}
function effectiveSiteState(array $site, ?int $now=null): ?string {
    return (int)($site['manual_until']??0)>($now??time())?'up':($site['state']??null);
}

function browserEnabled(): bool {
    return is_file(dataDir().'/browser-enabled') && is_file(dataDir().'/browser-token');
}
