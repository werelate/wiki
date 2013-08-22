<?php
/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */
require_once("$IP/extensions/structuredNamespaces/TipManager.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialShowPedigreeSetup";

function wfSpecialShowPedigreeSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "showpedigree" => "ShowPedigree" ) );
	$wgSpecialPages['ShowPedigree'] = array('SpecialPage','ShowPedigree');
}

/**
 * constructor
 */
function wfSpecialShowPedigree() {
	global $wgOut, $wgRequest, $wgScriptPath, $wgGoogleMapKey;

	$wgOut->setArticleRelated(false);
	$wgOut->setRobotpolicy('noindex,nofollow');
	$titleText = $wgRequest->getVal('pagetitle');
	$error = '';

	if ($titleText) {
		$title = Title::newFromText($titleText);
		if (is_null($title) || ($title->getNamespace() != NS_PERSON && $title->getNamespace() != NS_FAMILY) || !$title->exists()) {
			$error = 'Please enter the title of a person or family page (include the "Person:" or "Family:")';
		}
		else {
		  	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/jtip.1.js\"></script>");
			$wgOut->setPageTitle('Pedigree for ' . $title->getText());
			$sp = new ShowPedigree($title);
			$wgOut->addHtml($sp->getPedigreeTable());

			// pedigree map
			$wgOut->addHTML($sp->getCheckboxes());
			$wgOut->addHTML('<div id="pedigreemap" style="width: 760px; height: 520px"></div>');
			$wgOut->addHTML($sp->getLegend());
			$wgOut->addScript("<script src=\"http://maps.google.com/maps?file=api&v=2&key=$wgGoogleMapKey\" type=\"text/javascript\"></script>");
			$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/pedigreemap.1.js\"></script>");
			$mapData = $sp->getPedigreeMapData();
			$mapData = str_replace("'", "\'", $mapData);
			$wgOut->addScript("<script type=\"text/javascript\">
//<![CDATA[
$(document).ready(function() { ShowMap(); });
function getPedigreeData() { return '<events>$mapData</events>'; }
//]]>
</script>");
			return;
		}
	}
	$wgOut->setPageTitle('Show Pedigree');
	if ($error) {
		$wgOut->addHTML("<p><font color=red>$error</font></p>");
	}

	$queryBoxStyle = 'width:100%;text-align:center;';
	$form = <<< END
<form name="search" action="/wiki/Special:ShowPedigree" method="get">
<div id="searchFormDiv" style="$queryBoxStyle">
Person or Family page title: <input type="text" name="pagetitle" size="24" maxlength="100" value="$titleText" onfocus="select()" />
<input type="submit" value="Go" />
</div>
</form>
END;

	$wgOut->addHTML($form);
}

class ShowPedigree {
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

	//										child	  self		f			m			ff		  fm		  mf		  mm		  spouse
	private static $COLORS = array('808080', 'ffff00', 'ff0000', '00ff00', 'ff00c0', 'ff8000', 'b0ff00', '00ffa0', '0000ff');
	private static $INDI_FONT_COLOR = 'c0c000';
	private static $LABELS = array('Children', 'Individual', 'Father', 'Mother', 'Father\'s Father', 'Father\'s Mother', 'Mother\'s Father', 'Mother\'s Mother', 'Spouse');

	private static function getColorIndexes($n) {
		$selfNumber = 1;
		$spouseNumber = ShowPedigree::MAX_FAMILIES+1;
		if ($n >= ShowPedigree::SPOUSE_FAMILY_BASE) {
			$familyNumber = 0;
			$husbandNumber = $selfNumber;
			$wifeNumber = $spouseNumber;
		}
		else {
			$familyNumber = $n;
			if ($n * 2 > ShowPedigree::MAX_FAMILIES) {
				$husbandNumber = $n;
				$wifeNumber = $n;
			}
			else {
				$husbandNumber = $n*2;
				$wifeNumber = $n*2 + 1;
			}
		}
		return array($familyNumber, $husbandNumber, $wifeNumber, $selfNumber, $spouseNumber);
	}

	private static function cleanPlace($place) {
		$pos = mb_strpos($place, '|');
		if ($pos !== false) {
			$place = mb_substr($place, 0, $pos);
		}
		return $place;
	}

	public function __construct($title) {
		global $wgUser;

		$this->title = $title;
		$this->dbr =& wfGetDb(DB_SLAVE);
		$this->skin = $wgUser->getSkin();
		$this->tm = new TipManager();

		$this->families = array();
		$this->numSpouseFamilies = 0;
		$this->selfTag = '';
		$this->spouseTag = '';
		$this->stdPlaces = null;
		$this->prevLastFamilyEndpoint = null;
		$this->loadPedigree();
	}

	public function getCheckboxes() {
		$result = '';
		for ($i = 0; $i < count(ShowPedigree::$COLORS); $i++) {
			if ($i == 0) {
				$j = 0;
			}
			else if ($i == 1) {
				$j = count(ShowPedigree::$COLORS) - 1;
			}
			else {
				$j = $i - 1;
			}
			$color = ShowPedigree::$COLORS[$j];
			$colorFont = $j == 1 ? ShowPedigree::$INDI_FONT_COLOR : $color;
			$label = ShowPedigree::$LABELS[$j];
			$result .= ' <input id="checkbox'.$color.'" type="checkbox" checked onclick="addMapOverlays()"/><font color="#'.$colorFont.'">'.$label.'</font>';
		}
		return $result;
	}

	public function getLegend() {
		global $wgStylePath;
		return "<img src=\"$wgStylePath/common/images/maps/lolly/ffffff.png\"/> Birth(s) ".
				 "<img src=\"$wgStylePath/common/images/maps/heart/ffffff.png\"/> Marriage(s) ".
				 "<img src=\"$wgStylePath/common/images/maps/grave/ffffff.png\"/> Death(s)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Icons are offset if more than one is coded to the same location)<br>";
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
		if ($index <= $limit || $index >= ShowPedigree::SPOUSE_FAMILY_BASE) {
			$title = Title::newFromText($familyTitleText, NS_FAMILY);
			if (!is_null($title)) {
				$revision = Revision::loadFromTitle($this->dbr, $title); // use load instead of new because I don't want ShowPedigree to ever access DB_MASTER
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
			$revision = Revision::loadFromTitle($this->dbr, $this->title); // use load instead of new because I don't want ShowPedigree to ever access DB_MASTER
			if ($revision) {
				$xml = StructuredData::getXml('person', $revision->getText());
				if ($xml) {
					foreach ($xml->spouse_of_family as $spouseFamily) {
						$pos = ShowPedigree::SPOUSE_FAMILY_BASE + $this->numSpouseFamilies;
						$this->loadFamily((string)$spouseFamily['title'], $pos, 0);
						if (@$this->families[$pos]['exists']) {
							$this->numSpouseFamilies += 1;
						}
						else {
							$this->families[$pos] = null;
						}
					}
					$this->loadFamily((string)$xml->child_of_family['title'], 1, ShowPedigree::MAX_FAMILIES);
					if ($this->numSpouseFamilies == 0) {
						// no spouse families exist; get information on self from the person page (put into ['husband'] - it shouldn't matter)
						$this->loadSelf($this->families[ShowPedigree::SPOUSE_FAMILY_BASE ]['husband'], $xml);
					}
				}
			}
			$selfTitle = @$this->families[ShowPedigree::SPOUSE_FAMILY_BASE]['husband']['title'];
			if ($selfTitle) {
				$this->selfTag = ($selfTitle == $this->title->getText() ? 'husband' : 'wife');
				$this->spouseTag = ($this->selfTag == 'husband' ? 'wife' : 'husband');
			}
		}
		else {
			$this->loadFamily($this->title->getText(), 1, ShowPedigree::MAX_FAMILIES);
			$this->selfTag = '';
			$this->spouseTag = '';
		}
	}

	private function makeLink($titleText, $linkText, $ns) {
		$title = Title::newFromText($titleText, $ns);
		return $this->skin->makeLinkObj($title, htmlspecialchars($linkText));
	}

	private function makePedLink($person, $linkText) {
		$title = Title::makeTitle(NS_SPECIAL, 'ShowPedigree');
		$titleText = $person['title'];
		$fullName = StructuredData::getFullname($person);
		return $this->skin->makeKnownLinkObj($title, $linkText, "pagetitle=Person:$titleText", '', '', "title=\"Show pedigree for $fullName\"", "class='cont'");
	}

	private function formatUplink($n, $tag) {
		$person = @$this->families[$n][$tag];
		if (@$person['child_of_family']) {
			$title = Title::newFromText($person['child_of_family'], NS_FAMILY);
			if ($title && $title->exists()) {
				return $this->makePedLink($person, '>');
			}
		}
		return "";
	}

	private function formatDownlink($person) {
		return $this->makePedLink($person, '<');
	}

	private function formatPlace($place) {
		if ($place) {
			$fields = explode('|', $place);
			return $this->makeLink($fields[0], @$fields[1] ? $fields[1] : $place, NS_PLACE);
		}
		return '';
	}

	private function formatDatePlace($person, $dateTag, $placeTag) {
		$date = @$person[$dateTag];
		$place = $this->formatPlace(@$person[$placeTag]);
		return $date . ($date && $place ? ', ' : '') . $place;
	}

	private function formatPerson($person, $family, $childNum, $class) {
		if (@$person['title']) {
			$birth = $this->formatDatePlace($person, 'birthdate', 'birthplace');
			if (!$birth) {
				$chr = $this->formatDatePlace($person, 'chrdate', 'chrplace');
			}
			else {
				$chr = '';
			}
			$marriage = $this->formatDatePlace($family, 'marriagedate', 'marriageplace');
			$death = $this->formatDatePlace($person, 'deathdate', 'deathplace');
			if (!$death) {
				$bur = $this->formatDatePlace($person, 'burialdate', 'burialplace');
			}
			else {
				$bur = '';
			}
			$result = "<div class='$class'><table cellspacing=0 cellpadding=0><tr><td>"
				.($childNum !== false ? "$childNum. " : '')
				.$this->makeLink($person['title'], StructuredData::getFullname($person), NS_PERSON)."</td></tr>\n"
				.(($childNum === false && !$chr) || $birth ? "<tr><td>Birth: $birth</td></tr>\n" : '')
				.($chr ? "<tr><td>Chr: $chr</td></tr>\n" : '')
				.($family ? "<tr><td>Marriage: $marriage</td></tr>\n" : '')
				.(($childNum === false && !$bur) || $death ? "<tr><td>Death: $death</td></tr>\n" : '')
				.($bur ? "<tr><td>Burial: $bur</td></tr>\n" : '')
				.'</table></div>';
		}
		else {
			$result = "<div class='personempty'>&nbsp</div>";
		}
		return $result;
	}

	private function formatChild($child, $childNum) {
		return $this->formatPerson($child, null, $childNum, 'child');
	}

	private function formatSpouse($n) {
		$person = @$this->families[$n][$this->spouseTag];
		$family = @$this->families[$n];
		return $this->formatPerson($person, $family, false, 'spouse');
	}

	private function formatAncestor($n, $tag, $self = false) {
		$person = @$this->families[$n][$tag];
		$family = null;
		if ($self) {
			$class = 'self';
		}
		else {
			if ($tag != 'wife') {
				$family = @$this->families[$n];
			}
			list($familyNumber, $husbandNumber, $wifeNumber, $selfNumber, $spouseNumber) = ShowPedigree::getColorIndexes($n);
			$familyNumber = ($tag == 'wife') ? $wifeNumber : $husbandNumber;
			$class = "ancestor$familyNumber";
		}
		return $this->formatPerson($person, $family, false, $class);
	}

	private function formatSpouseChildren() {
		$result = '';
		$tbl = "<table class='spouse_children_table' cellspacing=0 cellpadding=0>";
		if ($this->spouseTag) {
			for ($i = 0; $i < $this->numSpouseFamilies; $i++) {
				$family = $this->families[$i + ShowPedigree::SPOUSE_FAMILY_BASE];
				if (@$family[$this->spouseTag]) {
					$result .= "$tbl<tr><th colspan=2>Spouse</th></tr><tr><td></td><td>".$this->formatSpouse($i + ShowPedigree::SPOUSE_FAMILY_BASE).'</td></tr>';
					$tbl = '';
				}
				$j = 0;
				if (isset($family['children'])) {
					foreach ($family['children'] as $child) {
						$title = Title::newFromText($child['title'], NS_PERSON);
						if ($j == 0) {
							$result .= "$tbl<tr><th colspan=2>Children</th></tr>";
							$tbl = '';
						}
						$result .= '<tr><td>'
							.(($title != null && $title->exists()) ? $this->formatDownlink($child) : '')
							.'</td><td>'.$this->formatChild($child, $j+1).'</td></tr>';
						$j++;
					}
				}
			}
		}
		else { // this is a family pedigree; get the first family's children
			$family = @$this->families[1];
			$j = 0;
			if (is_array(@$family['children'])) {
				foreach ($family['children'] as $child) {
					$title = Title::newFromText($child['title'], NS_PERSON);
					if ($j == 0) {
						$result .= "$tbl<tr><th colspan=2>Children</th></tr>";
						$tbl = '';
					}
					$result .= '<tr><td>'
						.(($title != null && $title->exists()) ? $this->formatDownlink($child) : '')
						.'</td><td>'.$this->formatChild($child, $j+1).'</td></tr>';
					$j++;
				}
			}
		}
		if (!$tbl) {
			$result .= '</table>';
		}
		return $result;
	}

	private function formatFamily($familyNumber) {
		if (@$this->families[$familyNumber]['exists']) {
			$family = $this->families[$familyNumber];
			$familyTitle = Title::newFromText($family['title'], NS_FAMILY);
			$familyText = '<dl>';
			if (is_array(@$family['children'])) {
				foreach ($family['children'] as $child) {
					$fullname = StructuredData::getFullname($child);
					$birth = $this->formatDatePlace($child, 'birthdate', 'birthplace');
					if (!$birth) {
						$chr = $this->formatDatePlace($child, 'chrdate', 'chrplace');
					}
					else {
						$chr = '';
					}
					$death = $this->formatDatePlace($child, 'deathdate', 'deathplace');
					if (!$death) {
						$bur = $this->formatDatePlace($child, 'burialdate', 'burialplace');
					}
					else {
						$bur = '';
					}
					$familyText .= '<dt>'.$fullname.'</dt>'
						.($birth ? "<dd>Birth: $birth</dd>" : '')
						.($chr ? "<dd>Chr: $chr</dd>" : '')
						.($death ? "<dd>Death: $death</dd>" : '')
						.($bur ? "<dd>Burial: $bur</dd>" : '');
				}
			}
			$familyText .= '</dl>';
			return $this->tm->addTip("family-$familyNumber", 'All Children', $familyText, $familyTitle->getPrefixedURL(), 'F');
		}
		return "";
	}

	private function getEmpty($familyNumber) {
		return @$this->families[$familyNumber]['exists'] ? '' : 'empty';
	}

	public function getPedigreeTable() {
		$this->tm->clearTipTexts();

		$result = "<table id='pd_table' cellspacing=0 cellpadding=0><tr><td rowspan=16>\n".$this->formatSpouseChildren()
			."\n</td>".($this->selfTag ? '<td rowspan=7>&nbsp;</td><td rowspan=8>&nbsp;</td>' : '').'<td rowspan=4>&nbsp;</td><td rowspan=3>&nbsp;</td>'
			."<td rowspan=4>&nbsp;</td><td rowspan=2>&nbsp;</td><td rowspan=1>&nbsp;</td><td rowspan=2>&nbsp;</td><td rowspan=1>&nbsp;</td>\n"
			."<td rowspan=2>".$this->formatAncestor(4,'husband')."</td><td rowspan=2>".$this->formatUplink(4,'husband')."</td></tr>\n"
			."<tr><td rowspan=2>".$this->formatAncestor(2,'husband')."</td><td class='bracket".$this->getEmpty(4)."' rowspan=2>"
				.$this->formatFamily(4)."</td></tr>\n"
			."<tr><td class='bracket".$this->getEmpty(2)."' rowspan=4>".$this->formatFamily(2)."</td><td class='line".$this->getEmpty(4)
				."' rowspan=4>&nbsp;</td><td rowspan=2>".$this->formatAncestor(4,'wife')."</td><td rowspan=2>".$this->formatUplink(4,'wife')."</td></tr>\n"
			."<tr><td rowspan=2>".$this->formatAncestor(1,'husband')."</td><td rowspan=2>&nbsp;</td><td rowspan=2>&nbsp;</td></tr>\n"
			."<tr><td class='bracket".$this->getEmpty(1)."' rowspan=8>".$this->formatFamily(1)."</td><td class='line".$this->getEmpty(2)
				."' rowspan=8>&nbsp;</td><td rowspan=2>".$this->formatAncestor(5,'husband')."</td><td rowspan=2>".$this->formatUplink(5,'husband')."</td></tr>\n"
			."<tr><td rowspan=6>&nbsp;</td><td rowspan=2>".$this->formatAncestor(2,'wife')."</td><td class='bracket".$this->getEmpty(5)."' rowspan=2>"
				.$this->formatFamily(5)."</td></tr>\n"
			."<tr><td rowspan=4>&nbsp;</td><td class='line".$this->getEmpty(5)."' rowspan=4>&nbsp;</td><td rowspan=2>".$this->formatAncestor(5,'wife')."</td><td rowspan=2>"
				.$this->formatUplink(5,'wife')."</td></tr>\n"
			."<tr>".($this->selfTag ? "<td rowspan=2>".$this->formatAncestor(ShowPedigree::SPOUSE_FAMILY_BASE,$this->selfTag, true)."</td>" : "")
				."<td rowspan=2>&nbsp;</td><td rowspan=2>&nbsp;</td></tr>\n"
			."<tr>".($this->selfTag ? "<td class='line".$this->getEmpty(1)."' rowspan=8>&nbsp;</td>" : "")."<td rowspan=2>"
				.$this->formatAncestor(6,'husband')."</td><td rowspan=2>".$this->formatUplink(6,'husband')."</td></tr>\n"
			."<tr>".($this->selfTag ? "<td rowspan=7>&nbsp;</td>" : "")."<td rowspan=2>".$this->formatAncestor(3,'husband')."</td><td class='bracket".$this->getEmpty(6)."' rowspan=2>"
				.$this->formatFamily(6)."</td></tr>\n"
			."<tr><td class='bracket".$this->getEmpty(3)."' rowspan=4>".$this->formatFamily(3)."</td><td class='line".$this->getEmpty(6)."' rowspan=4>&nbsp;</td><td rowspan=2>"
				.$this->formatAncestor(6,'wife')."</td><td rowspan=2>".$this->formatUplink(6,'wife')."</td></tr>\n"
			."<tr><td rowspan=2>".$this->formatAncestor(1,'wife')."</td><td rowspan=2>&nbsp;</td><td rowspan=2>&nbsp;</td></tr>\n"
			."<tr><td rowspan=4>&nbsp;</td><td class='line".$this->getEmpty(3)."' rowspan=4>&nbsp;</td><td rowspan=2>"
				.$this->formatAncestor(7,'husband')."</td><td rowspan=2>".$this->formatUplink(7,'husband')."</td></tr>\n"
			."<tr><td rowspan=3>&nbsp;</td><td rowspan=2>".$this->formatAncestor(3,'wife')."</td><td class='bracket".$this->getEmpty(7)."' rowspan=2>"
				.$this->formatFamily(7)."</td></tr>\n"
			."<tr><td rowspan=2>&nbsp;</td><td class='line".$this->getEmpty(7)."' rowspan=2>&nbsp;</td><td rowspan=2>".$this->formatAncestor(7,'wife')."</td><td rowspan=2>"
				.$this->formatUplink(7,'wife')."</td></tr>\n"
			."<tr><td rowspan=1>&nbsp;</td><td rowspan=1>&nbsp;</td></tr></table>"
			.$this->tm->getTipTexts();

		return $result;
	}

	private function addPlace($place, &$result) {
		if (isset($place)) {
			$place = ShowPedigree::cleanPlace($place);
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

	private function getPlaces($n, &$result) {
		$family = @$this->families[$n];
		if (@$family['exists']) {
			$this->addPlace(@$family['marriageplace'], $result);
			$this->addSpousePlaces(@$family['husband'], $result);
			$this->addSpousePlaces(@$family['wife'], $result);
			if (isset($family['children'])) {
				foreach ($family['children'] as $child) {
					// we don't map child deathplaces
					$this->addPlace(@$child['birthplace'], $result);
//					if (!@$child['chrplace'] && @$child['chrplace']) {
//						$this->addPlace($child['chrplace']);
//					}
				}
			}
		}
	}

	private function addEvent($pf, $type, $familyNumber, &$result) {
		if (isset($pf)) {
			$place = @$pf["{$type}place"];
			$place = ShowPedigree::cleanPlace($place);
			$stdPlace = @$this->stdPlaces[$place];
			if ($stdPlace) {
				$date = @$pf["{$type}date"];
				$name = StructuredData::getFullname($pf);
				$title = Title::newFromText(@$pf['title'], NS_PERSON);
				$url = $title->getLocalURL();
				$lat = $stdPlace['lat'];
				$lng = $stdPlace['lon'];
				$placeTitle = $stdPlace['title'];
				$color = ShowPedigree::$COLORS[$familyNumber];
				$type = substr($type, 0, 1); // we only need first character
				$dateKey = StructuredData::getDateKey($date);
				$result .= '<p n="'.StructuredData::escapeXml($name)
							  .'" u="'.StructuredData::escapeXml($url)
							  .'" p="'.StructuredData::escapeXml($placeTitle)
							  .'" d="'.StructuredData::escapeXml($date)
							  .'" k="'.$dateKey
							  .'" a="'.$lat
							  .'" o="'.$lng
							  .'" t="'.substr($type, 0, 1)
							  .'" c="'.$color
							  ."\"/>";
			}
		}
	}

	private function addEndpoint($pf, $type, &$endpoints) {
		$place = @$pf["{$type}place"];
		if ($place) {
			$place = ShowPedigree::cleanPlace($place);
			$stdPlace = @$this->stdPlaces[$place];
			if ($stdPlace) {
				$date = @$pf["{$type}date"];
				$dateKey = StructuredData::getDateKey($date);
				if (!$dateKey) {
					// set to previous dateKey + 1
					$cnt = count($endpoints);
					if ($cnt) {
						$fields = explode('|', $endpoints[$cnt-1]);
						$dateKey = trim($fields[0]).'1';
					}
					else {
						$dateKey = '0';
					}
				}
				$lat = $stdPlace['lat'];
				$lng = $stdPlace['lon'];
				// we need the space there so dates sort correctly
				$endpoints[] = $dateKey.' |'.$lat.'|'.$lng;
			}
		}
	}

	private function generateEdges($endpoints, $firstNumber, $secondNumber = -1) {
		$result = '';
		$start = null;
		$color = ShowPedigree::$COLORS[$firstNumber];
		if ($secondNumber >= 0) {
			$color .= '|' . ShowPedigree::$COLORS[$secondNumber];
		}
		foreach($endpoints as $endpoint) {
			$fields = explode('|',$endpoint);
			if ($start) {
				$result .= '<e a1="'.$start[1].'" o1="'.$start[2].'" a2="'.$fields[1].'" o2="'.$fields[2].'" c="'.$color."\"/>";
			}
			$start = $fields;
		}
		return $result;
	}

	private function getMapData($n, &$result) {
		$family = @$this->families[$n];

		// calculate color index numbers
		list ($familyNumber, $husbandNumber, $wifeNumber, $selfNumber, $spouseNumber) = ShowPedigree::getColorIndexes($n);

		if (@$family['exists']) {
			$endpoints = array();

			// get marriage
			$this->addEvent($family, 'marriage', $husbandNumber, $result);
			$this->addEndpoint($family, 'marriage', $endpoints);

			// get childbirths
			if (isset($family['children'])) {
				foreach ($family['children'] as $child) {
					// get birth for children
					$this->addEvent($child, 'birth', $familyNumber, $result);
					$this->addEndpoint($child, 'birth', $endpoints);
				}
			}

			// calculate which spouse dies first
			$husbandDeath = StructuredData::getDateKey(@$family['husband']['deathdate']);
			$wifeDeath = StructuredData::getDateKey(@$family['wife']['deathdate']);
			if ($husbandDeath && $wifeDeath) {
				if ($husbandDeath < $wifeDeath) {
					$firstDeathTag = 'husband';
					$secondDeathTag = 'wife';
					$secondDeathNumber = $wifeNumber;
				}
				else {
					$firstDeathTag = 'wife';
					$secondDeathTag = 'husband';
					$secondDeathNumber = $husbandNumber;
				}
			}
			else if ($n >= ShowPedigree::SPOUSE_FAMILY_BASE && $n < ShowPedigree::SPOUSE_FAMILY_BASE + $this->numSpouseFamilies - 1) {
				$firstDeathTag = $this->spouseTag;
				$secondDeathTag = $this->selfTag;
				$secondDeathNumber = $selfNumber;
			}
			else {
				$firstDeathTag = 'husband';
				$secondDeathTag = 'wife';
				$secondDeathNumber = $wifeNumber;
			}

			// include endpoint of first spouse to die in family endpoints
			$this->addEndpoint(@$family[$firstDeathTag], 'death', $endpoints);

			// generate edges from endpoints
			sort($endpoints, SORT_STRING);
			$firstFamilyEndpoint = count($endpoints) > 0 ? $endpoints[0] : null;
			$lastFamilyEndpoint = count($endpoints) > 0 ? $endpoints[count($endpoints)-1] : null;
			$result .= $this->generateEdges($endpoints, $husbandNumber, $wifeNumber);

			// get death edge for last spouse to die (self's death on last spouse-family only)
			if ($lastFamilyEndpoint &&
				 ($secondDeathTag != $this->selfTag || $n <= ShowPedigree::MAX_FAMILIES || $n == ShowPedigree::SPOUSE_FAMILY_BASE + $this->numSpouseFamilies - 1)) {
				$endpoints = array($lastFamilyEndpoint);
				$this->addEndpoint(@$family[$secondDeathTag], 'death', $endpoints);
				$result .= $this->generateEdges($endpoints, $secondDeathNumber);
			}

			// get death info (self's death on last spouse-family only)
			if ($n >= ShowPedigree::SPOUSE_FAMILY_BASE) {
				$this->addEvent(@$family[$this->spouseTag], 'death', $spouseNumber, $result);
				if ($n == ShowPedigree::SPOUSE_FAMILY_BASE + $this->numSpouseFamilies - 1) { // last spouse family
					$this->addEvent(@$family[$this->selfTag], 'death', $selfNumber, $result);
				}
			}
			else {
				$this->addEvent(@$family['husband'], 'death', $husbandNumber, $result);
				$this->addEvent(@$family['wife'], 'death', $wifeNumber, $result);
			}

			// get birth for self-spouses and anyone who doesn't appear in a parent-family
			if ($n >= ShowPedigree::SPOUSE_FAMILY_BASE) {
				$this->addEvent(@$family[$this->spouseTag], 'birth', $spouseNumber, $result);
				if ($n == ShowPedigree::SPOUSE_FAMILY_BASE && @!$this->families[1]['exists']) { // first family, and parent family doesn't exist
					$this->addEvent(@$family[$this->selfTag], 'birth', $selfNumber, $result);
				}
				// add edges from spouse and self to first family event (but don't do this for self for 2nd and later families)
				if ($firstFamilyEndpoint) {
					$endpoints = array($firstFamilyEndpoint);
					$this->addEndpoint(@$family[$this->spouseTag], 'birth', $endpoints);
					$result .= $this->generateEdges($endpoints, $spouseNumber);
					if ($n == ShowPedigree::SPOUSE_FAMILY_BASE) { // first family for self
						$endpoints = array($firstFamilyEndpoint);
						$this->addEndpoint(@$family[$this->selfTag], 'birth', $endpoints);
						$result .= $this->generateEdges($endpoints, $selfNumber);
					}
					else if ($this->prevLastFamilyEndpoint) { // second or later family for self
						$endpoints = array($this->prevLastFamilyEndpoint, $firstFamilyEndpoint);
						$result .= $this->generateEdges($endpoints, $selfNumber);
					}
				}
			}
			else {
				if (@!$this->families[$n * 2]['exists']) {
					$this->addEvent(@$family['husband'], 'birth', $husbandNumber, $result);
				}
				if (@!$this->families[$n * 2 + 1]['exists']) {
					$this->addEvent(@$family['wife'], 'birth', $wifeNumber, $result);
				}
				if ($firstFamilyEndpoint) {
					$endpoints = array($firstFamilyEndpoint);
					$this->addEndpoint(@$family['husband'], 'birth', $endpoints);
					$result .= $this->generateEdges($endpoints, $husbandNumber);
					$endpoints = array($firstFamilyEndpoint);
					$this->addEndpoint(@$family['wife'], 'birth', $endpoints);
					$result .= $this->generateEdges($endpoints, $wifeNumber);
				}
			}

			$this->prevLastFamilyEndpoint = $lastFamilyEndpoint;

		}

		// just in case there are no spouse-families and we're not doing a pedigree for a family, we still need to get info on self
		if ($this->numSpouseFamilies == 0 && $n == 1 && $this->selfTag) {
			$endpoints = array();
			if (@!$this->families[1]['exists']) {
				$this->addEvent(@$this->families[ShowPedigree::SPOUSE_FAMILY_BASE][$this->selfTag], 'birth', $selfNumber, $result);
			}
			$this->addEndpoint(@$this->families[ShowPedigree::SPOUSE_FAMILY_BASE][$this->selfTag], 'birth', $endpoints);
			$this->addEvent(@$this->families[ShowPedigree::SPOUSE_FAMILY_BASE][$this->selfTag], 'death', $selfNumber, $result);
			$this->addEndpoint(@$this->families[ShowPedigree::SPOUSE_FAMILY_BASE][$this->selfTag], 'death', $endpoints);
			$result .= $this->generateEdges($endpoints, $selfNumber);
		}
	}

	private function iterateFamilies($function, &$result) {
		for ($i = 1; $i <= ShowPedigree::MAX_FAMILIES; $i++) {
			$this->$function($i, $result);
		}
		for ($i = 0; $i < $this->numSpouseFamilies; $i++) {
			$this->$function($i + ShowPedigree::SPOUSE_FAMILY_BASE, $result);
		}
	}

	public function getPedigreeMapData() {
		$places = array();
		$this->iterateFamilies('getPlaces', $places);
		$this->stdPlaces = PlaceSearcher::getPlaceTitleLatLong($places);
		$eventXml = '';
		$this->iterateFamilies('getMapData', $eventXml);
		return $eventXml;
	}
}
?>
