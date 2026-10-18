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
    return $db;
}
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function validateUrl(string $url): string {
    $p = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower($p['scheme'] ?? ''), ['http','https'], true) || isset($p['user']) || isset($p['pass'])) throw new InvalidArgumentException('Geçerli bir http/https adresi girin.');
    return $url;
}
// Resolve and pin each connection: prevent redirects/DNS rebinding into private networks.
function publicAddresses(string $host): array {
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
    else {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = array_values(array_filter(array_map(fn($r)=>$r['ip'] ?? $r['ipv6'] ?? null, $records ?: [])));
    }
    if (!$ips) throw new RuntimeException('DNS adresi çözümlenemedi.');
    foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || str_starts_with(strtolower($ip),'::ffff:') || str_starts_with(strtolower($ip),'ff') || (filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) && (int)explode('.',$ip)[0]>=224)) throw new RuntimeException('Yalnızca genel internete açık adresler izlenebilir.');
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
            $ips=publicAddresses($host); $ip=$ips[0]; $location=null; $bytes=0; $truncated=false;
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'WebKontrol/1.0',CURLOPT_RESOLVE=>[$host.':'.$port.':'.(str_contains($ip,':')?'['.$ip.']':$ip)],CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$location){if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);},CURLOPT_WRITEFUNCTION=>function($ch,$data)use(&$bytes,&$truncated){$bytes+=strlen($data); if($bytes>1048576){$truncated=true;return 0;}return strlen($data);}]);
            $ok=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch);
            if ($ok===false && !$truncated) throw new RuntimeException($err);
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
    $db=db(); $now=time();
    // Atomic lease prevents cron and manual requests from checking the same site concurrently.
    $q=$db->prepare('UPDATE sites SET lease_until=? WHERE id=? AND lease_until<=? AND (CAST(? AS INTEGER)=1 OR next_check<=?)');
    $q->execute([$now+120,$id,$now,$force?1:0,$now]);
    if (!$q->rowCount()) return false;
    $q=$db->prepare('SELECT * FROM sites WHERE id=?');$q->execute([$id]);$s=$q->fetch();if(!$s)return false;
    $r=probe($s['url']);$at=time();
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO checks(site_id,checked_at,state,status,latency,error) VALUES(?,?,?,?,?,?)')->execute([$id,$at,$r['state'],$r['status'],$r['latency'],$r['error']]);
        $db->prepare('UPDATE sites SET checked_at=?,state=?,status=?,latency=?,error=?,next_check=?,lease_until=0 WHERE id=?')->execute([$at,$r['state'],$r['status'],$r['latency'],$r['error'],$at+(int)$s['interval_seconds'],$id]);
        $db->prepare('DELETE FROM checks WHERE checked_at < ?')->execute([$at-90*86400]);$db->commit();
    } catch(Throwable $e){$db->rollBack();throw $e;}
    return true;
}
