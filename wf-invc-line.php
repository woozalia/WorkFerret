<?php
/*
  PURPOSE: classes for managing Invoice Line-items in WorkFerret
  HISTORY:
    2013-11-08 split off from WorkFerret.main
    2014-03-26 moved session-to-invoice process here, completely rewritten
      This fixes the bug where rates weren't being set.
*/
class clsWFInvcLines extends clsTable_key_single {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('invc_line');
	  $this->KeyName('ID');
	  $this->ClassSng('clsWFInvcLine');
	  $this->ActionKey('invlin');
    }
}
class clsWFInvcLine extends cDataRecord_MW {
    private $objForm;

    // ++ OVERRIDES ++ //

    /*----
      PURPOSE: Stub off event-logging stuff until we have time to write MW-compatible classes
    */
    public function CreateEvent(array $arArgs) {
	return NULL;
    }

    // -- OVERRIDES -- //
    // ++ SESSION ACCUMULATION ++ //

    // ++ new, one-session-per-line session conversion

    /*----
      ACTION: Populate the current Invoice Line record with data adapted from the given Session array
    */
    public function CopySession(clsWFSession $rcSess) {

	$this->Value('CostLine'	,$rcSess->CostLine_stored());
	$this->Value('LineDate'	,$rcSess->WhenStart_raw());
	$this->Value('What'	,$rcSess->Description());
	$this->Value('Qty'	,$rcSess->TimeFinal_hours());
	if ($rcSess->IsTimeBased()) {
	    $this->Value('Unit'      ,'hours');
	    $this->Value('Rate'      ,$rcSess->RateUsed());
	} else {
	    $this->ClearValue('Unit');
	    $this->ClearValue('Rate');
	}
	$this->Value('CostBal'	,$rcSess->CostBalance_stored());
    }

    // ++ old, complicated session conversion begins

    private $arSess,$rcSess;
    private $sWhat,$sWhatLast;
    private $idRateLast,$sDateLast;
    private $nSeq;
    private $nQtyMin;	// number of minutes
    private $dlrLine,$dlrBal;
    public function InvoiceStart() {
	$this->nSeq = $this->InvoiceRecord()->LineCount();
	$this->idRateLast = NULL;
	$this->sDateLast = NULL;
	$this->dlrBal = NULL;
//	$this->ClearLine();
    }
    public function ClearLine() {
	$this->arSess = NULL;
	$this->sWhat = NULL;
	$this->sWhatLast = NULL;
	$this->nQtyMin = NULL;
	$this->dlrLine = NULL;
	$this->nSeq++;
    }
    public function AddSessions(array $arSess) {
	$rcSess = $this->SessionsTable()->SpawnItem();
	foreach ($arSess as $strSort => $rowSess) {
	    $rcSess->Values($rowSess);
	    $this->AddSession($rcSess);
	}
	$this->CloseLine();	// close out any remaining sessions
    }
    public function AddSession(clsWFSession $rcSess) {
	// if session needs a new line, write the old line and start a new one
	if (!$this->SameLine($rcSess)) {
	    $this->CloseLine();
	    $this->ClearLine();
	}

	$sWhatNow = $rcSess->Value('Descr');
	if ($sWhatNow != $this->sWhatLast) {
	    if (($sWhatNow != '') && ($this->sWhat != '')) {
		$this->sWhat .= ' / ';
	    }
	    $this->sWhat .= $sWhatNow;
	}
	$this->arSess[$rcSess->KeyValue()] = $rcSess->Values();
	$this->nQtyMin += $rcSess->Value('TimeTotal');
	$this->dlrLine += $rcSess->Value('CostLine');
	$this->rcSess = clone $rcSess;	// save latest session for reference
    }
    /*----
      ACTION: If the line has anything to write, write it and update the sessions
    */
    protected function CloseLine() {
	if (count($this->arSess) > 0) {
	    $this->WriteRecord();
	    $this->UpdateSessions();
	}
    }
    /*----
      TODO: This needs to handle unit conversion properly -- when there is proper support for units
    */
    protected function WriteRecord() {
	$this->dlrBal += $this->dlrLine;
        $arIns = array(
          'ID_Invc'	=> $this->InvoiceID(),
          'Seq'		=> $this->nSeq,
          'LineDate'	=> SQLValue($this->sDateLast),
          'What'	=> SQLValue($this->sWhat),
          'Qty'		=> round(100*$this->nQtyMin/60)/100,
          'Unit'	=> 'NULL',	// not really supported yet
          'Rate'	=> $this->rcSess->Value('BillRate'),
          'CostLine'	=> $this->dlrLine,
          'CostBal'	=> $this->dlrBal
          );

        // add the condensed sessions as invoice lines
        $id = $this->Table()->Insert($arIns);
        $this->Value('ID',$id);
    }
    protected function UpdateSessions() {
	$rcSess = clone $this->rcSess;	// just to use as a blank session record
	foreach ($this->arSess as $idSess => $arRec) {
	    $rcSess->Values($arRec);
	    $this->UpdateSession($rcSess);
	}
    }
    protected function UpdateSession(clsWFSession $rcSess) {
	$arUpd = array(
	    'ID_Invc'		=> $this->InvoiceID(),
	    'ID_InvcLine'	=> $this->KeyValue()
	    );
	$rcSess->Update($arUpd);
    }
    protected function SameLine(clsWFSession $rcSess) {
	  $idRateThis = $rcSess->Value('ID_Rate');
	  $sDateThis = $rcSess->WhenStart_text();

	  $isSame =
	    ($idRateThis == $this->idRateLast) &&
	    ($sDateThis == $this->sDateLast);
	  if (!$isSame) {
	      $this->idRateLast = $idRateThis;
	      $this->sDateLast = $sDateThis;
	  }
	  return $isSame;
    }

    // -- SESSION ACCUMULATION -- //
    // ++ DATA FIELD ACCESS ++ //

    /*----
      PUBLIC so that Invoice object can set it in a new line
    */
    public function InvoiceID($id=NULL) {
	return $this->Value('ID_Invc',$id);
    }
    public function Seq($n=NULL) {
	return $this->Value('Seq',$n);
    }
    public function ShortDesc() {
	$rcInvc = $this->InvoiceRecord();
	$sDate = $this->LineDate_forDisplay();
	$out = $rcInvc->Value('InvcNum').'.'.$this->Value('Seq').' '.$sDate;
	return $out;
    }
    protected function LineDate() {
	return $this->Value('LineDate');
    }
    protected function LineDate_forDisplay() {
	$utDate = strtotime($this->LineDate());
	$sDate = date('Y-m-d',$utDate);
	return $sDate;
    }
    /*----
      PURPOSE: callback for drop-down list
    */
    public function Text_forList() {
	return $this->ShortDesc();
    }
    protected function IsVoid() {
	return !is_null($this->Value('WhenVoid'));
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function ProjectTable() {
	return $this->Engine()->Make('clsWFProjects');
    }
    /*----
      RETURNS: object for the Sessions table
      HISTORY:
	2011-12-04 written
    */
    protected function SessionsTable() {
	return $this->Engine()->Make('clsWFSessions');
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      RETURNS: recordset of sessions assigned to this invoice line
      HISTORY:
	2011-12-04 written
    */
    public function SessionsRc() {
	$tblSess = $this->SessionsTable();
	$sql = $this->SQL_Filter_Sessions();
	$rcSess = $tblSess->GetData($sql);
	return $rcSess;
    }
    private $objInvc;
    public function InvoiceRecord() {
	$doLoad = FALSE;
	$idInvc = $this->Value('ID_Invc');
	if (empty($this->objInvc)) {
	    $doLoad = TRUE;
	} else {
	    if ($this->objInvc->KeyValue() != $idInvc) {
		$doLoad = TRUE;
	    }
	}
	if ($doLoad) {
	    $this->objInvc = $this->Engine()->Invoices()->GetItem($idInvc);
	}
	return $this->objInvc;
    }
    /*----
      RETURNS: recordset of invoices for this project
    */
    protected function InvoiceRecords() {
	return $this->ProjectRecord()->InvoiceRecords();
    }
    protected function ProjectRecord() {
	return $this->InvoiceRecord()->ProjectRecord();
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Creates a new ILine record from $this, which is assumed
	to be a fully-populated ILine recordset object.
    */
    public function Create() {
	$arIns = $this->Values();
	$arIns = array(
	    'ID_Invc'	=> $this->InvoiceID(),
	    'Seq'	=> $this->Value('Seq'),
	    'LineDate'	=> SQLValue($this->Value('LineDate')),
	    'WhenVoid'	=> 'NULL',
	    'What'	=> SQLValue($this->Value('What')),
	    'Qty'	=> $this->Value('Qty'),
	    'Unit'	=> SQLValue($this->Value('Unit')),
	    'Rate'	=> SQLValue($this->Value('Rate')),
	    'CostLine'	=> $this->Value('CostLine'),
	    'CostBal'	=> $this->Value('CostBal'),
	    //'Notes`      VARCHAR(255) DEFAULT NULL COMMENT "human-entered notes",
	  );
	$id = $this->Table()->Insert($arIns);
	if ($id === FALSE) {
	    return NULL;
	} else {
	    $this->KeyValue($id);
	    return $id;
	}
    }

    /*----
      ACTION: voids the current line and returns its sessions to the un-invoiced pool
      HISTORY:
	2011-12-03 started
    */
    public function DoVoid() {
	$tblSess = $this->SessionsTable();
	$ar = array(
	  'ID_Invc'	=> 'NULL',
	  'ID_InvcLine'	=> 'NULL',
	  );
	$sql = $this->SQL_Filter_Sessions();
	$tblSess->Update($ar,$sql);

	$ar = array('WhenVoid' => 'NOW()');
	$this->Update($ar);
    }

    // -- ACTIONS -- //
    // ++ CALCULATIONS ++ //

    /*----
      RETURNS: SQL filter for retrieving sessions assigned to this invoice line
    */
    protected function SQL_Filter_Sessions() {
	return 'ID_InvcLine='.$this->KeyValue();
    }

    // -- CALCULATIONS -- //
    // ++ WEB UI ELEMENTS ++ //

    public function DropDown(array $iarArgs=NULL) {
	if ($this->hasRows()) {
//	    $strName = nz($iarArgs['ctrl.name'],'invcline');
	    if (array_key_exists('ctrl.name',$iarArgs)) {
		$strName = $iarArgs['ctrl.name'];
	    } else {
		throw new exception('Control name must be specified in input array.');
	    }
	    $idSel = nz($iarArgs['cur.id']);
	    $doNone = nz($iarArgs['none.use'],FALSE);		// include 'none' as an option
	    $strNone = nz($iarArgs['none.text'],'NONE');	// text to show for 'none'
	    $sqlNone = nz($iarArgs['none.sql'],'NULL');	// SQL to use for 'none'
	    $out = "\n".'<select name="'.$strName.'">';
	    if ($doNone) {
		// "NONE"
		if (empty($idSel)) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= "\n  ".'<option'.$htSelect.' value="'.$sqlNone.'">'.$strNone.'</option>';
	    }
	    // actual invc lines
	    while ($this->NextRow()) {
		if ($this->ID == $idSel) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= "\n  ".'<option'.$htSelect.' value="'.$this->ID.'">'.$this->ShortDesc().'</option>';
	    }
	    $out .= "\n</select>";

	    return $out;
	} else {
	    return 'N/A';
	}
    }

    // -- WEB UI ELEMENTS -- //
    // ++ WEB UI PAGES/SECTIONS ++ //

    public function AdminList($iDoRenum) {
	global $wgOut;
	global $vgPage;

	if ($this->hasRows()) {
	    $doSessionsReq = $vgPage->Arg('vsess');
	    $doSendableReq = $vgPage->Arg('vsend');

	    $doSessions = $doSessionsReq;
	    $doSendable = $doSendableReq;

	    $strAction = $vgPage->Arg('do');
	    $doBal = ($strAction == 'bal');
//	    $doRen = ($strAction == 'ren');
	    $doRen = $iDoRenum;
	    $strType = $vgPage->Arg('page');
	    if ($strType == 'invc') {
		$idInvc = $vgPage->Arg('id');
		// get list of sessions for this invoice
		$objInvc = $this->objDB->Invoices()->GetItem($idInvc);
		$rsSess = $objInvc->SessionRecords();
		if ($rsSess->hasRows()) {
		    while ($rsSess->NextRow()) {
			$idSess = $rsSess->KeyValue();
			$idLine = $rsSess->Value('ID_InvcLine');
			$rcSess = $rsSess->RowCopy();
			$arSess[$idSess] = $rcSess;
			$arLines[$idLine][] = $rcSess;
		    }
		} else {
		    // can't list sessions because none are assigned
		    $doSessions = FALSE;
		    $sSessMsg = 'No sessions are assigned to this invoice.';
		}
	    } else {
		// can't list sessions because we don't know the invoice ID
		$doSessions = FALSE;
		$sSessMsg = 'Invoice ID is not known.';
	    }

	    $objPage = new clsWikiFormatter($vgPage);

	    $objSection = new clsWikiSection_std_page($objPage,'Lines',2);

	    $objLink = $objSection->AddLink(new clsWikiSection_section('do'));
	    $objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'ren','do','renumber'));
	      $objLink->Popup('re-number the invoice lines');
	    $objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'bal','do','balance'));
	      $objLink->Popup('recalculate the invoice balance');
	    $objLink = $objSection->AddLink(new clsWikiSection_section('view'));
	    $objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'sendable','vsend'));
	      $objLink->Popup('view invoice lines formatted for sending');
	    $objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'sessions','vsess'));
	      $objLink->Popup('view individual sessions for each line');

	    $out = $objSection->Render();

	    if ($doSendable) {
		$out .= 'Sendable format:';

		$out .= "\n<table><tr><th>#</th><th>Date</th><th>Description</th><th>Qty & Units</th><th>Rate</th><th>$ Line</th><th>$ Bal</th></tr>";
		$qCols = 7;
	    } else {
		if ($doSessionsReq) {
		    if ($doSessions) {
			$out .= 'Showing individual sessions:';
		    } else {
			$out .= 'Cannot show individual sessions: '.$sSessMsg;
		    }
		}

		$out .= "\n<table><tr><th>ID</th><th>Seq</th><th>Date</th><th>Qty</th><th>Unit</th><th>Rate</th><th>$ Line</th><th>$ Bal</th><th>Description</th></tr>";
		$qCols = 9;
	    }
	    $isOdd = TRUE;
	    $yrLast = 0;
	    $ctsBalCalc = 0;
	    $dlrBalSaved = NULL;
	    $intSeq = 0;
	    while ($this->NextRow()) {
		$isVoid = !is_null($this->Value('WhenVoid'));
		$doHide = ($doSendable && $isVoid);

		if (!$doHide) {
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		    $isOdd = !$isOdd;

		    if ($isVoid) {
			$wtStyle .= ' text-decoration: line-through;';
		    } else {
			$intSeq++;
		    }

		    $utLine = strtotime($this->Value('LineDate'));


		    $yrCur = date('Y',$utLine);
		    if ($yrLast != $yrCur) {
			// display header with year
			$yrLast = $yrCur;
			$out .= "\n<tr style=\"background: #444466; color: #ffffff;\"\n><td colspan=$qCols><b>$yrCur</b></td></tr>";
		    }
		    $ftDate = date('m/d',$utLine);

		    $ar = $this->Values();

		    $ftID = $this->AdminLink();
		    $txtSeq = $ar['Seq'];
		    if ($doRen) {
			if ($txtSeq == $intSeq) {
			    $ftSeq = $txtSeq.'&radic;';
			} else {
			    $arUpd = array('Seq'=>$intSeq);
			    $this->Update($arUpd);
			    $this->Value('Seq',$intSeq);
			    $ftSeq = '<i>'.$intSeq.'</i>';
			}
		    } else {
			if ($isVoid) {
			    $ftSeq = '';
			} else {
			    $ftSeq = $txtSeq;
			}
		    }
		    $ftWhat = htmlspecialchars($ar['What']);
		    $ftQty = sprintf('%0.2f',$ar['Qty']);
		    $ftUnit = $ar['Unit'];
		    $ftRate = $ar['Rate'];
		    $ftCost = $ar['CostLine'];
		    $ctsCostLine = round($ar['CostLine']*100);
		    if (!$isVoid) {
			$ctsBalCalc += $ctsCostLine;
		    }
		    $dlrBalCalc = (round($ctsBalCalc))/100;
		    $dlrBalSaved = $ar['CostBal'];
		    $ctsBalSaved = round($dlrBalSaved*100);
		    //$ftBalCalc = sprintf('%0.2f',$dlrBalSaved);
		    if ($ctsBalCalc == $ctsBalSaved) {
			$ftBal = $ar['CostBal'];
		    } else {
			// create string showing the change:
			//$ctsBalSaved = ((int)($dlrBalSaved*100));
			$ftBalWas = sprintf('%0.2f',$dlrBalSaved);
			if ($isVoid) {
			    $ftBalCalc = '';
			} else {
			    $ftBalCalc = sprintf('%0.2f',$dlrBalCalc);
			}
			//$ftBalDiff = 'was:'.$ftBalWas.' now:'.$ctsBalCalc.' diff:'.($ctsBalCalc-((int)($dlrBalSaved*100)));
			if ($doBal) {
			  // update the balance
			    $arUpd['CostBal'] = $dlrBalCalc;
			    $this->Update($arUpd);
			    $ftBal = '<small><s>'.$ftBalWas.'</s> &rarr; <b>'.$ftBalCalc.'</b></small>';
			} else {
			    $ftBal = '<small><font color=red><i>'.$ar['CostBal'].'</i></font><br><font color=green><b>'.$ftBalCalc.'</b></font></small>';
			}
		    }

		    $out .= "\n<tr style=\"$wtStyle\">";
		    if ($doSendable) {
			if ($ftQty == 0) {
			    $ftQtyUnit = '';
			} else {
			    $ftQtyUnit = $ftQty.' '.$ftUnit;
			}
			$out .=
			  "<td>$ftSeq</td><td>$ftDate</td><td>$ftWhat</td><td>$ftQtyUnit</td><td>$ftRate"
			  ."</td><td align=right>$ftCost</td><td align=right>$ftBal</td>";
		    } else {
			$out .=
			  "<td>$ftID</td><td>$ftSeq</td><td>$ftDate</td><td>$ftQty</td><td>$ftUnit</td><td>$ftRate"
			  ."</td><td align=right>$ftCost</td><td align=right>$ftBal</td><td>$ftWhat</td>";
		    }
		    $out .= "\n</tr>";
		    if ($doSessions) {
			$idLine = $this->KeyValue();

			if (array_key_exists($idLine,$arLines)) {
			    $arLineSess = $arLines[$idLine];
			    foreach ($arLineSess as $key => $obj) {
				$idSess = $obj->KeyValue();
				unset($arSess[$idSess]);
				$intSeq = $obj->Value('Seq');
				$htSeq = ($intSeq > 0)?($intSeq.'. '):'';
				$htLine = $htSeq.' ('.$obj->AdminLink().') '.$obj->Value('TimeTotal').'m/'.$obj->Value('CostLine').': '.$obj->Value('Descr');

				$out .= "\n<tr style=\"$wtStyle\">"
				  ."\n<td bgcolor=blue></td><td colspan=".($qCols-1).">$htLine</td></tr>";
			    }
			} else {
			    $out .= "\n<tr style=\"$wtStyle\">"
			      ."\n<td bgcolor=red></td><td colspan=".($qCols-1).">no sessions</td></tr>";
			}
		    }
		}
	    }
	    if ($doSessions && (count($arSess) > 0)) {
		$out .= "\n<tr style=\"background: #888888; color: #ffffff;\"><td colspan=$qCols><b>non line-specific</b></td>";
		foreach ($arSess as $key => $obj) {
		    $htSeq = ($obj->Seq > 0)?($obj->Seq.'. '):'';
		    $htLine = $htSeq.$obj->TimeTotal.'m/'.$obj->CostLine.': '.$obj->Descr;
		    $out .= "\n<tr style=\"$wtStyle\"><td bgcolor=blue></td><td colspan=".($qCols-1)."</td><td>$htLine</td></tr>";
		}
	    }
	    if ($doSendable) {
		$out .= "\n<tr><td colspan=3 align=right><b>TOTAL</b>: </td><td></td><td></td><td></td><td><b>$ftBal</b></td></tr>";
	    }
	    $out .= "\n</table>";
	    if ($dlrBalSaved != $dlrBalCalc) {
		$out .= "\n<b>Warning</b>: Recorded total of <b>$dlrBalSaved</b> does not match calculated total of <b>$dlrBalCalc</b>!";
	    }
	    return $out;
	} else {
	    return 'No lines found in this invoice.';
	}
    }
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;

	$vgPage->UseHTML();

	$doSave = $wgRequest->getBool('btnSave');
	$doEdit = $vgPage->Arg('edit');
	$strAction = $vgPage->Arg('do');

	$doVoid = FALSE;
	switch ($strAction) {
/*	  case 'edit':
	    $doEdit = TRUE;
	    break;
*/
	  case 'void':
	    $doVoid = TRUE;
	    break;
	}

	if ($doVoid) {
	    $this->DoVoid();
	    //$this->AdminRedirect();
	    $this->Reload('Line has been VOIDed');
	}

	if ($doSave) {
	    $this->AdminSave();
	    $this->AdminRedirect();
	}

	$doForm = $doEdit;

	$id = $this->KeyValue();
	$strName = 'Invoice Line '.$this->InvoiceRecord()->Value('InvcNum').'.'.$this->Value('Seq');

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);

	$objSection = new clsWikiSection_std_page($objPage,$strName,2);
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'edit',NULL,NULL,'view'));
	  $objLink->Popup('edit this invoice line');
	$out = $objSection->Render();

	if ($doForm) {
	    $out .= "\n<form method=post>";
	}

	// calculate form add-ons

	if ($this->IsVoid() || $doEdit) {	// don't show VOID link when editing
	    $htVoidNow = NULL;
	} else {
	    $sPopup = 'immediately VOID the invoice line and mark its sessions as not invoiced';
	    $arLink['do'] = 'void';
//	    $htVoidNow = ' ['.$vgOut->SelfLink($arLink,'void now',$sPopup).']';
	    $htVoidNow = ' ['.$this->AdminLink('void now',$sPopup,$arLink).']';
	}

	// render the form

	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	  // custom vars
	  $arCtrls['ID'] = $this->AdminLink();
	  $arCtrls['!DoVoid'] = $htVoidNow;

	if (!$doEdit) {
	    $rcInvc = $this->InvoiceRecord();
	    $arCtrls['ID_Invc'] = $rcInvc->AdminLink_name();
	}

	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->Render();
	// TODO: working here

/* 2015-05-04 old version
	// get values in various formats
	$utDate = strtotime($this->Value('LineDate'));
	$fvVoid = $this->Value('WhenVoid');
	if (empty($fvVoid)) {
	    $htvVoid = '<i>n/a</i>';
	    $arLink = $vgPage->Args(array('page','id'));
	    $arLink['do'] = 'void';
	    $htVoidNow = ' ['.$vgOut->SelfLink($arLink,'void now', 'VOID the invoice line and mark its sessions as not invoiced (without saving edits)').']';
	} else {
	    $utVoid = strtotime($fvVoid);
	    $htvVoid = date('Y-m-d H:i',$utVoid);
	    $htVoidNow = NULL;
	}

	$strDate = date('Y-m-d',$utDate);
	$fltQty = $this->Value('Qty');
	$fltRate = $this->Value('Rate');
	$fltCost = $this->Value('CostLine');
	if (empty($fltRate)) {
	    $doShowCalc = FALSE;
	} else {
	    $dlrCostCalc = ((int)($fltRate * $fltQty * 100))/100;
	    $doShowCalc = ($dlrCostCalc != $fltCost);
	}

	// get HTML-safe values
	$htvSeq = htmlspecialchars($this->Value('Seq'));
	$htvWhat = htmlspecialchars($this->Value('What'));
	$htvQty = $this->Value('Qty');
	$htvUnit = htmlspecialchars($this->Value('Unit'));
	$htvCostLine = $fltCost;	// LATER: show extra digits, if any, in red or something
	$htvCostBal = $this->Value('CostBal');
	$htvNotes = htmlspecialchars($this->Value('Notes'));

	$idInvc = $this->Value('ID_Invc');

	if ($doEdit) {
	    $objForm = $this->objForm;
	    $ar = array(
	      'default'	=> $idInvc,
	      'name'	=> 'ID_Invc',
	      );
	    $htInvc	= $this->Engine()->Invoices()->DropDown($ar);
	    $htSeq	= $objForm->RenderControl('Seq');
	    $htDate	= $objForm->RenderControl('LineDate');
	    $htVoid	= $objForm->RenderControl('WhenVoid');
	    $htWhat	= $objForm->RenderControl('What');
	    $htQty	= $objForm->RenderControl('Qty');
	    $htUnit	= $objForm->RenderControl('Unit');
	    $htRate	= $objForm->RenderControl('Rate');
	    $htCost	= $objForm->RenderControl('CostLine');
	    $htBal	= $objForm->RenderControl('CostBal');
	    $htNotes	= $objForm->RenderControl('Notes');
	} else {
	    $objInvc = $this->InvoiceRecord();
	    $htInvc = $objInvc->AdminLink($objInvc->Value('InvcNum'));
	    $htSeq = $htvSeq;
	    $htDate = $strDate;
	    $htVoid = $htvVoid;
	    $htWhat = $htvWhat;
	    $htQty = $htvQty;
	    $htUnit = $htvUnit;
	    $htRate = $fltRate;
	    $htCost = '$'.$htvCostLine;
	    $htBal = '$'.$htvCostBal;
	    $htNotes = $htvNotes;
	}

	$htVoid .= $htVoidNow;

	$htID = $this->AdminLink();

//	$rcRate = $this->RateObj();	// TO BE WRITTEN
//	if (!$rcRate->isLineItem()) {
	    // only show this if total is based on a rate calculation
	    if ($doShowCalc) {
		$htCost .= ' <font color=red><b>!</b></font> should be '.$dlrCostCalc;
	    } else {
		$htCost .= ' <font color=green>ok</font>';
	    }
//	}

	$out .= '<table>';
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>";
	$out .= "\n<tr><td align=right><b>Invoice</b>:</td><td>$htInvc</td></tr>";
	$out .= "\n<tr><td align=right><b>Sequence</b>:</td><td>$htSeq</td></tr>";
	$out .= "\n<tr><td align=right><b>Date</b>:</td><td>$htDate</td></tr>";
	$out .= "\n<tr><td align=right><b>Void</b>:</td><td>$htVoid</td></tr>";
	$out .= "\n<tr><td align=right><b>What</b>:</td><td>$htWhat</td></tr>";
	$out .= "\n<tr><td align=right><b>Quantity</b>:</td><td>$htQty</td></tr>";
	$out .= "\n<tr><td align=right><b>Unit</b>:</td><td>$htUnit</td></tr>";
	$out .= "\n<tr><td align=right><b>Rate</b>:</td><td>$htRate</td></tr>";
	$out .= "\n<tr><td align=right><b>Cost</b>:</td><td>$htCost</td></tr>";
	$out .= "\n<tr><td align=right><b>Balance</b>:</td><td>$htBal</td></tr>";
	$out .= "\n<tr><td align=right><b>Notes</b>:</td><td>$htNotes</td></tr>";
	$out .= '</table>'; */

	if ($doForm) {
	    $out .= '<input type=submit name=btnSave value="Save">';
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out); $out = '';

	$out .= '<h3>Sessions</h3>';
	$rcSess = $this->SessionsRc();
	$out .= $rcSess->AdminList(array());
	$wgOut->AddHTML($out); $out = '';
    }
    /*-----
      TO DO: When lines are moved to different invoices, both the original invoice
	and the target invoice need to be renumbered.
    */
    /*-----
      ACTION: Save the user's edits to the shipment
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
	2011-12-03 Adapted from VbzCart to WorkFerret.clsWFInvcLine.
    */
    private function AdminSave() {
	global $vgOut;

	$out = $this->PageForm()->Save();
	$vgOut->AddText($out);
    }

    // -- WEB UI PAGES/SECTIONS -- //
    // ++ WEB UI PAGE CONSTRUCTION ++ //

    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-01-26 adapted from VbzAdminTitle
	2011-12-03 adapted for WorkFerret
    */
    private $frmPage;
    private function PageForm() {
	if (is_null($this->frmPage)) {
	    $oForm = new fcForm_DB($this->Table()->ActionKey(),$this);

	      $oField = new fcFormField_Num($oForm,'ID_Invc');
		$oCtrl = new fcFormControl_HTML_DropDown($oForm,$oField,array());
                $oCtrl->Records($this->InvoiceRecords());	// ideally: all open invoices for the same project

	      $oField = new fcFormField_Text($oForm,'Seq');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>2));

	      $oField = new fcFormField_Time($oForm,'LineDate');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>10));

	      $oField = new fcFormField_Time($oForm,'WhenVoid');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>15));	// allow room for time-of-day

	      $oField = new fcFormField_Text($oForm,'What');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>40));

	      $oField = new fcFormField_Num($oForm,'Qty');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>2));

	      $oField = new fcFormField_Text($oForm,'Unit');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));

	      $oField = new fcFormField_Num($oForm,'Rate');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));

	      $oField = new fcFormField_Num($oForm,'CostLine');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>7));

	      $oField = new fcFormField_Num($oForm,'CostBal');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>7));

	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oForm,$oField,array('rows'=>3,'cols'=>50));
/* Forms v1

	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $oForm = new fcForm_DB($this->Table()->ActionKey(),$this);


	    $objForm = new clsForm_recs($this,$vgOut);
	    $objForm->AddField(new clsFieldNum	('ID_Invc'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField	('Seq'),	new clsCtrlHTML(array('size'=>2)));
	    $objForm->AddField(new clsFieldTime	('LineDate'),	new clsCtrlHTML(array('size'=>10)));
	    $objForm->AddField(new clsFieldTime	('WhenVoid'),	new clsCtrlHTML(array('size'=>15)));
	    $objForm->AddField(new clsField	('What'),	new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsFieldNum	('Qty'),	new clsCtrlHTML(array('size'=>3)));
	    $objForm->AddField(new clsField	('Unit'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum	('Rate'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum	('CostLine'),	new clsCtrlHTML(array('size'=>7)));
	    $objForm->AddField(new clsFieldNum	('CostBal'),	new clsCtrlHTML(array('size'=>7)));
	    $objForm->AddField(new clsField	('Notes'),	new clsCtrlHTML_TextArea(array('height'=>3,'width'=>50)));

	    $this->objForm = $objForm; */
	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>[#ID#]</td></tr>
  <tr><td align=right><b>Invoice</b>:</td><td>[#ID_Invc#]</td></tr>
  <tr><td align=right><b>Sequence</b>:</td><td>[#Seq#]</td></tr>
  <tr><td align=right><b>Date</b>:</td><td>[#LineDate#]</td></tr>
  <tr><td align=right><b>Void</b>:</td><td>[#WhenVoid#] [#!DoVoid#]</td></tr>
  <tr><td align=right><b>What</b>:</td><td>[#What#]</td></tr>
  <tr><td align=right><b>Quantity</b>:</td><td>[#Qty#]</td></tr>
  <tr><td align=right><b>Unit</b>:</td><td>[#Unit#]</td></tr>
  <tr><td align=right><b>Rate</b>:</td><td>[#Rate#]</td></tr>
  <tr><td align=right><b>Cost</b>:</td><td>[#CostLine#]</td></tr>
  <tr><td align=right><b>Balance</b>:</td><td>[#CostBal#]</td></tr>
  <tr><td align=right><b>Notes</b>:</td><td>[#Notes#]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpPage;
    }

    // -- WEB UI PAGE CONSTRUCTION -- //
}
