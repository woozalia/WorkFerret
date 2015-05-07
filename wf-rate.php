<?php
/*
  PURPOSE: classes for managing Rates in WorkFerret
  HISTORY:
    2013-10-31 split off from WorkFerret.main
*/
class clsWFRates extends clsTable_key_single {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('rate');
	  $this->KeyName('ID');
	  $this->ClassSng('clsWFRate');
	  $this->ActionKey('rate');	// for AdminLink -- used where?
    }
    /*----
      HISTORY:
	2011-04-21 renamed from ListPage() -> AdminPage()
    */
    public function AdminPage() {
	$this->AdminSave();	// apply any user edits
	$objRows = $this->GetData();
	return $objRows->AdminList();
    }
    public function AdminSave() {
	global $wgRequest;

	if ($wgRequest->GetBool('btnSave')) {
	    $id = $wgRequest->GetIntOrNull('ID');
	    $doNew = (empty($id));

	    $isActv = $wgRequest->GetBool('isActive');
	    $isLItm = $wgRequest->GetBool('isLineItem');
	    //$idProj = $wgRequest->GetIntOrNull('ID_Proj');
	    $strName = $wgRequest->GetText('Name');
	    $dlrHourly = $wgRequest->GetText('PerHour');

	    $arRow = array(
	      'isActive'	=> BoolToBit($isActv),
	      'isLineItem'	=> BoolToBit($isLItm),
	      //'ID_Proj'	=> SQLValue($idProj),	// can be NULL
	      'Name'	=> SQLValue($strName),
	      'PerHour'	=> SQLValue($dlrHourly)
	      );
	    if ($doNew) {
echo __FILE__.' line '.__LINE__.'<br>';
		$this->Insert($arRow);
	    } else {
echo __FILE__.' line '.__LINE__.'<br>';
		$this->Update($arRow,'ID='.$id);
	    }
	}
    }
    /*
      RETURNS: clsRate recordset (or descendant)
    */
    public function Rates_forProj($idProj) {
	$sql = 'SELECT r.* FROM rate_proj AS rp LEFT JOIN rate AS r ON rp.ID_Rate=r.ID WHERE rp.ID_Proj='.$idProj;
	$rs = $this->DataSQL($sql);
	return $rs;
    }
}
class clsWFRate extends cDataRecord_MW {

    // ++ BOILERPLATE ++ //
/* 2015-05-03 hopefully this is redundant now
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminRedirect($this,$iarArgs);
    }
*/
    // -- BOILERPLATE -- //
    // ++ STATUS ACCESS ++ //

    public function isLineItem() {
	$bit = $this->Value('isLineItem');
	//return ($bit == chr(1));
	return BitToBool($bit);
    }

    // -- STATUS ACCESS -- //
    // ++ DATA FIELD ACCESS ++ //

    /*----
      PUBLIC so Project can retrieve description of default rate
    */
    public function Descr() {
	$strName = $this->Value('Name');
	$curPerHour = $this->Value('PerHour');
	$ftAmt = ($curPerHour == 0)?'':('($'.$curPerHour.') ');
	return $ftAmt.$strName;
    }
    /*----
      PUBLIC for drop-down control (and, theoretically, other lists)
      DONE AS alias of Descr(), but that could change
    */
    public function Text_forList() {
        return $this->Descr();
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //

    private $tProjs;
    protected function XProjs() {
	if (empty($this->oProjs)) {
	    $this->tProjs = $this->Engine()->ProjsXRates();
	    $this->tProjs->Load();
	}
	return $this->tProjs;
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function Records_forDropDown(array $arArgs=NULL) {
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ WEB INTERFACE ++ //

    public function DropDown(array $iArArgs=NULL) {
	$strName = nz($iArArgs['name'],'rate');
	$strNone = nz($iArArgs['none'],'none found');
	$intDeflt = nz($iArArgs['default'],0);

	if ($this->hasRows()) {
	    $out = "\n".'<select name="'.$strName.'">';
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		if ($id == $intDeflt) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= "\n".'  <option'.$htSelect.' value="'.$id.'">'.$this->Descr().'</option>';
	    }
	    $out .= "\n</select>\n";
	    return $out;
	} else {
	    return $strNone;
	}
    }
    public function AdminPage() {
//	$arArgs['edit'] = $this->KeyValue();
	$objRows = $this->Table->GetData();
//	return $objRows->AdminList($arArgs);
	return $objRows->AdminList();
    }
    /*----
      HISTORY:
	2011-12-28 uncommented edit-saving code
    */
//    public function AdminList(array $iarArgs=NULL) {
    public function AdminList() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$strDo = $vgPage->Arg('do');
	$doNew = ($strDo == 'add');
	$idEdit = $vgPage->Arg('id');
	$doEdit = $vgPage->Arg('edit') || (!empty($idEdit));
	$doAssg = $vgPage->Arg('assign');
	$doForm = $doNew || $doEdit || $doAssg;
	$doSave = $wgRequest->getBool('btnSave');
	$doSaveAssign = $wgRequest->getBool('btnAssign');

	// save variables to pass to DisplayRow()
	$this->idEdit = $idEdit;

	if ($doSave) {
	    $intChg = $this->AdminSave();	// save edit
	    $this->AdminRedirect();		// display non-edit version
	}

	if ($doSaveAssign) {
	    $tXProjs = $this->XProjs();
	    $arX = $wgRequest->getArray('rxp');
	    $tXProjs->Update_fromArray($arX);
	    $this->AdminRedirect();
	}

	$strName = 'Rates';

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection_std_page($objPage,$strName,2);
	//$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'edit'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'assign'));
	  $objLink->Popup('assign rates to projects');

	$out = $objSection->Render();

	if ($doForm) {
	    $out .= $objSection->FormOpen();
	    if ($doAssg) {
		$out .= '<table><tr><td>';
	    }
	}
	$wgOut->AddHTML($out);

	if ($this->hasRows() || $doNew) {
	    $out = "\n<table>".$this->XProjs()->RenderRate_hdr();

	    if ($doNew) {
		$out .= $this->DoEditRow();
	    }

	    $this->isOdd = FALSE;
	    while ($this->NextRow()) {
		$out .= $this->DisplayRow($doAssg);
	    }
	    // display new row
	    $arNew = array(
	      'ID'		=> 'new',
	      //'ID_Proj'		=> NULL,
	      'Name'		=> NULL,
	      'PerHour'		=> NULL,
	      'isActive'	=> TRUE,
	      'isLineItem'	=> TRUE,
	      );
	    $this->Values($arNew);
	    $out .= $this->DisplayRow(FALSE);

	    $out .= "</table>";
	} else {
	    $out = 'No rates defined.';
	}
	if ($doForm) {
	    if ($doAssg) {
		$out .= '<div align=right><input type=submit name=btnAssign value="Save Assignments"></div></td></tr></table>';
	    }
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out);
    }
    public function DisplayRow($iDoAssign) {
	$idEdit = $this->idEdit;

	$id = $this->KeyValue();
	$wtStyle = $this->isOdd?'background:#ffffff;':'background:#eeeeee;';
	$this->isOdd = !$this->isOdd;
	$htTR = "\n<tr style=\"$wtStyle\">";

	//$objProj = $this->ProjectObject();

	$ftID = $this->AdminLink();
	$ftActv = BitToBool($this->Value('isActive'))?'A':'';
	$ftLItm = BitToBool($this->Value('isLineItem'))?'LI':'';
/*
	if ($objProj->IsNew()) {
	    $ftProj = 'ALL';
	} else {
	    $ftProj = $objProj->AdminLink($objProj->Value('Name'));
	}
*/
	$ftName = $this->Value('Name');
	$ftPerHr = empty($this->Row['PerHour'])?'-':sprintf('%0.2f',$this->Value('PerHour'));

	$id = $this->Value('ID');
	$ftProjs = $this->XProjs()->RenderRate($id,$iDoAssign);

	$out = NULL;
	if ($id == $idEdit) {
	    $out .= $this->DoEditRow();
	} else {
	    $out .= $htTR;
	    $out .= "\n<td>$ftID</td>"
	      ."<td align=center>$ftActv</td>"
	      ."<td align=center>$ftLItm</td>"
	      //."<td>$ftProj</td>"
	      ."<td>$ftName</td>"
	      ."<td align=right>$ftPerHr</td>"
	      .'<td></td>'	// empty cell under "Projects"
	      .$ftProjs
	      .'</tr>';
	}
	return $out;
    }
    public function DoEditRow() {
	//$idRate = nz($iarArgs['rate']);	// currently selected

	if(empty($this->Row['ID'])) {
	    $htID = '<i>new</i>';
	    $htActv = ' checked';
	    $htLItm = '';
	    //$arProj = NULL;
	    $htName = '';
	    $htSort = NULL;
	    $htPerHour = NULL;
	} else {
	    $id = $this->KeyValue();
	    $htID = '<b>'.$id.'</b><input type=hidden name=ID value='.$id.'>';
	    $htActv = BitToBool($this->Value('isActive'))?' checked':'';
	    $htLItm = BitToBool($this->Value('isLineItem'))?' checked':'';

	    //$arProj['default'] = $this->Value('ID_Proj');

	    $htName = htmlspecialchars($this->Value('Name'));
	    $htPerHour = sprintf('%0.02f',$this->Value('PerHour'));
	}
	//$arProj['incl none'] = TRUE;
	//$htProj = $this->objDB->Projects()->DropDown($arProj);
	$out = "\n<tr>"
	  .'<td>'.$htID.'</td>'
	  .'<td align=center>A<br><input type=checkbox name=isActive'.$htActv.'></td>'
	  .'<td align=center>LI<br><input type=checkbox name=isLineItem'.$htLItm.'></td>'
	//  .'<td>'.$htProj.'</td>'
	  .'<td><input name=Name value="'.$htName.'" size=20></td>'
	  .'<td><input name=PerHour value="'.$htPerHour.'" size=5></td>'
	  .'<td><input type=submit name=btnSave value="Save"></td>'
	  .'</tr>';
	return $out;
    }
    public function AdminSave() {
	$id = $_POST['ID'];

	$isActive = ($_POST['isActive'] == 'on');

	$arEdit = array(
	  'isActive'	=> ($isActive?'TRUE':'FALSE'),
	  'Name'	=> SQLValue($_POST['Name']),
	  'PerHour'	=> SQLValue($_POST['PerHour']),
	  );

	if (is_numeric($id)) {
	    // save existing record
	    $rc = $this->Table->GetItem($id);
	    $rc->Update($arEdit);
	} else {
	    // create new record
	    $this->Table->Insert($arEdit);
	}
    }

    // -- WEB INTERFACE -- //
}
