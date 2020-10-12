<?php

/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');
require_once("$IP/extensions/other/PlaceSearcher.php");

# Register with AjaxDispatcher as a function
$wgAjaxExportList[] = "wfShowPedigreeData";

function wfShowPedigreeData($titleText) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	$title = Title::newFromText($titleText);
	$return = <<< END
{
	types: {
		"Person" : {
			pluralLabel: "People"
		}
	},
	properties: {
		BirthYear: { valueType: "number" },
		DeathYear: { valueType: "number" },
		MarriageYear: { valueType: "number" },
		Parents: { valueType: "item" },
		Spouse: { valueType: "item" },
		Husband: { valueType: "item" },
		Wife: { valueType: "item" },
		Child: { valueType: "item" },
		BirthPlace: { valueType: "Place"	},
		DeathPlace: { valueType: "Place"	},
		AllPlaces: { valueType: "Place" }
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
	const THUMB_WIDTH = 96;
	const THUMB_WIDTH = 48;
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
				$result .= "'".str_replace("'","\\'",trim($value[$i]))."'";
			}
			if (count($value) > 1) {
				$result .= ']';
			}
		}
		else if ($value) {
			$result = "'".str_replace("'","\\'",trim($value))."'";
		}
		if ($result) {
			return $label.": $result".($addComma ? ',' : '')."\n";
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
							if ($position > 0 || $titleText != $this->title->getText()) {
								$this->people[$titleText] = array('position' => ($position == 0 ? 'Spouse' : 'P'.$position*2), 'xml' => $this->loadPerson($titleText));
							}
							if ($position > 0) {
								$titleText = (string)$husband['child_of_family'];
								$this->families[$titleText] = array('position' => 'F'.$position*2, 'xml' => $this->loadFamily($titleText, $position*2));
							}
							break; // first husband only
						}
					}
					if (isset($xml->wife)) {
						foreach($xml->wife as $wife) {
							$titleText = (string)$wife['title'];
							if ($position > 0 || $titleText != $this->title->getText()) {
								$this->people[$titleText] = array('position' => ($position == 0 ? 'Spouse' : 'P'.$position*2+1), 'xml' => $this->loadPerson($titleText));
							}
							if ($position > 0) {
								$titleText = (string)$husband['child_of_family'];
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
	
	private function getEventData($xml, $eventTypes) {
		foreach ($types as $type) {
			foreach ($xml->event_fact as $event_fact) {
				if ((string)$event_fact['type'] == $type) {
					$eventDate = (string)$event_fact['date'];
					$eventYear = substr(DateHandler::getDateKey($date), 0, 4);      // changed to DateHandler function Oct 2020 by Janet Bjorndahl
					$place = (string)$event_fact['place'];
					$pos = mb_strpos($place, '|');
					if ($pos !== false) {
						$eventPlace = mb_substr($place, 0, $pos);
						$eventPlaceText = mb_substr($place, $pos+1);
					}
					else {
						$eventPlace = $eventPlaceText = $place;
					}
					return array($eventDate, $eventPlace, $eventPlaceText, $eventYear);
				}
			}
		}
		return array('','','','');
	}
	
	private function getTitles($xml, $type) {
		$result = array();
		foreach ($xml[$type] as $node) {
			$title = $node['title'];
			if ($title) {
				$result[] = $title;
			}
		}
		return $result;
	}
	
	private function cleanPlace($place) {
		$pos = mb_strpos($place, '|');
		if ($pos !== false) {
			$place = mb_substr($place, 0, $pos);
		}
		return $place;
	}
	
	private function getAllEventPlaces($xml) {
		$events = array();
		foreach ($xml->event_fact as $event_fact) {
			$eventPlace = $this->cleanPlace((string)$event_fact['place']);
			$eventDate = DateHandler::getDateKey((string)$event_fact['date']);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl
			if ($eventPlace) {
				$events[] = $eventDate.'|'.$eventPlace;
			}
		}
		foreach ($xml->spouse_of_family as $spouseFamily) {
			$titleText = (string)$spouseFamily['title'];
			$family = $this->families[$titleText];
			if ($family && $family['xml']) {
				foreach ($family['xml']->event_fact as $event_fact) {
					$eventPlace = $this->cleanPlace((string)$event_fact['place']);
					$eventDate = DateHandler::getDateKey((string)$event_fact['date']);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl
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
			$result[] = mb_substr($event, $pos+1);
		}
		return $result;
	}
	
	private function addExternals($titleTexts, $nsText) {
		foreach ($titleTexts as $titleText) {
			$this->externals[$nsText.':'.$titleText] = true;
		}
	}
	
	private function addPlaces($places) {
		foreach ($places as $place) {
			if (!in_array($place, $this->places)) {
				$this->places[] = $place;
			}
		}
	}
	
	public function getItems() {
		global $wrIconSize;
		
		$result = '';
		$this->places = array();
		$this->externals = array();
		
		foreach ($this->people as $titleText => $p) {
			$given = $surname = $fullname = '';
			$birthDate = $birthPlace = $birthPlaceText = $birthYear = '';
			$deathhDate = $deathPlace = $deathPlaceText = $deathYear = '';
			$imageURL = $iconURL = '';
			$parents = $spouse = $allPlace = '';
			$xml = $p['xml'];
			$position = $p['position'];
			if ($xml) {
				foreach ($xml->name as $name) {
					$given = (string)$name['given'];
					$surname = (string)$name['surname'];
					$fullname = StructuredData::getFullname($name);
					break;
				}
				list( $birthDate, $birthPlace, $birthPlaceText, $birthYear ) = $this->getEventData($xml, array('birth', 'christening'));
				list( $deathDate, $deathPlace, $deathPlaceText, $deathYear ) = $this->getEventData($xml, array('death', 'burial'));
				foreach ($xml->image as $image) {
					if ($image['primary'] == 'true') {
						$t = Title::makeTitle(NS_IMAGE, (string)$image['filename']);
						if ($t && $t->exists()) {
							$image = new Image($t);
							$imageURL = $image->createThumb(PedigreeData::THUMB_WIDTH);
							$iconURL = $image->createThumb(PedigreeData::ICON_WIDTH, -1, true);
							break;
						}
					}
				}
				$parents = $this->getTitles($xml, 'child_of_family');
				$this->addExternals($parents, 'Family');
				$spouse = $this->getTitles($xml, 'spouse_of_family');
				$this->addExternals($spouse, 'Family');
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

		foreach ($this->people as $titleText => $p) {
			$marriageDate = $marriagePlace = $marriagePlaceText = $marriageYear = '';
			$husband = $wife = $children = '';
			$xml = $p['xml'];
			$position = $p['position'];
			if ($xml) {
				list( $marriageDate, $marriagePlace, $marriagePlaceText, $marriageYear ) = $this->getEventData($xml, array('marriage'));
				$husband = $this->getTitles($xml, 'husband');
				$this->addExternals($husband, 'Person');
				$wife = $this->getTitles($xml, 'wife');
				$this->addExternals($wife, 'Person');
				$children = $this->getTitles($xml, 'child');
				$this->addExternals($children, 'Person');
			}
			$result .= ($result ? ",\n" : '') . '{ '.
				PedigreeData::outJSON('type', 'Family').
				PedigreeData::outJSON('label', 'Family:'.$titleText).
				PedigreeData::outJSON('MarriageYear', $marriageYear).
				PedigreeData::outJSON('MarriageDate', $marriageDate).
				PedigreeData::outJSON('MarriagePlace', $marriagePlace).
				PedigreeData::outJSON('MarriagePlaceText', $marriagePlaceText).
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
		
		// add places
		$stdPlaces = PlaceSearcher::getPlaceTitleLatLong($this->places);
		foreach ($stdPlaces as $titleText => $stdPlace) {
			if ($stdPlace['lat'] && $stdPlace['lng']) {
				$result .= ($result ? ",\n" : '') . '{ '.
					PedigreeData::outJSON('id', $titleText).
					PedigreeData::outJSON('type', 'Place').
					PedigreeData::outJSON('label', $titleText).
					PedigreeData::outJSON('addressLatLng', $stdPlace['lat'].','.$stdPlace['lng'], false).
					"}";
			}
		}
		
		return $result;
	}
}
?>