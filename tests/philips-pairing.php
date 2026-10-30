<?php
declare(strict_types=1);
// Reuse isolated SDK fixture, then exercise pairing without a real TV.
require __DIR__ . '/philips-direct.php';
$instances = [];
function IPS_SetProperty(int $id, string $name, mixed $value): void { $GLOBALS['instances'][$id]->props[$name] = $value; }
function IPS_ApplyChanges(int $id): void { $GLOBALS['instances'][$id]->ApplyChanges(); }
class PairFixture extends TVFixture
{
    public array $pairCalls = [];
    public bool $denyGrant = false, $denyStart = false;
    protected function Api(string $method, string $endpoint, ?array $body = null, ?array $credentials = null): array
    {
        if (!str_starts_with($endpoint, 'pair/')) { return parent::Api($method, $endpoint, $body, $credentials); }
        $this->pairCalls[] = [$method, $endpoint, $body, $credentials];
        if ($endpoint === 'pair/request') {
            if ($this->denyStart) { throw new RuntimeException('TLS-Verbindungsaufbau fehlgeschlagen.', 201); }
            check($credentials === [], 'Initial pairing must be unauthenticated');
            check($body['access']['scope'] === ['read','write','control'], 'Request control permission');
            return ['error_id'=>'SUCCESS','timestamp'=>123456,'auth_key'=>'fixture-generated-key'];
        }
        if ($this->denyGrant) { return ['error_id'=>'DENIED']; }
        $s = json_decode($this->attrs['PairingSession'], true);
        check($credentials === ['username'=>$s['device']['id'],'password'=>'fixture-generated-key'], 'Grant uses new temporary credentials');
        $key=base64_decode('ZmVay1EQVFOaZhwQ4Kv81ypLAZNczV9sG4KkseXWn1NEk6cXmPKO/MCa9sryslvLCFMnNe4Z4CPXzToowvhHvA==');
        check($body['auth']['auth_signature']===base64_encode(hash_hmac('sha1','1234560123',$key,true)), 'Timestamp + PIN HMAC signature, including leading zero');
        check($body['auth']['pin']==='0123'&&$body['device']===$s['device'], 'Exact PIN and original device reused');
        return ['error_id'=>'SUCCESS'];
    }
}
$m=new PairFixture();$instances[$m->InstanceID]=$m;
$m->props['Enabled']=false;$m->props['Username']='';$m->props['Password']='';$m->ApplyChanges();
$m->StartPairing();$session=json_decode($m->attrs['PairingSession'],true);
check(strlen($session['device']['id'])===32&&$m->timers['Poll']===0&&$m->timers['PairingTimeout']===120000,'Fresh pairing without old credentials pauses polls and expires');
check($m->props['Username']===''&&$m->props['Password']==='','No credentials stored before grant');
$n=count($m->calls);$m->UpdateStatus();check(count($m->calls)===$n,'No status traffic while waiting for PIN');
rejects(fn()=>$m->CompletePairing('abcd'));check(count($m->pairCalls)===1,'Invalid PIN sends nothing');
$m->CompletePairing('0123');
check($m->props['Enabled']&&$m->props['Username']===$session['device']['id']&&$m->props['Password']==='fixture-generated-key','Confirmed grant stores generated credentials and enables connection');
check($m->attrs['PairingSession']===''&&$m->timers['PairingTimeout']===0&&$m->timers['Poll']===1000,'Temporary secrets cleared and polling resumed');
check($m->formFields['Password']['value']==='fixture-generated-key'&&$m->formFields['Enabled']['value']===true,'Masked form fields synchronized after pairing');
check(!str_contains($m->GetState(),'fixture-generated-key')&&!str_contains($m->values['PairingStatus'],'fixture-generated-key'),'Generated secret not in status payload or pairing message');
rejects(fn()=>$m->CompletePairing('0123'));
$old=[$m->props['Username'],$m->props['Password']];$m->StartPairing();$m->denyGrant=true;rejects(fn()=>$m->CompletePairing('0123'));
check([$m->props['Username'],$m->props['Password']]===$old&&$m->attrs['PairingSession']==='','Failed grant preserves existing working credentials');
$m->denyGrant=false;$m->StartPairing();$s=json_decode($m->attrs['PairingSession'],true);$s['expires']=time()-1;$m->attrs['PairingSession']=json_encode($s);$n=count($m->pairCalls);rejects(fn()=>$m->CompletePairing('0123'));check(count($m->pairCalls)===$n,'Expired PIN sends no grant');
$m->StartPairing();$m->props['Host']='another-tv.local';$m->ApplyChanges();check($m->attrs['PairingSession']==='','Changed target clears temporary secret');
$m->StartPairing();$s=json_decode($m->attrs['PairingSession'],true);$s['expires']=time()-1;$m->attrs['PairingSession']=json_encode($s);$m->ExpirePairing();check($m->attrs['PairingSession']===''&&$m->timers['PairingTimeout']===0,'Expiry timer cleans temporary credentials');
$m->denyStart=true;rejects(fn()=>$m->StartPairing());check($m->attrs['PairingSession']===''&&$m->timers['Poll']===1000,'Pairing transport failure resumes existing connection');
echo "Philips pairing: onboarding, permissions, grant signature, leading-zero PIN, form sync, expiry, target changes and credential preservation passed.\n";
