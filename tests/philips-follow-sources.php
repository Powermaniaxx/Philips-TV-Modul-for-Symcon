<?php
declare(strict_types=1);
require __DIR__ . '/philips-direct.php';

function followFixture(string $shield = 'standby', bool $avr = false): TVFixture
{
    $GLOBALS['signalValues'][16350] = $shield;
    $GLOBALS['signalValues'][10950] = $avr;
    $m = new TVFixture();
    $m->props['FollowSources'] = true;
    $m->props['ShieldSignal'] = 16350;
    $m->props['AVRSignal'] = 10950;
    $m->ApplyChanges();
    return $m;
}
function sourceUpdate(TVFixture $m, int $id, mixed $value): void
{
    static $counter = 100;
    $GLOBALS['signalValues'][$id] = $value;
    $m->MessageSink(++$counter, $id, VM_UPDATE, [$value, true]);
}
$m=followFixture();
check($m->timers['Poll']===0 && $m->calls===[], 'Both off: no timer and no commands after configuration');
sourceUpdate($m,16350,'awake');
check($m->calls===[['POST','powerstate',['powerstate'=>'On']]],'Shield awake switches TV on');
check($m->timers['Poll']===700,'On command confirmation scheduled');
$m->UpdateStatus();check($m->timers['Poll']===30000,'Active source gives 30-second polling');
sourceUpdate($m,10950,true);
check(count($m->calls)===6,'Second source becoming active does not resend On');
sourceUpdate($m,16350,'standby');
check(count($m->calls)===6,'Shield standby keeps TV on while AVR active');
sourceUpdate($m,10950,false);
check(end($m->calls)===['POST','powerstate',['powerstate'=>'Standby']],'Last active source turns TV to standby');
check($m->timers['Poll']===0,'No automatic poll when both sources are off');
$count=count($m->calls);$m->UpdateStatus();check(count($m->calls)===$count,'Idle background timer cannot request TV status');
$m->RequestAction('Refresh',true);check($m->timers['Poll']===100,'Manual tile refresh can schedule a read');
$m->UpdateStatus();check(count($m->calls)>$count,'Manual refresh works with both sources off');
check($m->timers['Poll']===0,'Manual refresh does not restart idle polling');
$m=followFixture();
sourceUpdate($m,16350,'unavailable');sourceUpdate($m,10950,false);
check($m->calls===[],'Unknown source must not issue standby');
$m=followFixture('awake',false);
check($m->calls===[] && $m->timers['Poll']===1000,'Configuring an already active source starts polling but does not send a power command');
sourceUpdate($m,16350,'standby');check(end($m->calls)===['POST','powerstate',['powerstate'=>'Standby']],'Active initial baseline may switch off on subsequent transition');
echo "Philips Shield/AVR OR automation: transitions, dual active, idle/no poll, manual refresh and unknown-state guard passed.\\n";
