<?php
/*
  NAME: SpecialWorkFerret
  PURPOSE: Special page as interface for WorkFerret
  AUTHOR: Woozle (Nick) Staddon
  TODO: Rewrite to use dtree.php; right now it requires the TreeAndMenu extension
  HISTORY:
    2010-01-29 0.0 (Wzl) Started writing
      Became usable at some point during this interval.
    2010-08-04 0.0 (Wzl) Fixed minor glitch in balance display (indicated calculation/stored mismatch when none visible)
    ????-??-?? 0.01 (Wzl) Much undocumented work.
    2013-06-14 0.02 (Wzl) More undocumented work; split off most code into WorkFerret.main.php for faster loading of wiki when WF is not being used.
    2013-06-16 0.03 (Wzl) Removed usage of ID_Proj field in Rates editing page.
*/
$wgSpecialPages['WorkFerret'] = 'SpecialWorkFerret'; # Let MediaWiki know about your new special page.
$wgExtensionCredits['specialpage'][] = array(	// "specialpage" is the section on the Version page where we want this to be listed
        'name' => 'Special:WorkFerret',
	'url' => 'http://htyp.org/WorkFerret',
        'description' => 'WorkFerret as Special page',
        'author' => 'Woozle (Nick) Staddon',
	'version' => '0.03 2013-06-16 dev'
);
$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['SpecialWorkFerret'] = $dir . 'WorkFerret.main.php'; # Location of the SpecialMyExtension class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['WorkFerret'] = $dir . 'WorkFerret.i18n.php'; # Location of a messages file

global $dbgShowLibs;
$dbgShowLibs = TRUE;

clsLibrary::Load_byName('ferreteria.forms.2');
