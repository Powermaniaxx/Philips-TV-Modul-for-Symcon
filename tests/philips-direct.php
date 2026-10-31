<?php
declare(strict_types=1);
// SDK and TV fixtures: never contact a real device.
const VM_UPDATE = 10603;
$profiles = [];
function IPS_VariableProfileExists(string $name): bool { return isset($GLOBALS['profiles'][$name]); }
function IPS_CreateVariableProfile(string $name, int $type): void { $GLOBALS['profiles'][$name] = ['ProfileType' => $type, 'Associations' => []]; }
function IPS_GetVariableProfile(string $name): array { return $GLOBALS['profiles'][$name]; }
function IPS_SetVariableProfileAssociation(string $name, mixed $value, string $caption, string $icon, int $color): bool {
    $items = &$GLOBALS['profiles'][$name]['Associations'];
    $items = array_values(array_filter($items, fn(array $item): bool => $item['Value'] !== $value));
    if ($caption !== '') { $items[] = ['Value' => $value, 'Name' => $caption]; }
    return true;
}
function IPS_SetVariableCustomProfile(int $id, string $profile): bool { return true; }

$locks = [];
$signalValues = [99 => false];
$signalUpdated = null;
function GetValue(int $id): mixed { return $GLOBALS['signalValues'][$id] ?? null; }
function IPS_GetVariable(int $id): array { return ['VariableUpdated'=>$GLOBALS['signalUpdated'] ?? time()]; }
function IPS_SemaphoreEnter(string $key, int $timeout): bool { if ($GLOBALS['locks'][$key] ?? false) { return false; } $GLOBALS['locks'][$key] = true; return true; }
function IPS_SemaphoreLeave(string $key): void { $GLOBALS['locks'][$key] = false; }
function IPS_VariableExists(int $id): bool { return in_array($id,[99,16350,10950],true); }
class IPSModuleStrict
{
    public int $InstanceID = 456;
    protected function GetIDForIdent(string $ident): int { return 1000 + array_search($ident, array_keys($this->values), true); }
    public array $props = [], $attrs = [], $values = [], $actions = [], $timers = [], $subscriptions = [], $formFields = [];
    public int $status = 0;
    public function Create(): void {}
    public function ApplyChanges(): void {}
    protected function RegisterPropertyBoolean(string $n, bool $v): void { $this->props[$n] = $v; }
    protected function RegisterPropertyInteger(string $n, int $v): void { $this->props[$n] = $v; }
    protected function RegisterPropertyString(string $n, string $v): void { $this->props[$n] = $v; }
    protected function RegisterAttributeString(string $n, string $v): void { $this->attrs[$n] = $v; }
    protected function RegisterAttributeBoolean(string $n, bool $v): void { $this->attrs[$n] = $v; }
    protected function ReadAttributeBoolean(string $n): bool { return (bool)$this->attrs[$n]; }
    protected function WriteAttributeBoolean(string $n, bool $v): void { $this->attrs[$n] = $v; }
    protected function RegisterAttributeInteger(string $n, int $v): void { $this->attrs[$n] = $v; }
    protected function RegisterVariableBoolean(string $n, mixed ...$args): bool { $this->values[$n] = false; return true; }
    protected function RegisterVariableInteger(string $n, mixed ...$args): bool { $this->values[$n] = 0; return true; }
    protected function RegisterVariableString(string $n, mixed ...$args): bool { $this->values[$n] = ''; return true; }
    protected function RegisterTimer(string $n, int $v, string $script): void { $this->timers[$n] = $v; }
    protected function SetTimerInterval(string $n, int $v): void { $this->timers[$n] = $v; }
    protected function ReadPropertyBoolean(string $n): bool { return $this->props[$n]; }
    protected function ReadPropertyInteger(string $n): int { return $this->props[$n]; }
    protected function ReadPropertyString(string $n): string { return $this->props[$n]; }
    protected function ReadAttributeString(string $n): string { return $this->attrs[$n]; }
    protected function ReadAttributeInteger(string $n): int { return $this->attrs[$n]; }
    protected function WriteAttributeString(string $n, string $v): void { $this->attrs[$n] = $v; }
    protected function WriteAttributeInteger(string $n, int $v): void { $this->attrs[$n] = $v; }
    protected function SetValue(string $n, mixed $v): void { $this->values[$n] = $v; }
    protected function GetValue(string $n): mixed { return $this->values[$n]; }
    protected function SetStatus(int $v): void { $this->status = $v; }
    protected function EnableAction(string $n): void { $this->actions[$n] = true; }
    protected function DisableAction(string $n): void { $this->actions[$n] = false; }
    protected function RegisterMessage(int $id, int $msg): void { $this->subscriptions[$id] = $msg; }
    protected function UnregisterMessage(int $id, int $msg): void { unset($this->subscriptions[$id]); }
    protected function SendDebug(mixed ...$args): void {}
    protected function UpdateFormField(string $name, string $field, mixed $value): void { $this->formFields[$name][$field] = $value; }
}
require __DIR__ . '/../PhilipsTV/module.php';
class TVFixture extends PhilipsTV
{
    public array $calls = [], $fail = [], $responses = [];
    public mixed $onRead = null;
    public function __construct()
    {
        $this->Create(); $this->props['Enabled'] = true; $this->props['Host'] = 'tv.local';
        $this->props['Username'] = 'fixture-user'; $this->props['Password'] = 'fixture-password';
        $this->responses = ['powerstate' => ['powerstate' => 'On'],
            'audio/volume' => ['min' => 10, 'max' => 70, 'current' => 40, 'muted' => true],
            'activities/current' => ['component' => ['packageName' => 'org.example.player']],
            'ambilight/power' => ['power' => 'On'], 'ambilight/currentconfiguration' => ['styleName' => 'FOLLOW_VIDEO']];
        $this->ApplyChanges();
    }
    protected function Api(string $method, string $endpoint, ?array $body = null, ?array $credentials = null): array
    {
        $this->calls[] = [$method, $endpoint, $body];
        if ($this->onRead && $method === 'GET') { ($this->onRead)($endpoint); }
        if (isset($this->fail[$endpoint])) { throw $this->fail[$endpoint]; }
        if ($method === 'POST') { return []; }
        return $this->responses[$endpoint];
    }
    public function state(): array { return json_decode($this->GetState(), true, 32, JSON_THROW_ON_ERROR); }
}
function check(bool $v, string $m): void { if (!$v) { throw new RuntimeException($m); } }
function rejects(callable $fn): void { try { $fn(); } catch (Throwable $e) { return; } throw new RuntimeException('Expected rejection'); }
$m = new TVFixture();
check($m->status === 102 && $m->timers['Poll'] === 1000, 'Valid configuration starts one short timer');
check($m->state()['power'] === 'unknown' && !$m->actions['Volume'], 'No fabricated initial status');
$m->UpdateStatus(); $s = $m->state();
check($s['online'] && $s['power'] === 'awake' && $s['volume'] === 50 && $s['mute'] === true, 'Observed power/audio and non-zero volume minimum');
check($s['activity'] === 'org.example.player' && $s['ambilight'] && $s['ambilightMode'] === 'FOLLOW_VIDEO', 'Application/Ambilight state');
check($m->actions['Volume'] && $m->actions['Ambilight'] && $m->timers['Poll'] === 15000, 'Only available controls enabled');
$before = count($m->calls); $m->RequestAction('Volume', 75);
check(count($m->calls) === $before + 1 && end($m->calls) === ['POST', 'audio/volume', ['current' => 55, 'muted' => true]], 'Volume sends directly, respects bounds and preserves mute');
check($m->values['Volume'] === 50, 'POST acknowledgement does not pretend the target was observed');
check($m->timers['Poll'] === 700, 'Status verification deferred until after acknowledgement');
$m->RequestAction('Mute', false); check(end($m->calls)[2] === ['current'=>55,'muted'=>false], 'Rapid mute preserves last accepted volume command');
$m->RequestAction('Ambilight', false); check(end($m->calls) === ['POST', 'ambilight/power', ['power' => 'Off']], 'Explicit Ambilight command');
$m->RequestAction('RemoteKey', 'CursorUp'); check(end($m->calls)[2] === ['key' => 'CursorUp'], 'Remote key allowlist');
foreach ([['Power',1],['Volume',101],['Volume',-1],['Volume','50'],['Mute','false'],['Ambilight',0],['RemoteKey','Standby'],['RemoteKey','reboot'],['Bad',true]] as [$ident,$value]) { rejects(fn() => $m->RequestAction($ident,$value)); }
$m->calls = []; $m->responses['powerstate'] = ['powerstate' => 'Standby']; $m->UpdateStatus();
check(count($m->calls) === 1 && $m->state()['paused'] && !$m->state()['online'], 'Standby skips optional endpoints and marks last-known state');
check($m->timers['Poll'] === 0 && !$m->actions['Volume'] && !$m->actions['Ambilight'], 'Standby pause and no stale controls');
$count=count($m->calls);$m->RequestAction('Refresh',true);check($m->timers['Poll']===100&&count($m->calls)===$count,'Manual tile refresh schedules work without waiting for the TV');
$m->RequestAction('Power',true); check(end($m->calls) === ['POST','powerstate',['powerstate'=>'On']] && $m->timers['Poll'] === 700, 'Wake bypasses paused reads, never toggles');
$m->props['StandbyInterval'] = 120; $m->UpdateStatus(); check($m->timers['Poll'] === 120000, 'Optional explicit standby polling');
$m->props['WakeSignal'] = 99; $m->props['StandbyInterval'] = 0; $m->ApplyChanges();
$m->MessageSink(time(),99,VM_UPDATE,['standby',true]); check($m->timers['Poll']===1000,'Standby signal does not wake');
$m->MessageSink(time(),99,VM_UPDATE,['awake',true]); check($m->timers['Poll']===100,'Shield awake resumes polling');
$m->fail['powerstate'] = new RuntimeException('Authentication fixture',202); $m->UpdateStatus();
check($m->status===202 && !$m->state()['online'] && $m->state()['power']==='unknown' && $m->timers['Poll']===15000,'Auth failure invalidates standby and retries');
unset($m->fail['powerstate']); $m->responses['powerstate']=['powerstate'=>'On']; $m->fail['ambilight/power']=new RuntimeException('Unsupported',404); $m->UpdateStatus();
check($m->state()['online'] && $m->actions['Volume'] && !$m->actions['Ambilight'],'Unsupported optional endpoint does not break basic controls');
$m->calls=[]; $m->UpdateStatus(); check(!in_array('ambilight/power',array_column($m->calls,1),true),'Unsupported endpoint is not hammered');
$m->ApplyChanges(); unset($m->fail['ambilight/power']); $m->UpdateStatus(); check($m->actions['Ambilight'],'Apply retries previously unsupported features');
$m->responses['audio/volume']['current']=1000; $m->UpdateStatus(); check(!$m->actions['Volume'] && $m->state()['volume']===null,'Malformed audio hides control');
$m->responses['audio/volume']['current']=40; $m->UpdateStatus();
$snapshot=json_decode($m->attrs['Snapshot'],true);$snapshot['updated']=time()-500;$m->attrs['Snapshot']=json_encode($snapshot);
check($m->state()['stale'] && $m->state()['power']==='unknown' && $m->state()['caps']===[],'Old online status expires without a timer');
rejects(fn()=>$m->RequestAction('Volume',20));
$m->UpdateStatus(); $m->calls=[];
$m->onRead=function($endpoint)use($m){ if($endpoint==='activities/current'){$m->onRead=null;$m->RequestAction('Power',false);} };
$m->UpdateStatus(); check($m->timers['Poll']===700 && str_contains($m->values['LastError'],'gesendet'),'In-flight poll cannot overwrite command or confirmation timer');
$m->onRead=null; $m->fail['powerstate']=new RuntimeException('Connection timeout',201); $m->calls=[];
rejects(fn()=>$m->RequestAction('Power',false)); check(count($m->calls)===1 && !$GLOBALS['locks']['SVPTVCommand456'],'No replay on timeout and command lock released');
$m->props['Enabled']=false; $m->ApplyChanges(); $before=count($m->calls); rejects(fn()=>$m->RequestAction('Power',true)); check(count($m->calls)===$before && $m->timers['Poll']===0 && !$m->state()['enabled'],'Disabled connection sends nothing');
$m->props['Enabled']=true; $m->props['Host']='https://user:pass@tv.local/path'; $m->ApplyChanges();check($m->status===200,'Reject host strings containing URLs/credentials');
$m=new TVFixture();$m->UpdateStatus();
$m->fail['powerstate']=new RuntimeException('TLS-Verbindungsaufbau fehlgeschlagen. [POST /6/powerstate · HTTP 0 · cURL 35]',201);
rejects(fn()=>$m->RequestAction('Power',false));
$command=$m->values['LastCommand'];
check(str_contains($command,'Power · POST /6/powerstate · Fehlgeschlagen: TLS-'),'Command failure is recorded independently');
unset($m->fail['powerstate']);$m->UpdateStatus();
check($m->values['LastError']===''&&$m->values['LastCommand']===$command,'Successful status poll cannot erase last command failure');
$form=json_decode($m->GetConfigurationForm(),true);$labels=array_column($form['actions'],'caption','name');
check(str_contains($labels['CommandDiagnostic'],'cURL 35'),'Command error visible when configuration is reopened');
check(str_contains($m->formFields['CommandDiagnostic']['caption'],'cURL 35'),'Live form receives diagnostic updates');
check($m->state()['lastCommand']===$command,'Saved command result included in read-only status');
$m->RequestAction('RemoteKey','Home');
check(str_contains($m->values['LastCommand'],'RemoteKey · POST /6/input/key · TV hat den Empfang bestätigt; Ausführung noch nicht bestätigt.'),'HTTP acceptance is distinguished from observed execution');
$m=new TVFixture();$m->props['WakeSignal']=99;$m->ApplyChanges();$before=count($m->calls);
foreach([true,1,'ON',' awake '] as $active){
    $m->timers['Poll']=0;$m->MessageSink(time(),99,VM_UPDATE,[$active,true]);
    check($m->timers['Poll']===100,'Generic AVR/Shield/own signal resumes status reads');
}
foreach([false,0,2,-1,'off','standby','unknown',null] as $inactive){
    $m->timers['Poll']=0;$m->MessageSink(time(),99,VM_UPDATE,[$inactive,true]);
    check($m->timers['Poll']===0,'Inactive or unknown signal never resumes polling');
}
$m->MessageSink(time(),99,VM_UPDATE,[true,false]);check($m->timers['Poll']===0,'Unchanged signal is ignored');
$m->MessageSink(time(),98,VM_UPDATE,[true,true]);check($m->timers['Poll']===0,'Only selected source can resume polling');
$m->props['WakeSignal']=0;$m->MessageSink(time(),0,VM_UPDATE,[true,true]);check($m->timers['Poll']===0,'Unconfigured optional signal does nothing');
$m->props['WakeSignal']=99;$m->props['Enabled']=false;$m->MessageSink(time(),99,VM_UPDATE,[true,true]);check($m->timers['Poll']===0,'Signal cannot activate disabled module');
check(count($m->calls)===$before,'Optional activation signal sends no TV power command or immediate network request');
echo "Philips direct: status, commands, bounds, auth/errors, capabilities, standby/wake, staleness and in-flight command priority passed.\n";
