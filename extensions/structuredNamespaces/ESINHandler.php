<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");
require_once("$IP/extensions/other/PlaceSearcher.php");
require_once("$IP/extensions/structuredNamespaces/TipManager.php");
require_once("$IP/extensions/structuredNamespaces/DateHandler.php");  // added Oct 2020 by Janet Bjorndahl

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfESINHandlerSetup";

# create global ESINHandler
$wgESINHandler = new ESINHandler();

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfESINHandlerSetup() {
	global $wgESINHandler;
	$wgESINHandler->setHooks();
}

/**
 * Handle events, sources, images, and notes - common to Person and Family
 */
class ESINHandler extends StructuredData {
   const PROPAGATE_MESSAGE = 'Propagate changes to';
	// tag to append to end of pages that use ESIN (currently person and family)
	const ESIN_FOOTER_TAG = "<show_sources_images_notes/>";
	
	public static $SOURCE_NAMESPACE_OPTIONS = array(
      'Citation only' => 0,
		'Source' => NS_SOURCE,
		'MySource' => NS_MYSOURCE,
	);
	public static $QUALITY_OPTIONS = array(
		'Unreliable' => '0',
		'Questionable' => '1',
		'Secondary' => '2',
		'Primary' => '3'
	);
      
  // Event type arrays added (to support event sorting) Oct 2020 by Janet Bjorndahl 
  private static $PERSON_EVENT_TYPES = array('Birth'=>'0010', 'Alt Birth'=>'0020', 'Christening'=>'0030',  'Alt Christening'=>'0040', 
      'During Life With Date'=>'0050', 'During Life Without Date'=>'0219', 'Death'=>'0220', 'Alt Death'=>'0230',
      'Obituary'=>'0240', 'Funeral'=>'0250', 'Cremation'=>'0260', 'Burial'=>'0270', 'Alt Burial'=>'0280',
      'Estate Inventory'=>'0290', 'Probate'=>'0300', 'Estate Settlement'=>'0310',
      'Ancestral File Number'=>'1010', 'Caste'=>'1020', 'Cause of Death'=>'1030', 'Citizenship'=>'1040',
      'DNA'=>'1050', 'Namesake'=>'1060', 'Nationality'=>'1070', 'Other'=>'1080', 'Physical Description'=>'1090',
      'Reference Number'=>'1100', 'Religion'=>'1110', 'Soc Sec No'=>'1120', 'Title (nobility)'=>'1130');
  private static $FAMILY_EVENT_TYPES = array('Engagement'=>'0060', 'Marriage Banns'=>'0070', 'Marriage Bond'=>'0080', 'Marriage Contract'=>'0090',
      'Marriage License'=>'0100', 'Marriage Notice'=>'0110', 'Marriage Settlement'=>'0120', 'Marriage'=>'0130',
      'Alt Marriage'=>'0140', 'Census'=>'0150', 'Residence'=>'0160', 'Other'=>'0170', 'Separation'=>'0180',
      'Divorce Filing'=>'0190', 'Divorce'=>'0200', 'Annulment'=>'0210', 'Other Family'=>'0211');
  
   private $parseLevel;
   private $childrenText;
   private $eventText;
   private $imageText;
   private $hasEventsTag;
   private $hasChildrenTag;
	private $sources;
	private $images;
	private $notes;
	private $sinMap;

	public static function getPlaces($xml) {
	   $places = array();
	   foreach ($xml->event_fact as $ef) {
	      $places[] = (string)$ef['place'];
	   }
	   return $places;
	}

	// if you change this, also change SpecialUpload::getAttrs
	public static function getBirthChrDeathBurDatePlaceDesc($xml) {
		$birthDate = null;
		$birthPlace = null;
		$chrDate = null;
		$chrPlace = null;
		$deathDate = null;
		$deathPlace = null;
		$burDate = null;
		$burPlace = null;
		$deathDesc = null;
		$burDesc = null;
		if (isset($xml->event_fact)) {
   		foreach ($xml->event_fact as $ef) {
   			if ($ef['type'] == Person::$BIRTH_TAG) {
   				$birthDate = (string)$ef['date'];
   				$birthPlace = (string)$ef['place'];
   			}
   			else if ($ef['type'] == Person::$DEATH_TAG) {
   				$deathDate = (string)$ef['date'];
   				$deathPlace = (string)$ef['place'];
   				$deathDesc = (string)$ef['desc'];
   			}
   			else if ($ef['type'] == Person::$CHR_TAG || $ef['type'] == Person::$BAPTISM_TAG) {
   				$chrDate = (string)$ef['date'];
   				$chrPlace = (string)$ef['place'];
   			}
   			else if ($ef['type'] == Person::$BUR_TAG) {
   				$burDate = (string)$ef['date'];
   				$burPlace = (string)$ef['place'];
   				$burDesc = (string)$ef['desc'];
   			}
   		}
		}
		return array($birthDate, $birthPlace, $chrDate, $chrPlace, $deathDate, $deathPlace, $burDate, $burPlace, $deathDesc, $burDesc);
	}

   // Created Nov 2021 by Janet Bjorndahl (to replace isAmbiguousDate)
   public static function isInvalidDate($date) {
      $formatedDate = $languageDate = '';
      if ( DateHandler::editDate($date, $formatedDate, $languageDate) !== true ) {        
         return true;
      }
      return false;
   }
  
  // Created Nov 2020 by Janet Bjorndahl; changed Mar 2021 to return true only if significant reformating
  public static function hasReformatedDates($xml) {
    $formatedDate = $languageDate = '';
    if (isset($xml->event_fact)) {
      foreach ($xml->event_fact as $ef) {
        $date = (string)$ef['date'];
        $dateStatus = DateHandler::editDate($date, $formatedDate, $languageDate, (string)$ef['type'], true);        
        if ( $dateStatus === 'Significant reformat' ) {                                                                                                         
          return true;
        }
      }
    }
  return false;
  }

	public static function getChildDate($child, $attr) {
	   $start = mb_strpos($child, $attr . '="');
	   if ($start !== false) {
        $start += strlen($attr . '="');                // adjustment added Oct 2020 by Janet Bjorndahl (to return only the date without the label)
	      $end = mb_strpos($child, '"', $start);
	      return mb_substr($child, $start, $end - $start);
	   }
	   return '';
	}

   public static function findRelationshipInsertionPointTag($tag, $text) {
      $tags = array('<event_fact ', '<source ', '<image ', '<note ', '</person>', '</family>');
      if ($tag == 'husband') {
         array_unshift($tags, '<wife ', '<child ');
      }
      else if ($tag == 'wife') {
         array_unshift($tags, '<child ');
      }
      else if ($tag == 'child') {
         // nothing to do
      }
      else if ($tag == 'child_of_family') {
         array_unshift($tags, '<spouse_of_family ');
      }
      else if ($tag == 'spouse_of_family') {
         // nothing to do
      }
      foreach ($tags as $t) {
         if (mb_strpos($text, $t) !== false) {
            return $t;
         }
      }
      error_log('findRelationshipInsertionPoint missing tag');
      return '';
   }

	public static function sortChildren(&$children) {
	   $childRows = explode('<', $children);
	   array_shift($childRows); // remove the first (empty) row
      $sort = array();
      $prevKey = 0;
      $pos = 0;
      $key = 0;
      foreach ($childRows as $childRow) {
         $pos++;
         $date = ESINHandler::getChildDate($childRow,'birthdate');
         if (!$date) {
            $date = ESINHandler::getChildDate($childRow,'chrdate');
         }
         if ($date) {
            $k = DateHandler::getDateKey($date, true);   // changed to DateHandler function Oct 2020 by Janet Bjorndahl 
            if ($k) {
               $key = $k;
               $prevKey = $key;
            }
         }
         if (!$key) $key = $prevKey; // if no date, assume same as previous
         $sort[$key*50+$pos] = $childRow;
      }
      if (count($sort)) {
         ksort($sort, SORT_NUMERIC);
         $children = '<' . join('<', $sort);
      }
	}

   public static function getPersonSummary($p) {
      $title = (string)$p['title'];
      $fullname = StructuredData::getFullname($p);
      $birthLabel = '&nbsp;';
      $deathLabel = '&nbsp;';
      $birthDate = $birthPlace = $deathDate = $deathPlace = '';
      if ((string)$p['birthdate'] || (string)$p['birthplace']) {
         $birthLabel = 'b. ';
         $birthDate = DateHandler::formatDate((string)$p['birthdate'],'Birth');        // formating call added Nov 2020 by Janet Bjorndahl; 2nd parm changed Apr 2024 by JB
         $birthPlace = (string)$p['birthplace'];
      }
      else if ((string)$p['chrdate'] || (string)$p['chrplace']) {
         $birthLabel = 'chr. ';
         $birthDate = DateHandler::formatDate((string)$p['chrdate'],'Christening');    // formating call added Nov 2020 by Janet Bjorndahl; 2nd parm changed Apr 2024 by JB
         $birthPlace = (string)$p['chrplace'];
      }
      if ((string)$p['deathdate'] || (string)$p['deathplace']) {
         $deathLabel = 'd. ';
         $deathDate = DateHandler::formatDate((string)$p['deathdate'],'Death');        // formating call added Nov 2020 by Janet Bjorndahl; 2nd parm changed Apr 2024 by JB
         $deathPlace = (string)$p['deathplace'];
      }
      else if ((string)$p['burialdate'] || (string)$p['burialplace']) {
         $deathLabel = 'bur. ';
         $deathDate = DateHandler::formatDate((string)$p['burialdate'],'Burial');      // formating call added Nov 2020 by Janet Bjorndahl; 2nd parm changed Apr 2024 by JB
         $deathPlace = (string)$p['burialplace'];
      }
      if ($birthPlace) $birthPlace = '[[Place:' . StructuredData::addBarToTitle($birthPlace) . ']]';
      if ($deathPlace) $deathPlace = '[[Place:' . StructuredData::addBarToTitle($deathPlace) . ']]';

      return array ($title, $fullname, $birthLabel, $birthDate, $birthPlace, $deathLabel, $deathDate, $deathPlace);
   }

	/**
     * Construct a new object
     */
	public function __construct() {
		parent::__construct('show_sources_images_notes', '', NS_MAIN); // this titleString and ns aren't right, but it doesn't matter
      $this->clearESINState();
	}

	// these functions should never be called
	protected function toWikiText($parser) { }
	protected function toEditFields(&$textbox1) { }
	protected function fromEditFields($request) { }

	public function setHooks() {
		global $wgParser, $wgHooks;

      $wgParser->setHook( 'show_sources_images_notes' , array( $this, 'renderESIN' ) );
      $wgParser->setHook( 'events' , array( $this, 'renderEvents' ) );
      $wgParser->setHook( 'children' , array( $this, 'renderChildren' ) );
      $wgParser->setHook( 'images' , array( $this, 'renderImages' ) );
//		$wgParser->setHook( 'cite' , array( &$this, 'renderCite' ) );
      $wgHooks['ParserClearState'][] = array( $this, 'clearESINState' );
      $wgHooks['ParserBeforeStrip'][] = array( $this, 'beforeStrip' );
      $wgHooks['ParserAfterStrip'][] = array( $this, 'afterStrip' );
      $wgHooks['ParserBeforeInternalParse'][] = array( $this, 'beforeInternalParse' );
	}

   private function showChildrenSection() {
      $result = '';
      if ($this->childrenText) {
         $result = "<div class=\"h2like\">Children</div>\n".$this->childrenText;
         $this->childrenText = '';
      }
      return $result;
   }

   private function showEventsSection() {
      $result = '';
      if ($this->eventText) {
         $result = "<div class=\"h2like\">Facts and Events</div>\n".$this->eventText;
         $this->eventText = '';
      }
      return $result;
   }

	public function renderESIN($input, $argv, $parser) {
      global $wgCite;

      $footer = '';
      $footer .= $this->showEventsSection();
      $footer .= $this->showChildrenSection();
      if ($this->imageText) {
         $footer .= "<div class=\"h2like\">Image Gallery</div>\n".$this->imageText;
         $this->imageText = '';
      }
      if ($footer) {
         $parserOutput = $parser->parse($footer, $parser->mTitle, $parser->getOptions(), true, false);
         $footer = $parserOutput->getText();
      }
      if ($wgCite->hasReferences()) {
         $footer .= "<div class=\"h2like\">References</div>\n".$wgCite->references(null, array(), $parser);
      }
      return $footer;
	}

   public function renderEvents($input, $argv, $parser) {
      $parserOutput = $parser->parse($this->eventText, $parser->mTitle, $parser->mOptions, true, false);
      $this->eventText = '';
      return $parserOutput->getText();
   }

   public function renderChildren($input, $argv, $parser) {
      $parserOutput = $parser->parse($this->childrenText, $parser->mTitle, $parser->mOptions, true, false);
      $this->childrenText = '';
      return $parserOutput->getText();
   }

   public function renderImages($input, $argv, $parser) {
      $parserOutput = $parser->parse($this->imageText, $parser->mTitle, $parser->mOptions, true, false);
      $this->imageText = '';
      return $parserOutput->getText();
   }

//	public function renderCite($input, $argv, $parser) {
//		$citations = StructuredData::formatAsLinks($input);
//		$parserOutput = $parser->parse("<sup>$citations</sup>", $parser->mTitle, $parser->mOptions, false, false);
//		return $parserOutput->getText();
//	}

   public function getHeaderHtml() {
      return '';
   }

	public function clearESINState() {
      $this->parseLevel = 0;
      $this->childrenText = '';
      $this->eventText = '';
      $this->imageText = '';
      $this->hasEventsTag = false;
      $this->hasChildrenTag = false;
      return true;
	}

   // before/afterStrip and beforeInternalParse don't work yet
   // I need to figure out how to either inject html text or inject <references/> tag
   public function beforeStrip(&$parser, &$text, &$x) {
      if ($this->parseLevel == 0) {
         $ns = $parser->getTitle()->getNamespace();
         $this->hasEventsTag = ($ns == NS_PERSON || $ns == NS_FAMILY) && (mb_stripos($text, '<events/>') !== false);
         $this->hasChildrenTag = ($ns == NS_FAMILY) && (mb_stripos($text, '<children/>') !== false);
      }
      $this->parseLevel++;
   }
   public function afterStrip(&$parser, &$text, &$x) {
      $this->parseLevel--;
   }
   public function beforeInternalParse(&$parser, &$text, &$x) {
      if ($this->parseLevel === 0) {
         $footer = '';
         if ($footer) {
            $pos = mb_strpos($text, '{{wikipedia-notice|');
            if ($pos === false) {
               $pos = mb_strpos($text, '{{Wikipedia-notice|');
            }
            if ($pos === false) {
               $text .= $footer;
            }
            else {
               $text = mb_substr($text, 0, $pos).$footer.mb_substr($text, $pos);
            }
         }
      }
      return true;
   }

   private function getRefsText($nameEvent, $parser, $addCitationNeeded) {
      global $wgCite;

      $cites = $this->getCites((string)@$nameEvent['sources']);
      $cites = array_merge($cites, $this->getCites((string)@$nameEvent['notes']));
      $cites = array_merge($cites, $this->getCites((string)@$nameEvent['images']));
      $refText = '';
      foreach ($cites as $cite) {
         $refText .= preg_replace('#<a href="([^"]*)"[^>]*>([^<]*)</a>#', '[[$1|$2]]',
                                 $wgCite->ref(null, array('name' => $cite), $parser)); 
      }
      if (!$refText && $addCitationNeeded && !isset($nameEvent['no_citation_needed']) && 
            !($nameEvent['type'] == 'Ancestral File Number') && !($nameEvent['type'] == 'Reference Number')) {
         $refText = '<span class="redlinks"><tt><sup>[[Citation needed|?]]</sup></tt></span>';
      }
      return $refText;
   }

   protected function formatName($name, $parser, $defaultType, $firstChildClass='') {
      $refs = $this->getRefsText($name, $parser, false);
      $type = (string)$name['type'];
      if (!$type) $type = $defaultType;
      $fullname = StructuredData::getFullname($name);
      return <<<END
<tr>
   <td class="wr-infotable-type $firstChildClass"><span class="wr-infotable-type">$type</span>$refs</td>
   <td colspan="2" class="wr-infotable-fullname $firstChildClass"><span class="wr-infotable-fullname">$fullname</span></td>
</tr>
END;
   }

   protected function formatGender($gender, $firstChildClass='') {
      if ($gender == 'M') {
         $gender = 'Male';
      }
      else if ($gender == 'F') {
         $gender = 'Female';
      }
      else if ($gender == '?') {
         $gender = 'Unknown';
      }
      return <<<END
<tr>
   <td class="wr-infotable-type $firstChildClass"><span class="wr-infotable-type">Gender</span></td>
   <td colspan="2" class="wr-infotable-gender $firstChildClass"><span class="wr-infotable-gender">$gender</span></td>
</tr>
END;
   }
  
  // sortEvents rewritten Oct 2020 by Janet Bjorndahl                  
  private function sortEvents($xml, $marriageEvents=null) {
    $sortp=array();
    $sortf=array();
    $sorta=array();
    $sortedEvents=array();
    $familyDate = false;

    /* Goal:
     *   Sort events and facts by date.
     *   When the date includes a modifier (e.g., Bef, Aft), adjust the date by one unit (day, month or year, depending on the level of detail in the date). 
     *   Treat dates as the same date when one or more is incomplete (e.g., only the year) and they match on as much information as exists.
     *   Person events (certain event_types) without a date are placed immediately before the death event.
     *   Person facts (certain event_types) without a date are placed after all events.
     *   Marriage events without a date are grouped by marriage. 
     *   When two or more events have the same sort key, keep them in logical order (e.g., death before burial), or alphabetical order when there is no logical order.
     * Sort strategy: 
     *   Overall strategy is to sort first by type and then by date, using a sort that preserves relative order when 2 events have equivalent sort keys.
     *   This ensures the goal of having events in logical order when they have equivalent dates (e.g., will=5 Sep 1875, death=1875).
     *   Marriage events are sorted by marriage number and type, and sort year is assigned when date is missing. 
     *     Both of these are required to ensure grouping by marriage.   
     */
      
    // Sort family events with dates
    if ( isset($marriageEvents) ) {
      $i=0;
      $marriageNum=0;
      $marriageTo='';
      foreach ($marriageEvents as $eventFact) {
        if ( $marriageNum === 0 || substr($eventFact['desc'], strpos($eventFact['desc'],'[')) != $marriageTo ) {
          $marriageNum++;
          $marriageTo = substr($eventFact['desc'], strpos($eventFact['desc'],'['));
        }
        if ( isset($eventFact['date']) && $dateKey = DateHandler::getDateKey((string)$eventFact['date'], true) ) {
          $sortf[$i]['data'] = $eventFact;
          $sortf[$i]['num'] = $marriageNum;                                   
          if ( $typekey = @self::$FAMILY_EVENT_TYPES[(string)$eventFact['type']] ) {
            $sortf[$i]['typekey'] = $typekey;
          }
          else {
            $sortf[$i]['typekey'] = self::$FAMILY_EVENT_TYPES['Other Family'] . $eventFact['type'];
          }
          // Next 5 lines changed to handle BC years, Apr 2021 by Janet Bjorndahl
          $sortf[$i]['datekey'] = $dateKey;
          $sortf[$i]['year'] = substr($dateKey,0,-4);
          $sortf[$i]['month'] = substr($dateKey,0,-2);
          $sortf[$i]['keytype'] = ( substr($dateKey,-2,2) !== '00' ? 'day' : 
                                   (substr($dateKey,-4,2) !== '00' ? 'month' : 'year') );
          $i++;        
        }
      }
      if ( $i > 0 ) {
        $familyDate = true;
        $sortf = ESINHandler::sortEventKeys($sortf, 'numtypekey');
        $sortf = ESINHandler::sortEventKeys($sortf, 'datekey');
      }
    }
      
    // Sort person events with dates (separately from family events)
    if ( isset($xml->event_fact) ) {
      $i=0;
      foreach ($xml->event_fact as $eventFact) {
        if ( $this->massageEvent($eventFact) &&     // A few event types need to be changed or suppressed due to changes in WeRelate (added Apr 2025 by Janet Bjorndahl)
              isset($eventFact['date']) && $dateKey = DateHandler::getDateKey((string)$eventFact['date'], true) ) {
          $sortp[$i]['data'] = $eventFact;
          if ( $typekey = @self::$PERSON_EVENT_TYPES[(string)$eventFact['type']] ) {
            $sortp[$i]['typekey'] = $typekey;
          }
          else {
            $sortp[$i]['typekey'] = self::$PERSON_EVENT_TYPES['During Life With Date'] . $eventFact['type'];
          }
          // Next 5 lines changed to handle BC years, Apr 2021 by Janet Bjorndahl
          $sortp[$i]['datekey'] = $dateKey;
          $sortp[$i]['year'] = substr($dateKey,0,-4);
          $sortp[$i]['month'] = substr($dateKey,0,-2);
          $sortp[$i]['keytype'] = ( substr($dateKey,-2,2) !== '00' ? 'day' : 
                                   (substr($dateKey,-4,2) !== '00' ? 'month' : 'year') );
          
          // If no family events have a date, track birth and death (or proxy) date keys for setting a default sort year for family events
          if ( !$familyDate ) {
            if ( !isset($birthKey) && ($eventFact['type'] == "Birth" || $eventFact['type'] == "Christening" || $eventFact['type'] == "Baptism") ) {
              $birthKey = $dateKey;
            }
            if ( !isset($deathKey) && ($sortp[$i]['typekey'] >= self::$PERSON_EVENT_TYPES["Death"]) ) {
              $deathKey = $dateKey;
            }
          }
                                              
          $i++;        
        }
      }
      if ( $i > 0 ) {
        $sortp = ESINHandler::sortEventKeys($sortp, 'typekey');
        $sortp = ESINHandler::sortEventKeys($sortp, 'datekey');
        
        // If no family events have a date, set a default sort year for family events
        // Assume 20 years after birth/proxy (but before death/proxy), or 1 year after first person event if no birth/proxy or death/proxy date
        if ( !$familyDate ) {
          if ( isset($birthKey) ) {
            $marriageKey = $birthKey + 200000;
          }
          if ( isset($deathKey) ) {
            if ( !isset($marriageKey) || $marriageKey >= $deathKey ) {
              $marriageKey = $deathKey - 10000;
            }
          }
          if ( !isset($marriageKey) ) {
            $marriageKey = $sortp[0]['datekey'] + 10000;
          }
          $marriageYear = substr($marriageKey,0,-4);              // Changed Apr 2021 to handle BC dates - Janet Bjorndahl
        }
      }
    }
    
    // Add family events without dates to the family array, setting a sort year for each. 
    // The sort year prevents events from one family overlapping events from another family, when the next sort-by-date occurs.
    if ( isset($marriageEvents) ) {
      $start = sizeof($sortf);
      $i = $start;
      $marriageNum=0;
      $marriageTo='';
      foreach ($marriageEvents as $eventFact) {
        if ( $marriageNum === 0 || substr($eventFact['desc'], strpos($eventFact['desc'],'[')) != $marriageTo ) {
          $marriageNum++;
          $marriageTo = substr($eventFact['desc'], strpos($eventFact['desc'],'['));
        }
        // The template NotToBeConfusedWith used on a Family page doesn't display correctly (and is not particularly relevant) on a Person page, so exclude it.
        if ( (!isset($eventFact['date']) || !($dateKey = DateHandler::getDateKey((string)$eventFact['date'], true))) && substr($eventFact['desc'], 0, 21) != "{{NotToBeConfusedWith" ) 
        {
          $sortf[$i]['data'] = $eventFact;
          $sortf[$i]['num'] = $marriageNum;                                   
          if ( $typekey = @self::$FAMILY_EVENT_TYPES[(string)$eventFact['type']] ) {
            $sortf[$i]['typekey'] = $typekey;
          }
          else {
            $sortf[$i]['typekey'] = self::$FAMILY_EVENT_TYPES['Other Family'] . $eventFact['type'];
          }
          // If at least one family event has a date, set date key (year) relative to the family date keys available.
          // Adjust by one year for each different family, as required.
          if ( $familyDate ) {
            $j=0;
            // In order to get the best date key, look at least as far as the next event in the same family or the next family before accepting
            // a date key. If a date key (year) is not found by that point, keep looking (until the end of the events previously sorted by date key).
            while ( $j < $start && (!isset($sortf[$i]['year']) || ($sortf[$j-1]['num'] < $sortf[$i]['num']) ||
                     ($sortf[$j-1]['num'] == $sortf[$i]['num'] && $sortf[$j-1]['typekey'] < $sortf[$i]['typekey'])) ) {
              if ( isset($sortf[$j]['year']) ) {
                $sortf[$i]['year'] = $sortf[$j]['year'] + $sortf[$i]['num'] - $sortf[$j]['num'];    // Changed Apr 2021 due to other changes to handle BC dates - JB
                $sortf[$i]['keytype'] = 'year';
              }
              $j++;
            }
          }
          // If no family events had a date but at least one person event had a date, set all family events to the same default sort year - they will sort by family number and type
          else {
            if ( isset($marriageYear) ) {
              $sortf[$i]['year'] = $marriageYear;
              $sortf[$i]['keytype'] = 'year';
            }
          }
          $i++;        
        }
      }
      // Incorporate the additional family events (if any) into the previously sorted family events, inserting them where they belong based on family number and event type 
      if ( $i > $start ) {
        $sortf = ESINHandler::sortEventKeys($sortf, 'numtypekey', $start);
      }
    }
    
    // Combine the person and family events and sort first by type and then date. Relative order achieved in previous sorts will be preserved when dates are equivalent.
    $sorta = array_merge($sortp, $sortf);
    $sorta = ESINHandler::sortEventKeys($sorta, 'typekey'); 
    $sorta = ESINHandler::sortEventKeys($sorta, 'datekey'); 

    // Add person events without dates, inserting them where they belong based on event type
    if ( isset($xml->event_fact) ) {
      $start = sizeof($sorta);
      $i = $start;
      foreach ($xml->event_fact as $eventFact) {
        if ( $this->massageEvent($eventFact) &&     // A few event types need to be changed or suppressed due to changes in WeRelate (added Apr 2025 by Janet Bjorndahl)
              (!isset($eventFact['date']) || !($dateKey = DateHandler::getDateKey((string)$eventFact['date'], true))) ) {
          $sorta[$i]['data'] = $eventFact;
          if ( $typekey = @self::$PERSON_EVENT_TYPES[(string)$eventFact['type']] ) {
            $sorta[$i]['typekey'] = $typekey;
          }
          else {
            $sorta[$i]['typekey'] = self::$PERSON_EVENT_TYPES['During Life Without Date'] . $eventFact['type'];
          }
          $i++;        
        }
      }
      if ( $i > $start ) {
        $sorta = ESINHandler::sortEventKeys($sorta, 'typekey', $start);
      }
    }
    foreach ($sorta as $sort) {
      $sortedEvents[] = $sort['data'];
    }
    return $sortedEvents;
  }
  
  private function massageEvent(&$event) {
    // Massage or suppress an event as appropriate based on changes in WeRelate event types or conventions.
    // Returns whether or not to keep the event.
     
    // Keep Soc Sec No fact as Residence only if a place is identified. Otherwise, drop it.
    if ($event['type'] == "Soc Sec No") {
      if ($event['place']) {
        $event['type'] = "Residence";
        $event['desc'] = "";
        return true;
      }
      else {
        return false;
      }
    }
     
    // Keep Ancestral File Number fact as Reference Number if a validly-formated ancestral file number is present. Otherwise, drop it.
    if ($event['type'] == "Ancestral File Number") {
      $matches = array();
      if ($event['desc'] && preg_match("/[A-Za-z0-9]+-[A-Za-z0-9]+/", $event['desc'], $matches)) {
        $event['type'] = "Reference Number";
        $event['desc'] = "{{AFN|" . strtoupper($matches[0]) . "}}";
        return true;
      }
      else {
        return false;
      }
    }

    return true; 
  }
 
  // "Insert sort" - this preserves the original order of items with equivalent keys
  private function sortEventKeys($sort, $key, $start=1) {
    $temp = array();
    
    if ($key == 'typekey') {
      $i = $start;
      while ( $i < sizeof($sort) ) {
        $temp = $sort[$i];
        $j = $i-1;
        while ( $j >= 0 and $sort[$j]['typekey'] > $temp['typekey'] ) {
          $sort[$j+1] = $sort[$j];
          $j--;
        }
        $sort[$j+1] = $temp;
        $i++;
      }
    }
    if ($key == 'numtypekey') {
      $i = $start;
      while ( $i < sizeof($sort) ) {
        $temp = $sort[$i];
        $j = $i-1;
        while ( $j >= 0 and ($sort[$j]['typekey'] > $temp['typekey'] || $sort[$j]['num'] > $temp['num']) ) {
          $sort[$j+1] = $sort[$j];
          $j--;
        }
        $sort[$j+1] = $temp;
        $i++;
      }
    }
    if ($key == 'datekey') {
      $i = $start;
      while ( $i < sizeof($sort) ) {
        $temp = $sort[$i];
        $j = $i-1;
        // Reorder dates if the first is later than the second or if it has greater precision and the types are of the same order.
        // The latter condition is required to ensure that all dates in the same month are eventually compared to each other. 
        while ( $j >=0 and (ESINHandler::compareDates($sort[$j], $temp) === 'later' ||
            (ESINHandler::compareDates($sort[$j], $temp) === 'greater precision'  && substr($sort[$j]['typekey'],0,4) === substr($temp['typekey'],0,4))) ) {
          $sort[$j+1] = $sort[$j];
          $j--;
        }
        $sort[$j+1] = $temp;
        $i++;
      }
    }
    return $sort;
  }
  
  // Compare dates at the lowest level (day, month or year) in common between the 2 dates
  // Returns:
  //    equal - same date, same precision (or one or both dates are missing)
  //    later - the first date is later than the second
  //    earlier - the first date is earlier than the second
  //    greater precision - the dates are equivalent, and the first has greater precision than the second
  //    less precision - the dates are equivalent, and the first has less precision than the second
  private function compareDates($s1, $s2) {
    if ( !isset($s1['keytype']) || !isset($s2['keytype']) ) {
      return 'equal';
    }
    
    // Compare years. If both dates have the same year, and one of them has only the year, compare precision.
    if ( $s1['year'] > $s2['year'] ) {
      return 'later';
    }
    if ( $s1['year'] < $s2['year'] ) {
      return 'earlier';
    }
    if ( $s1['keytype'] === 'year' ) {
      if ( $s2['keytype'] === 'year' ) {
        return 'equal';
      }
      else {
        return 'less precision';
      }
    }
    if ( $s2['keytype'] === 'year' ) {
      return 'greater precision';
    }
    
    // Neither is year only. Compare months. If both dates have the same month, and one of them has only the month, compare precision.
    if ( $s1['month'] > $s2['month'] ) {
      return 'later';
    }
    if ( $s1['month'] < $s2['month'] ) {
      return 'earlier';
    }
    if ( $s1['keytype'] === 'month' ) {
      if ( $s2['keytype'] === 'month' ) {
        return 'equal';
      }
      else {
        return 'less precision';
      }
    }
    if ( $s2['keytype'] === 'month' ) {
      return 'greater precision';
    }
    
    // Both dates are precise to the day. Compare.
    if ($s1['datekey'] > $s2['datekey']) {
      return 'later';
    }
    if ($s1['datekey'] < $s2['datekey']) {
      return 'earlier';
    }
    return 'equal';
  }
  
   private function getCites($text) {
      $citations = array();
      $cites = preg_split('/[;,]/', $text, -1, PREG_SPLIT_NO_EMPTY);
      foreach ($cites as $cite) {
         $cite = trim($cite);
         if (!in_array($cite, $citations)) {
            $citations[] = $cite;
         }
      }
      return $citations;
   }

   protected function formatEventFact($eventFact, $parser, $firstChildClass='') {
      $refs = $this->getRefsText($eventFact, $parser, true);
//      $sources = StructuredData::formatAsLinks((string)@$eventFact['sources']);
//      $notes = StructuredData::formatAsLinks((string)@$eventFact['notes']);
//      $images = StructuredData::formatAsLinks((string)@$eventFact['images']);
      $type = (string)$eventFact['type'];
      $date = DateHandler::formatDate((string)$eventFact['date'], $type);         // added Nov 2020 by Janet Bjorndahl; 2nd parm changed Apr 2024 by JB
      $place = (string)$eventFact['place'];
      if ($place) {
         $place = '[[Place:' . StructuredData::addBarToTitle($place) . ']]';
      }
      $desc = (string)$eventFact['desc'];
      return <<<END
<tr>
   <td class="wr-infotable-type $firstChildClass"><span class="wr-infotable-type">$type</span>$refs</td>
   <td class="wr-infotable-date $firstChildClass"><span class="wr-infotable-date">$date</span></td>
   <td class="wr-infotable-placedesc $firstChildClass"><span class="wr-infotable-place">$place</span><span class="wr-infotable-desc">$desc</span></td>
</tr>
END;
   }

   private function getSourceCitationText($sourceCitation) {
      $citation = '';
      $recordName = (string)$sourceCitation['record_name'];
      $citation .= $recordName;
      $title = (string)$sourceCitation['title'];
      $titleLower = mb_strtolower($title);
      if ($recordName && $title) {
         $citation = StructuredData::chomp($citation,',').', in ';
      }
      $srcTitle = '';
      $altTitle = '';
      if (mb_strpos($titleLower, 'source:') === 0 || mb_strpos($titleLower, 'mysource:') === 0) {
         $fields = explode('|',$title);
         if (count($fields) > 1) {
            $altTitle = $fields[1];
         }
         $t = Title::newFromText($fields[0]);
         if ($t) {
            $srcTitle = StructuredData::getRedirectToTitle($t);
         }
      }
      if ($srcTitle) {
         if ($altTitle) {
            $citation .= "[[".$srcTitle->getPrefixedText()."|$altTitle]]";
         }
         else {
            if ($srcTitle->getNamespace() == NS_SOURCE) {
               $source = new Source($srcTitle->getText());
            }
            else {
               $source = new MySource($srcTitle->getText());
            }
            $source->loadPage();
            $citation .= $source->getCitationText(true);
         }
      }
      else {
         $citation .= $title;
      }
      $extra = "";
      $page = (string)$sourceCitation['page'];
      if ($page) {
         $extra = $page;
      }
      $date = (string)$sourceCitation['date'];
      if ($date) {
         $extra = $extra ? "$extra, $date" : $date;
      }
//       $quality = (string)$sourceCitation['quality'];
//       if (strlen($quality) > 0) {
//          $qualName = array_search($quality, self::$QUALITY_OPTIONS);
//          if (!$qualName && @self::$QUALITY_OPTIONS[$quality]) $qualName = $quality; // allow old alpha form
//          if ($qualName) {
//          $extra = StructuredData::chomp($extra,',').", $qualName quality";
//          }
//       }
      if ($extra) {
            $citation .= "<br>$extra";
      }
      return $citation;
   }

   protected function formatSourceCitation($sourceCitation, $xml, $parser) {
      global $wgCite;

      $citation = $this->getSourceCitationText($sourceCitation);

//      $notes = StructuredData::formatAsLinks((string)$sourceCitation['notes']);
//      $images = StructuredData::formatAsLinks((string)$sourceCitation['images']);
//      $refs = '';
//      if ($notes || $images) {
//         $refs = ' <sup>' . $images . ($notes && $images ? ', ' : '') . $notes . '</sup>';
//      }
      $text = (string)$sourceCitation['text'];
      $text .= (string)$sourceCitation; // get from both until we standardize on the latter
//      if ($text) {
//         $text = " <span class=\"wr-infotable-text\">$text</span>";
//      }
      $text = trim(str_replace("\n", "<br>", $text)); // hack - parser call in cite translates \n to '', so add spaces
      if ($text) $text = "\n<div class=\"wr-citation-text\">\n$text\n</div>\n"; // <code> tag must be at begin of line for parser to handle it properly
      
      $notes = '';
      $cites = $this->getCites((string)$sourceCitation['notes']);
      foreach ($cites as $cite) {
         foreach ($xml->note as $note) {
            if ($cite == (string)$note['id']) {
               // get note text from both until we standardize on the latter
               $noteText = (string)$note['text'];
               $noteText .= (string)$note;
               $noteText = trim(str_replace("\n", "<br>", $noteText));
               $notes .= "\n<div class=\"wr-citation-sourcenote\">\n$noteText\n</div>\n";  // <code> tag must be at begin of line for parser to handle it properly
            }
         }
      }
      $images = '';
      $cites = $this->getCites((string)$sourceCitation['images']);
      foreach ($cites as $cite) {
         foreach ($xml->image as $image) {
            if ($cite == (string)$image['id']) {
               $images .= $this->formatImage($image, true);
            }
         }
      }
      if ($images) {
         $images = '<div class="wr-citation-images">'.$images.'</div>';
      }

      return StructuredData::chomp($citation,'.').'. '.$text.' '.$notes.$images;
//      return <<<END
//<tr id="$id">
//   <td class="wr-infotable-id $firstChildClass"><span class="wr-infotable-id">$id.</span></td>
//   <td class="wr-infotable-citation $firstChildClass"><span class="wr-infotable-citation">$citation.</span>$refs$text</td>
//</tr>
//END;
   }

   protected function formatImage($image, $inCitation=false) {
      $filename = (string)$image['filename'];
      if (!$filename) return '';
      $caption = (string)$image['caption'];
      $iconWidth = SearchForm::THUMB_WIDTH;
      $iconHeight = 48;
      $t = Title::makeTitle(NS_IMAGE, $filename);
      if (!$t || !$t->exists()) return '';
      $image = new Image($t);
      $maxHoverWidth = 700;
      $maxHoverHeight = 300;
      $width = $image->getWidth();
      $height = $image->getHeight();
      if ( $height > 0 && $maxHoverWidth > $width * $maxHoverHeight / $height) {
         $maxHoverWidth = wfFitBoxWidth( $width, $height, $maxHoverHeight );
      }
      $imageURL = $image->createThumb($maxHoverWidth, $maxHoverHeight);
      $titleAttr = StructuredData::escapeXml("$imageURL|$maxHoverWidth");
      if ($inCitation) {
         return "<span class=\"wr-citation-image wr-imagehover inline-block\" title=\"$titleAttr\">$caption [[Image:$filename|{$iconWidth}x{$iconHeight}px]]</span>";
      }
      else {
         return "<span class=\"wr-imagehover inline-block\"  title=\"$titleAttr\">[[Image:$filename|thumb|left|$caption]]</span>";
      }
//      return <<<END
//<div class="inline-block">
//   <span id="$id">[[Image:$filename|thumb|left|$id. $caption]]</span>
//</div>
//END;
   }

   protected function formatNote($note) {
      $text = (string)$note['text'];
      $text .= (string)$note; // get from both until we standardize on the latter
      $text = trim(str_replace("\n", "<br>", $text));
      return $text;
//      return <<<END
//<tr id="$id">
//   <td class="wr-infotable-id $firstChildClass"><span class="wr-infotable-id">$id.</span></td>
//   <td class="wr-infotable-text $firstChildClass"><span class="wr-infotable-text">$text</span></td>
//</tr>
//END;
   }

   protected function formatChild($childNum, $child, $firstChildClass='') {
      list ($title, $fullname, $birthLabel, $birthDate, $birthPlace, $deathLabel, $deathDate, $deathPlace) = ESINHandler::getPersonSummary($child);
      if ($birthLabel == 'b. ') $birthLabel = '';
      if ($deathLabel == 'd. ') $deathLabel = '';
      return <<<END
<tr>
   <td class="wr-infotable-id $firstChildClass">$childNum.</td>
   <td class="$firstChildClass"><div class="wr-infotable-fullname">[[Person:$title|$fullname]]</div></td>
   <td class="$firstChildClass"><div class="wr-infotable-event">$birthLabel<span class="wr-infotable-date">$birthDate</span> <span class="wr-infotable-place">$birthPlace</span></div></td>
   <td class="$firstChildClass"><div class="wr-infotable-event">$deathLabel<span class="wr-infotable-date">$deathDate</span> <span class="wr-infotable-place">$deathPlace</span></div></td>
</tr>
END;
   }

   private function getChildrenInfotable($xml, $pf) {
      $firstChildClass='first-child';
      $infotable = '';
      $childNum = 0;
      foreach ($xml->child as $child) {
         $childNum++;
         $infotable .= $this->formatChild($childNum, $child, $firstChildClass);
         $firstChildClass='';
      }
      $t = Title::makeTitle(NS_SPECIAL, 'AddPage');
      $url = $t->getFullURL('namespace=Person&pf='.urlencode($pf->title->getText()));
      $addChildLink = ($pf->isGedcomPage() ? '' : "<div class=\"plainlinks addchildlink\">[$url Add child]</div>");
      if ($infotable || $addChildLink) {
         $infotable = <<<END
<table class="wr-infotable wr-infotable-children">
<tr><th class="wr-infotable-id"></th><th></th><th>Birth</th><th>Death</th></tr>
$infotable
</table>
$addChildLink
END;
      }
      return $infotable;
   }

   private function getEventInfotable($xml, $parser, $events) {
      $firstChildClass='first-child';
      $infotable = '';
      if (isset($xml->name)) {
         $infotable .= $this->formatName($xml->name, $parser, "Name", $firstChildClass);
         $firstChildClass='';
      }
      // add alt names
      if (isset($xml->alt_name)) {
         foreach ($xml->alt_name as $name) {
            $infotable .= $this->formatName($name, $parser, '', $firstChildClass);
            $firstChildClass='';
         }
      }
      // add gender
      if (isset($xml->gender)) {
         $infotable .= $this->formatGender((string)$xml->gender, $firstChildClass);
         $firstChildClass='';
      }
      // add events
      foreach ($events as $event) {
         $infotable .= $this->formatEventFact($event, $parser, $firstChildClass);
         $firstChildClass='';
      }
      if ($infotable) {
         $infotable = "<table class=\"wr-infotable wr-infotable-factsevents\">$infotable</table>\n";
      }
      return $infotable;
   }

	public function addSourcesImagesNotes($pf, $parser, $marriageEvents=null) {
      global $wgCite;

      $xml = $pf->xml;

      // add sources and notes to refs
      $sourceCitedImages = array();
      $sourceCitedNotes = array();
      if (isset($xml->source_citation)) {
         foreach ($xml->source_citation as $sourceCitation) {
            $sourceCitedImages = array_merge($sourceCitedImages, $this->getCites((string)$sourceCitation['images']));
            $sourceCitedNotes = array_merge($sourceCitedNotes, $this->getCites((string)$sourceCitation['notes']));
         }
      }
      $eventCitedImages = array();
      $eventCitedNotes = array();
      if (isset($xml->event_fact)) {
         foreach ($xml->event_fact as $eventFact) {
            $eventCitedImages = array_merge($eventCitedImages, $this->getCites((string)$eventFact['images']));
            $eventCitedNotes = array_merge($eventCitedNotes, $this->getCites((string)$eventFact['notes']));
         }
      }
      if (isset($xml->source_citation)) {
         foreach ($xml->source_citation as $sourceCitation) {
            $key = (string)$sourceCitation['id'];
            $text = $this->formatSourceCitation($sourceCitation, $xml, $parser);
            if ($text) {
               $wgCite->addUncitedRef($key, $text);
            }
         }
      }
      if (isset($xml->note)) {
         foreach ($xml->note as $note) {
            $key = (string)$note['id'];
            if (!in_array($key, $sourceCitedNotes) || in_array($key, $eventCitedNotes)) {
               $text = $this->formatNote($note);
               if ($text) {
                  $wgCite->addUncitedRef($key, $text);
               }
            }
         }
      }
      if (isset($xml->image)) {
         foreach ($xml->image as $image) {
            $key = (string)$image['id'];
            if (in_array($key, $eventCitedImages)) {
               $text = $this->formatImage($image, true);
               if ($text) {
                  $wgCite->addUncitedRef($key, $text);
               }
            }
         }
      }
      // generate children table
      $this->childrenText = ($pf->ns == NS_FAMILY ? $this->getChildrenInfotable($xml, $pf) : '');
      // generate events table
      $events = $this->sortEvents($xml, $marriageEvents);
      $this->eventText = $this->getEventInfotable($xml, $parser, $events);
      // add images
     if (isset($xml->image)) {
         $header = "<div class=\"wr-gallery\">";
         $headerAdded = false;
         foreach ($xml->image as $image) {
            $key = (string)$image['id'];
            if (!(string)$image['primary'] && !in_array($key, $sourceCitedImages) && !in_array($key, $eventCitedImages)) {
               if (!$headerAdded) {
                  $this->imageText .= $header;
                  $headerAdded = true;
               }
               $this->imageText .= $this->formatImage($image);
				}
         }
         if ($headerAdded) {
            $this->imageText .= "</div>\n";
         }
      }

      $result = '';
      if (!$this->hasEventsTag) {
         $result .= $this->showEventsSection();
      }
      if (!$this->hasChildrenTag) {
         $result .= $this->showChildrenSection();
      }
      if ($result) {
         $result .= '<div class="wr-openclose-end"></div>';
      }
      return $result;
	}

   public function getPrimaryImage($xml) {
      if (isset($xml->image)) {
         foreach ($xml->image as $image) {
            if ((string)$image['primary']) {
               return $image;
            }
         }
      }
      return null;
   }

	protected function addEventFactInput($efNum, $eventFact, $stdEventType, $otherEventTypes, $tm) {
		$typeString = '';
		$date = '';
		$place = '';
		$desc = '';
		$sources = '';
		$notes = '';
		$images = '';
    $dateStyle = '';
    $dateStatus = '';
    $formatedDate = '';
    $languageDate = '';
    $prevDate = '';
    
		if (isset($eventFact)) {
      if ( !$this->massageEvent($eventFact) ) {     // A few event types need to be changed or suppressed due to changes in WeRelate (added Apr 2025 by Janet Bjorndahl)
        return "";
      }

			$typeString = htmlspecialchars((string)$eventFact['type']);
			$date = htmlspecialchars((string)$eventFact['date']);
      // If the date passes date editing but is not in standard format, replace it with the properly formated version
      // added Oct 2020; changed Mar 2021 by Janet Bjorndahl; changed Apr 2024 
      $dateStatus = DateHandler::editDate($date, $formatedDate, $languageDate, $typeString, true);
      if ( $dateStatus === 'Significant reformat' ) {              // Display in yellow with prev date below if parser had to do a significant reformat
          $prevDate = $date;
          $dateStyle = ' style="background-color:#ffff99;"';
          $dateStatus = true;                                      // Set datestatus to true (successful edit) for remaining code
      }
      if ( $dateStatus === true ) {
        $date = $languageDate;
      }
//    If the date fails date editing, display in light red (will need to be corrected). (Changed from a check for just ambiguous dates Nov 2021 by Janet Bjorndahl)
      else {                               
         $dateStyle = ' style="background-color:#fdd;"';
      }
         
			$place = htmlspecialchars((string)$eventFact['place']);
			$desc = htmlspecialchars((string)$eventFact['desc']);
			$sources = htmlspecialchars((string)$eventFact['sources']);
			$notes = htmlspecialchars((string)$eventFact['notes']);
			$images = htmlspecialchars((string)$eventFact['images']);
		}
		$result = '<tr>';
		if ($stdEventType) {
			$result .= "<td>$stdEventType</td>";
		}
		else {
			$result .= "<td><select class=\"ef_select\" tabindex=\"1\" name=\"event_fact$efNum\">" .
			'<option value="Unknown"' . (empty($typeString) || $typeString == 'Unknown' ? ' selected="selected"' : '') . '>Type</option>';
			foreach ($otherEventTypes as $eventType) {
				$eventType = str_replace('~',"'", $eventType);
				if (mb_substr($eventType, 0, 1) == '=') {
					$eventType = mb_substr($eventType, 1);
					$attrs = ' disabled="disabled" style="color:GrayText"';
					$value = '';
				}
				else {
					$attrs = '';
					if (mb_substr($eventType, 0, 1) == ':') {
						$value = mb_substr($eventType, 1);
						$eventType = "\xc2\xa0\xc2\xa0".$value;
					}
					else {
						$value = $eventType;
					}
				}
				$result .= '<option value="'.$value.'"'.($typeString == $value ? ' selected="selected"' : '')."$attrs>$eventType</option>";
			}
			$result .= '</select></td>';
		}
		$result .= "<td><input class=\"ef_date\" tabindex=\"1\" type=\"text\" name=\"date$efNum\" value=\"$date\"$dateStyle/></td>";
		if ($stdEventType == 'Burial' || $typeString == 'Alt Burial') {
		    $acClass = "place_input";
		} else {
		    $acClass = "nocemetery_input";
		}
       	$result .= "<td><input class=\"$acClass ef_place\" tabindex=\"1\" type=\"text\" name=\"place$efNum\" value=\"$place\"/></td>";
		$result .= "<td colspan=\"2\"><input class=\"ef_desc\" tabindex=\"1\" type=\"text\" name=\"desc$efNum\" value=\"$desc\"/></td>";
      $oneBased = $efNum+1;
      if (!$stdEventType) {
         $result .= "<td><a title='Remove this event/fact' href=\"javascript:void(0);\" onClick=\"removeEventFact($oneBased); return preventDefaultAction(event);\">remove</a></td>";
      }
      else {
         $result .= "<td></td>";
      }
      $result .= "</tr><tr><td colspan=\"2\"><output>" ;   // output tag added (to support removeEventFact) Mar 2021 by Janet Bjorndahl
      $result .= ( $dateStatus === true && $prevDate ? "<b>was</b>: $prevDate" : 
                 ( ($dateStatus === true || $dateStatus === '') ? '' : "<font color=darkred>$dateStatus</font>") ); // changed Mar 2021
      $result .= "</output></td>";  
      if ($efNum == 0) {
//         if ($typeString == 'Birth') {
//            $sourceTip = '<b>Sources'.$tm->addMsgTip('EventFactSourceIDs').'&nbsp; Images'.$tm->addMsgTip('EventFactImageIDs').'&nbsp; Notes'.$tm->addMsgTip('EventFactNoteIDs').'&nbsp; &raquo; &nbsp; &nbsp;</b>';
//            $imageTip = '';
//            $noteTip = '';
//         }
//         else {
            $sourceTip = '<div class="sin_heading">Sources'.$tm->addMsgTip('EventFactSourceIDs').'</div>';
            $imageTip = '<div class="sin_heading">Images'.$tm->addMsgTip('EventFactImageIDs').'&nbsp;</div>';
            $noteTip = '<div class="sin_heading">Notes'.$tm->addMsgTip('EventFactNoteIDs').'&nbsp;&nbsp;</div>';
//         }
      }
      else {
         $sourceTip = $imageTip = $noteTip = '';
      }
      $rowNum = ($efNum*2)+2;
		$result .= "<td class=\"ef_ref\">$sourceTip<a title='Add a source for this event/fact' href=\"#sourcesSection\" onClick=\"addRef('event_fact_input',$rowNum,1,newSource());\">+</a>";
		$result .= "<input tabindex=\"1\" type=\"text\" name=\"sources$efNum\" value=\"$sources\"/></td>";
		$result .= "<td class=\"ef_ref\">$imageTip<a title='Add an image for this event/fact' href=\"#imagesSection\" onClick=\"addRef('event_fact_input',$rowNum,2,newImage());\">+</a>";
		$result .= "<input tabindex=\"1\" type=\"text\" name=\"images$efNum\" value=\"$images\"/></td>";
		$result .= "<td class=\"ef_ref\">$noteTip<a title='Add a note for this event/fact' href=\"#notesSection\" onClick=\"addRef('event_fact_input',$rowNum,3,newNote());\">+</a>";
		$result .= "<input tabindex=\"1\" type=\"text\" name=\"notes$efNum\" value=\"$notes\"/></td>";
		$result .= "<td></td></tr>\n";
		return $result;
	}

	/**
	 * Add input fields for all events/facts
	 *
	 * @param unknown_type $xml
	 */
	public function addEventsFactsInput($xml, $stdEventTypes, $otherEventTypes) {
		$tm = new TipManager();

		$stdEvents = array();
		for ($i = 0; $i < count($stdEventTypes); $i++) {
			$stdEvents[$i] = null;
		}
		$otherEventFacts = null;
		if (isset($xml)) {
			foreach ($xml->event_fact as $eventFact) {
				$found = false;
				for ($i = 0; $i < count($stdEventTypes); $i++) {
					if ($eventFact['type'] == $stdEventTypes[$i]) {
						$stdEvents[$i] = $eventFact;
						$found = true;
						break;
					}
				}
				if (!$found) {
					$otherEventFacts[] = $eventFact;
				}
			}
		}

		$result = '<h2>Events and Facts<small>'.$tm->addMsgTip('EventsFacts', 400).'</small></h2><table id="event_fact_input" border=0 cellpadding=3>' .
		'<tr><th></th><th>Date'.$tm->addMsgTip('EventFactDate').'</th><th>Place'.$tm->addMsgTip('EventFactPlace').'</th><th colspan="2">Description'.$tm->addMsgTip('EventFactDescription').
			'</th><th></th></tr>';
		for ($i = 0; $i < count($stdEventTypes); $i++) {
			$result .= $this->addEventFactInput($i, $stdEvents[$i], $stdEventTypes[$i], $otherEventTypes, $tm);
		}
		if (isset($otherEventFacts)) {
			$i = count($stdEventTypes);
			foreach ($otherEventFacts as $otherEventFact) {
        $eventFactInp = $this->addEventFactInput($i, $otherEventFact, '', $otherEventTypes, $tm);
        if ($eventFactInp <> "") {
				  $result .= $eventFactInp;
				  $i++;
        }
			}
		}
		$result .= '</table><div class="addESINLink"><a href="javascript:void(0);" onClick="addEventFact(\''.implode(',',$otherEventTypes).'\'); return preventDefaultAction(event);">Add event/fact</a></div>';
		$result .= $tm->getTipTexts();
		return $result;
	}

	protected function addSourceInput($srcNum, $source) {
		$id = '';
		$titleString = '';
		$title = null;
		$recordName = '';
		$page = '';
		$quality = '';
		$date = '';
		$images = '';
		$notes = '';
		$text = '';
		$ns = 0;
		$sourceSelected = '';
		$mysourceSelected = '';
		$nosourceSelected = '';
		if (isset($source)) {
			$id = htmlspecialchars((string)$source['id']);
			$titleString = (string)$source['title'];
			$recordName = htmlspecialchars((string)$source['record_name']);
			$page = htmlspecialchars((string)$source['page']);
			$quality = htmlspecialchars((string)$source['quality']);
			if ($quality && @self::$QUALITY_OPTIONS[$quality]) $quality = self::$QUALITY_OPTIONS[$quality]; // convert old alpha form to numeric
			$date = htmlspecialchars((string)$source['date']);
			$images = htmlspecialchars((string)$source['images']);
			$notes = htmlspecialchars((string)$source['notes']);
			$text = htmlspecialchars((string)$source['text']);
			$text .= htmlspecialchars((string)$source); // get from both until we standardize on the latter
		}
		else {
			$id = 'S'.($srcNum+1);
		}
		$titleLower = mb_strtolower($titleString);
		if (mb_strpos($titleLower, 'source:') === 0) {
			$ns = NS_SOURCE;
		   $titleString = mb_substr($titleString, strlen('source:'));
			$sourceSelected = ' checked';
			$autocompleteClass = ' source_input';
			$chooseVisibility = 'visible';
		}
		else if (mb_strpos($titleLower, 'mysource:') === 0) {
			$ns = NS_MYSOURCE;
		   $titleString = mb_substr($titleString, strlen('mysource:'));
			$mysourceSelected = ' checked';
			$autocompleteClass = ' mysource_input';
			$chooseVisibility = 'visible';
		}
		else {
			$ns = 0;
			$autocompleteClass = '';
			$nosourceSelected = ' checked';
			$chooseVisibility = 'hidden';
		}
		$titleString = htmlspecialchars($titleString);
		$rowNum = $srcNum*5;
		$tempNum = $srcNum+1;
		return '<tr>'
			.'<td align="right" style="padding-top:13px"><b>Citation ID</b></td>'
			."<td style=\"padding-top:13px\">$id<input type=\"hidden\" name=\"source_id$srcNum\" value=\"$id\"/>&nbsp;&nbsp;&nbsp;<a title=\"Copy this source\" href=\"javascript:void(0);\" onClick=\"copySource($tempNum); return preventDefaultAction(event);\">copy</a>&nbsp;|&nbsp;<a title=\"Remove this source\" href=\"javascript:void(0);\" onClick=\"removeSource($tempNum); return preventDefaultAction(event);\">remove</a></td>" // copy link added Sep 2020 by Janet Bjorndahl
			.'</tr><tr>'
			.'<td align="right">Source</td><td><span class="s_source">'.StructuredData::addSelectToHtml(1, "source_namespace$srcNum", self::$SOURCE_NAMESPACE_OPTIONS, $ns, 'class="s_select" onChange="changeSourceNamespace('.$srcNum.',\''.$id.'\')"', false).'</span>'
			."<span class=\"s_label\">Title</span><input class=\"s_title$autocompleteClass\" id=\"{$id}input\" tabindex=\"1\" type=\"text\" name=\"source_title$srcNum\" value=\"$titleString\"/>"
			."&nbsp;<span class=\"s_findadd\" style=\"font-size: 90%\"><a id=\"{$id}choose\" style=\"visibility:$chooseVisibility\" href=\"javascript:void(0);\" onClick=\"choose($ns,'{$id}input'); return preventDefaultAction(event);\">find/add&nbsp;&raquo;</a></span></td>"
            ."</tr><tr>"
			."<td align=\"right\">Record&nbsp;name</td><td><input class=\"s_recordname\" tabindex=\"1\" type=\"text\" name=\"record_name$srcNum\" value=\"$recordName\"/>"
			."<span class=\"s_label\">Images&nbsp;<a title='Add an image to this citation' href=\"#imagesSection\" onClick=\"addRefToSrc('source_input',".($rowNum+2).",1,newImage());\">+</a></span>"
			."<input class=\"s_ref s_ref-images\" tabindex=\"1\" type=\"text\" name=\"source_images$srcNum\" value=\"$images\"/>"
			."<span class=\"s_label\">Notes&nbsp;<a title='Add a note to this citation' href=\"#notesSection\" onClick=\"addRefToSrc('source_input',".($rowNum+2).",2,newNote());\">+</a></span>"
			."<input class=\"s_ref s_ref-notes\" tabindex=\"1\" type=\"text\" name=\"source_notes$srcNum\" value=\"$notes\"/></td>"
			."</tr><tr>"
			."<td align=\"right\">Volume / Pages</td><td><input class=\"s_page\" tabindex=\"1\" type=\"text\" name=\"source_page$srcNum\" value=\"$page\"/>"
			."<span class=\"s_widelabel\">Date</span><input class=\"s_date\" tabindex=\"1\" type=\"text\" name=\"source_date$srcNum\" value=\"$date\"/>"
			."</tr><tr>"
			."<td align=\"right\">Text /<br/>Transcription<br/>location</td><td><textarea class=\"s_text\" tabindex=\"1\" name=\"source_text$srcNum\" rows=\"3\">$text</textarea></td>"
			."</tr>";
//		return '<tr>'.
//				"<td align=\"center\">$id<input type=\"hidden\" name=\"source_id$srcNum\" value=\"$id\"/></td>".
//				"<td rowspan=2 style=\"vertical-align: top\"><span style=\"white-space: nowrap; font-size: 90%\">".
//					"<input type=\"radio\" name=\"source_namespace$srcNum\" value=\"".NS_SOURCE."\"$sourceSelected onClick=\"setSourceNs(".NS_SOURCE.",'$id')\">Source<br>".
//					"<input type=\"radio\" name=\"source_namespace$srcNum\" value=\"".NS_MYSOURCE."\"$mysourceSelected onClick=\"setSourceNs(".NS_MYSOURCE.",'$id')\">MySource<br>".
//					"<input type=\"radio\" name=\"source_namespace$srcNum\" value=\"0\"$nosourceSelected onClick=\"setSourceNs(0,'$id')\">Title only".
//					"</span></td>".
//				"<td><input id=\"{$id}input\"$autocompleteClass tabindex=\"1\" type=\"text\" size=30 name=\"source_title$srcNum\" value=\"$titleString\"/></td>".
//				"<td><a id=\"{$id}choose\" style=\"display:$chooseDisplay\" href=\"javascript:void(0);\" onClick=\"choose($ns,'{$id}input'); return preventDefaultAction(event);\">choose&nbsp;&raquo;</a>&nbsp;&nbsp;</td>".
//				"<td><input tabindex=\"1\" type=\"text\" size=5 name=\"source_page$srcNum\" value=\"$page\"/></td>".
//				"<td><input tabindex=\"1\" type=\"text\" size=5 name=\"source_quality$srcNum\" value=\"$quality\"/></td>".
//				"<td><input tabindex=\"1\" type=\"text\" size=11 name=\"source_date$srcNum\" value=\"$date\"/></td>".
//				"<td><a title='Add an image for this source' href=\"javascript:void(0);\" onClick=\"addRef('source_input',$rowNum,6,newImage()); return preventDefaultAction(event);\">+</a></td>".
//				"<td><input tabindex=\"1\" type=\"text\" size=8 name=\"source_images$srcNum\" value=\"$images\"/></td>".
//				"<td><a title='Add a note for this source' href=\"javascript:void(0);\" onClick=\"addRef('source_input',$rowNum,8,newNote()); return preventDefaultAction(event);\">+</a></td>".
//				"<td><input tabindex=\"1\" type=\"text\" size=8 name=\"source_notes$srcNum\" value=\"$notes\"/></td>".
//				"<td><a title='Remove this source' href=\"javascript:void(0);\" onClick=\"removeSource($tempNum); return preventDefaultAction(event);\">remove</a></td>".
//				"</tr><tr><td></td><td>Record Name:<input tabindex=\"1\" type=\"text\" size=20 name=\"record_name$srcNum\" value=\"$recordName\"/></td>".
//				'<td align="right">Text /<br/>Where&nbsp;found:</td>'.
//				"<td colspan=7><textarea tabindex=\"1\" name=\"source_text$srcNum\" rows=\"3\" cols=\"65\">$text</textarea></td>".
//				'</tr>';
	}

	protected function addImageInput($imgNum, $image) {
		$filename = '';
		$caption = '';
		$checked = '';
		if (isset($image)) {
			$id = htmlspecialchars((string)$image['id']);
			$filename = htmlspecialchars((string)$image['filename']);
			$caption = htmlspecialchars((string)$image['caption']);
			if ((string)$image['primary']) {
				$checked = ' checked="checked"';
			}
		}
		else {
			$id = 'I'.($imgNum+1);
		}
		$tempNum = $imgNum+1;
		$ns = NS_IMAGE;
		$result = '<tr>' .
						"<td align=\"center\">$id<input type=\"hidden\" name=\"image_id$imgNum\" value=\"$id\"/></td>" .
						"<td align=\"center\"><input tabindex=\"1\" type=\"checkbox\"$checked name=\"image_primary$imgNum\"/></td>" .
						"<td><input id=\"{$id}input\" class=\"image_input\" tabindex=\"1\" type=\"text\" size=35 name=\"image_filename$imgNum\" value=\"$filename\"/></td>" .
						"<td><span style=\"font-size: 90%\"><a href=\"javascript:void(0);\" onClick=\"choose($ns,'{$id}input'); return preventDefaultAction(event);\">find&nbsp;&raquo;</a>&nbsp;<br>" .
							"<a href=\"javascript:void(0);\" onClick=\"uploadImage('{$id}input'); return preventDefaultAction(event);\">add&nbsp;&raquo;</a>&nbsp;</span></td>" .
						"<td><input tabindex=\"1\" type=\"text\" size=30 name=\"image_caption$imgNum\" value=\"$caption\"/></td>" .
						"<td><a title='Remove this image' href=\"javascript:void(0);\" onClick=\"removeImage($tempNum); return preventDefaultAction(event);\">remove</a></td>" .
						'</tr>';
		return $result;
	}

	protected function addNoteInput($noteNum, $note) {
		$id = '';
		$text = '';
		if (isset($note)) {
			$id = htmlspecialchars((string)$note['id']);
			$text = htmlspecialchars((string)$note['text']);
			$text .= htmlspecialchars((string)$note); // get from both locations until all attr-style notes have been converted over (could be awhile)
		}
		$result = '<tr>';
		$result .= "<td align=\"center\">$id<input type=\"hidden\" name=\"note_id$noteNum\" value=\"$id\"/></td>";
		$result .= "<td><textarea tabindex=\"1\" name=\"note_text$noteNum\" rows=\"3\" cols=\"85\">$text</textarea></td>";
		$tempNum = $noteNum+1;
		$result .= "<td><a title='Remove this note' href=\"javascript:void(0);\" onClick=\"removeNote($tempNum); return preventDefaultAction(event);\">remove</a></td>";
		$result .= '</tr>';
		return $result;
	}

	public function addSourcesImagesNotesInput($xml) {
		$tm = new TipManager();

		$sources = null;
		$notes = null;
		$images = null;
		if (isset($xml)) {
			$sources = $xml->source_citation;
			$images = $xml->image;
			$notes = $xml->note;
		}

		// add source input
		$rows = '';
		if (isset($sources)) {
			$sortedSources = array();
			$maxId = 0;
			// sort sources, display holes caused by blanking out an entire source record
			foreach ($sources as $source) {
				$id = (int)substr((string)$source['id'], 1);
				$sortedSources[$id] = $source;
				if ($maxId < $id) {
					$maxId = $id;
				}
			}
			for ($i = 1; $i <= $maxId; $i++) {
				$rows .= $this->addSourceInput($i - 1, @$sortedSources[$i]);
			}
		}
		$result = "<a name=\"sourcesSection\"></a><h2>Source Citations<small>".$tm->addMsgTip('SourceCitations', 400)."</small></h2><table id=\"source_input\" border=0>" .
//		'<tr><th>ID</th><th>Type'.$tm->addMsgTip('CitationType').'</th><th>Title'.$tm->addMsgTip('SourceTitle').'</th><th></th><th>Page'.$tm->addMsgTip('SourcePage').'</th><th>Quality'.$tm->addMsgTip('SourceQuality').
//			'</th><th>Date'.$tm->addMsgTip('SourceDate').'</th><th colspan=2>Image&nbsp;ID(s)'.$tm->addMsgTip('SourceImageIDs').'</th><th colspan=2>Note&nbsp;ID(s)'.$tm->addMsgTip('SourceNoteIDs').'</th></tr>' .
			$rows .
			'</table><div class="addESINLink"><a href="javascript:void(0);" onClick="addSource(); return preventDefaultAction(event);">Add source citation</a></div>';
		// add image input
		$display = 'none';
		$rows = '';
		if (isset($images)) {
			// sort images, display holes caused by image deletion
			$sortedImages = array();
			$maxId = 0;
			foreach ($images as $image) {
				$id = (int)substr((string)$image['id'], 1);
				if ($maxId < $id) {
					$maxId = $id;
				}
			}
			foreach ($images as $image) {
				$id = (int)substr((string)$image['id'], 1);
				if (isset($sortedImages[$id])) { // caused by a bug, move the image to the end rather than lose it
					$maxId += 1;
					$id = $maxId;
					$image['id'] = 'I' . $id;
				}
				$sortedImages[$id] = $image;
			}
			for ($i = 1; $i <= $maxId; $i++) {
				$display = 'block';
				$rows .= $this->addImageInput($i - 1, @$sortedImages[$i]);
			}
		}
		$result .= "<a name=\"imagesSection\"></a><h2>Images</h2><table id=\"image_input\" border=0 style=\"display:$display\">" .
		'<tr><th>ID</th><th>Primary'.$tm->addMsgTip('ImagePrimary').'</th><th>Title'.$tm->addMsgTip('ImageFilename').'</th><th></th><th>Caption'.$tm->addMsgTip('ImageCaption').'</th></tr>' .
			$rows .
			'</table><div class="addESINLink"><a href="javascript:void(0);" onClick="addImage(); return preventDefaultAction(event);">Add image</a></div>';
		// add note input
		$display = 'none';
		$rows = '';
		if (isset($notes)) {
			$i=0;
			foreach ($notes as $note) {
				$display = 'block';
				$rows .= $this->addNoteInput($i, $note);
				$i++;
			}
		}
		$result .= "<a name=\"notesSection\"></a><h2>Notes</h2><table id=\"note_input\" border=0 width=\"670px\" style=\"display:$display\">" .
		'<tr><th>ID</th><th width="90%">Text'.$tm->addMsgTip('NoteText').'</th></tr>' . $rows . '</table><div class="addESINLink"><a href="javascript:void(0);" onClick="addNote(); return preventDefaultAction(event);">Add note</a></div>';
		$result .= $tm->getTipTexts();
		return $result;
	}

	protected function fromEvent($request, $type, $num, $correctedPlaceTitles) {
    $formatedDate='';
    $languageDate='';
		$result = '';
   
		$date = $request->getVal("date$num");
    // Format the date. If successful and no significant reformating was required, replace the date with the formated date. Added Nov 2020; Changed Mar 2021, Apr 2024 by Janet Bjorndahl
    $dateStatus = DateHandler::editDate($date, $formatedDate, $languageDate, $type, true);
    if ( $dateStatus === true ) {
      $date = $formatedDate;
    }

    $place = $request->getVal("place$num");
		$correctedPlace = @$correctedPlaceTitles[$place];
		if ($correctedPlace) {
      $place = $correctedPlace;
//			$place = strcasecmp($place,$correctedPlace) == 0 ? $correctedPlace : $correctedPlace . '|' . $place;
		}
		$desc = $request->getVal("desc$num");
		$sources = $this->mapSIN($request->getVal("sources$num"));
		$images = $this->mapSIN($request->getVal("images$num"));
		$notes = $this->mapSIN($request->getVal("notes$num"));
		if (!StructuredData::isEmpty($date) ||
		!StructuredData::isEmpty($place) ||
		!StructuredData::isEmpty($desc) ||
		!StructuredData::isEmpty($sources) ||
		!StructuredData::isEmpty($images) ||
		!StructuredData::isEmpty($notes)) {
			$result = $this->addMultiAttrFieldToXml(array('type' => $type,
			'date' => $date,
			'place' => $place,
			'desc' => $desc,
			'sources' => $sources,
			'images' => $images,
			'notes' => $notes),
			'event_fact');
		}
		return $result;
	}
	
	public static function addEventToRequestData(&$requestData, $i, $type, $date, $place, $description, $sources, $images, $notes) {
		$requestData["event_fact$i"] = $type;
		$requestData["date$i"] = $date;
		$requestData["place$i"] = $place;
		$requestData["desc$i"] = $description;
		$requestData["sources$i"] = $sources;
		$requestData["images$i"] = $images;
		$requestData["notes$i"] = $notes;
	}

	private static function correctPlaceTitles($request, $stdEventFacts) {
		$titles = array(); 
		for ($i = 0; $i < count($stdEventFacts) || $request->getVal("event_fact$i"); $i++) {
			$place = $request->getVal("place$i");
      if ($place) {
//			if ($place && mb_strpos($place, '|') === false) {    // Don't exclude places with display names (changed Aug 2025 by Janet Bjorndahl)
				$titles[] = $place;
			}
		}
    return PlaceSearcher::correctPlaceTitles($titles);
	}

	private static function correctSourceTitle($titleString, $ns) {
		global $wgUser;
		
   	$barPos = mb_strpos($titleString, '|');
   	if ($barPos !== false) {
   		$barText = mb_substr($titleString, $barPos);
   		$titleString = mb_substr($titleString, 0, $barPos);
   	}
   	else {
   		$barText = '';
   	}
   	$title = Title::newFromText($titleString, $ns);
	   if (!$title) {
	   	wfDebug("correctSourceTitle empty title=$titleString barText=$barText ns=$ns\n");
	   }
	   if ($title && $title->getNamespace() == NS_MYSOURCE && mb_strpos($title->getText(), $wgUser->getName().'/') !== 0 && !$title->exists()) {
	   	$prefix = '';
	   	$pos = mb_strpos($title->getText(), '/');
	   	if ($pos === false) {
   			$prefix = $wgUser->getName() . '/';
	   	}
	   	else if ($pos == 0) {
   			$prefix = $wgUser->getName();
   		}
   		else if (!User::idFromName(mb_substr($title->getText(), 0, $pos))) {
   			$prefix = $wgUser->getName() . '/';
   		}
	   	$title = Title::newFromText($prefix.$title->getText(), NS_MYSOURCE);
	   }
      return ($title ? $title->getPrefixedText() : $titleString) . $barText;
	}

	public function fromEventsFacts($request, $stdEventFacts) {
		$result = '';
		$correctedPlaceTitles = ESINHandler::correctPlaceTitles($request, $stdEventFacts);

		$usedEvents = array();
		for ($i = 0; $i < count($stdEventFacts); $i++) {
			$id = $stdEventFacts[$i];
			$event = $this->fromEvent($request, $id, $i, $correctedPlaceTitles);
			if (!$event) {
				for ($j = count($stdEventFacts); $request->getVal("event_fact$j"); $j++) {
					if ($request->getVal("event_fact$j") == ('Alt '.$id)) {
						$event = $this->fromEvent($request, $id, $j, $correctedPlaceTitles);
						$usedEvents[$j] = true;
					}
				}
			}
			if ($event) {
				$result .= $event;
			}
		}
		for ($i = count($stdEventFacts); $request->getVal("event_fact$i"); $i++) {
			if (!@$usedEvents[$i]) {
				$type = $request->getVal("event_fact$i");
				$result .= $this->fromEvent($request, $type, $i, $correctedPlaceTitles);
			}
		}

		return $result;
	}
	
	// given an assoc array, turn it into an image element
	private function formatImageElement($image) {
		return $this->addMultiAttrFieldToXml($image, 'image');
	}
	
	// call this function if you've generated your own SIN map
	public static function mapSourcesImagesNotes(&$map, $refs) {
   	$refs = explode(',', $refs);
   	$result = array();
   	foreach ($refs as $ref) {
   		$ref = strtoupper(trim($ref));
   		if ($ref) {
	   		$ref = @$map[$ref];
	   		if ($ref) {
	   			if (!in_array($ref, $result)) {
		   			$result[] = $ref;
	   			}
	   		}
   		}
   	}
   	return join(', ',$result);
   }

   // call this function to map sources, images, notes generated by generateSINMap
	public function mapSIN($refs) {
		return ESINHandler::mapSourcesImagesNotes($this->sinMap, $refs);
	}

	public function clearSINMap() {
		$this->sources = array();
		$this->images = array();
		$this->notes = array();
		$this->sinMap = array();
	}
	
	public function generateSINMap($request) {
		$this->clearSINMap();

		for ($i = 0; $request->getVal("source_id$i"); $i++) {
			$id = $request->getVal("source_id$i");
			$namespace = $request->getVal("source_namespace$i");			
			$title = urldecode($request->getVal("source_title$i"));
			$title = ESINHandler::correctSourceTitle($title, $namespace);
			$recordName = $request->getVal("record_name$i");
			$page = $request->getVal("source_page$i");
			$quality = $request->getVal("source_quality$i");
			$date = $request->getVal("source_date$i");
			$notes = $request->getVal("source_notes$i");
			$images = $request->getVal("source_images$i");
			$text = trim($request->getVal("source_text$i"));
			if (!StructuredData::isEmpty($title) ||
			!StructuredData::isEmpty($recordName) ||
			!StructuredData::isEmpty($page) ||
			!StructuredData::isEmpty($quality) ||
			!StructuredData::isEmpty($date) ||
			!StructuredData::isEmpty($notes) ||
			!StructuredData::isEmpty($images) ||
			!StructuredData::isEmpty($text)) {
				$found = false;
				foreach($this->sources as &$src) {
					if ($title == $src['title'] && $recordName == $src['record_name'] && $page == $src['page'] && $quality == $src['quality'] &&
						 $date == $src['date'] && $notes == $src['notes'] && $images == $src['images'] && $text == $src['source_citation']) {
						$this->sinMap[$id] = $src['id'];
						$found = true;
						break;
					}
				}
				if (!$found) {
					$newId = 'S' . (count($this->sources) + 1);
					$this->sinMap[$id] = $newId;
					$this->sources[] = array('id' => $newId, 'title' => $title, 'record_name' => $recordName, 'page' => $page, 'quality' => $quality,
													 'date' => $date, 'notes' => $notes, 'images' => $images, 'source_citation' => $text);
				}
			}
		}
		$foundPrimary = false;
		for ($i = 0; $request->getVal("image_id$i"); $i++) {
			$id = $request->getVal("image_id$i");
			$filename = str_replace('_', ' ', urldecode($request->getVal("image_filename$i")));
			$caption = $request->getVal("image_caption$i");
			$primary = $request->getVal("image_primary$i");
			if (!StructuredData::isEmpty($filename)) {
				if ($primary) {
					if ($foundPrimary) {
						$primary = '';
					}
					else {
						$primary = 'true';
						$foundPrimary = true;
					}
				}
				$found = false;
				foreach($this->images as &$img) {
					if ($filename == $img['filename'] && $caption == $img['caption'] && $primary == $img['primary']) {
						$this->sinMap[$id] = $img['id'];
						$found = true;
						break;
					}
				}
				if (!$found) {
					$newId = 'I' . (count($this->images) + 1);
					$this->sinMap[$id] = $newId;
					$this->images[] = array('id' => $newId, 'filename' => $filename, 'caption' => $caption, 'primary' => $primary);
				}
			}
		}
		for ($i = 0; $request->getVal("note_id$i"); $i++) {
			$id = $request->getVal("note_id$i");
			$text = trim($request->getVal("note_text$i"));
			if (!StructuredData::isEmpty($text)) {
				$found = false;
				foreach($this->notes as &$note) {
					if ($text == $note['note']) {
						$this->sinMap[$id] = $note['id'];
						$found = true;
						break;
					}
				}
				if (!$found) {
					$newId = 'N' . (count($this->notes) + 1);
					$this->sinMap[$id] = $newId;
					$this->notes[] = array('id' => $newId, 'note' => $text);
				}
			}
		}
	}
	
	// you must call generateSINMap before calling this function
	public function fromSourcesImagesNotes($request) {
		$result = '';

		foreach ($this->sources as $src) {
			$text = $src['source_citation'];
			unset($src['source_citation']);
			$src['notes'] = $this->mapSIN($src['notes']);
			$src['images'] = $this->mapSIN($src['images']);
			$result .= $this->addMultiAttrFieldToXml($src, 'source_citation', $text);
		}
		$seenImages = array();
		foreach ($this->images as $img) {
			// need to standardize image filename
			$t = Title::newFromText((string)$img['filename'], NS_IMAGE);
			$filename = ($t ? $t->getText() : '');
			if ($filename && !in_array($filename, $seenImages)) {
				$seenImages[] = $filename;
				$img['filename'] = $filename;
				$result .= $this->formatImageElement($img);
			}
		}
		foreach ($this->notes as $note) {
			$text = $note['note'];
			unset($note['note']);
			$result .= $this->addMultiAttrFieldToXml($note, 'note', $text);
		}

		return $result;
	}
	
	public static function addSourceToRequestData(&$requestData, $i, $id, $namespace, $title, $recordName, $page, $quality, $date, $notes, $images, $text) {
		$requestData["source_id$i"] = $id;
		$requestData["source_namespace$i"] = $namespace;
		$requestData["source_title$i"] = $title;
		$requestData["record_name$i"] = $recordName;
		$requestData["source_page$i"] = $page;
		$requestData["source_quality$i"] = $quality;
		$requestData["source_date$i"] = $date;
		$requestData["source_notes$i"] = $notes;
		$requestData["source_images$i"] = $images;
		$requestData["source_text$i"] = $text;
	}
	
	public static function addImageToRequestData(&$requestData, $i, $id, $filename, $caption, $primary) {
		$requestData["image_id$i"] = $id;
		$requestData["image_filename$i"] = $filename;
		$requestData["image_caption$i"] = $caption;
		if ($primary) $requestData["image_primary$i"] = 'true';
	}
	
	public static function addNoteToRequestData(&$requestData, $i, $id, $text) {
		$requestData["note_id$i"] = $id;
		$requestData["note_text$i"] = $text;
	}

	private function updateImageContent($tag, $oldTitle, $newTitle, &$pd, &$text) {
		if ($newTitle) {
			if ($tag == 'person') {
				$new = Person::getPropagatedElement($tag, $newTitle, $pd);
			}
			else { // family
				$new = Family::getPropagatedElement($tag, $newTitle, $pd);
			}
		}
		else {
			$new = '';
		}
		$old = "<{$tag}[^>]*? title=\"" . StructuredData::protectRegexSearch(StructuredData::escapeXml($oldTitle)) . "\".*?/>\n";
		if (!preg_match('$'.$old.'$', $text)) {
		   $old = "</image_data>";
			$new .= $old;
		}

		$result = preg_replace('$'.$old.'$', StructuredData::protectRegexReplace($new), $text);

		// if nothing changed, return empty string
		if ($result == $text) {
			$result = '';
		}
		return $result;
	}

	private function updateImage($thisTitle, $imageTitle, $tag, $oldTitle, $newTitle, &$propagatedData, &$text, &$textChanged) {
	   if (!PropagationManager::isPropagatablePage($imageTitle)) {
	      return true;
	   }

	   $result = true;
		$article = StructuredData::getArticle($imageTitle, true);
		if ($article) {
			$content =& $article->fetchContent(); // fetches from master
			$updatedContent =& $this->updateImageContent($tag, $oldTitle, $newTitle, $propagatedData, $content);
			if ($updatedContent) {
				$result = $article->doEdit($updatedContent, self::PROPAGATE_MESSAGE.' [['.$thisTitle->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
			}
			else {
			   error_log("updateImage propagating nothing changed: " . $imageTitle->getText());
			}

			// if we're not deleting this entry (newTitle is not empty), and the image being updated is a redirect (article title != pageToUpdate),
			// we need to update the page title in this page text
			if ($newTitle && $imageTitle->getText() != $article->getTitle()->getText()) {
				$old = ' filename="' . StructuredData::escapeXml($imageTitle->getText()) . '"';
				$new = ' filename="' . StructuredData::escapeXml($article->getTitle()->getText()) . '"';
				$text = str_replace($old, $new, $text);
				$textChanged = true;
			}
		}
		return $result;
	}
	
	private function getImageFilenames($images) {
		$filenames = array();
		foreach ($images as $i) {
			$filenames[] = (string)$i['filename'];
		}
		return $filenames;
	}

	public function propagateSINEdit($thisTitle, $tag, $titleString, &$propagatedData, &$origPropagatedData, &$text, &$textChanged) {
		$result = true;
		$currFilenames = $this->getImageFilenames($propagatedData['images']);
		$origFilenames = $this->getImageFilenames($origPropagatedData['images']);
		$addImages = array_diff($currFilenames, $origFilenames);
		$delImages = array_diff($origFilenames, $currFilenames);
		$sameImages = array_intersect($currFilenames, $origFilenames);

		// remove backlink from deleted images
		$dummyPropagatedData = array();
		foreach ($delImages as $i) {
			$imageTitle = Title::newFromText($i, NS_IMAGE);
			PropagationManager::addPropagatedAction($thisTitle, 'delimage', $imageTitle);
			if (PropagationManager::isPropagatableAction($imageTitle, 'dellink', $thisTitle)) {
				$result = $result && $this->updateImage($thisTitle, $imageTitle, $tag, $titleString, null, $dummyPropagatedData, $text, $textChanged);
			}
		}

		// add backlink to new images
		foreach ($addImages as $i) {
			$imageTitle = Title::newFromText($i, NS_IMAGE);
			PropagationManager::addPropagatedAction($thisTitle, 'addimage', $imageTitle);
			if (PropagationManager::isPropagatableAction($imageTitle, 'addlink', $thisTitle)) {
				$result = $result && $this->updateImage($thisTitle, $imageTitle, $tag, $titleString, $titleString, $propagatedData, $text, $textChanged);
			}
		}

		// update data on same images
		if ($tag == 'person' && Person::propagatedFieldsChanged($propagatedData, $origPropagatedData)) {
			foreach ($sameImages as $i) {
				$imageTitle = Title::newFromText($i, NS_IMAGE);
				$result = $result && $this->updateImage($thisTitle, $imageTitle, $tag, $titleString, $titleString, $propagatedData, $text, $textChanged);
			}
		}

		return $result;
	}
	
	public function getImageData($wlhImages, $thisImages) {
		$images = array();
		$maxId = 0;
      foreach ($thisImages as $i) {
			$filename = (string)$i['filename'];
         $images[$filename] = array('id' => (string)$i['id'], 'filename' => (string)$i['filename'], 'caption' => (string)$i['caption'], 'primary' => (string)$i['primary']);
         $id = (int)substr($i['id'],1);
         if ($id > $maxId) {
         	$maxId = $id;
         }
      }
      foreach ($wlhImages as $i) {
      	$filename = (string)$i['filename'];
      	if (!@$images[$filename]) {
      		$maxId++;
      		$images[$filename] = array('id' => 'I'+$maxId, 'filename' => $filename);
      	}
      }
      $result = '';
      foreach ($images as $filename => $i) {
         $result .= $this->formatImageElement($i);
      }
	   return $result;
	}
	
	public function propagateSINMoveDeleteUndelete($thisTitle, $tag, $oldTitleString, $newTitleString, &$propagatedData, &$text, &$textChanged) {
		$result = true;
		$newTitle = ($newTitleString ? Title::newFromText($newTitleString, $tag == 'person' ? NS_PERSON : NS_FAMILY) : null);

		foreach ($propagatedData['images'] as $i) {
		   $imageTitle = Title::newFromText((string)$i['filename'], NS_IMAGE);
			PropagationManager::addPropagatedAction($thisTitle, 'delimage', $imageTitle);
			if ($newTitle) PropagationManager::addPropagatedAction($newTitle, 'addimage', $imageTitle);
			// don't need to check propagated action before calling updateImage, because propagateSINMoveDeleteUndelete is never part of a loop
  			$result = $result && $this->updateImage($thisTitle, $imageTitle, $tag, $oldTitleString, $newTitleString, $propagatedData, $text, $textChanged);
		}
		return $result;
	}
	
	public static function getLastImageId(&$text) {
		$maxId = 0;
		if (preg_match_all('/<image +id="I(\\d+)" /', $text, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				if ($maxId < (int)$match[1]) {
					$maxId = (int)$match[1];
				}
			}
		}
		return $maxId;
	}

	public static function updateImageLink($tag, $oldTitle, $newTitle, $caption, &$text, &$textChanged) {
		// TODO if you allow renaming images you must find a way to preserve caption and primary attributes here
		$old = "<image id=\"I(\\d+)\" filename=\"" . StructuredData::protectRegexSearch(StructuredData::escapeXml($oldTitle)) . "\".*?/>\n";
		$matches = array();
		$id = 0;
		$oldFound = false;
		if (preg_match('$'.$old.'$', $text, $matches)) {
			$id = (int)$matches[1];
			$oldFound = true;
		}
		else {
			$old = "</$tag>";
		}
		if ($newTitle) {
			$newTitle = StructuredData::escapeXml($newTitle);
			// get the last image number in the text
			if ($id == 0) {
				$id = ESINHandler::getLastImageId($text) + 1;
			}
			if ($caption) {
				$caption = " caption=\"$caption\"";
			}
			$new = "<image id=\"I$id\" filename=\"$newTitle\"$caption/>\n";
		}
		else {
			$new = '';
		}
		if (!$oldFound) {
			$new .= $old;
		}

		$result = preg_replace('$'.$old.'$', StructuredData::protectRegexReplace($new), $text);
		if ($result != $text) {
			$text = $result;
			$textChanged = true;
		}
	}
}
?>
