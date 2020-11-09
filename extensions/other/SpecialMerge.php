<?php
/**
 * @package MediaWiki
 */
if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once("$IP/includes/Defines.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");
require_once("$IP/extensions/other/SpecialCompare.php");
require_once("$IP/extensions/gedcom/GedcomUtil.php");
require_once("$IP/includes/memcached-client.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialMergeSetup";

function wfSpecialMergeSetup() {
	global $wgMessageCache, $wgSpecialPages, $wgHooks;
	
	$wgMessageCache->addMessages( array( "merge" => "Merge" ) );
	$wgSpecialPages['Merge'] = array('SpecialPage','Merge');

   # Add new log types
   global $wgLogTypes, $wgLogNames, $wgLogHeaders, $wgLogActions;

	$wgLogTypes[]					   = 'merge';
	$wgLogNames['merge']			   = 'mergelogpage';
	$wgLogHeaders['merge']			= 'mergelogpagetext';
	$wgLogActions['merge/merge']	= 'mergelogentry';
	$wgLogActions['merge/unmerge']= 'unmergelogentry';
}

/**
 * Called to display the Special:Merge page
 *
 * @param unknown_type $par
 * @param unknown_type $specialPage
 */
function wfSpecialMerge( $par=NULL, $specialPage ) {
	global $wgOut, $wgScriptPath, $wgCommandLineMode, $wrSidebarHtml, $wgMemc, $wgUser;

//		$sideText = '';
	$mergeForm = new MergeForm();

	// read query parameters into variables
	$mergeForm->readQueryParms($par);

   if (!$wgUser->isLoggedIn()) {
		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
			User::SetupSession();
		}
		$mergeTargetTitle = $mergeForm->getMergeTargetTitle();
		$requestData = array();
		if ($mergeTargetTitle) {
			$requestData['returnto'] = $mergeTargetTitle->getPrefixedUrl();
		}
		$request = new FauxRequest($requestData);
		require_once('includes/SpecialUserlogin.php');
		$form = new LoginForm($request);
		$form->mainLoginForm("You need to sign in to merge pages<br/><br/>", '');
		return;
	}
	if( $wgUser->isBlocked() ) {
		$wgOut->blockedPage();
		return;
	}
	if( wfReadOnly() ) {
		$wgOut->readOnlyPage();
		return;
	}
  	
	$isGedcom = $mergeForm->isGedcom();
	$wgOut->setPageTitle($isGedcom ? 'Update pages' : 'Merge pages');

	if ($mergeForm->getFormAction() == 'Cancel') {
		$output = '<H2>Merge Cancelled</H2><p>You can use the <b>back</b> button on your browser to navigate back to where you were, or select an item from the menu.</p>';
	}
	else if ($mergeForm->getFormAction() == 'NotMatch') {
		$output = $mergeForm->getNotMatchResults();
	}
	else if ($mergeForm->getFormAction() != 'Merge' || !$mergeForm->preMerge()) {
		$output = '<H2>Unable to merge</H2>' . $mergeForm->getWarnings().
			'<p>Press the <b>back</b> button on your browser to go back to the Compare page.</p>';
	}
	else if ($mergeForm->isSecondPhase()) {

		// disallow merging the same page twice in a row (result of double-click)
		if ($mergeForm->isGedcom()) {
			$cacheKey = 'mergekey:ged:'.$mergeForm->gedcomId.$mergeForm->gedcomKey;
		}
		else {
			$cacheKey = 'mergekey:'.$wgUser->getID().$mergeForm->editToken;
		}
		if (!$wgMemc->get($cacheKey)) {
			$wgMemc->set($cacheKey,'t',5);
			$output = $mergeForm->doMerge();
		}
	}
	else {
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/merge.11.js\"></script>");
//		$mergeText = $isGedcom ? 'update' : 'merge';
//		$mergeButton = $isGedcom ? 'Update' : 'Merge';
//		$sideText = '<h3>Instructions</h3><p>' .
//						($mergeForm->getMergesCount() > 1 ? "For each set of pages to $mergeText, check" : 'Check') .
//						" the boxes next to the pieces of information you want included in the {$mergeText}d page.</p>" .
//						($isGedcom ? '<p><b>Updating pages is optional.</b>  It is not necessary or desirable to update these pages unless you have more accurate information or reliable sources to add.</b></p>'
//								: '<p>The <i>target</i> is the page that the other page(s) will be merged into.</p>').
//						'<p>The box colors are for your information only:</p>'.
//						'<p><font color="green">Green</font> boxes mean the information is specific and matches exactly.</p>'.
//						'<p><font color="yellow">Yellow</font> boxes mean the information is non-specific (missing some pieces) or is a partial match.</p>'.
//						'<p><font color="red">Red</font> boxes mean the information differs.</p>'.
//						"<p>Once you have chosen which pieces of information to include, click on the \"$mergeButton\" button at the bottom of the screen to $mergeText the pages.</p>".
//						'<p>(<a href="/wiki/Help:Merging_pages">more help</a>)</p>';
		$output = $mergeForm->getMergeResults();
	}
	
//   $skin = $wgUser->getSkin();
   $wrSidebarHtml = wfMsgWikiHtml('MergeHelp');
	$wgOut->addHTML($output);
}

 /**
  * Compare form used in Special:Compare
  */
class MergeForm {
	const MAX_MERGES = 10;
	
	public static $HIGH_MATCH_THRESHOLD = 6;
	public static $LOW_MATCH_THRESHOLD = 2;
	
	private static $HUSBAND_ROW = 1;
	private static $WIFE_ROW = 2;
	private static $CHILD_START_ROW = 3;

	private static $MATCH_NOMATCH = -1;
	private static $MATCH_JUNK = 0; // not used yet
	private static $MATCH_INCOMPARABLE = 1;
	private static $MATCH_DIFF = 2;
	private static $MATCH_COMPATIBLE = 3;
	private static $MATCH_SUB = 4;
	private static $MATCH_SUP = 5;
	private static $MATCH_EQ = 6;

	private static $LABEL_WIDTH = 15;
	private static $ADD_WIDTH = 5;
	
	private static $CLASS_DEFAULT = 'compare_default';
	private static $CLASS_MATCH = 'compare_match';
	private static $CLASS_PARTIAL = 'compare_partial';
	private static $CLASS_DIFF = 'compare_nomatch';
	private static $CLASS_CHECKED = 'merge_checked';
	private static $CLASS_UNCHECKED = 'merge_unchecked';
	private static $CLASS_HEADER = 'compare_page';
	private static $CLASS_LABEL = 'compare_label';
	private static $CLASS_SEPARATOR = 'merge_separator';
	private static $CLASS_ADD = 'merge_checkbox';
	private static $CLASS_GEDCOM_SOURCE = 'gedcom_source';
	private static $CLASS_GEDCOM_TARGET = 'gedcom_target';

	private static $UNPRINTED_ATTRS = array ('type', 'key');
	private static $PRIMARY_NAME = 'Name';
	
	private $data; // [i]=merge group [j]=page in group [type] [k]=element [attr]=element attr
						//   [type]= title|object|names|gender|events|sources|images|notes|contents|child_of_families|spouse_of_families|husbands|wives|children
	private $add; // [i]=merge group [j]=page in group [k]=row #
	private $key; //  ditto
	private $formAction;
	private $namespace;
	private $isMerging;
	public $editToken;
	private $mergeNumber;
	private $pageNumber;
	private $rowNumber;
	private $redirects;
	private $nomerges;
	private $target;
	private $warnings;
	private $gedcomData;
	private $gedcomDataString;
	public $gedcomId;
	private $gedcomTab;
	public $gedcomKey;
	private $addWatches;
	private $isTrusted;
	private $userComment;
	
   public static function isTrustedMerge($mergeScore, $isTrustedUser) {
		return (($mergeScore >= self::$HIGH_MATCH_THRESHOLD) ||
			     ($mergeScore >= self::$LOW_MATCH_THRESHOLD && $isTrustedUser));
   }
   
	private static function getStdType($type) {
   	if (strpos($type, 'Alt ') === 0) {
   		return substr($type, 4);
   	}
   	return $type;
   }
   
   private static function getSoundexString($name) {
   	$sdx = '';
   	$pieces = explode(' ', $name);
   	foreach ($pieces as $piece) {
   		if ($sdx) $sdx .= ' ';
   		$sdx .= soundex($piece);
   	}
   	return $sdx;
   }
   
   private static function readName($type, $o) {
   	$stdType = MergeForm::getStdType($type);
   	$given = (string)$o['given'];
   	$stdGiven = mb_convert_case($given, MB_CASE_LOWER);
   	$givenSdx = MergeForm::getSoundexString($stdGiven);
   	$surname = (string)$o['surname'];
		$stdSurname = mb_convert_case($surname, MB_CASE_LOWER);
		$surnameSdx = MergeForm::getSoundexString($stdSurname);
   	$titlePrefix = (string)$o['title_prefix'];
		$stdTitlePrefix = mb_convert_case($titlePrefix, MB_CASE_LOWER);
   	$titleSuffix = (string)$o['title_suffix'];
		$stdTitleSuffix = mb_convert_case($titleSuffix, MB_CASE_LOWER);
   	$sources = (string)$o['sources'];
   	$notes = (string)$o['notes'];
   	$key = 'NAME|'.$type.'|'.$given.'|'.$surname.'|'.$titlePrefix.'|'.$titleSuffix.'|'.$sources.'|'.$notes;
   	
   	return array('type' => $type, 'stdtype' => $stdType, 'given' => $given, 'stdgiven' => $stdGiven, 'stdgivensdx' => $givenSdx,
   					'surname' => $surname, 'stdsurname' => $stdSurname, 'stdsurnamesdx' => $surnameSdx,
   					'title_prefix' => $titlePrefix, 'stdtitle_prefix' => $stdTitlePrefix, 'title_suffix' => $titleSuffix, 'stdtitle_suffix' => $stdTitleSuffix,
   					'sources' => $sources, 'notes' => $notes, 'key' => $key);
   }
   
   private static function readNames($xml) {
   	$names = array();
   	$names[] =& MergeForm::readName(self::$PRIMARY_NAME, $xml->name);
   	foreach ($xml->alt_name as $o) {
	   	$type = (string)$o['type'];
	   	$names[] =& MergeForm::readName($type, $o);
   	}
   	return $names;
   }

   private static function addSourcesImagesNotes($list, &$srcArray, &$imgArray, &$noteArray) {
      $list = trim($list);
      if ($list) {
         $elements = preg_split('/[, ]+/', $list);
         foreach ($elements as $element) {
            $firstChar = strtoupper(substr($element, 0, 1));
            if ($firstChar == 'S') {
               $srcArray[] = $element;
            }
            else if ($firstChar == 'I') {
               $imgArray[] = $element;
            }
            else if ($firstChar == 'N') {
               $noteArray[] = $element;
            }
         }
      }
   }

   private static function readSourcesImagesNotes($sources, $images, $notes) {
      $srcArray = array();
      $imgArray = array();
      $noteArray = array();
      MergeForm::addSourcesImagesNotes($sources, $srcArray, $imgArray, $noteArray);
      MergeForm::addSourcesImagesNotes($images, $srcArray, $imgArray, $noteArray);
      MergeForm::addSourcesImagesNotes($notes, $srcArray, $imgArray, $noteArray);
      $sources = join(', ', $srcArray);
      $images = join(', ', $imgArray);
      $notes = join(', ', $noteArray);

      return array($sources, $images, $notes);
   }
   
	private static function readEvents($xml) {
   	$events = array();
   	foreach ($xml->event_fact as $o) {
   		$type = (string)$o['type'];
	   	$stdType = MergeForm::getStdType($type);
   		$date = (string)$o['date'];
   		$stdDate = (string)StructuredData::getDateKey($date);
   		$place = (string)$o['place'];
   		$stdPlace = StructuredData::getPlaceKey($place);
   		$desc = (string)$o['desc'];
         // sometimes people put the references in the wrong fields, so gather them all together
         list ($sources, $images, $notes) = MergeForm::readSourcesImagesNotes((string)$o['sources'], (string)$o['images'], (string)$o['notes']);
   		$key = 'EVENT|'.$type.'|'.$date.'|'.$place.'|'.$desc.'|'.$sources.'|'.$notes.'|'.$images;
   		
   		$events[] = array('type' => $type, 'stdtype' => $stdType, 'date' => $date, 'stddate' => $stdDate, 'place' => $place, 'stdplace' => $stdPlace,
   								'description' => $desc, 'sources' => $sources, 'notes' => $notes, 'images' => $images, 'key' => $key);
   	}
		return $events;
   }
   
   private static function readSources($xml) {
   	$sources = array();
   	foreach ($xml->source_citation as $o) {
   		$id = (string)$o['id'];
   		$key = 'SOURCE|'.$id;
         // sometimes people put the references in the wrong fields, so gather them all together
         list ($dummy, $images, $notes) = MergeForm::readSourcesImagesNotes(null, (string)$o['images'], (string)$o['notes']);
   		$sources[] = array('id' => $id, 'title' => (string)$o['title'], 'record_name' => (string)$o['record_name'],
   								'page' => (string)$o['page'], 'quality' => (string)$o['quality'], 'date' => (string)$o['date'],
   								'notes' => $notes, 'images' => $images, 'text' => ((string)$o['text'] . (string)$o),
   								'key' => $key);
   	}
		return $sources;
   }
   
   private static function readImages($xml) {
   	$images = array();
   	foreach ($xml->image as $o) {
   		$id = (string)$o['id'];
   		$caption = (string)$o['caption'];
   		$filename = (string)$o['filename'];
   		$key = 'IMAGE|'.$id;
   		$images[] = array('id' => $id, 'filename' => $filename, 'stdfilename' => str_replace('_',' ',$filename), 'caption' => $caption,
   								'primary' => (string)$o['primary'], 'key' => $key);
   	}
		return $images;
   }
   
   private static function readNotes($xml) {
   	$notes = array();
   	foreach ($xml->note as $o) {
   		$id = (string)$o['id'];
   		$text = ((string)$o['text'] . (string)$o);
   		$key = 'NOTE|'.$id;
   		$notes[] = array('id' => $id, 'text' => $text, 'key' => $key);
   	}
		return $notes;
   }
   
   private static function readTitles($xml, $role) {
   	$titles = array();
   	foreach($xml->$role as $o) {
   		$titles[] = array('title' => (string)$o['title']);
   	}
   	return $titles;
   }
   
   public static function readXmlData($isFamily, $xml, &$dataij) {
		if ($isFamily) {
			$dataij['husbands'] =& MergeForm::readTitles($xml, 'husband');
			$dataij['wives'] =& MergeForm::readTitles($xml, 'wife');
			$dataij['children'] =& MergeForm::readTitles($xml, 'child');
		}
		else {
			$dataij['names'] =& MergeForm::readNames($xml);
			$dataij['gender'] = (string)$xml->gender;
			$dataij['child_of_families'] =& MergeForm::readTitles($xml, 'child_of_family');
			$dataij['spouse_of_families'] =& MergeForm::readTitles($xml, 'spouse_of_family');
		}
		$dataij['events'] =& MergeForm::readEvents($xml);
		$dataij['sources'] =& MergeForm::readSources($xml);
		$dataij['images'] =& MergeForm::readImages($xml);
		$dataij['notes'] =& MergeForm::readNotes($xml);
   }
   
   // Note: the CompareForm standardize functions are similar to, but not exactly the same as the std... attributes used in MergeForm
   //   someday we should "standardize" on a common data representation for CompareForm::scoreMatch
   public static function standardizePersonData($data) {
   	$stdValues = array();
   	
   	$givens = array();
   	$surnames = array();
   	foreach (@$data['names'] as $name) {
   		$givens[] = $name['given'];
   		$surnames[] = $name['surname'];
   	}
   	$stdValues['Given'] = CompareForm::standardizeValues('Given', $givens);
   	$stdValues['Surname'] = CompareForm::standardizeValues('Given', $surnames);
   	
   	$birthdates = array();
   	$birthplaces = array();
   	$deathdates = array();
   	$deathplaces = array();
   	foreach (@$data['events'] as $event) {
   		if ($event['stdtype'] == Person::$BIRTH_TAG) {
   			$birthdates[] = $event['date'];
   			$birthplaces[] = $event['place'];
   		}
   		else if ($event['stdtype'] == Person::$DEATH_TAG) {
   			$deathdates[] = $event['date'];
   			$deathplaces[] = $event['place'];
   		}
   	}
   	$stdValues['Birthdate'] = CompareForm::standardizeValues('Birthdate', $birthdates);
   	$stdValues['Birthplace'] = CompareForm::standardizeValues('Birthplace', $birthplaces);
   	$stdValues['Deathdate'] = CompareForm::standardizeValues('Deathdate', $deathdates);
   	$stdValues['Deathplace'] = CompareForm::standardizeValues('Deathplace', $deathplaces);
   	
   	$genders = array();
   	$genders[] = @$data['gender'];
   	$stdValues['Gender'] = CompareForm::standardizeValues('Gender', $genders);
   	
   	$familyTitles = array();
   	foreach (@$data['child_of_families'] as $family) {
   		$familyTitles[] = $family['title'];
   	}
   	$stdValues['ParentFamilyTitle'] = CompareForm::standardizeValues('ParentFamilyTitle', $familyTitles);

   	$familyTitles = array();
   	foreach (@$data['spouse_of_families'] as $family) {
   		$familyTitles[] = $family['title'];
   	}
   	$stdValues['SpouseFamilyTitle'] = CompareForm::standardizeValues('SpouseFamilyTitle', $familyTitles);
   	
   	return $stdValues;
   }
   
   public static function standardizeFamilyData($data) {
   	$stdValues = array();
   	
		list($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($data['title']);
		$stdValues['HusbandGiven'] = CompareForm::standardizeValues('HusbandGiven', array($hg));
		$stdValues['HusbandSurname'] = CompareForm::standardizeValues('HusbandSurname', array($hs));
		$stdValues['WifeGiven'] = CompareForm::standardizeValues('WifeGiven', array($wg));
		$stdValues['WifeSurname'] = CompareForm::standardizeValues('WifeSurname', array($ws));
   	
   	$marriagedates = array();
   	$marriageplaces = array();
   	foreach (@$data['events'] as $event) {
   		if ($event['stdtype'] == Family::$MARRIAGE_TAG) {
   			$marriagedates[] = $event['date'];
   			$marriageplaces[] = $event['place'];
   		}
   	}
   	$stdValues['Marriagedate'] = CompareForm::standardizeValues('Marriagedate', $marriagedates);
   	$stdValues['Marriageplace'] = CompareForm::standardizeValues('Marriageplace', $marriageplaces);
   	
   	return $stdValues;
   }
   
   public static function calcMatchScore($d1, $d2) {
   	$score = 0;
   	foreach ($d1 as $label => $stdValues1) {
   		// don't count the following labels -- they're often already counted
   		if ($label != 'ParentFamilyTitle' && $label != 'SpouseFamilyTitle' && 
   			 $label != 'HusbandGiven' && $label != 'HusbandSurname' && 
   			 $label != 'WifeGiven' && $label != 'WifeSurname') {
	   		$stdValues2 = @$d2[$label];
	   		if (is_array($stdValues2)) {
               list($s, $class) = CompareForm::scoreMatch($label, $stdValues1, $stdValues2);
               $score += $s;
	   		}
   		}
   	}
   	return $score;
   }
   
	public function __construct() {
		$this->data = array();
		$this->editToken = '';
		$this->isMerging = false;
		$this->add = array();
		$this->key = array();
		$this->redirects = array();
		$this->nomerges = array();
		$this->target = array();
		$this->gedcomData = null;
		$this->gedcomId = 0;
		$this->gedcomDataString = '';
		$this->gedcomTab = '';
		$this->gedcomKey = '';
		$this->addWatches = true;
		$this->isTrusted = false;
		$this->userComment = '';
   	$this->warnings = array();
	}
	
	private function appendTitle($i, $title) {
		foreach ($this->data[$i] as $temp) { // ignore duplicate titles in the same row
			if ($temp['title'] == $title) return;
		}
		$this->data[$i][] = array();
		$j = count($this->data[$i]) - 1;
		$this->data[$i][$j]['title'] = $title;
	}
	
	public function removeSingletonFamilyMemberRows() {
      // remove empty and singleton rows
      $i = 1;
      while ($i < count($this->data)) {
      	if (count($this->data[$i]) < 2) {
      		array_splice($this->data, $i, 1);
      	}
      	else {
      		$i++;
      	}
		}
	}

   public function readQueryParms($par) {
   	global $wgRequest, $wgUser;
   	
   	$this->formAction = $wgRequest->getVal('formAction');
      $this->namespace = $wgRequest->getVal('ns');
		$this->editToken = $wgRequest->getVal( 'wpEditToken' );
		$this->isMerging = $wgRequest->getCheck('merging');
      $this->gedcomTab = $wgRequest->getVal('gedcomtab');
      $this->gedcomKey = $wgRequest->getVal('gedcomkey');
		$this->gedcomDataString = $wgRequest->getCheck('gedcom') ? GedcomUtil::getGedcomDataString() : $wgRequest->getVal('gedcomdata');
		if ($this->gedcomDataString) {
   		$this->gedcomData = GedcomUtil::getGedcomDataMap($this->gedcomDataString);
   		$this->gedcomId = GedcomUtil::getGedcomId($this->gedcomDataString);
		}
      $this->isTrusted = CompareForm::isTrustedMerger($wgUser, $this->gedcomDataString);
		$this->addWatches = $wgUser->getOption( 'watchdefault' );
//wfDebug("merge user={$wgUser->getName()} formAction={$this->formAction} ns=$this->namespace gedcomId={$this->gedcomId} wpEditToken={$this->editToken} merging={$this->isMerging}\n");
		if (!$this->editToken) { // first phase
	      $maxPages = $wgRequest->getVal('maxpages');
	      if ($this->namespace == 'Family') {
	      	$maxChildren = $wgRequest->getVal('maxchildren');
	   		for ($i = 0; $i < $maxChildren + self::$CHILD_START_ROW; $i++) { 
   				$this->data[$i] = array(); // create a bunch of empty rows
   			}
	      }
	      else {
	      	$maxChildren = 0;
	      	$this->data[0] = array();
			}
//wfDebug("merge phase1 maxpages=$maxPages maxchildren=$maxChildren\n");	      
	      for ($i = 0; $i < $maxPages; $i++) {
	      	$pageTitle = trim($wgRequest->getVal('m_'.$i));
//wfDebug("m_$i=$pageTitle\n");	      	
	      	if ($pageTitle) {
	      		$this->appendTitle(0, $pageTitle);
		      	if ($this->namespace == 'Family') {
		      		$pageTitle = trim($wgRequest->getVal('mh_'.$i));
//wfDebug("mh_$i=$pageTitle\n");	      	
		      		if ($pageTitle) {
			      		$this->appendTitle(self::$HUSBAND_ROW, $pageTitle);
		      		}
		      		$pageTitle = trim($wgRequest->getVal('mw_'.$i));
//wfDebug("mw_$i=$pageTitle\n");
		      		if ($pageTitle) {
			      		$this->appendTitle(self::$WIFE_ROW, $pageTitle);
		      		}
		      	}
	      	}
	      }
	      if ($this->namespace == 'Family') {
	      	for ($c = 0; $c < $maxChildren; $c++) {
	      		for ($i = 0; $i < $maxPages; $i++) {
	      			if ($wgRequest->getVal('m_'.$i)) {
			     			$mergeRow = $wgRequest->getVal("mcr_{$i}_$c");
//wfDebug("mcr_{$i}_$c=$mergeRow\n");
	      				if ($mergeRow) {
	      					$childTitle = trim($wgRequest->getVal("mc_{$i}_$c"));
//wfDebug("mc_{$i}_$c=$childTitle\n");
	      					$this->appendTitle($mergeRow - 1 + self::$CHILD_START_ROW, $childTitle);
	      				}
	      			}
	      		}
	      	}
	      	
	      	$this->removeSingletonFamilyMemberRows();
	      }
		}
		else { // second phase: merges = #merges; merges_m = #pages in merge m; merges_m_p = page title; rows_m = #rows in merge m; 
			    // add_m_p_r = 1 if checked; key_m_p_r = key of row r
			$mergesCount = $wgRequest->getVal('merges');
//wfDebug("merge phase2 merges=$mergesCount\n");			
			for ($m = 0; $m < $mergesCount; $m++) {
				$pagesCount = $wgRequest->getVal("merges_$m");
				$rowsCount = $wgRequest->getVal("rows_$m");
				$this->target[$m] = $wgRequest->getVal("target_$m");
//wfDebug("merges_$m=$pagesCount rows_$m=$rowsCount target_$m={$this->target[$m]}\n");
				$this->data[$m] = array();
				$this->add[$m] = array();
				$this->key[$m] = array();
				$pos = 0;
				$namespace = ($this->namespace == 'Family' && $m == 0 ? 'Family' : 'Person');
				for ($p = 0; $p < $pagesCount; $p++) {
					$pageTitle = trim($wgRequest->getVal("merges_{$m}_{$p}"));
//wfDebug("merges_{$m}_{$p}=$pageTitle\n");					
					if ($pageTitle) {
						$this->data[$m][$pos] = array();
						$this->add[$m][$pos] = array();
						$this->key[$m][$pos] = array();
						$this->data[$m][$pos]['title'] = $pageTitle;
//						$this->data[$m][$pos]['missingdata'] = false;
						for ($r = 0; $r < $rowsCount; $r++) {
							$this->add[$m][$pos][$r] = $wgRequest->getVal("add_{$m}_{$p}_{$r}");
							$this->key[$m][$pos][$r] = $wgRequest->getVal("key_{$m}_{$p}_{$r}");
//wfDebug("add_{$m}_{$p}_{$r}={$this->add[$m][$pos][$r]} key_{$m}_{$p}_{$r}={$this->key[$m][$pos][$r]}\n");							
//							if ($this->key[$m][$pos][$r] && !$this->add[$m][$pos][$r]) {
//								$this->data[$m][$pos]['missingdata'] = true;
//							}
						}
						$pos++;
					}
					else {
						error_log("merge readQueryParms page title missing user={$wgUser->getName()}");
					}
				}
			}
			$this->userComment = $wgRequest->getVal("userComment");
		}
   }
   
   public function getNotMatchResults() {
   	global $wgUser;

     	if (count($this->data[0]) < 2) {
   		return "Please press the <b>back</b> button on your browser and <b>check the boxes</b> under the title of each {$this->namespace} that should not be merged.";
   	}
   	$skin =& $wgUser->getSkin();
   	$targetTitle = Title::newFromText($this->data[0][0]['title'], ($this->namespace == 'Family' ? NS_FAMILY : NS_PERSON));
   	$output = '<ul>The following pages have been marked <i>not a match</i> with <b>'.$skin->makeKnownLinkObj($targetTitle)."</b>:\n";
   	$noMergeTemplates = array();
		for ($j = 1; $j < count($this->data[0]); $j++) {
			$sourceTitle = Title::newFromText($this->data[0][$j]['title'], $this->namespace == 'Family' ? NS_FAMILY : NS_PERSON);
			$noMergeTemplates[] = '{{nomerge|'.$sourceTitle->getPrefixedText().'}}';
			$output .= '<li>'.$skin->makeKnownLinkObj($sourceTitle)."</li>\n";

   	}
		$article = new Article($targetTitle->getTalkPage(), 0);
		if ($article) {
			$targetTalkContents = $article->fetchContent();
			if ($targetTalkContents) {
				$targetTalkContents = rtrim($targetTalkContents) . "\n\n";
			}
			$article->doEdit($targetTalkContents . join("\n", $noMergeTemplates), 'Add nomerge template');
			if ($this->addWatches) {
   			StructuredData::addWatch($wgUser, $article, true);
			}
		}
		$output .= '</ul>';
   	
   	return $output;
   }
   
   public function isSecondPhase() {
   	return $this->isMerging == true;
   }
   
   public function getMergesCount() {
   	return count($this->data);
   }
   
   public function isGedcom() {
   	return isset($this->gedcomDataString) && $this->gedcomDataString != '';
   }
   
   // read data and validate merge
   // return false if there are warnings
   public function preMerge() {
   	global $wgUser;

   	$skin =& $wgUser->getSkin();
   	
   	if ($this->isSecondPhase()) {
	   	$this->getMergeTargets();
	   	$this->createTargetPages();
	   	$this->readData();
	   	$this->createTargetTalkPages();
	   	$this->getUpdatable();
	   	if (!$this->editToken || !$wgUser->matchEditToken( $this->editToken)) {
	   		$this->warnings[] = 'Not logged in, or session data lost; please try again.';
	   	}
   	}
   	else {
	   	$this->readData();
	   	$this->getUpdatable();
	   	$this->setMergeTargets();
	   	$this->sortData();
   	}
   	
   	if (!$this->validateMergeTargets()) {
   		$this->warnings[] = 'Something is wrong.  The merge target is invalid.';
   	}

   	if (count($this->nomerges) > 0) {
   		$warning = 'The following pages cannot be merged. (see comments on the talk page):<ul>';
   		foreach ($this->nomerges as $nomerge) {
				$warning .= '<li>'.$skin->makeKnownLinkObj($nomerge->getTalkPage(), htmlspecialchars($nomerge->getTalkPage()->getPrefixedText())).'</li>';
   		}
   		$warning .= '</ul>';
   		$this->warnings[] = $warning;
		}
		
		$seenTitles = array();
		$maxWidth = 0;
		for ($m = 0; $m < count($this->data); $m++) {
			if (count($this->data[$m]) > $maxWidth) $maxWidth = count($this->data[$m]);
			$husbands = array();
			$wives = array();
			$children = array();
			$parentFamilies = array();
			$spouseFamilies = array();
			$rowGender = '';
			for ($p = 0; $p < count($this->data[$m]); $p++) {
				$fullTitle = ($m == 0 && $this->namespace == 'Family' ? 'Family:' : 'Person:') . $this->data[$m][$p]['title'];
				if (@$seenTitles[$fullTitle]) {
					$this->warnings[] = '<b>'.htmlspecialchars($fullTitle).'</b> appears in multiple rows.'.
						' If the same person appears in different families, they must be merged together in the same row.';
				}
				$seenTitles[$fullTitle] = 1;
				
				// verify that all pages in row 0 (the primary row) exist
				if ($m == 0 && !$this->data[$m][$p]['revid'] && !$this->data[$m][$p]['gedcom']) {
						$this->warnings[] = '<b>'.htmlspecialchars($this->data[$m][$p]['title']).'</b> not found.  This page cannot be merged.';
				}
				
				// verify that the pages being merged in this row won't have overlapping husbands/wives/children or child_of_families/spouse_of_families
				if ($m == 0 && $this->namespace == 'Family' && $this->data[$m][$p]['revid']) {
					foreach ($this->data[$m][$p]['husbands'] as $temp) $husbands[] = $temp['title'];
					foreach ($this->data[$m][$p]['wives'] as $temp) $wives[] = $temp['title'];
					foreach ($this->data[$m][$p]['children'] as $temp) $children[] = $temp['title'];
				}
				else if ($this->data[$m][$p]['revid']) {
					foreach ($this->data[$m][$p]['child_of_families'] as $temp) $parentFamilies[] = $temp['title'];
					foreach ($this->data[$m][$p]['spouse_of_families'] as $temp) $spouseFamilies[] = $temp['title'];
					if (!$rowGender) {
						$rowGender = $this->data[$m][$p]['gender'];
					}
					else if ($this->data[$m][$p]['gender'] && $this->data[$m][$p]['gender'] != $rowGender) {
						$this->warnings[] = '<b>'.htmlspecialchars($this->data[$m][$p]['title']).'</b> has a different gender than the page(s) being merged.  These people cannot be merged.';
					}
				}
			}
			$inter = array_intersect($husbands, $wives);
			if (count($inter) > 0) $this->warnings[] = '<b>'.htmlspecialchars($inter[0]).'</b> appears as both a husband and a wife.  These families cannot be merged.';
			$inter = array_intersect($husbands, $children);
			if (count($inter) > 0) $this->warnings[] = '<b>'.htmlspecialchars($inter[0]).'</b> appears as both a husband and a child.  These families cannot be merged.';
			$inter = array_intersect($children, $wives);
			if (count($inter) > 0) $this->warnings[] = '<b>'.htmlspecialchars($inter[0]).'</b> appears as both a wife and a child.  These families cannot be merged.';
			$inter = array_intersect($parentFamilies, $spouseFamilies);
			if (count($inter) > 0) $this->warnings[] = '<b>'.htmlspecialchars($inter[0]).'</b> appears as both a parent family and a spouse family. These people cannot be merged.';
		}
		
		if ($maxWidth < 2) {
			$this->warnings[] = '<b>No pages to merge.</b> You need to check the boxes above the pages you want to merge.';
		}
		else if ($maxWidth > self::MAX_MERGES) {
			$this->warnings[] = '<b>Too many pages to merge.</b> Sorry - you can merge up to '.self::MAX_MERGES.' pages into a single page.'.
				' If you need to merge more, please divide them into groups of '.self::MAX_MERGES.', merge the pages in each group, then merge the groups.';
		}
		
		if ($this->isGedcom() && !$this->gedcomId) {
			$this->warnings[] = "Something's wrong - we're missing the GEDCOM id; please refresh your browser and try again.";
		}
		
		return count($this->warnings) == 0;
   }
   
   public function getWarnings() {
   	
   	$output = '';
   	foreach ($this->warnings as $warning) {
   		$output .= "<p>$warning</p>\n";
   	}
   	return $output;
   }

   public function getMaxMergingPagesCount() {
   	$max = 0;
   	foreach ($this->data as &$datai) {
			if ($max < count($datai)) {
				$max = count($datai);
			}
   	}
   	return $max;
   }
   
   public function getFormAction() {
   	return $this->formAction;
   }
   
   public function getMergeTargetTitle() {
   	$title = null;
		$titleString = @$this->data[0][0]['title'];
		if ($titleString) {
			$title = Title::newFromText($titleString, $this->namespace == 'Family' ? NS_FAMILY : NS_PERSON);
		}
		return $title;
   }
   
/////////////////////
// functions for both phase one and two
/////////////////////

   private function doNotMerge(&$datai, &$title) {
		$nomergeTitles = CompareForm::getNomergeTitleStrings($title);
   	foreach ($nomergeTitles as $nomergeTitle) {
  			foreach ($datai as &$dataij) {
  				if ($nomergeTitle == $dataij['title']) {
  					return true;
   			}
   		}
   	}
   	return false;
   }
   
   private function readData() {
   	$first = true;
		$familyMembers = array();
   	
   	foreach ($this->data as &$datai) {
			$j = 0;
			while ($j < count($datai)) {
				$dataij =& $datai[$j];
				if (GedcomUtil::isGedcomTitle($dataij['title'])) {
					$xml = GedcomUtil::getGedcomXml($this->gedcomData, $dataij['title']);
					if ($xml['merged'] == 'true' && (string)$xml['match'] == (string)$datai[count($datai)-1]['title']) {
						$this->redirects[] = Title::newFromText($dataij['title'], $this->namespace =='Family' && $first ? NS_FAMILY : NS_PERSON);
						array_splice($datai, $j, 1); // remove this page from the merge
						$j--;
					}
					else {
						$dataij['gedcom'] = true;
						$dataij['object'] = null;
						$dataij['revid'] = false;
						$dataij['talkrevid'] = false;
						MergeForm::readXmlData(($this->namespace == 'Family' && $first), $xml, $dataij);
						$dataij['contents'] = GedcomUtil::getGedcomContents($this->gedcomData, $dataij['title']);
					}
				}
				else {
					$dataij['gedcom'] = false;
					if ($this->namespace == 'Family' && $first) {
						$title = Title::newFromText($dataij['title'], NS_FAMILY);
						$dataij['object'] = new Family($dataij['title']);
					}
					else {
						$title = Title::newFromText($dataij['title'], NS_PERSON);
						$dataij['object'] = new Person($dataij['title']);
					}
					$dataij['revid'] = $title->getLatestRevID(GAID_FOR_UPDATE); // make sure you read the master db
					$dataij['object']->loadPage($dataij['revid']);
					$xml = $dataij['object']->getPageXml();
					$talkTitle = $title->getTalkPage();
					$dataij['talkrevid'] = $talkTitle->getLatestRevID(GAID_FOR_UPDATE); // make sure you read the master db
					// this must be a family member 
					// non-family members can appear in gedcom updates -- gedcom page has been matched to a page in another family
					// or they can appear when the family is do not merge
					if ($this->namespace == 'Family' && !$first && !@$familyMembers[$dataij['title']]) { 
						array_splice($datai, $j, 1); // remove this page from the merge
						$j--;
					}
					else if ($this->doNotMerge($datai, $title)) {
						$this->nomerges[] = $title;
					}
					else if (isset($xml)) {
						MergeForm::readXmlData(($this->namespace == 'Family' && $first), $xml, $dataij);
						$dataij['contents'] =& $dataij['object']->getPageContents();
						if ($this->namespace == 'Family' && $first) {
							foreach ($xml->husband as $m) {
								$familyMembers[(string)$m['title']] = 1;
							}
							$data['wifeTitle'] = array();
							foreach ($xml->wife as $m) {
								$familyMembers[(string)$m['title']] = 1;
							}
							$data['childTitle'] = array();
							foreach ($xml->child as $m) {
								$familyMembers[(string)$m['title']] = 1;
							}
						}
					}
					else if (StructuredData::isRedirect($dataij['object']->getPageContents())) {
						$this->redirects[] = $title;
						array_splice($datai, $j, 1); // remove this page from the merge
						$j--;
					}
					else if ($dataij['revid']) { // page exists but doesn't have xml and is not a redirect
						array_splice($datai, $j, 1); // remove this page from the merge
						$j--;
						error_log("ERROR: Merging page exists without XML: ".$title->getPrefixedText()."\n");
					}
				}
				$j++;
			}
   		$first = false;
   	}
   	
   	$this->removeSingletonFamilyMemberRows();
   }
   
   public function getUpdatable() {
   	for ($m = 0; $m < count($this->data); $m++) {
//   		$addedData = false;
   		for ($p = 0; $p < count($this->data[$m]); $p++) {
				if (!GedcomUtil::isGedcomTitle($this->data[$m][$p]['title'])) {
					$this->data[$m][$p]['updatable'] = CompareForm::isUpdatable($this->data[$m][$p]['object']->getTitle(), $this->data[$m][$p]['contents']);
				}
	   			
//	   		if ($this->isGedcom() && $this->isSecondPhase() && $p > 0) {
//					for ($r = 0; $r < count($this->add[$m][$p]); $r++) {
//						if ($this->add[$m][$p][$r]) {
//							$addedData = true;
//							break;
//						}
//					}
//	   		}
   		}
   		// Don't update if the user hasn't added/removed any data
   		// We could do this for normal merges as well, but we might need to make Family always updatable
//	   	if ($this->isGedcom() && $this->isSecondPhase()) { 
//				if (!$addedData && !$this->data[$m][0]['missingdata']) {
//					$this->data[$m][0]['updatable'] = false;
//				}
//	   	}
   	}
   }
   
   // if the target is updatable, all of the sources had better be updatable
   public function validateMergeTargets() {
   	if ($this->isTrusted) return true;
   	
   	foreach ($this->data as &$datai) {
  			if ($datai[0]['updatable']) {
	   		for ($j = 1; $j < count($datai); $j++) {
	   			if (!GedcomUtil::isGedcomTitle($datai[$j]['title']) && !$datai[$j]['updatable']) return false;
	   		}
   		}
   	}
   	return true;
   }
   
   private function getNonmergedPages() {
   	global $wgUser;
   	
   	$output = '';
   	if ($this->namespace == 'Family') {
	   	$sk =& $wgUser->getSkin();
	   	$mergingPeople = array();
	   	for ($m = 1; $m < count($this->data); $m++) {
	   		for ($p = 0; $p < count($this->data[$m]); $p++) {
					$titleString = $this->data[$m][$p]['title'];
					$mergingPeople[$titleString] = 1;
	   		}
	   	}
	   	$nonmergedPeople = array();
	   	for ($p = 0; $p < count($this->data[0]); $p++) {
	   		foreach ($this->data[0][$p]['husbands'] as $temp) if (!@$mergingPeople[$temp['title']]) $nonmergedPeople[$temp['title']] = 1;
	   		foreach ($this->data[0][$p]['wives'] as $temp) if (!@$mergingPeople[$temp['title']]) $nonmergedPeople[$temp['title']] = 1;
	   		foreach ($this->data[0][$p]['children'] as $temp) if (!@$mergingPeople[$temp['title']]) $nonmergedPeople[$temp['title']] = 1;
	   	}
	   	foreach ($nonmergedPeople as $k => $v) {
	   		$titleLink = '';
	   		if (GedcomUtil::isGedcomTitle($k)) {
					$xml = GedcomUtil::getGedcomXml($this->gedcomData, $k);
					if (!(string)$xml['match']) $titleLink = 'Person:'.htmlspecialchars($k);
	   		}
	   		else {
		   		$t = Title::newFromText($k, NS_PERSON);
	   			$titleLink = $sk->makeLinkObj($t);
				}
				if ($titleLink) {
	   			$output .= '<li>' . $titleLink . '</li>';
				}
	   	}
	   	if ($output) $output = '<ul>' . $output . '</ul>';
   	}
   	
   	return $output;
   }
   
/////////////////////
// phase one functions
/////////////////////

   private function setMergeTargets() {
   	$first = true;
   	
   	foreach ($this->data as &$datai) {
   		if (count($datai) > 1) {
   			$lowPageColumn = -1;
   			$lowPageId = 0;
   			$lowPageUpdatable = true;
   			for ($j = 0; $j < count($datai); $j++) {
   				if (!GedcomUtil::isGedcomTitle($datai[$j]['title'])) {
	   				if ($this->namespace == 'Family' && $first) {
	   					$title = Title::newFromText($datai[$j]['title'], NS_FAMILY);
	   				}
	   				else {
	   					$title = Title::newFromText($datai[$j]['title'], NS_PERSON);
	   				}
	   				$pageId = $title->getArticleID();
	   				if ($pageId > 0 && ($lowPageColumn == -1 || 
	   										  ($lowPageUpdatable && !$datai[$j]['updatable']) || 
	   										  ($lowPageUpdatable == $datai[$j]['updatable'] && $pageId < $lowPageId))) {
	   					$lowPageColumn = $j;
	   					$lowPageId = $pageId;
	   					$lowPageUpdatable = $datai[$j]['updatable'];
	   				}
   				}
   			}
   			if ($lowPageColumn == -1) { // none of the pages exist, so get one with the lowest index #
   				for ($j = 0; $j < count($datai); $j++) {
   					$matches = array();
						if (preg_match('/\((\d+)\)$/', $datai[$j]['title'], $matches) && ($lowPageColumn == -1 || $matches[1] < $lowPageId)) {
							$lowPageColumn = $j;
							$lowPageId = $matches[1];
						}
   				}
   			}
   			$reorder = array();
   			$reorder[] = $datai[$lowPageColumn];
   			for ($j = count($datai) - 1; $j >= 0; $j--) {
   				if (!GedcomUtil::isGedcomTitle($datai[$j]['title']) && $j != $lowPageColumn) {
   					$reorder[] = $datai[$j];
   				}
   			}
   			// put gedcom pages last
   			for ($j = count($datai) - 1; $j >= 0; $j--) { 
   				if (GedcomUtil::isGedcomTitle($datai[$j]['title'])) {
   					$reorder[] = $datai[$j];
   				}
   			}
   			$datai = $reorder;
   		}
   		$first = false;
   	}
   }
   
   // don't use this for sorting
   private static function compareNames($a, $b) {
   	return 1; // so getNext returns the names in order
   }
   
   public static function compareEvents($a, $b) {
   	if ($a['key'] == $b['key']) {
   		return 0;
   	}
   	if ($a['stddate'] && !$b['stddate']) {
   		if ($b['stdtype'] == Person::$BIRTH_TAG || $b['stdtype'] == Person::$CHR_TAG) {
   			return 1;
   		}
   		else {
   			return -1;
   		}
   	}
   	else if (!$a['stddate'] && $b['stddate']) {
   		if ($a['stdtype'] == Person::$BIRTH_TAG || $a['stdtype'] == Person::$CHR_TAG) {
   			return -1;
   		}
   		else {
   			return 1;
   		}
   	}
   	$cmp = strcmp($a['stddate'], $b['stddate']);
   	if ($cmp < 0) {
   		return -1;
   	}
   	else if ($cmp > 0) {
   		return 1;
   	}
   	else { // dates are equal (may be empty)
   		if (!$a['stddate'] && !$b['stddate']) {
   			if (($a['stdtype'] == Person::$BIRTH_TAG || $a['stdtype'] == Person::$CHR_TAG) &&
   				!($b['stdtype'] == Person::$BIRTH_TAG || $b['stdtype'] == Person::$CHR_TAG)) {
   				return -1;
   			}
   			else if (!($a['stdtype'] == Person::$BIRTH_TAG || $a['stdtype'] == Person::$CHR_TAG) &&
   						 ($b['stdtype'] == Person::$BIRTH_TAG || $b['stdtype'] == Person::$CHR_TAG)) {
   				return 1;
   			}
   		}
   		return ($a['key'] < $b['key']) ? -1 : 1;
   	}
   }
   
   public static function compareIds($a, $b) {
   	if ($a['id'] == $b['id']) {
   		return 0;
   	}
   	$aId = intval(substr($a['id'], 1));
   	$bId = intval(substr($b['id'], 1));
   	return ($aId < $bId) ? -1 : 1;
   }
   
   private function sortData() {
   	foreach ($this->data as &$datai) {
   		if (count($datai) > 1) {
	   		foreach ($datai as &$dataij) {
		  			// don't sort names
		  			if (count(@$dataij['events']) > 1) {
	   				usort($dataij['events'], array('MergeForm', 'compareEvents'));
		  			}
		  			if (count(@$dataij['sources']) > 1) {
   					usort($dataij['sources'], array('MergeForm', 'compareIds'));
		  			}
		  			if (count(@$dataij['images']) > 1) {
   					usort($dataij['images'], array('MergeForm', 'compareIds'));
		  			}
		  			if (count(@$dataij['notes']) > 1) {
	   				usort($dataij['notes'], array('MergeForm', 'compareIds'));
		  			}
	   		}
   		}
   	}
   }
   
   private function formatContents(&$elm) {
   	return trim((string)$elm);
   	//return trim(str_replace(ESINHandler::ESIN_FOOTER_TAG, '', $elm));
   }
   
   private function formatGender(&$elm) {
   	return $elm;
   }
   
   private function labelName(&$elm) {
   	return htmlspecialchars($elm['type']);
   }
   
   private function labelEvent(&$elm) {
   	return htmlspecialchars($elm['type']);
   }
   
   private function labelSource(&$elm) {
   	return htmlspecialchars('Source');
   }
   
   private function labelImage(&$elm) {
   	return htmlspecialchars('Image');
   }
   
   private function labelNote(&$elm) {
   	return htmlspecialchars('Note');
   }
   
	private function matchName(&$baseElm, &$elm) {
   	if ($baseElm['stdtype'] != $elm['stdtype']) {
   		return self::$MATCH_NOMATCH;
   	}
   	else if ($baseElm['stdgiven'] == $elm['stdgiven'] && $baseElm['stdsurname'] == $elm['stdsurname'] && 
   				$baseElm['stdtitle_prefix'] == $elm['stdtitle_prefix'] && $baseElm['stdtitle_suffix'] == $elm['stdtitle_suffix']) {
   		return self::$MATCH_EQ;
   	}
   	else if ((!$elm['stdgiven'] || strpos($baseElm['stdgiven'], $elm['stdgiven']) !== false) && 
   				(!$elm['stdsurname'] || strpos($baseElm['stdsurname'], $elm['stdsurname']) !== false) && 
   				(!$elm['stdtitle_prefix'] || strpos($baseElm['stdtitle_prefix'], $elm['stdtitle_prefix']) !== false) && 
   				(!$elm['stdtitle_suffix'] || strpos($baseElm['stdtitle_suffix'], $elm['stdtitle_suffix']) !== false)) {
   		return self::$MATCH_SUB;
   	}
   	else if ((!$baseElm['stdgiven'] || strpos($elm['stdgiven'], $baseElm['stdgiven']) !== false) && 
   				(!$baseElm['stdsurname'] || strpos($elm['stdsurname'], $baseElm['stdsurname']) !== false) && 
   				(!$baseElm['stdtitle_prefix'] || strpos($elm['stdtitle_prefix'], $baseElm['stdtitle_prefix']) !== false) && 
   				(!$baseElm['stdtitle_suffix'] || strpos($elm['stdtitle_suffix'], $baseElm['stdtitle_suffix']) !== false)) {
   		return self::$MATCH_SUP;
   	}
   	else if ((!$baseElm['stdgivensdx'] || !$elm['stdgivensdx'] || strpos($elm['stdgivensdx'], $baseElm['stdgivensdx']) !== false || strpos($baseElm['stdgivensdx'], $elm['stdgivensdx']) !== false) && 
   				(!$baseElm['stdsurname'] || !$elm['stdsurnamesdx'] || strpos($elm['stdsurnamesdx'], $baseElm['stdsurnamesdx']) !== false || strpos($baseElm['stdsurnamesdx'], $elm['stdsurnamesdx']) !== false) && 
   				(!$baseElm['stdtitle_prefix'] || !$elm['stdtitle_prefix'] || strpos($elm['stdtitle_prefix'], $baseElm['stdtitle_prefix']) !== false || strpos($baseElm['stdtitle_prefix'], $elm['stdtitle_prefix']) !== false) && 
   				(!$baseElm['stdtitle_suffix'] || !$elm['stdtitle_suffix'] || strpos($elm['stdtitle_suffix'], $baseElm['stdtitle_suffix']) !== false || strpos($baseElm['stdtitle_suffix'], $elm['stdtitle_suffix']) !== false)) {
   		return self::$MATCH_COMPATIBLE;
   	}
   	return self::$MATCH_DIFF;
   }
   
   private function matchEvent(&$baseElm, &$elm) {
   	if ($baseElm['stdtype'] != $elm['stdtype']) {
   		return self::$MATCH_NOMATCH;
   	}
   	else if ($baseElm['stddate'] == $elm['stddate'] && $baseElm['stdplace'] == $elm['stdplace'] && $baseElm['description'] == $elm['description']) {
   		return self::$MATCH_EQ;
   	}
   	else if ((!$elm['stddate'] || strpos($baseElm['stddate'], $elm['stddate']) === 0) && 
   				(!$elm['stdplace'] || strpos($baseElm['stdplace'], $elm['stdplace']) !== false) && 
   				(!$elm['description'] || strpos($baseElm['description'], $elm['description']) !== false)) {
   		return self::$MATCH_SUB;
   	}
   	else if ((!$baseElm['stddate'] || strpos($elm['stddate'], $baseElm['stddate']) === 0) && 
   				(!$baseElm['stdplace'] || strpos($elm['stdplace'], $baseElm['stdplace']) !== false) && 
   				(!$baseElm['description'] || strpos($elm['description'], $baseElm['description']) !== false)) {
   		return self::$MATCH_SUP;
   	}
   	else if ((!$baseElm['stddate'] || !$elm['stddate'] || strpos($elm['stddate'], $baseElm['stddate']) === 0 || strpos($baseElm['stddate'], $elm['stddate']) === 0) && 
   				(!$baseElm['stdplace'] || !$elm['stdplace'] || strpos($elm['stdplace'], $baseElm['stdplace']) !== false || strpos($baseElm['stdplace'], $elm['stdplace']) !== false) && 
   				(!$baseElm['description'] || !$elm['description'] || strpos($elm['description'], $baseElm['description']) !== false || strpos($baseElm['description'], $elm['description']) !== false)) {
   		return self::$MATCH_COMPATIBLE;
   	}
   	else if (in_array($elm['stdtype'], Person::$STD_EVENT_TYPES) || in_array($elm['stdtype'], Family::$STD_EVENT_TYPES)) {
   		return self::$MATCH_DIFF;
   	}
   	return self::$MATCH_INCOMPARABLE;
   }
   
// Don't say sources, images, notes are equal anymore, since they might be ref'd by names/events and we want to keep them if they are
// Duplicates will be removed during editPage
   private function matchSource(&$baseElm, &$elm) {
//   	if ($baseElm['title'] == $elm['title'] && $baseElm['record_name'] == $elm['record_name'] && 
//   		 $baseElm['page'] == $elm['page'] && $baseElm['quality'] == $elm['quality'] &&
//   		 $baseElm['date'] == $elm['date'] && $baseElm['text'] == $elm['text']) {
//		 	if (!$elm['notes'] && !$elm['images']) {
//		 		return self::$MATCH_EQ;
//		 	}
//		 	else if (!$baseElm['notes'] && !$baseElm['images']) {
//		 		return self::$MATCH_SUP;
//		 	}
//		 	else {
//		 		return self::$MATCH_COMPATIBLE;
//		 	}
//   	}
   	return self::$MATCH_INCOMPARABLE;
   }
   
   private function matchImage(&$baseElm, &$elm) {
//   	if ($baseElm['stdfilename'] == $elm['stdfilename'] && $baseElm['caption'] == $elm['caption']) {
//   		return self::$MATCH_EQ;
//   	}
//   	else if ($baseElm['stdfilename'] == $elm['stdfilename'] && 
//   				(!$elm['caption'] || strpos($baseElm['caption'], $elm['caption']) !== false)) {
//   		return self::$MATCH_SUB;
//   	}
//   	else if ($baseElm['stdfilename'] == $elm['stdfilename'] && 
//   				(!$baseElm['caption'] || strpos($elm['caption'], $baseElm['caption']) !== false)) {
//   		return self::$MATCH_SUP;
//   	}
   	return self::$MATCH_INCOMPARABLE;
   }
   
   private function matchNote(&$baseElm, &$elm) {
//   	if ($baseElm['text'] == $elm['text']) {
//   		return self::$MATCH_COMPATIBLE;
//   	}
//   	else if ((!$elm['text'] || strpos($baseElm['text'], $elm['text']) !== false)) { 
//   		return self::$MATCH_SUB;
//   	}
//   	else if ((!$baseElm['text'] || strpos($elm['text'], $baseElm['text']) !== false)) {
//   		return self::$MATCH_SUP;
//   	}
   	return self::$MATCH_INCOMPARABLE;
   }
   
   private function printElement(&$elm) {
   	$output = '';
		foreach ($elm as $label => $value) {
			if ($value && strpos($label, 'std') !== 0 && !in_array($label, self::$UNPRINTED_ATTRS)) {
				if ($label == 'place') {
					$value = StructuredData::getPlaceLink($value);
				}
				else {
					$value = htmlspecialchars($value);
				}
				$label = wfMsg($label);
				$output .= "<i>$label</i>: $value<br>";
			}
		}
   	return $output;
   }
   
   private function getNext(&$datai, $type, $compareFn) {
   	$foundElm = null;
   	$foundJ = -1;
   	$foundK = -1;
   	for ($j = 0; $j < count($datai); $j++) {
   		$elms =& $datai[$j][$type];
   		for ($k = 0; $k < count($elms); $k++) {
   			if (is_array($elms[$k])) {
   				if ($foundElm == null || MergeForm::$compareFn($elms[$k], $foundElm) < 0) {
   					$foundJ = $j;
   					$foundK = $k;
   					$foundElm =& $elms[$k];
   				}
   				break;
   			}
   		}
   	}
   	return array($foundJ, $foundK);
   }
   
   private function getBestMatch(&$datai, $type, $nextj, $nextk, $j, $matchFn) {
   	$baseElm = $datai[$nextj][$type][$nextk];
   	$elms = $datai[$j][$type];
   	$bestRow = -1;
   	$bestMatch = self::$MATCH_NOMATCH;
   	for ($k = 0; $k < count($elms); $k++) {
   		if (is_array($elms[$k])) {
   			$match = $this->$matchFn($baseElm, $elms[$k]);
   			// if we're testing column j>0, don't return this row if it matches better with another row in column 0
   			$otherMatch = self::$MATCH_NOMATCH;
   			if ($j > 0) {
	   			$otherElms = $datai[0][$type];
	   			for ($ok = 0; $ok < count($otherElms); $ok++) {
	   				// other value must exist and not be $baseElm
	   				if (is_array($otherElms[$ok]) && !($nextj == 0 && $ok == $nextk)) {
	   					$altMatch = $this->$matchFn($otherElms[$ok], $elms[$k]);
	   					if ($altMatch > $otherMatch) $otherMatch = $altMatch;
	   				}
	   			}
   			}
   			if ($match > $bestMatch && $match >= $otherMatch) {
   				$bestRow = $k;
   				$bestMatch = $match;
   			}
   		}
   	}
   	return array($bestRow, $bestMatch);
   }

   private function getCheckbox($checked, $disabled, $mergeNumber, $pageNumber, $rowNumber) {
   	$id = "{$mergeNumber}_{$pageNumber}_{$rowNumber}";
		return "<input type=\"checkbox\" id=\"add_$id\"".($disabled && $pageNumber == 0 ? '' : " name=\"add_$id\"").
					($checked ? ' checked' : '').($disabled ? ' disabled' : '').
					" value=\"true\" onClick=\"addClick($mergeNumber,$pageNumber,$rowNumber)\"/>" .
					($disabled && $pageNumber == 0 ? "<input type=\"hidden\" name=\"add_$id\" value=\"true\"/>" : '');
	}
	
	private function getKeyInput($mergeNumber, $pageNumber, $rowNumber, $key) {
   	$id = "{$mergeNumber}_{$pageNumber}_{$rowNumber}";
		return "<input type=\"hidden\" name=\"key_$id\" value=\"".htmlspecialchars($key)."\">";		
	}
   
   private function getRows(&$datai, $width, $type, $labelFn, $matchFn, $compareFn) {
   	$last = count($datai) - 1;
   	$labelWidth = self::$LABEL_WIDTH;
   	$addWidth = self::$ADD_WIDTH;
   	$addClass = self::$CLASS_ADD;
   	$labelClass = self::$CLASS_LABEL;
   	$output = '';
   	while (true) {
   		list ($nextj, $nextk) = $this->getNext($datai, $type, $compareFn);
   		if ($nextj < 0) {
   			break;
   		}
	   	if ($nextj > 0) { // prefer last column as the "base" column, so find a matching value in the last column if you can
   			list ($k, $dummy) = $this->getBestMatch($datai, $type, $nextj, $nextk, 0, $matchFn);
   			if ($k >= 0) {
   				$nextj = 0;
   				$nextk = $k;
   			}
   		}
   		
   		$foundSup = false;
	   	$output .= "<tr><td width=\"$labelWidth%\" class=\"$labelClass\">".$this->$labelFn($datai[$nextj][$type][$nextk])."</td>\n";
			
   		for ($j = $last; $j >= 0; $j--) {
   			$value = '';
   			$key = '';
   			$class = self::$CLASS_DEFAULT;
   			$checked = false;
   			$nobox = false;
   			if ($j == $nextj) {
   				if (!$foundSup) {
   					$checked = true;
   				}
   				$k = $nextk;
   			}
   			else {
   				list($k, $match) = $this->getBestMatch($datai, $type, $nextj, $nextk, $j, $matchFn);
   				if ($match == self::$MATCH_NOMATCH) {
		   			$nobox = true;
		   		}
		   		else if ($match == self::$MATCH_EQ) {
		   			$class = self::$CLASS_MATCH;
		   			//$nobox = true;
		  			}
		  			else if ($match == self::$MATCH_SUP) {
		  				$class = self::$CLASS_PARTIAL;
		  				$checked = true;
		  				$foundSup = true;
		  			}
		  			else if ($match == self::$MATCH_SUB) {
		  				$class = self::$CLASS_PARTIAL;
		  			}
		  			else if ($match == self::$MATCH_COMPATIBLE) {
		  				$class = self::$CLASS_PARTIAL;
		  				$checked = true;
		  			}
		  			else if ($match == self::$MATCH_DIFF) {
		  				$class = self::$CLASS_DIFF;
		  				$checked = true;
		  			}
		  			else { // $match == self::$MATCH_INCOMPARABLE
		  				$checked = true;
		  			}
   			}
	  			if ($this->isGedcom() && $j == $last) {
  					$checked = false;
  					$foundSup = false;
  					$gedcomClass = ' '.self::$CLASS_GEDCOM_SOURCE;
	  			}
	  			else if ($this->isGedcom() && $j == 0) {
	  				$checked = true;
	  				$gedcomClass = ' '.self::$CLASS_GEDCOM_TARGET;
				}
	  			else {
	  				$gedcomClass = '';
				}
   			$disabled = ($datai[0]['updatable'] || $this->isTrusted ? false : true);
   			if ($disabled && $j == 0) $checked = true;
	  			if ($checked) {
	  				$class .= ' ' . self::$CLASS_CHECKED;
	  			}
	  			else {
	  				$class .= ' ' . self::$CLASS_UNCHECKED;
	  			}
   			
   			if ($k >= 0) {
	   			$value = $this->printElement($datai[$j][$type][$k]);
	   			$key = $datai[$j][$type][$k]['key'];
   				$datai[$j][$type][$k] = null; // clear it so we don't print it again
   			}
   			$checkbox = $nobox ? '&nbsp;' : $this->getCheckbox($checked, $disabled, $this->mergeNumber, $j, $this->rowNumber);
   			$keyInput = $this->getKeyInput($this->mergeNumber, $j, $this->rowNumber, $key);
   			$id = "value_{$this->mergeNumber}_{$j}_{$this->rowNumber}";
   			if (!$value) $value = '&nbsp;';
				$output .= "<td width=\"$addWidth%\" class=\"{$addClass}{$gedcomClass}\">{$checkbox}{$keyInput}</td><td id=\"$id\" width=\"$width%\" class=\"$class\">$value</td><td></td>\n";
   		}
			$this->rowNumber++;
   		$output .= '</tr>';
   	}
   	return $output;
   }
   
   private function getRow(&$datai, $width, $label, $formatFn) {
   	$found = false;
   	$values = array();
   	for ($j = 0; $j < count($datai); $j++) {
   		if (isset($datai[$j][$label])) {
   			$values[$j] = $this->$formatFn($datai[$j][$label]);
   			if ($values[$j]) {
   				$found = true;
   			}
   		}
   		else {
   			$values[$j] = '';
   		}
   	}
   	if (!$found) {
   		return '';
   	}
   	
   	$last = count($datai) - 1;
   	$labelWidth = self::$LABEL_WIDTH;
		$addWidth = self::$ADD_WIDTH;
		$addClass = self::$CLASS_ADD;
   	$labelClass = self::$CLASS_LABEL;
   	$labelString = wfMsg($label);
   	$output = "<tr><td width=\"$labelWidth%\" class=\"$labelClass\">$labelString</td>\n";
   	for ($j = $last; $j >= 0; $j--) {
   		$value =& $values[$j];
   		$class = self::$CLASS_DEFAULT;
  			$checked = false;
   		$nobox = false;
   		$disabled = false;

   		if (!$value) {
   			$nobox = true;
   		}
   		else if ($j > 0 && mb_strpos($values[0], $value) !== false) {
   			$class = self::$CLASS_MATCH;
   			$nobox = true;
  			}
  			else if ($label == 'gender' && $j > 0 && $value != $values[0]) {
  				$class = self::$CLASS_DIFF;
  				$nobox = true;
  			}
  			else if ($label == 'gender' && $j == 0) {
  				$checked = true;
  				$disabled = true;
  			}
  			else {
  				$checked = true;
  			}
			if ($this->isGedcom() && $j == $last) {
				$checked = false;
				$gedcomClass = ' '.self::$CLASS_GEDCOM_SOURCE;
			}
  			else if ($this->isGedcom() && $j == 0) {
  				$checked = true;
  				$gedcomClass = ' '.self::$CLASS_GEDCOM_TARGET;
			}
			else {
				$gedcomClass = '';
			}
			if (!$datai[0]['updatable'] && !$this->isTrusted) $disabled = true;
  			if ($disabled && $j == 0) $checked = true;
  			if ($checked) {
  				$class .= ' ' . self::$CLASS_CHECKED;
  			}
  			else {
  				$class .= ' ' . self::$CLASS_UNCHECKED;
  			}
  			$checkbox = $nobox ? '&nbsp;' : $this->getCheckbox($checked, $disabled, $this->mergeNumber, $j, $this->rowNumber);
  			$keyInput = $this->getKeyInput($this->mergeNumber, $j, $this->rowNumber, $label);
  			$id = "value_{$this->mergeNumber}_{$j}_{$this->rowNumber}";
  			if ($value) {
  				$value = htmlspecialchars($value);
  			}
  			else {
  				$value = '&nbsp;';
  			}
			$output .= "<td width=\"$addWidth%\" class=\"{$addClass}{$gedcomClass}\">{$checkbox}{$keyInput}</td><td id=\"$id\" width=\"$width%\" class=\"$class\">".
							($label == 'contents' ? '<div style="height:75px; overflow:auto">' : '').$value.($label == 'contents' ? '</div>' : '').
							"</td><td></td>\n";
   	}
   	$this->rowNumber++;
   	$output .= '</tr>';
   	return $output;
   }
   
   private function getTitleRow(&$datai, $width, $ns) {
   	global $wgUser;

   	$skin =& $wgUser->getSkin();
   	$labelWidth = self::$LABEL_WIDTH;
		$addWidth = self::$ADD_WIDTH;
		$headerClass = self::$CLASS_HEADER;
   	$labelClass = self::$CLASS_LABEL;
   	$output = "<tr><td width=\"$labelWidth%\" class=\"$labelClass\"></td>\n";
   	$last = count($datai) - 1;
   	for ($j = $last; $j >= 0; $j--) {
			$dataij =& $datai[$j];
			$titleOut = htmlspecialchars($dataij['title']);
			if ($dataij['gedcom']) {
				$titleLink = $titleOut;
			}
			else {
   			$titleLink = $skin->makeLinkObj(Title::newFromText($dataij['title'], $ns), $titleOut);
			}
   		$output .= "<td width=\"$addWidth%\" class=\"$headerClass\">Keep</td><td width=\"$width%\" class=\"$headerClass\">".
   						"<b>$titleLink</b><input type=\"hidden\" name=\"merges_{$this->mergeNumber}_{$j}\" value=\"$titleOut\"></td><td></td>\n";
   	}
   	$output .= '</tr>';
   	return $output;
   }
   
   private function getTargetsRow(&$datai, $width, $ns) {
   	global $wgUser;

   	$labelWidth = self::$LABEL_WIDTH;
		$addWidth = self::$ADD_WIDTH;
		$headerClass = self::$CLASS_HEADER;
   	$labelClass = self::$CLASS_LABEL;
   	$label = ($this->gedcomDataString ? '' : '<i>Target</i>');
   	$output = "<tr><td width=\"$labelWidth%\" class=\"$labelClass\">$label</td>\n";
   	$last = count($datai) - 1;
   	for ($j = $last; $j >= 0; $j--) {
			$semiProtected = (!$datai[0]['updatable'] && $j == 0 ? "<font color=\"red\">Semi-protected</font> (see below)<br>" : '');
   		if (!$datai[0]['updatable'] && !$this->isTrusted) {
   			$radioButton = ($j == 0 ? "$semiProtected<input type=\"hidden\" name=\"target_{$this->mergeNumber}\" value=\"0\">" : '');
			}
			else if ($this->gedcomDataString) {
				$radioButton = ($j == 0 ? "<input type=\"hidden\" name=\"target_{$this->mergeNumber}\" value=\"0\">" : '');
			}
			else if ($datai[$j]['revid'] || $j == 0) { // if page exists or this is the target column
				$checked = ($j == 0 ? ' checked' : '');
				$radioButton = "$semiProtected<input type=\"radio\" name=\"target_{$this->mergeNumber}\" value=\"$j\"$checked>";
			}
			else {
				$radioButton = '';
			}
   		$output .= "<td width=\"$addWidth%\" class=\"$headerClass\"></td><td width=\"$width%\" class=\"$headerClass\">$radioButton</td><td></td>\n";
   	}
   	$output .= '</tr>';
   	return $output;
   }
   
   private function insertSeparatorRow(&$datai, $width, $extra = '') {
   	$cols = count($datai)*3 + 1;
   	$class = self::$CLASS_SEPARATOR;
		return "<tr><td colspan=\"$cols\" class=\"$class\">&nbsp;$extra</td></tr>";
   }
   
   private function getPagesWidth() {
   	$maxPages = 0;
   	foreach ($this->data as &$datai) {
   		if (count($datai) > $maxPages) {
   			$maxPages = count($datai);
   		}
   	}
   	if ($maxPages == 0) {
   		$maxPages = 1;
   	}
   	$width = floor(((100 - self::$LABEL_WIDTH) / $maxPages) - self::$ADD_WIDTH);
   	if ($width < 10) {
   		$width = 10;
   	}
   	return array($maxPages, $width);
   }

   public function getMergeResults() {
   	global $wgUser;
   	
   	list ($maxPages, $width) = $this->getPagesWidth();

   	$output = '';
   	if (count($this->redirects) > 0) {
	   	$skin =& $wgUser->getSkin();
   		$output .= '<b>The following pages have already been merged</b><ul>';
   		foreach ($this->redirects as $redir) {
   			if (GedcomUtil::isGedcomTitle($redir->getText())) {
   				$titleLink = htmlspecialchars($redir->getPrefixedText());
				}
				else {
					$titleLink = $skin->makeKnownLinkObj($redir, htmlspecialchars($redir->getPrefixedText()), 'redirect=no');
				}
				$output .= "<li>$titleLink</li>\n";
   		}
   		$output .= '</ul>';
		}
   	
   	$first = true;
		$editToken = htmlspecialchars($wgUser->editToken());
		if ($this->isGedcom()) {
			$gedcomField = "<input type=\"hidden\" name=\"gedcomdata\" value=\"".htmlspecialchars($this->gedcomDataString)."\">";
			$gedcomExtra = '?gedcomtab='.htmlspecialchars($this->gedcomTab).'&gedcomkey='.htmlspecialchars($this->gedcomKey);
		}
		else {
			$gedcomField = '';
			$gedcomExtra = '';
		}
		$output .= <<< END
<form id="merge" name="merge" action="/wiki/Special:Merge$gedcomExtra" method="post">
<input type="hidden" name="merging" value="true">
<input type="hidden" name="ns" value="{$this->namespace}">
<input type="hidden" name="wpEditToken" value="$editToken">
$gedcomField
<table border="0" cellspacing="0" cellpadding="4">
END;
		$this->mergeNumber = 0;
		$hiddenFields = '';
		$semiProtected = false;
   	foreach ($this->data as &$datai) {
   		$pageCount = count($datai);
   		if ($pageCount > 1) {
				$this->rowNumber = 0;
   			$output .= $this->insertSeparatorRow($datai, $width, $hiddenFields);
   			$ns = ($this->namespace == 'Person' || !$first ? NS_PERSON : NS_FAMILY);
   			$output .= $this->getTitleRow($datai, $width, $ns);
   			$output .= $this->getTargetsRow($datai, $width, $ns);
   			if ($ns == NS_PERSON) {
   				$output .= $this->getRows($datai, $width, 'names', 'labelName', 'matchName', 'compareNames');
   				$output .= $this->getRow($datai, $width, 'gender', 'formatGender');
   			}
   			$output .= $this->getRows($datai, $width, 'events', 'labelEvent', 'matchEvent', 'compareEvents');
   			$output .= $this->getRows($datai, $width, 'sources', 'labelSource', 'matchSource', 'compareIds');
   			$output .= $this->getRows($datai, $width, 'images', 'labelImage', 'matchImage', 'compareIds');
   			$output .= $this->getRows($datai, $width, 'notes', 'labelNote', 'matchNote', 'compareIds');
   			$output .= $this->getRow($datai, $width, 'contents', 'formatContents');
   			$hiddenFields .= "<input type=\"hidden\" name=\"merges_{$this->mergeNumber}\" value=\"$pageCount\"><input type=\"hidden\" name=\"rows_{$this->mergeNumber}\" value=\"{$this->rowNumber}\">";
   			$this->mergeNumber++;
				if (!$datai[0]['updatable']) {
					$semiProtected = true;
				}
   		}
   		$first = false;
   	}
   	
   	$cols = $maxPages*3 + 1;
   	$nonmergedPages = $this->getNonmergedPages();
		if ($nonmergedPages) {
			$nonmergedPages = '<p>In addition to people listed above, the following will also be included in the target family' . 
										($this->isGedcom() ? '<br/>(GEDCOM people listed will be added when the GEDCOM imported)' : '') .
										$nonmergedPages . "</p>\n";
		}
		$semiProtectedMsg = ($semiProtected ? '<br>'.CompareForm::getSemiProtectedMessage($this->isTrusted) : '');
		if ($this->isGedcom()) {
			$cancelFunction = 'doCancelGedcom()';
			$mergeFunction = 'doMergeGedcom()';
			$mergeLabel = 'Update';
			$mergeSummary = '';
			$mergeTitle = 'Update the existing page(s) with the checked information from your GEDCOM';
		}
		else {
			$cancelFunction = 'doCancel()';
			$mergeFunction = 'doMerge()';
			$mergeLabel = 'Merge';
			$mergeSummary = 'Merge Summary: &nbsp; <input type="text" size="25" name="userComment"> &nbsp; &nbsp;';
			$mergeTitle = 'Combine the pages, keeping only the checked information';
		}
		$output .= <<< END
<tr><td align=right colspan="$cols"><input type="hidden" name="formAction">
$mergeSummary
<input id="mergeButton" type="button" title="$mergeTitle" value="$mergeLabel" onClick="$mergeFunction"/></td></tr>
</table>
$hiddenFields
<input type="hidden" name="merges" value="{$this->mergeNumber}">
</form>
$semiProtectedMsg
$nonmergedPages
END;
   	return $output;
   }
   
/////////////////////
// phase two functions
/////////////////////

   private function getMergeTargets() {
		for ($i = 0; $i < count($this->data); $i++) {
			$datai =& $this->data[$i];
			$addi =& $this->add[$i];
			$keyi =& $this->key[$i];
   		if (count($datai) > 1) {
   			$target = $this->target[$i];
   			if ($target > 0) {
	   			$reorder = array();
	   			$reorderAdd = array();
	   			$reorderKey = array();
	   			$reorder[] = $datai[$target];
	   			$reorderAdd[] = $addi[$target];
	   			$reorderKey[] = $keyi[$target];
	   			for ($j = 0; $j < count($datai); $j++) {
	   				if ($j != $target) {
	   					$reorder[] = $datai[$j];
	   					$reorderAdd[] = $addi[$j];
	   					$reorderKey[] = $keyi[$j];
	   				}
	   			}
	   			$this->data[$i] = $reorder;
	   			$this->add[$i] = $reorderAdd;
	   			$this->key[$i] = $reorderKey;
   			}
   		}
   	}
   }
   
   private function createTargetPages() {
   	for ($m = 0; $m < count($this->data); $m++) {
   		$title = Title::newFromText($this->data[$m][0]['title'], $this->namespace == 'Family' && $m == 0 ? NS_FAMILY : NS_PERSON);
			$title->getArticleID(GAID_FOR_UPDATE); // make sure you read the master db for the pageid
   		if (!$title->exists()) {
   			// create page
   			if ($title->getNamespace() == NS_FAMILY) {
   				$obj = new Family($this->data[$m][0]['title']);
   			}
   			else {
   				$obj = new Person($this->data[$m][0]['title']);
   			}
   			$obj->createPage('Create page in preparation for merge');
   			
	  			// update link cache
	  			$title->resetArticleID(0);
   		}
   	}
   }
   
   private function createTargetTalkPages() {
   	for ($m = 0; $m < count($this->data); $m++) {
	   	// create talk page
   		$talkRevids = array();
   		$found = false;
   		for ($p = 0; $p < count($this->data[$m]); $p++) {
   			if ($this->data[$m][$p]['talkrevid']) {
   				$found = true;
   				break;
   			}
   		}
   		if ($found && !$this->data[$m][0]['talkrevid']) {
				$mergeTargetTalkTitle = Title::newFromText($this->data[$m][0]['title'], $this->namespace == 'Family' && $m == 0 ? NS_FAMILY_TALK : NS_PERSON_TALK);
				$article = new Article($mergeTargetTalkTitle, 0);
				if ($article) {
					$article->doEdit('', 'Create page in preparation for merge', EDIT_NEW);
					$this->data[$m][0]['talkrevid'] = $article->getRevIdFetched();
				}
   		}
   	}
   }

   private function generateKeepKeys(&$datai, &$addi, &$keyi) {
		$keepKeys = array();
		for ($p = 0; $p < count($datai); $p++) {
			if ($this->isMergeable($datai[$p])) {
				$keepKeys[$p] = array();
				for ($r = 0; $r < count($addi[$p]); $r++) {
					if ($addi[$p][$r]) $keepKeys[$p][$keyi[$p][$r]] = 1;
				}
			}
		}
		return $keepKeys;
   }
   
   private function addRefs($elements, $label, $id, &$refKeys) {
   	if (isset($elements)) {
			foreach ($elements as $element) {
				$found = false;
				$refs = explode(',', $element[$label]);
				foreach ($refs as $ref) {
					if (trim($ref) == $id) {
						$found = true;
						break;
					}
				}
				if ($found) {
					$refKeys[] = $element['key'];
				}
			}
   	}
   }
   
   private function generateMapAdoptions($label, $initial, $ns, &$datai, &$addi, &$keyi, &$keepKeys, &$map, &$adoptions) {
   	$count = 0;
		for ($p = 0; $p < count($datai); $p++) {
			if ($this->isMergeable($datai[$p])) {
				$map[$p] = array();
				if (is_array($datai[$p][$label])) {
			   	foreach ($datai[$p][$label] as $element) {
			   		if (@$keepKeys[$p][$element['key']]) {
							$count++;
			   			$id = $initial . $count;
			   			$map[$p][$element['id']] = $id;
							
							// does it need to be adopted?
							// get all elements referencing this item
							$refKeys = array();
							if ($label != 'images' && $ns == NS_PERSON) {
								$this->addRefs($datai[$p]['names'], $label, $element['id'], $refKeys);
							}
							$this->addRefs($datai[$p]['events'], $label, $element['id'], $refKeys);
							if ($label != 'sources') {
								$this->addRefs($datai[$p]['sources'], $label, $element['id'], $refKeys);
							}
							foreach ($refKeys as $refKey) {
								// if the referencing element is not being kept
								if (!@$keepKeys[$p][$refKey]) {
									// find the row for the referencing element
									for ($r = 0; $r < count($keyi[$p]); $r++) {
										if ($keyi[$p][$r] == $refKey) {
											// look for an element in the same row for another page
											for ($p2 = 0; $p2 < count($datai); $p2++) {
												// if the other element is being kept
												if ($p2 != $p && $this->isMergeable($datai[$p2]) && $addi[$p2][$r]) {
													// have the other element adopt this item
													$key = $keyi[$p2][$r];
													if (!is_array(@$adoptions[$key])) {
														$adoptions[$key] = array();
													}
													$adoptions[$key][] = $id;
													break; // assign to the earliest page in a row only
												}
											}
										}
									}
								}
							}
			   		}
			   		else {
			   			$map[$p][$element['id']] = '';
						}
			   	}
				}
			}
   	}
   }
   
   private function mapContents(&$sourcesMap, &$imagesMap, &$notesMap, $contents) {
   	// can't pass in oldId and newId as arrays, cause 1->2, 2->3 causes 1 to be mapped to 3
   	foreach ($sourcesMap as $oldId => $newId) {
   		if ($oldId != $newId) {
   			$contents = preg_replace(array("/\{\{cite\s*\|\s*$oldId\s*(\|[^\}]*)?\}\}/i", "/\[\[#$oldId\s*(\|[^\]]*)?\]\]/i"), 
   											 array($newId ? "{{cite12345|$newId$1}}" : '',        $newId ? "[[12345#$newId$1]]" : ''), 
   											 $contents);
   		}
   	}
   	foreach ($imagesMap as $oldId => $newId) {
   		if ($oldId != $newId) {
   			$contents = preg_replace(array("/\{\{cite\s*\|\s*$oldId\s*(\|[^\}]*)?\}\}/i", "/\[\[#$oldId\s*(\|[^\]]*)?\]\]/i"), 
   											 array($newId ? "{{cite12345|$newId$1}}" : '',        $newId ? "[[12345#$newId$1]]" : ''), 
   											 $contents);
   		}
   	}
   	foreach ($notesMap as $oldId => $newId) {
   		if ($oldId != $newId) {
   			$contents = preg_replace(array("/\{\{cite\s*\|\s*$oldId\s*(\|[^\}]*)?\}\}/i", "/\[\[#$oldId\s*(\|[^\]]*)?\]\]/i"), 
   											 array($newId ? "{{cite12345|$newId$1}}" : '',        $newId ? "[[12345#$newId$1]]" : ''), 
   											 $contents);
   		}
   	}
		$contents = str_replace(array("{{cite12345|", "[[12345#"), 
										array("{{cite|",      "[[#"), 
										$contents);
   	return $contents;
   }
   
   private function addImagesToRequestData(&$requestData, &$keepKeys, &$count, &$imagesMap, &$primaryFound, $elements) {
   	if (is_array($elements)) {
	   	foreach ($elements as $element) {
	   		if (@$keepKeys[$element['key']]) {
	   			$id = $imagesMap[$element['id']];
	   			if ($element['primary'] && !$primaryFound) {
	   				$primary = true;
	   				$primaryFound = true;
	   			}
	   			else {
	   				$primary = false;
	   			}
					ESINHandler::addImageToRequestData($requestData, $count, $id, $element['filename'], $element['caption'], $primary);
					$count++;
	   		}
	   	}
   	}
   }
   
   private function addNotesToRequestData(&$requestData, &$keepKeys, &$count, &$notesMap, $elements) {
   	if (is_array($elements)) {
	   	foreach ($elements as $element) {
	   		if (@$keepKeys[$element['key']]) {
	   			$id = $notesMap[$element['id']];
					ESINHandler::addNoteToRequestData($requestData, $count, $id, $element['text']);
					$count++;
	   		}
	   	}
   	}
   }
   
   private function addAdoptions($key, $refs, &$adoptions) {
   	if (count(@$adoptions[$key]) > 0) {
   		if ($refs) $refs .= ', ';
   		$refs .= join(', ',$adoptions[$key]);
   	}
   	return $refs;
   }
   
   private function addSourcesToRequestData(&$requestData, &$keepKeys, &$count, &$sourcesMap, &$notesMap, &$imagesMap, $elements, &$noteAdoptions, &$imageAdoptions) {
   	if (is_array($elements)) {
	   	foreach ($elements as $element) {
	   		if (@$keepKeys[$element['key']]) {
	   			$id = $sourcesMap[$element['id']];
	   			$title = $element['title'];
	   			$titleLower = mb_strtolower($title);
	   			$ns = '';
	   			if (strpos($titleLower, 'source:') === 0) {
	   				$title = mb_substr($title, strlen('source:'));
	   				$ns = NS_SOURCE;
	   			}
	   			else if (strpos($titleLower, 'mysource:') === 0) {
	   				$title = mb_substr($title, strlen('mysource:'));
	   				$ns = NS_MYSOURCE;
	   			}
	   			$notes = $this->addAdoptions($element['key'], ESINHandler::mapSourcesImagesNotes($notesMap, $element['notes']), $noteAdoptions);
	   			$images = $this->addAdoptions($element['key'], ESINHandler::mapSourcesImagesNotes($imagesMap, $element['images']), $imageAdoptions);
					ESINHandler::addSourceToRequestData($requestData, $count, $id, $ns, $title, $element['record_name'], 
																	$element['page'], $element['quality'], $element['date'], $notes, $images, $element['text']);
					$count++;
	   		}
	   	}
   	}
   }
   
   private function addEventsToRequestData(&$requestData, &$keepKeys, &$count, $stdEventTypes, &$birthFound, &$christeningFound, &$deathFound, &$burialFound, &$marriageFound,
   													 &$notesMap, &$imagesMap, &$sourcesMap, $elements, &$noteAdoptions, &$sourceAdoptions, &$imageAdoptions) {
		if (is_array($elements)) {   													 	
	   	foreach ($elements as $element) {
	   		if (@$keepKeys[$element['key']]) {
	   			if ($count < count($stdEventTypes)) $count = count($stdEventTypes);
	   			$type = $element['type'];
	   			if ($type == Person::$BIRTH_TAG) {
	   				if (!$birthFound) {
	   					$i = array_search($type, Person::$STD_EVENT_TYPES);
	   					$birthFound = true;
	   				}
	   				else {
	   					$type = Person::$ALT_BIRTH_TAG;
	   					$i = $count;
	   					$count++;
	   				}
	   			}
	   			else if ($type == Person::$CHR_TAG) {
	   				if (!$christeningFound) {
	   					$i = array_search($type, Person::$STD_EVENT_TYPES);
	   					$christeningFound = true;
	   				}
	   				else {
	   					$type = Person::$ALT_CHR_TAG;
	   					$i = $count;
	   					$count++;
	   				}
	   			}
	   			else if ($type == Person::$DEATH_TAG) {
	   				if (!$deathFound) {
	   					$i = array_search($type, Person::$STD_EVENT_TYPES);
	   					$deathFound = true;
	   				}
	   				else {
	   					$type = Person::$ALT_DEATH_TAG;
	   					$i = $count;
	   					$count++;
	   				}
	   			}
	   			else if ($type == Person::$BUR_TAG) {
	   				if (!$burialFound) {
	   					$i = array_search($type, Person::$STD_EVENT_TYPES);
	   					$burialFound = true;
	   				}
	   				else {
	   					$type = Person::$ALT_BUR_TAG;
	   					$i = $count;
	   					$count++;
	   				}
	   			}
	   			else if ($type == Family::$MARRIAGE_TAG) {
	   				if (!$marriageFound) {
	   					$i = array_search($type, Family::$STD_EVENT_TYPES);
	   					$marriageFound = true;
	   				}
	   				else {
	   					$type = Family::$ALT_MARRIAGE_TAG;
	   					$i = $count;
	   					$count++;
	   				}
	   			}
	   			else {
	   				$i = $count;
	   				$count++;
	   			}
	   			$notes = $this->addAdoptions($element['key'], ESINHandler::mapSourcesImagesNotes($notesMap, $element['notes']), $noteAdoptions);
	   			$images = $this->addAdoptions($element['key'], ESINHandler::mapSourcesImagesNotes($imagesMap, $element['images']), $imageAdoptions);
	   			$sources = $this->addAdoptions($element['key'], ESINHandler::mapSourcesImagesNotes($sourcesMap, $element['sources']), $sourceAdoptions);
	   			ESINHandler::addEventToRequestData($requestData, $i, $type, $element['date'], $element['place'], $element['description'], $sources, $images, $notes);
	   		}
	   	}
   	}
   }
   
   private function addNamesToRequestData(&$requestData, &$keepKeys, &$count, &$primaryNameFound, &$notesMap, &$sourcesMap, $elements, &$noteAdoptions, &$sourceAdoptions) {
   	if (is_array($elements)) {
	   	foreach ($elements as $element) {
	   		if (@$keepKeys[$element['key']]) {
	   			if ($count == 0) $count = 1;
	   			if ($element['type'] == self::$PRIMARY_NAME) {
	   				if (!$primaryNameFound) {
	   					$i = 0;
	   					$type = '';
	   					$primaryNameFound = true;
	   				}
	   				else {
	   					$type = Person::$ALT_NAME_TAG;
	   					$i = $count;
	   					$count++;
	   				}
	   			}
	   			else {
	   				$type = $element['type'];
	   				$i = $count;
	   				$count++;
	   			}
	   			$notes = $this->addAdoptions($element['key'], ESINHandler::mapSourcesImagesNotes($notesMap, $element['notes']), $noteAdoptions);
	   			$sources = $this->addAdoptions($element['key'], ESINHandler::mapSourcesImagesNotes($sourcesMap, $element['sources']), $sourceAdoptions);
	   			Person::addNameToRequestData($requestData, $i, $type, $element['given'], $element['surname'], $element['title_prefix'], $element['title_suffix'], $sources, $notes);
	   		}
	   	}
   	}
   }
   
   private function addFamilyMembersToRequestData(&$requestData, &$mergingPeople, $role, &$count, $elements) {
   	if (is_array($elements)) {
	   	foreach ($elements as $element) {
	   		$titleString = $element['title'];
	   		$mergeTarget = @$mergingPeople[$titleString];
	   		if (!$mergeTarget && !GedcomUtil::isGedcomTitle($titleString)) {
	   			Family::addPersonToRequestData($requestData, $role, $count, $titleString);
	   			$count++;
	   		} // don't need an else clause here (like we do below) because family merge sources are guaranteed to exist, so target person will always be added
	   	}
   	}
   }
   
   private function addFamilyToRequestData(&$requestData, &$mergingFamilies, $role, &$count, $elements) {
   	if (is_array($elements)) {
	   	foreach ($elements as $element) {
	   		$titleString = $element['title'];
	   		if (!GedcomUtil::isGedcomTitle($titleString)) {
		   		$mergeTarget = @$mergingFamilies[$titleString];
		   		if (!$mergeTarget) {
		   			Person::addFamilyToRequestData($requestData, $role, $count, $titleString);
		   			$count++;
		   		}
		   		else { // add the merge target to the request data, because the person merge source for with the target family may not exist, so target family won't be added to request data
		   			Person::addFamilyToRequestData($requestData, $role, $count, $mergeTarget);
		   			$count++;
		   		}
	   		}
	   	}
   	}
   }
   
   private function addContents(&$targetContents, &$keepKeys, &$pageContents) {
   	if ($pageContents && @$keepKeys['contents']) {
   		if ($targetContents) {
   			$targetContents .= "\n\n";
   		}
   		$targetContents .= $pageContents;
   	}
   }
   
   private function addTalkContents(&$talkContents, $talkTitle, &$pageContents) {
   	if ($pageContents) {
   		$hdrPrefix = StructuredData::protectRegexReplace('From [['.$talkTitle->getPrefixedText().'|'.$talkTitle->getText().']]: ');
   		$talkContents .= preg_replace('/^ *==( *)([^=].*?[^=])== *$/m', "==\\1$hdrPrefix\\2==", $pageContents);
   	}
   }
   
   private function getMergeScore() {
		$totalScore = $totalCount = 0;
		$baseStdData = array();
		for ($m = count($this->data)-1; $m >= 0; $m--) {
			for ($p = 0; $p < count($this->data[$m]); $p++) {
				// calculate a total match score
				if ($this->data[$m][$p]['revid'] || $this->data[$m][$p]['gedcom']) { // if page exists or is gedcom
					$mergeTargetNs = ($this->namespace == 'Family' && $m == 0) ? NS_FAMILY : NS_PERSON;
					if ($mergeTargetNs == NS_FAMILY) {
						$stdData = MergeForm::standardizeFamilyData($this->data[$m][$p]);
					}
					else {
						$stdData = MergeForm::standardizePersonData($this->data[$m][$p]);
					}
					if ($p == 0) {
						$baseStdData = $stdData;
					}
					else {
						$score = MergeForm::calcMatchScore($baseStdData, $stdData);
						wfDebug("MERGESCORE title=".$this->data[$m][$p]['title']." score=$score\n");
						$totalScore+= $score;
						$totalCount++;
					}
				}
			}
		}
		
		if ($totalCount) {
			return $avgScore = $totalScore / $totalCount;
		}
		else {
			return 0;
		}
   }
   
   private function getMergeLogKey(&$dataij) {
   	if ($dataij['gedcom']) {
   		return GedcomUtil::generateGedcomMergeLogKey($dataij['title']);
   	}
   	else if ($dataij['revid']) {
			return $dataij['revid'];
   	}
   	else {
   		return '';
   	}
   }
   
   private function getMergeRow($titleString, &$mergeRevids) {
   	for ($m = 1; $m < count($this->data); $m++) {
   		for ($p = 0; $p < count($this->data[$m]); $p++) {
   			if ($titleString == $this->data[$m][$p]['title']) {
   				return array($m, $this->getMergeLogKey($this->data[$m][$p]));
   			}
   		}
   	}
   	if (GedcomUtil::isGedcomTitle($titleString)) {
   		$revid = GedcomUtil::generateGedcomMergeLogKey($titleString);
   	}
   	else {
			$t = Title::newFromText($titleString, NS_PERSON);
			$revid = $t->getLatestRevID(GAID_FOR_UPDATE); // make sure you read the master db
   	}
		// look for same revid in an existing row
		if ($revid) {
			for ($m = 1; $m < count($mergeRevids); $m++) {
				for ($p = 0; $p < count($mergeRevids[$m]); $p++) {
					if (in_array($revid, $mergeRevids[$m][$p])) {
						return array($m, $revid);
					}
				}
			}
		}
   	return array(0, $revid ? $revid : '');
   }
   
   private function addFamilyMembersToMergeData(&$elements, $p, $role, &$mergeRoles, &$mergeRevids, &$mergeRows) {
   	foreach ($elements as $element) {
   		$titleString = $element['title'];
			list($mergeRow, $revid) = $this->getMergeRow($titleString, $mergeRevids);
			if ($revid) {
				if (!$mergeRow) {
					$mergeRow = $mergeRows;
					$mergeRows++;
					$mergeRevids[$mergeRow] = array();
					for ($i = 0; $i < count($this->data[0]); $i++) {
						$mergeRevids[$mergeRow][$i] = array(); // placeholders for other revids in this row
					}
				}
				$mergeRoles[$mergeRow] = $role;
				$mergeRevids[$mergeRow][$p][] = $revid;
			}
   	}
   }
   
   private function logMerge($mergeScore, $isTrustedMerge) {
   	global $wgUser;
   	
   	// create merges array
   	$mergeRoles = array();
   	$mergeTitles = array();
   	$mergeRoles[0] = $this->namespace;
   	$mergeRevids[0] = array();
  		$mergeRows = count($this->data);
  		// one column per merging person/family
		for ($p = 0; $p < count($this->data[0]); $p++) {
	   	for ($m = 0; $m < count($this->data); $m++) {
   			$mergeRevids[$m][$p] = array(); // placeholders for revids in this column
	   	}
			$mergeRevids[0][$p][] = $this->getMergeLogKey($this->data[0][$p]);
			if ($this->namespace == 'Family') {
				$this->addFamilyMembersToMergeData($this->data[0][$p]['husbands'], $p, 'husband', $mergeRoles, $mergeRevids, $mergeRows);
				$this->addFamilyMembersToMergeData($this->data[0][$p]['wives'], $p, 'wife', $mergeRoles, $mergeRevids, $mergeRows);
				$this->addFamilyMembersToMergeData($this->data[0][$p]['children'], $p, 'child', $mergeRoles, $mergeRevids, $mergeRows);
			}
   	}
   	
   	// merges[merge row][main person/family being merged][can have multiple revids merged together from the same family]
   	$merges = array();
   	for ($m = 0; $m < count($mergeRevids); $m++) {
   		$cellRevids = array();
   		$found = false;
   		$rowPages = 0;
   		for ($p = 0; $p < count($mergeRevids[$m]); $p++) {
   			if (count($mergeRevids[$m][$p]) > 0) {
   				$found = true;
	   			$cellRevids[$p] = join('/', $mergeRevids[$m][$p]);
   			}
   			else {
   				$cellRevids[$p] = '';
   			}
   		}
   		if ($found) {
   			$merges[] = $mergeRoles[$m].'|'.join('#', $cellRevids);
   		}
   	}

   	// talk page columns don't match up with person/family columns
   	for ($m = 0; $m < count($this->data); $m++) {   // add the talk pages
   		$talkRevids = array();
   		$found = false;
   		for ($p = 0; $p < count($this->data[$m]); $p++) {
   			$talkRevid = $this->data[$m][$p]['talkrevid'];
   			if ($talkRevid) {
   				$found = true;
   			}
   			else {
   				$talkRevid = '';
   			}
   			$talkRevids[$p] = $talkRevid;
   		}
   		if ($found) {
   			$merges[] = 'talk|'.join('#', $talkRevids);
   		}
   	}
   	
   	// create mergelog record
		$t = Title::newFromText($this->data[0][0]['title'], $this->namespace == 'Family' ? NS_FAMILY : NS_PERSON);
      $dbw =& wfGetDB(DB_MASTER);
  	   $record = array('ml_timestamp' => $dbw->timestamp( wfTimestampNow() ), 'ml_user' => $wgUser->getID(), 'ml_score' => $mergeScore, 'ml_trusted' => ($isTrustedMerge ? 1 : 0),
  	   					 'ml_namespace' => $t->getNamespace(), 'ml_title' => $t->getDBkey(), 'ml_gedcom_id' => $this->gedcomId, 'ml_pages' => join("\n", $merges));
      $dbw->insert('mergelog', $record);
		return $dbw->insertId();
   }
   
   private function makeComment($userComment, $comment, $reviewComment) {
   	if ($userComment) {
   		$comment = "$userComment - $comment";
   	}
   	$comment = mb_substr($comment, 0, 255 - 60 - strlen($reviewComment)); // 255=max comment length; 60=room for up to 20 extended UTF8 characters
   	$lastClose = mb_strrpos($comment, ']]');
   	$lastOpen = mb_strrpos($comment, '[[');
   	if ($lastOpen > $lastClose) {
   		$comment = mb_substr($comment, 0, $lastOpen) . ' ...';
   	}
		return $comment . $reviewComment;   	
	}
	
	private function isMergeable(&$dataij) {
		return $dataij['revid'] || $dataij['gedcom']; // exists or is gedcom
	}
   
   public function doMerge() {
   	global $wgOut, $wgUser;
   	
   	$skin =& $wgUser->getSkin();
   	
   	if ($this->isGedcom()) {
			$editFlags = EDIT_UPDATE;
			$mergeText = 'updated';
   	}
   	else {
	   	// create a mergelog record
			$mergeScore = $this->getMergeScore();
			$isTrustedUser = CompareForm::isTrustedMerger($wgUser, false);
			$isTrustedMerge = MergeForm::isTrustedMerge($mergeScore, $isTrustedUser);
			$mergeLogId = $this->logMerge($mergeScore, $isTrustedMerge);
			wfDebug("MERGESCORE mergeLogId=$mergeLogId total score=$mergeScore\n");
			if (!$isTrustedUser && $mergeScore < self::$LOW_MATCH_THRESHOLD) {
				error_log("WARNING suspect merge: id=$mergeLogId user={$wgUser->getName()} score=$mergeScore");
			}
			$editFlags = EDIT_UPDATE | EDIT_FORCE_BOT;
			$mergeText = 'merged';
   	}
   	
   	// get merging people and families
   	// add merging people and families to blacklist so propagation doesn't also try to update them
   	$mergingPeople = array();
   	$mergingFamilies = array();
   	for ($m = 0; $m < count($this->data); $m++) {
   		for ($p = 0; $p < count($this->data[$m]); $p++) {
				$titleString = $this->data[$m][$p]['title'];
   			if ($this->namespace == 'Family' && $m == 0) {
   				if ($p > 0) {
   					$mergingFamilies[$titleString] = $this->data[$m][0]['title'];
   				}
   				$ns = NS_FAMILY;
   			}
   			else {
   				if ($p > 0) {
   					$mergingPeople[$titleString] = $this->data[$m][0]['title'];
   				}
   				$ns = NS_PERSON;
   			}
	   		if (!GedcomUtil::isGedcomTitle($titleString)) {
   				$title = Title::newFromText($titleString, $ns);
   				PropagationManager::addBlacklistPage($title);
   			}
   		}
   	}
   	
   	$output = "<H2>Pages $mergeText successfully</H2>";
   	$output .= $this->getWarnings();
   	$output .= '<ul>';
   	$outputRows = array();
		$emptyRequest = new FauxRequest(array(), true);
		$mergeCmtSuffix = $this->isGedcom() ? '' : " - [[Special:ReviewMerge/$mergeLogId|review/undo]]";
		if ($this->namespace == 'Family' && !$this->isGedcom()) {
			$t = Title::newFromText($this->data[0][0]['title'], NS_FAMILY);
			$mergeCmtFamily = ($this->namespace == 'Family' ? " in merge of [[{$t->getPrefixedText()}]]" : '');
		}
		else {
			$mergeCmtFamily = '';
		}
		// backwards, because you must merge family last, so that propagated person data in family xml is correct
		// and so that mergeCmtFamily can be cleared at the end and mergeSummary and mergeTargetTitle are correct after the for loop
		for ($m = count($this->data)-1; $m >= 0; $m--) { 
			$requestData = array();
			$contents = '';
			$talkContents = '';
			$outputRow = '';
			$mainOutput = '';
			$talkOutput = '';
			$nameCount = $eventCount = $sourceCount = $imageCount = $noteCount = $husbandCount = $wifeCount = $childrenCount = $parentFamilyCount = $spouseFamilyCount = 0;
			$primaryNameFound = $primaryImageFound = $birthFound = $christeningFound = $deathFound = $burialFound = $marriageFound = false;
			if ($this->namespace == 'Family' && $m == 0) {
				$mergeTargetNs = NS_FAMILY;
				$mergeTargetTalkNs = NS_FAMILY_TALK;
				$mergeCmtFamily = '';
			}
			else {
				$mergeTargetNs = NS_PERSON;
				$mergeTargetTalkNs = NS_PERSON_TALK;
			}
			$mergeTargetTitle = Title::newFromText($this->data[$m][0]['title'], $mergeTargetNs);
			if ($mergeTargetTitle->getNamespace() != $mergeTargetNs) {
				error_log("Merge glitch:$mergeTargetNs:{$this->data[$m][0]['title']}:{$mergeTargetTitle->getNamespace()}:");
			}
			$mergeTargetTalkTitle = Title::newFromText($this->data[$m][0]['title'], $mergeTargetTalkNs);
			$mergeSummary = '';
			$talkMergeSummary = '';
			
			$keepKeys = $this->generateKeepKeys($this->data[$m], $this->add[$m], $this->key[$m]);
			
			$notesMap = array();
			$noteAdoptions = array();
			$this->generateMapAdoptions('notes', 'N', $mergeTargetNs, $this->data[$m], $this->add[$m], $this->key[$m], $keepKeys, $notesMap, $noteAdoptions);
			$sourcesMap = array();
			$sourceAdoptions = array();
			$this->generateMapAdoptions('sources', 'S', $mergeTargetNs, $this->data[$m], $this->add[$m], $this->key[$m], $keepKeys, $sourcesMap, $sourceAdoptions);
			$imagesMap = array();
			$imageAdoptions = array();
			$this->generateMapAdoptions('images', 'I', $mergeTargetNs, $this->data[$m], $this->add[$m], $this->key[$m], $keepKeys, $imagesMap, $imageAdoptions);
				
			// get request data for merge target
			for ($p = 0; $p < count($this->data[$m]); $p++) {
				if ($this->isMergeable($this->data[$m][$p])) {
					$this->addNotesToRequestData($requestData, $keepKeys[$p], $noteCount, $notesMap[$p], $this->data[$m][$p]['notes']);
					$this->addImagesToRequestData($requestData, $keepKeys[$p], $imageCount, $imagesMap[$p], $primaryImageFound, $this->data[$m][$p]['images']);
					$this->addSourcesToRequestData($requestData, $keepKeys[$p], $sourceCount, $sourcesMap[$p], $notesMap[$p], $imagesMap[$p], 
															$this->data[$m][$p]['sources'], $noteAdoptions, $imageAdoptions);
					$this->addEventsToRequestData($requestData, $keepKeys[$p], $eventCount, 
															$mergeTargetNs == NS_PERSON ? Person::$STD_EVENT_TYPES : Family::$STD_EVENT_TYPES,
															$birthFound, $christeningFound, $deathFound, $burialFound, $marriageFound,
															$notesMap[$p], $imagesMap[$p], $sourcesMap[$p], $this->data[$m][$p]['events'], 
															$noteAdoptions, $sourceAdoptions, $imageAdoptions);
					if ($mergeTargetNs == NS_PERSON) {
						$this->addNamesToRequestData($requestData, $keepKeys[$p], $nameCount, $primaryNameFound, $notesMap[$p], $sourcesMap[$p], 
															$this->data[$m][$p]['names'], $noteAdoptions, $sourceAdoptions);
						$this->addFamilyToRequestData($requestData, $mergingFamilies, 'child_of_family', $parentFamilyCount, $this->data[$m][$p]['child_of_families']);
						$this->addFamilyToRequestData($requestData, $mergingFamilies, 'spouse_of_family', $spouseFamilyCount, $this->data[$m][$p]['spouse_of_families']);
					}
					else {
						$this->addFamilyMembersToRequestData($requestData, $mergingPeople, 'husband', $husbandCount, $this->data[$m][$p]['husbands']);
						$this->addFamilyMembersToRequestData($requestData, $mergingPeople, 'wife', $wifeCount, $this->data[$m][$p]['wives']);
						$this->addFamilyMembersToRequestData($requestData, $mergingPeople, 'child', $childrenCount, $this->data[$m][$p]['children']);
					}
					$pageContents = $this->mapContents($sourcesMap[$p], $imagesMap[$p], $notesMap[$p], $this->data[$m][$p]['contents']);
					$this->addContents($contents, $keepKeys[$p], $pageContents);
					
					if ($p > 0) {
						if ($mergeSummary) {
							$mergeSummary .= ', ';
						}
						if ($mainOutput) {
							$mainOutput .= ', ';
						}
						if ($this->data[$m][$p]['gedcom']) {
							$mergeSummary .= 'gedcom';
							$mainOutput .= htmlspecialchars(($mergeTargetNs == NS_FAMILY ? 'Family:' : 'Person:').$this->data[$m][$p]['title']);
						}
						else {
							$title = Title::newFromText($this->data[$m][$p]['title'], $mergeTargetNs);
							$mergeSummary .= "[[".$title->getPrefixedText()."]]";
							$mainOutput .= $skin->makeKnownLinkObj($title, htmlspecialchars($title->getPrefixedText()), 'redirect=no');
						}
					}
				}
			}
			
			// redirect other pages to merge target
			$redir = "#REDIRECT [[".$mergeTargetTitle->getPrefixedText()."]]";
			$talkRedir = "#REDIRECT [[".$mergeTargetTalkTitle->getPrefixedText()."]]";
			for ($p = 1; $p < count($this->data[$m]); $p++) {
				if (!$this->data[$m][$p]['gedcom']) {
					$obj = $this->data[$m][$p]['object'];
					$comment = $this->makeComment($this->userComment, "merge into [[".$mergeTargetTitle->getPrefixedText()."]]".$mergeCmtFamily,$mergeCmtSuffix);
					$obj->editPage($emptyRequest, $redir, $comment, $editFlags, false);
					
					// redir talk page as well
					if ($this->data[$m][$p]['talkrevid']) { // if talk page exists
						$talkTitle = Title::newFromText($this->data[$m][$p]['title'], $mergeTargetTalkNs);
						$article = new Article($talkTitle, 0);
						if ($article) {
							$this->addTalkContents($talkContents, $talkTitle, $article->fetchContent());
							if ($talkMergeSummary) {
								$talkMergeSummary .= ', ';
							}
							if ($talkOutput) {
								$talkOutput .= ', ';
							}
							$talkMergeSummary .= "[[" . $talkTitle->getPrefixedText() . "]]";
							$talkOutput .= $skin->makeKnownLinkObj($talkTitle, htmlspecialchars($talkTitle->getPrefixedText()), 'redirect=no');
							$comment = $this->makeComment($this->userComment, "merge into [[".$mergeTargetTalkTitle->getPrefixedText()."]]".$mergeCmtFamily, $mergeCmtSuffix);
							$article->doEdit($talkRedir, $comment,  $editFlags);
						}
					}
				}
			}
			
			// update merge target talk
			if ($talkContents) {
				$article = new Article($mergeTargetTalkTitle, 0);
				if ($article) {
					$mergeTargetTalkContents = $article->fetchContent();
					if ($mergeTargetTalkContents) {
						$mergeTargetTalkContents = rtrim($mergeTargetTalkContents) . "\n\n";
					}
					$comment = $this->makeComment($this->userComment, 'merged with '.$talkMergeSummary.$mergeCmtFamily,$mergeCmtSuffix);
					$article->doEdit($mergeTargetTalkContents . $talkContents, $comment, $editFlags);
					if ($this->addWatches) {
		   			StructuredData::addWatch($wgUser, $article, true); 
					}
				}
				$outputRow .= '<li>Merged ' . $talkOutput . ' into ' . $skin->makeKnownLinkObj($mergeTargetTalkTitle, htmlspecialchars($mergeTargetTalkTitle->getPrefixedText()))."</li>";
			}
			
			$obj = $this->data[$m][0]['object'];
			if ($mergeTargetNs == NS_PERSON) {
				Person::addGenderToRequestData($requestData, $this->data[$m][0]['gender']);
			}
			else { // family
				$obj->isMerging(true); // to read propagated data from person pages, not from prev family revision
			}
			// update merge target
			$req = new FauxRequest($requestData, true);
			$comment = $this->makeComment($this->userComment, ($mergeSummary == 'gedcom' ? 'Add data from gedcom' : 'merged with '.$mergeSummary).$mergeCmtFamily,$mergeCmtSuffix);
			$obj->editPage($req, $contents, $comment, $editFlags, $this->addWatches);
			$outputRow .= '<li>Merged ' . $mainOutput . ' into ' . $skin->makeKnownLinkObj($mergeTargetTitle, htmlspecialchars($mergeTargetTitle->getPrefixedText()))."</li>";

			$outputRows[] = $outputRow;
		}
		
		// add log and recent changes
		if (!$this->isGedcom()) {
			if (!$mergeSummary) $mergeSummary = 'members of other families';
			$mergeComment = 'Merge [['.$mergeTargetTitle->getPrefixedText().']] and '.$mergeSummary;
			$log = new LogPage( 'merge', false );
			$t = Title::makeTitle(NS_SPECIAL, "ReviewMerge/$mergeLogId");
			$log->addEntry('merge', $t, $mergeComment);
			RecentChange::notifyLog(wfTimestampNow(), $t, $wgUser, $mergeComment, '', 'merge', 'merge', 
											$t->getPrefixedText(), $mergeComment, '', $isTrustedMerge, 0);
		}
										
		$nonmergedPages = $this->getNonmergedPages();
		$output .= join("\n", array_reverse($outputRows)) . '</ul>'.  // reverse the order to put back in the top-down order
						($nonmergedPages ? '<p>In addition to the people listed above, the following have also been included in the target family' . 
												($this->isGedcom() ? '<br/>(GEDCOM people listed will be added when the GEDCOM is imported)' : '') .
												$nonmergedPages . "</p>\n" : '') .
						($this->isGedcom() ? '' : '<p>'.
														$skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'ReviewMerge/'.$mergeLogId), htmlspecialchars("Review/undo merge")).'<br>'.
														$skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'ShowDuplicates'), htmlspecialchars("Show more duplicates")).
														'</p>');

		return $output;
   }
}
?>
