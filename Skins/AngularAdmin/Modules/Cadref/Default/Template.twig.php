<?php
$vars['Annee'] = $GLOBALS['Systeme']->getRegVars('AnneeEnCours');

$info = Info::getInfos($vars['Query']);
$o = genericClass::createInstance($info['Module'],$info['ObjectType']);
//$o->setView();
$vars['CurrentObj'] =  $o;
$vars['filters'] = $o->getCustomFilters();
$vars['recursiv'] = $o->isRecursiv();
foreach ($vars['filters'] as $k=>$f){
    if (empty($f->icon))$vars['filters'][$k]->icon = 'stats-growth';
    $vars['filters'][$k]->count = Sys::getCount($info['Module'],$info['ObjectType'].'/'.$f->filter);
}
$vars['CurrentMenu'] = Sys::$CurrentMenu;
if(Sys::$User->Admin && !$vars['CurrentMenu']){
    $oc = $o->getObjectClass();
    $vars['CurrentMenu'] = ['Titre' =>$oc->Description ];
}
$vars['identifier'] = $info['Module'] . $info['ObjectType'];

// PGF 20180912
if (isset($vars['Path']))
    $Path = $vars['Path'];
else
    $vars['Path'] = $Path = $vars['Query'];

$file = $o->isRecursiv() ? 'Tree' : 'List';
//$vars['listPath'] = 'Cadref/Default/'.$file;


$q = $info['Module'].'/'.$info['ObjectType'].'/';
$p = getcwd().'/Skins/'.Sys::$Skin.'/Modules/'.$q.$file;
$vars['listPath'] = file_exists($p.'.twig') ? $q.$file : 'Cadref/Default/'.$file;

?>