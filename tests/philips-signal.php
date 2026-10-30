<?php
declare(strict_types=1);
require __DIR__ . '/philips-direct.php';
function signal(TVFixture $m, mixed $value, bool $changed = true, ?int $timestamp = null): void
{
    static $counter = 0;
    $GLOBALS['signalValues'][99] = $value;
    $m->MessageSink($timestamp ?? ++$counter, 99, VM_UPDATE, [$value, $changed]);
}
function configuredSignal(bool $on, bool $off, mixed $initial): TVFixture
{
    $GLOBALS['signalValues'][99] = $initial;
    $GLOBALS['signalUpdated'] = null;
    $m = new TVFixture();
    $m->props['WakeSignal'] = 99;
    $m->props['SignalPowerOn'] = $on;
    $m->props['SignalPowerOff'] = $off;
    $m->ApplyChanges();
    check($m->calls === [], 'Configuring options establishes a baseline without a TV request');
    return $m;
}
$m=configuredSignal(false,false,false);signal($m,true);signal($m,false);check($m->calls===[],'Both options off preserve read-only signal behavior');
$m=configuredSignal(true,false,false);signal($m,true);
check($m->calls===[['POST','powerstate',['powerstate'=>'On']]],'On-only sends explicit On on inactive-to-active transition');
signal($m,1);signal($m,'ON');check(count($m->calls)===1,'Equivalent active values do not repeat On');
signal($m,false);check(count($m->calls)===1,'On-only never sends Standby');
signal($m,true);check(count($m->calls)===2,'Next complete cycle may send On once');
$m=configuredSignal(false,true,true);signal($m,false);
check($m->calls===[['POST','powerstate',['powerstate'=>'Standby']]],'Off-only sends explicit Standby on active-to-inactive transition');
signal($m,0);signal($m,'OFF');check(count($m->calls)===1,'Equivalent inactive values do not repeat Standby');
signal($m,true);check(count($m->calls)===1,'Off-only never sends On');
$m=configuredSignal(true,true,'off');signal($m,'awake');signal($m,'standby');
check(array_column($m->calls,2)===[['powerstate'=>'On'],['powerstate'=>'Standby']],'Both options follow confirmed state changes independently');
$m=configuredSignal(true,true,true);signal($m,'unavailable');signal($m,false);check($m->calls===[],'Unknown-to-inactive recovery is not an off transition');
signal($m,true);check(end($m->calls)[2]===['powerstate'=>'On'],'New valid cycle restores automation');
$m=configuredSignal(true,true,null);signal($m,true);check($m->calls===[],'First valid state after unknown baseline does not switch');
signal($m,'offline');signal($m,false);check($m->calls===[],'Offline text cannot turn the TV off');
$m=configuredSignal(true,true,true);$GLOBALS['signalUpdated']=time()-200;signal($m,false);check($m->calls===[],'Stale source cannot switch TV');
$GLOBALS['signalUpdated']=null;
signal($m,false);check($m->calls===[],'State after stale event only restores baseline');
signal($m,true);check(count($m->calls)===1,'Fresh subsequent transition works');
$m=configuredSignal(true,true,true);$GLOBALS['signalUpdated']=time()+30;signal($m,false);check($m->calls===[],'Future source timestamp cannot switch TV');$GLOBALS['signalUpdated']=null;
$m=configuredSignal(true,true,true);signal($m,false,false);check($m->calls===[],'Unchanged event does not switch');
$m->props['Enabled']=false;signal($m,false);check($m->calls===[],'Disabled module cannot be switched by source');
$m=configuredSignal(true,true,true);$m->attrs['PairingSession']=json_encode(['expires'=>time()+120]);signal($m,false);
check($m->calls===[],'Pairing pauses signal actuation');$m->attrs['PairingSession']='';signal($m,false);check($m->calls===[],'No delayed off after pairing');
$m=configuredSignal(true,true,true);$m->fail['powerstate']=new RuntimeException('Connection fixture',201);signal($m,false);
check(count($m->calls)===1&&str_contains($m->values['LastError'],'Signal-Schaltbefehl fehlgeschlagen'),'Signal failure is retained as diagnostic, not an uncaught event exception');
signal($m,0);check(count($m->calls)===1,'Failed write is not automatically replayed for equivalent state');
$m=configuredSignal(true,true,true);$m->props['WakeSignal']=0;signal($m,false);check($m->calls===[],'No source means no automatic switch');
$m=configuredSignal(true,true,false);signal($m,true,true,1);check(count($m->calls)===1,'Sequence counter 1 is valid and is not compared with Unix time');
signal($m,false,true,1);signal($m,false,true,0);check(count($m->calls)===1,'Duplicate and out-of-order message counters cannot replay actions');
signal($m,false,true,2);check(count($m->calls)===2,'Next increasing sequence observes current source');
$m=configuredSignal(true,true,false);$GLOBALS['signalValues'][99]=true;$m->MessageSink(1,99,VM_UPDATE,[false,true]);
check($m->calls===[['POST','powerstate',['powerstate'=>'On']]],'Actuation uses current source, not obsolete queued payload');
echo "Philips signal automation: independent defaults/on/off/both, apply baseline, no repeats, unknown/stale/disabled/pairing guards and write failures passed.\n";
