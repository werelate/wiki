<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");
require_once("$IP/extensions/structuredNamespaces/ESINHandler.php");
require_once("$IP/extensions/structuredNamespaces/TipManager.php");
require_once("$IP/extensions/other/PlaceSearcher.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfPersonExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfPersonExtensionSetup() {
	global $wgHooks;
	global $wgParser;

	# Register hooks for edit UI, request handling, and save features
	$wgHooks['ArticleEditShow'][] = 'renderPersonEditFields';
	$wgHooks['ImportEditFormDataComplete'][] = 'importPersonEditData';
	$wgHooks['EditFilter'][] = 'validatePerson';
	$wgHooks['ArticleSave'][] = 'propagatePersonEdit';
	$wgHooks['TitleMoveComplete'][] = 'propagatePersonMove';
	$wgHooks['ArticleDeleteComplete'][] = 'propagatePersonDelete';
	$wgHooks['ArticleUndeleteComplete'][] = 'propagatePersonUndelete';
	$wgHooks['ArticleRollbackComplete'][] = 'propagatePersonRollback';

	# register the extension with the WikiText parser
	$wgParser->setHook('person', 'renderPersonData');
}

/**
 * Callback function for converting resource to HTML output
 */
function renderPersonData( $input, $argv, $parser) {
   // TODO handle [edit section]'s by right-justifying text
//   $parser->getOptions()->setEditSection(false);
   $title = $parser->getTitle()->getText();
	$person = new Person($title);
   if ($title == 'GedcomPage') {
      $person->setGedcomPage(true);
   }
	return $person->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderPersonEditFields( &$editPage ) {
	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_PERSON) {
		$person = new Person($editPage->mTitle->getText());
		$person->renderEditFields($editPage, true);
	}
	return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importPersonEditData( &$editPage, &$request ) {
	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_PERSON) {
		$person = new Person($editPage->mTitle->getText());
		$person->importEditData($editPage, $request);
	}
	return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validatePerson($editPage, $textBox1, $section, &$hookError) {
	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_PERSON) {
		$person = new Person($editPage->mTitle->getText());
		$person->validate($textBox1, $section, $hookError, true);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagatePersonEdit(&$article, &$user, &$text, &$summary, $minor, $dummy1, $dummy2, &$flags) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_PERSON) {
		$person = new Person($article->getTitle()->getText());
		$person->propagateEdit($text, $article);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagatePersonMove(&$title, &$newTitle, &$user, $pageId, $redirPageId) {
	$ns = $title->getNamespace();
	if ($ns == NS_PERSON) {
		$person = new Person($title->getText());
		$person->propagateMove($newTitle);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagatePersonDelete(&$article, &$user, $reason) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_PERSON) {
		$person = new Person($article->getTitle()->getText());
		$person->propagateDelete($article);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagatePersonUndelete(&$title, &$user) {
	$ns = $title->getNamespace();
	if ($ns == NS_PERSON) {
		$person = new Person($title->getText());
		$revision = StructuredData::getRevision($title, false, true);
		$person->propagateUndelete($revision);
	}
	return true;
}

/**
 * Callback function to propagate rollback
 * @param Article article
 * @return bool must return true or other hooks don't get called
 */
function propagatePersonRollback(&$article, &$user) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_PERSON) {
		$person = new Person($article->getTitle()->getText());
		$person->propagateRollback($article);
	}
	return true;
}

/**
 * Handles people
 */
class Person extends StructuredData {
   const PROPAGATE_MESSAGE = 'Propagate changes to';
	// if you add more standard events, you must change the javascript function addEventFact
	// if you change 'Birth' or 'Death', change them below as well.
	public static $STD_EVENT_TYPES = array('Birth', 'Christening', 'Death', 'Burial');
	public static $BIRTH_TAG = 'Birth';
   public static $CHR_TAG = 'Christening';
   public static $BAPTISM_TAG = 'Baptism';
	public static $DEATH_TAG = 'Death';
	public static $BUR_TAG = 'Burial';
	public static $ALT_BIRTH_TAG = 'Alt Birth';
	public static $ALT_CHR_TAG = 'Alt Christening';
	public static $ALT_DEATH_TAG = 'Alt Death';
	public static $ALT_BUR_TAG = 'Alt Burial';
	public static $GENDER_OPTIONS = array(
		'Female' => 'F',
		'Male' => 'M',
		'Unknown' => '?',
	);
	// TODO Remove Citizenship (same as Naturalization), Employment, Funeral, Illness, Obituary, Pension, Stillborn?
	protected static $OTHER_EVENT_TYPES = array(
	'Alt Birth', 'Alt Burial', 'Alt Christening', 'Alt Death', 
	'Adoption', 'Ancestral File Number', 'Baptism',
	'Bar Mitzvah', 'Bat Mitzvah',	'Blessing', 'Caste', 'Cause of Death', 'Census', 'Citizenship', 'Confirmation', 'Cremation', 'Degree', 'DNA', 'Education',
	'Emigration', 'Employment', 'Excommunication', 'First Communion', 'Funeral', 'Graduation', 'Illness', 'Immigration', 'Living', 'Medical', 'Military',
	'Mission', 'Namesake', 'Nationality', 'Naturalization', 'Obituary', 'Occupation', 'Ordination', 'Pension', 'Physical Description',
	'Probate', 'Property', 'Reference Number', 'Religion', 'Residence', 'Retirement', 'Soc Sec No', 'Stillborn', 'Title (nobility)', 'Will',
	'=African American', 
	':Distribution List',
	':Emancipation',
	':Escape or Runaway',
	':Estate Inventory',
	':Estate Settlement',
	':First Appearance',
	':Freedmen~s Bureau', // use ~ for '
	':Hired Away',
   ':Homestead',
	':Household List',
	':Plantation Journal',
	':Purchase',
	':Recapture',
	':Relocation',
	':Sale',
	':Slave List',
	'Other');
	// keep $NAME_TYPES in sync with GedcomExporter
	protected static $NAME_TYPES = array('Alt Name', 'Baptismal Name', 'Immigrant Name', 'Married Name', 'Religious Name');
	public static $ALT_NAME_TAG = 'Alt Name';
	protected $historicalData;

	/**
	 * get spouse tag (husband or wife) from gender
	 *
	 * @param unknown_type $gender
	 * @return unknown
	 */
	public static function getSpouseTagFromGender($gender) {
		return ($gender == 'F' ? 'wife' : 'husband');
	}

   /**
    * Return true if the title should be renamed
    * @static
    * @param $titleString
    * @param $correctTitle
    * @return bool true if rename is needed
    */
   public static function isRenameNeeded($titleString, $correctTitle) {
      return (mb_strpos($titleString, 'Unknown') !== false &&
              (mb_strpos($correctTitle, 'Unknown') === false ||
               ($correctTitle != 'Unknown' && preg_match('/^Unknown( \(\d+\))?$/', $titleString))));
   }

	/**
     * Construct a new person object
     */
	public function __construct($titleString) {
		parent::__construct('person', $titleString, NS_PERSON, ESINHandler::ESIN_FOOTER_TAG);
		$this->historicalData = '';
	}

   protected function getHeaderHTML() {
      global $wgESINHandler;

      $result = '';
      if (isset($this->xml)) {
         $parents = array();
         foreach ($this->xml->child_of_family as $f) {
            $parents[] = (string)$f['title'];
         }
         $parents = str_replace("'", "\'", join('|', $parents));
         $spouses = array();
         foreach ($this->xml->spouse_of_family as $f) {
            $spouses[] = (string)$f['title'];
         }
         $spouses = str_replace("'", "\'", join('|', $spouses));
         $esinHeader = $wgESINHandler->getHeaderHTML();
         $result = "<script type=\"text/javascript\">/*<![CDATA[*/var personParents='$parents'; var personSpouses='$spouses';/*]]>*/</script>$esinHeader";
      }
      if ($this->historicalData) {
         $result .= '<span itemscope itemtype="http://historical-data.org/HistoricalPerson.html">'.
                    $this->historicalData.'</span>';
      }
      return $result;
   }

   private function initHistoricalDataPerson($fullname, $gender, $imageURL) {
      global $wgServer;

      switch ($gender) {
         case 'M':
            $hdGender = 'male';
            break;
         case 'F':
            $hdGender = 'female';
            break;
         default:
            $hdGender = 'unknown';
      }
      $url = $this->title->escapeFullURL();
      $this->historicalData = "<meta itemprop=\"url\" content=\"$url\"/><meta itemprop=\"gender\" content=\"$hdGender\"/>";
      if ($fullname) {
         $fullname = htmlspecialchars($fullname);
         $this->historicalData .= "<meta itemprop=\"name\" content=\"$fullname\"/>";
      }
      if ($imageURL) {
         $imageURL = htmlspecialchars($wgServer.$imageURL);
         $this->historicalData .= "<meta itemprop=\"image\" content=\"$imageURL\"/>";
      }
   }

   private function addHistoricalDataEvent($label, $date, $place) {
      $hdDate = '';
      $hdPlace = '';
      if ($date) {
         $hdDate = '<meta itemprop="startDate" content="'.
                   htmlspecialchars(StructuredData::getIsoDate(StructuredData::getDateKey($date))).'"/>';
      }
      if ($place) {
         $pos = mb_strpos($place, '|');
         if ($pos !== false) {
            $place = mb_substr($place, 0, $pos);
         }
         $hdPlace = '<span itemprop="location" itemscope itemtype="http://schema.org/Place">'.
                    '<meta itemprop="name" content="'.htmlspecialchars($place).'"/>'.
                    Person::getHistoricalDataAddress($place).'</span>';
      }
      $this->historicalData .= "<span itemprop=\"$label\" itemscope itemtype=\"http://historical-data.org/HistoricalEvent.html\">$hdDate$hdPlace</span>";
   }

   private static function getHistoricalDataAddress($place) {
      $result = '';
      $pieces = mb_split(',', $place);
      if (count($pieces) > 2) {
         $result .= '<meta itemprop="addressLocality" content="'.htmlspecialchars(trim($pieces[0])).'"/>';
      }
      if (count($pieces) > 1) {
         $result .= '<meta itemprop="addressRegion" content="'.htmlspecialchars(trim($pieces[count($pieces)-2])).'"/>';
      }
      if (count($pieces) > 0) {
         $result .= '<meta itemprop="addressCountry" content="'.htmlspecialchars(trim($pieces[count($pieces)-1])).'"/>';
      }
      if ($result) {
         $result = '<span itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">'.$result.'</span>';
      }
      return $result;
   }

   private function addHistoricalDataRelative($label, $titleString, $fullname) {
      if ($titleString != $this->titleString) {
         $t = Title::newFromText($titleString, NS_PERSON);
         $url = $t->escapeFullURL();
         $itemProp = '';
         if ($label == 'F' || $label == 'M') {
            $itemProp = 'parents';
         }
         else if ($label == 'H' || $label == 'W') {
            $itemProp = 'spouses';
         }
         else if ($label == 'S') {
            $itemProp = 'siblings';
         }
         else if ($label == 'C') {
            $itemProp = 'children';
         }
         $this->historicalData .= '<span itemprop="'.$itemProp.'" itemscope itemtype="http://historical-data.org/HistoricalPerson.html">'.
                                  '<meta itemprop="url" content="'.$url.'"/>'.
                                  '<meta itemprop="name" content="'.htmlspecialchars($fullname).'"/></span>';
      }
   }

   protected function formatFamily($value, $parms) {
		$title = (string)$value['title'];
		$label = $parms[0] . (isset($parms[1]) && $title != (string)$parms[1]['title'] ? ' (alternate)' : '');
		return "<dt>$label<dd>[[Family:" . StructuredData::addBarToTitle($title) . ']]';
	}

   protected function formatPlace($place) {
      return ($place ? '[[Place:' . StructuredData::addBarToTitle($place) . ']]' : '');
   }

   protected function getFamilyMember($member, $label, $addSpouseLink, &$spouseLinks, $defaultName='', $gender='', $familyTitle='') {
      global $wrHostName;

//      $birthKey = 0;
      $yearrange = '';
      if (isset($member)) {
         $title = (string)$member['title'];
         $fullname = StructuredData::getFullname($member);
         if (!$fullname) $fullname = trim(preg_replace('/\(\d+\)\s*$/', '', $title));
         $link = "[[Person:$title|$fullname]]";
         $birthDate = (string)$member['birthdate'] ? (string)$member['birthdate'] : (string)$member['chrdate'];
   //      $birthKey = StructuredData::getDateKey($birthDate, true);
         $beginYear = StructuredData::getYear($birthDate, true);
         $endYear = StructuredData::getYear((string)$member['deathdate'] ? (string)$member['deathdate'] : (string)$member['burialdate'], true);
         if ($beginYear || $endYear) {
            $yearrange = "<span class=\"wr-infobox-yearrange\">$beginYear - $endYear</span>";
         }
         $this->addHistoricalDataRelative($label, $title, $fullname);
      }
      else {
         $fields = explode(' ', $defaultName, 2);
         $given = urlencode($fields[0]);
         $surname = urlencode($fields[1]);
         $sf = urlencode($familyTitle);
         $params = "&g=$given&s=$surname&gnd=$gender&sf=$sf";
         $link = "$defaultName <span class=\"plainlinks wr-infobox-editlink\">([http://$wrHostName/wiki/Special:AddPage?ns=Person$params add])</span>";
      }
      if ($addSpouseLink) {
         $spouseLinks[] = $link;
      }
		if ($label == 'C' || $label == 'S') $label = '';
      if ($label) $label = "<span class=\"wr-infobox-label\">$label</span>.&nbsp; ";
      $result = "<li><span class=\"wr-infobox-fullname\">{$label}{$link}</span>$yearrange</li>";
//      return array($birthKey, $result);
      return $result;
   }

   protected function getFamilyBadge($family, $isParentsSiblings, $gender, $parentFamilies, &$marriageEvents) {
      global $wrHostName;
      
      $marriageKey = 0;
      $title = (string)$family['title'];
      $warning = '';
      $subtitle = '';
      if ($isParentsSiblings) {
         $label = "Parents and Siblings";
         $husbandLabel = 'F';
         $wifeLabel = 'M';
			$childLabel = 'S';
         $class = "wr-infobox-parentssiblings";
         if (count($parentFamilies) > 1) {
            $pfs = array();
            foreach ($parentFamilies as $pf) {
               $t = Title::newFromText($pf, NS_FAMILY);
               $pfs[] = wfUrlencode($t->getDBkey());
            }
            $warning = "Duplicate parents - <span class=\"plainlinks\">[http://$wrHostName/wiki/Special:Compare?ns=Family&compare=".join('|',$pfs)." compare]</span>";
         }
      }
      else {
         $label = "Spouse and Children";
         $husbandLabel = 'H';
         $wifeLabel = 'W';
			$childLabel = 'C';
         $class = "wr-infobox-spousechildren";
      }
      $spouses = '';
      $marriage = '';
      $children = '';
      $spouseLinks = array();
      if ($this->isGedcomPage) {
         if (mb_strpos($title, ' - Excluded (') > 0) {
            $label .= ' - Excluded';
         }
         $spouses = "<li>[[Family:$title|View family members]]<br><span style=\"font-size:75%\">family members not shown in gedcom review</span></li>";
      }
      else {
         $familyObject = new Family($title);
         $familyXml = $familyObject->getPageXml(true);
         if (isset($familyXml)) {
            if (isset($familyXml->husband)) {
               foreach ($familyXml->husband as $spouse) {
                  $spouses .= $this->getFamilyMember($spouse, $husbandLabel, !$isParentsSiblings && $gender == 'F', $spouseLinks);
               }
            }
            else {
               list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($title);
               if ($hg || $hs) {
                  $spouses .= $this->getFamilyMember(null, $husbandLabel, !$isParentsSiblings && $gender == 'F', $spouseLinks, "$hg $hs", 'M', $title);
               }
            }
            if (isset($familyXml->wife)) {
               foreach ($familyXml->wife as $spouse) {
                  $spouses .= $this->getFamilyMember($spouse, $wifeLabel, !$isParentsSiblings && $gender == 'M', $spouseLinks);
               }
            }
            else {
               list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($title);
               if ($wg || $ws) {
                  $spouses .= $this->getFamilyMember(null, $wifeLabel, !$isParentsSiblings && $gender == 'M', $spouseLinks, "$wg $ws", 'F', $title);
               }
            }
            if (isset($familyXml->event_fact)) {
               foreach ($familyXml->event_fact as $eventFact) {
                  if ((string)$eventFact['type'] == 'Marriage') {
                     $marriageDate = (string)$eventFact['date'];
                     $marriageKey = StructuredData::getDateKey($marriageDate, true);
                     $marriage = "<div class=\"wr-infobox-event\">m. <span class=\"wr-infobox-date\">$marriageDate</span></div>";
                  }
                  if (!$isParentsSiblings) {
                     $marriageDesc = (string)$eventFact['desc'];
                     if ($marriageDesc) $marriageDesc .= '<br/>';
                     // sometimes event type is "Reference Number" -- I don't know why
                     $conjunction = @Family::$EVENT_CONJUNCTIONS[(string)$eventFact['type']];
                     $marriageDesc .= $conjunction.' '.join(' or ',$spouseLinks);
                     $marriageEvents[] = array('type' => (string)$eventFact['type'], 'date' => (string)$eventFact['date'],
                        'place' => (string)$eventFact['place'], 'desc' => $marriageDesc,
                        'srcs' => (string)$eventFact['sources'], 'no_citation_needed' => true);
                  }
               }
            }
            foreach ($familyXml->child as $child) {
               $children .= $this->getFamilyMember($child, $childLabel, false, $spouseLinks);
            }
            if ($children) {
               $children = "<ol>$children</ol>";
            }
         }
         else {
            list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($title);
            if ($hg || $hs) {
               $spouses .= $this->getFamilyMember(null, $husbandLabel, false, $spouseLinks, "$hg $hs", 'M', $title);
            }
            if ($wg || $ws) {
               $spouses .= $this->getFamilyMember(null, $wifeLabel, false, $spouseLinks, "$wg $ws", 'F', $title);
            }
         }
         $t = Title::makeTitleSafe(NS_FAMILY, $title);
         $subtitle = "<div class=\"wr-infobox-editlink\"><span class=\"plainlinks\">([".$t->getFullURL('action=edit')." edit])</span></div>";
      }
      if ($warning) {
         $subtitle = "<div class=\"wr-infobox-warning\">$warning</div>";
      }
      $result = <<<END
<div class="wr-infobox wr-infobox-familybadge $class">
   <div class="wr-infobox-heading">[[Family:$title|$label]]</div>$subtitle<ul>$spouses</ul>$marriage$children
</div>
END;
      return array($marriageKey, $result);
   }

   // mode: 0=parents, 1=first spouse, 2=later spouses
   private function getAddFamilyLink($mode, $given='', $surname='') {
      $t = Title::makeTitle(NS_SPECIAL, 'AddPage');
      $titleParam = urlencode($this->title->getText());
      if ($mode == 0) {
         $label = 'Add Parents and Siblings';
         $params = '&ct='.$titleParam;
      }
      else {
         if ($mode == 1) {
            $label = 'Add Spouse and Children';
         }
         else {
            $label = 'Add another spouse & children';
         }
         $pos = mb_strpos($given, ' ');
         if ($pos) {
            $given = mb_substr($given, 0, $pos);
         }
         if (@$this->xml->gender == 'F') {
            $params = '&wt='.$titleParam.'&wg='.urlencode($given).'&ws='.urlencode($surname);
         }
         else {
            $params = '&ht='.$titleParam.'&hg='.urlencode($given).'&hs='.urlencode($surname);
         }
      }
      $url = $t->getFullURL('namespace=Family'.$params);
      $addOtherFamilyLink = ($mode == 2 ? ' addotherfamilylink' : '');
      return <<<END
<div class="plainlinks addfamilylink$addOtherFamilyLink">[$url $label]</div>
END;
   }

   public static function getSummary($xml, $title) {
      // surname | given | gender | birthdate | birthplace | deathdate | deathplace

      list ($given, $surname) = StructuredData::parsePersonTitle($title->getText());
      $gender = '';
      $birthDate = $birthPlace = $deathDate = $deathPlace = '';
      $chrDate = $chrPlace = $burDate = $burPlace = '';
      $birthFound = $deathFound = false;
      if (isset($xml)) {
         $name = (string)@$xml->name['surname'];
         if ($name) {
            $surname = $name;
         }
         $name = (string)@$xml->name['given'];
         if ($name) {
            $given = $name;
         }
         $gender = (string)@$xml->gender;
         if (isset($xml->event_fact)) {
            foreach ($xml->event_fact as $eventFact) {
               if ($eventFact['type'] == 'Birth') {
                  $birthFound = true;
                  $birthDate = (string)@$eventFact['date'];
                  $birthPlace = (string)@$eventFact['place'];
               }
               else if ($eventFact['type'] == 'Christening' || $eventFact['type'] == 'Baptism') {
                  $chrDate = (string)@$eventFact['date'];
                  $chrPlace = (string)@$eventFact['place'];
               }
               else if ($eventFact['type'] == 'Death') {
                  $deathFound = true;
                  $deathDate = (string)@$eventFact['date'];
                  $deathPlace = (string)@$eventFact['place'];
               }
               else if ($eventFact['type'] == 'Burial') {
                  $burDate = (string)@$eventFact['date'];
                  $burPlace = (string)@$eventFact['place'];
               }
            }
         }
      }
      return StructuredData::removeBars($surname).
             '|'.StructuredData::removeBars($given).
             '|'.StructuredData::removeBars($gender).
             '|'.StructuredData::removeBars($birthFound ? $birthDate : $chrDate).
             '|'.StructuredData::removePreBar($birthFound ? $birthPlace : $chrPlace).
             '|'.StructuredData::removeBars($deathFound ? $deathDate : $burDate).
             '|'.StructuredData::removePreBar($deathFound ? $deathPlace : $burPlace);
   }

	/**
	 * Create wiki text from xml property
	 */
	protected function toWikiText($parser) {
//        wfDebug("toWikiText=" . $this->xml->asXML() . "\n");

		global $wgESINHandler, $wgOut, $wgUser, $wrMyHeritageKey;

		$result= '';
		if (isset($this->xml)) {
         // check rename needed
         if (!$this->isGedcomPage && mb_strpos($this->titleString, 'Unknown') !== false) {
            $correctTitle = StructuredData::constructName(@$this->xml->name['given'], @$this->xml->name['surname']);
            if (Person::isRenameNeeded($this->titleString, $correctTitle)) {
               $t = Title::makeTitle(NS_SPECIAL, 'Movepage');
               $url = $t->getLocalURL('target='.$this->title->getPrefixedURL().
                                      '&wpNewTitle='.wfUrlencode("Person:$correctTitle").
                                      '&wpReason='.wfUrlencode('make page title agree with name'));
               $parser->mOutput->mSubtitle = 'This page can be <a href="'.$url.'">renamed</a>';
               $wgOut->setSubtitle($parser->mOutput->mSubtitle);
            }
         }

         // add infoboxes
         $gender = (string)$this->xml->gender;
         switch ($gender) {
            case 'M':
               $genderClass='-male';
               break;
            case 'F':
               $genderClass='-female';
               break;
            default:
               $genderClass='';
         }
         $image = $wgESINHandler->getPrimaryImage($this->xml);
         $imageText = "<div class=\"wr-infobox-noimage{$genderClass}\"></div>";
         $imageURL = '';
         if (isset($image)) {
            $thumbWidth = SearchForm::THUMB_WIDTH;
            $filename = (string)$image['filename'];
            $t = Title::makeTitle(NS_IMAGE, $filename);
            if ($t && $t->exists()) {
               $img = new Image($t);
               $caption = (string)$image['caption'];
               if (!$caption) $caption = $filename;
               $maxWidth = 700;
               $maxHeight = 300;
               $width = $img->getWidth();
               $height = $img->getHeight();
               if ( $maxWidth > $width * $maxHeight / $height ) {
                  $maxWidth = wfFitBoxWidth( $width, $height, $maxHeight );
               }
               $imageURL = $img->createThumb($maxWidth, $maxHeight);
               $caption = str_replace('|',' ',$caption);
               $titleAttr = StructuredData::escapeXml("$imageURL|$maxWidth|$caption");
               $imageText = "<span class=\"wr-imagehover\" title=\"$titleAttr\">[[Image:$filename|{$thumbWidth}x{$thumbWidth}px]]</span>";
            }
         }
         $fullname = StructuredData::getFullname($this->xml->name);
         if (!$fullname) $fullname = '&nbsp;';
         $birthDate = $birthPlace = $deathDate = $deathPlace = '';
         $chrDate = $chrPlace = $burDate = $burPlace = '';
         $birthFound = $deathFound = $chrFound = $burFound = false;
         $birthSource = $deathSource = false;
         if (isset($this->xml->event_fact)) {
            foreach ($this->xml->event_fact as $eventFact) {
               if ($eventFact['type'] == 'Birth') {
                  $birthFound = true;
                  $birthDate = (string)$eventFact['date'];
                  $birthPlace = (string)$eventFact['place'];
                  $birthSource = (string)$eventFact['sources'];
               }
               else if ($eventFact['type'] == 'Christening' || $eventFact['type'] == 'Baptism') {
                  $chrFound = true;
                  $chrDate = (string)$eventFact['date'];
                  $chrPlace = (string)$eventFact['place'];
               }
               else if ($eventFact['type'] == 'Death') {
                  $deathFound = true;
                  $deathDate = (string)$eventFact['date'];
                  $deathPlace = (string)$eventFact['place'];
                  $deathSource = (string)$eventFact['sources'];
               }
               else if ($eventFact['type'] == 'Burial') {
                  $burFound = true;
                  $burDate = (string)$eventFact['date'];
                  $burPlace = (string)$eventFact['place'];
               }
            }
         }
			$this->initHistoricalDataPerson($fullname, $gender, $imageURL);
         $birthLabel = '&nbsp;';
         if ($birthFound) {
            $birthLabel = 'b.';
				$this->addHistoricalDataEvent('birth', $birthDate, $birthPlace);
         }
         else if ($chrFound) {
            $birthLabel = 'chr.';
				$this->addHistoricalDataEvent('christening', $chrDate, $chrPlace);
            $birthDate = $chrDate;
            $birthPlace = $chrPlace;
         }
         $deathLabel = '&nbsp;';
         if ($deathFound) {
            $deathLabel = 'd.';
				$this->addHistoricalDataEvent('death', $deathDate, $deathPlace);
         }
         else if ($burFound) {
            $deathLabel = 'bur.';
				$this->addHistoricalDataEvent('burial', $burDate, $burPlace);
            $deathDate = $burDate;
            $deathPlace = $burPlace;
         }
         $fmtBirthPlace = Person::formatPlace($birthPlace);
         $fmtDeathPlace = Person::formatPlace($deathPlace);

         $familybadges = '';
         $marriageEvents = array();
         $parentFamilies = array();
         foreach ($this->xml->child_of_family as $f) {
            $parentFamilies[] = (string)$f['title'];
         }
         $found = false;
         foreach ($this->xml->child_of_family as $f) {
            list($key, $text) = $this->getFamilyBadge($f, true, $gender, $parentFamilies, $marriageEvents);
            $found = true;
            $familybadges .= $text;
         }
         if (!$found) {
            $familybadges .= $this->getAddFamilyLink(0);
         }
         $sort = array();
         $ix = 0;
         $prevKey = 0;
         $found = false;
         foreach ($this->xml->spouse_of_family as $f) {
            list($key, $text) = $this->getFamilyBadge($f, false, $gender, null, $marriageEvents);
            $found = true;
            if ($key) {
               $prevKey = $key;
            }
            else {
               $key = $prevKey;
            }
            $sort[$key*50+$ix] = $text;
            $ix++;
         }

         $result = <<<END
<div class="wr-infobox wr-infobox-person clearfix">
   <div class="wr-infobox-image">
      $imageText
   </div>
   <div class="wr-infobox-content">
      <div class="wr-infobox-fullname">$fullname</div>
      <div class="wr-infobox-event">$birthLabel<span class="wr-infobox-date">$birthDate</span> <span class="wr-infobox-place">$fmtBirthPlace</span></div>
      <div class="wr-infobox-event">$deathLabel<span class="wr-infobox-date">$deathDate</span> <span class="wr-infobox-place">$fmtDeathPlace</span></div>

   </div>
</div>
<wr_ad></wr_ad>
<div style="margin-top:2px" class="clearfix"><div id="wr_familytreelink" style="float:left">
<span class="wr-familytreelink-text">Family tree</span><span class="wr-familytreelink-arrow">▼</span>
</div><div style="float:right; margin-right:10px;">
</div></div>
END;

         ksort($sort, SORT_NUMERIC);
         foreach ($sort as $key => $text) {
            $familybadges .= $text;
         }
         $familybadges .= $this->getAddFamilyLink(!$found ? 1 : 2, (string)@$this->xml->name['given'], (string)@$this->xml->name['surname']);
         if ($familybadges) {
            $result .= "<div class=\"wr-infobox-familybadges\">$familybadges</div>";
         }
//         $result .= '<div class="visualClearLeft"></div>';

			// add source citations, images, notes
			$result .= $wgESINHandler->addSourcesImagesNotes($this, $parser, $marriageEvents);

            //
            // MyHeritage Ad
            //
            $now = wfTimestampNow();
            if ($wgUser->getOption('wrnoads') < $now) {
                    $firstNames = mb_split(' ', (string)@$this->xml->name['given']);
                    $lastNames = mb_split(' ', (string)@$this->xml->name['surname']);
                    $events = array();
                    $dateKey = StructuredData::getDateKey($birthDate);
                    $birthDay = intval(substr($dateKey, 6, 2));
                    $birthMonth = intval(substr($dateKey, 4, 2));
                    $birthYear = intval(substr($dateKey, 0, 4));
                    $birthPlace = trim($birthPlace);
                    $events = array();
                    if ($birthYear > 0 || strlen($birthPlace) > 0) {
                            $birthEvent = array(
                                    "type" => "birth",
                                    "is_exact" => true,
                                    "is_place_required" => false
                            );
                            if ($birthYear > 0) {
                                    $birthEvent['year'] = $birthYear;
                                    $birthEvent['year_range'] = 0;
                                    if ($birthDay > 0) {
                                            $birthEvent['day'] = $birthDay;
                                    }
                                    if ($birthMonth > 0) {
                                            $birthEvent['month'] = $birthMonth;
                                    }
                            }
                            if (strlen($birthPlace) > 0) {
                                    $pos = mb_strpos($birthPlace, '|');
                                    $birthEvent['place'] = ($pos !== false) ? mb_substr($birthPlace, 0, $pos) : $birthPlace;
                            }
                            $events[] = $birthEvent;
                    }
                    $seconds = time();
                    $payload = "2.ef9898a359d609687dc084175ffba6de.3401.{$seconds}.1.4225";
                    $sig = hash_hmac('md5', $payload, $wrMyHeritageKey);
                    $url = "http://familygraph.myheritage.com/search/query?bearer_token={$payload}.{$sig}";
                    $query = array(
                            "request" => array(
                                    "general_info" => array(
                                            "first_name" => array(
                                                    "data" => $firstNames,
                                                    "advanced_options" => array(
                                                            "is_exact" => true
                                                    )
                                            ),
                                            "last_name" => array(
                                                    "data" => $lastNames,
                                                    "advanced_options" => array(
                                                            "is_exact" => true
                                                    )
                                            ),
                                            "gender" => "M"
                                    ),
                                    "events" => $events,
                                    "relatives" => array(),
                                    "additional_options" => array(
                                            "fallback_policy" => "ppc",
                                            "use_translations" => true,
                                            "categories" => array(
                                                    "birth-marriage-death"
                                            )
                                    )
                            )
                    );
                    $query = json_encode($query);

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($query))
                    );
                    $response = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($code == 200) {
                            $response = json_decode($response);
                            if (response != null) {
                                    $count = $response->response->summary->category_counts[0]->count;
                                    $link = $response->response->summary->category_counts[0]->link;
                                    if ($count > 0) {
                                            $isare = $count == 1 ? 'is' : 'are';
                                            $records = $count == 1 ? 'record' : 'records';
                                            $ad = <<<END
<div class="h2like">Vital Records</div>
<p>There $isare '''$count''' vital $records available on MyHeritage for $fullname, including [$link birth records, marriage records, and death records].
Vital records are historical records that are typically recorded around the actual time of the event, which means they are likely accurate.
Vital records include information like the event date and place, and the person's occupation and residence.
Vital records also often include information about the person's relatives.
For example, birth and marriage records include names of parents and divorce records list the names of children.</p>
<p>[$link See all vital records for $fullname]</p>
END;
                                            $result .= $ad;
                                    }
                            }
                    }
            }

			// add categories
			$surnames = array();
			$surnames[] = (string)$this->xml->name['surname'];
			foreach ($this->xml->alt_name as $altName) {
			   $surnames[] = (string)$altName['surname'];
			}
			$places = ESINHandler::getPlaces($this->xml);
         
			$result .= StructuredData::addCategories($surnames, $places, false);

		}
		return $result;
	}

	protected function addNameInput($nameNum, $name, $display = 'inline') {
		$typeString = '';
		$given = '';
		$surname = '';
		$titlePrefix = '';
		$titleSuffix = '';
		$sources = '';
		$notes = '';
		if (isset($name)) {
			$typeString = htmlspecialchars((string)$name['type']);
			$given = htmlspecialchars((string)$name['given']);
			$surname = htmlspecialchars((string)$name['surname']);
			$titlePrefix = htmlspecialchars((string)$name['title_prefix']);
			$titleSuffix = htmlspecialchars((string)$name['title_suffix']);
			$sources = htmlspecialchars((string)$name['sources']);
			$notes = htmlspecialchars((string)$name['notes']);
		}
		$result = '<tr>';
		if ($nameNum == 0) {
			$result .= "<td><span style=\"display:$display\">Preferred name</span></td>";
		}
		else {
			$result .= "<td><select class=\"n_select\" tabindex=\"1\" name=\"alt_name$nameNum\">" .
			'<option value="Unknown"' . (empty($typeString) || $typeString == 'Unknown' ? ' selected="selected"' : '') . '>Type of name</option>';
			foreach (self::$NAME_TYPES as $nameType) {
				$result .= '<option value="'.$nameType.'"'.($typeString == $nameType ? ' selected="selected"' : '').'>'.$nameType.'</option>';
			}
			$result .= '</select></td>';
		}
      $result .= "<td><input class=\"n_presuf\" tabindex=\"1\" type=\"text\" name=\"title_prefix$nameNum\" value=\"$titlePrefix\"/></td>";
      $result .= "<td><input id=\"given$nameNum\" class=\"n_given\" tabindex=\"1\" type=\"text\" name=\"given$nameNum\" value=\"$given\"/></td>";
      $result .= "<td><input id=\"surname$nameNum\" class=\"n_surname\" tabindex=\"1\" type=\"text\" name=\"surname$nameNum\" value=\"$surname\"/></td>";
		$result .= "<td><input class=\"n_presuf\" tabindex=\"1\" type=\"text\" name=\"title_suffix$nameNum\" value=\"$titleSuffix\"/></td>";
		$rowNum = $nameNum+1;
		$result .= "<td class=\"n_plus\"><a title='Add a source for this name' href=\"#sourcesSection\" onClick=\"addRef('name_input',$rowNum,6,newSource());\">+</a></td>";
		$result .= '<td><input class="n_ref" tabindex="1" type="text" name="name_sources'.$nameNum.'" value="'.$sources.'"/></td>';
		$result .= "<td class=\"n_plus\"><a title='Add a note for this name' href=\"#notesSection\" onClick=\"addRef('name_input',$rowNum,8,newNote());\">+</a></td>";
		$result .= '<td><input class="n_ref" tabindex="1" type="text" name="name_notes'.$nameNum.'" value="'.$notes.'"/></td>';
		if ($nameNum > 0) {
			$result .= "<td><a title='Remove this name' href=\"javascript:void(0)\" onClick=\"removeName($rowNum); return preventDefaultAction(event);\">remove</a></td>";
		}
		$result .= '</tr>';
		return $result;
	}

	public static function getPageText($givenname, $surname, $gender, $birthdate, $birthplace, $deathdate, $deathplace,
	                                    $titleString, $pageids = NULL, $parentFamily='', $spouseFamily='',
                                       $chrdate='', $chrplace='', $burdate='', $burplace='') {
      // standardize places
      $placeTitles = array();
      if ($birthplace && mb_strpos($birthplace, '|') === false) $placeTitles[] = $birthplace;
      if ($deathplace && mb_strpos($deathplace, '|') === false) $placeTitles[] = $deathplace;
      if ($placeTitles) {
         $correctedTitles = PlaceSearcher::correctPlaceTitles($placeTitles);
         $correctedPlace = @$correctedTitles[$birthplace];
         if ($correctedPlace) $birthplace = strcasecmp($birthplace,$correctedPlace) == 0 ? $correctedPlace : $correctedPlace . '|' . $birthplace;
         $correctedPlace = @$correctedTitles[$deathplace];
         if ($correctedPlace) $deathplace = strcasecmp($deathplace,$correctedPlace) == 0 ? $correctedPlace : $correctedPlace . '|' . $deathplace;
      }

		$result = "<person>\n";
		if ($givenname || $surname) {
			$result .= '<name given="'.StructuredData::escapeXml(trim($givenname)).'" surname="'.StructuredData::escapeXml(trim($surname))."\"/>\n";
		}
		$db =& wfGetDB(DB_MASTER); // make sure we get the most current version
      $spouseFamilies = '';
      $parentFamilies = '';
      $images = '';
		$imageId = 0;
		if ($pageids) {
			foreach ($pageids as $pageid) {
				$revision = Revision::loadFromPageId($db, $pageid);
				if ($revision) {
					if ($revision->getTitle()->getNamespace() == NS_FAMILY) {
						$text = $revision->getText();
						$xml = StructuredData::getXml('family', $text);
						if (isset($xml)) {
							$familyTitle = StructuredData::escapeXml($revision->getTitle()->getText());
							foreach ($xml->husband as $member) {
								if ((string)$member['title'] == $titleString) {
									$spouseFamilies .= '<spouse_of_family title="'.$familyTitle."\"/>\n";
									if (!$gender) $gender = 'M';
								}
							}
							foreach ($xml->wife as $member) {
								if ((string)$member['title'] == $titleString) {
									$spouseFamilies .= '<spouse_of_family title="'.$familyTitle."\"/>\n";
									if (!$gender) $gender = 'F';
								}
							}
							foreach ($xml->child as $member) {
								if ((string)$member['title'] == $titleString) {
									$parentFamilies .= '<child_of_family title="'.$familyTitle."\"/>\n";
								}
							}
						}
					}
					else if ($revision->getTitle()->getNamespace() == NS_IMAGE) {
						$text = $revision->getText();
						$xml = StructuredData::getXml('image_data', $text);
						if (isset($xml)) {
							$imageTitle = StructuredData::escapeXml($revision->getTitle()->getText());
							foreach ($xml->person as $person) {
								if ((string)$person['title'] == $titleString) {
									$imageId++;
									$images .= '<image id="I'.$imageId.'" filename="'.$imageTitle."\"/>\n";
								}
							}
						}					
					}
				}
			}
		}
      if ($parentFamily) {
         $parentFamilies .= '<child_of_family title="'.StructuredData::escapeXml($parentFamily)."\"/>\n";
      }
      if ($spouseFamily) {
         $spouseFamilies .= '<spouse_of_family title="'.StructuredData::escapeXml($spouseFamily)."\"/>\n";
      }
		if ($gender) {
		   $result .= '<gender>'.StructuredData::escapeXml($gender)."</gender>\n";
		}
      $result .= $parentFamilies;
      $result .= $spouseFamilies;
      if ($birthdate || $birthplace) {
         $result .= '<event_fact type="Birth" date="'.StructuredData::escapeXml($birthdate).'" place="'.StructuredData::escapeXml($birthplace)."\"/>\n";
      }
      if ($deathdate || $deathplace) {
         $result .= '<event_fact type="Death" date="'.StructuredData::escapeXml($deathdate).'" place="'.StructuredData::escapeXml($deathplace)."\"/>\n";
      }
      if ($chrdate || $chrplace) {
         $result .= '<event_fact type="Christening" date="'.StructuredData::escapeXml($chrdate).'" place="'.StructuredData::escapeXml($chrplace)."\"/>\n";
      }
      if ($burdate || $burplace) {
         $result .= '<event_fact type="Burial" date="'.StructuredData::escapeXml($burdate).'" place="'.StructuredData::escapeXml($burplace)."\"/>\n";
      }
      $result .= $images;
		$result .= "</person>\n";
		return $result;
	}
	
	// construct the page text from what links here
	protected function getPageTextFromWLH($toEditFields, $request = NULL) {
		// don't construct the name or get birth/death from request if called by propagation
		if ($toEditFields && $request) {
			$givenname = $request->getVal('g');
			$surname = $request->getVal('s');
			if (!$givenname && !$surname && preg_match('/([^ ]+)\s+([^(]+)/', $this->titleString, $m)) {
				$givenname = $m[1];
				$surname = $m[2];
			}
			$gender = $request->getVal('gnd');
			if ($request->getval('bt') == 'chr') {
			   $birthdate = '';
			   $birthplace = '';
   			$chrdate = $request->getVal('bd');
	   		$chrplace = $request->getVal('bp');
			}
			else {
            $birthdate = $request->getVal('bd');
            $birthplace = $request->getVal('bp');
            $chrdate = '';
            $chrplace = '';
			}
			if ($request->getval('dt') == 'bur') {
			   $deathdate = '';
			   $deathplace = '';
   			$burdate = $request->getVal('dd');
	   		$burplace = $request->getVal('dp');
			}
			else {
            $deathdate = $request->getVal('dd');
            $deathplace = $request->getVal('dp');
            $burdate = '';
            $burplace = '';
			}
		}
		else {
			$givenname = $surname = $gender = $birthdate = $birthplace = $deathdate = $deathplace = $chrdate = $chrplace = $burdate = $burplace = '';
		}
		$pageids = $this->getWhatLinksHere();
		return Person::getPageText($givenname, $surname, $gender, $birthdate, $birthplace, $deathdate, $deathplace,
		                           $this->titleString, $pageids, '', '',
		                           $chrdate, $chrplace, $burdate, $burplace);
	}

	protected function addFamilyInput($families, $name, $header, $msgTip, $style, $tm, $invalidStyle) {
		$rows = '';
		$i = 0;
		if ($this->isGedcomPage) {
			foreach ($families as $family) {
			   $f = htmlspecialchars($family);
			   $rows .= "<tr><td><input type=\"hidden\" name=\"{$name}_id$i\" value=\"". ($i+1) ."\"/></td>".
			      "<td>$f<input id=\"$name$i\" class=\"family_input\" tabindex=\"1\" type=\"hidden\" name=\"$name$i\" value=\"$f\"/></td>".
			      "</tr>";
				$i++;
			}
			return "<h2>$header</h2>"
			   ."<table id=\"{$name}_table\" border=0>"
			   ."$rows</table><br><br>";
		}
		else {
			$ns = NS_FAMILY;
			foreach ($families as $family) {
			   $f = htmlspecialchars($family);
			   $s = $style;
		   	if (!StructuredData::titleStringHasId($family) || !StructuredData::titleExists(NS_FAMILY, $family)) {
			   	$s = $invalidStyle;
		   	}
            $editable = false;
			   $rows .= "<tr><td>&nbsp;<input type=\"hidden\" name=\"{$name}_id$i\" value=\"". ($i+1) ."\"/></td>".
			      "<td><input id=\"$name$i\" class=\"family_input\" tabindex=\"1\" type=\"text\" size=40 name=\"$name$i\"$s value=\"$f\"".($editable ? '' : ' readonly="readonly"')."/></td>".
					"<td><a href=\"javascript:void(0)\" onClick=\"removePersonFamily('$name',$i); return preventDefaultAction(event);\">remove</a></td>" .
			      "</tr>";
				$i++;
			}
//			if (count($families) == 0) {
//			   $rows .= "<tr><td><input type=\"hidden\" name=\"{$name}_id$i\" value=\"". ($i+1) ."\"/></td>".
//			      "<td><input id=\"$name$i\" class=\"family_input\" tabindex=\"1\" type=\"text\" size=40 name=\"$name$i\" value=\"\"/></td>".
//					"<td><a href=\"javascript:void(0)\" onClick=\"choose($ns,'$name$i'); return preventDefaultAction(event);\"><b>find/add&nbsp;&raquo;</b></a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".
//                  "<a href=\"javascript:void(0)\" onClick=\"removePersonFamily('$name',$i); return preventDefaultAction(event);\">remove</a></td>" .
//			      "</tr>";
//			}
//         if ($addingToFamily) {
//            $rows .= '<tr><td colspan="3"><font color="red">The family will be added when the <i>Family page</i> is saved.</font></td></tr>';
//         }
         if ($name == 'spouse_of_family') {
            $linkText = 'Add spouse and children';
            $display = 'block';
         }
         else {
            $linkText = "Add parents";
            $display = (count($families) == 0 ? 'block' : 'none');
         }
			return "<h2>$header<small>".$tm->addMsgTip($msgTip, 400)."</small></h2>"
			   ."<table id=\"{$name}_table\" border=0>"
			   .$rows.'</table>'
            ."<div id=\"{$name}_addlink\" style=\"display:$display\" class=\"addMemberLink\">"
            ."<a href=\"javascript:void(0)\" onClick=\"addPage('$name'); return preventDefaultAction(event);\">$linkText</a></div>";
//            ."<a id=\"{$name}_link\" href=\"javascript:void(0)\" onClick=\"addPage('$name','new'); return preventDefaultAction(event);\">Find/Add another family page</a><br>"
//            .($rows ? '' : "<br>");
		}
	}
	
   private function addRequestMembers($var, &$titles) {
      global $wgRequest;

      $title = $wgRequest->getVal($var);
      if ($title) {
         $titles[] = $title;
      }
   }

	/**
     * Create edit fields from xml property
     * textbox1 passed by reference for efficiency; don't change it
     */
	protected function toEditFields(&$textbox1) {
		global $wgOut, $wgScriptPath, $wgESINHandler, $wgRequest;

      $result = '';
//      $target = $wgRequest->getVal('target');

		// add javascript functions
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/jquery.tablednd_0_5.yui.1.js\"></script>");
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/personfamily.31.js\"></script>");
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.10.js\"></script>");

		$tm = new TipManager();

		$name = null;
		$altNames = null;
		$invalidStyle = ' style="background-color:#fdd;"';
		$genderStyle = '';
		$childOfFamilyStyle = '';
		$spouseOfFamilyStyle = '';
		$genderString = '';
		$childOfFamilies = array();
		$spouseOfFamilies = array();
		$exists = isset($this->xml);
		if (!$exists) { // && !StructuredData::isRedirect($textbox1)) {
			// construct <person> text from What Links Here and from request
			$oldText = $this->getPageTextFromWLH(true, $wgRequest);
			$this->xml = StructuredData::getXml('person', $oldText);
		}
		if (isset($this->xml)) {
			$name = $this->xml->name;
			$altNames = $this->xml->alt_name;
			$genderString = (string)$this->xml->gender;
			$childOfFamilies = StructuredData::getTitlesAsArray($this->xml->child_of_family);
			$spouseOfFamilies = StructuredData::getTitlesAsArray($this->xml->spouse_of_family);
		}
      $this->addRequestMembers('pf', $childOfFamilies);
      $this->addRequestMembers('sf', $spouseOfFamilies);

		if (ESINHandler::isLiving($this->xml)) {
		   $result .= "<p><font color=red>This person was born/christened less than 110 years ago and does not have a death/burial date.  Living people cannot be entered into WeRelate.org.</font></p>";
		}
	   else if (!$this->isGedcomPage && !StructuredData::titleStringHasId($this->titleString)) {
	      $result .= "<p><font color=red>The page title does not have an ID; please create a page with an ID using <a href='/wiki/Special:AddPage/Person'>Add page</a></font></p>";
	   }
	   if ($exists && !$genderString) {
	   	$result .= "<p><font color=red>You must select a gender</font></p>";
	   	$genderStyle = $invalidStyle;
	   }
	   if (StructuredData::titlesOverlap($childOfFamilies,$spouseOfFamilies)) {
	   	$result .= "<p><font color=red>This person cannot be a child of and a spouse of the same family</font></p>";
	   	$childOfFamilyStyle = $invalidStyle;
	   	$spouseOfFamilyStyle = $invalidStyle;
	   }
	   if (!$this->isGedcomPage && (StructuredData::titlesMissingId($childOfFamilies) || !StructuredData::titlesExist(NS_FAMILY, $childOfFamilies))) {
	   		$result .= "<p><font color=red>Parents family page not found; please remove it, save this page, then add a new one</font></p>";
	   }
	   if (!$this->isGedcomPage && (StructuredData::titlesMissingId($spouseOfFamilies) || !StructuredData::titlesExist(NS_FAMILY, $spouseOfFamilies))) {
   		$result .= "<p><font color=red>Spouse family page not found; please remove it, save this page, then add a new one</font></p>";
	   }
      if (ESINHandler::hasAmbiguousDates($this->xml)) {
         $result .= "<p><font color=red>Please write dates in \"<i>D MMM YYYY</i>\" format so they are unambiguous (ie 5 Jan 1900)</font></p>";
      }

		// add name input table
		$rows = '';
		$display = 'none';
		if (isset($altNames)) {
			$i = 1;
			foreach ($altNames as $altName) {
				$display = 'inline';
				$rows .= $this->addNameInput($i, $altName);
				$i++;
			}
		}
		$result .= '<h2>Name</h2>';
		$result .= '<table id="name_input" border=0 cellpadding=3>' .
		'<tr><th></th><th>Name&nbsp;prefix'.$tm->addMsgTip('TitlePrefix').'</th><th>Given'.$tm->addMsgTip('GivenName').'</th><th>Surname'.$tm->addMsgTip('Surname').'</th>'.
			'<th>Name&nbsp;suffix'.$tm->addMsgTip('TitleSuffix').'</th>'.
         '<th></th><th>Source(s)'.$tm->addMsgTip('NameSourceIDs').'</th><th></th><th>Note(s)'.$tm->addMsgTip('NameNoteIDs').'</th></tr>' .
		$this->addNameInput(0, $name, $display) .
		$rows .
		'</table><div class="addESINLink"><a href="javascript:void(0);" onClick="addName(\''.implode(',',self::$NAME_TYPES).'\'); return preventDefaultAction(event);">Add alternate name</a></div>';

		// add gender input
		$result .= '<br><br><label for="gender">Gender: </label>'
			. StructuredData::addSelectToHtml(1, 'gender', Person::$GENDER_OPTIONS, $genderString, $genderStyle)
			. '<br><br>';
		// add child of family input
		$result .= $this->addFamilyInput($childOfFamilies, 'child_of_family', 'Parents and siblings family',
                                       'ChildOfFamily', $childOfFamilyStyle, $tm, $invalidStyle); //, substr($target, 0, 5) == 'child');

		// add spouse of family input
		$result .= $this->addFamilyInput($spouseOfFamilies, 'spouse_of_family', 'Spouse and children family',
                                       'SpouseOfFamily', $spouseOfFamilyStyle, $tm, $invalidStyle); //, substr($target, 0, 4) == 'wife' || substr($target, 0, 7) == 'husband');

		// add event_fact input table
		$result .= $wgESINHandler->addEventsFactsInput($this->xml, self::$STD_EVENT_TYPES, self::$OTHER_EVENT_TYPES);

		// add sources, images, notes input tables
		$result .= $wgESINHandler->addSourcesImagesNotesInput($this->xml);

		$result .= $tm->getTipTexts();

		$result .= '<h2>Personal History</h2>';

		return $result;
	}

	protected function formatFamilyElement($tag, $titleString) {
	   $title = Title::newFromText($titleString,NS_FAMILY);
	   if ($title) {
	   	return $this->addMultiAttrFieldToXml(array('title' => $title->getText()), $tag);
	   }
	   else {
	   	return '';
	   }
	}

	protected function fromFamily($request, $name) {
	   $result = '';
	   $seenTitles = array();
		for ($i = 0; $request->getVal("{$name}_id$i"); $i++) {
		   $titleString = urldecode($request->getVal("$name$i"));
		   if (!$this->isGedcomPage && $titleString) {
		   	$title = Title::newFromText($titleString, NS_FAMILY);
		   	if ($title) {
		   		$title = StructuredData::getRedirectToTitle($title); // ok to read from slave here; mistakes will get corrected in propagate
		   		$titleString = $title->getText();
		   	}
		   	else {
		   		$titleString = '';
		   	}
		   }
		   if ($titleString && !in_array($titleString, $seenTitles)) {
		      $seenTitles[] = $titleString;
		      if (!$this->isGedcomPage && !StructuredData::titleStringHasId($titleString)) {
		         $titleString = StructuredData::standardizeNameCase($titleString);
		      }
	      	$result .= $this->formatFamilyElement($name, $titleString);
		   }
		}
	   return $result;
	}
	
	public static function addFamilyToRequestData(&$requestData, $name, $i, $titleString) {
		$requestData["{$name}_id$i"] = $i+1;
		$requestData["$name$i"] = $titleString;
	}
	
	/**
     * Return xml elements from data in request
     * @param unknown $request
     */
	protected function fromEditFields($request) {
		global $wgESINHandler;
		//		wfDebug("WR:FromEditFields\n");
		$result = '';
		$wgESINHandler->generateSINMap($request); // must be called before fromEventsFacts or fromSourcesImagesNotes or mapSIN

		$primaryNameFound = false;
		for ($i = 0; $i == 0 || $request->getVal("alt_name$i"); $i++) {
			$given = $request->getVal("given$i");
			$surname = $request->getVal("surname$i");
			$titlePrefix = $request->getVal("title_prefix$i");
			$titleSuffix = $request->getVal("title_suffix$i");
			$sources = $wgESINHandler->mapSIN($request->getVal("name_sources$i"));
			$notes = $wgESINHandler->mapSIN($request->getVal("name_notes$i"));
			if (!StructuredData::isEmpty($given) ||
			!StructuredData::isEmpty($surname) ||
			!StructuredData::isEmpty($titlePrefix) ||
			!StructuredData::isEmpty($titleSuffix) ||
			!StructuredData::isEmpty($sources) ||
			!StructuredData::isEmpty($notes)) {
				$type = ($i == 0 ? '' : $request->getVal("alt_name$i"));
				if (!$primaryNameFound && ($i == 0 || $type == PERSON::$ALT_NAME_TAG)) {
					$primaryNameFound = true;
					$result .= $this->addMultiAttrFieldToXml(array(
							'given' => $given,
							'surname' => $surname,
							'title_prefix' => $titlePrefix,
							'title_suffix' => $titleSuffix,
							'sources' => $sources,
							'notes' => $notes),
						'name');
				}
				else {
					$result .= $this->addMultiAttrFieldToXml(array(
							'type' => $type,
							'given' => $given,
							'surname' => $surname,
							'title_prefix' => $titlePrefix,
							'title_suffix' => $titleSuffix,
							'sources' => $sources,
							'notes' => $notes),
						'alt_name');
				}
				
			}
		}
		$result .= $this->addSingleLineFieldToXml($request->getVal('gender'), 'gender');
		$result .= $this->fromFamily($request, 'child_of_family');
		$result .= $this->fromFamily($request, 'spouse_of_family');

		$result .= $wgESINHandler->fromEventsFacts($request, self::$STD_EVENT_TYPES);

		$result .= $wgESINHandler->fromSourcesImagesNotes($request);
		
		$wgESINHandler->clearSINMap();

		return $result;
	}
	
	/**
	 * Add primary or alternate name to request data
	 *
	 * @param unknown_type $requestData
	 * @param unknown_type $i if i == 0, this is the primary name
	 * @param unknown_type $type only set for alternate names (i > 0)
	 */
	public static function addNameToRequestData(&$requestData, $i, $type, $given, $surname, $titlePrefix, $titleSuffix, $sources, $notes) {
		if ($i > 0) {
			$requestData["alt_name$i"] = $type;
		}
		$requestData["given$i"] = $given;
		$requestData["surname$i"] = $surname;
		$requestData["title_prefix$i"] = $titlePrefix;
		$requestData["title_suffix$i"] = $titleSuffix;
		$requestData["name_sources$i"] = $sources;
		$requestData["name_notes$i"] = $notes;
	}
	
	public static function addGenderToRequestData(&$requestData, $gender) {
		$requestData['gender'] = $gender;
	}

    /**
     * Return true if xml property is valid
     * textbox1 passed by reference for efficiency; don't change it
     */
    protected function validateData(&$textbox1) {
       global $wgUser;
		 if (!StructuredData::titleStringHasId($this->titleString)) {
		 	return false;
		 }
       if (ESINHandler::hasAmbiguousDates($this->xml)) {
          return false;
       }
       if (!StructuredData::isRedirect($textbox1)) {
   		$parentFamilies = StructuredData::getTitlesAsArray($this->xml->child_of_family);
   		$spouseFamilies = StructuredData::getTitlesAsArray($this->xml->spouse_of_family);
   		return (!ESINHandler::isLiving($this->xml)
   		         && strlen((string)$this->xml->gender) > 0
   		         && !StructuredData::titlesOverlap($parentFamilies, $spouseFamilies)
   		         && ($this->isGedcomPage || !StructuredData::titlesMissingId($parentFamilies))
   		         && ($this->isGedcomPage || !StructuredData::titlesMissingId($spouseFamilies))
                  && ($wgUser->isAllowed('patrol') || StructuredData::titlesExist(NS_FAMILY, $parentFamilies))
                  && ($wgUser->isAllowed('patrol') || StructuredData::titlesExist(NS_FAMILY, $spouseFamilies))
         );
       }
       return true;
    }

   public static function propagatedFieldsChanged(&$propagatedData, &$origPropagatedData, $familyTag = null) {
		return $propagatedData['given']    != $origPropagatedData['given'] || 
			 $propagatedData['surname']     != $origPropagatedData['surname'] || 
			 $propagatedData['titlePrefix'] != $origPropagatedData['titlePrefix'] || 
			 $propagatedData['titleSuffix'] != $origPropagatedData['titleSuffix'] ||
			 $propagatedData['birthDate']   != $origPropagatedData['birthDate'] || 
			 $propagatedData['birthPlace']  != $origPropagatedData['birthPlace'] || 
			 $propagatedData['chrDate']     != $origPropagatedData['chrDate'] || 
			 $propagatedData['chrPlace']    != $origPropagatedData['chrPlace'] ||
			 $propagatedData['deathDate']   != $origPropagatedData['deathDate'] || 
			 $propagatedData['deathPlace']  != $origPropagatedData['deathPlace'] || 
			 $propagatedData['burDate']     != $origPropagatedData['burDate'] || 
			 $propagatedData['burPlace']    != $origPropagatedData['burPlace'] ||
			 ($familyTag == 'spouse_of_family' && $propagatedData['firstParentFamily'] != $origPropagatedData['firstParentFamily']);
   }
   
   public static function getPropagatedElement($tag, $title, &$pd, $familyTag = null) {
		$title = StructuredData::escapeXml($title);
		$given = StructuredData::escapeXml($pd['given']);
		$surname = StructuredData::escapeXml($pd['surname']);
		$titlePrefix = StructuredData::escapeXml($pd['titlePrefix']);
		$titleSuffix = StructuredData::escapeXml($pd['titleSuffix']);
		$birthDate = StructuredData::escapeXml($pd['birthDate']);
		$birthPlace = StructuredData::escapeXml($pd['birthPlace']);
		$chrDate = StructuredData::escapeXml($pd['chrDate']);
		$chrPlace = StructuredData::escapeXml($pd['chrPlace']);
		$deathDate = StructuredData::escapeXml($pd['deathDate']);
		$deathPlace = StructuredData::escapeXml($pd['deathPlace']);
		$burDate = StructuredData::escapeXml($pd['burDate']);
		$burPlace = StructuredData::escapeXml($pd['burPlace']);
		$firstParentFamily = ($familyTag == 'spouse_of_family' ? StructuredData::escapeXml($pd['firstParentFamily']) : '');

		return "<$tag title=\"$title\"" .
		($given ? " given=\"$given\"" : '') .
		($surname ? " surname=\"$surname\"" : '') .
		($titlePrefix ? " title_prefix=\"$titlePrefix\"" : '') .
		($titleSuffix ? " title_suffix=\"$titleSuffix\"" : '') .
		($birthDate ? " birthdate=\"$birthDate\"" : '') .
		($birthPlace ? " birthplace=\"$birthPlace\"" : '') .
		($chrDate ? " chrdate=\"$chrDate\"" : '') .
		($chrPlace ? " chrplace=\"$chrPlace\"" : '') .
		($deathDate ? " deathdate=\"$deathDate\"" : '') .
		($deathPlace ? " deathplace=\"$deathPlace\"" : '') .
		($burDate ? " burialdate=\"$burDate\"" : '') .
		($burPlace ? " burialplace=\"$burPlace\"" : '') .
		($firstParentFamily ? " child_of_family=\"$firstParentFamily\"" : '') .
		"/>\n";
   }
    
	public static function getPropagatedData($xml) {
		$o = array();
		$o['given'] = null;
		$o['surname'] = null;
		$o['titlePrefix'] = null;
		$o['titleSuffix'] = null;
		$o['gender'] = null;
		$o['birthDate'] = null;
		$o['birthPlace'] = null;
		$o['chrDate'] = null;
		$o['chrPlace'] = null;
		$o['deathDate'] = null;
		$o['deathPlace'] = null;
		$o['burDate'] = null;
		$o['burPlace'] = null;
		$o['firstParentFamily'] = null;
		$o['parentFamilies'] = array();
		$o['spouseFamilies'] = array();
		$o['images'] = array();
		if (isset($xml)) {
			$name = $xml->name;
			if (isset($name)) {
				$o['given'] = (string)$name['given'];
				$o['surname'] = (string)$name['surname'];
				$o['titlePrefix'] = (string)$name['title_prefix'];
				$o['titleSuffix'] = (string)$name['title_suffix'];
			}
			$o['gender'] = (string)$xml->gender;
			list ($birthDate, $birthPlace, $chrDate, $chrPlace, $deathDate, $deathPlace, $burDate, $burPlace, $deathDesc, $burDesc)
			      = ESINHandler::getBirthChrDeathBurDatePlaceDesc($xml);
			$o['birthDate'] = $birthDate;
			$o['birthPlace'] = $birthPlace;
			$o['chrDate'] = $chrDate;
			$o['chrPlace'] = $chrPlace;
			$o['deathDate'] = $deathDate;
			$o['deathPlace'] = $deathPlace;
			$o['burDate'] = $burDate;
			$o['burPlace'] = $burPlace;
			foreach ($xml->child_of_family as $pf) {
				if (!$o['firstParentFamily']) {
					$o['firstParentFamily'] = (string)$pf['title'];
				}
				$o['parentFamilies'][] = (string)$pf['title'];
			}
			$o['spouseFamilies'] = StructuredData::getTitlesAsArray($xml->spouse_of_family);
			foreach($xml->image as $i) {
				$o['images'][] = array('filename' => (string)$i['filename'], 'caption' => (string)$i['caption']);
			}
		}
		return $o;
	}

	// update a family link on a person page
	// tag is spouse_of_family or child_of_family
	public static function updateFamilyLink($tag, $oldTitle, $newTitle, &$text, &$textChanged) {
		if ($newTitle) {
			$new = Family::getPropagatedElement($tag, $newTitle, $pd);
		}
		else {
			$new = '';
		}
		$old = "<$tag title=\"" . StructuredData::protectRegexSearch(StructuredData::escapeXml($oldTitle)) . "\"/>\n";
      if (!preg_match('$'.$old.'$', $text)) {
         $old = ESINHandler::findRelationshipInsertionPointTag($tag, $text);
			$new .= $old;
		}

      $result = preg_replace('$'.$old.'$', StructuredData::protectRegexReplace($new), $text, 1);

		if ($result != $text) {
			$text = $result;
			$textChanged = true;
		}
	}

	private function updateFamily($familyTitle, $familyTag, $newPersonTag, $newTitle, &$propagatedData, &$text, &$textChanged) {
		if (!PropagationManager::isPropagatablePage($familyTitle)) {
	      return true;
	   }

	   $result = true;
		$article = StructuredData::getArticle($familyTitle, true);
		if ($article) {
			$content =& $article->fetchContent(); // fetches from master
			$updated = false;
			Family::updatePersonLink($newPersonTag, $this->titleString, $newTitle, $propagatedData, $familyTag, $content, $updated);
			if ($updated) {
				$result = $article->doEdit($content, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
            StructuredData::purgeTitle($familyTitle, +1); // purge family with a fudge factor so person link will be blue
			}
			else {
			   error_log("propagating person {$this->titleString} nothing changed in {$familyTitle->getPrefixedText()}");
			}

			// if we're not deleting this entry (newTitle is not empty), and the family article is a redirect (article title != familyTitle),
			// we need to update the family page title in the person page text
			if ($newTitle && $familyTitle->getText() != $article->getTitle()->getText()) {
				$old = 'title="' . StructuredData::escapeXml($familyTitle->getText()) . '"';
				$new = 'title="' . StructuredData::escapeXml($article->getTitle()->getText()) . '"';
				$text = str_replace($old, $new, $text);
				$textChanged = true;
			}
		}
		return $result;
	}

	// pass propagatedData by reference because it might be updated; pass origPropgatedData by reference to save cpu
	private function propagateFamilyEditData($families, $origFamilies, $familyTag, $oldPersonTag, $newPersonTag, 
														  &$propagatedData, &$origPropagatedData, &$text, &$textChanged) {
		$result = true;
		
		$addFamilies = array_diff($families, $origFamilies);
		$delFamilies = array_diff($origFamilies, $families);
		$sameFamilies = array_intersect($families, $origFamilies);
		
		// remove from deleted families
		$dummyPropagatedData = Person::getPropagatedData(null);
		foreach ($delFamilies as $f) {
			$familyTitle = Title::newFromText($f, NS_FAMILY);
			PropagationManager::addPropagatedAction($this->title, 'del'.$familyTag, $familyTitle);
			if (PropagationManager::isPropagatableAction($familyTitle, 'del'.($familyTag == 'child_of_family' ? 'child' : 'spouse'), $this->title)) {
				$result = $result && $this->updateFamily($familyTitle, $familyTag, $newPersonTag, null, $dummyPropagatedData, $text, $textChanged);
			}
		}

		// add to new families
		foreach ($addFamilies as $f) {
			$familyTitle = Title::newFromText($f, NS_FAMILY);
			PropagationManager::addPropagatedAction($this->title, 'add'.$familyTag, $familyTitle);
			if (PropagationManager::isPropagatableAction($familyTitle, 'add'.($familyTag == 'child_of_family' ? 'child' : 'spouse'), $this->title)) {
				$result = $result && $this->updateFamily($familyTitle, $familyTag, $newPersonTag, $this->titleString, $propagatedData, $text, $textChanged);
			}
		}

		// update data on same families
		if ($oldPersonTag != $newPersonTag || Person::propagatedFieldsChanged($propagatedData, $origPropagatedData, $familyTag)) {
			foreach ($sameFamilies as $f) {
				$familyTitle = Title::newFromText($f, NS_FAMILY);
				// don't try to propagate changes to a family if this page was just added or deleted as a child of that family
				if (PropagationManager::isPropagatableAction($familyTitle, 'addchild', $this->title) &&
					 PropagationManager::isPropagatableAction($familyTitle, 'delchild', $this->title)) {
					$result = $result && $this->updateFamily($familyTitle, $familyTag, $newPersonTag, $this->titleString, $propagatedData, $text, $textChanged);
				}
			}
		}

		return $result;
	}

	/**
     * Propagate data in xml property to other articles if necessary
     * @param string $oldText contains text being replaced
     * @param String $text new text
     * @param bool $textChanged which we never touch when propagating places
     * @return bool true if propagation was successful
     */
	protected function propagateEditData($oldText, &$text, &$textChanged) {
		global $wrIsGedcomUpload, $wgESINHandler;
		
		$result = true;

      // clear xml cache
      $this->clearPageXmlCache();

		// get current info
		$propagatedData = Person::getPropagatedData($this->xml);
		$redirTitle = Title::newFromRedirect($text);

		// get original info
		$origPropagatedData = Person::getPropagatedData(null);
		// don't bother construction page text from WLH in a gedcom upload because nothing will link to this new page
		if (!@$wrIsGedcomUpload && (!$oldText || mb_strpos($oldText, '<person>') === false)) { // oldText contains MediaWiki:noarticletext if the article is being created
			// construct <person> text from What Links Here
			$oldText = $this->getPageTextFromWLH(false);
		}
		$origXml = null;
		if ($oldText) {
			$origXml = StructuredData::getXml('person', $oldText);
			if (isset($origXml)) {
				$origPropagatedData = Person::getPropagatedData($origXml);
			}
		}
		
		$result = $result && $this->propagateFamilyEditData($propagatedData['parentFamilies'], $origPropagatedData['parentFamilies'], 'child_of_family', 'child', 'child', 
																			 $propagatedData, $origPropagatedData, $text, $textChanged);
		$oldTag = Person::getSpouseTagFromGender($origPropagatedData['gender']);
		$newTag = Person::getSpouseTagFromGender($propagatedData['gender']);
		$result = $result && $this->propagateFamilyEditData($propagatedData['spouseFamilies'], $origPropagatedData['spouseFamilies'], 'spouse_of_family', $oldTag, $newTag, 
																			 $propagatedData, $origPropagatedData, $text, $textChanged);

		if (StructuredData::removeDuplicateLinks('child_of_family|spouse_of_family', $text)) {
			$textChanged = true;
		}
		
		$result = $result && $wgESINHandler->propagateSINEdit($this->title, 'person', $this->titleString, $propagatedData, $origPropagatedData, $text, $textChanged);
		
		// ensure footer tag is still there (might have been removed by editing the last section)
      if ($redirTitle == null && strpos($text, ESINHandler::ESIN_FOOTER_TAG) === false) {
         if (strlen($text) > 0 && substr($text, strlen($text) - 1) != "\n") {
		      $text .= "\n";
		   }
		   $text .= ESINHandler::ESIN_FOOTER_TAG;
		   $textChanged = true;
		}

      // update watchlist summary if changed
      $summary = Person::getSummary($this->xml, $this->title);
      $origSummary = Person::getSummary($origXml, $this->title);
      if ($summary != $origSummary) {
         StructuredData::updateWatchlistSummary($this->title, $summary);
      }
		
		// if it's a redirect, add the people, families, and images that were on this page to the redirect target
		// but don't bother updating the redir target during a merge
		if ($redirTitle != null && PropagationManager::isPropagatablePage($redirTitle)) {
		   // get the text of the redir page
			$article = StructuredData::getArticle($redirTitle, true);
			if ($article) {
				$content =& $article->fetchContent();
				$updated = false;
		   	// add parent families from this page to the redir page
				foreach ($origPropagatedData['parentFamilies'] as $f) {
					Person::updateFamilyLink('child_of_family', $f, $f, $content, $updated);
				}
			   // add spouse families from this page to the redir page
				foreach ($origPropagatedData['spouseFamilies'] as $f) {
					Person::updateFamilyLink('spouse_of_family', $f, $f, $content, $updated);
				}
			   // add images from this page to the redir page
			   foreach ($origPropagatedData['images'] as $i) {
					ESINHandler::updateImageLink('person', $i['filename'], $i['filename'], $i['caption'], $content, $updated);
			   }
			   // update the redir page if necessary
				if ($updated) {
					$result = $result && $article->doEdit($content, 'Copy data from [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
				}
			}
		}
		
		if (!$result) {
			error_log("ERROR! Person edit/rollback not propagated: $this->titleString\n");
		}
		return $result;
	}

	private function getFamilyData($wlhFamilies, $thisFamilies, $tag) {
		$families = array();
      foreach ($wlhFamilies as $f) {
         $families[] = (string)$f['title'];
      }
      foreach ($thisFamilies as $f) {
         $familyTitle = (string)$f['title'];
         $t = Title::newFromText($familyTitle, NS_FAMILY);
         $t = StructuredData::getRedirectToTitle($t, true);
         $familyTitle = $t->getText();
         if (!in_array($familyTitle, $families)) {
            $families[] = $familyTitle;
         }
      }
      $familyText = '';
      foreach ($families as $f) {
         $familyText .= $this->formatFamilyElement($tag, $f);
      }
	   return $familyText;
	}

	/**
     * Propagate move, delete, or undelete to other articles if necessary
     *
     * @param String $newTitleString null in case of delete; same as this title string in case of undelete
     * @param String $text text of article
     * @param bool $textChanged set to true if we change the text
     * @return bool true if success
     */
	protected function propagateMoveDeleteUndelete($newTitleString, $newNs, &$text, &$textChanged) {
		global $wgESINHandler;

		$result = true;
		$newTitle = ($newTitleString ? Title::newFromText($newTitleString, NS_PERSON) : null);

		// if we're undeleting, add additional families from WLH
      if ($this->titleString == $newTitleString) {
			$wlh = simplexml_load_string($this->getPageTextFromWLH(false));
         // get text for all families
			$familyText = $this->getFamilyData($wlh->child_of_family, $this->xml->child_of_family, 'child_of_family') .
			              $this->getFamilyData($wlh->spouse_of_family, $this->xml->spouse_of_family, 'spouse_of_family') .
				           $wgESINHandler->getImageData($wlh->image, $this->xml->image);
			// update text: replace old family information with new
         $text = preg_replace("$<((child|spouse)_of_family|image) [^>]*>\n$", '', $text);
         $text = preg_replace('$</person>$', StructuredData::protectRegexReplace($familyText . '</person>'), $text, 1);
			$this->xml = StructuredData::getXml($this->tagName, $text);
         $textChanged = true;
      }

		// get data to propagate
		$propagatedData = Person::getPropagatedData($this->xml);
		
		foreach ($propagatedData['parentFamilies'] as $f) {
		   $familyTitle = Title::newFromText((string)$f, NS_FAMILY);
			PropagationManager::addPropagatedAction($this->title, 'delchild_of_family', $familyTitle);
			if ($newTitle) PropagationManager::addPropagatedAction($newTitle, 'addchild_of_family', $familyTitle);
			// don't need to check propagated action before calling updateFamily, because propagateMoveDeleteUndelete is never part of a loop
  			$result = $result && $this->updateFamily($familyTitle, 'child_of_family', 'child', $newTitleString, $propagatedData, $text, $textChanged);
		}

		$spouseTag = ($propagatedData['gender'] == 'F' ? 'wife' : 'husband');
		foreach ($propagatedData['spouseFamilies'] as $f) {
		   $familyTitle = Title::newFromText((string)$f, NS_FAMILY);
			PropagationManager::addPropagatedAction($this->title, 'delspouse_of_family', $familyTitle);
			if ($newTitle) PropagationManager::addPropagatedAction($newTitle, 'addspouse_of_family', $familyTitle);
			// don't need to check propagated action before calling updateFamily, because propagateMoveDeleteUndelete is never part of a loop
  			$result = $result && $this->updateFamily($familyTitle, 'spouse_of_family', $spouseTag, $newTitleString, $propagatedData, $text, $textChanged);
		}

		if (StructuredData::removeDuplicateLinks('child_of_family|spouse_of_family', $text)) {
			$textChanged = true;
		}
		
		$result = $result && $wgESINHandler->propagateSINMoveDeleteUndelete($this->title, 'person', $this->titleString, $newTitleString, $propagatedData, $text, $textChanged);

		if (!$result) {
			error_log("ERROR! Person move/delete/undelete not propagated: $this->titleString -> " .
			($newTitleString ? $newTitleString : "[delete]") . "\n");
		}
		return $result;
	}
}
?>
