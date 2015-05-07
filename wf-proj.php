<?php
/*
  PURPOSE: classes for managing Projects in WorkFerret
  HISTORY:
    2013-11-08 split off from WorkFerret.main
*/

class clsWFProjects extends clsTable_key_single {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('project');
	  $this->KeyName('ID');
	  $this->ClassSng('clsWFProject');
	  $this->ActionKey('proj');
    }
    /*----
      HISTORY:
	2011-04-21 renamed from ListPage() -> AdminPage()
    */
    public function AdminPage() {
	global $wgOut, $wgRequest;
	global $vgPage;

	$strAction = $vgPage->Arg('do');
	$doAdd = ($strAction == 'add');

	$doAddSave = $wgRequest->GetBool('btnSave');

	if ($doAddSave) {
	    $strName = $wgRequest->GetText('name');
	    $strPfx = $wgRequest->GetText('pfx');
	    $arIns = array(
	      'ID_Parent'	=> 'NULL',
	      'Name'		=> SQLValue($strName),
	      'InvcPfx'		=> SQLValue($strPfx)
	      );
	    $this->Insert($arIns);
	}

	$vgPage->UseHTML();

/* NEW CLASSES - action needs implementation
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection_std_page($objPage,'Projects');
	$objSection->AddLink_local(new clsWikiSectionLink_keyed(array('do'=>'add'),'add'));
	$out = $objSection->Render();
*/

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection_std_page($objPage,'Projects',2);

	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'add','do','add'));
	  $objLink->Popup('add a new project');


	$out = $objSection->Render();

	$wgOut->AddHTML($out);

	$wgOut->AddHTML($this->DrawTree());

	if ($doAdd) {
	    $out = '<h3>new project</h3>'
	      //.$objSection->FormOpen()	// TODO: fix this
	      .'<form method=post>'
	      .'Prefix: <input name=pfx size=3> Name: <input name=name size=10>'
	      .'<input type=submit name=btnSave value="Add"><input type=submit name=btnCancel value="Discard"></form>'
	      ;
	    $wgOut->AddHTML($out);
	}
    }
    public function RootNode() {
	$objRoot = $this->SpawnItem();
	$objRoot->ClearValue('ID');
	return $objRoot;
    }
    public function DrawTree() {
	$objRoot = $this->RootNode();
	$out = $objRoot->DrawTree();
	return $out;
    }
    public function Records_forDropDown(array $arArgs=NULL) {
        $idAlways = clsArray::Nz($arArgs,'current');    // show this project even if it's inactive
        $doInact = clsArray::Nz($arArgs,'inact',FALSE); // show inactive projects?
	if (!$doInact) {
	    $sqlFilt = 'isActive';
	    if (!is_null($idAlways)) {
		$sqlFilt .= ' OR (ID='.$idAlways.')';
	    }
	} else {
	    $sqlFilt = NULL;
	}
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }
    public function DropDown(array $arArgs=NULL) {
        $rs = $this->Records_forDropDown($arArgs);
	return $rs->DropDown($arArgs);
    }
}

/*
  HISTORY:
    2011-10-19 moved HasParent() from clsWFProjects to clsWFProject
*/
//class clsWFProject extends clsRecs_key_single {
class clsWFProject extends cDataRecord_MW {

    // ++ BOILERPLATE ++ //
/*
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminRedirect($this,$iarArgs);
    }
    public function StartEvent(array $iArgs) { }	// stubbed off for now
    public function FinishEvent(array $iArgs=NULL) { }	// stubbed off for now
*/
    // -- BOILERPLATE -- //
    // ++ STATUS ACCESS ++ //

    public function IsActive() {
	if ($this->IsNew()) {
	    return TRUE;
	} else {
	    return BitToBool($this->Value('isActive'));
	}
    }
    public function HasParent() {
	return !is_null($this->Value('ID_Parent'));
    }
    public function HasRateDefault() {
	return !is_null($this->RateID());
    }

    // -- STATUS ACCESS -- //
    // ++ FIELDS ACCESS ++ //

    protected function ParentID() {
        return $this->Value('ID_Parent');
    }
    protected function RateID() {
	return $this->Value('ID_Rate');
    }
    protected function NameString() {
	return $this->Value('Name');
    }
    /*----
      RETURNS: text description of the default rate for this project
    */
    protected function RateDescr($htNone='<i>(none)</i>') {
	if ($this->HasRateDefault()) {
	    return $this->DefaultRateRecord()->Descr();
	} else {
	    return $htNone;
	}
    }
    public function Text_forList() {
	return $this->NameString();
    }

    // -- FIELDS ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function ParentObj() {
	$idParent = $this->ParentID();
	if (is_null($idParent)) {
	    return NULL;
	} else {
	    return $this->Table()->GetItem($idParent);
	}
    }
    public function Rates() {
        throw new exception('Rates() is deprecated; call RateRecords() instead.');
    }
    public function RateRecords() {
	if ($this->IsNew()) {
	    $rs = NULL;
	} else {
	    $tX = $this->Engine()->Rates();	// Projs X Rates table
	    $rs = $tX->Rates_forProj($this->KeyValue());
	}
	return $rs;
    }
    /*----
      RETURNS: Record for the default rate for this project
    */
    protected function DefaultRateRecord() {
	$idRate = $this->RateID();
	$rcRate = $this->RateTable($idRate);
	return $rcRate;
    }
    public function InvoiceRecords() {
	return $this->InvoiceTable()->GetData('ID_Proj='.$this->KeyValue());
    }
    protected function ChildRecords() {
	$id = $this->KeyValue();
	if (is_null($id)) {
	    $sql = 'ID_Parent IS NULL';
	} else {
	    $sql = 'ID_Parent='.$id;
	}

	return $this->Table()->GetData($sql,NULL,'Name');
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function RateTable($id=NULL) {
	return $this->Engine()->Make('clsWFRates',$id);
    }
    protected function SessionTable($id=NULL) {
	return $this->Engine()->Make('clsWFSessions',$id);
    }
    protected function InvoiceTable($id=NULL) {
	return $this->Engine()->Make('clsWFInvoices',$id);
    }

    // -- DATA TABLES ACCESS -- //
    // ++ UI: ACTIONS ++ //

    /*-----
      ACTION: Save the user's edits to the record
    */
    private function AdminSave($iNotes) {
	global $vgOut;

	$out = $this->objForm->Save($iNotes);
	$vgOut->AddText($out);
    }

    // -- UI: ACTIONS -- //
    // ++ UI: CONTROLS ++ //

    public function Rates_DropDown(array $iarArgs=NULL) {
	$rs = $this->RateRecords();
	return $rs->DropDown($iarArgs);
    }
    /*----
      VERSION: straight HTML ul-list
    */
    public function DrawTree($nLevel=0) {
	$rs = $this->ChildRecords();
	if ($rs->HasRows()) {
	    $sIndent = str_repeat("\t",$nLevel);		// make the raw HTML more readable
	    $out = "\n$sIndent<ul>";
	    $nLevel++;
	    $sIndent = str_repeat("\t",$nLevel);		// make the raw HTML more readable
	    while ($rs->NextRow()) {
		$sName = $rs->NameString();
		$sTwig = $rs->IsActive()?"<b>$sName</b>":"<i>$sName</i>";	// italicize if inactive
		$ftTwig = $rs->AdminLink($sTwig,'edit '.$sTwig);
		$out .=
		  "\n<li>$sIndent$ftTwig</li>"
		  .$rs->DrawTree($nLevel);
	    }
	    $nLevel--;
	    $sIndent = str_repeat("\t",$nLevel);		// make the raw HTML more readable
	    $out .= "\n$sIndent</ul>";
	 } else {
	    if ($nLevel == 0) {
		$out = "\nNo projects created yet.";
	    } else {
		$out = '';
	    }
	 }
	 return $out;
    }
    public function DropDown(array $iArArgs=NULL) {
	$strName = nz($iArArgs['name'],'proj');
	$strNone = nz($iArArgs['none'],'none found');
	$useNone = isset($iArArgs['incl none']);
	$intDeflt = (int)nz($iArArgs['default'],0);

	if ($this->hasRows()) {
	    $out = "\n".'<select name="'.$strName.'">';
	    if ($useNone) {
		$htSelect = empty($intDeflt)?' selected':'';
		$out .= "\n  <option$htSelect value=0>- global -</option>\n";
	    }

	    while ($this->NextRow()) {
		$id = $this->KeyValue();

		if ($id == $intDeflt) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$strName = '';
		if ($this->HasParent()) {
		    $strName = $this->ParentObj()->Value('Name').'.';
		}
		$strName .= $this->Value('Name');
		if (!$this->IsActive()) {
		    $strName = '('.$strName.')';	// indicate inactive projects
		}
		$out .= "\n".'  <option'.$htSelect.' value="'.$id.'">'.$strName."</option>\n";
	    }
	    $out .= "\n</select>\n";
	    return $out;
	} else {
	    return $strNone;	// only one item in list, so no choice to make
	}
    }

    // -- UI: CONTROLS -- //
    // ++ UI: DISPLAY ++ //

    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage;

	if (!is_object($vgPage)) {
	    throw new exception('vgPage object is not set.');
	}

	$vgPage->UseHTML();
	if ($wgRequest->getBool('btnSaveProj')) {
	    $this->BuildEditForm();
	    $this->AdminSave($wgRequest->GetText('EvNotes'));
	}

	$strAction = $vgPage->Arg('do');
//	$doEdit = ($strAction == 'edit');
	$doEdit = $vgPage->Arg('edit');
	$strCtxt = $vgPage->Arg('context');
	$doAdd = (($strAction == 'new') && (empty($strCtxt)));
	$doShowInvcd = $vgPage->Arg('invcd');

	// do the header, with edit link if appropriate
	if ($doAdd) {
	    $doEdit = TRUE;	// new row must be edited before it can be created
	    $this->ID_Proj = $vgPage->Arg('proj');
	    $strName = 'New project';
	    $id = NULL;
	} else {
	    $id = $this->KeyValue();
	    $strName = 'Project '.$id.' - '.$this->Value('Name');
	}
	$objPage = new clsWikiFormatter($vgPage);

	$objSection = new clsWikiSection_std_page($objPage,$strName,2);
//	$objSection->PageKeys(array('page','id')); // add "show.invoiced" to the list of page-identifiers

	if (!$doAdd) {
	    $objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'edit'));
	      $objLink->Popup('edit this session');
	}

	$out = $objSection->Render();

	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);

	//$strName = $this->Value('Name');
	//$strIPfx = $this->Value('InvcPfx');
	if ($this->HasParent()) {
	    $rcParent = $this->ParentObj();
	    $htParent = $rcParent->AdminLink($objParent->Value('Name'));
	} else {
	    $htParent = '<i>none</i>';
	}
	if (is_null($id)) {
	    $htNextInvc = NULL;
	} else {
	    $nNextInvc = $this->InvoiceTable()->NextInvoiceSeq($id);
	    $htNextInvc = "<tr><td align=right><b>Invc seq</b>:</td><td>$nNextInvc</td></tr>";
	}

	$arCtrls['ID'] = $this->AdminLink();
	$arCtrls['ID_Parent'] = $htParent;
	$arCtrls['!NextInvc'] = $htNextInvc;

	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->Render();

	/* 2015-05-04 old version
	if ($doEdit) {
	    $objForm = $this->PageForm();

	    $out .= "\n<form method=post>";

	    $htName = $objForm->Render('Name');
	    $htRate = $this->Rates_DropDown(array('name'=>'ID_Rate'));
	    $htCode = $objForm->Render('InvcPfx');
	    $htActv = $objForm->Render('isActive');
	    $htPNotes = $objForm->Render('Notes');
	    $htActvWidget = $htActv.'Active';
	} else {
	    $htName = $strName;
	    $htRate = $this->RateDescr();
	    $htCode = $strIPfx;
	    $isActv = $this->IsActive();
	    $htActvWidget = $isActv?'<font color=green>active</font>':'<font color=grey>inactive</font>';
	    $sPNotes = $this->Value('Notes');
	    if (empty($sPNotes)) {
		$htPNotes = '<i>none</i>';
	    } else {
		$htPNotes = htmlspecialchars($sPNotes);
	    }
	}
	$htID = $this->AdminLink();
	if (is_null($this->Value('ID_Parent'))) {
	    $htParent = '<i>none</i>';
	} else {
	    $objParent = $this->ParentObj();
	    $htParent = $objParent->AdminLink($objParent->Value('Name'));
	}

	$out .= '<table>';
	$out .= "\n<tr><td class='mw-label'><b>ID</b>:</td><td>$htID $htActvWidget</td></tr>";
	$out .= "\n<tr><td class='mw-label'><b>Name</b>:</td><td>$htName</td></tr>";
	$out .= "\n<tr><td class='mw-label'><b>Default Rate</b>:</td><td>$htRate</td></tr>";
	$out .= "\n<tr><td class='mw-label'><b>Invc Prefix</b>:</td><td>$htCode</td></tr>";
	$out .= $htNextInvc;
	$out .= "\n<tr><td class='mw-label'><b>Parent</b>:</td><td>$htParent</td></tr>";
	$out .= "\n<tr><td class='mw-label'><b>Project Notes</b>:</td><td>$htPNotes</td></tr>";
	if (isset($htENotes)) {
	    $out .= "\n<tr><td class='mw-label'><b>Edit Notes</b>:</td><td>$htENotes</td></tr>";
	}
	$out .= '</table>';
	if ($doEdit) {
	    $out .= '<input type=submit name="btnSaveProj" value="Save">';
	    $out .= '</form>';
	}*/

	$wgOut->AddHTML($out); $out = '';

	// sessions for this project
	$wgOut->AddWikiText($this->ListSessions($doShowInvcd),TRUE);
	// invoices for this project
	$wgOut->AddHTML($this->ListInvoices(),TRUE);
    }
    public function ListSessions($iShowInvcd) {
	global $wgOut;
	global $vgPage;

	$this->HandleSessForm();

	$idProj = $this->KeyValue();

	$objSQL = new clsSQLFilt('AND');
	$objSQL->AddCond('ID_Proj='.$idProj);

	// TODO: prevent ID_InvcLine from being saved as "0" when "NONE" is selected
	if ($iShowInvcd) {
	    $objSQL->AddCond('(ID_InvcLine IS NOT NULL) AND (ID_InvcLine != 0)');
	} else {
	    $objSQL->AddCond('(ID_InvcLine IS NULL) OR (ID_InvcLine=0)');
	}
	$sqlFilt = $objSQL->RenderFilter();

	$tSess = $this->Engine()->Sessions();
	$rsSess = $tSess->GetData($sqlFilt,NULL,'WhenStart,IFNULL(Sort,1000),Seq');

// set up formatting and emit section header:
	$strCtxt = $vgPage->Arg('context');
	if ($strCtxt == 'session') {
	    $strAction = $vgPage->Arg('do');
	    $doInvc = ($strAction == 'invc');
	    $doNew = ($strAction == 'new');
	} else {
	    $doInvc = FALSE;
	    $doNew = FALSE;
	}

      // common arguments array
	$arArgs = array(
	  'doNew'	=> $doNew,
	  'doInvc'	=> $doInvc,
	  'doBal'	=> ($vgPage->Arg('do') == 'bal'),
	  'proj'	=> $this->KeyValue(),
	  'rate'	=> $this->RateID()	// default rate
	  );
      // show "Sessions" subheader
	$out = clsWFSession::SectionHdr($arArgs);
	$doForm = $arArgs['doForm'];
      // show the list of sessions
	$out .= $rsSess->AdminList($arArgs);
	if ($doForm) {
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out);

	return NULL;
    }
    public function ListInvoices() {
	$objTbl = $this->Engine()->Invoices();
	$sqlFilt = 'ID_Proj='.$this->KeyValue();
	$objRows = $objTbl->GetData($sqlFilt,NULL,'InvcNum DESC');
	return $objRows->AdminList();
    }

    // -- UI: DISPLAY -- //
    // ++ UI: FORM CREATION ++ //

    /*----
      HISTORY:
	2011-03-29 adapted from clsPackage to VbzAdminStkItem
	2013-06-08 adapted from VbzCart:VbzAdminStkItem to WorkFerret:clsWFProject
    */
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	    $oForm = new fcForm_DB($this->Table()->ActionKey(),$this);

	      $oField = new fcFormField_Text($oForm,'Name');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>20));

              $oField = new fcFormField_Num($oForm,'ID_Parent');
		$oCtrl = new fcFormControl_HTML_DropDown($oForm,$oField,array());
		// TODO: set $oCtrl->Records() to recordset of all Projects except current one

              $oField = new fcFormField_Num($oForm,'ID_Rate');
		$oCtrl = new fcFormControl_HTML_DropDown($oForm,$oField,array());
                $oCtrl->Records($this->RateRecords());
                $oCtrl->NoDataString('none available');

              $oField = new fcFormField_Text($oForm,'InvcPfx');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>4));

	      $oField = new fcFormField_Bit($oForm,'isActive');
		$oCtrl = new fcFormControl_HTML_CheckBox($oForm,$oField,array());
		  $oCtrl->DisplayStrings(
		    '<font color=green>active</font>',
		    '<font color=grey>inactive</font>'
		    );

              $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oForm,$oField,array('rows'=>4,'cols'=>60));

	    $this->frmPage = $oForm;
	}
	return $this->frmPage;

	/* forms v1

	global $vgOut;
	// create fields & controls

	if (empty($this->objForm)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsFieldNum	('ID_Rate'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('InvcPfx'),	new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsFieldBool('isActive'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea(array('height'=>3,'width'=>40)));

	    $this->objForm = $objForm;
	} */
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr><td class='mw-label'><b>ID</b>:</td><td>[#ID#] [#isActive#]</td></tr>
  <tr><td class='mw-label'><b>Name</b>:</td><td>[#Name#]</td></tr>
  <tr><td class='mw-label'><b>Default Rate</b>:</td><td>[#ID_Rate#]</td></tr>
  <tr><td class='mw-label'><b>Invc Prefix</b>:</td><td>[#InvcPfx#]</td></tr>
  [#!NextInvc#]
  <tr><td class='mw-label'><b>Parent</b>:</td><td>[#ID_Parent#]</td></tr>
  <tr><td class='mw-label'><b>Project Notes</b>:</td><td>[#Notes#]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpPage;
    }

    // -- UI: FORM CREATION -- //
    // ++ UI: FORM PROCESSING ++ //

    /*-----
      ACTION: Handles input from session-related form submissions
      HISTORY:
	2011-11-22 commented out check for context == 'session'; let's make the action
	  entirely dependent on the name of the button (plus hidden field values if needed)
    */
    public function HandleSessForm() {
	global $wgOut, $wgRequest;
	global $vgPage;

	// don't do anything unless we're in a session context
	if ($wgRequest->getBool('context')) {
	    $strCtxt = $wgRequest->getText('context');
	} else {
	    $strCtxt = $vgPage->Arg('context');
	}

	$tSess = $this->Engine()->Sessions();
	$nChg = 0;

	if ($wgRequest->getBool('btnSaveSess')) {

	    // SAVE NEW SESSION

	    $nChg = $tSess->AdminSave($this->KeyValue());
	}

	if ($wgRequest->getBool('btnFinishSess')) {

	    // FINISH NEW SESSION (save with end at current time)

	    $nChg = $tSess->AdminFinish($this->KeyValue());
	}

	if ($wgRequest->getBool('btnInvc')) {

	    // RENDER LIST OF SESSIONS TO SELECT for including on invoice

	    $argSess = $wgRequest->getArray('sess');	// get list of sessions selected
	    if (is_array($argSess)) {
		$objPage = new clsWikiFormatter($vgPage);
		//$objSection = new clsWikiSection($objPage,'Invoice','create or modify invoice',3);
		$oSection = new clsWikiSection_std_page($objPage,'Add to Invoice',3);

		$out = NULL;
		$out .= $oSection->FormOpen();
		$out .= '<table style="border: thin solid black;"><tr><td>';
		$out .= $oSection->Render();

		$arBoxAttr = array('checked' => TRUE);	// set all sessions checked by default

		// show check box for each session; also calculate what the balance should be:
		$intTotal = 0;
		$intBalLast = NULL;
		$htChecks = '';
		//$htRadios = '';

		// This builds the preliminary list of sessions for the user to approve
		foreach ($argSess as $idSess=>$val) {
		// for each session to be added...
// TO DO: make sure there are no gaps by checking the Seq field
		    $rcSess = $this->SessionTable($idSess);
		    $htChecks .= ' '.$rcSess->CheckBox('SessAdd',$arBoxAttr).$idSess."\n";

// TO DO: get rid of code for handling balance-only sessions
		    // add to balance and
		    $intTotalPenult = $intTotal;
		    $intTotal += (int)($rcSess->Value('CostLine') * 100);
		    $intBalPenult = $intBalLast;	// penultimate balance is the one which should be on the invoice
		    $intBalLast = (int)($rcSess->Value('CostBal') * 100);
		}
		$out .= "\n<table>"
		  ."\n<tr><td align=right><b>Sessions to add</b>:</td><td>\n$htChecks</td></tr>"
		  ."\n</table>";
		$rsInvcs = $this->InvoiceTable()->GetUnsent($this->KeyValue());
		if ($rsInvcs->hasRows()) {
		    $htInvcs = '<input type=radio name=addTo value="old">Add to: '.$rsInvcs->DropDown();
		} else {
		    $htInvcs = '<i>No unsent invoices available</i>';
		}
		$out .= "\n<ul>";
		  $out .= "\n".'<li><input type=radio name=addTo value="new" checked>Create new invoice</li>';
		  $out .= "\n<li>$htInvcs</li>";
		$out .= "\n</ul>";
		$out .= '<br>Notes for invoice:<br><textarea name="notes" rows=3 cols=30></textarea>';
		$out .= '<input type=submit name="btnAddSess" value="Add Sessions">';
		$out .= '</td></tr></table></form>';
		$wgOut->addHTML($out);
	    } else {
		$wgOut->addHTML('Please select sessions to be added to the invoice.');
	    }
	} elseif ($wgRequest->getBool('btnAddSess')) {

	    // ADD THE SELECTED SESSIONS TO AN INVOICE

	    $argSess = $wgRequest->getArray('SessAdd');	// get list of sessions to add
	    $strActCode = $wgRequest->getText('addTo');
	    switch ($strActCode) {
	      case 'new':
		// create the invoice object
		$strNotes = $wgRequest->getText('notes');
 		$id = $this->InvoiceTable()->Create($this->KeyValue(),$strNotes);
		$strAction = 'Adding sessions to new invoice #';
		break;
	      case 'old':
		// get the ID of the invoice to use
		$id = $wgRequest->getInt('invoice');
		$strAction = 'Adding sessions to existing invoice #';
		break;
	    }
	    if (empty($id)) {
		echo 'REQUEST:<pre>'.print_r($_REQUEST,TRUE).'</pre>';
		throw new exception('Internal error: no invoice ID for '.$strActCode.' invoice.');
	    }
	    $rcInvc = $this->InvoiceTable($id);	// get the target invoice
	    $sInvcNum = $rcInvc->Value('InvcNum');
	    $out = $strAction.$rcInvc->AdminLink($sInvcNum);
	    $wgOut->addHTML($out);	$out = '';

	    $idSessInvc = $wgRequest->GetIntOrNull('SessInv');
	    $rcInvc->AddLines($argSess,$idSessInvc);
	}
    }

    // -- UI: FORM PROCESSING -- //

    /* 2015-05-03 this is the old version that used TreeAndMenu wiki markup
      We'll want that later when subprojects are implemented, but for now it's overkill.
    public function DrawTree($iLevel=0) {
	global $vgPage;

	$out = '';
	$intLevel = $iLevel + 1;
	$strIndent = str_repeat('*',$intLevel);
	$id = $this->KeyValue();
	if (is_null($id)) {
	    $sql = 'ID_Parent IS NULL';
	} else {
	    $sql = 'ID_Parent='.$id;
	}

	$objRows = $this->Table->GetData($sql,NULL,'Name');
	if ($objRows->HasRows()) {
	    if ($iLevel == 0) {
		$out .= "\n{{#tree:id=root|root='''Projects'''|";
		$vgPage->UseWiki();
	    }
	    while ($objRows->NextRow()) {
		$strName = $objRows->Value('Name');
		$strTwig = $objRows->IsActive()?"<b>$strName</b>":"<i>$strName</i>";	// italicize if inactive
		$ftTwig = $objRows->AdminLink($strTwig,'edit '.$strTwig);
		$out .= "\n$strIndent$ftTwig";
		$out .= $objRows->DrawTree($intLevel);
	    }
	    if ($iLevel == 0) {
		$out .= "\n}}";
		//$vgPage->UseWiki();
	    }
	    return $out;
	} else {
	    return NULL;
	}
    }*/
}
