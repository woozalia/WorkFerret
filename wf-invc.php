<?php
/*
  PURPOSE: classes for managing Invoices in WorkFerret
  HISTORY:
    2013-11-08 split off from WorkFerret.main
*/

define('KS_CLASS_PROJECTS','clsWFProjects');

class clsWFInvoices extends clsTable_key_single {
    use ftLinkableTable;

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('invoice');
	  $this->KeyName('ID');
	  $this->ClassSng('clsWFInvoice');
	  $this->ActionKey('invc');
    }

    // -- SETUP -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function ProjectTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_PROJECTS,$id);
    }

    // -- DATA TABLES ACCESS -- //
    // ++ ACTIONS ++ //

    /*-----
      ACTION: Creates a new, empty invoice
      INPUT:
	iProj: ID of project to which this invoice will belong
	iArSess: array of sessions to be included on the invoice (as a list of IDs)
	  Sessions may not be sorted correctly in this array, so Create should sort them
	    before generating invoice lines.
    */
    public function Create($idProj,$sNotes) {
	// get invoice sequencing information and generate the invoice number:
	if (empty($idProj)) {
	    throw new exception('Internal Error: Trying to create invoice for unspecified project.');
	}
	$nSeq = $this->NextInvoiceSeq($idProj);
	$idNext = $this->NextID();
	$rcProj = $this->ProjectTable($idProj);
	$strPfx = $rcProj->Value('InvcPfx');
	$strInvcNum = sprintf('%1$05u-%2$s-%3$04u',$idNext,$strPfx,$nSeq);

	// create the new invoice record
	$db = $this->Engine();
	$arIns = array(
	  'ID_Proj'	=> $idProj,
	  'InvcSeq'	=> $nSeq,
	  'InvcNum'	=> $db->SanitizeAndQuote($strInvcNum),
	  'WhenCreated'	=> 'NOW()',
	  'Notes'	=> $db->SanitizeAndQuote($sNotes)
	  );
	$this->Insert($arIns);
	$id = $db->NewID();
	return $id;
    }

    // -- ACTIONS -- //
    // ++ CALCULATIONS ++ //

    /*-----
      RETURNS: The next available sequence number for the given project
      PUBLIC so Project page can show it
      NOTES:
	* Sorting by "InvcSeq+0" instead of just "InvcSeq" because otherwise InvcSeq is treated as a string.
	  * Perhaps the InvcSeq field should be changed to INTEGER. It was VARCHAR because I thought I might
	    use some alpha characters at some point, like "12A". It's not clear if this is even sortable in SQL.
	    If the ability to insert between integers is needed, then maybe it should be a FLOAT and we could
	    do things like "12.1" rather than "12A"
    */
    public function NextInvoiceSeq($idProj) {
	$rs = $this->GetData('ID_Proj='.$idProj,NULL,'(InvcSeq+0) DESC');
	if ($rs->HasRows()) {
	    $rs->NextRow();
	    $nSeq = $rs->Value('InvcSeq');
	    return $nSeq+1;
	} else {
	    return 1;
	}
    }

    // -- CALCULATIONS -- //
    // ++ RECORDSET ACCESS ++ //

    /*-----
      RETURNS: Dataset of all unsent invoices. Only unsent invoices can be modified; sent invoices should be voided and replaced
	with a new invoice.
    */
    public function GetUnsent($idProj=NULL) {
	$sqlFilt = '(WhenSent IS NULL) AND (WhenVoid IS NULL)';
	if (!is_null($idProj)) {
	    $sqlFilt .= ' AND (ID_Proj='.$idProj.')';
	}
	return $this->GetData($sqlFilt);
    }

    // -- RECORDSET ACCESS -- //
    // ++ WEB UI COMPONENTS ++ //

    public function DropDown(array $iArArgs=NULL) {
	if (isset($iArArgs['where'])) {
	    $sqlFilt = $iArArgs['where'];
	} else {
	    $sqlFilt = NULL;
	}
	$objRows = $this->GetData($sqlFilt,NULL,'InvcNum');
	return $objRows->DropDown($iArArgs);
    }

    // -- WEB UI COMPONENTS -- //

    public function AdminPage() {
	$rs = $this->GetData();
	return $rs->AdminList();
    }
}
class clsWFInvoice extends cDataRecord_MW {
    private $objProj;
    private $idCache;           // ID of invoice for which we have cached data
    private $arLineStats;       // cached invoice data

    // ++ SETUP ++ //

    protected function InitVars() {
            parent::InitVars();
            $this->arLineStats = NULL;
            $this->idCache = NULL;
    }

    // -- SETUP -- //
    // ++ BOILERPLATE AUXILIARY ++ //

    // STUB until we have time to create MW-compatible event logging
    public function CreateEvent(array $arArgs) {
	return NULL;
    }
    public function SelfLink_name() {
	return $this->SelfLink($this->InvoiceNumber());
    }

    // -- BOILERPLATE AUXILIARY -- //
    // ++ DATA FIELD ACCESS ++ //

    protected function InvoiceNumber() {
	return $this->Value('InvcNum');
    }
    public function IsVoid() {
	$dtVoid = $this->Value('WhenVoid');
	return (!is_null($dtVoid));
    }
    // PURPOSE: callback for drop-down lists
    public function Text_forList() {
	return $this->InvoiceNumber();
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA FIELD CALCULATIONS ++ //

    /*----
      TODO: This should either be faster (just retrieve the highest-Seq line) or
	it could be useful by renumbering the Seq field every time. Probably being
	faster would be better.
    */
    protected function NextILineSeq() {
	$nSeqMax = 0;
	$rs = $this->InvoiceLineRecords(FALSE);
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$nSeqCur = $rs->Value('Seq');
		if ($nSeqCur > $nSeqMax) {
		    $nSeqMax = $nSeqCur;
		}
	    }
	}
	return $nSeqMax + 1;
    }

    // -- DATA FIELD CALCULATIONS -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function ProjectTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_PROJECTS);
    }
    protected function SessionTable($id=NULL) {
	return $this->Engine()->Make('wfcSessions');
    }
    protected function InvoiceLineTable() {
	return $this->Engine()->Make('clsWFInvcLines');
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function InvoiceRecord() {
	return $this->InvoiceTable($this->InvoiceID);
    }
    /*----
      RETURNS: recordset of lines for this invoice
      HISTORY:
	2011-06-18 written
    */
    protected function Lines($iSortByDate) {
	throw new exception('Lines() is deprecated; call InvoiceLineRecords().');
    }
    public function InvoiceLineRecords($bDateSort) {
	$tInvc = $this->InvoiceLineTable();
	if ($bDateSort) {
	    $sqlSort = 'LineDate';
	} else {
	    $sqlSort = 'Seq,LineDate';
	}
	return $tInvc->GetData('ID_Invc='.$this->KeyValue(),NULL,$sqlSort);
    }
    public function ProjectRecord() {
	$doLoad = FALSE;
	$idProj = $this->Value('ID_Proj');
	if (empty($this->objProj)) {
	    $doLoad = TRUE;
	} elseif ($this->objProj->KeyValue() != $idProj) {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $this->objProj = $this->objDB->Projects()->GetItem($idProj);
	}
	return $this->objProj;
    }
    public function SessionRecords() {
	$tSess = $this->SessionTable();
	$rs = $tSess->GetData('ID_Invc='.$this->KeyValue());
	return $rs;
    }
    protected function ProjectRecords() {
	return $this->ProjectTable()->Records_forDropDown();
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ FORM BUILDING ++ //

    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-01-26 adapted from VbzAdminTitle
	2011-12-03 adapted for WorkFerret
	2011-12-28 adapting for clsWFInvoice
    */
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {

	    $oForm = new fcForm_DB($this);
	      $oField = new fcFormField_Num($oForm,'ID_Proj');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
                $oCtrl->Records($this->ProjectRecords());

	      $oField = new fcFormField_Text($oForm,'InvcSeq');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>3));

	      $oField = new fcFormField_Text($oForm,'InvcNum');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));

	      $oField = new fcFormField_Num($oForm,'TotalAmt');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));

	      $oField = new fcFormField_Time($oForm,'WhenCreated');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>10));
		  $oCtrl->Editable(FALSE);	// make control read-only

	      $oField = new fcFormField_Time($oForm,'WhenEdited');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>10));
		  $oCtrl->Editable(FALSE);	// make control read-only

	      $oField = new fcFormField_Time($oForm,'WhenSent');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>10));
		//$oField->Format('n/j G:i');

	      $oField = new fcFormField_Time($oForm,'WhenPaid');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>10));

	      $oField = new fcFormField_Time($oForm,'WhenVoid');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>15));

	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>50));

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
  <tr><td align=right><b>Project</b>:</td><td>[#ID_Proj#]</td></tr>
  <tr><td align=right><b>Sequence</b>:</td><td>[#InvcSeq#]</td></tr>
  <tr><td align=right><b>Number</b>:</td><td>[#InvcNum#]</td></tr>
  <tr><td align=right><b>Total</b>:</td><td>[#TotalAmt#]</td></tr>
  <tr><td align=right><b>Created</b>:</td><td>[#WhenCreated#]</td></tr>
  <tr><td align=right><b>Edited</b>:</td><td>[#WhenEdited#]</td></tr>
  <tr><td align=right><b>Sent</b>:</td><td>[#WhenSent#]</td></tr>
  <tr><td align=right><b>Paid</b>:</td><td>[#WhenPaid#]</td></tr>
  <tr><td align=right><b>Voided</b>:</td><td>[#WhenVoid#] [#!DoVoid#]</td></tr>
  <tr><td align=right><b>Notes</b>:</td><td>[#Notes#]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpPage;
    }

    // -- FORM BUILDING -- //
    // ++ WEB UI ++ //

    public function ListLines($bDoRenum) {
	$rs = $this->InvoiceLineRecords($bDoRenum);
	return $rs->AdminList($bDoRenum);
    }
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;

	$doSave = $wgRequest->getBool('btnSave');
	$strAction = $vgPage->Arg('do');
	$doVoid = ($strAction == 'void');
	$doRenum = ($strAction == 'ren');
	$doEdit = ($strAction == 'edit');
	$doForm = $doEdit;

	$vgPage->UseHTML();

	if ($doVoid) {
	    $arUpd = array(
	      'WhenVoid'	=> 'NOW()',
	      );
	    $this->Update($arUpd);
	    $this->SelfRedirect();
	}

	if ($doSave) {
	    $this->AdminSave();
	}

	$id = $this->KeyValue();
	$strName = 'Invoice '.$this->Value('InvcNum');

	$objPage = new clsWikiFormatter($vgPage);

	$objSection = new clsWikiSection_std_page($objPage,$strName,2);
	//$objSection->PageKeys(array('page','id','show.invoiced')); // add "show.invoiced" to the list of page-identifiers

	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'edit','do','edit','view'));
/*
If we ever want to have more "do" options, then use this code instead:
	$objLink = $objSection->AddLink(new clsWikiSection_section('do'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'view','do'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'edit','do'));
*/

	$out = $objSection->Render();

	// calculate form customizations

	if (!$this->IsVoid() && !$doEdit) {
	    $sPopup = 'immediately VOID this invoice';
	    $arLink['do'] = 'void';
	    $htVoidNow = ' ['.$this->SelfLink('void now',$sPopup,$arLink).']';
	} else {
	    $htVoidNow = NULL;
	}

	// ++ RENDER THE FORM

	if ($doForm) {
	    $out .= '<form method=post>';
	}

	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}

	// -- RENDER THE FORM

	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	  // custom vars
	  $arCtrls['ID'] = $this->SelfLink();
	  $arCtrls['!DoVoid'] = $htVoidNow;

	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->Render();

	// FORM FOOTER

	if ($doForm) {
	    $out .= '<input type=submit name=btnSave value="Save">';
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out); $out = '';
	// invoice lines
	$wgOut->AddHTML($this->ListLines($doRenum));
    }
    /*-----
      ACTION: Save the user's edits to the shipment
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
	2011-12-03 Adapted from VbzCart to WorkFerret.clsWFInvcLine.
	2011-12-27 Copying from clsWFInvcLine to clsWFInvoice, replacing hand-built AdminSave().
    */
    private function AdminSave() {
	global $vgOut;

	$oForm = $this->PageForm();
	$oForm->ClearValues();
	$out = $oForm->Save();

	//$out = $this->PageForm()->Save();
	$vgOut->AddText($out);
    }

    public function AdminList() {
	global $vgPage;

	if ($this->hasRows()) {
	    $vgPage->UseHTML();

	    $doShowVoid = $vgPage->Arg('vvi');

	    $objPage = new clsWikiFormatter($vgPage);
	    $objSection = new clsWikiSection_std_page($objPage,'Invoices',3);
	    $objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'vvi',NULL,'voided'));
	      $objLink->Popup('view voided invoices');

	    $out = $objSection->Render();

	    $out .= <<<__END__
<table>
  <tr>
    <th>ID</th>
    <th>Prj</th>
    <th>Number</th>
    <th>Total</th>
    <th>Created</th>
    <th>Edited</th>
    <th>Sent</th>
    <th>Paid</th>
    <th>Voided</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$doShow = TRUE;
		$isVoid = $this->IsVoid();
		if ($isVoid) {
		    $doShow = $doShowVoid;
		}

		if ($doShow) {
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		    $isOdd = !$isOdd;

		    $wtStyle .= ($isVoid?' text-decoration:line-through;':'');

		    $ftID = $this->SelfLink();
		    $objProj = $this->ProjectRecord();
		    $ftProj = $objProj->SelfLink($objProj->Value('InvcPfx'),$objProj->Value('Name'));
		    $ftNum = $this->Value('InvcNum');
		    $ftTot = $this->Value('TotalAmt');
		    $ftWhenCrea = $this->Value('WhenCreated');
		    $ftWhenEdit = $this->Value('WhenEdited');
		    $ftWhenSent = $this->Value('WhenSent');
		    $ftWhenPaid = $this->Value('WhenPaid');
		    $ftWhenVoid = $this->Value('WhenVoid');
		    $ftNotes = $this->Value('Notes');

		    $out .= <<<__END__
  <tr style="$wtStyle">
    <td>$ftID</td>
    <td>$ftProj</td>
    <td>$ftNum</td>
    <td align=right>$ftTot</td>
    <td>$ftWhenCrea</td>
    <td>$ftWhenEdit</td>
    <td>$ftWhenSent</td>
    <td>$ftWhenPaid</td>
    <td>$ftWhenVoid</td>
    <td>$ftNotes</td>
  </tr>
__END__;
		}
	    }
	    $out .= "\n</table>";
	    return $out;
	} else {
	    return 'No invoices found.';
	}
    }
    /*-----
      RETURNS: HTML for drop-down list of invoices in current data set
	Includes "NONE" as a choice. (should this be an option in $arArgs?)
    */
    public function DropDown(array $arArgs=NULL) {
	$strName = fcArray::Nz($arArgs,'ctrl.name',$this->Table()->Name());
	$strNone = fcArray::Nz($arArgs,'none','none found');
	$sqlNone = fcArray::Nz($arArgs,'none.sql','NULL');	// SQL to use for 'none'
	$intDeflt = fcArray::Nz($arArgs,'default',0);
	if ($this->hasRows()) {
	    $out = "\n".'<select name="'.$strName.'">';
	    // "NONE"
	    if ($intDeflt == 0) {
		$htSelect = " selected";
	    } else {
		$htSelect = '';
	    }
	    $out .= "\n".'  <option'.$htSelect.' value="'.$sqlNone.'">NONE</option>';
	    // actual invoices
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		if ($id == $intDeflt) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= "\n".'  <option'.$htSelect.' value="'.$id.'">'.$this->Value('InvcNum')."</option>";
	    }
	    $out .= "\n</select>\n";
	    return $out;
	} else {
	    return $strNone;
	}
    }

    // -- WEB UI -- //
    // ++ CALCULATIONS ++ //

    /*----
      ACTION: Calculates a few stats about the invoice's lines
	* renumbers those lines, preserving their existing order
	* updates each line's balance
      HISTORY:
	2011-06-19 written
	2011-12-04 fixed update -- was calling table update instead of row update
    */
    private function MakeLineStats() {
        $id = $this->Value('ID');
        if ($this->idCache != $id) {
                $rsLines = $this->Lines(TRUE);	// TRUE = sort by date (not sure if that's best -- prevents Seq override)
                $intSeq = 0;
                $dlrBal = 0;
                while ($rsLines->NextRow()) {
                        $intSeq++;
                        $dlrLine = $rsLines->Value('CostLine');
                        $dlrBal += $dlrLine;
                        $dlrBalOld = $rsLines->Value('CostBal');
                        $arUpd = NULL;
                        if ($rsLines->Value('Seq') != $intSeq) {
                                $arUpd['Seq'] = $intSeq;
                        }
                        if ($dlrBalOld != $dlrBal) {
                                $arUpd['CostBal'] = $dlrBal;
                        }
                        if (is_array($arUpd)) {
                                $rsLines->Update($arUpd);
                        }
                }
                $this->arLineStats['count'] = $intSeq;
                $this->arLineStats['bal'] = $dlrBal;
                $this->idCache = $id;
        }
    }
    /*----
        RETURNS: number of lines in this invoice
        ACTIONS: runs MakeLineStats(), which corrects balances and sequence
        HISTORY:
                2011-06-18 written
    */
    public function LineCount() {
        $this->MakeLineStats();
        return $this->arLineStats['count'];
    }
    /*----
        RETURNS: balance of all lines currently assigned to this invoice
        ACTIONS: runs MakeLineStats(), which corrects balances and sequence
        HISTORY:
                2011-06-18 written
    */
    public function LineBalance() {
        $this->MakeLineStats();
        return $this->arLineStats['bal'];
    }

    // -- CALCULATIONS -- //
    // ++ ACTIONS ++ //

    protected function AddNewLine(clsWFInvcLine $rcLine) {
	// insert the given data

    }
    /*----
      INPUT:
	$arSess: array of session data, unsorted
      HISTORY:
	2015-05-05 rewriting from scratch
    */
    public function AddLines(array $arSess) {

	// collect sessions in an array so we can sort them

	$tSess = $this->SessionTable();
	foreach ($arSess as $idSess => $val) {
	    $rcSess = $tSess->GetItem($idSess);
	    $arSessRecs[$rcSess->SortKey()] = $rcSess->Values();
	}

	// do the sort

	ksort($arSessRecs);

	// go through them in order, doing additional calculations, and add them to the invoice

	$nSeq = $this->NextILineSeq();
	$dlrBal = 0;
	$idInvc = $this->KeyValue();
	$rcSess = $this->SessionTable()->SpawnItem();
	$rcILine = $this->InvoiceLineTable()->SpawnItem();
	foreach ($arSessRecs as $sSort => $arSess) {
	    $rcSess->Values($arSess);
	    $dlrLine = $rcSess->Cost_calc();
	    $dlrBal += $dlrLine;
	    $arSess['CostLine'] = $dlrLine;
	    $arSess['CostBal'] = $dlrBal;
	    $arSess['TimeTotal'] = $rcSess->TimeFinal_hours();
	    $rcSess->Values($arSess);			// update object from modified fields
	      $rcILine->CopySession($rcSess);
	      $rcILine->InvoiceID($idInvc);
	      $rcILine->Seq($nSeq);
	      $rcILine->Create();			// write the ILine data to a new record
	    $rcSess->Assign_toInvoice($rcILine);	// assign the Session record to that ILine
	    $nSeq++;
	}

	// update the invoice

	$arUpd = array(
	  'TotalAmt'	=> $dlrBal,
	  'ID_Debit'	=> 'NULL'       // not using this anymore
	  );

	  $this->Update($arUpd);
    }
    /*-----
      INPUT:
	$iArSess: array of session data, unsorted
      VERSION: consolidates by date+rate
	This implementation is probably buggy, however.
      HISTORY:
        2011-06-15 significant rewrite to fix transcription bug
	2014-03-26 moved most of this process into the Invoice Line class, to fix the bug for real.
    */
    public function AddLines_OLD(array $iArSess) {

        // copy the sessions into an array for sorting:
	$idxLine = 0;
        $intBillAmt = 0;
	$arSess = NULL;
	foreach ($iArSess as $idSess=>$val) {
	    $rcSess = $this->Engine()->Sessions()->GetItem($idSess);
	    $strSort = $rcSess->SortKey().'.'.$idSess;         // date plus sorting index plus Session ID
	    $arSess[$strSort] = $rcSess->Values();
            $intBillAmt += (int)($rcSess->Value('CostLine') * 100);
	}

	$dlrAddedAmt = $intBillAmt/100;
	$dlrInvcAmtOld = $this->LineBalance();
	$dlrInvcAmtNew = $dlrInvcAmtOld + $dlrAddedAmt;	// add new lines to existing invc total

	// update the invoice
	$arUpd = array(
	  'TotalAmt'	=> $dlrInvcAmtNew,
	  'ID_Debit'	=> 'NULL'       // not using this anymore
	  );
	$this->Update($arUpd);

	if (!is_null($arSess)) {
	    // sort lines by sort key (defined by session object SortKey(): date + seq)
	    ksort($arSess);

        // go through sessions and add invoice lines as appropriate
        // - setup for iteration:

	    // get the number of lines already in the invoice -- numbering starts there
	    // when these change, an invoice line is triggered:
	    $idRateLast = NULL;
	    $strDateLast = NULL;

	    // invoice line setup
	    $rcILine = $this->InvoiceLineTable()->SpawnItem();
	    $rcILine->InvoiceID($this->KeyValue());
	    $rcILine->InvoiceStart();
	    $rcILine->AddSessions($arSess);
        }
    }

    // -- ACTIONS -- //
}
