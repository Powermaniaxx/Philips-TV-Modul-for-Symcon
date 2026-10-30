<?php
declare(strict_types=1);
// Run WITHOUT curl extension: inspect the real transport through a curl fixture.
foreach (['CURLOPT_RETURNTRANSFER','CURLOPT_FOLLOWLOCATION','CURLOPT_HTTPAUTH','CURLAUTH_DIGEST','CURLOPT_USERNAME','CURLOPT_PASSWORD',
    'CURLOPT_CONNECTTIMEOUT_MS','CURLOPT_TIMEOUT_MS','CURLOPT_SSL_VERIFYPEER','CURLOPT_SSL_VERIFYHOST','CURLOPT_HTTPHEADER','CURLOPT_CUSTOMREQUEST',
    'CURLOPT_POSTFIELDS','CURLINFO_HTTP_CODE'] as $i=>$name) { define($name, $i+1); }
$options=[];$response='{}';$httpStatus=200;$url='';
function curl_init(string $url): object { $GLOBALS['url']=$url; return (object)[]; }
function curl_setopt_array(object $h,array $options): void { $GLOBALS['options']=$options; }
function curl_setopt(object $h,int $option,mixed $value): void { $GLOBALS['options'][$option]=$value; }
function curl_exec(object $h): string|false { return $GLOBALS['response']; }
function curl_getinfo(object $h,int $option): int { return $GLOBALS['httpStatus']; }
function curl_close(object $h): void {}
class IPSModuleStrict
{
    public array $props=['Host'=>'tv.local','Port'=>1926,'Username'=>'fixture-user','Password'=>'fixture-secret','Timeout'=>2000,'VerifyTLS'=>false];
    protected function ReadPropertyString(string $n): string { return $this->props[$n]; }
    protected function ReadPropertyInteger(string $n): int { return $this->props[$n]; }
    protected function ReadPropertyBoolean(string $n): bool { return $this->props[$n]; }
}
require __DIR__.'/../PhilipsTV/module.php';
class TransportFixture extends PhilipsTV { public function request(string $method,string $endpoint,?array $body=null): array { return $this->Api($method,$endpoint,$body); } }
function check(bool $v,string $m): void { if(!$v)throw new RuntimeException($m); }
function failure(callable $fn,int $code): void { try{$fn();}catch(RuntimeException $e){check($e->getCode()===$code,'Error classification');check(!str_contains($e->getMessage(),'fixture-secret'),'No secret in errors');return;}throw new RuntimeException('Expected failure'); }
$m=new TransportFixture();$response='{"powerstate":"On"}';
check($m->request('GET','powerstate')===['powerstate'=>'On'],'Parse real JSON body');
check($url==='https://tv.local:1926/6/powerstate','HTTPS API 6 URL');
check($options[CURLOPT_HTTPAUTH]===CURLAUTH_DIGEST && $options[CURLOPT_USERNAME]==='fixture-user' && $options[CURLOPT_PASSWORD]==='fixture-secret','Digest via separate credential options');
check($options[CURLOPT_FOLLOWLOCATION]===false&&$options[CURLOPT_TIMEOUT_MS]===2000,'Bounded request with no redirects');
$response='';check($m->request('POST','powerstate',['powerstate'=>'On'])===[],'Accept empty successful POST');
check(json_decode($options[CURLOPT_POSTFIELDS],true)===['powerstate'=>'On'],'POST JSON');
$response='<html><head><title>Ok</title></head><body>Ok</body></html>';check($m->request('POST','input/key',['key'=>'Home'])===[],'Firmware HTML acknowledgement');
failure(fn()=>$m->request('GET','powerstate'),201);
$response='{"error":"fixture-secret"}';failure(fn()=>$m->request('POST','input/key',['key'=>'Home']),201);
$response='{"error_id":"FAIL","error_text":"fixture-secret"}';failure(fn()=>$m->request('POST','input/key',['key'=>'Home']),201);
foreach([401,403] as $status){$httpStatus=$status;failure(fn()=>$m->request('GET','powerstate'),202);}
$httpStatus=404;failure(fn()=>$m->request('GET','ambilight/power'),404);
$httpStatus=302;failure(fn()=>$m->request('GET','powerstate'),302);
$httpStatus=0;$response=false;failure(fn()=>$m->request('POST','powerstate',['powerstate'=>'On']),201);
$httpStatus=200;$response='{}';$m->props['VerifyTLS']=true;$m->props['Host']='2001:db8::1';$m->request('GET','powerstate');
check($url==='https://[2001:db8::1]:1926/6/powerstate'&&$options[CURLOPT_SSL_VERIFYHOST]===2&&$options[CURLOPT_SSL_VERIFYPEER]===true,'IPv6 and optional TLS verification');
echo "Philips transport: HTTPS/Digest, JSON, TLS, limits, no redirects, auth errors and redacted diagnostics passed.\n";
