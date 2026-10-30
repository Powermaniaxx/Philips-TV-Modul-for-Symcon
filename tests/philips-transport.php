<?php
declare(strict_types=1);
// Run WITHOUT curl extension: inspect the real transport through a curl fixture.
foreach (['CURLOPT_RETURNTRANSFER','CURLOPT_FOLLOWLOCATION','CURLOPT_HTTPAUTH','CURLAUTH_DIGEST','CURLOPT_USERNAME','CURLOPT_PASSWORD',
    'CURLOPT_CONNECTTIMEOUT_MS','CURLOPT_TIMEOUT_MS','CURLOPT_SSL_VERIFYPEER','CURLOPT_SSL_VERIFYHOST','CURLOPT_HTTPHEADER','CURLOPT_CUSTOMREQUEST',
    'CURLOPT_POSTFIELDS','CURLINFO_HTTP_CODE'] as $i=>$name) { define($name, $i+1); }
$options=[];$response='{}';$httpStatus=200;$url='';$errno=0;
function curl_init(string $url): object { $GLOBALS['url']=$url; return (object)[]; }
function curl_setopt_array(object $h,array $options): void { $GLOBALS['options']=$options; }
function curl_setopt(object $h,int $option,mixed $value): void { $GLOBALS['options'][$option]=$value; }
function curl_exec(object $h): string|false { return $GLOBALS['response']; }
function curl_getinfo(object $h,int $option): int { return $GLOBALS['httpStatus']; }
function curl_close(object $h): void {}
function curl_errno(object $h): int { return $GLOBALS['errno']; }
class IPSModuleStrict
{
    public array $props=['Host'=>'tv.local','Port'=>1926,'Username'=>'fixture-user','Password'=>'fixture-secret','Timeout'=>2000,'VerifyTLS'=>false];
    public array $values=[], $debug=[];
    protected function ReadPropertyString(string $n): string { return $this->props[$n]; }
    protected function ReadPropertyInteger(string $n): int { return $this->props[$n]; }
    protected function ReadPropertyBoolean(string $n): bool { return $this->props[$n]; }
    protected function SetValue(string $n,mixed $value): void { $this->values[$n]=$value; }
    protected function SendDebug(string $name,string $data,int $format): void { $this->debug[]=[$name,$data]; }
}
require __DIR__.'/../PhilipsTV/module.php';
class TransportFixture extends PhilipsTV { public function request(string $method,string $endpoint,?array $body=null,?array $credentials=null): array { return $this->Api($method,$endpoint,$body,$credentials); } }
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
$response=false;$httpStatus=0;
foreach([6=>'Hostname',7=>'TV-Port',28=>'Zeitüberschreitung',35=>'TLS-Verbindungsaufbau',60=>'TLS-Zertifikat',52=>'ohne HTTP-Antwort',56=>'abgebrochen',99=>'HTTPS-Anfrage'] as $number=>$expected){
    $errno=$number;
    try{$m->request('POST','powerstate',['powerstate'=>'On']);throw new LogicException('Expected failure');}
    catch(RuntimeException $e){
        check(str_contains($e->getMessage(),$expected),'Distinguish transport failure '.$number);
        check(str_contains($e->getMessage(),'POST /6/powerstate · HTTP 0 · cURL '.$number),'Method, endpoint, HTTP and cURL error retained');
        check(!str_contains($e->getMessage(),'fixture-secret')&&!str_contains($e->getMessage(),'fixture-user'),'No credentials in transport error');
    }
}
check(str_contains($m->values['TransportInfo'],'cURL 99'),'Last transport details saved');
check(!str_contains(json_encode($m->debug),'fixture-secret')&&!str_contains(json_encode($m->debug),'fixture-user'),'Debug contains metadata only');
$response='<html>fixture-secret</html>';$httpStatus=200;$errno=0;
try{$m->request('GET','powerstate');throw new LogicException('Expected malformed body failure');}
catch(RuntimeException $e){check(str_contains($e->getMessage(),'HTML statt JSON')&&!str_contains($e->getMessage(),'fixture-secret'),'Classify invalid response without leaking body');}
$response='{"error_id":"SUCCESS"}';$m->request('POST','pair/request',['device'=>['id'=>'new-device']],[]);
check(!isset($options[CURLOPT_HTTPAUTH],$options[CURLOPT_USERNAME],$options[CURLOPT_PASSWORD]),'Pair request sends no existing credentials');
$m->request('POST','pair/grant',['auth'=>['pin'=>'0123']],['username'=>'new-device','password'=>'new-temporary-key']);
check($options[CURLOPT_USERNAME]==='new-device'&&$options[CURLOPT_PASSWORD]==='new-temporary-key','Grant uses new temporary credentials only');
check(!str_contains(json_encode($m->debug),'new-temporary-key')&&!str_contains(json_encode($m->debug),'0123'),'Pairing debug excludes secret and PIN');
echo "Philips transport: HTTPS/Digest, JSON, TLS, limits, no redirects, auth errors and redacted diagnostics passed.\n";
