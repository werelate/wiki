<?php
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/ESINHandler.php");
require_once("$IP/extensions/structuredNamespaces/Person.php");
require_once("$IP/extensions/structuredNamespaces/Family.php");
require_once("$IP/includes/Title.php");

class GedcomExporter {
	private static $EOL = "\r\n";
	
	public static $EVENT_FACT_TAGS = array(
		"Adoption" => "ADOP",
		"Ancestral File Number" => "AFN",
		"Annulment" => "ANUL",
		"Baptism" => "BAPM",
		"Bar Mitzvah" => "BARM",
		"Bat Mitzvah" => "BATM",
		"Blessing" => "BLES",
		"Birth" => "BIRT",
		"Alt Birth" => "BIRT",
		"Burial" => "BURI",
		"Alt Burial" => "BURI",
		"Caste" => "CAST",
		"Cause of Death" => "CAUS",
		"Census" => "CENS",
		"Christening" => "CHR",
		"Alt Christening" => "CHR",
		"Citizenship" => "NATU",
		"Confirmation" => "CONF",
		"Cremation" => "CREM",
		"Death" => "DEAT",
		"Alt Death" => "DEAT",
		"Degree" => "_DEG",
		"Divorce" => "DIV",
		"Divorce Filing" => "DIVF",
		"Education" => "EDUC",
		"Emigration" => "EMIG",
		"Engagement" => "ENGA",
		"Excommunication" => "_EXCM",
		"First Communion" => "FCOM",
		"Graduation" => "GRAD",
		"Immigration" => "IMMI",
		"Marriage Banns" => "MARB",
		"Marriage Contract" => "MARC",
		"Marriage License" => "MARL",
		"Marriage" => "MARR",
		"Alt Marriage" => "MARR",
		"Marriage Settlement" => "MARS",
		"Medical" => "_MDCL",
		"Military" => "_MILT",
		"Mission" => "_MISN",
		"Namesake" => "_NAMS",
		"Nationality" => "NATI",
		"Naturalization" => "NATU",
		"Occupation" => "OCCU",
		"Ordination" => "ORDN",
		"Physical Description" => "DSCR",
		"Probate" => "PROB",
		"Property" => "PROP",
		"Reference Number" => "REFN",
		"Religion" => "RELI",
		"Residence" => "RESI",
		"Retirement" => "RETI",
		"Separation" => "_SEPARATED",
		"Soc Sec No" => "SSN",
		"Will" => "WILL"
	);

	private $fh;
	private $userName;
	private $treeName;
	private $treeId;
	private $primary;
	private $titleMap;
	private $titleList;
	private $titleOnlySources;
	private $repositories;
	private $cntPeople;
	private $cntFamilies;
	private $cntSources;
	private $cntRepos;
	private $title;
	private $xml;
	private $contents;
	private $refdCitations;
	private $refdNotes;
	private $refdImages;
	
	public function __construct() {
		$this->titleMap = array();
		$this->titleList = array();
		$this->titleOnlySources = array();
		$this->repositories = array();
		$this->cntPeople = $this->cntFamilies = $this->cntRepos = 0;
		$this->cntSources = 1; // WeRelate source
		$this->fh = null;
	}
	
	private function generateId($ns) {
		$id = '';
		if ($ns == NS_PERSON) {
			$id = 'I'.(++$this->cntPeople);
		}
		else if ($ns == NS_FAMILY) {
			$id = 'F'.(++$this->cntFamilies);
		}
		else if ($ns == NS_SOURCE || $ns == NS_MYSOURCE) {
			$id = 'S'.(++$this->cntSources);
		}
		else if ($ns == NS_REPOSITORY) {
			$id = 'R'.(++$this->cntRepos);
		}
		return $id;
	}
	
	private function getRepositoryId($repoName, $repoAddr) {
		$key = "$repoName^|^$repoAddr";
		$id = @$this->repositories[$key];
		if (!$id) {
			$id = $this->generateId(NS_REPOSITORY);
			$this->repositories[$key] = $id;
		}
		return $id;
	}
	
	// get the user-entered place
	private function getGedcomPlace($place) {
		$pos = mb_strpos($place, '|');
		if ($pos !== false) {
			$place = mb_substr($place, $pos+1);
		}
		return $place;
	}
	
	private function wl($s) {
		fwrite($this->fh, $s . GedcomExporter::$EOL);
	}
	
	private function cleanUrl($url) {
		return str_replace(array('%28', '%29', '%2C'), array('(', ')', ','), $url);
	}

	private function writeHeader() {
		global $wgLang, $wgServer;
		$date = $wgLang->date(wfTimestampNow(), true, false);
		
		$this->wl("0 HEAD");
		$this->wl("1 SOUR WeRelate.org");
		$this->wl("2 NAME WeRelate.org");
		$this->wl("2 VERS 0.9");
		$this->wl("1 DATE $date");
		$this->wl("1 SUBM @SUB1@");
		$this->wl("1 FILE {$this->treeName}.ged");
		$this->wl("1 GEDC");
		$this->wl("2 VERS 5.5");
		$this->wl("2 FORM LINEAGE_LINKED");
		$this->wl("1 CHAR UTF-8");
		$this->wl("0 @SUB1@ SUBM");
		$this->wl("1 NAME {$this->userName}");
		$this->wl("1 ADDR $wgServer/wiki/{$this->userName}");
	}

	private function writeTrailer() {
		$this->wl("0 TRLR");
	}

	private function appendContents($s) {
		if ($this->contents) {
			$this->contents .= "\n\n";
		}
		$this->contents .= $s;
	}
	
	private function writeText($level, $tag, $text) {
		$lines = explode("\n", $text);
		$lineWritten = false;
		foreach ($lines as $line) {
			while (true) {
				if (mb_strlen($line) > 200) {
					$part = mb_substr($line, 0, 200);
					$line = mb_substr($line, 200);
				}
				else {
					$part = $line;
					$line = '';
				}
				$this->wl("$level $tag $part");
				if (!$lineWritten) $level++;
				$lineWritten = true;
				if (!$line) {
					break;
				}
				$tag = "CONC";
			}
			$tag = "CONT";
		}
	}
	
	private function writeNote($level, $text) {
		if ($text) {
			$this->writeText($level, "NOTE", $text);
		}
	}
	
	private function writeImage($level, $image) {
		global $wgServer;
		
		$title = Title::newFromText((string)$image['filename'], NS_IMAGE);
		$caption = (string)$image['caption'];
		if ($title) {
			$noteText = "References image ".$this->cleanUrl($title->getFullURL()).($caption ? " with caption $caption" : '');
			if ($level == 1) {
				$this->appendContents($noteText);
			}
			else {
				$this->writeNote($level, $noteText);
			}
		}
	}
	
	private function writeCitation($level, $sc) {
		$title = (string)$sc['title'];
		
		// get or construct an id
		$ns = 0;
		if (mb_stripos($title, "source:") === 0) {
			$ns = NS_SOURCE;
			$title = mb_substr($title, strlen('source:'));
		}
		else if (mb_stripos($title, "mysource:") === 0) {
			$ns = NS_MYSOURCE;
			$title = mb_substr($title, strlen('mysource:'));
		}
		$pos = mb_strpos($title, '|');
		if ($pos !== false) {
			$title = mb_substr($title, 0, $pos);
		}
		$t = null;
		if ($ns) {
			$t = Title::newFromText($title, $ns);
			if (!$t) {
				echo "Bad source title: $title ns=$ns treeId={$this->treeId}\n";
			}
			else {
				$t = StructuredData::getRedirectToTitle($t);
				$ns = $t->getNamespace(); // MySources can be redirected to Sources
			}
		}
		if ($t) {
			$fullTitle = $t->getFullText();
			$id = @$this->titleMap[$fullTitle];
			if (!$id) {
				$id = $this->generateId($ns);
				$this->titleMap[$fullTitle] = $id;
				$this->titleList[] = $fullTitle;
			}
		}
		else {
			$id = @$this->titleOnlySources[$title];
			if (!$id) {
				$id = $this->generateId(NS_MYSOURCE);
				$this->titleOnlySources[$title] = $id;
			}
		}

		// gather citation field values
		$recordName = (string)$sc['record_name'];
		$page = (string)$sc['page'];
		$date = (string)$sc['date'];
		$quality = (string)$sc['quality'];
		if (strlen($quality) > 0) {
			// convert old alpha form to numeric
			if (array_key_exists($quality, ESINHandler::$QUALITY_OPTIONS)) {
				$quality = ESINHandler::$QUALITY_OPTIONS[$quality];
			}
		}
		$text = (string)$sc['text'];
		$text .= (string)$sc; // get from both until we standardize on the latter
		if ($recordName) {
			if ($text) {
				$recordName .= "\n\n";
			}
			$text = "Record name: " . $recordName . $text;
		}

		$this->wl("$level SOUR @$id@");
		$levelPlusOne = $level+1;
		if ($page) $this->wl("$levelPlusOne PAGE $page");
		if (strlen($quality) > 0) $this->wl("$levelPlusOne QUAY $quality");
		if ($date || $text) {
			$this->wl("$levelPlusOne DATA");
			$levelPlusTwo = $levelPlusOne+1;
			if ($date) {
				$this->wl("$levelPlusTwo DATE $date");
			}
			if ($text) {
				if ($text) $this->writeText($levelPlusTwo, "TEXT", $text);
			}
		}
		$this->writeNoteRefs($levelPlusOne, (string)$sc['notes']);
		$this->writeImageRefs($levelPlusOne, (string)$sc['images']);
	}
	
	private function getDownloadText($urlLabel) {
		$url = $this->cleanUrl($this->title->getFullURL());
		$history = $this->cleanUrl($this->title->getFullURL()."?action=history");
//		return "Content downloaded from WeRelate.org under the CC-BY-SA license: http://creativecommons.org/licenses/by-sa/3.0\n".
		return "$urlLabel: $url\n".
				 "Authors: $history";
	}
	
	private function writeWeRelateCitation() {
		global $wgLang;
		
		$this->wl("1 SOUR @S1@");
		$date = $wgLang->date(wfTimestampNow(), true, false);
		$url = $this->cleanUrl($this->title->getFullURL());
//		$this->wl("2 PAGE $url");
		$this->wl("2 DATA");
		$this->wl("3 DATE $date");
		$this->writeText(3, "TEXT", $this->getDownloadText("Current version"));
//		$this->writeText(2, "NOTE", $this->getDownloadText("Current version"));
	}
	
	private function writeCitationRefs($level, $citationRefs) {
		if ($citationRefs) {
			$refs = explode(",", $citationRefs);
			foreach ($refs as $ref) {
				$ref = trim($ref);
				foreach ($this->xml->source_citation as $sc) {
					$id = (string)$sc['id'];
					if ($id == $ref) {
						$this->writeCitation($level, $sc);
						$this->refdCitations[$id] = true;
					}
				}
			}
		}
	}
	
	private function writeNoteRefs($level, $noteRefs) {
		if ($noteRefs) {
			$refs = explode(",", $noteRefs);
			foreach ($refs as $ref) {
				$ref = trim($ref);
				foreach ($this->xml->note as $note) {
					$id = (string)$note['id'];
					if ($id == $ref) {
						$text = (string)$note['text'];
						$text .= (string)$note; // get from both until we standardize on the latter
						$this->writeNote($level, $note);
						$this->refdNotes[$id] = true;
					}
				}
			}
		}
	}
	
	private function writeImageRefs($level, $imageRefs) {
		if ($imageRefs) {
			$refs = explode(",", $imageRefs);
			foreach ($refs as $ref) {
				$ref = trim($ref);
				foreach ($this->xml->image as $image) {
					$id = (string)$image['id'];
					if ($id == $ref) {
						$this->writeImage($level, $image);
						$this->refdImages[$id] = true;
					}
				}
			}
		}
	}
	
	private function writeCitations() {
		foreach ($this->xml->source_citation as $sc) {
			$id = (string)$sc['id'];
			if (!@$this->refdCitations[$id]) {
				$this->writeCitation(1, $sc);
			}
		}
		
		$this->writeWeRelateCitation();
	}
	
	private function writeImages() {
		foreach ($this->xml->image as $image) {
			$id = (string)$image['id'];
			if (!@$this->refdImages[$id]) {
				$this->writeImage(1, $image);
			}
		}
	}
	
	private function writeNotes() {
		$this->writeNote(1, $this->contents);
		
		foreach ($this->xml->note as $note) {
			$id = (string)$note['id'];
			if (!@$this->refdNotes[$id]) {
				$text = (string)$note['text'];
				$text .= (string)$note; // get from both until we standardize on the latter
				$this->writeNote(1, $text);
			}
		}
	}
	
	private function writeName($name) {
		$fullName = StructuredData::getFullname($name, true);
		$this->wl("1 NAME $fullName");
		$namePiece = (string)$name['title_prefix'];
		if ($namePiece) $this->wl("2 NPFX $namePiece");
		$namePiece = (string)$name['given'];
		if ($namePiece) $this->wl("2 GIVN $namePiece");
		$namePiece = (string)$name['surname'];
		if ($namePiece) $this->wl("2 SURN $namePiece");
		$namePiece = (string)$name['title_suffix'];
		if ($namePiece) $this->wl("2 NSFX $namePiece");
		$type = (string)$name['type'];
		if ($type) {
			if ($type == 'Baptismal Name') {
				$type = 'baptismal';
			}
			else if ($type == 'Immigrant Name') {
				$type = 'immigrant';
			}
			else if ($type == 'Married Name') {
				$type = 'married';
			}
			else if ($type == 'Religious Name') {
				$type = 'religious';
			}
			else {
				$type = 'aka'; // default
			}
			$this->wl("2 TYPE $type");
		}
		$this->writeCitationRefs(2, (string)$name['sources']);
		$this->writeNoteRefs(2, (string)$name['notes']);
	}
	
	private function writePersonFamilyRef($ref, $tag, $label, $ns) {
		global $wgServer;
		
		$title = (string)$ref['title'];
		$t = Title::newFromText($title, $ns);
		$fullTitle = $t->getFullText();
		$id = @$this->titleMap[$fullTitle];
		if ($id) {
			$this->wl("1 $tag @$id@");
		}
		else {
			$this->appendContents($label . $this->cleanUrl($t->getFullURL()));
		}
	}
	
	private function writeEvent($ef) {
		$type = (string)$ef['type'];
		$desc = (string)$ef['desc'];
		$date = (string)$ef['date'];
		$place = $this->getGedcomPlace((string)$ef['place']);
		$tag = @GedcomExporter::$EVENT_FACT_TAGS[$type];
		if ($tag) {
			$this->wl("1 $tag $desc");
		}
		else {
			$this->wl("1 EVEN $desc");
			$this->wl("2 TYPE $type");
		}
		
		if ($date) {
			$this->wl("2 DATE $date");
		}
		if ($place) {
			$this->wl("2 PLAC $place");
		}
		$this->writeCitationRefs(2, (string)$ef['sources']);
		$this->writeNoteRefs(2, (string)$ef['notes']);
		$this->writeImageRefs(2, (string)$ef['images']);
	}

	private function writeEvents() {
		// write standard events first
		foreach ($this->xml->event_fact as $ef) {
			$type = (string)$ef['type'];
			if (in_array($type, Person::$STD_EVENT_TYPES) || 
			    in_array($type, Family::$STD_EVENT_TYPES)) {
				$this->writeEvent($ef);
			}
		}
		// now write the rest of the events (including alt-standard events)
		foreach ($this->xml->event_fact as $ef) {
			$type = (string)$ef['type'];
			if (!in_array($type, Person::$STD_EVENT_TYPES) &&
			    !in_array($type, Family::$STD_EVENT_TYPES)) {
				$this->writeEvent($ef);
			}
		}
	}
	
	private function writePerson($id, $uid) {
		$this->wl("0 @$id@ INDI");
      $this->wl("1 _UID $uid");
		if (isset($this->xml)) {
			if (isset($this->xml->name)) {
				$this->writeName($this->xml->name);
			}
			foreach ($this->xml->alt_name as $name) {
				$this->writeName($name);
			}
			$gender = (string)$this->xml->gender;
			if ($gender) $this->wl("1 SEX $gender");
			foreach ($this->xml->child_of_family as $family) {
				$this->writePersonFamilyRef($family, "FAMC", "Parent family not included in tree: ", NS_FAMILY);
			}
			foreach ($this->xml->spouse_of_family as $family) {
				$this->writePersonFamilyRef($family, "FAMS", "Spouse family not included in tree: ", NS_FAMILY);
			}
			$this->writeEvents();
			$this->writeCitations();
			$this->writeImages();
			$this->writeNotes();
		}
	}
	
	private function writeFamily($id) {
		$this->wl("0 @$id@ FAM");
		if (isset($this->xml)) {
			foreach ($this->xml->husband as $mbr) {
				$this->writePersonFamilyRef($mbr, "HUSB", "Husband not included in tree: ", NS_PERSON);
			}
			foreach ($this->xml->wife as $mbr) {
				$this->writePersonFamilyRef($mbr, "WIFE", "Wife not included in tree: ", NS_PERSON);
			}
			foreach ($this->xml->child as $mbr) {
				$this->writePersonFamilyRef($mbr, "CHIL", "Child not included in tree: ", NS_PERSON);
			}
			$this->writeEvents();
			$this->writeCitations();
			$this->writeImages();
			$this->writeNotes();
		}
	}
	
	private function writeSource($id) {
		$this->wl("0 @$id@ SOUR");
		if (isset($this->xml)) {
			$sourceTitle = (string)$this->xml->source_title;
			$subtitle = (string)$this->xml->subtitle;
		}
		else {
			$sourceTitle = '';
			$subtitle = '';
		}
		if (!$sourceTitle) $sourceTitle = $this->title->getText();
		if ($subtitle) {
			if ($sourceTitle) {
				$subtitle = " : " . $subtitle;
			}
			$sourceTitle .= $subtitle;
		}
		if ($sourceTitle) $this->wl("1 TITL $sourceTitle");
		
		if (isset($this->xml)) {
			$author = (string)$this->xml->author;
			if ($author) $this->wl("1 AUTH $author");
			$pubFacts = '';
			$seriesName = (string)$this->xml->series_name;
			if ($seriesName) $pubFacts .= "$seriesName. ";
			$placeIssued = (string)$this->xml->place_issued;
			if ($placeIssued) $pubFacts .= "$placeIssued: ";
			$publisher = (string)$this->xml->publisher;
			if ($publisher) $pubFacts .= "$publisher, ";
			$dateIssued = (string)$this->xml->date_issued;
			if ($dateIssued) $pubFacts .= "$dateIssued. ";
			$pages = (string)$this->xml->pages;
			if ($pages) $pubFacts .= "volume / film# / pages $pages. ";
			$references = (string)$this->xml->references;
			if ($references) $pubFacts .= "references / cites: $references. ";
			$pubInfo = (string)$this->xml->publication_info;
			if ($pubInfo) $pubFacts .= "$pubInfo. ";
			$ending = mb_substr($pubFacts, mb_strlen($pubFacts)-2);
			if ($ending == ', ' || $ending == ': ') $pubFacts = mb_substr($pubFacts, 0, mb_strlen($pubFacts)-2).'.';
			$pubFacts = trim($pubFacts);
			if ($pubFacts) $this->writeText(1, "PUBL", $pubFacts);
		
//			$this->appendContents($this->getDownloadText("Full source text"));
//			$this->writeText(1, "TEXT", $this->contents);
			$this->writeText(1, "TEXT", ""); // workaround for Legacy bug
			$this->writeText(1, "NOTE", $this->getDownloadText("Full source text"));
		}
	}
	
	private function writeWeRelateSource() {
//		$title = Title::newFromText("WeRelate:GNU Free Documentation License");
//		$gfdlRevision = StructuredData::getRevision($title, true);
//		$gfdlText =& $gfdlRevision->getText();

		$this->wl("0 @S1@ SOUR");
		$this->wl("1 TITL WeRelate.org");
		$this->writeText(1, "TEXT", "Text content at WeRelate.org is available under the Creative Commons Attribution/Share-Alike License: http://creativecommons.org/licenses/by-sa/3.0\n");
//								"Additional terms may apply.  See http://werelate.org/wiki/WeRelate:Terms_of_Use for details.");
//								$gfdlText);
	}
	
	private function writeMySource($id) {
		$this->wl("0 @$id@ SOUR");
		
		$titleString = $this->title->getText();
		// remove username/  and (unique id)
		$pos = mb_strpos($titleString, '/');
		if ($pos !== false) {
			$titleString = mb_substr($titleString, $pos+1);
		}
		if (preg_match('/\(\d+\)$/', $titleString)) {
			$pos = mb_strrpos($titleString, '(');
			$titleString = trim(mb_substr($titleString, 0, $pos));
		}
      $this->wl("1 TITL $titleString");
      
      if (isset($this->xml)) {
	      $author = (string)$this->xml->author;
	      if ($author) $this->wl("1 AUTH $author");
			$abbrev = (string)$this->xml->abbrev;
	      if ($abbrev) $this->wl("1 ABBR $abbrev");
	      $pubInfo = (string)$this->xml->publication_info;
	      if ($pubInfo) $this->wl("1 PUBL $pubInfo");
	      
	      $callNumber = (string)$this->xml->call_number;
	      $repoName = (string)$this->xml->repository_name;
	      $repoAddr = (string)$this->xml->repository_addr;
	      if ($repoName || $repoAddr || $callNumber) {
	      	$repoId = $this->getRepositoryId($repoName, $repoAddr);
	      	$this->wl("1 REPO @$repoId@");
	      	if ($callNumber) {
	      		$this->wl("2 CALN $callNumber");
	      	}
	      }
	      
	      $url = (string)$this->xml->url;
	      if ($url) $this->appendContents("URL: $url");
	      $type = (string)$this->xml->type;
	      if ($type) $this->appendContents("Type: $type");
	      
//			$this->appendContents($this->getDownloadText("Current version"));
	   	$this->writeText(1, "NOTE", $this->getDownloadText("Current version"));
      }
	   if ($this->contents) $this->writeText(1, "TEXT", $this->contents);
	}
	
	private function writePage($fullTitle) {
		$this->title = Title::newFromText($fullTitle);
		$id = $this->titleMap[$fullTitle];
		$obj = null;
		$this->xml = null;
		$this->contents = null;
		$ns = $this->title->getNamespace();
		if ($this->title->exists()) {
			if ($ns == NS_PERSON) {
				$obj = new Person($this->title->getText());
			}
			else if ($ns == NS_FAMILY) {
				$obj = new Family($this->title->getText());
			}
			else if ($ns == NS_SOURCE) {
				$obj = new Source($this->title->getText());
			}
			else if ($ns == NS_MYSOURCE) {
				$obj = new MySource($this->title->getText());
			}
			if ($obj) {
				$obj->loadPage();
				$this->xml = $obj->getPageXml();
				if ($this->xml == null) echo "XML not found for title=".$this->title->getPrefixedText()." treeId={$this->treeId}\n";
				$this->contents = $obj->getPageContents();
			}
		}
		$this->refdCitations = array();
		$this->refdNotes = array();
		$this->refdImages = array();
		if ($ns == NS_PERSON) {
         $uid = "WeRelate:".$this->title->getNsText().":".$this->title->getDBkey();
			$this->writePerson($id, $uid);
		}
		else if ($ns == NS_FAMILY) {
			$this->writeFamily($id);
		}
		else if ($ns == NS_SOURCE) {
			$this->writeSource($id);
		}
		else if ($ns == NS_MYSOURCE) {
			$this->writeMySource($id);
		}
	}
	
	private function writeTitleOnlySource($id, $title) {
		$this->wl("0 @$id@ SOUR");
		$this->wl("1 TITL $title");
	}
	
	private function writeRepository($id, $repoName, $repoAddr) {
		$this->wl("0 @$id@ REPO");
		if ($repoName) $this->wl("1 NAME $repoName");
		if ($repoAddr) $this->writeText(1, "ADDR", $repoAddr);
	}

	/**
	 * Returns an error message in case of error
	 *
	 * @param unknown_type $treeId
	 * @param unknown_type $filename
	 * @return unknown
	 */
	public function exportGedcom($treeId, $filename) {
		$this->treeId = $treeId;
	   $dbr =& wfGetDB( DB_SLAVE );
	   $dbr->ignoreErrors(true);

		// read tree
		$row = $dbr->selectRow( 'familytree', array('ft_user', 'ft_name', 'ft_primary_namespace', 'ft_primary_title'), array('ft_tree_id' => $this->treeId));
		$this->userName = $row->ft_user;
		$this->treeName = $row->ft_name;
		$this->primary = '';
		$primaryId = '';
		$title = Title::makeTitle($row->ft_primary_namespace, $row->ft_primary_title);
		if ($title) $title = StructuredData::getRedirectToTitle($title);
		if ($title && $title->getNamespace() == NS_PERSON) {
			$this->primary = $title->getFullText();
			$primaryId = $this->generateId(NS_PERSON);
		}
		
		// read pages in the tree
	   $rows = $dbr->select('familytree_page', array('fp_namespace', 'fp_title'), array('fp_tree_id' => $this->treeId), 'GedcomExport::select');
  		if ($dbr->lastErrno() > 0) {
  			return "Error reading tree $treeId";
  		}
  		else if ($rows !== false) {
  			while ($row = $dbr->fetchObject($rows)) {
  				$title = Title::makeTitle($row->fp_namespace, $row->fp_title);
				if (!$title) {
					echo "Bad title: ns={$row->fp_namespace} title={$row->fp_title} treeId={$this->treeId}\n";
				}
				else {
					$title = StructuredData::getRedirectToTitle($title);
					$ns = $title->getNamespace();
	  				$fullTitle = $title->getFullText();
	  				if (!@$this->titleMap[$fullTitle]) { // don't add this page if it's been added already (can happen in the case of redirects)
		  				$id = ($this->primary && $this->primary == $fullTitle ? $primaryId : $this->generateId($ns));
		  				if ($id) { // pages without an id don't go into the GEDCOM
			  				$this->titleMap[$fullTitle] = $id;
		  					$this->titleList[] = $fullTitle;
		  				}
	  				}
				}
  			}
 		   $dbr->freeResult($rows);
  		}
	   
		// create the file
		$this->fh = fopen($filename, "w");
		if (!$this->fh || !is_writable($filename)) {
			return "Unable to create gedcom file for $treeId";
		}
		
		// write the header
		$this->writeHeader();
		
		// write the primary page
		if ($this->primary) {
			$this->writePage($this->primary);
		}
		
		// read and write each page
		for ($i = 0; $i < count($this->titleList); $i++) {
			if ($this->primary != $this->titleList[$i]) {
				$this->writePage($this->titleList[$i]);
			}
		}
		
		foreach ($this->titleOnlySources as $title => $id) {
			$this->writeTitleOnlySource($id, $title);
		}
		
		foreach ($this->repositories as $key => $id) {
			list($repoName, $repoAddr) = explode("^|^", $key, 2);
			$this->writeRepository($id, $repoName, $repoAddr);
		}
		
		$this->writeWeRelateSource();
		
		// write the trailer
		$this->writeTrailer();
		
		// close the file
		fclose($this->fh);
		
		return '';
	}
}

?>
