<?php
/*
  PURPOSE: classes for managing Sessions in WorkFerret
  HISTORY:
    2013-11-08 split off from WorkFerret.main
*/
class wfcSessions extends clsDataTable_Menu {
    private $ctsBalCalc;
    private $arSave;	// fields to set on insert/update

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('session');
	  $this->KeyName('ID');
	  $this->ClassSng('wfcSession');
	  $this->ActionKey('sess');
	$this->arSave = NULL;
    }

    // -- SETUP -- //
    // ++ RECORDSET ACCESS ++ //

    /*----
      INPUT:
	$idProj = ID of Project whose Sessions we want
	$doUseInvcd = if TRUE, include Sessions that have already been invoiced
    */
    public function Records_forProject($idProj,$doUseInvcd) {
	$qof = new fcSQLt_Filt('AND');
	$qof->AddCond('ID_Proj='.$idProj);

	// TODO: prevent ID_InvcLine from being saved as "0" when "NONE" is selected
	if ($doUseInvcd) {
	    $qof->AddCond('(ID_InvcLine IS NOT NULL) AND (ID_InvcLine != 0)');
	} else {
	    $qof->AddCond('(ID_InvcLine IS NULL) OR (ID_InvcLine=0)');
	}
	$sqlFilt = $qof->RenderValue();

	return $this->GetData($sqlFilt,NULL,'WhenStart,IFNULL(Sort,1000),WhenFinish,Seq');
    }


    // -- RECORDSET ACCESS -- //
    // ++ BUTTON PUSHES ++ //

    /*----
      HISTORY:
	2013-10-23 removed array from parameter list because nothing can use it
	2015-06-18 had to put it back in for AdminFinish, but now taking it back out again
    */
/*    public function EditSave_line() {
	$rcSess = $this->SpawnItem();
	$rcSess->EditSave_line();
    }*/
    /*----
      PURPOSE: Specialized AdminSave() that records the current time as the end of the session
      HISTORY:
	2013-10-24 created
    */
/*    public function EditFinish_line() {
	$rcSess = $this->SpawnItem();
	$rcSess->EditFinish_line();
    }*/

    // -- BUTTON PUSHES -- //
}
class wfcSession extends cDataRecord_MW {

    // ++ STATIC ++ //

    static public function SectionHdr(array &$arArgs) {
	global $vgPage;

	$doNew = $arArgs['doNew'];
	$doInvc = $arArgs['doInvc'];
	$doForm = $doInvc || $doNew;
	$arArgs['doForm'] = $doForm;

	$arOpts = array('context'=>'session','do'=>NULL);
	$objPage = new clsWikiFormatter($vgPage);

	$objSection = new clsWikiSection_std_page($objPage,'Sessions',3);
//	$objSection->PageKeys(array('page','id','show.invoiced')); // add "show.invoiced" to the list of page-identifiers
	$objSection->PageKeys(array('page','id','invcd')); // add "show.invoiced" to the list of page-identifiers

	$objLink = $objSection->AddLink(new clsWikiSection_section('do'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option($arOpts,'invc','do','invoice'));
	  $objLink->Popup('create an invoice');
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option($arOpts,'new','do'));
	  $objLink->Popup('create/edit new session');
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option($arOpts,'bal','do','balance'));
	  $objLink->Popup('recalculate running balance');
	$objLink = $objSection->AddLink(new clsWikiSection_section('view'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'invoiced','invcd'));
	  $objLink->Popup('show invoiced sessions');

	$out = $objSection->Render();

	if ($doForm) {
	    $out .= $objSection->FormOpen();
	    if ($doInvc) {
		$htBtns = '<div align=right><input type=submit name=btnInvc value="Create Invoice"></div>';
	    } else {
		$htBtns = '';
	    }
	    $out .= $htBtns;
	}
	return $out;
    }
    /*-----
      INPUT:
	$iDoEdit: TRUE = table should be formatted for editing (allow for wider columns
	  by putting description on separate line)
    */
    static protected function TableHdr($iDoEdit) {
	$out = "\n<tr>\n"
	      .'<th colspan=3>ID</th>'
	      .'<th>Date</th>'
	      .'<th>Start</th>'
	      .'<th>Finish</th>'
	      .'<th>Sort</th>'
	      .'<th>+Time</th>'
	      .'<th>-Time</th>'
	      .'<th>t Tot</th>'
	      .'<th>$Rate</th>'
	      .'<th>t*R</th>'
	      .'<th>+$</th>'
	      .'<th>$ Tot</th>'
//	      .'<th>Invoice</th>'
	      .'<th><big>&sum;</big>$</th>';
	if ($iDoEdit) {
	    // multiline header is more confusing than helpful, so do nothing
	} else {
	    $out .= '<th>Description</th>';
	}
	$out .= '</tr>';
	return $out;
    }

    // -- STATIC -- //
    // ++ FIELD ACCESS ++ //

    // PUBLIC for copying to invoice line
    public function Description() {
	return $this->Value('Descr');
    }
    // PUBLIC so it can be set externally before creating a new Session record
    public function ProjectID($id=NULL) {
        return $this->Value('ID_Proj',$id);
    }
    protected function RateID($id=NULL) {
	return $this->Value('ID_Rate',$id);
    }
    protected function InvoiceID($id=NULL) {
	return $this->Value('ID_Invc',$id);
    }
    protected function InvoiceLineID($id=NULL) {
        return $this->Value('ID_InvcLine',$id);
    }
    protected function HasInvoice() {
	return !is_null($this->InvoiceID());
    }
    protected function HasRate() {
        return ($this->HasValue('ID_Rate') && !is_null($this->RateID()));
    }
    // PUBLIC for ILine to copy from
    public function CostLine_stored() {
	return $this->Value('CostLine');
    }
    // PUBLIC for ILine to copy from
    public function CostBalance_stored() {
	return $this->Value('CostBal');
    }
    // PUBLIC for ILine to copy from
    public function TimeTotal_stored() {
	return $this->Value('TimeTotal');
    }
    public function SortKey() {
	return $this->Value('WhenStart').'.'.$this->Value('Sort').'.'.sprintf('%04d',$this->KeyValue());
    }
    // PUBLIC for ILine to copy from
    public function WhenStart_raw() {
	return $this->Value('WhenStart');
    }
    protected function WhenFinish_raw() {
	return $this->Value('WhenFinish');
    }
    /*----
      RETURNS: Session start time in UNIX integer format
    */
    protected function WhenStart_UT() {
	return strtotime($this->WhenStart_raw());	// convert SQL time to UNIX time
    }
    public function WhenStart_text() {
	$utTime = $this->WhenStart_UT();	// start time as UNIX integer
	$out = date('Y-m-d',$utTime);		// convert UNIX time to string
	return $out;
    }
    protected function WhenFinish_UT() {
	return strtotime($this->WhenFinish_raw());	// convert SQL time to UNIX time
    }
    public function WhenEntered_text() {
	$utTime = strtotime($this->Value('WhenEntered'));	// convert SQL time to UNIX time
	$out = date('Y-m-d',$utTime);				// convert UNIX time to string
	return $out;
    }
    public function WhenEdited_text() {
	$utTime = strtotime($this->Value('WhenEdited'));	// convert SQL time to UNIX time
	$out = date('Y-m-d',$utTime);				// convert UNIX time to string
	return $out;
    }
    public function WhenFigured_text() {
	$utTime = strtotime($this->Value('WhenFigured'));	// convert SQL time to UNIX time
	$out = date('Y-m-d',$utTime);				// convert UNIX time to string
	return $out;
    }
    protected function TimeSpan_isSet() {
	return !is_null($this->WhenStart_raw()) && !is_null($this->WhenFinish_raw());
    }
    protected function TimePlus() {
	return $this->Value('TimeAdd');
    }
    protected function TimePlus_isSet() {
	return !is_null($this->TimePlus());
    }
    protected function TimeMinus() {
	return $this->Value('TimeSub');
    }
    protected function TimeMinus_isSet() {
	return !is_null($this->TimeMinus());
    }
    protected function CostPlus() {
	return $this->Value('CostAdd');
    }
    protected function CostPlus_isSet() {
	return !is_null($this->CostPlus());
    }

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: Calculation of the final cost for this session, based on:
	* WhenStart
	* WhenFinish
	* TimeAdd
	* TimeSub
	* BillRate
	* CostAdd
    */
    public function Cost_calc() {
	$dlrCalc = 0;
	if ($this->UsesTime()) {
	    $dlrRate = $this->RateUsed();
	    $nHours = $this->TimeFinal_hours();
	    $dlrCalc = $dlrRate * $nHours;
	}
	$dlrCalc += $this->CostPlus();
	return $dlrCalc;
    }
    /*----
      RETURNS: TRUE iff enough fields are set to determine a cost based on time
    */
    protected function UsesTime() {
	return $this->TimePlus_isSet() || $this->TimeMinus_isSet() || $this->TimeSpan_isSet();
    }
    protected function TimeSpan_seconds() {
	if ($this->TimeSpan_isSet()) {
	    return $this->WhenFinish_UT() - $this->WhenStart_UT();
	} else {
	    return 0;
	}
    }
    protected function TimeSpan_minutes() {
	$nSpanSeconds = $this->TimeSpan_seconds();
	$fltSpanMinutes = ($nSpanSeconds) / 60;
	return (int)$fltSpanMinutes;
    }
    protected function TimeSpan_hours() {
	$nSpanSeconds = $this->TimeSpan_seconds();
	$fltSpanHours = ($nSpanSeconds) / 3600;
	return (int)$fltSpanHours;
    }
    protected function TimeAdjustments_minutes() {
	$nMinutes = 0;
	$nMinutes += $this->TimePlus();
	$nMinutes -= $this->TimeMinus();
	return $nMinutes;
    }
    protected function TimeAdjustments_hours() {
	$fltHours = $this->TimeAdjustments_minutes() / 60;
	return (int)$fltHours;
    }
    protected function TimeFinal_minutes() {
	return $this->TimeSpan_minutes() + $this->TimeAdjustments_minutes();
    }
    // PUBLIC for ILine to copy from
    public function TimeFinal_hours($nRound = 0.1) {
	$fltHours = $this->TimeFinal_minutes() / 60;
	return ((int)($fltHours / $nRound)) * $nRound;
    }
    /*----
      RETURNS: TRUE iff the final total is based only on hours worked
    */
    public function IsTimeBased() {
	return !$this->CostPlus_isSet();
    }
    /*----
      RETURNS: TRUE if the Rate is based on quantity (typically hours)
      PUBLIC so that Form object can call it at save time
    */
    public function HasQtyRate() {
	if ($this->HasRate()) {
	    $rcRate = $this->RateRecord();
	    return $rcRate->IsQtyBased();
	}
	return FALSE;
    }
    public function RateUsed() {
	if ($this->IsNew() || is_null($this->Value('BillRate'))) {
	    $rcRate = $this->RateRecord();
	    if (is_object($rcRate)) {
		$dlrRate = $rcRate->Value('PerHour');
	    } else {
		$dlrRate = NULL;
	    }
	} else {
	    $dlrRate = $this->Value('BillRate');
	}
	return $dlrRate;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ DATA TABLES ++ //

    protected function ProjectTable($id=NULL) {
        return $this->Engine()->Make('clsWFProjects',$id);
    }
    protected function RateTable($id=NULL) {
        return $this->Engine()->Make('clsWFRates',$id);
    }
    protected function InvoiceTable($id=NULL) {
        return $this->Engine()->Make('clsWFInvoices',$id);
    }
    protected function InvoiceLineTable($id=NULL) {
        return $this->Engine()->Make('clsWFInvcLines',$id);
    }

    // -- DATA TABLES -- //
    // ++ FOREIGN RECORD CACHE ++ //

    private $rcProj;
    private $rcRate;
    private $rcInvc;
    private $rcInvcLine;

    public function ProjectRecord() {
	$idProj = $this->ProjectID();
	if (is_null($idProj)) {
	    return NULL;
	} else {
	    $doLoad = TRUE;
	    if (is_object($this->rcProj)) {
		if ($this->rcProj->KeyValue() == $idProj) {
		    $doLoad = FALSE;
		}
	    }
	    if ($doLoad) {
		$this->rcProj = $this->ProjectTable($idProj);
	    }
	    return $this->rcProj;
	}
    }
    public function RateRecord() {
	if ($this->HasRate()) {
	    $idRate = $this->RateID();
	    if (!is_null($idRate)) {
		$doLoad = TRUE;
		if (is_object($this->rcRate)) {
		    if ($this->rcRate->KeyValue() == $idRate) {
			$doLoad = FALSE;
		    }
		}
		if ($doLoad) {
		    $this->rcRate = $this->RateTable($idRate);
		}
		return $this->rcRate;
	    }
	}
	return NULL;
    }
    public function InvoiceRecord() {
	$idInvc = $this->InvoiceID();
	if (is_null($idInvc)) {
	    return NULL;
	} else {
	    $doLoad = TRUE;
	    if (is_object($this->rcInvc)) {
		if ($this->rcInvc->KeyValue() == $idInvc) {
		    $doLoad = FALSE;
		}
	    }
	    if ($doLoad) {
		$this->rcInvc = $this->InvoiceTable($idInvc);
	    }
	    return $this->rcInvc;
	}
    }
    public function InvcLineObj() {
	$idLine = $this->InvoiceLineID();
	if (is_null($idLine)) {
	    return NULL;
	} else {
	    $doLoad = TRUE;
	    if (is_object($this->rcInvcLine)) {
		if ($this->rcInvcLine->KeyValue() == $idLine) {
		    $doLoad = FALSE;
		}
	    }
	    if ($doLoad) {
		$this->rcInvcLine = $this->InvoiceLineTable($idLine);
	    }
	    return $this->rcInvcLine;
	}
    }

    // -- FOREIGN RECORD CACHE -- //
    // ++ FOREIGN RECORDSET ACCESS ++ //

    // PUBLIC so Form object can call it
    public function ProjectRecords_forChoice() {
	$arArgs = array(
	  'inact'	=> !$this->ProjectRecord()->IsActive(),
	  'always'	=> $this->ProjectID(),				// always show $idProj even if inactive
	);
	return $this->ProjectTable()->Records_forDropDown($arArgs);
    }
    // PUBLIC so Form object can call it
    public function InvoiceLineRecords_forChoice() {
	$tILines = $this->InvoiceLineTable();
	if ($this->HasInvoice()) {
	    $rs = $tILines->GetData('ID_Invc='.$this->InvoiceID());
	    return $rs;
	} else {
	    return NULL;
	}
    }

    // -- FOREIGN RECORDSET ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Assign this Session to an invoice.
      INPUT: $idLine is the specific invoice line to which it should be attached.
    */
    public function Assign_toInvoice(clsWFInvcLine $rcLine) {
	$idInvc = $rcLine->InvoiceID();
	$arUpd = array(
	  'ID_Invc'	=> $idInvc,
	  'ID_InvcLine'	=> $rcLine->KeyValue(),
	  );
	$this->Update($arUpd);
    }

    // -- ACTIONS -- //
    // ++ WEB INTERFACE: CONTROLS ++ //

    // 2015-05-03 these may be obsolete shortly...

    public function Rates_DropDown() {
	$arArgs = array(
	  'default'	=> $this->ValueNz('ID_Rate'),
	  'name'	=> 'ID_Rate',
	  'none'	=> 'none available'
	  );
	$htOut = $this->ProjectRecord()->Rates_DropDown($arArgs);
	return $htOut;
    }
    public function CheckBox($iName='sess',array $iAttr=NULL) {
	$htAttr = NULL;
	if (is_array($iAttr)) {
	    foreach ($iAttr as $key=>$val) {
		if ($val === TRUE) {
		    $htAttr .= ' '.$key;
		} elseif ($val === FALSE) {
		    // add nothing
		} else {
		    $htAttr .= ' '.$key.'="'.$val.'"';
		}
	    }
	}
	$out = '<input type=checkbox name="'.$iName.'['.$this->KeyValue().']"'.$htAttr.'>';
	return $out;
    }
    public function RadioBtn($iName='sess',array $iAttr=NULL) {
	$htAttr = NULL;
	if (is_array($iAttr)) {
	    foreach ($iAttr as $key=>$val) {
		if ($key === TRUE) {
		    $htAttr .= ' '.$key;
		} elseif ($key === FALSE) {
		    // add nothing
		} else {
		    $htAttr .= ' '.$key.'="'.$val.'"';
		}
	    }
	}
	$out = '<input type=radio name="'.$iName.'" value="'.$this->ID.'"'.$htAttr.'>';
	return $out;
    }

    // -- WEB INTERFACE: CONTROLS -- //
    // ++ WEB INTERFACE: TEMPLATES ++ //

    private $tpLine;
    protected function LineTemplate() {
	if (empty($this->tpLine)) {
            $sNow = date('n/j G:i');
            //$ctRates = $this->Rates_DropDown();
	    $sTplt = <<<__END__
<tr>
  <td colspan=2>
    <input type=hidden name="context" value="session">
    [#ID_Proj#]
      new
  </td>
  <td colspan=2></td>
  <td>[#WhenStart#]</td>
  <td>[#WhenFinish#]</td>
  <td>[#Sort#]</td>
  <td>[#TimeAdd#]</td>
  <td>[#TimeSub#]</td>
  <td align=center>-</td>
  <td>[#ID_Rate#]</td>
  <td align=center>-</td>
  <td>[#CostAdd#]</td>
</tr>
<tr>
  <td colspan=12>
    Description: [#Descr#]
    <input type=submit name=btnSaveSess value="Save">
    <input type=submit name=btnFinishSess value="Finish">
  </td>
</tr>
__END__;
	    $this->tpLine = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpLine;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>[#ID#]</td></tr>
  <tr><td align=right><b>Project</b>:</td><td>[#ID_Proj#]</td></tr>
  <tr><td align=right><b>Started</b>:</td><td>[#WhenStart#]</td></tr>
  <tr><td align=right><b>Finished</b>:</td><td>[#WhenFinish#]</td></tr>
  <tr><td align=right><b>Sort</b>:</td><td>[#Sort#]</td></tr>
  <tr><td align=right><b>+Time</b>:</td><td>[#TimeAdd#]</td></tr>
  <tr><td align=right><b>-Time</b>:</td><td>[#TimeSub#]</td></tr>
  [#!TimeTotalRow#]
  <tr><td align=right><b>Rate Type</b>:</td><td>[#ID_Rate#]</td></tr>
  <tr><td align=right><b>Rate Used</b>:</td><td>[#BillRate#]</td></tr>
  <tr><td align=right><b>+Cost</b>:</td><td>[#CostAdd#]</td></tr>
  [#!CostTotalRow#]
  <tr><td align=right><b>Invoice</b>:</td><td>[#ID_Invc#]</td></tr>
  <tr><td align=right><b>Invoice Line</b>:</td><td>[#ID_InvcLine#]</td></tr>

  <tr><td align=right><b>Description</b>:</td><td>[#Descr#]</td></tr>
  <tr><td align=right><b>Notes</b>:</td><td>[#Notes#]</td></tr>
  <tr><td align=right><b>When Entered</b>:</td><td>[#WhenEntered#]</td></tr>
  <tr><td align=right><b>When Edited</b>:</td><td>[#WhenEdited#]</td></tr>
  <tr><td align=right><b>When Figured</b>:</td><td>[#WhenFigured#]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpPage;
    }

    // -- WEB INTERFACE: TEMPLATES -- //
    // ++ WEB INTERFACE: FORMS ++ //

    private $frmLine;
    protected function LineForm() {
	if (is_null($this->frmLine)) {
	    $oForm = new wfcForm_Session_line($this);
	    $this->frmLine = $oForm;
	}
	return $this->frmLine;
    }
    /*----
      HISTORY:
	2011-03-29 adapted from clsPackage to VbzAdminStkItem
	2013-06-08 adapted from VbzCart:VbzAdminStkItem to WorkFerret:clsWFProject
	2013-10-23 changed from private to public (needed when adding new session)
    */
    private $frmPage;
    public function PageForm() {
	if (empty($this->frmPage)) {
	    $oForm = new wfcForm_Session_page($this);
	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }

    // -- WEB INTERFACE: FORMS -- //
    // ++ WEB INTERFACE: DISPLAY ++ //

    /*-----
      NOTES: This ought to be somehow consolidated with AdminPage, so we don't duplicate edit-handling code
    */
    public function DoEditRow(array $arArgs) {

    	$idProj = $arArgs['proj'];
    	$this->ProjectID($idProj);

	$frmEdit = $this->LineForm();
        $frmEdit->ClearValues();  // for now, this is only used to add new rows
	$arCtrls = $frmEdit->RenderControls(TRUE); // always edit
	$oTplt = $this->LineTemplate();

  	// render the template
	$oTplt->VariableValues($arCtrls);
	$out = $oTplt->Render();

	return $out;
    }
    /*-----
      RETURNS: HTML table of all sessions in dataset
      TODO: convert this to use Ferreteria Forms
    */
    public function AdminList(array $iArArgs) {
	$doNew = fcArray::Nz($iArArgs,'doNew');

	if ($this->hasRows() || $doNew) {
	    $doInvc = fcArray::Nz($iArArgs,'doInvc');
	    $doBal = fcArray::Nz($iArArgs,'doBal');
	    $doEdit = $doNew;	// we might eventually edit existing lines, but for now...

	    //$objInvcLines = $this->Engine()->InvcLines();

	    $out = "\n<table>".self::TableHdr($doEdit);

	    $isOdd = TRUE;
	    $yrLast = 0;
	    //$this->Table->BalanceReset();

	    if ($doNew) {
		$out .= $this->DoEditRow($iArArgs);   // add form row
	    }
	    $objCalc = new SessionObjCalc($this);
	    while ($this->NextRow()) {
/* TO DO: if Seq is 0 (or NULL), set it (...if the sorting is appropriate; maybe we should signal that
  with a flag argument to this function)
*/
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$objCalc->CalcRow();
		$objCalc->SumRow();
		$objCalc->Update();
                $ftStop = $objCalc->ftStop;
                $ftWorkCost = $objCalc->dlrWorkCost;
                $ftCostLine = ShowBal($this->Value('CostLine'),$objCalc->dlrCostTot);
                $ftCostBal = ShowBal($this->Value('CostBal'),$objCalc->dlrBalCalc);
                $needCalc = $objCalc->needCalc;

		// CALCULATIONS FOR DISPLAY

		$strWhenStart = $this->Value('WhenStart');
		$dtStart = strtotime($strWhenStart);

		$yrCur = date('Y',$dtStart);
		if ($yrLast != $yrCur) {
		    $yrLast = $yrCur;
		    $out .= "\n<tr style=\"background: #444466; color: #ffffff;\"><td colspan=12><b>$yrCur</b></td></tr>";
		}

		$ftDate = date('m/d',$dtStart);
		$ftStart = fcTime::DefaultDate($strWhenStart,$strWhenStart);

		$ftTimeAdd = $this->Value('TimeAdd');
		$ftTimeSub = $this->Value('TimeSub');
		$ftTimeTot = $this->Value('TimeTotal');
		$sRate = $this->Value('BillRate');
		if (is_null($this->Value('ID_Rate'))) {
		    $ftRate = $sRate;
		} else {
		    $rcRate = $this->RateRecord();
		    if (is_null($sRate)) {
			$sRate = '('.$rcRate->PerUnit().')';
		    }
		    $ftRate = $rcRate->SelfLink($sRate);
		}
		$ftCostAdd = $this->Value('CostAdd');
		$ftCostTot = $this->Value('CostLine');

		$vSort = $this->Value('Sort');
		$ftSort = empty($vSort)?'-':sprintf('%0.1f',$vSort);

		$ftID_link = $this->SelfLink();
		if ($needCalc) {
		    $ftDelta = '<span style="color: red;">&Delta;</span>';
		} else {
		    $ftDelta = '';
		}
		if ($doInvc) {
		    $ftID = '<td>'.$this->CheckBox('sess',array('checked'=>TRUE)).'</td><td>'.$ftDelta.'</td><td>'.$ftID_link.'</td>';
		} else {
		    $ftID = '<td align=right>'.$this->Row['Seq'].'</td><td>'.$ftDelta.'</td><td>'.$ftID_link.'</td>';
		}

		$ftDescr = $this->Value('Descr');
		$txtNotes = $this->Value('Notes');
		if (!is_null($txtNotes)) {
		    $ftDescr .= " <i>$txtNotes</i>";
		}

	      // INVOICE INFO
		$ftInvc = '';

		$idInvc = $this->Value('ID_Invc');
		if ($idInvc <= 0) {
		    $ftInvc .= '<b>no invc</b>';
		} else {
		    $objInvc = $this->InvoiceRecord();
		    if (is_null($objInvc)) {
			$ftInvc .= '<b>unknown invc ID='.$idInvc.'</b>';
		    } else {
			$ftInvc .= '<b>invc</b> '.$objInvc->SelfLink($objInvc->Value('InvcNum'));
		    }
		}

		$ftInvc .= ' | ';

		$idILine = $this->Value('ID_InvcLine');
		if ($idILine <= 0) {
		    $ftInvc .= '<b>no line</b>';
		} else {
		    $objILine = $this->InvcLineObj();
		    if (is_null($objILine)) {
			$ftInvc .= '<b>unknown line ID='.$idILine.'</b>';
		    } else {
			$ftInvc .= '<b>line</b> '.$objILine->SelfLink($objILine->ShortDesc());
		    }
		}
		$htTR = "\n<tr style=\"$wtStyle\">";
		$out .= $htTR
		  ."\n$ftID"
		  ."<td align=center>$ftDate</td>"
		  ."<td align=center>$ftStart</td>"
		  ."<td align=center>$ftStop</td>"
		  ."<td align=center>$ftSort</td>"
		  ."<td align=right>$ftTimeAdd</td>"
		  ."<td align=right>$ftTimeSub</td>"
		  ."<td align=right>$ftTimeTot</td>"
		  ."<td align=right>$ftRate</td>"
		  ."<td align=right>$ftWorkCost</td>"	// t * R
		  ."<td align=right>$ftCostAdd</td>"	// +$
		  ."<td align=right>$ftCostLine</td>"	// $ tot
		  //."<td align=right><small>$ftInvc</small></td>"	// invoice info
		  ."<td align=right>$ftCostBal</td>";	// SUM($)
		if ($doEdit) {
		    $out .= "</tr>$htTR<td colspan=3></td><td colspan=11>$ftDescr</td>";
		} else {
		    $out .= "<td>$ftDescr</td>";
		}
		if (($idInvc > 0) || ($idILine > 0)) {
		    $out .= "</tr>$htTR<td colspan=3></td><td colspan=12>$ftInvc</td>";
		}
		$out .= '</tr>';
	    }
	    $out .= "</table>";
	} else {
	    $out = 'No sessions found.';
	}
	return $out;
    }
    function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$strAct = $vgPage->Arg('do');
	$doEdit = $vgPage->Arg('edit');
	$doAdd = ($strAct == 'new');
	$doCalc = ($strAct == 'recalc');
	$doForm = $doEdit || $doAdd;
	$doSave = $wgRequest->getBool('btnSaveSess');

	$vgPage->UseHTML();

	if ($doSave) {
	    $this->EditSave_page();
	    $this->SelfRedirect();	// clear the form data out of the page reload
	}

	if ($doCalc) {
	    $objCalc = new SessionObjCalc($this);
	    $objCalc->CalcRow();
	    $objCalc->Update();
	    //$arCalc = $this->DoCalc(TRUE,FALSE);
	    $this->Reload();
	}

	// do the header, with edit link if appropriate
	if ($doAdd) {
	    $doEdit = TRUE;	// new row must be edited before it can be created
	    $this->KeyValue($vgPage->Arg('sess'));
	    $strName = 'New session';
	} else {
	    $id = $this->KeyValue();
	    $strDesc = $this->Value('WhenStart');
	    $intSeq = $this->Value('Seq');
	    if ($intSeq) {
		$strDesc .= '.'.$intSeq;
	    }
	    $strName = 'Session '.$id.' - '.$strDesc;
	}
	$objPage = new clsWikiFormatter($vgPage);

	$objSection = new clsWikiSection_std_page($objPage,$strName,2);
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'edit',NULL,NULL,'view'));
	  $objLink->Popup('edit this session');

	$out = $objSection->Render();

	$tInvcs = $this->InvoiceTable();
	$tILines = $this->InvoiceLineTable();

	$isNew = $this->IsNew();
	$frmEdit = $this->PageForm();
	if ($isNew) {
	echo __FILE__.' line '.__LINE__.'<br>';
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	  // custom vars
	  $arCtrls['ID'] = $this->SelfLink();
	  $arCtrls['WhenEntered'] = $this->WhenEntered_text();
	  $arCtrls['WhenEdited'] = $this->WhenEdited_text();
	  $arCtrls['WhenFigured'] = $this->WhenFigured_text();

	if ($doForm) {
	    $out .= "\n<form method=post>";

	    // ++ CUSTOM VARS

	    $arCtrls['!TimeTotalRow'] = '';
	    $arCtrls['!CostTotalRow'] = '';

	    // -- CUSTOM VARS

	    $oTplt->VariableValues($arCtrls);
	    $out .= $oTplt->Render();

	    $out .=
	      '<input type=submit name=btnSaveSess value="Save">'
	      .'</form>';
	} else {

	    // ++ CALCULATIONS

	    $objCalc = new SessionObjCalc($this);
	      $objCalc->CalcRow();
	      $intTimeTotCalc	= $objCalc->intTimeTot;
	      $dlrCalcCost	= $objCalc->dlrCostTot;
	      $needCalc		= $objCalc->needCalc;

	    // // ++ TIME CALCULATIONS

	    $intTimeTot = $this->Value('TimeTotal');
	    $htTimeTot = empty($intTimeTot)?'-':$intTimeTot.' minute'.Pluralize($intTimeTot);
	    if ($intTimeTot==$intTimeTotCalc) {
		// calculated time matches recorded time
		$htTimeTotCalc = '&radic;';
	    } else {
		// recorded time needs recalculating
		$htTimeTotCalc = '&rarr; '.$intTimeTotCalc.' minute'.Pluralize($intTimeTotCalc);
		$needCalc = TRUE;
	    }
	    $htTimeTotRow = "\n<tr><td align=right><b>Total Time</b>:</td><td><i>$htTimeTot</i></td><td>$htTimeTotCalc</td></tr>";
	    $arCtrls['!TimeTotalRow'] = $htTimeTotRow;

	    // // -- TIME CALCULATIONS
	    // // ++ COST CALCULATIONS

	    $mnyCostLine = $this->Value('CostLine');
	    if ($mnyCostLine == $dlrCalcCost) {
		// calculated line cost total matches recorded line cost total
		$htCostTotCalc = '&radic;';
	    } else {
		// recorded line cost total needs recalculating
		$htCostTotCalc = '&rarr; '.$dlrCalcCost;
		$needCalc = TRUE;
	    }
	    $htCostTot = is_null($mnyCostLine)?'-':('$'.$mnyCostLine);
	    $htCostTotRow = "\n<tr><td align=right><b>Total Cost</b>:</td><td>$htCostTot</td><td>$htCostTotCalc</td></tr>";
	    $arCtrls['!CostTotalRow'] = $htTimeTotRow;

	    // // -- COST CALCULATIONS
	    // -- CALCULATIONS

	    $oTplt->VariableValues($arCtrls);
	    $out .= $oTplt->Render();
	}

	$wgOut->AddHTML($out); $out = '';
    }
    /*-----
      ACTION: Save the user's edits to the shipment
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
	2011-12-03 Adapted from VbzCart to WorkFerret.clsWFInvcLine.
	2011-12-27 Copying from clsWFInvcLine to clsWFInvoice, replacing hand-built AdminSave().
	2013-06-16 Copying from clsWFInvoice to wfcSession, but commenting out for now
		Retro note: presumably this refers to the old code. no longer needed.
	2013-10-23 This is needed in order to, you know, save sessions. It also needs
		to be public (but was private); fixed that.
	2015-06-18 AdminSave() split into EditSave_page(), EditSave_line(), and EditFinish_line().
    */
    public function EditSave_page() {
	global $vgOut;

	$oForm = $this->PageForm();
	$out = $oForm->Save();
	$vgOut->AddText($out);
    }
    public function EditSave_line() {
	global $vgOut;

	$oForm = $this->LineForm();
	$out = $oForm->Save();
	$vgOut->AddText($out);
    }
    public function EditFinish_line() {
	global $vgOut;

	$oForm = $this->LineForm();
	$oForm->SetRecordValue('WhenFinish',time());		// for now, must be in NATIVE format
	$out = $oForm->Save();
	$vgOut->AddText($out);
    }

    // -- WEB INTERFACE: DISPLAY -- //
}
abstract class SessionCalc {
// results - single-line
    public $intTimeTot;
    public $ctsWorkCost,$dlrWorkCost;
    public $ctsWorkTot,$dlrCostTot;
    public $needCalc;
    public $ftStop;
// results - multi-line
    public $intSeq;
    public $ctsBalCalc,$dlrBalCalc;

    public function __construct() {
/*
	if (is_null($iSess)) {
	    $this->objSess = NULL;
	} else {
	    $this->objSess = $iSess;
	    $this->arRow = $iSess->Row;
	}
*/
	$this->ctsBalCalc = NULL;
	$this->dlrBalCalc = NULL;
	$this->intSeq = 0;
    }
    abstract protected function Data($iName);
    /*-----
      ACTION: Do calculations using row data passed as array
      USAGE: Call UseRow() first
      RETURNS: public fields
    */
    public function CalcRow() {
	$strWhenStart = $this->Data('WhenStart');
	$strWhenFinish = $this->Data('WhenFinish');
	$intTimeAdd = $this->Data('TimeAdd');
	$intTimeSub = $this->Data('TimeSub');
	$dlrBillRate = $this->Data('BillRate');
	$dlrCostAdd =  $this->Data('CostAdd');
      // PART 1
	//clsModule::LoadFunc('Time_DefaultDate');
	$ftStart = fcTime::DefaultDate($strWhenStart,$strWhenStart);
	if (empty($strWhenFinish)) {
	    $ftStop = '--';
	    $intMinWorked = 0;
	    $rndMinWorked = 0;
	} else {
	    $ftStop = fcTime::DefaultDate($strWhenFinish,$strWhenStart);
	    // ++ these calculations are now duplicated in TimeSpan_min()
	    $intWhenStart = strtotime($strWhenStart);
	    $intWhenStop = strtotime($strWhenFinish);
	    $fltMinWorked = ($intWhenStop - $intWhenStart) / 60;
	    $rndMinWorked = (int)$fltMinWorked;
	    // --
	    $ftStop .= ' <small>('.$rndMinWorked.'m)</small>';
	}
	$this->ftStop = $ftStop;

      // PART 2
	$intTimeTot = $rndMinWorked + $intTimeAdd - $intTimeSub;
	$ctsWorkCost = round((($intTimeTot * $dlrBillRate)/60.0)*100);
	$ctsCostTot = round($ctsWorkCost + (nz($dlrCostAdd) * 100));
	$dlrCostTot = $ctsCostTot/100;

	$needCalc = ($this->Data('CostLine') != $dlrCostTot);
/*
	$arOut['data']['time.tot'] = $intTimeTot;
	$arOut['data']['cost.time'] = $ctsWorkCost/100;
	$arOut['data']['cost.tot'] = $dlrCostTot;
	$arOut['data']['need.calc'] = $needCalc;
*/
      // OUTPUT
	$this->intTimeTot = $intTimeTot;
	$this->ctsWorkCost = $ctsWorkCost;
	$this->dlrWorkCost = $ctsWorkCost/100;
	$this->ctsCostTot = $ctsCostTot;
	$this->dlrCostTot = $dlrCostTot;
	$this->needCalc = $needCalc;
    }
    /*-----
      USAGE: Call CalcRow() first
    */
    public function SumRow() {
	$ctsBalCalc = $this->ctsBalCalc;
	$ctsCostTot = $this->ctsCostTot;
	$ctsBalCalc += $ctsCostTot;
	$dlrBalCalc = $ctsBalCalc/100;
	$this->ctsBalCalc = $ctsBalCalc;
	$this->dlrBalCalc = $dlrBalCalc;

	if ($this->Data('CostBal') != $dlrBalCalc) {
	    $this->needCalc = TRUE;
	}

	$this->intSeq++;
    }
}
// TODO: rename to SessionRecordCalc
class SessionObjCalc extends SessionCalc {

    public function __construct(wfcSession $rcSess) {
	$this->Record($rcSess);
	parent::__construct();
    }
    protected function Data($sName) {
	return $this->Record()->Value($sName);
    }
    private $rcSess;
    protected function Record($rc=NULL) {
	if (!is_null($rc)) {
	    $this->rcSess = $rc;
	}
	return $this->rcSess;
    }
    /*-----
      USAGE: First call CalcRow() and, if needed, SumRow()
	Updates balance if calculated balance is not NULL
    */
    public function Update() {
	$arUpd = array(
	  'TimeTotal'	=> $this->intTimeTot,
	  'CostLine'	=> $this->dlrCostTot
	);
	if (!is_null($this->dlrBalCalc)) {
	    $arUpd['CostBal']	= $this->dlrBalCalc;
	    $arUpd['Seq']	= $this->intSeq;
	}
	$this->Record()->Update($arUpd);
    }
}
class SessionArrCalc extends SessionCalc {
    private $arRow;

    public function __construct(array $iRow) {
	$this->arRow = $iRow;
	parent::__construct();
    }
    protected function Data($iName) {
	return $this->arRow[$iName];
    }
}
