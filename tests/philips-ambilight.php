<?php
declare(strict_types=1);
require __DIR__ . '/philips-direct.php';
$m=new TVFixture();
$m->UpdateStatus();
check($m->presentations['AmbilightMode'] === '' && !$m->actions['AmbilightMode'], 'No mode selector before discovery');
$m->responses['ambilight/supportedstyles']=[
    'supportedStyles'=>[
        ['styleName'=>'FOLLOW_VIDEO','algorithms'=>['STANDARD','NATURAL','VIVID']],
        ['styleName'=>'FOLLOW_AUDIO','algorithms'=>['PARTY']],
        ['styleName'=>'FOLLOW_COLOR','algorithms'=>['MANUAL_HUE']],
        ['styleName'=>'OFF']
    ]
];
$m->DiscoverAmbilightStyles();
$modePresentation = $m->presentations['AmbilightMode'];
check($modePresentation['PRESENTATION'] === VARIABLE_PRESENTATION_ENUMERATION, 'Modes use a standard enumeration');
check(array_column(json_decode($modePresentation['OPTIONS'], true, 32, JSON_THROW_ON_ERROR), 'Value') ===
    ['FOLLOW_VIDEO', 'FOLLOW_AUDIO', 'FOLLOW_COLOR', 'OFF'], 'Mode options use the existing string values');
check($m->actions['AmbilightMode'] && $m->actions['AmbilightAlgorithm'], 'Available selectors are actionable while awake');
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
$variantPresentation = $m->presentations['AmbilightAlgorithm'];
check($variantPresentation['PRESENTATION'] === VARIABLE_PRESENTATION_ENUMERATION, 'Variants use a standard enumeration');
check(array_column(json_decode($variantPresentation['OPTIONS'], true, 32, JSON_THROW_ON_ERROR), 'Value') ===
    ['STANDARD', 'NATURAL', 'VIVID'], 'Only variants of the current mode are offered');
$m->ApplyChanges();
check($m->presentations['AmbilightAlgorithm'] === $variantPresentation && $m->values['AmbilightAlgorithm'] === 'NATURAL',
    'Updating the default presentation preserves the confirmed string value');
check(!$m->actions['AmbilightMode'] && !$m->actions['AmbilightAlgorithm'], 'Actions wait for a fresh online state');
$m->UpdateStatus();
$m->responses['ambilight/supportedstyles']=['unexpected'=>'value'];
$m->DiscoverAmbilightStyles();
check(str_contains($m->values['LastError'], 'keine gültige Liste'), 'Bad discovery reports the API error');
$formOptions = json_decode($m->formFields['AmbilightChoice']['options'], true, 32, JSON_THROW_ON_ERROR);
check(in_array('FOLLOW_VIDEO|NATURAL', array_column($formOptions, 'value'), true), 'Bad discovery keeps previous available options');
echo "Philips Ambilight: discovery, standard enumerations, validated POST, confirmed variant and malformed response passed.\n";
