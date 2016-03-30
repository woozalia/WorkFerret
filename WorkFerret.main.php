<?php

//require_once(KFP_LIB.'/modloader.php');
clsModule::DebugMode(FALSE);
require(dirname( __FILE__ ).'/config-libs.php');

define('kwp_WF_DocPfx','htyp:WorkFerret/');
define('kwp_WF_DocTblPfx',kwp_WF_DocPfx.'tables/');
define('kwp_WF_DocTermPfx',kwp_WF_DocPfx.'ui/terms/');

if (!defined('KS_CHAR_URL_ASSIGN')) {
    define('KS_CHAR_URL_ASSIGN',':');	// character used for encoding values in wiki-internal URLs
}

class SpecialWorkFerret extends SpecialPageApp {
    protected $args;

    // ++ SETUP ++ //

    public function __construct() {
	global $wgMessageCache,$wgUser;
	global $vgUserName;
	global $vgPage;

	parent::__construct( 'WorkFerret' );
	$vgPage = $this;

        // 2015-04-29 "includable" no longer invoked this way
	//$this->includable( TRUE );
        //$wgMessageCache->addMessage('workferret', 'WorkFerret time billing');
	$vgUserName = 'wiki:'.$wgUser->getName();
    }

    // -- SETUP -- //
    // ++ CEMENT ++ //

    private $objDb;
    public function DB() {
	if (!isset($this->objDb)) {
	    $this->objDb = new clsWorkFerretData(KS_DB_WORKFERRET);
	    $this->objDb->Open();
	}
	return $this->objDb;
    }

    // -- CEMENT -- //
    // ++ MW CALLBACK ++ //

    function execute( $par ) {
	global $wgUser;

	$this->setHeaders();
	$this->GetArgs($par);
	if ($wgUser->isAllowed('editinterface')) {
		$this->doAdmin();
	} else {
		$this->doUser();
	}
    }

    // -- MW CALLBACK -- //
    // ++ INTERNAL ENTRY POINTS ++ //

    /*-----
      PURPOSE: do stuff that only admins are allowed to do
    */
    public function doAdmin() {
	global $wgOut;

	if (isset($this->args['page'])) {
	    $page = $this->args['page'];
	} else {
	    $page = NULL;
	}
// display menu
	//$wtSelf = 'Special:'.$this->name();
	//$wtSelf = $this->getFullTitle();
	$wtSelf = $this->Page()->BaseURL_rel();

	$objMenu = new clsMenu($wtSelf);
	$objMenu->Add($objRow = new clsMenuRow('Choose','menu.top'));
	  $objRow->Add(new clsMenuItem('projects','proj'));
	  $objRow->Add(new clsMenuItem('rates','rate'));
	  $objRow->Add(new clsMenuItem('invoices','invc'));

	$out = $objMenu->Render($page);
	$out .= $objMenu->Execute();

	$wgOut->addHTML(
            '<table style="background: #ccffcc;"><tr><td>'
            .$out
            .'</td></tr></table>'
            );
        $out = NULL;

	if (!is_null($page)) {
	    $db = self::DB();
	    $id = isset($this->args['id'])?$this->args['id']:NULL;
	    $doNorm = FALSE;
	    switch ($page) {
	      case 'proj':
		$objTbl = $db->Projects();
		$doNorm = TRUE;
		break;
	      case 'invc':
		$objTbl = $db->Invoices();
		$doNorm = TRUE;
		break;
	      case 'invlin':
		$objTbl = $db->InvcLines();
		$doNorm = TRUE;
		break;
	      case 'sess':
		$objTbl = $db->Sessions();
		$doNorm = TRUE;
		break;
	      case 'rate':
		$objTbl = $db->Rates();
		$doNorm = TRUE;
		break;
	    }
	    if ($doNorm) {
		if (is_null($id)) {
		    $out .= $objTbl->AdminPage();
		} else {
		    $objRow = $objTbl->GetItem($id);
		    $out .= $objRow->AdminPage();
		}
	    }
	    $wgOut->AddHTML($out);
	}
    }
    /*----
	PURPOSE: do only stuff that regular users are allowed to do
    */
    public function doUser() {
	global $wgOut;

	$wgOut->AddWikiText('Hello regular user! I haven\'t written anything for you yet, but eventually.');
    }

    // -- INTERNAL ENTRY POINTS -- //

}

class clsWorkFerretData extends clsDatabase {
    protected $arObjs;

    public function App(clsApp $iApp=NULL) {
	if (!is_null($iApp)) {
	    $this->objApp = $iApp;
	} elseif (empty($this->objApp)) {
	    $this->objApp = new clsApp_MW();
	    $this->objApp->Data($this);
	}
	return $this->objApp;
    }
/*
    protected function Make($iName) {
	if (!isset($this->arObjs[$iName])) {
	    $this->arObjs[$iName] = new $iName($this);
	}
	return $this->arObjs[$iName];
    }
*/
    public function Projects() {	// override parent
	return $this->Make('clsWFProjects');
    }
    public function Invoices() {
	return $this->Make('clsWFInvoices');
    }
    public function InvcLines() {
	return $this->Make('clsWFInvcLines');
    }
    public function Rates() {
	return $this->Make('clsWFRates');
    }
    public function Sessions() {
	return $this->Make('wfcSessions');
    }
    public function ProjsXRates() {
	return $this->Make('clsProjs_x_Rates');
    }
}
/******
 STANDALONE FUNCTIONS
*/
function ShowBal($iSaved,$iCalc) {
    if ($iSaved == $iCalc) {
	return $iSaved;
    } else {
	return '<small><s>'.$iSaved.'</s><br>'.$iCalc.'</small>';
    }
}

function BitToBool($iBit) {
    return $iBit != chr(0);
}
function BoolToBit($iBool) {
    //return SQLValue($iBool?chr(1):chr(0));	// works, but may not be reliable
    return "b'".($iBool?'1':'0')."'";
}
