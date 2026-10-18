<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/app.php';
$url=$argv[1]??'';
try {validateUrl($url);}catch(Throwable $e){fwrite(STDERR,"Kullanım: php bin/diagnose.php https://ornek.com\n");exit(1);}
echo 'PHP: '.PHP_VERSION.PHP_EOL;
echo 'cURL: '.(curl_version()['version']??'?').PHP_EOL;
echo 'TLS: '.(curl_version()['ssl_version']??'?').PHP_EOL;
echo 'Adres: '.$url.PHP_EOL;
$host=trim((string)parse_url($url,PHP_URL_HOST),'[]');
foreach(['Yerel/yedek DNS'=>false,'Bağımsız HTTPS DNS'=>true] as $label=>$external) {
    try {echo $label.': '.implode(', ',publicAddresses($host,$external)).PHP_EOL;}
    catch(Throwable $e){echo $label.': '.$e->getMessage().PHP_EOL;}
}
$result=probe($url);
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($result['state']==='up'?0:1);
