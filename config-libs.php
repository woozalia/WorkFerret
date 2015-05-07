<?php
/*
PURPOSE: define locations for libraries using modloader.php
FILE SET: WorkFerret libraries
HISTORY:
  2013-10-31 created
*/
$fp = dirname( __FILE__ );
clsModule::BasePath($fp.'/');

$om = new clsModule(__FILE__, 'wf-invc.php');
  $om->AddClass('clsWFInvoices');
$om = new clsModule(__FILE__, 'wf-invc-line.php');
  $om->AddClass('clsWFInvcLines');
$om = new clsModule(__FILE__, 'wf-proj.php');
  $om->AddClass('clsWFProjects');
$om = new clsModule(__FILE__, 'wf-proj-rate.php');
  $om->AddClass('clsProjs_x_Rates');
$om = new clsModule(__FILE__, 'wf-rate.php');
  $om->AddClass('clsWFRates');
//  $om->AddFunc('SQLValue');
//  $om->AddFunc('NzArray');
$om = new clsModule(__FILE__, 'wf-sess.php');
  $om->AddClass('clsWFSessions');