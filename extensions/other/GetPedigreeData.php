<?php

/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');
require_once("$IP/extensions/other/PlaceSearcher.php");

# Register with AjaxDispatcher as a function
$wgAjaxExportList[] = "wfGetPedigreeData";

function wfGetPedigreeData($titleText = null) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	if ($titleText) {
		$title = Title::newFromText($titleText);
	}
	else {
		$title = null;
	}
	$return = <<< END
{
"types": {
	"Person" : { "pluralLabel": "People" }
},
"properties": {
	"BirthYear": { "valueType": "number" },
	"DeathYear": { "valueType": "number" },
	"MarriageYear": { "valueType": "number" },
	"Parents": { "valueType": "item" },
	"Spouse": { "valueType": "item" },
	"Husband": { "valueType": "item" },
	"Wife": { "valueType": "item" },
	"Child": { "valueType": "item" },
	"OtherEvents": { "valueType": "item" }
},
"items" : [

END;
	
	if (!is_null($title) && ($title->getNamespace() == NS_PERSON || $title->getNamespace() == NS_FAMILY) && $title->exists()) {
		$pd = new PedigreeData($title);
		$return .= $pd->getItems();
	}
	$return .= <<< END

]
}
END;
	return $return;
}

class PedigreeData {
	const MAX_FAMILIES = 15;
	const ICON_WIDTH = 48;
	protected static $LINES = array('Immediate Family', 'Immediate Family', 'Father', 'Mother', 
												array('Father', "Father's Father"), array('Father', "Father's Mother"), array('Mother', "Mother's Father"), array('Mother', "Mother's Mother"), 
												array('Father', "Father's Father"), array('Father', "Father's Father"), array('Father', "Father's Mother"), array('Father', "Father's Mother"), 
												array('Mother', "Mother's Father"), array('Mother', "Mother's Father"), array('Mother', "Mother's Mother"), array('Mother', "Mother's Mother"), 
												array('Father', "Father's Father"), array('Father', "Father's Father"), array('Father', "Father's Father"), array('Father', "Father's Father"), 
												array('Father', "Father's Mother"), array('Father', "Father's Mother"), array('Father', "Father's Mother"), array('Father', "Father's Mother"), 
												array('Mother', "Mother's Father"), array('Mother', "Mother's Father"), array('Mother', "Mother's Father"), array('Mother', "Mother's Father"), 
												array('Mother', "Mother's Mother"), array('Mother', "Mother's Mother"), array('Mother', "Mother's Mother"), array('Mother', "Mother's Mother")
												);
	protected static $GENERATIONS = array('0 (child)', '1st', '2nd', '2nd', '3rd', '3rd', '3rd', '3rd', '4th', '4th', '4th', '4th', '4th', '4th', '4th', '4th',
													'5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th', '5th');
	private $title;
	private $dbr;
	private $skin;
	private $people;
	private $families;
	private $externals;
	private $places;
	private $otherEvents;
	private $latlngs;

	public static function outJSON($label, $value, $addComma = true) {
		$result = '';
		if (is_array($value)) {
			if (count($value) > 1) {
				$result = '[';
			}
			for ($i = 0; $i < count($value); $i++) {
				if ($i > 0) {
					$result .= ',';
				}
				$result .= '"'.str_replace('"',"\\\"",trim($value[$i])).'"';
			}
			if (count($value) > 1) {
				$result .= ']';
			}
		}
		else if ($value) {
			$result = '"'.str_replace('"',"\\\"",trim($value)).'"';
		}
		if ($result) {
			return "\"$label\": $result".($addComma ? ',' : '')."\n";
		}
		else {
			return '';
		}
	}
	
	public function __construct($title) {
		global $wgUser;

		$this->title = $title;
		$this->dbr =& wfGetDb(DB_SLAVE);
		$this->skin = $wgUser->getSkin();

		$this->families = array();
		$this->people = array();
		$this->loadPedigree();
	}

	private function loadFamily($titleText, $position, $loadChildren = false) {
		$xml = null;
		if ($position <= PedigreeData::MAX_FAMILIES) {
			$title = Title::newFromText($titleText, NS_FAMILY);
			$revision = Revision::loadFromTitle($this->dbr, $title); // use load instead of new because I don't want to ever access DB_MASTER
			if ($revision) {
				$xml = StructuredData::getXml('family', $revision->getText());
				if ($xml) {
					if (isset($xml->husband)) {
						foreach($xml->husband as $husband) {
							$titleText = (string)$husband['title'];
							if ($titleText != $this->title->getText()) {
								$this->people[$titleText] = array('position' => ($position == 0 ? 'Spouse' : 'P'.$position*2), 'xml' => $this->loadPerson($titleText));
							}
							$titleText = (string)$husband['child_of_family'];
							if ($titleText && $position > 0) {
								$this->families[$titleText] = array('position' => 'F'.$position*2, 'xml' => $this->loadFamily($titleText, $position*2));
							}
							break; // first husband only
						}
					}
					if (isset($xml->wife)) {
						foreach($xml->wife as $wife) {
							$titleText = (string)$wife['title'];
							if ($titleText != $this->title->getText()) {
								$this->people[$titleText] = array('position' => ($position == 0 ? 'Spouse' : 'P'.($position*2+1)), 'xml' => $this->loadPerson($titleText));
							}
							$titleText = (string)$wife['child_of_family'];
							if ($titleText && $position > 0) {
								$this->families[$titleText] = array('position' => 'F'.$position*2+1, 'xml' => $this->loadFamily($titleText, $position*2+1));
							}
							break; // first wife only
						}
					}
					if ($loadChildren && isset($xml->child)) {
						foreach($xml->child as $child) {
							$titleText = (string)$child['title'];
							$this->people[$titleText] = array('position' => 'Child', 'xml' => $this->loadPerson($titleText));
						}
					}
				}
			}
		}
		return $xml;
	}
	
	private function loadPerson($titleText) {
		$xml = null;
		$title = Title::newFromText($titleText, NS_PERSON);
		$revision = Revision::loadFromTitle($this->dbr, $title); // use load instead of new because I don't want to ever access DB_MASTER
		if ($revision) {
			$xml = StructuredData::getXml('person', $revision->getText());
		}
		return $xml;
	}

	private function loadPedigree() {
		$titleText = $this->title->getText();
		// read the person
		if ($this->title->getNamespace() == NS_PERSON) {
			$xml = $this->loadPerson($titleText);
			$this->people[$titleText] = array('position' => 'P1', 'xml' => $xml);
			if ($xml) {
				foreach ($xml->spouse_of_family as $spouseFamily) {
					$titleText = (string)$spouseFamily['title'];
					$this->families[$titleText] = array('position' => 'F0', 'xml' => $this->loadFamily($titleText, 0, true));
				}
				foreach ($xml->child_of_family as $parentFamily) {
					$titleText = (string)$parentFamily['title'];
					$this->families[$titleText] = array('position' => 'F1', 'xml' => $this->loadFamily($titleText, 1));
					break; // load just one parent family
				}
			}
		}
		// read the family
		else {
			$this->families[$titleText] = array('position' => 'F1', 'xml' => $this->loadFamily($titleText, 1, true));
		}
	}
	
	private function getEventPlace($event_fact) {
		$eventPlace = $eventPlaceText = '';
		$place = (string)$event_fact['place'];
		if ($place) {
			$pos = mb_strpos($place, '|');
			if ($pos !== false) {
				$eventPlace = 'Place:'.mb_substr($place, 0, $pos);
				$eventPlaceText = mb_substr($place, $pos+1);
			}
			else {
				$eventPlace = 'Place:'.$place;
				$eventPlaceText = $place;
			}
		}
		$latlng = array();
		if ((string)$event_fact['desc'] && preg_match('/{{googlemap\|([0-9.-]+)\|([0-9.-]+)/', (string)$event_fact['desc'], $latlng)) {
			$eventPlace = 'LatLng:'.$latlng[1].','.$latlng[2];
			if (!$eventPlaceText) {
				$eventPlaceText = $latlng[1].','.$latlng[2];
			}
		}
		return array($eventPlace, $eventPlaceText);
	}
	
	private function getEventData($xml, $eventTypes) {
		foreach ($eventTypes as $type) {
			foreach ($xml->event_fact as $event_fact) {
				if ((string)$event_fact['type'] == $type) {
					$eventDate = (string)$event_fact['date'];
          $eventDate = DateHandler::formatDate($eventDate, $type);            // changed parameter to eventType Apr 2024 by Janet Bjorndahl
					$eventYear = substr(DateHandler::getDateKey($eventDate), 0, 4);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl
					list ($eventPlace, $eventPlaceText) = $this->getEventPlace($event_fact);
					return array($type, $eventDate, $eventPlace, $eventPlaceText, $eventYear);
				}
			}
		}
		return array('','','','','');
	}

	private function getOtherEventData($xml, $ignoreEventTypes) {
		$result = array();
		foreach ($xml->event_fact as $event_fact) {
			$type = (string)$event_fact['type'];
			if (!in_array($type, $ignoreEventTypes)) {
				$eventDate = (string)$event_fact['date'];
        $eventDate = DateHandler::formatDate($eventDate, $type);             // changed parameter to eventType Apr 2024 by Janet Bjorndahl
				list ($eventPlace, $eventPlaceText) = $this->getEventPlace($event_fact);
				$result[] = 'Event:'.(count($this->otherEvents)+1);
				$this->otherEvents[] = array('type' => $type, 'date' => $eventDate, 'place' => $eventPlace, 'placetext' => $eventPlaceText);
			}
		}
		return $result;
	}
	
	private function getTitles($xml, $type, $nsText) {
		$result = array();
		foreach ($xml->$type as $node) {
			$title = (string)$node['title'];
			if ($title) {
				$result[] = $nsText.':'.$title;
			}
		}
		return $result;
	}
	
	private function getAllEventPlaces($xml) {
		$events = array();
		foreach ($xml->event_fact as $event_fact) {
			list ($eventPlace, $eventPlaceText) = $this->getEventPlace($event_fact);
			$eventDate = (string)DateHandler::getDateKey((string)$event_fact['date'], true);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl
			if ($eventPlace) {
				$events[] = $eventDate.'|'.$eventPlace;
			}
		}
		foreach ($xml->spouse_of_family as $spouseFamily) {
			$titleText = (string)$spouseFamily['title'];
			$family = @$this->families[$titleText];
			if ($family && $family['xml']) {
				foreach ($family['xml']->event_fact as $event_fact) {
					list ($eventPlace, $eventPlaceText) = $this->getEventPlace($event_fact);
					$eventDate = (string)DateHandler::getDateKey((string)$event_fact['date'], true);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl
					if ($eventPlace) {
						$events[] = $eventDate.'|'.$eventPlace;
					}
				}
			}
		}
		
		// sort events by date
		sort($events);
		
		$result = array();
		foreach ($events as $event) {
			$pos = mb_strpos($event, '|');
			$place = mb_substr($event, $pos+1);
			if (!in_array($place, $result)) {
				$result[] = $place;
			}
		}
		return $result;
	}
	
	private function addExternals($titleTexts) {
		foreach ($titleTexts as $titleText) {
			$this->externals[$titleText] = true;
		}
	}
	
	private function addPlaces($places) {
		foreach ($places as $place) {
			if (substr($place, 0, 6) == 'Place:') {
				$place = mb_substr($place, 6); // strip off Place:
				if ($place && !in_array($place, $this->places)) {
					$this->places[] = $place;
				}
			}
			else if (substr($place, 0, 7) == 'LatLng:') {
				$place = mb_substr($place, 7);
				if (!in_array($place, $this->latlngs)) {
					$this->latlngs[] = $place;
				}
			}
		}
	}
	
	public function getItems() {
		global $wrIconSize;
		
		$result = '';
		$this->places = array();
		$this->externals = array();
		$this->otherEvents = array();
		$this->latlngs = array();
		
		foreach ($this->people as $titleText => $p) {
			$given = $surname = $fullname = '';
			$birthDate = $birthPlace = $birthPlaceText = $birthYear = '';
			$deathDate = $deathPlace = $deathPlaceText = $deathYear = '';
			$imageURL = $iconURL = '';
			$parents = $spouse = $allPlace = '';
			$otherEvents = '';
			$xml = $p['xml'];
			$position = $p['position'];
			$allPlaces = array();
			if ($xml) {
				foreach ($xml->name as $name) {
					$given = (string)$name['given'];
					$surname = (string)$name['surname'];
					$fullname = StructuredData::getFullname($name);
					break;
				}
				list( $birthEventType, $birthDate, $birthPlace, $birthPlaceText, $birthYear ) = $this->getEventData($xml, array('Birth', 'Christening', 'Baptism'));
				list( $deathEventType, $deathDate, $deathPlace, $deathPlaceText, $deathYear ) = $this->getEventData($xml, array('Death', 'Burial'));
				$otherEvents = $this->getOtherEventData($xml, array($birthEventType, $deathEventType));
				foreach ($xml->image as $image) {
					if ($image['primary'] == 'true') {
						$t = Title::makeTitle(NS_IMAGE, (string)$image['filename']);
						if ($t && $t->exists()) {
							$image = new Image($t);
							$imageURL = $image->createThumb(SearchForm::THUMB_WIDTH);
							$iconURL = $image->createThumb(PedigreeData::ICON_WIDTH, -1, true);
							break;
						}
					}
				}
				$parents = $this->getTitles($xml, 'child_of_family', 'Family');
				$this->addExternals($parents);
				$spouse = $this->getTitles($xml, 'spouse_of_family', 'Family');
				$this->addExternals($spouse);
				$allPlaces = $this->getAllEventPlaces($xml);
				$this->addPlaces($allPlaces);
			}
			if (substr($position, 0, 1) == 'P') {
				$pos = substr($position, 1);
				$line = self::$LINES[$pos];
				$generation = self::$GENERATIONS[$pos];
			}
			else if ($position == 'Spouse') {
				$line = self::$LINES[1];
				$generation = self::$GENERATIONS[1];
			}
			else { // $position == 'Child'
				$line = self::$LINES[0];
				$generation = self::$GENERATIONS[0];
			}
			$result .= ($result ? ",\n" : '') . '{ '.
				PedigreeData::outJSON('type', 'Person').
				PedigreeData::outJSON('label', 'Person:'.$titleText).
				PedigreeData::outJSON('Surname', $surname).
				PedigreeData::outJSON('Givenname', $given).
				PedigreeData::outJSON('FullName', $fullname).
				PedigreeData::outJSON('BirthYear', $birthYear).
				PedigreeData::outJSON('BirthDate', $birthDate).
				PedigreeData::outJSON('BirthPlace', $birthPlace).
				PedigreeData::outJSON('BirthPlaceText', $birthPlaceText).
				PedigreeData::outJSON('DeathYear', $deathYear).
				PedigreeData::outJSON('DeathDate', $deathDate).
				PedigreeData::outJSON('DeathPlace', $deathPlace).
				PedigreeData::outJSON('DeathPlaceText', $deathPlaceText).
				PedigreeData::outJSON('OtherEvents', $otherEvents).
				PedigreeData::outJSON('ImageURL', $imageURL).
				PedigreeData::outJSON('IconURL', $iconURL).
				PedigreeData::outJSON('Parents', $parents).
				PedigreeData::outJSON('Spouse', $spouse).
				PedigreeData::outJSON('AllPlaces', $allPlaces).
				PedigreeData::outJSON('Line', $line).
				PedigreeData::outJSON('Generation', $generation).
				PedigreeData::outJSON('Position', $position, false).
				"}";
		}

		foreach ($this->families as $titleText => $p) {
			$marriageDate = $marriagePlace = $marriagePlaceText = $marriageYear = '';
			$husband = $wife = $children = '';
			$otherEvents = '';
			$xml = $p['xml'];
			$position = $p['position'];
			if ($xml) {
				list( $marriageEventType, $marriageDate, $marriagePlace, $marriagePlaceText, $marriageYear ) = $this->getEventData($xml, array('Marriage'));
				$otherEvents = $this->getOtherEventData($xml, array($marriageEventType));
				$husband = $this->getTitles($xml, 'husband', 'Person');
				$this->addExternals($husband);
				$wife = $this->getTitles($xml, 'wife', 'Person');
				$this->addExternals($wife);
				$children = $this->getTitles($xml, 'child', 'Person');
				$this->addExternals($children);
			}
			$result .= ($result ? ",\n" : '') . '{ '.
				PedigreeData::outJSON('type', 'Family').
				PedigreeData::outJSON('label', 'Family:'.$titleText).
				PedigreeData::outJSON('MarriageYear', $marriageYear).
				PedigreeData::outJSON('MarriageDate', $marriageDate).
				PedigreeData::outJSON('MarriagePlace', $marriagePlace).
				PedigreeData::outJSON('MarriagePlaceText', $marriagePlaceText).
				PedigreeData::outJSON('OtherEvents', $otherEvents).
				PedigreeData::outJSON('Husband', $husband).
				PedigreeData::outJSON('Wife', $wife).
				PedigreeData::outJSON('Child', $children).
				PedigreeData::outJSON('Position', $position, false).
				"}";
		}
		
		// add externals
		foreach ($this->externals as $titleText => $dummy) {
			$result .= ($result ? ",\n" : '') . '{ '.
				PedigreeData::outJSON('type', 'External').
				PedigreeData::outJSON('label', $titleText, false).
				"}";
		}
		
		// add other events
		foreach ($this->otherEvents as $index => $event) {
			$result .= ($result ? ",\n" : '') . '{ '.
				PedigreeData::outJSON('type', 'Event').
				PedigreeData::outJSON('label', 'Event:'.($index+1)).
				PedigreeData::outJSON('Date', $event['date']).
				PedigreeData::outJSON('Place', $event['place']).
				PedigreeData::outJSON('PlaceText', $event['placetext']).
				PedigreeData::outJSON('EventType', $event['type'], false).
				"}";
		}
		
		// add places
		$stdPlaces = PlaceSearcher::getPlaceTitlesLatLong($this->places);
		foreach ($stdPlaces as $titleText => $stdPlace) {
			if ($stdPlace['lat'] || $stdPlace['lon']) {
				$result .= ($result ? ",\n" : '') . '{ '.
					PedigreeData::outJSON('type', 'Place').
					PedigreeData::outJSON('label', 'Place:'.$titleText).
					PedigreeData::outJSON('addressLatLng', $stdPlace['lat'].','.$stdPlace['lon'], false).
					"}";
			}
		}
		foreach ($this->latlngs as $latlng) {
			$result .= ($result ? ",\n" : '') . '{ '.
				PedigreeData::outJSON('type', 'LatLng').
				PedigreeData::outJSON('label', 'LatLng:'.$latlng).
				PedigreeData::outJSON('addressLatLng', $latlng, false).
				"}";
		}
		
		return $result;
	}
}
?>