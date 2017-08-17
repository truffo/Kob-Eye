<?php
$info = Info::getInfos($vars['Path']);
$o = genericClass::createInstance($info['Module'],$info['ObjectType']);
$vars['identifier'] = $info['Module'].$info['ObjectType'];
$vars['ObjectClass'] = $o->getObjectClass();
$vars['operation'] = $vars['ObjectClass']->getOperations();
$vars['fields'] = $o->getElementsByAttribute('list','',true);
$vars['filters'] = $o->getCustomFilters();
if (is_object(Sys::$CurrentMenu)) {
    if ($vars['Type']=='Children') {
        $vars['CurrentUrl'] = Sys::getMenu($info['Module'] . '/' . $info['ObjectType']);
    }else $vars['CurrentUrl'] = Sys::$CurrentMenu->Url;
}else $vars['CurrentUrl'] = $vars['Query'];
if (!$vars['ObjectClass']->AccessPoint) $vars['Type'] = "Tail";
?>