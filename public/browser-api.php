<?php
declare(strict_types=1);
require __DIR__.'/../src/browser.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function respond(int $status,array $data): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(!browserEnabled())respond(503,['error'=>'Tarayıcı modu etkin değil.']);
$expected=trim((string)file_get_contents(dataDir().'/browser-token'));
$provided=$_SERVER['HTTP_X_WEBKONTROL_TOKEN']??'';
if(!preg_match('/^[a-f0-9]{64}$/D',$expected) || !is_string($provided) || !hash_equals($expected,$provided))respond(401,['error'=>'Yetkisiz istek.']);
if($_SERVER['REQUEST_METHOD']!=='POST')respond(405,['error'=>'POST gerekli.']);
try {
    if(($_GET['action']??'')==='claim')respond(200,['jobs'=>claimBrowserJobs()]);
    if(($_GET['action']??'')!=='result')respond(404,['error'=>'Bilinmeyen işlem.']);
    $body=file_get_contents('php://input',false,null,0,3000001);
    if($body===false || strlen($body)>3000000)respond(413,['error'=>'İstek çok büyük.']);
    $data=json_decode($body,true,16,JSON_THROW_ON_ERROR);if(!is_array($data))throw new InvalidArgumentException('JSON nesnesi gerekli.');
    acceptBrowserResult($data);respond(200,['ok'=>true]);
}catch(DomainException $e){respond(409,['error'=>$e->getMessage()]);}
catch(InvalidArgumentException|JsonException $e){respond(400,['error'=>$e->getMessage()]);}
catch(Throwable $e){error_log('WebKontrol tarayıcı API: '.$e->getMessage());respond(500,['error'=>'Sonuç kaydedilemedi. Sunucu hata kaydını kontrol edin.']);}
