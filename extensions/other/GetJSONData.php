<?php

/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialGetJSONDataSetup";

function wfSpecialGetJSONDataSetup() {
   global $wgMessageCache, $wgSpecialPages;
   $wgMessageCache->addMessages( array( "getjsondata" => "GetJSONData" ) );
   $wgSpecialPages['GetJSONData'] = array('SpecialPage','GetJSONData');
}

/**
 * Entry point : initialise variables and call subfunctions.
 * @param $par String: becomes "FOO" when called like Special:Allpages/FOO (default NULL)
 * @param $specialPage @see SpecialPage object.
 */
function wfSpecialGetJSONData() {
	global $wgRequest, $wgOut, $wgContLang;

	$titleText = $wgRequest->getVal('pagetitle');
	$error = '';

	if ($titleText) {
		$title = Title::newFromText($titleText);
		if (is_null($title) || ($title->getNamespace() != NS_PERSON && $title->getNamespace() != NS_FAMILY) || !$title->exists()) {
			$error = 'Please enter the title of a person or family page (include the "Person:" or "Family:")';
		}
		else {
			$wgOut->disable();
			$sp = new JSONPedigree($title);
			$sp->OutputJSONData();
			return;
		}
	}
	$wgOut->setPageTitle('Get JSON Data');
	$wgOut->setArticleRelated(false);
	$wgOut->setRobotpolicy('noindex,nofollow');
	if ($error) {
		$wgOut->addHTML("<p><font color=red>$error</font></p>");
	}

	$queryBoxStyle = 'width:100%;text-align:center;';
	$form = <<< END
<form name="search" action="/wiki/Special:GetJSONData" method="get">
<div id="searchFormDiv" style="$queryBoxStyle">
Person or Family page title: <input type="text" name="pagetitle" size="24" maxlength="100" value="$titleText" onfocus="select()" />
<input type="submit" value="Go" />
</div>
</form>
END;

	$wgOut->addHTML($form);
}


class JSONPedigree {
	const MAX_FAMILIES = 7;
	const SPOUSE_FAMILY_BASE = 100;

	private $title;
	private $dbr;
	private $skin;
	private $tm;
	private $families;
	private $numSpouseFamilies;
	private $selfTag;
	private $spouseTag;
	private $stdPlaces;
	private $prevLastFamilyEndpoint;

	public function __construct($title) {
		global $wgUser;

		$this->title = $title;
		$this->dbr =& wfGetDb(DB_SLAVE);
		$this->skin = $wgUser->getSkin();

		$this->families = array();
		$this->numSpouseFamilies = 0;
		$this->selfTag = '';
		$this->spouseTag = '';
		$this->stdPlaces = null;
		$this->prevLastFamilyEndpoint = null;
		$this->loadPedigree();
	}

	private function loadPersonAttrs($xmlPerson) {
		$person = array();
		foreach ($xmlPerson->attributes() as $attr => $value) {
			$person[(string)$attr] = (string)$value;
		}
		return $person;
	}

	private function loadFamily($familyTitleText, $index, $limit) {
		$this->families[$index] = array();
		$this->families[$index]['title'] = $familyTitleText;
		// capture person information if this family is in the pedigree or is one of the spouse families
		if ($index <= $limit || $index >= JSONPedigree::SPOUSE_FAMILY_BASE) {
			$title = Title::newFromText($familyTitleText, NS_FAMILY);
			if (!is_null($title)) {
				$revision = Revision::loadFromTitle($this->dbr, $title); // use load instead of new because I don't want JSONPedigree to ever access DB_MASTER
				if (!is_null($revision)) {
					$xml = StructuredData::getXml('family', $revision->getText());
					if ($xml) {
						$this->families[$index]['exists'] = true;
						if (isset($xml->husband)) {
							$this->families[$index]['husband'] = $this->loadPersonAttrs($xml->husband);
						}
						if (isset($xml->wife)) {
							$this->families[$index]['wife'] = $this->loadPersonAttrs($xml->wife);
						}
						foreach($xml->event_fact as $event_fact) {
							if ($event_fact['type'] == 'Marriage') {
								$this->families[$index]['marriagedate'] = (string)$event_fact['date'];
								$this->families[$index]['marriageplace'] = (string)$event_fact['place'];
								break;
							}
						}
						if (isset($xml->child)) {
							$this->families[$index]['children'] = array();
							foreach($xml->child as $child) {
								$this->families[$index]['children'][] = $this->loadPersonAttrs($child);
							}
						}
					}
				}
			}
			$revision = null; // free up memory
			if ($limit > 0) { // omit this step for the individual's spouse_of_family
				if (@$this->families[$index]['husband']['child_of_family']) {
					$this->loadFamily($this->families[$index]['husband']['child_of_family'], $index * 2, $limit);
				}
				if (@$this->families[$index]['wife']['child_of_family']) {
					$this->loadFamily($this->families[$index]['wife']['child_of_family'], $index * 2 + 1, $limit);
				}
			}
		}
	}

	private function loadSelf(&$person, $xml) {
		$person = array();
		$person['title'] = $this->title->getText();
		$person['child_of_family'] = (string)$xml->child_of_family;
		foreach ($xml->event_fact as $event_fact) {
			if ((string)$event_fact['type'] == 'Birth') {
				$person['birthdate'] = (string)$event_fact['date'];
				$person['birthplace'] = (string)$event_fact['place'];
			}
			else if ((string)$event_fact['type'] == 'Death') {
				$person['deathdate'] = (string)$event_fact['date'];
				$person['deathplace'] = (string)$event_fact['place'];
			}
			else if ((string)$event_fact['type'] == 'Christening') {
				$person['chrdate'] = (string)$event_fact['date'];
				$person['chrplace'] = (string)$event_fact['place'];
			}
			else if ((string)$event_fact['type'] == 'Burial') {
				$person['burialdate'] = (string)$event_fact['date'];
				$person['burialplace'] = (string)$event_fact['place'];
			}
		}
		if (isset($xml->name)) {
			foreach ($xml->name->attributes() as $attr => $value) {
				$person[(string)$attr] = (string)$value;
			}
		}
	}

	private function loadPedigree() {
		// read the person
		if ($this->title->getNamespace() == NS_PERSON) {
			$revision = Revision::loadFromTitle($this->dbr, $this->title); // use load instead of new because I don't want JSONPedigree to ever access DB_MASTER
			if ($revision) {
				$xml = StructuredData::getXml('person', $revision->getText());
				if ($xml) {
					foreach ($xml->spouse_of_family as $spouseFamily) {
						$pos = JSONPedigree::SPOUSE_FAMILY_BASE + $this->numSpouseFamilies;
						$this->loadFamily((string)$spouseFamily['title'], $pos, 0);
						if (@$this->families[$pos]['exists']) {
							$this->numSpouseFamilies += 1;
						}
						else {
							$this->families[$pos] = null;
						}
					}
					$this->loadFamily((string)$xml->child_of_family['title'], 1, JSONPedigree::MAX_FAMILIES);
					if ($this->numSpouseFamilies == 0) {
						// no spouse families exist; get information on self from the person page (put into ['husband'] - it shouldn't matter)
						$this->loadSelf($this->families[JSONPedigree::SPOUSE_FAMILY_BASE ]['husband'], $xml);
					}
				}
			}
			$selfTitle = @$this->families[JSONPedigree::SPOUSE_FAMILY_BASE]['husband']['title'];
			if ($selfTitle) {
				$this->selfTag = ($selfTitle == $this->title->getText() ? 'husband' : 'wife');
				$this->spouseTag = ($this->selfTag == 'husband' ? 'wife' : 'husband');
			}
		}
		else {
			$this->loadFamily($this->title->getText(), 1, JSONPedigree::MAX_FAMILIES);
			$this->selfTag = '';
			$this->spouseTag = '';
		}
	}


	private function addPlace($place, &$result) {
		if (isset($place)) {
			$place = $this->cleanPlace($place);
			if ($place && !in_array($place, $result)) {
				$result[] = $place;
			}
		}
	}

	private function addSpousePlaces($person, &$result) {
		if (isset($person)) {
			$this->addPlace(@$person['birthplace'], $result);
//			if (!@$person['birthplace'] && @$person['chrplace']) {
//				$this->addPlace($person['chrplace']);
//			}
			$this->addPlace(@$person['deathplace'], $result);
//			if (!@$person['deathplace'] && @$person['burialplace']) {
//				$this->addPlace($person['burialplace']);
//			}
		}
	}

	private function getPlaces() {
		$result = array();
		foreach($this->families as $family)
		{
			$this->addPlace(@$family['marriageplace'], $result);
			$this->addSpousePlaces(@$family['husband'], $result);
			$this->addSpousePlaces(@$family['wife'], $result);
			if (isset($family['children'])) {
				foreach ($family['children'] as $child) {
					// we don't map child deathplaces
					$this->addPlace(@$child['birthplace'], $result);
					$this->addPlace(@$child['deathplace'], $result);
//					if (!@$child['chrplace'] && @$child['chrplace']) {
//						$this->addPlace($child['chrplace']);
//					}
				}
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

	private function formatPlace($place){
		$placeArr = preg_split('/\s*,\s*/', $place, -1, PREG_SPLIT_NO_EMPTY);
		$placeArr = array_reverse($placeArr);
		return implode(', ', $placeArr);
	}

	private function cleanDate($str){
		return DateHandler::getDateKey($str);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl

//		if($str == 'UNKNOWN')
//			return '';
//		$str = preg_replace('/abt\.?\s*/i', '', $str);
//		$date = date_create($str); // function new in PHP 5.2
//		return @date_format($date, DATE_ISO8601);

//		$matches = array();
//		$pattern = '/abt\.?\s+([0-9]{4})';
//		preg_match($pattern, $str, $matches);
//		if(isset($matches[1]))
//			return $matches[1];
//		$pattern = '([0-9]{1,2}\s+(\w+)\s+'
	}

	private $counter = 0;

	public function OutputJSONData(){
		$places = $this->getPlaces();
		$this->stdPlaces = PlaceSearcher::getPlaceTitleLatLong($places);
		//print_r($this->families);#for debug
		//print_r($this->stdPlaces);#for debug
		$persons = array();
		$extras = array();
		print "{\n\t\"items\":[";
		$first = true;
		// we go through all the families and create a JSON Family object from each
		// we add to the persons array going through it
		// which we use down below to make a bunch of JSON Person objects
		// we also add to an event array containing marriages, deaths, and births
		// each event has a place and a date
		foreach($this->families as $family ){
			if($first)
				$first = false;
			else
				print ','; // lot of work for a dumb comma
			print "\n\t\t{";
			print "\n\t\t\ttype: 'Family'";
			print ",\n\t\t\tlabel: '" . str_replace("'","\\'",$family['title']) . "'";
			$title = Title::newFromText($family['title'], NS_FAMILY);
			$url = $title->getLocalURL();
			print ",\n\t\t\tURL: '$url'";
			$id = $this->counter++;
			$extras[] = @array('id' => $id, 'URL' => $url, 'Family' => $family['title'], 'type' => 'Marriage', 'Date' => $family['marriagedate'], 'Place' => $family['marriageplace']);
			print ",\n\t\t\tMarriage: '$id'";
			if(isset($family['husband']) && $family['husband'] != "UNKNOWN"){
				$family['husband']['family'] = $family['title'];
				$persons[$family['husband']['title']] = $family['husband'];
			}
			if(isset($family['wife']) && $family['wife'] != "UNKNOWN"){
				$family['wife']['family'] = $family['title'];
				$persons[$family['wife']['title']] = $family['wife'];
			}
			if(isset($family['children'])){
				$count = count($family['children']);
				print ",\n\t\t\tChildCount: $count";
				foreach($family['children'] as $child){
					$child['family'] = $family['title'];
					$child['siblingCount'] = $count - 1;
					$persons[$child['title']] = $child;
				}
			}
			print "\n\t\t}";
		}
		foreach($persons as $person){
			print ",\n\t\t{";
			print "\n\t\t\ttype: 'Person'";
			print ",\n\t\t\tlabel: '" . str_replace("'","\\'",$person['title']) . "'";
			$title = Title::newFromText($person['title'], NS_PERSON);
			$url = $title->getLocalURL();
			print ",\n\t\t\tURL: '$url'";
			print ",\n\t\t\tFamily: '" . str_replace("'","\\'",$person['family']) . "'";
			if(isset($person['siblingCount']))
				print ",\n\t\t\tSiblingCount: " . $person['siblingCount'];

			$arr = array();
			preg_match('/(.*)\s+(\S+)\s+(\(\S+\))/', $person['title'], $arr);

			if(isset($person['given']) && strlen($person['given']) > 0)
				print ",\n\t\t\tGiven: '" . str_replace("'","\\'",$person['given']) . "'";
			elseif(count($arr) == 4 && strlen($arr[1]) > 0)
				print ",\n\t\t\tGiven: '" . str_replace("'","\\'",trim($arr[1])) . "'";
			if(isset($person['surname']) && strlen($person['surname']) > 0)
				print ",\n\t\t\tSurname: '" . str_replace("'","\\'",$person['surname']) . "'";
			elseif(count($arr) == 4 && strlen($arr[2]) > 0)
				print ",\n\t\t\tSurname: '" . str_replace("'","\\'",trim($arr[2])) . "'";

			$id = $this->counter++;
			$extras[] = @array('id' => $id, 'URL' => $url, 'Person' => $person['title'], 'Family' => $person['family'], 'type' => 'Birth', 'Date' => $person['birthdate'], 'Place' => $person['birthplace']);
			print ",\n\t\t\tBirth: '$id'";
			$id = $this->counter++;
			$extras[] = @array('id' => $id, 'URL' => $url, 'Person' => $person['title'], 'Family' => $person['family'], 'type' => 'Death', 'Date' => $person['deathdate'], 'Place' => $person['deathplace']);
			print ",\n\t\t\tDeath: '$id'";
			print "\n\t\t}";
		}
		foreach($extras as $extra){
			print ",\n\t\t{";
			print "\n\t\t\ttype: '" . $extra['type'] . "'";
			print ",\n\t\t\tid: '" . $extra['id'] . "'";
			print ",\n\t\t\tURL: '" . $extra['URL'] . "'";
			$label = '';
			if(isset($extra['Place']) && strlen($extra['Place']) > 0){
				$place = $this->cleanPlace($extra['Place']);
				if(strlen($place) > 0){
					print ",\n\t\t\tLocation: '" . str_replace("'","\\'",$this->formatPlace($place)) . "'";
					if(isset($this->stdPlaces[$place])) // this should be unnecessary, but there appears to be a bug
						print ",\n\t\t\tLatLon: '" . $this->stdPlaces[$place]['lat'] . ',' . $this->stdPlaces[$place]['lon'] . "'";
					$pos = strpos($place, ',');
					if($pos >= 0)
						$label .= str_replace("'","\\'",substr($place, 0, $pos));
					else
						$label .= str_replace("'","\\'",$place);
					$label .= ': ';
				}
			}
			if(isset($extra['Person'])){
				if(isset($extra['Family']))
					print ",\n\t\t\tFamily: '" . str_replace("'","\\'",$extra['Family']) . "'";
				print ",\n\t\t\tPerson: '" . str_replace("'","\\'",$extra['Person']) . "'";
				$label .= $extra['type'] . ' of ' . str_replace("'","\\'",$extra['Person']);
			}
			else if(isset($extra['Family'])){
				print ",\n\t\t\tFamily: '" . str_replace("'","\\'",$extra['Family']) . "'";
				$label .= $extra['type'] . ' of ' . str_replace("'","\\'",$extra['Family']);
			}
			if(isset($extra['Date']) && strlen($extra['Date']) > 0){
				$date = $this->cleanDate(str_replace("'","\\'",$extra['Date']));
				if(strlen($date) > 0){
					print ",\n\t\t\tDate: '$date'";
				}
				else
					$label .= ': ' . str_replace("'","\\'",$extra['Date']);

			}
			print ",\n\t\t\tlabel: '$label'";
			print "\n\t\t}";
		}

		print "\n\t],";
//		"Date" : { valueType: "date" },
		print '
	"properties": {
		"Marriage" : { valueType: "item" },
		"Family" : { valueType: "item" },
		"Person" : { valueType: "item" },
		"Death" : { valueType: "item" },
		"Birth" : { valueType: "item" },
		"Date" : { valueType: "date" },
		"URL" : { valueType: "url" }
	},
	"types": {
		"Person" : { pluralLabel: "People" },
		"Family" : { pluralLabel: "Families" },
		"Marriage" : { pluralLabel: "Marriages" },
		"Birth" : { pluralLabel: "Births" },
		"Death" : { pluralLabel: "Deaths" },
		"Date" : { pluralLabel: "Dates" },
		"Location" : { pluralLabel: "Locations" }
	}
}';
	}
}

//$this->img = new Image( $this->mTitle );
//$thumbnail = $this->img->createThumb( 96, -1);
//$iconThumbnail = $this->img->createThumb($wrIconSize, -1, true);
?>
