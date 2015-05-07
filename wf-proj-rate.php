<?php
/*
  PURPOSE: classes for managing Project-Rate mapping in WorkFerret
  HISTORY:
    2013-11-08 split off from WorkFerret.main
*/
class clsProjs_x_Rates extends clsTable_abstract {
    private $arRates;	// array of all rates
    private $arProjs;	// array of all projects
    private $arX;	// complete matrix of projs x rates
    private $arP_IDs;	// project IDs, sorted for display

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('rate_proj');
	$this->arX = NULL;
    }
    public function Load() {
	if (empty($this->arX)) {
	    $rc = $this->GetData(NULL,NULL,'ID_Proj,ID_Rate');
	    while ($rc->NextRow()) {
		$idProj = $rc->Value('ID_Proj');
		$idRate = $rc->Value('ID_Rate');
		$this->arX[$idProj][$idRate] = TRUE;
	    }
	}
    }
    public function Exists($iProj,$iRate) {
	if (is_null($this->arX)) {
	    return NULL;
	} else {
	    $arRates = NzArray($this->arX,$iProj);
	    return NzArray($arRates,$iRate);
	}
    }
    /*----
      RETURNS: array of all project data
      OUTPUT: RETURN and $arProjs
    */
    protected function Projs() {
	if (empty($this->arProjs)) {
	    $tblP = $this->Engine()->Projects();
	    $rc = $tblP->GetData();
	    while ($rc->NextRow()) {
		$idProj = $rc->Value('ID');
		$this->arProjs[$idProj] = $rc->Values();
	    }
	}
	return $this->arProjs;
    }
    /*----
      RETURNS: array of all rate data
      OUTPUT: RETURN and $arRates
    */
    protected function Rates() {
	if (empty($this->arRates)) {
	    $tblR = $this->Engine()->Rates();
	    $rc = $tblR->GetData();
	    while ($rc->NextRow()) {
		$idRate = $rc->Value('ID');
		$this->arRates[$idRate] = $rc->Values();
	    }
	}
	return $this->arRates;
    }
    /*----
      RETURNS: list of project IDs, sorted for display purposes
      OUTPUT: RETURN and $arP_IDs
    */
    protected function ProjSort() {
	if (empty($this->arP_IDs)) {
	    $rsP = $this->Engine()->Projects()->GetData(NULL,NULL,'InvcPfx');
	    $idx = 0;
	    while ($rsP->NextRow()) {
		$this->arP_IDs[$idx] = $rsP->Value('ID');
		$idx++;
	    }
	}
	return $this->arP_IDs;
    }
    public function RenderRate_Hdr() {
	$out = "\n<tr>\n"
	  .'<th>ID</th>'
	  .'<th colspan=2>Status</th>'
	//  .'<th>Project</th>'
	  .'<th>Name</th>'
	  .'<th>$/Hour</th>';

	// add a column for each project
	$arPS = $this->ProjSort();
	$arPR = $this->Projs();
	$rcP = $this->Engine()->Projects()->SpawnItem();
	if (count($arPS)) {
	    $out .= '<th>Projects:</th>';
	    foreach ($arPS as $idx => $idP) {
		$arP = $arPR[$idP];
		$rcP->Values($arP);
		$out .= '<th>'.$rcP->AdminLink($rcP->Value('InvcPfx'),$rcP->Value('Name')).'</th>';
	    }
	}
/* OLD VERSION
	$oPrjs = $this->Engine()->Projects()->GetData(NULL,NULL,'InvcPfx');
	if ($oPrjs->HasRows()) {
	    $out .= '<th>Projects:</th>';
	    while ($oPrjs->NextRow()) {
		$out .= '<th>'.$oPrjs->AdminLink($oPrjs->Value('InvcPfx'),$oPrjs->Value('Name')).'</th>';
	    }
	}
*/

	$out .= '</tr>';
	  return $out;
    }
    public function RenderRate($iRate,$iDoAssign) {
	if ($iDoAssign) {
	    $sName = 'rxp[<$PROJ$>]['.$iRate.']';
	    $ftYes = '<td align=center><input type=checkbox checked name="'.$sName.'"></td>';
	    $ftNo = '<td align=center><input type=checkbox name="'.$sName.'"></td>';
	} else {
	    $ftYes = '<td align=center>+</td>';
	    $ftNo = '<td></td>';
	}

	$out = NULL;
	$arPS = $this->ProjSort();
//	$arPR = $this->Projs();
	foreach ($arPS as $idx => $idP) {
	    if ($this->Exists($idP,$iRate)) {
		$out .= str_replace('<$PROJ$>',$idP,$ftYes);
	    } else {
		$out .= str_replace('<$PROJ$>',$idP,$ftNo);
	    }
	}
	return $out;
    }
    /*----
      INPUT: iar = new assignment data to completely replace whatever was previously in the table
	Each element is in the format iar[project ID][rate ID] = (something)
    */
    public function Update_fromArray($iar) {
	$db = $this->Engine();
	$db->Exec('START TRANSACTION;');
	$db->Exec('DELETE FROM '.$this->Name().';');
	foreach ($iar as $idP => $arRates) {
	    foreach ($arRates as $idR => $dummy) {
		$this->Insert(array('ID_Proj'=>$idP,'ID_Rate'=>$idR));
	    }
	}
	$db->Exec('COMMIT;');
    }
}
