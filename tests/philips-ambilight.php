<?php
declare(strict_types=1);
require __DIR__ . '/philips-direct.php';
$m=new TVFixture();
$m->UpdateStatus();
$m->responses['ambilight/supportedstyles']=[
    'supportedStyles'=>[
        ['styleName'=>'FOLLOW_VIDEO','algorithms'=>['STANDARD','NATURAL','VIVID']],
        ['styleName'=>'FOLLOW_AUDIO','algorithms'=>['PARTY']],
        ['styleName'=>'FOLLOW_COLOR','algorithms'=>['MANUAL_HUE']],
        ['styleName'=>'OFF']
    ]
];
$m->DiscoverAmbilightStyles();
$form=json_decode($m->GetConfigurationForm(),true,32,JSON_THROW_ON_ERROR);
$choice=array_values(array_filter($form['actions'],fn($x)=>($x['name']??'')==='AmbilightChoice'))[0];
$values=array_column($choice['options'],'value');
check(in_array('FOLLOW_VIDEO|NATURAL',$values,true),'TV-reported video preset is listed');
check(in_array('FOLLOW_COLOR|MANUAL_HUE',$values,true),'TV-reported color preset is listed');
check(!in_array('FOLLOW_VIDEO|GAME',$values,true),'Unreported video preset is not invented');
$before=count($m->calls);
rejects(fn()=>$m->SetAmbilightStyle('FOLLOW_VIDEO|GAME'));
check(count($m->calls)===$before,'Unreported mode cannot send a command');
$m->SetAmbilightStyle('FOLLOW_VIDEO|NATURAL');
check(end($m->calls)===['POST','ambilight/currentconfiguration',['styleName'=>'FOLLOW_VIDEO','isExpert'=>false,'menuSetting'=>'NATURAL']],'Video preset uses currentconfiguration and safe payload');
check($m->values['AmbilightMode']==='FOLLOW_VIDEO','Command does not fabricate the displayed mode');
$m->responses['ambilight/currentconfiguration']=['styleName'=>'FOLLOW_VIDEO','menuSetting'=>'NATURAL'];
$m->UpdateStatus();
check($m->values['AmbilightAlgorithm']==='NATURAL','Confirmed ambilight algorithm is published');
$m->responses['ambilight/supportedstyles']=['unexpected'=>'value'];
rejects(fn()=>$m->DiscoverAmbilightStyles());
check(in_array('FOLLOW_VIDEO|NATURAL',array_column($m->formFields['AmbilightChoice']['options'],'value'),true),'Bad discovery keeps previous available options');
echo "Philips Ambilight: discover, supported selections, validated POST, confirmed variant and malformed response passed.\n";
