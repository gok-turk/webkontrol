<?php
declare(strict_types=1);
namespace NetworkFixture;
// Deterministic DNS and transport faults; no external network or weakened TLS required.
$mode='mixed';$attempts=[];
function dns_get_record($host,$type) {
    global $mode;
    if($mode==='fallback' || $mode==='private')return false;
    if($mode==='mixed')return $type===DNS_A?[['ip'=>'8.8.8.8']]:false;
    return $type===DNS_A?[['ip'=>'8.8.8.8'],['ip'=>'1.1.1.1']]:[['ipv6'=>'2606:4700:4700::1111']];
}
function gethostbynamel($host) {global $mode;return $mode==='private'?['127.0.0.1']:['1.1.1.1'];}
function curl_init($url) {return (object)['options'=>[],'status'=>0];}
function curl_setopt_array($ch,$options) {$ch->options=$options;return true;}
function curl_exec($ch) {
    global $attempts,$mode;
    $pin=$ch->options[CURLOPT_RESOLVE][0];$attempts[]=$pin;
    if($mode==='http-error'){$ch->status=503;return true;}
    if(str_ends_with($pin,':8.8.8.8'))return false;
    $ch->status=200;return true;
}
function curl_getinfo($ch,$key) {return $ch->status;}
function curl_error($ch) {return 'Simüle edilen bağlantı hatası';}
function curl_close($ch) {}
$source=file_get_contents(__DIR__.'/../src/app.php');
$source=str_replace('declare(strict_types=1);','declare(strict_types=1); namespace NetworkFixture; use \PDO; use \PDOException; use \RuntimeException; use \InvalidArgumentException; use \Throwable;',substr($source,5));
eval($source);
function check(bool $ok,string $message):void {if(!$ok)throw new \RuntimeException($message);echo 'PASS '.$message.PHP_EOL;}
check(publicAddresses('test.example')===['8.8.8.8'],'AAAA hatasında geçerli A kaydı kullanılır');
$mode='fallback';check(publicAddresses('test.example')===['1.1.1.1'],'Sistem çözümleyicisi yedek olarak kullanılır');
$mode='private';try{publicAddresses('test.example');throw new \LogicException('Özel IP kabul edildi');}catch(\RuntimeException $e){check(str_contains($e->getMessage(),'genel internete'),'Yedek çözümleyicide özel IP engellenir');}
$mode='multi';$r=probe('https://test.example');check($r['state']==='up' && count($attempts)===2,'İlk IP başarısız olunca ikinci IPv4 adresi denenir');
$attempts=[];$mode='http-error';$r=probe('https://test.example');check($r['state']==='down' && $r['status']===503 && count($attempts)===1,'HTTP 503 başka IP denenerek gizlenmez');
