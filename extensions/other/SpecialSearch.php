<?php
/**
 * @package MediaWiki
 */
require_once("$IP/extensions/gedcom/GedcomUtil.php");
require_once("$IP/extensions/Mobile_Detect.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialSearchSetup";

function wfSpecialSearchSetup() {
	global $wgMessageCache, $wgSpecialPages, $wgParser, $wgHooks;
	
	$wgMessageCache->addMessages( array( "search" => "Search" ) );
	$wgSpecialPages['Search'] = array('SpecialPage','Search');
	$wgHooks['ArticleSaveComplete'][] = 'wrClearRecentChanges';
	$wgHooks['ArticleDeleteComplete'][] = 'wrClearRecentChanges';
	$wgHooks['TitleMoveComplete'][] = 'wrClearRecentChanges';
}

function wrClearRecentChanges() {
	global $wgMemc;
	$cacheKey = SearchForm::getCacheKey();
  	$wgMemc->delete($cacheKey);
}

/**
 * Called to display the Special:Search page
 *
 * @param unknown_type $par
 * @param unknown_type $specialPage
 */
function wfSpecialSearch( $par=NULL, $specialPage ) {
	global $wgOut, $wgRequest, $wgScriptPath, $wrSidebarHtml, $wgUser;
	
	$searchForm = new SearchForm();

	// read query parameters into variables
	$searchForm->readQueryParms($par);

	// check if we should redirect to a specific page
	$redirTitle = $searchForm->getRedirTitle();
	if ($redirTitle) {
		$wgOut->redirect($redirTitle->getFullURL());
		return;
	}
	
	$wgOut->setPageTitle($searchForm->target ? 'Search for possible matches' : 'Search WeRelate');
   $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/search.31.js\"></script>");
	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.10.js\"></script>");

    $now = wfTimestampNow();
    $ns = $wgRequest->getVal('ns');
    if (!$ns) {
        $ns = $par;
    }

   // construct query to send to server
   $errMsg = $searchForm->validateQuery();
   $searchServerQuery = '';
   if (!$errMsg) {
	   $searchServerQuery = $searchForm->getSearchServerQuery($par);
   }
	$formHtml = $searchForm->getFormHtml();
	$mhAd = '';
	if ($searchServerQuery || $errMsg) {
      if ($errMsg) {
         $sideText = '';
         $errMsg = htmlspecialchars($errMsg);
         $results = "<p><font color=\"red\">$errMsg</font></p>";
      }
      else {
        // don't show to people without ads and if not person
        $firstName = $searchForm->givenname;
        $lastName = $searchForm->surname;
        if ($wgUser->getOption('wrnoads') < $now && $lastName) {
            // detect mobile/tablet/desktop
            $detect = new Mobile_Detect;
            $device = 'c';
            if ($detect->isMobile()) {
                if ($detect->isTablet()) {
                    $device = 't';
                }
                else {
                    $device = 'm';
                }
            }
            else if ($detect->isTablet()) {
                $device = 't';
            }

            $mhAd = <<< END
<div style="margin: -23px 0 16px 0;">
<iframe src="https://www.myheritage.com/FP/partner-widget.php?partnerName=werelate&clientId=3401&campaignId=werelate_widgets_+aug19&widget=records_carousel&width=728&height=90&onSitePlacement=Search+People_728x90_records&tr_ifid=werelate_252927986&firstName=$firstName&lastName=$lastName&tr_device=$device&size=728x90" frameborder="0" scrolling="no" width="728" height="90"></iframe></div>
END;
         }
		 list ($sideText, $results) = $searchForm->getSearchResultsHtml($searchServerQuery);
      }
      $wrSidebarHtml = "<div id=\"wr-search-sidebar\">$sideText</div>";
		$wgOut->addHTML(<<< END
$mhAd
<p>$formHtml</p>
$results
END
		);		
	}
	else {
        // don't show to people without ads
        $mhAd = '';
        if ($wgUser->getOption('wrnoads') < $now) {
            if ($ns == 'Person') {
                $mhAdId = '132605092';
            }
            else {
                $mhAdId = '133316437';
            }
            $mhAd = <<< END
<ins class='dcmads' style='display:inline-block;width:728px;height:90px'
    data-dcm-placement='N217801.2353305WERELATE.ORG/B9799048.$mhAdId'
    data-dcm-rendering-mode='iframe'
    data-dcm-https-only
    data-dcm-resettable-device-id=''>
  <script src='https://www.googletagservices.com/dcm/dcmads.js'></script>
</ins>
END;
        }
		$sideText = $searchForm->getStatsHtml();
		$endText = wfMsgWikiHtml('searchend');
      $wrSidebarHtml = "<div id=\"wr-search-sidebar\">$sideText</div>";
		$wgOut->addHTML(<<< END
$mhAd
<p>$formHtml</p><p> </p>
$endText
END
		);
	}
}

 /**
  * Search form used in Special:Search and <search> hook
  */
class SearchForm {
	public $target;
	private $match;
	private $pagetitle;
	private $ecp;
   private $talk;
	private $sort;
	private $titleLetter;
	public $namespace;
	private $watch;
	public $givenname;
	public $surname;
	private $place;
	private $birthdate;
	private $birthrange;
	private $birthplace;
	private $birthType;
	private $deathdate;
	private $deathrange;
	private $deathplace;
	private $deathType;
	private $fatherGivenname;
	private $fatherSurname;
	private $motherGivenname;
	private $motherSurname;
	private $spouseGivenname;
	private $spouseSurname;
	private $husbandGivenname;
	private $husbandSurname;
	private $wifeGivenname;
	private $wifeSurname;
	private $marriagedate;
	private $marriagerange;
	private $marriageplace;
   private $childTitle;
   private $husbandTitle;
   private $wifeTitle;
   private $parentFamily;
   private $spouseFamily;
	private $placename;
	private $locatedinplace;
	private $sourceSubject;
	private $sourceAvailability;
	private $personSurnameFacet;
	private $personGivennameFacet;
	private $personCountryFacet;
	private $personStateFacet;
	private $personCenturyFacet;
	private $personDecadeFacet;
   private $sub;
   private $sup;
	private $personGender;
	private $author;
	private $sourceType;
	private $sourceTitle;
	private $placeIssued;
	private $publisher;
	private $title;
	private $keywords;
	private $go;
   private $start;
   private $rows;
   private $condensedView;
   private $seenUsers;

  	const CACHE_EXP_TIME = 1800;
	const THUMB_WIDTH = 96;
   const THUMB_HEIGHT = 96;

	public static $NAMESPACE_OPTIONS_NAME = array(  // list reordered (moved Place higher) Sep 2020 by Janet Bjorndahl
		'All' => 'All',
		'Person' => 'Person',
		'Family' => 'Family',
		'Portal' => 'Portal',
		'Article' => 'Article',
		'Image' => 'Image',
		'Place' => 'Place',
		'MySource' => 'MySource',
		'Source' => 'Source',
    'Transcript' => 'Transcript',
		'Repository' => 'Repository',
		'User' => 'User',
		'Category' => 'Category',
		'Surname' => 'Surname',
		'Givenname' => 'Givenname',
		'Help' => 'Help',
		'WeRelate' => 'WeRelate',
		'Template' => 'Template',
		'MediaWiki' => 'MediaWiki'
	);

  // This list is not used in this file, but was moved here to make it easier to keep in sync with the one above
  // Note that SpecialTrees also has a list with fewer options that should be kept in sync
  // The order of these lists should be kept in sync with LocalSettings $wgSortedNamespaces
	public static $NAMESPACE_OPTIONS_ID = array(    // list reordered Sep 2020 by Janet Bjorndahl
		'All' => '',
    'Person' => NS_PERSON,
		'Family' => NS_FAMILY,
		'Portal' => NS_PORTAL,
		'Article' => '0',
		'Image' => NS_IMAGE,
		'Place' => NS_PLACE,
		'MySource' => NS_MYSOURCE,
    'Source' => NS_SOURCE,
    'Transcript' => NS_TRANSCRIPT,
		'Repository' => NS_REPOSITORY,
		'User' => NS_USER,
		'Category' => NS_CATEGORY,
		'Surname' => NS_SURNAME,
		'Givenname' => NS_GIVEN_NAME,
		'Help' => NS_HELP,
		'WeRelate' => NS_PROJECT,
		'Template' => NS_TEMPLATE,
		'MediaWiki' => NS_MEDIAWIKI
	);

   public static $WATCH_OPTIONS = array(
      'Watched and unwatched' => 'wu',
      'Watched only' => 'w',
      'Unwatched only' => 'u',
//      'Watched, need sources' => 'ws'  // TODO
   );

   public static $SORT_OPTIONS = array(
      'Relevance' => 'score',
      'Page title' => 'title',
      'Date last modified' => 'date'
   );

	public static $DATE_RANGE_OPTIONS = array(
		' ' => '0',
		'+/- 1 yr' => '1',
		'+/- 2 yrs' => '2',
		'+/- 5 yrs' => '5'
	);

   public static $ROWS_OPTIONS = array('10','20','50','100','200');

   public static $ECP_OPTIONS = array(
      'Exact match only' => 'e',
      'Exact & close match' => 'c',
      'Exact, close, & partial' => 'p'
   );

	public static $TITLE_LETTERS = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','WXYZ','other');

	public function __construct() {
   	$this->seenUsers = array();
	}

	public static function getCacheKey() {
		global $wgUser;
		return 'searchrc:'.$wgUser->getName();
	}

   private static function removeSelf($selfTitle, $familyTitle) {
      list ($selfGiven, $selfSurname) = StructuredData::parsePersonTitle($selfTitle);
      $titles = explode(', ', $familyTitle);
      $results = array();
      foreach ($titles as $t) {
         list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($t);
         if (preg_replace('/<\/?b>/','',$hg) == $selfGiven && preg_replace('/<\/?b>/','',$hs) == $selfSurname) {
            $results[] = "$wg $ws";
         }
         else if (preg_replace('/<\/?b>/','',$wg) == $selfGiven && preg_replace('/<\/?b>/','',$ws) == $selfSurname) {
            $results[] = "$hg $hs";
         }
         else {
            $results[] = $t;
         }
      }
      return join(', ', $results);
   }

   private static function removeId($titleString) {
      $titles = explode(', ', $titleString);
      $results = array();
      foreach ($titles as $t) {
         $results[] = trim(preg_replace('/\(\d+\)$/', '', $t));
      }
      return join(', ', $results);
   }

   private function setPersonMatchVars($gedcomData) {
   	if ($gedcomData) {
   		$xml = GedcomUtil::getGedcomXml($gedcomData, $this->pagetitle);
   	}
   	else {
	  		$p = new Person($this->pagetitle);
   	   $xml = $p->getPageXml();
   	}

 		if (isset($xml)) {
			// add match string
			$this->givenname = (string)@$xml->name['given'];
			$this->surname   = (string)@$xml->name['surname'];
			foreach ($xml->event_fact as $ef) {
				$type = (string)$ef['type'];
				if ($type == Person::$BIRTH_TAG || $type == Person::$CHR_TAG) {
					$date = (string)@$ef['date'];
					if ($date && (!$this->birthdate || $type == Person::$BIRTH_TAG)) {
						$this->birthdate = $date;
					}
					$place = (string)@$ef['place'];
					if ($place && (!$this->birthplace || $type == Person::$BIRTH_TAG)) {
						$pos = strpos($place, '|'); if ($pos !== false) $place = substr($place, 0, $pos);
						$this->birthplace = $place;
					}
				}
				else if ($type == Person::$DEATH_TAG || $type == Person::$BUR_TAG) {
					$date = (string)@$ef['date'];
					if ($date && (!$this->deathdate || $type == Person::$DEATH_TAG)) {
						$this->deathdate = $date;
					}
					$place = (string)@$ef['place'];
					if ($place && (!$this->deathplace || $type == Person::$DEATH_TAG)) {
						$pos = strpos($place, '|'); if ($pos !== false) $place = substr($place, 0, $pos);
						$this->deathplace = $place;
					}
				}
			}
			foreach ($xml->child_of_family as $f) {
				list($fg, $fs, $mg, $ms) = StructuredData::parseFamilyTitle((string)$f['title']);
				$this->fatherGivenname .= ($this->fatherGivenname ? ' ' : '') . $fg;
				$this->fatherSurname .= ($this->fatherSurname ? ' ' : '') . $fs;
				$this->motherGivenname .= ($this->motherGivenname ? ' ' : '') . $mg;
				$this->motherSurname .= ($this->motherSurname ? ' ' : '') . $ms;
			}
			$gender = (string)$xml->gender;
			foreach ($xml->spouse_of_family as $f) {
				list($fg, $fs, $mg, $ms) = StructuredData::parseFamilyTitle((string)$f['title']);
				if ($gender == 'M') {
					$this->spouseGivenname .= ($this->spouseGivenname ? ' ' : '') . $mg;
					$this->spouseSurname .= ($this->spouseSurname ? ' ' : '') . $ms;
				}
				else if ($gender == 'F') {
					$this->spouseGivenname .= ($this->spouseGivenname ? ' ' : '') . $fg;
					$this->spouseSurname .= ($this->spouseSurname ? ' ' : '') . $fs;
				}
			}
    	}
   }
	
   private function setFamilyMatchVars($gedcomData) {
   	if ($gedcomData) {
   		$xml = GedcomUtil::getGedcomXml($gedcomData, $this->pagetitle);
   	}
   	else {
	  		$f = new Family($this->pagetitle);
	      $xml = $f->getPageXml();
   	}
 		if (isset($xml)) {
			// add match string
			foreach ($xml->husband as $h) {
				$this->husbandGivenname .= ($this->husbandGivenname ? ' ' : '') . (string)@$h['given'];
				$this->husbandSurname .= ($this->husbandSurname ? ' ' : '') . (string)@$h['surname'];
			}
			foreach ($xml->wife as $w) {
				$this->wifeGivenname .= ($this->wifeGivenname ? ' ' : '') . (string)@$w['given'];
				$this->wifeSurname .= ($this->wifeSurname ? ' ' : '') . (string)@$w['surname'];
			}
			foreach ($xml->event_fact as $ef) {
				$type = (string)$ef['type'];
				if ($type == Family::$MARRIAGE_TAG) {
					$this->marriagedate = (string)@$ef['date'];
					$this->marriageplace = (string)@$ef['place'];
					$pos = strpos($this->marriageplace, '|'); if ($pos !== false) $this->marriageplace = substr($this->marriageplace, 0, $pos);
					break;
				}
			}
    	}
    	if (!$this->husbandGivenname || !$this->husbandSurname || !$this->wifeGivenname || !$this->wifeSurname) {
			$t = Title::makeTitleSafe(NS_FAMILY, $this->pagetitle);
			if ($t) {
	    		list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($t->getText());
	    		if (!$this->husbandGivenname) $this->husbandGivenname = $hg;
	    		if (!$this->husbandSurname) $this->husbandSurname = $hs;
	    		if (!$this->wifeGivenname) $this->wifeGivenname = $wg;
	    		if (!$this->wifeSurname) $this->wifeSurname = $ws;
			}
    	}
   }
	
   public function readQueryParms($par) {
      global $wgRequest, $wgUser;

		$this->target = $wgRequest->getVal('target');
		$this->match = $wgRequest->getVal('match');
		$this->pagetitle = $wgRequest->getVal('pagetitle');
		$gedcomData = null;
		if ($this->pagetitle && GedcomUtil::isGedcomTitle($this->pagetitle)) {
			$gedcomDataString = GedcomUtil::getGedcomDataString();
   		$gedcomData = GedcomUtil::getGedcomDataMap($gedcomDataString);
		}
      $this->ecp = $wgRequest->getVal('ecp');
      if (!$this->ecp) {
          if ($this->target || $this->match) {
             $this->ecp = 'p'; // partial
          }
         else {
            $this->ecp = 'c';
         }
      }
      $this->talk = $wgRequest->getCheck('talk');
    	$this->sort = $wgRequest->getVal('sort');
      $this->titleLetter = $wgRequest->getVal('tl');
      $this->namespace = $wgRequest->getVal('ns');
      if (!$this->namespace && $par) $this->namespace = $par;
      if ($this->namespace == 'All') $this->namespace = '';
      $this->rows = $wgRequest->getVal('rows');
      if ($this->rows < 10 || $this->rows > 200) {
         $this->rows = 20;
      }
      $this->condensedView = $wgRequest->getCheck('cv');
      $ns = $this->namespace;
      $this->watch = $wgRequest->getVal('watch');
      if (!$this->watch) {
      	$this->watch = "wu";
      }
      if ($ns == '' || $ns == 'Image' || $ns == 'Person') {
			$this->givenname = trim($wgRequest->getVal('g'));
      }
      if ($ns == '' || $ns == 'Image' || $ns == 'Person' || $ns == 'Article' || $ns == 'Source' || $ns == 'MySource' || $ns == 'User') {
			$this->surname = trim($wgRequest->getVal('s'));
      }
      if ($ns == '' || $ns == 'Image' || $ns == 'Article' || $ns == 'Source' || $ns == 'MySource' || $ns == 'User' || $ns == 'Repository') {
			$this->place = trim($wgRequest->getVal('p'));
      }
      if ($ns == 'Person') {
      	if ($this->pagetitle && $this->match == 'on') {
				$this->givenname = $this->surname = $this->birthdate = $this->birthplace = $this->deathdate = $this->deathplace = 
						$this->fatherGivenname = $this->fatherSurname = $this->motherGivenname = $this->motherSurname = $this->spouseGivenname = $this->spouseSurname =
		      		$this->birthrange = $this->deathrange = $this->personGender = $this->parentFamily = $this->spouseFamily =
                  $this->wifeGivenname = $this->wifeSurname = $this->birthType = $this->deathType = '';
      		$this->setPersonMatchVars($gedcomData);
      	}
      	else {
	      	$this->birthdate = trim($wgRequest->getVal('bd'));
	      	$this->birthrange = $wgRequest->getVal('br');
	      	if ($this->birthrange == '0') $this->birthrange = '';
	      	$this->birthplace = trim($wgRequest->getVal('bp'));
	      	$this->deathdate = trim($wgRequest->getVal('dd'));
	      	$this->deathrange = $wgRequest->getVal('dr');
	      	if ($this->deathrange == '0') $this->deathrange = '';
	      	$this->deathplace = trim($wgRequest->getVal('dp'));
		      $this->fatherGivenname = trim($wgRequest->getVal('fg'));
		      $this->fatherSurname = trim($wgRequest->getVal('fs'));
		      $this->motherGivenname = trim($wgRequest->getVal('mg'));
		      $this->motherSurname = trim($wgRequest->getVal('ms'));
		      $this->spouseGivenname = trim($wgRequest->getVal('sg'));
		      $this->spouseSurname = trim($wgRequest->getVal('ss'));
		      $this->personGender = trim($wgRequest->getVal('gnd'));
		      $this->birthType = trim($wgRequest->getVal('bt'));
		      $this->deathType = trim($wgRequest->getVal('dt'));
            $this->parentFamily = trim($wgRequest->getVal('pf'));
            $this->spouseFamily = trim($wgRequest->getVal('sf'));
            $this->wifeGivenname = trim($wgRequest->getVal('wg')); // if we're adding a father, remember the mother's name
            $this->wifeSurname = trim($wgRequest->getVal('ws'));
				$this->personSurnameFacet = $wgRequest->getVal('psf');
				$this->personGivennameFacet = $wgRequest->getVal('pgf');
				$this->personCountryFacet = $wgRequest->getVal('pcof');
				$this->personStateFacet = $wgRequest->getVal('pstf');
				$this->personCenturyFacet = $wgRequest->getVal('pcf');
				$this->personDecadeFacet = $wgRequest->getVal('pdf');
      	}
      }
      if ($ns == 'Family') {
      	if ($this->pagetitle && $this->match == 'on') {
				$this->husbandGivenname = $this->husbandSurname = $this->wifeGivenname = $this->wifeSurname = $this->personGender =
                  $this->marriagedate = $this->marriageplace = $this->marriagerange = $this->childTitle = $this->husbandTitle = $this->wifeTitle = '';
      		$this->setFamilyMatchVars($gedcomData);
      	}
      	else {
		      $this->husbandGivenname = trim($wgRequest->getVal('hg'));
		      $this->husbandSurname = trim($wgRequest->getVal('hs'));
		      $this->wifeGivenname = trim($wgRequest->getVal('wg'));
		      $this->wifeSurname = trim($wgRequest->getVal('ws'));
            $this->personGender = trim($wgRequest->getVal('gnd')); // used in javascript add person page prompt to determine gender of spouse
		      $this->marriagedate = trim($wgRequest->getVal('md'));
		      $this->marriagerange = $wgRequest->getVal('mr');
		      if ($this->marriagerange == '0') $this->marriagerange = '';
            $this->marriageplace = trim($wgRequest->getVal('mp'));
            $this->childTitle = trim($wgRequest->getVal('ct'));
            $this->husbandTitle = trim($wgRequest->getVal('ht'));
            $this->wifeTitle = trim($wgRequest->getVal('wt'));
      	}
      }
      if ($ns == 'Place') {
	      $pn = $wgRequest->getVal('pn');
   	   $li = $wgRequest->getVal('li');
   	   $pos = mb_strpos($li, '|');
   	   if ($pos !== false) {
   	   	$li = mb_substr($li, 0, $pos);
			}
			$pn = preg_replace('/(^[, ]+)|([, ]+$)/', '', $pn);
			$li = preg_replace('/(^[, ]+)|([, ]+$)/', '', $li);
			$place = $pn . ($pn && $li ? ', ' : '') . $li;
			$pos = mb_strpos($place, ',');
			if ($pos !== false) {
				$this->placename = trim(mb_substr($place, 0, $pos));
				$this->locatedinplace = trim(mb_substr($place, $pos+1));
			}
			else {
				$this->placename = $place;
				$this->locatedinplace = '';
			}
      }
      if ($ns == 'Source') {
      	$this->sourceType = trim($wgRequest->getVal('sty'));
      	$this->sourceTitle = trim($wgRequest->getVal('st'));
      	$this->placeIssued = trim($wgRequest->getVal('pi'));
      	$this->publisher = trim($wgRequest->getVal('pu'));
      	$this->sourceSubject = $wgRequest->getVal('su');
      	$this->sourceAvailability = $wgRequest->getVal('sa');
         $this->sub = $wgRequest->getCheck('sub');
         $this->sup = $wgRequest->getCheck('sup');
      }
      if ($ns == 'Source' || $ns == 'MySource') {
      	$this->author = trim($wgRequest->getVal('a'));
      }
      if ($ns != '' && $ns != 'Person' && $ns != 'Family' && $ns != 'Place') {
      	$this->title = trim($wgRequest->getVal('t'));
      }
      if ($this->target == 'AddPage' && $ns == 'MySource' &&
          $this->title && mb_strpos($this->title, $wgUser->getName().'/') !== 0) {
            $this->title = $wgUser->getName().'/'.$this->title;
      }
      $this->keywords = trim($wgRequest->getVal('k'));
		$this->go = $wgRequest->getVal('go');
      $this->start = $wgRequest->getVal('start');
   }
   
   public function getRedirTitle() {
   	if (!$this->go || strpos($this->keywords, ':') === false) {
   		return '';
   	}
   	if (stripos($this->keywords, 'Article:') === 0) {
   		$titleString = substr($this->keywords, 8);
		}
		else {
			$titleString = $this->keywords;
		}
   	
   	$t = Title::newFromText($titleString);
   	if ($t) {
   		if ($t->exists()) {
   			return $t;
   		}
   		$this->namespace = $t->getSubjectNsText();
   		$this->keywords = $t->getText();
   	}
   	return '';
   }

	private function addQuotes($value) {
      $value = trim(str_replace('"', '', $value));
		if (mb_strpos($value, ' ') !== false) {
			$value = '"'.$value.'"';
		}
		return $value;
	}
	
	private function getRange($date, $range) {
		if ($range) {
			$year = substr(StructuredData::getDateKey($date), 0, 4);
			if ($year && $range <= 5) {
				return '['.($year - $range) . ' TO ' . ($year + $range) . ']';
			}
		}
		return $this->addQuotes($date);
	}
	
	private function repeatFieldName($value, $required, $fieldName='', $removeSpecialChars=false, $allowWildcards=false) {
		if ($removeSpecialChars) {
         if ($allowWildcards) {
			   $value = preg_replace('$[^A-Za-z0-9"*?]$', ' ', $value);
         }
         else {
            $value = preg_replace('$[^A-Za-z0-9"]$', ' ', $value);
         }
		}
		$words = explode(' ', $value);
		$result = '';
		$inQuotes = false;
		foreach ($words as $word) {
			if (!$word) { // skip empty words
				continue;
			}
			if ($inQuotes) {
				$pos = strpos($word, '"');
				if ($pos !== false) {
					$inQuotes = false;
				}
				$result .= ' '.$word;
			}
			else {
				$result .= ' ';
				$firstChar = substr($word, 0, 1);
				if ($firstChar == '-' || $firstChar == '+') {
					$word = substr($word, 1);
				}
				if ($firstChar == '-') {
					$result .= '-';
				}
				else if ($required || $firstChar == '+') {
					$result .= '+';
				}
				if ($fieldName) {
					$result .= $fieldName.':';
				}
				$result .= $word;
				$pos = strpos($word, '"');
				if ($pos !== false && strpos($word, '"', $pos+1) === false) { // not a closing "
					$inQuotes = true;
				}
			}
		}
      if ($inQuotes) {
         $result .= '"';
      }
		return $result;
	}

   private function wildcardWithoutEnoughLetters($s) {
      $nw = str_replace(array("*", "?"),"", $s, $cnt);
      return $cnt > 0 && mb_strlen($nw) < 3;
   }

   public function validateQuery() {
      $errMsg = array();
      $words = explode(' ',$this->title);
      foreach ($words as $word) {
         if ($this->wildcardWithoutEnoughLetters($word)) {
            $errMsg[] = "Need at least three non-wildcard letters in Title";
         }
      }
      $words = explode(' ',$this->sourceTitle);
      foreach ($words as $word) {
         if ($this->wildcardWithoutEnoughLetters($word)) {
            $errMsg[] = "Need at least three non-wildcard letters in Title";
         }
      }
      $words = explode(' ',$this->author);
      foreach ($words as $word) {
         if ($this->wildcardWithoutEnoughLetters($word)) {
            $errMsg[] = "Need at least three non-wildcard letters in Author";
         }
      }
      if (mb_strpos($this->keywords, '?') !== false || mb_strpos($this->keywords, '*') !== false) {
         $errMsg[] = "Cannot use wildcards in the Keywords field";
      }
      if ($this->wildcardWithoutEnoughLetters($this->givenname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Givenname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->surname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Surname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->fatherGivenname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Father Givenname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->fatherSurname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Father Surname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->motherGivenname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Mother Givenname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->motherSurname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Mother Surname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->spouseGivenname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Spouse Givenname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->spouseSurname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Spouse Surname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->husbandGivenname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Husband Givenname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->husbandSurname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Husband Surname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->wifeGivenname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Wife Givenname";
      }
      if ($this->wildcardWithoutEnoughLetters($this->wifeSurname)) {
         $errMsg[] = "Need at least three non-wildcard letters in Wife Surname";
      }
      return join(' ', $errMsg);
   }

	/**
	 * Construct query to send to search server
	 * @return string query
	 */
	public function getSearchServerQuery($par) {
		global $wrSearchHost, $wrSearchPort, $wrSearchPath, $wgUser;

		$mod = ($this->ecp != 'p' ? ' +' : ' ');
		$query = '';
      if ($this->surname) {
         $query .= $mod . ($this->namespace == 'Person' ? 'PersonSurname:' : 'Surname:') . $this->addQuotes($this->surname);
      }
      if ($this->givenname) {
         $query .= $mod . ($this->namespace == 'Person' ? 'PersonGivenname:' : 'Givenname:') . $this->addQuotes($this->givenname);
      }
		if ($this->place) {
			$query .= $mod . 'Place:' . $this->addQuotes($this->place);
		}
		if ($this->birthdate) {
			$query .= $mod . 'PersonBirthDate:' . $this->getRange($this->birthdate, $this->birthrange);
		}
		if ($this->birthplace) {
			$query .= $mod . 'PersonBirthPlace:' . $this->addQuotes($this->birthplace);
		}
		if ($this->deathdate) {
			$query .= $mod . 'PersonDeathDate:' . $this->getRange($this->deathdate, $this->deathrange);
		}
		if ($this->deathplace) {
			$query .= $mod . 'PersonDeathPlace:' . $this->addQuotes($this->deathplace);
		}
		if ($this->fatherGivenname) {
			$query .= $mod . 'FatherGivenname:' . $this->addQuotes($this->fatherGivenname);
		}
		if ($this->fatherSurname) {
			$query .= $mod . 'FatherSurname:' . $this->addQuotes($this->fatherSurname);
		}
		if ($this->motherGivenname) {
			$query .= $mod . 'MotherGivenname:' . $this->addQuotes($this->motherGivenname);
		}
		if ($this->motherSurname) {
			$query .= $mod . 'MotherSurname:' . $this->addQuotes($this->motherSurname);
		}
		if ($this->spouseGivenname) {
			$query .= $mod . 'SpouseGivenname:' . $this->addQuotes($this->spouseGivenname);
		}
		if ($this->spouseSurname) {
			$query .= $mod . 'SpouseSurname:' . $this->addQuotes($this->spouseSurname);
		}
		if ($this->husbandGivenname) {
			$query .= $mod . 'HusbandGivenname:' . $this->addQuotes($this->husbandGivenname);
		}
		if ($this->husbandSurname) {
			$query .= $mod . 'HusbandSurname:' . $this->addQuotes($this->husbandSurname);
		}
      // we remember the wife's name when we're adding a father, but we don't want to search on it
		if ($this->wifeGivenname && $this->namespace != 'Person') {
			$query .= $mod . 'WifeGivenname:' . $this->addQuotes($this->wifeGivenname);
		}
		if ($this->wifeSurname && $this->namespace != 'Person') {
			$query .= $mod . 'WifeSurname:' . $this->addQuotes($this->wifeSurname);
		}
		if ($this->marriagedate) {
			$query .= $mod . 'MarriageDate:' . $this->getRange($this->marriagedate, $this->marriagerange);
		}
		if ($this->marriageplace) {
			$query .= $mod . 'MarriagePlace:' . $this->addQuotes($this->marriageplace);
		}
		if ($this->placename) {
			$query .= $mod . 'PlaceName:' . $this->addQuotes($this->placename);
		}
		if ($this->locatedinplace) {
			$query .= ' +LocatedInPlace:' . $this->addQuotes($this->locatedinplace); // force required
		}
		if ($this->author) {
			$query .= $this->repeatFieldName($this->author, $this->ecp != 'p', 'Author', true, true);
		}
		if ($this->sourceTitle) {
			$query .= $this->repeatFieldName($this->sourceTitle, $this->ecp != 'p', 'Title', true, true); // search regular title
		}
		if ($this->title) {
			$query .= $this->repeatFieldName($this->title, $this->ecp != 'p', 'Title', true, true);
		}
      if ($this->keywords) {
         $query .= $this->repeatFieldName($this->keywords, $this->ecp != 'p');
      }
      $filters = '';
      if ($wgUser->isLoggedIn() && $this->watch != "wu") {
         if ($this->watch == 'w' || $this->watch == 'ws') {
            $watchQ = "+User:" . $this->addQuotes($wgUser->getName());
            if ($this->watch == 'ws') {
               $watchQ .= " +Unsourced:T";
            }
         }
         else { // unwatched
            $watchQ = "-User:" . $this->addQuotes($wgUser->getName());
         }
      	if ($query) {
      		$filters .= '&fq=' . urlencode($watchQ);
      	}
      	else {
      		$query = $watchQ;
      	}
      }
      if ($this->namespace) {
      	$nsQ = 'Namespace:' . $this->namespace;
      	if ($query) {
      		$filters .= '&fq=' . urlencode($nsQ);
      	}
			// don't issue a query based only upon the passed-in parm (i.e., if parm = 108, then don't issue a query)
      	else if (!$par) {
      		$query = $nsQ;
      	}
      }
      if (!$this->talk) {
         // TODO
         //$filters .= '&fq=' . urlencode('-TalkNamespace:T');
      }
		if ($this->sourceSubject) {
			$filters .= '&fq=' . urlencode('SourceSubject:' . $this->addQuotes($this->sourceSubject));
		}
		if ($this->sourceAvailability) {
			$filters .= '&fq=' . urlencode('SourceAvailability:' . $this->addQuotes($this->sourceAvailability));
		}
		if ($this->personSurnameFacet) {
			$filters .= '&fq=' . urlencode('PersonSurnameFacet:' . $this->addQuotes($this->personSurnameFacet));
		}
		if ($this->personGivennameFacet) {
			$filters .= '&fq=' . urlencode('PersonGivennameFacet:' . $this->addQuotes($this->personGivennameFacet));
		}
		if ($this->personCountryFacet) {
			$filters .= '&fq=' . urlencode('PersonCountryFacet:' . $this->addQuotes($this->personCountryFacet));
		}
		if ($this->personStateFacet) {
			$filters .= '&fq=' . urlencode('PersonStateFacet:' . $this->addQuotes($this->personStateFacet));
		}
		if ($this->personCenturyFacet) {
			$filters .= '&fq=' . urlencode('PersonCenturyFacet:' . $this->addQuotes($this->personCenturyFacet));
		}
		if ($this->personDecadeFacet) {
			$filters .= '&fq=' . urlencode('PersonDecadeFacet:' . $this->addQuotes($this->personDecadeFacet));
		}

		if ($query) {
			if ($this->sort == 'title') {
            $sortSpec = '&sort=TitleSortValue+asc';
         }
         else if ($this->sort == 'date') {
            $sortSpec = '&sort=LastModDate+desc';
         }
         else {
				$sortSpec = '';
			}
			
			if ($this->sort == 'title' && $this->titleLetter) {
				$filters .= '&fq=' . urlencode('TitleFirstLetter:'.$this->titleLetter);
			}
			$facets = ($this->namespace ? '' : '&facet.field=Namespace') .
						 ($wgUser->isLoggedIn() && $this->watch == "wu" ? '&facet.query='.urlencode('User:'.$this->addQuotes($wgUser->getName())) : '') .
						 ($this->sort == 'title' && !$this->titleLetter ? '&facet.field=TitleFirstLetter' : '') .
						 ($this->namespace == 'Person' && !$this->personSurnameFacet   ? '&facet.field=PersonSurnameFacet&f.PersonSurnameFacet.facet.limit=10' : '') .
						 ($this->namespace == 'Person' && !$this->personGivennameFacet ? '&facet.field=PersonGivennameFacet&f.PersonGivennameFacet.facet.limit=10' : '') .
						 ($this->namespace == 'Person' && !$this->personCountryFacet   ? '&facet.field=PersonCountryFacet&f.PersonCountryFacet.facet.limit=10' : '') .
						 ($this->namespace == 'Person' && $this->personCountryFacet && $this->personCountryFacet != 'Unknown' && !$this->personStateFacet
						 	? ('&facet.field=PersonStateFacet&f.PersonStateFacet.facet.limit=10&f.PersonStateFacet.facet.prefix='.urlencode($this->personCountryFacet)) : '') .
						 ($this->namespace == 'Person' && !$this->personCenturyFacet   ? '&facet.field=PersonCenturyFacet' : '') .
						 ($this->namespace == 'Person' && $this->personCenturyFacet && $this->personCenturyFacet != 'pre1600' && $this->personCenturyFacet != 'Unknown' && !$this->personDecadeFacet
						 	? ('&facet.field=PersonDecadeFacet&f.PersonDecadeFacet.facet.prefix='.urlencode(substr($this->personCenturyFacet, 0, 2))) : '') .
						 ($this->namespace == 'Source' && !$this->sourceSubject        ? '&facet.field=SourceSubject' : '') .
						 ($this->namespace == 'Source' && !$this->sourceAvailability   ? '&facet.field=SourceAvailability' : '');
			if ($facets) {
				$facets .= '&facet=true';
			}
			$query = "http://$wrSearchHost:$wrSearchPort$wrSearchPath/search?q=" . urlencode(trim($query)) .
		   			$filters . $facets . $sortSpec . '&start=' . $this->start . '&rows=' . $this->rows .
                  ($this->ecp == 'e' ? '&exact=true' : '') .
                  ($this->sub ? '&sub=true' : '') .
                  ($this->sup ? '&sup=true' : '');
		}
      return $query;
	}
	
	private function getSelfQuery() {
		$result = '/wiki/Special:Search?ns=' . urlencode($this->namespace)
         .($this->target ? '&target=' . urlencode($this->target) : '')
			.($this->match ? '&match=true' : '')
			.($this->pagetitle ? '&pagetitle=' . urlencode($this->pagetitle) : '')
			.($this->ecp ? '&ecp=' . urlencode($this->ecp) : '')
			.(($this->sort == 'title' || $this->sort == 'date') ? '&sort=' . urlencode($this->sort) : '')
         .($this->rows ? '&rows=' . urlencode($this->rows) : '')
         .($this->condensedView ? '&cv=true' : '')
         .($this->watch ? '&watch=' . urlencode($this->watch) : '')
         .($this->talk ? '&talk=true' : '')
         .($this->givenname ? '&g=' . urlencode($this->givenname) : '')
         .($this->surname ? '&s=' . urlencode($this->surname) : '')
         .($this->place ? '&p=' . urlencode($this->place) : '')
         .($this->birthdate ? '&bd=' . urlencode($this->birthdate) : '')
         .($this->birthrange ? '&br=' . urlencode($this->birthrange) : '')
         .($this->birthplace ? '&bp=' . urlencode($this->birthplace) : '')
         .($this->deathdate ? '&dd=' . urlencode($this->deathdate) : '')
         .($this->deathrange ? '&dr=' . urlencode($this->deathrange) : '')
         .($this->deathplace ? '&dp=' . urlencode($this->deathplace) : '')
         .($this->fatherGivenname ? '&fg=' . urlencode($this->fatherGivenname) : '')
         .($this->fatherSurname ? '&fs=' . urlencode($this->fatherSurname) : '')
         .($this->motherGivenname ? '&mg=' . urlencode($this->motherGivenname) : '')
         .($this->motherSurname ? '&ms=' . urlencode($this->motherSurname) : '')
         .($this->spouseGivenname ? '&sg=' . urlencode($this->spouseGivenname) : '')
         .($this->spouseSurname ? '&ss=' . urlencode($this->spouseSurname) : '')
         .($this->husbandGivenname ? '&hg=' . urlencode($this->husbandGivenname) : '')
         .($this->husbandSurname ? '&hs=' . urlencode($this->husbandSurname) : '')
         .($this->wifeGivenname ? '&wg=' . urlencode($this->wifeGivenname) : '')
         .($this->wifeSurname ? '&ws=' . urlencode($this->wifeSurname) : '')
         .($this->marriagedate ? '&md=' . urlencode($this->marriagedate) : '')
         .($this->marriagerange ? '&mr=' . urlencode($this->marriagerange) : '')
         .($this->marriageplace ? '&mp=' . urlencode($this->marriageplace) : '')
         .($this->childTitle ? '&ct=' . urlencode($this->childTitle) : '')
         .($this->husbandTitle ? '&ht=' . urlencode($this->husbandTitle) : '')
         .($this->wifeTitle ? '&wt=' . urlencode($this->wifeTitle) : '')
         .($this->parentFamily ? '&pf=' . urlencode($this->parentFamily) : '')
         .($this->spouseFamily ? '&sf=' . urlencode($this->spouseFamily) : '')
         .($this->placename ? '&pn=' . urlencode($this->placename) : '')
         .($this->locatedinplace ? '&li=' . urlencode($this->locatedinplace) : '')
         .($this->personGender ? '&gnd=' . urlencode($this->personGender) : '')
         .($this->birthType ? '&bt=' . urlencode($this->birthType) : '')
         .($this->deathType ? '&dt=' . urlencode($this->deathType) : '')
         .($this->sub ? '&sub=true' : '')
         .($this->sup ? '&sup=true' : '')
         .($this->sourceSubject ? '&su=' . urlencode($this->sourceSubject) : '')
         .($this->sourceAvailability ? '&sa=' . urlencode($this->sourceAvailability) : '')
         .($this->personSurnameFacet ? '&psf=' . urlencode($this->personSurnameFacet) : '')
         .($this->personGivennameFacet ? '&pgf=' . urlencode($this->personGivennameFacet) : '')
         .($this->personCountryFacet ? '&pcof=' . urlencode($this->personCountryFacet) : '')
         .($this->personStateFacet ? '&pstf=' . urlencode($this->personStateFacet) : '')
         .($this->personCenturyFacet ? '&pcf=' . urlencode($this->personCenturyFacet) : '')
         .($this->personDecadeFacet ? '&pdf=' . urlencode($this->personDecadeFacet) : '')
         .($this->author ? '&a=' . urlencode($this->author) : '')
         .($this->sourceType ? '&sty=' . urlencode($this->sourceType) : '')
         .($this->sourceTitle ? '&st=' . urlencode($this->sourceTitle) : '')
         .($this->placeIssued ? '&pi=' . urlencode($this->placeIssued) : '')
         .($this->publisher ? '&pu=' . urlencode($this->publisher) : '')
         .($this->title ? '&t=' . urlencode($this->title) : '')
			.($this->sort == 'title' && $this->titleLetter ? '&tl=' . urlencode($this->titleLetter)  : '')
         .($this->keywords ? '&k=' . urlencode($this->keywords) : ''); // must be last
      return $result;
	}

   private function prepareValue($v, $max = 90) {
		if (mb_strlen($v) > $max) {
			$pos = mb_strpos($v, '</b>');
			if ($pos !== false && $pos+4 > $max) {
				$v = '...' . mb_substr($v, mb_strlen($v)-$max+3);
			}
			else {
				$v = mb_substr($v, 0, $max-3) . '...';
			}
		}
		$v = str_replace(array('&lt;b&gt;','&lt;/b&gt;'), array('<b>','</b>'), htmlspecialchars($v));
		return $v;
	}
	
	private function formatValue($label, $value, $twoCol=false, $prepare=true) {
      if (!$value) return '';

		if ($prepare) {
			$value = $this->prepareValue($value);
		}
		if ($value) {
			return '<tr><td '. ($twoCol ?  'colspan=2>' : 'align=right width="75px">' . ($label ? "<span class=\"searchresultlabel\">$label:</span>" : '') . '</td><td>') . $value . '</td></tr>';
		}
		else {
			return '';
		}
	}
	
	private function getStarsImage($score) {
		global $wgStylePath;
		
		$n = (int)($score * 2);
		if ($n < 0) {
			$n = 0;
		}
		else if ($n > 10) {
			$n = 10;
		}
		return "<img src=\"$wgStylePath/common/images/stars/stars$n.png\"/>";
	}
	
	private function makeUserLinks($userText, $max = 90) {
		global $wgUser;
		
		if (!$userText) {
			return '';
		}

		$result = '';
   	$skin =& $wgUser->getSkin();
		$users = explode(';',$userText);
		$cnt = 0;
		foreach ($users as $user) {
			$user = trim($user);
			$isBold = (strpos($user, '<b>') === 0 && strpos($user, '</b>') == strlen($user) - 4);
			$user = str_replace(array('<b>','</b>'), '', $user);
			$cnt += mb_strlen($user) + 2;
			if ($cnt > $max) {
				$result .= '...';
				break;
			}
			if ($result) {
				$result .= ", ";
			}
			$t = Title::makeTitle(NS_USER, $user);
			if ($t) {
				$userPageExists = @$this->seenUsers[$user];
				if (!isset($userPageExists)) {
					$userPageExists = $t->exists();
					$this->seenUsers[$user] = $userPageExists;
				}
				$user = ($isBold ? '<b>' : '') . htmlspecialchars($user) . ($isBold ? '</b>' : '');
	   		$result .= ($userPageExists ? $skin->makeKnownLinkObj($t, $user) : $skin->makeBrokenLinkObj($t, $user));
			}
			else {
				$result .= $user;
			}
		}
		return $result;
	}

   private function addSelectButtons() {
      return $this->target &&
        ($this->target != 'AddPage' || $this->parentFamily || $this->spouseFamily || $this->childTitle || $this->husbandTitle || $this->wifeTitle);
   }
	
	private function getSelectButton($nsText, $titleString) {
		$titleString = rawurlencode($titleString);
		return "<input type=\"submit\" value=\"Select\" onClick=\"selectPage('{$this->target}','$nsText','$titleString')\"/> ";
	}
	
	private function getMatchCheckbox($titleString, $pos) {
		return '<input type="checkbox" name="compare_'.$pos.'" value="'.htmlspecialchars($titleString).'"/> ';
	}

   private function getEventInfo($hl, $label) {
      $date = @$hl[$label.'DateStored'];
      if (is_array($date)) $date = $date[0];
      $place = @$hl[$label.'PlaceStored'];
      if (is_array($place)) $place = $place[0];
      return $date.($date && $place ? ', ' : '').$place;
   }

   private function formatResult($doc, $hl, $pos, $condensed) {
      global $wgUser;

      $skin =& $wgUser->getSkin();
      $ns = @$doc['NamespaceStored'];
      $titleString = $doc['TitleStored'];
      $title = Title::newFromText($ns.':'.$titleString);
      if ($this->pagetitle && $ns == $this->namespace && $title->getDBkey() == $this->pagetitle) {
         return '';
      }
      if (!$condensed) {
         $birth = $chr = $death = $burial = $marriage = $banns = '';
         $husbandBirth = $husbandDeath = $wifeBirth = $wifeDeath = '';
         if ($ns == 'Person') {
            $birth = $this->getEventInfo($hl, 'PersonBirth');
            $chr = $this->getEventInfo($hl, 'PersonChr');
            $death = $this->getEventInfo($hl, 'PersonDeath');
            $burial = $this->getEventInfo($hl, 'PersonBurial');
         }
         else if ($ns == 'Family') {
            $marriage = $this->getEventInfo($hl, 'Marriage');
            $banns = $this->getEventInfo($hl, 'Banns');
            $husbandBirth = $this->getEventInfo($doc, 'HusbandBirth');
            if (!$husbandBirth) {
               $husbandBirth = $this->getEventInfo($doc, 'HusbandChr');
            }
            $husbandDeath = $this->getEventInfo($doc, 'HusbandDeath');
            if (!$husbandDeath) {
               $husbandDeath = $this->getEventInfo($doc, 'HusbandBurial');
            }
            $wifeBirth = $this->getEventInfo($doc, 'WifeBirth');
            if (!$wifeBirth) {
               $wifeBirth = $this->getEventInfo($doc, 'WifeChr');
            }
            $wifeDeath = $this->getEventInfo($doc, 'WifeDeath');
            if (!$wifeDeath) {
               $wifeDeath = $this->getEventInfo($doc, 'WifeBurial');
            }
         }

         $imageURL = '';
         if (@$doc['PrimaryImage']) {
            $t = Title::makeTitle(NS_IMAGE, $doc['PrimaryImage']);
            if ($t && $t->exists()) {
               $i = new Image($t);
               $imageURL = $i->createThumb(SearchForm::THUMB_WIDTH, SearchForm::THUMB_HEIGHT);
               $imagePageURL = $t->getLocalURL();
            }
         }
         else if ($doc['NamespaceStored'] == 'Image') {
            $t = Title::makeTitle(NS_IMAGE, $doc['TitleStored']);
            if ($t && $t->exists()) {
               $i = new Image($t);
               $imageURL = $i->createThumb(SearchForm::THUMB_WIDTH, SearchForm::THUMB_HEIGHT);
               $imagePageURL = $t->getLocalURL();
            }
         }
      }
      $displayTitle = '';
      if ($ns == 'Family') {
         if (@$hl['TitleStored']) {
            $displayTitle = SearchForm::removeId($hl['TitleStored'][0]);
         }
         else {
            $displayTitle = SearchForm::removeId($titleString);
         }
      }
      else if ($ns == 'Person') {
         if (@$hl['FullnameStored'][0] && mb_strpos($hl['FullnameStored'][0], ' ') !== false) {
            $displayTitle = $hl['FullnameStored'][0];
         }
         else if (@$hl['TitleStored']) {
            $displayTitle = SearchForm::removeId($hl['TitleStored'][0]);
         }
         else {
            $displayTitle = SearchForm::removeId($titleString);
         }
      }
      else if (@$hl['TitleStored']) {
         $displayTitle = $hl['TitleStored'][0];
      }
      else {
         $displayTitle = $titleString;
      }

      $titleText = $this->prepareValue(($ns ? $ns.':' : '').$displayTitle, 200);
      $result = '<tr><td class="searchresult" colspan=2>'
         . ($this->addSelectButtons() ? $this->getSelectButton($ns, $titleString) : '' )
         . ($this->match ? $this->getMatchCheckbox($titleString, $pos+1) : '')
         . '<span class="searchresulttitle">'
         . $skin->makeKnownLinkObj($title, $titleText, ($this->addSelectButtons() ? 'target='.urlencode($this->target) : ''))
         . '</span></td>'
         . ($pos >= 0 ? '' : '<td>Recent edit, not yet indexed</td>')
         . '</tr>';
      // $pos is -1 for recent contribs
      if ($pos >= 0 && !$condensed) {
         $result .= '<tr><td '.($imageURL ? 'width="55%"' : 'colspan=2 width="70%"').'><table width="100%">'
//			. $this->formatValue('Name', @$hl['FullnameStored'][0])
         . $this->formatValue('Birth', $birth)
         . (!$birth && $chr ? $this->formatValue('Chr/Bap', $chr) : '')
         . $this->formatValue('Death', $death)
         . (!$death && $burial ? $this->formatValue('Burial', $burial) : '')
         . $this->formatValue('Parents', SearchForm::removeId(@$hl['ParentFamilyTitle'][0]))
         . $this->formatValue('Spouse', SearchForm::removeSelf($titleString, SearchForm::removeId(@$hl['SpouseFamilyTitle'][0])))
         . ($husbandBirth || $husbandDeath ? $this->formatValue('Husband', "$husbandBirth - $husbandDeath") : '')
         . ($wifeBirth || $wifeDeath ? $this->formatValue('Wife', "$wifeBirth - $wifeDeath") : '')
         . $this->formatValue('Marriage', $marriage)
         . (!$marriage && $banns ? $this->formatValue('Banns', $banns) : '')
         . $this->formatValue('Children', SearchForm::removeId(@$hl['ChildTitle'][0]))
         . $this->formatValue('Author', @$hl['AuthorStored'][0])
         . $this->formatValue('Title', @$hl['SourceTitleStored'][0])
         . $this->formatValue('Surnames', @$hl['SurnameStored'][0])
         . $this->formatValue('Places', @$hl['PlaceStored'][0])
         . (@$hl['SourceSubjectStored'][0] ? $this->formatValue('Subject', @$hl['SourceSubjectStored'][0] . (@$doc['SourceSubSubject'] ? ' - ' . @$doc['SourceSubSubject'] : '')) : '')
         . (@$doc['FromYear'] || @$doc['ToYear'] ? $this->formatValue('Year range', @$doc['FromYear'] . ' - ' . @$doc['ToYear']) : '')
         . $this->formatValue('Availability', @$hl['SourceAvailabilityStored'][0])
         . $this->formatValue('Type', @$hl['PlaceType'][0])
         . $this->formatValue('', @$hl['TextStored'][0], true)
         . '</table></td>'
         . ($imageURL ? "<td width=\"15%\"><a href=\"$imagePageURL\"><img alt=\"\" src=\"$imageURL\"/></a></td>" : '')
         . "<td width=\"24%\" align=\"right\"><table>"
         . ($this->ecp == 'e' ? '' : $this->formatValue('', $this->getStarsImage($doc['score']), false, false))
         . $this->formatValue('Watching',$this->makeUserLinks(@$hl['UserStored'][0]), false, false)
         . $this->formatValue('Modified', @$doc['LastModDate'] ? date('j M Y',wfTimestamp(TS_UNIX, $doc['LastModDate'].'000000')) : '')
         . "</table></td></tr>\n";
      }
      return $result;
   }

//	private function convertQuotes($s) {
//      return str_replace("'", '', htmlspecialchars($s));
//		//return str_replace(array("'",'"'), array("\'",'&quot;'), $s);
//	}
	
	private function getAddJSFunction() {
		if ($this->namespace == 'Person') {
			return "addPersonPage('{$this->target}')";
		}
		else if ($this->namespace == 'Family') {
			return "addFamilyPage('{$this->target}')";
		}
		else if ($this->namespace == 'Source') {
			$sourceType = rawurlencode($this->sourceType);
			$placeIssued = rawurlencode($this->placeIssued);
			$publisher = rawurlencode($this->publisher);
			return "addSourcePage('{$this->target}','$sourceType','$placeIssued','$publisher')";
		}
		else if ($this->namespace == 'MySource') {
			return "addMySourcePage('{$this->target}')";
		}
		else if ($this->namespace == 'Place') {
			return "addPlacePage('{$this->target}')";
		}
		return '';
	}

	private function formatResults($response, $recentDocs, $selfQuery) {
		global $wgUser;
		
		$output = '';
      $start = $response['response']['start'];
      $numFound = $response['response']['numFound'];
      $end = $start + $this->rows;
      if ($end > $numFound) {
      	$end = $numFound;
      }
      
		if ($this->target && $this->namespace != 'Image') { // can't currently add images from here; just select them
         $jsFunction = $this->getAddJSFunction().'; return preventDefaultAction(event);';
			$output .= "<table><tr><td><input type=\"submit\" value=\"Add Page\" onClick=\"$jsFunction\"/></td>"
				."<td><b> Select one of the pages below, or click <a href=\"javascript:void(0);\" onClick=\"$jsFunction\">Add Page</a> to create a new page</b>"
            .' &nbsp; <span id="pleasewait" style="display: none"><span style="font-size: 80%; padding: 0 .2em; color: #fff; background-color: #888">Please Wait</span></span>'
				.($this->target != 'AddPage' && $this->target != 'gedcom' && ($this->namespace == 'Person' || $this->namespace == 'Family')
                 ? '<br/>'.FamilyTreeUtil::generateTreeCheckboxes($wgUser, null, true) : '')
				.'</td></tr></table><hr/>';
		}
		
      if ($numFound == 0 && count($recentDocs) == 0) {
          $output .= '<p></p><p><font size=+1>Your search did not match any documents.</font></p>';
      }
      else {
	      // display prev..next naviagtion
			if ($start > 0) {
			   $prevStart = $start - $this->rows;
			   if ($prevStart < 0) {
			       $prevStart = 0;
			   }
			}
			$startPlusOne = $start + 1;
	      $prevNextLinks = ($start > 0 ? "<a href=\"$selfQuery&start=$prevStart\">&laquo;&nbsp;Prev</a> |" : '') .
			        " Viewing <b>$startPlusOne-$end</b> of $numFound" .
			        ($end < $numFound ? " | <a href=\"$selfQuery&start=$end\">Next&nbsp;&raquo;</a>" : '');

			$output .= "<div class=\"prev_next_links_top\">$prevNextLinks</div>\n";
	
			// add compare button
			if ($this->match) {
		   	$skin =& $wgUser->getSkin();
				$wrCompareURL = "/wiki/Special:Compare";
				$t = Title::newFromText($this->namespace.':'.$this->pagetitle);
				$ts = '';
				if ($t) {
					$ts = htmlspecialchars($t->getText());
					$titleText = GedcomUtil::isGedcomTitle($this->pagetitle) ? '<b>'.htmlspecialchars($t->getPrefixedText()).'</b>' : $skin->makeKnownLinkObj($t);
					$compareButton = '<input type="submit" value="Compare"/> checked pages with '.$titleText;
				}
				$output .= '<form name="compare" action="'.$wrCompareURL.'" method="post">'.
								$compareButton.
								'<input type="hidden" name="ns" value="'.$this->namespace.'"/>'.
								'<input type="hidden" name="compare_0" value="'.$ts.'"/>';
			}
			
			// generate the result list
         $docs = $response['response']['docs'];
	      $highlighting = $response['highlighting'];
	      $output .= '<table class="searchresulttable">';
	      $pos = 0;
         foreach ($recentDocs as $doc) {
            $output .= $this->formatResult($doc, array(), -1, $this->condensedView);
         }
	      foreach ($docs as $doc) {
	      	$pageId = $doc['PageId'];
	      	$hl = $highlighting[$pageId];
	      	$output .= $this->formatResult($doc, $hl, $pos, $this->condensedView);
	      	$pos++;
			}
			$output .= '</table>';
	
			// add compare button
			if ($this->match) {
				$output .= $compareButton.'</form>';
			}
			
			// display prev..next navigation
         if ($this->namespace == 'Person' || $this->namespace == 'Family') {
            $similarNamesProject = wfMsgWikiHtml('SimilarNamesProjectLink');
         }
         else {
            $similarNamesProject = '';
         }
         $output .= <<<END
<table style="width:99%"><tr>
<td style="width:33%">&nbsp;</td>
<td style="width:33%; text-align:center">$prevNextLinks</td>
<td style=" width:33%; text-align:right">$similarNamesProject</td>
</tr></table>
END;
      }
      
		return $output;
	}
	
	private function populateField($queryField, $luceneField, $fieldSources, $q, $place = false) {
		if (preg_match('/&k=([^&]*)/', $q, $matches)) {
			$keywords = urldecode($matches[1]);
			if (preg_match('/(^| )'.$luceneField.':(("[^"]*")|([^ ]*))/', $keywords, $keymatches)) {
				$keywords = str_replace($keymatches[0], '', $keywords);
				return str_replace($matches[0], '&k='.urlencode($keywords), $q) . "&$queryField=".urlencode(str_replace('"','',$keymatches[2]));
			}
		}
		$match = '';
		foreach ($fieldSources as $fieldSource) {
			if (preg_match("/&$fieldSource=([^&]*)/", $q, $matches)) {
				if ($place && $match) {
					$match .= ", ";
				}
				$match .= $matches[1];
				if (!$place) {
					break;
				}
			}
		}
		if ($match) {
			$q .= "&$queryField=$match";
		}
		
		return $q;
	}
	
	private function populateKeywords($fieldSources, $q) {
		if (strpos($q, '&k=') === false) {
			$q .= '&k=';
		}
		foreach ($fieldSources as $fieldSource => $luceneField) {
			if (preg_match("/&$fieldSource=([^&]*)/", $q, $matches)) {
				$q = str_replace($matches[0], '', $q) . urlencode(" $luceneField:". $this->addQuotes(urldecode($matches[1])));
			}
		}
		return $q;
	}
	
	private function getNamespaceFacetHtml($response, $selfQuery) {
      $result = '';
		if ($this->namespace) {
			$selfQuery = preg_replace('/([?&])ns=[^&]*/', '${1}ns=', $selfQuery);
			if ($this->namespace == 'Person') {
				$selfQuery = $this->populateField('p', 'Place', array('bp', 'dp'), $selfQuery);
			}
			else if ($this->namespace == 'Family') {
				$selfQuery = $this->populateField('s', 'Surname', array('hs', 'ws'), $selfQuery);
				$selfQuery = $this->populateField('g', 'Givenname', array('hg', 'wg'), $selfQuery);
				$selfQuery = $this->populateField('p', 'Place', array('mp'), $selfQuery);
			}
			else if ($this->namespace == 'Place') {
				$selfQuery = $this->populateField('p', 'Place', array('pn', 'li'), $selfQuery, true);
			}
			$result = "<h3>Namespaces</h3><p>&laquo <a href=\"$selfQuery\">All namespaces</a></p>";
		}
		else {
			$facets = @$response['facet_counts']['facet_fields']['Namespace'];
			if (count($facets) > 0) {
				$result = '<h3>Namespaces</h3><ul>';
				foreach ($facets as $ns => $nsCount) {
					if ($ns == 'Person' || $ns == 'Place') {
						$q = $this->populateKeywords(array('p' => 'Place'), $selfQuery);
					}
					else if ($ns == 'Family') {
						$q = $this->populateKeywords(array('g' => 'Givenname', 's' => 'Surname', 'p' => 'Place'), $selfQuery);
					}
					else {
						$q = $selfQuery;
					}
					$q = str_replace('?ns=', '?ns='.$ns, $q);
					$result .= "<li><a href=\"$q\">$ns</a> ($nsCount)</li>\n";
				}
				$result .= '</ul>';
			}
		}
		
		return $result;
	}
	
	private function getSubjectFacetHtml($response, $selfQuery, $numFound) {
		if ($this->namespace != 'Source') {
			return '';
		}
		
		$result = '<h3>Subjects</h3>';
		if ($this->sourceSubject) {
			$selfQuery = preg_replace('/&su=[^&]*/', '', $selfQuery);
			$result .= "<p>&laquo <a href=\"$selfQuery\">All subjects</a></p>";
		}
		else {
			$facets = @$response['facet_counts']['facet_fields']['SourceSubject'];
			$result .= '<ul>';
			foreach ($facets as $subject => $subjectCount) {
				$result .= "<li><a href=\"$selfQuery&su=".urlencode($subject)."\">$subject</a> ($subjectCount)</li>\n";
				$numFound -= $subjectCount;
			}
			if ($numFound > 0) {
				$result .= "<li>n/a ($numFound)</li>\n";
			}
			$result .= '</ul>';
		}
		
		return $result;
	}
	
	private function getAvailabilityFacetHtml($response, $selfQuery, $numFound) {
		if ($this->namespace != 'Source') {
			return '';
		}
		
		$result = '<h3>Availability</h3>';
		if ($this->sourceAvailability) {
			$selfQuery = preg_replace('/&sa=[^&]*/', '', $selfQuery);
			$result .= "<p>&laquo <a href=\"$selfQuery\">All availabilities</a></p>";
		}
		else {
			$facets = @$response['facet_counts']['facet_fields']['SourceAvailability'];
			$result .= '<ul>';
			foreach ($facets as $avail => $availCount) {
				$result .= "<li><a href=\"$selfQuery&sa=".urlencode($avail)."\">$avail</a> ($availCount)</li>\n";
				$numFound -= $availCount;
			}
			if ($numFound > 0) {
				$result .= "<li>n/a ($numFound)</li>\n";
			}
			$result .= '</ul>';
		}
		
		return $result;
	}

	private function getPersonFacetHtml($response, $selfQuery, $heading, $facet, $abbrev, $allLabel, $facetLabel, $facetStart = 0) {
		if ($this->namespace != 'Person') {
			return '';
		}

		$result = '';
		if ($facet) {
			$selfQuery = preg_replace('/&'.$abbrev.'=[^&]*/', '', $selfQuery);
			$result .= "<h3>$heading</h3>";
			$result .= "<p>&laquo <a href=\"$selfQuery\">$allLabel</a></p>";
		}
		else {
			$facets = @$response['facet_counts']['facet_fields'][$facetLabel];
			if (count($facets) > 0) {
				$result .= "<h3>$heading</h3>";
				$result .= '<ul>';
				foreach ($facets as $facet => $count) {
					$result .= "<li><a href=\"$selfQuery&$abbrev=".urlencode($facet)."\">".htmlspecialchars(substr($facet,$facetStart))."</a> ($count)</li>\n";
				}
				$result .= '</ul>';
			}
		}

		return $result;
	}

	private function getSurnameFacetHtml($response, $selfQuery, $numFound) {
		return $this->getPersonFacetHtml($response, $selfQuery, 'Top 10 Surnames', $this->personSurnameFacet, 'psf', 'All Surnames', 'PersonSurnameFacet');
	}

	private function getGivennameFacetHtml($response, $selfQuery, $numFound) {
		return $this->getPersonFacetHtml($response, $selfQuery, 'Top 10 Given names', $this->personGivennameFacet, 'pgf', 'All Givennames', 'PersonGivennameFacet');
	}

	private function getCountryFacetHtml($response, $selfQuery, $numFound) {
		if ($this->personStateFacet) {
			return '';
		}
		return $this->getPersonFacetHtml($response, $selfQuery, 'Top 10 Countries', $this->personCountryFacet, 'pcof', 'All Countries', 'PersonCountryFacet');
	}

	private function getStateFacetHtml($response, $selfQuery, $numFound) {
		if (!$this->personCountryFacet) {
			return '';
		}
		return $this->getPersonFacetHtml($response, $selfQuery, 'Top 10 States', $this->personStateFacet, 'pstf', 'All States', 'PersonStateFacet', strlen($this->personCountryFacet)+2);
	}

	private function getCenturyFacetHtml($response, $selfQuery, $numFound) {
		if ($this->personDecadeFacet) {
			return '';
		}
		return $this->getPersonFacetHtml($response, $selfQuery, 'Birth Century', $this->personCenturyFacet, 'pcf', 'All Centuries', 'PersonCenturyFacet');
	}

	private function getDecadeFacetHtml($response, $selfQuery, $numFound) {
		if (!$this->personCenturyFacet) {
			return '';
		}
		return $this->getPersonFacetHtml($response, $selfQuery, 'Birth Decade', $this->personDecadeFacet, 'pdf', 'All Decades', 'PersonDecadeFacet');
	}

	private function getTitleLetterFacetHtml($response, $selfQuery) {
		if ($this->sort != 'title') {
			return '';
		}
		
		$result = "<h3>Title index</h3>";
		if ($this->titleLetter) {
			$selfQuery = preg_replace('/&tl=[^&]*/', '', $selfQuery);
			$result .= "<p>&laquo <a href=\"$selfQuery\">All titles</a></p>";
			$pos = array_search($this->titleLetter, self::$TITLE_LETTERS);
			if ($pos !== false) {
				$result .= '<dl><dd><ul>';
				if ($pos > 0) {
					$tl = self::$TITLE_LETTERS[$pos-1];
					$result .= "<li><a href=\"$selfQuery&tl=".urlencode($tl)."\">&uarr; ($tl)</a></li>";
				}
				$result .= "<li><b>{$this->titleLetter}</b></li>";
				if ($pos < count(self::$TITLE_LETTERS) - 1) {
					$tl = self::$TITLE_LETTERS[$pos+1];
					$result .= "<li><a href=\"$selfQuery&tl=".urlencode($tl)."\">&darr; ($tl)</a></li>";
				}
				$result .= '</ul></dd></dl>';
			}
		}
		else {
			$facets = @$response['facet_counts']['facet_fields']['TitleFirstLetter'];
			if (count($facets) > 0) {
				$result .= '<ul>';
				foreach ($facets as $tl => $tlCount) {
					$result .= "<li><a href=\"$selfQuery&tl=".urlencode($tl)."\">$tl</a> ($tlCount)</li>";
				}
				$result .= '</ul>';
			}
		}
		
		return $result;
	}
	
	private function getWatchingFacetHtml($response, $selfQuery, $numFound) {
		global $wgUser;

		if (!$wgUser->isLoggedIn()) {
			return '';
		}
		
		$result = '<h3>Watching</h3>';
      $selfQuery = preg_replace('/&watch='.$this->watch.'/', '', $selfQuery);
		if ($this->watch != 'wu') {
			$result .= "<p>&laquo <a href=\"$selfQuery\">All pages</a></p>";
		}
		else {
			$facets = @$response['facet_counts']['facet_queries'];
			if (count($facets) > 0) {
				$result .= '<ul>';
				foreach ($facets as $userName => $count) {
					$result .= "<li><a href=\"$selfQuery&watch=w\">Watched</a> ($count)</li>" .
					           "<li><a href=\"$selfQuery&watch=u\">Unwatched</a>".($numFound ? ' ('.($numFound - $count).')' : '').'</li>';
					break;
				}
				$result .= '</ul>';
			}
		}
		
		return $result;
	}

   private function getCachedTitles() {
      global $wgMemc, $wgUser;

      $titles = array();
      $cacheKey = SearchForm::getCacheKey();
      $cacheData = $wgMemc->get($cacheKey);
      if (!isset($cacheData) || $cacheData === false) {
         $dbr =& wfGetDB(DB_SLAVE);
         $use_index = $dbr->useIndexClause( 'user_timestamp' );
         extract( $dbr->tableNames( 'page', 'revision', 'index_checkpoint' ) );
         $sql = "SELECT
            page_namespace,page_title
            FROM $index_checkpoint,$page,$revision $use_index
            WHERE ic_name='revisions' AND page_id=rev_page AND rev_user = {$wgUser->getID()} AND rev_timestamp >= ic_rev_timestamp
             ORDER BY rev_timestamp DESC";
         $sql = $dbr->limitResult( $sql, 50);
         $rows = $dbr->query($sql, 'SpecialSearch');
         while ( $row = $dbr->fetchObject( $rows ) ) {
            $t = Title::makeTitle($row->page_namespace, $row->page_title);
            $titleString = $t->getPrefixedText();
            if (!in_array($titleString, $titles)) {
               $titles[] = $titleString;
            }
         }
         $dbr->freeResult( $rows );
         $cacheData = join('|', $titles);
         $wgMemc->set($cacheKey, $cacheData, self::CACHE_EXP_TIME);
      }
      else if ($cacheData) {
         $titles = explode('|', $cacheData);
      }
      return $titles;
   }

   private function checkContrib($value, $match, $titleString, $matchAll=false) {
      if ($value) {
         $words = explode(' ', trim(preg_replace('`[-.\'?~!@#$%^\&*\.()\_+=/*<>{}\[\];:"\\,/| ]+`', ' ', $value)));
         if (count($words)) {
            $found = $matchAll;
            foreach ($words as $word) {
               // word must be anchored to word breaks to match
               if (preg_match('/\b'.$word.'\b/i', $titleString)) {
                  if (!$matchAll) {
                     $found = true;
                  }
               }
               else if ($matchAll) {
                  $found = false;
               }
            }
            $match = $match || $found;
         }
      }
      return $match;
   }
	
	public function getRecentContribs($start) {
		global $wgUser, $wgLang;
		
      $recentDocs = array();
		if ($wgUser->isLoggedIn() && !$this->match && $start == 0 &&
          ($this->givenname || $this->surname || $this->husbandGivenname || $this->husbandSurname ||
             $this->wifeGivenname || $this->wifeSurname || $this->title || $this->keywords)) {
         $ns = $wgLang->getNsIndex($this->namespace);
         $titles = $this->getCachedTitles();
         foreach ($titles as $title) {
            $t = Title::newFromText($title);
            if (!$t->getNamespace() || $t->getNamespace() == $ns) {
               $titleString = $t->getText();
               $match = false;
               $match = $this->checkContrib($this->givenname, $match, $titleString);
               $match = $this->checkContrib($this->surname, $match, $titleString);
               $match = $this->checkContrib($this->husbandGivenname, $match, $titleString);
               $match = $this->checkContrib($this->husbandSurname, $match, $titleString);
               $match = $this->checkContrib($this->wifeGivenname, $match, $titleString);
               $match = $this->checkContrib($this->wifeSurname, $match, $titleString);
               $match = $this->checkContrib($this->title, $match, $titleString, true);
               $match = $this->checkContrib($this->keywords, $match, $titleString, true);
               if ($match) {
                  $doc = array();
                  $doc['NamespaceStored'] = $t->getNsText();
                  $doc['TitleStored'] = $titleString;
                  $recentDocs[] = $doc;
               }
            }
         }
		}
      return $recentDocs;
	}

	/**
	 * Return HTML for displaying search results
	 * @return string HTML
	 */
	public function getSearchResultsHtml($searchServerQuery) {
		global $wgOut, $wgScriptPath, $http_response_header;

		// send the query to the search server
//wfDebug("searchServerQuery=$searchServerQuery\n");
		$responseString = file_get_contents($searchServerQuery);
		if (!$responseString) {
			list($version, $status_code, $msg) = explode(' ', $http_response_header[0], 3);
			if ($status_code != '400') {
				$msg = 'There was an error processing your search, or the search server is down; please try a different search or try again later.';
			}
			$pos = strpos($msg, ':');
			if ($pos > 0) {
				$msg = substr($msg, $pos+1);
			}
         $msg = htmlspecialchars($msg);
			return array('', "<p><font color=\"red\">$msg</font></p>");
		}
		eval('$response = ' . $responseString . ';');

		// construct title index from facets
		
		// create basic re-query for use in various links
		$selfQuery = $this->getSelfQuery();

		// get facets
		$facetSelfQuery = preg_replace('/&ti=[^&]*/', '', $selfQuery); // remove title index from self query for facet links
      $numFound = @$response['response']['numFound'];

		$sidebar = ($this->target ? '' : $this->getNamespaceFacetHtml($response, $facetSelfQuery)) . 
					  $this->getSubjectFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getAvailabilityFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getWatchingFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getSurnameFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getGivennameFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getCountryFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getStateFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getCenturyFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getDecadeFacetHtml($response, $facetSelfQuery, $numFound) .
					  $this->getTitleLetterFacetHtml($response, $facetSelfQuery);

		// get results
      $start = @$response['response']['start'];
      $recentDocs = $this->getRecentContribs($start);
		$results = $this->formatResults($response, $recentDocs, $selfQuery);
		
		return array($sidebar, $results);
	}
	
	public function getStatsHtml() {
		global $wrSearchHost, $wrSearchPort, $wrSearchPath, $wgUser;
		
		$searchServerQuery = "http://$wrSearchHost:$wrSearchPort$wrSearchPath/stats?q=" . urlencode($wgUser->isLoggedIn() ? $wgUser->getName() : '');
		$responseString = file_get_contents($searchServerQuery);
		if (!$responseString) {
			return '';
		}
		eval('$response = ' . $responseString . ';');

		$selfQuery = $this->getSelfQuery();

		$result = $this->getNamespaceFacetHtml($response['response'][0], $selfQuery) .
					 $this->getWatchingFacetHtml($response['response'][0], $selfQuery, $response['response'][0]['total']);

		return $result;
	}

   private function addHiddenInput($name, $value) {
      return ($value ? "<input type=\"hidden\" id=\"input_$name\" name=\"$name\" value=\"".htmlspecialchars($value).'"/>' : '');
   }
	
   public function getFormHtml() {
	   global $wgUser;
	   
		$target = htmlspecialchars($this->target);
		$givenname = htmlspecialchars($this->givenname);
		$surname = htmlspecialchars($this->surname);
		$place = htmlspecialchars($this->place);
		$birthdate = htmlspecialchars($this->birthdate);
		$birthplace = htmlspecialchars($this->birthplace);
		$deathdate = htmlspecialchars($this->deathdate);
		$deathplace = htmlspecialchars($this->deathplace);
		$fathergivenname = htmlspecialchars($this->fatherGivenname);
		$fathersurname = htmlspecialchars($this->fatherSurname);
		$mothergivenname = htmlspecialchars($this->motherGivenname);
		$mothersurname = htmlspecialchars($this->motherSurname);
		$spousegivenname = htmlspecialchars($this->spouseGivenname);
		$spousesurname = htmlspecialchars($this->spouseSurname);
		$husbandgivenname = htmlspecialchars($this->husbandGivenname);
		$husbandsurname = htmlspecialchars($this->husbandSurname);
		$wifegivenname = htmlspecialchars($this->wifeGivenname);
		$wifesurname = htmlspecialchars($this->wifeSurname);
		$marriagedate = htmlspecialchars($this->marriagedate);
		$marriageplace = htmlspecialchars($this->marriageplace);
		$placename = htmlspecialchars($this->placename);
		$locatedinplace = htmlspecialchars($this->locatedinplace);
		$title = htmlspecialchars($this->title);
		$author = htmlspecialchars($this->author);
		$sourcetitle = htmlspecialchars($this->sourceTitle);
		$keywords = htmlspecialchars($this->keywords);

	   // generate form
	   if ($wgUser->isLoggedIn()) {
         $watchSelectExtra = '';
      }
      else {
	   	$watchSelectExtra = 'disabled';
	   }
      $watchSelect = StructuredData::addSelectToHtml(0, "watch", self::$WATCH_OPTIONS, $this->watch, $watchSelectExtra, false);
	   $hiddenFields = '';
	   if ($target || $this->match) {
	   	if ($target) {
	   		$hiddenFields .= '<input type="hidden" name="target" value="'.$target.'"/>';
	   	}
	   	else {
	   		$hiddenFields .= '<input type="hidden" name="match" value="true"/><input type="hidden" name="pagetitle" value="'.htmlspecialchars($this->pagetitle).'"/>';
	   	}
	   	$hiddenFields .= '<input id="ns" type="hidden" name="ns" value="'.htmlspecialchars($this->namespace).'"/>';
	   	$nsSelectField = 'dummy'; // disabled fields don't get passed back
	   	$nsSelectExtra = 'disabled';
	   }
	   else {
	   	$nsSelectField = 'ns';
	   	$nsSelectExtra = 'onChange="showSearchFields()"';
	   }
      $hiddenFields .= $this->addHiddenInput('gnd', $this->personGender);
      $hiddenFields .= $this->addHiddenInput('bt', $this->birthType);
      $hiddenFields .= $this->addHiddenInput('dt', $this->deathType);
      $hiddenFields .= $this->addHiddenInput('sty', $this->sourceType);
      $hiddenFields .= $this->addHiddenInput('pi', $this->placeIssued);
      $hiddenFields .= $this->addHiddenInput('pu', $this->publisher);
      $hiddenFields .= $this->addHiddenInput('ct', $this->childTitle);
      $hiddenFields .= $this->addHiddenInput('ht', $this->husbandTitle);
      $hiddenFields .= $this->addHiddenInput('wt', $this->wifeTitle);
      $hiddenFields .= $this->addHiddenInput('pf', $this->parentFamily);
      $hiddenFields .= $this->addHiddenInput('sf', $this->spouseFamily);
	   $nsSelect = StructuredData::addSelectToHtml(0, $nsSelectField, self::$NAMESPACE_OPTIONS_NAME, $this->namespace, $nsSelectExtra, false);
      if ($this->ecp == 'p') {
         $this->sort = 'score';
      }
      $sortSelect = StructuredData::addSelectToHtml(0, "sort", self::$SORT_OPTIONS, $this->sort, '', false);
      // TODO
      //$talkSpan = '<span id="talk_input"><input type="checkbox" name="talk"'.($this->talk ? ' checked' : '').'/>Include talk</span>';
      $talkSpan = '';
      $subChecked = ($this->sub ? ' checked' : '');
      $supChecked = ($this->sup ? ' checked' : '');
	   $birthRangeSelect = StructuredData::addSelectToHtml(0, 'br', self::$DATE_RANGE_OPTIONS, $this->birthrange, '', false);
	   $deathRangeSelect = StructuredData::addSelectToHtml(0, 'dr', self::$DATE_RANGE_OPTIONS, $this->deathrange, '', false);
		$marriageRangeSelect = StructuredData::addSelectToHtml(0, 'mr', self::$DATE_RANGE_OPTIONS, $this->marriagerange, '', false);
		$sourceSubjectSelect = StructuredData::addSelectToHtml(0, 'su', Source::$SOURCE_SUBJECT_OPTIONS, $this->sourceSubject);
		$sourceAvailabilitySelect = StructuredData::addSelectToHtml(0, 'sa', Source::$SOURCE_AVAILABILITY_OPTIONS, $this->sourceAvailability);
      $rowsSelector = StructuredData::addSelectToHtml(0, 'rows', self::$ROWS_OPTIONS, $this->rows, '', false);
      $ecpSelector = StructuredData::addSelectToHtml(0, 'ecp', self::$ECP_OPTIONS, $this->ecp, '', false);
      $heading = ($this->target && $this->namespace != 'Image' ? '<h2 style="padding-bottom:4px">Review possible matches. Select a match or click Add Page</h2>' : '');
      $condensedChecked = ($this->condensedView ? ' checked="checked"' : '');

      if ($this->target) {
         $relativeRows = '';
      }
      else {
         $relativeRows = <<< END
<tr id="father_row">
<td align=right>Father given: </td><td colspan=2><input id="input_fg" class="input_medium" type="text" name="fg" maxlength=50 value="$fathergivenname" onfocus="select()"/></td>
<td align=right>Surname: </td><td colspan=2><input id="input_fs" class="input_wider" type="text" name="fs" maxlength=50 value="$fathersurname" onfocus="select()"/></td>
</tr><tr id="mother_row">
<td align=right>Mother given: </td><td colspan=2><input id="input_mg" class="input_medium" type="text" name="mg" maxlength=50 value="$mothergivenname" onfocus="select()"/></td>
<td align=right>Surname: </td><td colspan=2><input id="input_ms" class="input_wider" type="text" name="ms" maxlength=50 value="$mothersurname" onfocus="select()"/></td>
</tr><tr id="spouse_row">
<td align=right>Spouse given: </td><td colspan=2><input id="input_sg" class="input_medium" type="text" name="sg" maxlength=50 value="$spousegivenname" onfocus="select()"/></td>
<td align=right>Surname: </td><td colspan=2><input id="input_ss" class="input_wider" type="text" name="ss" maxlength=50 value="$spousesurname" onfocus="select()"/></td>
</tr>
END;
      }

		$result = <<< END
$heading
<form id="search_form" name="search" action="/wiki/Special:Search" method="get">
$hiddenFields
<table id="searchform" class="searchform"><tr>
<td colspan=6 align=right><span class="sort_label">Sort by</span>$sortSelect</td>
</tr><tr>
<td align=right>Namespace: </td><td>$nsSelect</td><td>$talkSpan</td>
<td colspan=3 align=right>$watchSelect</td>
</tr><tr id="author_row">
<td align=right>Author: </td><td colspan=5><input id="input_a" class="input_long" type="text" name="a" maxlength=100 value="$author" onfocus="select()"/></td>
</tr><tr id="source_title_row">
<td align=right>Title: </td><td colspan=5><input id="input_st" class="input_long" type="text" name="st" maxlength=100 value="$sourcetitle" onfocus="select()"/></td>
</tr><tr id="coverage_row">
<td align=right></td><td colspan=5>Covers:</td>
</tr><tr id="name_row">
<td id="givenname_cell1" align=right>Given name: </td><td id="givenname_cell2" colspan=2><input id="input_g" class="input_medium" type="text" name="g" maxlength=50 value="$givenname" onfocus="select()"/></td>
<td align=right>Surname: </td><td colspan=2><input id="input_s" class="input_wider" type="text" name="s" maxlength=50 value="$surname" onfocus="select()"/></td>
</tr><tr id="place_row">
<td align=right>Place: </td><td colspan=5><input id="input_p" class="input_long place_input" type="text" name="p" maxlength=130 value="$place" onfocus="select()"/></td>
</tr><tr id="source_place_row">
<td align=right></td><td colspan=5>&nbsp; Include sources for <input type="checkbox" name="sub"$subChecked/>subordinate places <input type="checkbox" name="sup"$supChecked/>superior places</td>
</tr><tr id="birth_row">
<td align=right>Birth/Chr date: </td><td colspan=2><input id="input_bd" class="input_short" type="text" name="bd" size=14 maxlength=25 value="$birthdate" onfocus="select()"/> &nbsp;$birthRangeSelect</td>
<td align=right>Place: </td><td colspan=2><input id="input_bp" class="input_wider place_input" type="text" name="bp" maxlength=130 value="$birthplace" onfocus="select()"/></td>
</tr><tr id="death_row">
<td align=right>Death/Bur date: </td><td colspan=2><input id="input_dd" class="input_short" type="text" name="dd" size=14 maxlength=25 value="$deathdate" onfocus="select()"/> &nbsp;$deathRangeSelect</td>
<td align=right>Place: </td><td colspan=2><input id="input_dp" class="input_wider place_input" type="text" name="dp" maxlength=130 value="$deathplace" onfocus="select()"/></td>
</tr>
$relativeRows
<tr id="husband_row">
<td align=right>Husband given: </td><td colspan=2><input id="input_hg" class="input_medium" type="text" name="hg" maxlength=50 value="$husbandgivenname" onfocus="select()"/></td>
<td align=right>Surname: </td><td colspan=2><input id="input_hs" class="input_wider" type="text" name="hs" maxlength=50 value="$husbandsurname" onfocus="select()"/></td>
</tr><tr id="wife_row">
<td align=right>Wife given: </td><td colspan=2><input id="input_wg" class="input_medium" type="text" name="wg" maxlength=50 value="$wifegivenname" onfocus="select()"/></td>
<td align=right>Surname: </td><td colspan=2><input id="input_ws" class="input_wider" type="text" name="ws" maxlength=50 value="$wifesurname" onfocus="select()"/></td>
</tr><tr id="marriage_row">
<td align=right>Marriage date: </td><td colspan=2><input id="input_md" class="input_short" type="text" name="md" size=14 maxlength=25 value="$marriagedate" onfocus="select()"/> &nbsp;$marriageRangeSelect</td>
<td align=right>Place: </td><td colspan=2><input id="input_mp" class="input_wider place_input" type="text" name="mp" maxlength=130 value="$marriageplace" onfocus="select()"/></td>
</tr><tr id="placename_row">
<td align=right>Place name: </td><td colspan=2><input id="input_pn" class="input_medium" type="text" name="pn" maxlength=50 value="$placename" onfocus="select()"/></td>
<td align=right>Located in: </td><td colspan=2><input id="input_li" class="input_wider place_input" type="text" name="li" maxlength=130 value="$locatedinplace" onfocus="select()"/></td>
</tr><tr id="subject_row">
<td align=right>Subject: </td><td colspan=2>$sourceSubjectSelect</td>
<td align=right>Availability: </td><td colspan=2>$sourceAvailabilitySelect</td>
</tr><tr id="title_row">
<td align=right>Page title: </td><td colspan=5><input id="input_t" class="input_long" type="text" name="t" maxlength=100 value="$title" onfocus="select()"/></td>
</tr><tr>
<td align=right>Keywords: </td><td colspan=5><input id="input_k" class="input_long" type="text" name="k" maxlength=100 value="$keywords" onfocus="select()"/></td>
</tr><tr>
<td colspan=2>$rowsSelector results per page <input type="checkbox" name="cv"$condensedChecked>condensed</td><td align="right" colspan=4>$ecpSelector <input type="submit" value="Search"/></td>
</tr></table></form>
END;
	   return $result;
   }
}
?>
