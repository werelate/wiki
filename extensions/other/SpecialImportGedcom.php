<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialImportGedcomSetup";

function wfSpecialImportGedcomSetup() {
   global $wgMessageCache, $wgSpecialPages, $wgParser;
   $wgMessageCache->addMessages( array( "importgedcom" => "Upload GEDCOM" ) );
   $wgSpecialPages['ImportGedcom'] = array('SpecialPage','ImportGedcom');
}

function wfSpecialImportGedcom() {
	global $wgRequest;
	$form = new GedcomImport( $wgRequest );
	$form->execute();
}

class GedcomImport {
// const MAX_FILE_SIZE =  5242880; //5m
// const MAX_FILE_SIZE = 10485760; //10m
	const MAX_FILE_SIZE = 12582912; //12m
	const LOWER_MAX_PEOPLE = 5100;
	const UPPER_MAX_PEOPLE = 20000;

   public static $DEFAULT_COUNTRIES = array(
      'United States' => 'United States',
      'Australia' => 'Australia',
      'Belgium' => 'Belgium',
      'Brazil' => 'Brazil',
      'Canada' => 'Canada',
      'Denmark' => 'Denmark',
      'England' => 'England',
      'Finland' => 'Finland',
      'France' => 'France',
      'Germany' => 'Germany',
      'Ireland' => 'Ireland',
      'Italy' => 'Italy',
      'Mexico' => 'Mexico',
      'Netherlands' => 'Netherlands',
      'New Zealand' => 'New Zealand',
      'Norway' => 'Norway',
      'Poland' => 'Poland',
      'Portugal' => 'Portugal',
      'Scotland' => 'Scotland',
      'South Africa' => 'South Africa',
      'Spain' => 'Spain',
      'Sweden' => 'Sweden',
      'Switzerland' => 'Switzerland',
      'Wales' => 'Wales',
      'None of the above' => ''
   );

   private $mTreeName;
   private $mDefaultCountry;
   private $mNewTreeName;
   private $mUpload;
   private $mUploadTempName;
   private $mUploadSize;
   private $mOname;
   private $mUploadError;

   /**
	 * Constructor : initialise object
	 * Get data POSTed through the form and assign them to the object
	 * @param $request Data posted.
	 */
	function __construct(&$request) {
      $this->mTreeName = $request->getText('wrTreeName');
		if( !$request->wasPosted() ) {
			# GET requests just give the main form; no data except wpDestfile.
			return;
		}
		// when the user tries to upload a file > PHP max file size, the _FILES and _POST arrays are empty
		else if ($_SERVER['CONTENT_LENGTH'] > self::MAX_FILE_SIZE) {
		   $this->mUpload = true;
		   $this->mUploadError = 1;
		}
		else {
   		$this->mUpload         = $request->getCheck( 'wrImport' );
         $this->mDefaultCountry = $request->getText('wrDefaultCountry');
     		$this->mNewTreeName    = $request->getText('wrNewTreeName');
   		$this->mUploadTempName = $request->getFileTempName( 'wrUploadFile' );
   		$this->mUploadSize     = $request->getFileSize( 'wrUploadFile' );
   		$this->mOname          = $request->getFileName( 'wrUploadFile' );
   		$this->mUploadError    = $request->getUploadError( 'wrUploadFile' );
		}
	}

	/**
	 * Start doing stuff
	 */
	public function execute() {
		global $wgUser, $wgOut;
		global $wgEnableUploads;
     	global $wrGedcomUploadDirectory;
     	global $wgEmailConfirmToEdit;

		# Check uploading enabled
		if( !$wgEnableUploads ) {
			$wgOut->showErrorPage( 'uploaddisabled', 'uploaddisabledtext' );
			return;
		}

		# Check permissions
		if( $wgUser->isLoggedIn() ) {
			if( !$wgUser->isAllowed( 'upload' )) {
				$wgOut->permissionRequired( 'upload' );
				return;
			}
   		if ($wgEmailConfirmToEdit && !$wgUser->isEmailConfirmed()) {
				$wgOut->showErrorPage('uploadnoconfirm', 'uploadnoconfirmtext' );
				return;
   		}
		} else {
			$wgOut->showErrorPage( 'uploadnologin', 'uploadnologintext' );
			return;
		}

		# Check blocks
		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}

		if( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

		/** Check if the image directory is writeable, this is a common mistake */
		if ( !is_writeable( $wrGedcomUploadDirectory ) ) {
			$wgOut->addWikiText( wfMsg( 'upload_directory_read_only', $wrGedcomUploadDirectory ) );
			return;
		}

      list($gedcomId,$gedcomStatus) = $this->inprocessGedcom();
      if ($gedcomId) {
         $this->notifyInprocess($gedcomId,$gedcomStatus);
         return;
      }

		if ($this->mUpload) {
			$this->processUpload();
		} else {
			$this->mainUploadForm();
		}
	}

   function inprocessGedcom() {
      global $wgUser;

      $dbr =& wfGetDB( DB_SLAVE );
      $res = $dbr->select(array('familytree', 'familytree_gedcom'), array('fg_id', 'fg_status'), 
                           array( 'fg_tree_id=ft_tree_id', 'ft_user' => $wgUser->getName(), GedcomsPage::getInProcessCondition()));
      $gedcomId = 0;
      $gedcomStatus = 0;
      // should be at most 1
      while ($row = $dbr->fetchObject($res)) {
         $gedcomId = $row->fg_id;
         $gedcomStatus = $row->fg_status;
      }
      $dbr->freeResult($res);

      return array($gedcomId,$gedcomStatus);
   }

   function notifyInprocess($gedcomId,$gedcomStatus) {
      global $wgOut, $wgUser;
      global $wgUseCopyrightUpload;

      // set up page
      $wgOut->setPagetitle('Upload GEDCOM');
      $wgOut->setArticleRelated(false);
      $wgOut->setRobotpolicy('noindex,nofollow');

      $wgOut->addHTML( "<h2>You have a GEDCOM already in process</h2>\n" .
           "<p>Before uploading a new GEDCOM you need to wait for your earlier GEDCOM to finish importing.</p>\n" );
      if ($gedcomStatus == GedcomsPage::$USER_REVIEW) {
         $msg = "<a href=\"/gedcom-review/?gedcomId=$gedcomId\" rel=\"nofollow\"><b>Click here</b></a> to review (or remove) your earlier GEDCOM.";  // link changed Dec 2020 by Janet Bjorndahl
      }
      else if ($gedcomStatus == GedcomsPage::$ADMIN_REVIEW) {
         $msg = "Please be patient; an Administrator should review your earlier GEDCOM and finalize the import within the next 24 hours";
      }
      $wgOut->addHtml("<p>$msg</p>");
   }

	/**
	 * Really do the upload
	 * Checks are made in SpecialUpload::execute()
	 * @access private
	 */
	function processUpload() {
		global $wgUser, $wgOut, $wrGedcomUploadDirectory, $wrGrepPath;

		/* Check for PHP error if any, requires php 4.2 or newer */
		// this must appear before the tree name check
		if ( $this->mUploadError == 1 /*UPLOAD_ERR_INI_SIZE*/ ) {
			$this->mainUploadForm( wfMsgHtml( 'largegedcom' ) );
			return;
		}
		/**
		 * If there was no filename or a zero size given, give up quick.
		 */
		if( trim( $this->mOname ) == '' || empty( $this->mUploadSize ) ) {
			$this->mainUploadForm( wfMsgHtml( 'emptyfile' ) );
			return;
		}

		if ($this->mUploadError != 0) {
		   $this->mainUploadForm('Error during upload.');
		   return;
		}

	   // get tree id
	   if ($this->mTreeName == '[new]') {
	      $treeName = $this->mNewTreeName;
	   }
	   else {
	      $treeName = $this->mTreeName;
	   }
   	$dbr =& wfGetDB( DB_SLAVE );
   	$tree = $dbr->selectRow('familytree', array('ft_tree_id', 'ft_person_count'), array('ft_user' => $wgUser->getName(), 'ft_name' => $treeName));
   	if ($tree === false) {
   		if (!$treeName) {
   			$this->mainUploadForm('Please enter a name for your new tree');
   			return;
   		}
   	   if (!FamilyTreeUtil::isValidTreeName($treeName)) {
   	      $this->mainUploadForm('Sorry, that name contains characters that are not allowed in a tree name');
   	      return;
   	   }
         else {
            if (FamilyTreeUtil::createFamilyTree($dbr, $wgUser->getName(), $treeName) == FTE_SUCCESS) {
            	$tree = $dbr->selectRow('familytree', array('ft_tree_id', 'ft_person_count'), array('ft_user' => $wgUser->getName(), 'ft_name' => $treeName));
            }
            if ($tree === false) {
               $this->mainUploadForm('Error creating tree');
               return;
            }
         }
   	}
//   	else {
//   		$this->mainUploadForm('This tree already exists.  You must import your GEDCOM into a new tree.');
//   		return;
//   	}
   	
		// check if there are too many people in the file
		// HACK: set ft_person_count = -1 to allow people to upload GEDCOM's up to UPPER_MAX_PEOPLE
//		$handle = popen($wrGrepPath.' -c "@ INDI" '.$this->mUploadTempName, 'r');  // use tr to translate from cr to lf so grep works for mac files
		$handle = popen('cat '.$this->mUploadTempName.' | tr \\\r \\\n | grep -c "@ INDI"', 'r');
		$cnt = fread($handle, 1024);
		pclose($handle);
		wfDebug("GEDCOM treeid={$tree->ft_tree_id} count=$cnt person_count={$tree->ft_person_count}\n");
		if ($cnt > self::UPPER_MAX_PEOPLE || ($tree->ft_person_count >= 0 && $cnt > self::LOWER_MAX_PEOPLE)) {
			$this->mainUploadForm( wfMsgHtml( 'largegedcom' ) );
			return;
		}

   	# Chop off any directories in the given filename
		$basename = wfBaseName( $this->mOname );

      $dbw =& wfGetDB(DB_MASTER);
	   $timestamp = wfTimestampNow();
  	   $record = array('fg_tree_id' => $tree->ft_tree_id, 'fg_gedcom_filename' => $basename, 'fg_status_date' => $timestamp,
                     'fg_file_size' => $this->mUploadSize, 'fg_default_country' => $this->mDefaultCountry, 'fg_status' => FG_STATUS_UPLOADED);
      $dbw->insert('familytree_gedcom', $record);

		$gedcomId = $dbw->selectField('', 'last_insert_id()', null);
		$destFilename = $wrGedcomUploadDirectory . '/' . $gedcomId . '.ged';
		wfSuppressWarnings();
		$success = move_uploaded_file($this->mUploadTempName, $destFilename);
		wfRestoreWarnings();

		if (!$success) {
         wfDebug("wfUploadGedcom error move=$destFilename\n");
			$wgOut->showFileNotFoundError( $this->mUploadTempName );
			return;
      }
      chmod($destFilename, 0644);
      $this->showSuccess($basename, $treeName);
	}

	/**
	 * Show some text and linkage on successful upload.
	 * @access private
	 */
	function showSuccess($basename, $treeName) {
		global $wgUser, $wgOut;

		$sk = $wgUser->getSkin();
		$wgOut->addHTML( '<h2>' . wfMsgHtml( 'successfulupload' ) . "</h2>\n" );
		$wgOut->addWikiText( wfMsg('gedcomimportedtext', $basename, $treeName));
	}

	/**
	 * Displays the main upload form, optionally with a highlighted
	 * error message up at the top.
	 *
	 * @param string $msg as HTML
	 * @access private
	 */
	function mainUploadForm( $msg='' ) {
		global $wgOut, $wgUser;
		global $wgUseCopyrightUpload;

      // set up page
      $wgOut->setPagetitle('Upload GEDCOM');
      $wgOut->setArticleRelated(false);
      $wgOut->setRobotpolicy('noindex,nofollow');

		if ( '' != $msg ) {
			$sub = 'Import Error';
			$wgOut->addHTML( "<h2>{$sub}</h2>\n" .
			  "<span class='error'>{$msg}</span>\n" );
		}

		$wgOut->addScript(<<< END
<script type='text/javascript'>/*<![CDATA[*/
function hideNewTreeName() {
	sel = document.getElementById('wrTreeName');
	treeName = sel.options[sel.selectedIndex].value;
   $('span.wrNewTreeName').css('display',treeName == '[new]' ? 'inline' : 'none');
}
/*]]>*/</script>
END
      );
		$sk = $wgUser->getSkin();

      $country = ($this->mDefaultCountry ? $this->mDefaultCountry : 'United States');
      $defaultCountryControl = StructuredData::addSelectToHtml(0, 'wrDefaultCountry', GedcomImport::$DEFAULT_COUNTRIES,
                                                               $country, '', false);

		$treeFound = false;
		$trees = FamilyTreeUtil::getFamilyTrees($wgUser->getName(), false);
      $treeList = '<select name="wrTreeName" id="wrTreeName" onchange="hideNewTreeName()">';
      for ($i=0; $i < count($trees); $i++) {
         $encTreeName = htmlspecialchars($trees[$i]['name']);
         if ($trees[$i]['name'] == $this->mTreeName) {
            $treeFound = true;
            $selected = ' selected="selected"';
         }
         else {
            $selected = '';
         }
         $treeList .= "<option value=\"$encTreeName\"$selected>$encTreeName</option>";
      }
      if ($treeFound || (strlen($this->mTreeName) == 0 && count($trees) > 0)) {
         $selected = '';
         $display = 'none';
		   $encNewTreeName = '';
      }
      else {
         $selected = ' selected="selected"';
         $display = 'inline';
         $encNewTreeName = htmlspecialchars( $this->mNewTreeName );
      }
      $treeList .= "<option value=\"[new]\"$selected>&lt;New tree&gt;</option>";
      $treeList .= '</select>';

		$sourcefilename = 'GEDCOM filename';
      $importIntoTree = 'Import into tree';
      $newTreeName = 'Tree name';
		$importGedcom = 'Upload GEDCOM';

		$titleObj = Title::makeTitle( NS_SPECIAL, 'ImportGedcom' );
		$action = $titleObj->escapeLocalURL();
		$wgOut->addHTML(<<< END
<form id='import' method='post' enctype='multipart/form-data' action="$action">
<table border='0'>
<tr>
 <td align='right'><label for='wrUploadFile'>{$sourcefilename}:</label></td>
 <td align='left'><input type='file' name='wrUploadFile' id='wrUploadFile' size='30' /></td>
</tr>
<tr>
 <td align="right"><label for="wrDefaultCountry">Default country:</label></td>
 <td align="left" style="font-size: 11px; line-height:2em">$defaultCountryControl &nbsp; if places in your GEDCOM don't have countries, this country will be assumed</td>
</tr>
<tr>
 <td align='right'><label for='wrTreeName'>$importIntoTree</label></td>
 <td align='left'>$treeList
	<span class='wrNewTreeName' style='display:$display'><label for='wrNewTreeName'>$newTreeName</label></span>
   <span class='wrNewTreeName' style='display:$display'><input type='text' name='wrNewTreeName' size='29' value="$encNewTreeName"/></span>
   &nbsp;<input type='submit' id='wrImport' name='wrImport' value="$importGedcom" /></td>
</tr>
</table>
</form>
END
      );

		$wgOut->addWikiText( wfMsg('importgedcomtext'));
	}
}
?>
