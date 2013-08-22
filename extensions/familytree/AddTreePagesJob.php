<?php
require_once("$IP/includes/JobQueue.php");
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

class AddTreePagesJob extends Job {
	private $allTrees;
	private $treeIds;
	private $dbw;
	private $includeAncestorChildren;
	private $count;
	private $addedPages;
	
	function __construct($params, $id = 0 ) {
	   // pass up a fake title
		parent::__construct('addTreePages', Title::makeTitle(NS_SPECIAL, 'AddTreePages'), $params, $id );
	}
	
	function readPageXml($title) {
	   $page = null;
	   $xml = null;
	   $titleString = $title->getText();
	   if ($title->getNamespace() == NS_PERSON) {
	      $page = new Person($titleString);
	   }
	   else {
	   	$page = new Family($titleString);
	   }
	   if ($page) {
		   $page->loadPage();
		   $xml = $page->getPageXml();
	   }
	   return $xml;
	}
	
	function addPage($title) {
		$titleString = $title->getPrefixedText();
		if (@$this->addedPages[$titleString]) return true; // already added
		
		if (!FamilyTreeUtil::updateTrees($this->dbw, $title, 0, $this->allTrees, array(), $this->treeIds, false)) return false;
		$this->addedPages[$titleString] = 1;
//echo("added $titleString\n");		
		if ($this->count++ > 50) {  // commit every 50 pages so we don't keep a tx open too long
			$this->dbw->commit();
			$this->dbw->begin();
			$this->count = 0;
		}
		return true;
	}
	
	function addAncestors($title, $ancGenerations) {
		if ($ancGenerations <= 0) return true;
		
		// read this page xml
		$xml = $this->readPageXml($title);
		if (!isset($xml)) return true;
		
		// person page
		if ($title->getNamespace() == NS_PERSON) {
			// add parent families
			foreach ($xml->child_of_family as $f) {
				$t = Title::newFromText((string)$f['title'], NS_FAMILY);
				if (!$this->addPage($t)) return false;
				if (!$this->addAncestors($t, $ancGenerations)) return false;
			}
		}
		else { // family page
			// add father and mother
			foreach ($xml->husband as $p) {
				$t = Title::newFromText((string)$p['title'], NS_PERSON);
				if (!$this->addPage($t)) return false;
				if (!$this->addAncestors($t, $ancGenerations-1)) return false;
			}
			foreach ($xml->wife as $p) {
				$t = Title::newFromText((string)$p['title'], NS_PERSON);
				if (!$this->addPage($t)) return false;
				if (!$this->addAncestors($t, $ancGenerations-1)) return false;
			}
			if ($this->includeAncestorChildren) {
				// add children
				foreach ($xml->child as $p) {
					$t = Title::newFromText((string)$p['title'], NS_PERSON);
					if (!$this->addPage($t)) return false;
				}
			}
		}
		return true;
	}
	
	function addDescendants($title, $descGenerations) {
		if ($descGenerations <= 0) return true;
		
		$xml = $this->readPageXml($title);
		if (!isset($xml)) return true;
		
		if ($title->getNamespace() == NS_PERSON) {
			// add spouse families
			foreach ($xml->spouse_of_family as $f) {
				$t = Title::newFromText((string)$f['title'], NS_FAMILY);
				if (!$this->addPage($t)) return false;
				if (!$this->addDescendants($t, $descGenerations)) return false;
			}
		}
		else { // family page
			// add parents to make sure we have both spouses
			foreach ($xml->husband as $p) {
				$t = Title::newFromText((string)$p['title'], NS_PERSON);
				if (!$this->addPage($t)) return false;
			}
			foreach ($xml->wife as $p) {
				$t = Title::newFromText((string)$p['title'], NS_PERSON);
				if (!$this->addPage($t)) return false;
			}
			// add children
			foreach ($xml->child as $p) {
				$t = Title::newFromText((string)$p['title'], NS_PERSON);
				if (!$this->addPage($t)) return false;
				if (!$this->addDescendants($t, $descGenerations-1)) return false;
			}
		}
		return true;
	}

	/**
	 * Run a refreshLinks job
	 * @return boolean success
	 */
	function run() {
		global $wgUser, $wgTitle;

		$userName = $this->params['user'];
		$wgUser = User::newFromName($userName);
		$this->treeIds = explode(',',$this->params['trees']);
		$titleString = $this->params['title'];
		$title = Title::newFromText($titleString);
		$wgTitle = $this->title;  // FakeTitle (the default) generates errors when accessed, and sometimes I log wgTitle, so set it to something else
		$ancGenerations = $this->params['ancGenerations'];
		$this->includeAncestorChildren = $this->params['includeAncestorChildren'];
		$descGenerations = $this->params['descGenerations'];
		$this->allTrees = array();
		foreach ($this->treeIds as $treeId) {
			$this->allTrees[] = array('id' => $treeId);
		}
		$this->count = 0;
		$this->addedPages = array();
		$this->addedPages[] = $title->getPrefixedText(); // already added
		
		// cap # generations in each direction
		if ($ancGenerations > 20) $ancGenerations = 20;
		if ($descGenerations > 5) $descGenerations = 5;

		$this->dbw =& wfGetDB( DB_MASTER );
	   $this->dbw->begin();
	   $this->dbw->ignoreErrors(true);
	   $result = true;
	   
	   if ($result) {
	   	$result = $this->addAncestors($title, $ancGenerations);
	   }
	   if ($result) {
	   	$result = $this->addDescendants($title, $descGenerations);
	   }
	   
      if ($result) {
		   $this->dbw->commit();
		   return true;
		}
		else {
		   $this->dbw->rollback();
		   $this->error = "Error adding pages: trees={$this->params['trees']} user=$userName title={$this->params['title']}".
		   					"ancGenerations=$ancGenerations includeAncestorChildren=$includeAncestorChildren descGenerations=$descGenerations\n";
		   return false;
		}
	}
}

?>
