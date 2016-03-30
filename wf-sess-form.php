<?php
/*
  PURPOSE: classes for specialized Session forms in WorkFerret
  HISTORY:
    2015-06-18 adapted partly from code in wf-sess.php
*/

class wfcForm_Session_page extends fcForm_DB {

    public function __construct(wfcSession $rs) {
	parent::__construct($rs);

	  $oField = new fcFormField_Num($this,'ID_Proj');
	    $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
	      $oCtrl->Records($rs->ProjectRecords_forChoice());
	      $oCtrl->NoDataString('none available');

	  $oField = new fcFormField_Time($this,'WhenStart');
	    $oCtrl = new fcFormControl_HTML_Timestamp($oField,array());

	  $oField = new fcFormField_Time($this,'WhenFinish');
	    $oCtrl = new fcFormControl_HTML_Timestamp($oField,array());

	  $oField = new fcFormField_Num($this,'Sort');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>2));

	  $oField = new fcFormField_Num($this,'TimeAdd');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>2));

	  $oField = new fcFormField_Num($this,'TimeSub');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>2));

	  $oField = new fcFormField_Num($this,'BillRate');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>5));

	  $oField = new fcFormField_Num($this,'TimeTotal');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>2));

	  $oField = new fcFormField_Num($this,'ID_Rate');
	    $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
	      $oCtrl->Records($rs->ProjectRecord()->RateRecords());
	      $oCtrl->NoDataString('no rates set');
	      $oCtrl->NoObjectString('no project set');
	      $oCtrl->AddChoice('','(none)');

	  $oField = new fcFormField_Num($this,'CostAdd');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>5));

	  $oField = new fcFormField_Num($this,'ID_Invc');
	    $oCtrl = new fcFormControl_HTML($oField,array());

	  $oField = new fcFormField_Num($this,'ID_InvcLine');
	    $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
	      if (!$rs->IsNew()) {	// shouldn't this be "if ($rs->HasInvoice())"?
		  $oCtrl->Records($rs->InvoiceLineRecords_forChoice());
	      }
	      $oCtrl->NoObjectString('no invoice assigned');
	      $oCtrl->AddChoice('','(unassign)');

	  $oField = new fcFormField_Text($this,'Descr');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>40));

	  $oField = new fcFormField_Text($this,'Notes');
	    $oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>50));

	  $oField = new fcFormField_Time($this,'WhenEdited');
	    // conveniently, page-editing is only for existing records, so we can just always update WhenEdited
	    //$oField->SetDefaultNative(time());
	    $oField->SetValue(time());	// trigger the "changed" flag
	    // 2016-03-30 This may or may not work...
    }
    public function Save() {
	$rs = $this->RecordsObject();

	if ($rs->HasQtyRate()) {
	    // having a separate field for this may turn out to be silly
	    $this->FieldObject('BillRate')->SetValue($rs->RateUsed());
	}

	return parent::Save();
    }
}
class wfcForm_Session_line extends fcForm_DB {

    public function __construct(wfcSession $rs) {
	parent::__construct($rs);

	  $oField = new fcFormField_Num($this,'ID_Proj');
	    $oCtrl = new fcFormControl_HTML_Hidden($oField,array());
	    $oField->SetDefault($rs->ProjectID());	// 2016-03-30 not sure if this is what was intended...

	  $oField = new fcFormField_Time($this,'WhenStart');
	    $oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>10));
	    $oCtrl->Format('n/j G:i');
	    //$oField->SetValueNative(time());
	    $oField->SetDefault(time());

	  $oField = new fcFormField_Time($this,'WhenFinish');
	    $oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>10));
	    $oCtrl->Format('n/j G:i');

	  $oField = new fcFormField_Num($this,'Sort');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>1));

	  $oField = new fcFormField_Num($this,'TimeAdd');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>2));

	  $oField = new fcFormField_Num($this,'TimeSub');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>2));

	  $oField = new fcFormField_Num($this,'ID_Rate');
	    $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
	    $oCtrl->Records($rs->ProjectRecord()->RateRecords());
	    $oCtrl->NoDataString('none available');

	  $oField = new fcFormField_Num($this,'CostAdd');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>4));

	  $oField = new fcFormField_Text($this,'Descr');
	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>40));

	  $oField = new fcFormField_Time($this,'WhenEntered');
	    // conveniently, line-editing is only for new records, so we can just always update WhenEntered
	    $oField->SetValue(time());
	  $oField = new fcFormField_Num($this,'BillRate');
	  $oField = new fcFormField_Num($this,'Seq');

//	$this->NewValue('WhenStart',time());
//	$this->NewValue('ID_Proj',$rs->ProjectID());
    }
    public function Save() {
	$rs = $this->RecordsObject();

	$this->FieldObject('Seq')->SetValue($rs->ProjectRecord()->NextSessSeq());
	return parent::Save();
    }
    // FAUX-ABSTRACT OVERRIDE
    protected function ProcessRecord_beforeSave() {
	$this->StoreRecord();	// copy Field data to Recordset object
	$rs = $this->RecordsObject();
	if ($rs->HasQtyRate()) {
	    // having a separate field for this may turn out to be silly
	    $this->FieldObject('BillRate')->SetValue($rs->RateUsed());
	}
    }
}