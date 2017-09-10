<?php
require_once("commandLine.inc");
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

function printUsage() {
    die("Usage: <count 0=all> [update]\n");
}

if ($_SERVER['argc'] < 2) {
	printUsage();
}

$cnt = intval($_SERVER['argv'][1]);
$update = ($_SERVER['argc'] == 3 && $_SERVER['argv'][2] == 'update');
echo "count=$cnt update=$update\n";

$gm = new GenerateMergelogs(!$update);
$gm->processRevisions($cnt);
$gm->writeFinalMergelogs();

class GenerateMergelogs {
	private static $LOG_LEVELS = array("INFO", "WARNING", "ERROR", "COUNT");
//	private static $LOG_LEVELS = array("WARNING", "ERROR", "COUNT");
	const START_REVID = 10073800;
	const END_REVID = 10788600;
//	const END_REVID = 10500000;
	const MAX_INTERVAL = 5;

	// userMerges[userid]{firstTimestamp, firstRevid, lastSeconds, lastTimestamp, lastRevid, error, merges[]{target{revPage, preRevid, postRevid, title, comment}, sources[]{same as target}}
	private $userMerges;
	private $testing;
	private $dbw;

	public function __construct($testing) {
		$this->userMerges = array();
		$this->testing = $testing;
      $this->dbw =& wfGetDB(DB_MASTER);
	}
	
	private function log($type, $msg, $userid, $timestamp) {
		if (in_array($type, self::$LOG_LEVELS)) {
			echo "$type userid=$userid timestamp=$timestamp msg=$msg\n";
		}
	}
	
	private function readData($title, $revid) {
		$data = array();
		$data['title'] = $title->getText();
		if ($title->getNamespace() == NS_FAMILY) {
			$obj = new Family($title->getText());
		}
		else {
			$obj = new Person($title->getText());
		}
		$obj->loadPage($revid);
		$xml = $obj->getPageXml();
		if (!$xml) {
			return null;
		}
		MergeForm::readXmlData($title->getNamespace() == NS_FAMILY, $xml, $data);
		return $data;
	}
	
	private function getMergeRowRevid($titleString, $timestamp, &$merges, &$mergeRows) {
		for ($m = 0; $m < count($merges); $m++) {
			if ($merges[$m]->target->title->getNamespace() == NS_PERSON) {
				if ($merges[$m]->target->title->getText() == $titleString) {
					return array($m, $merges[$m]->target->preRevid);
				}
				foreach ($merges[$m]->sources as $source) {
					if ($source->title->getText() == $titleString) {
						return array($m, $source->preRevid);
					}
				}
			}
		}
		$title = Title::newFromText($titleString, NS_PERSON);
		$revid = StructuredData::getRevidForTimestamp($title, $timestamp);
		if ($revid) {
			for ($m = 0; $m < count($mergeRows); $m++) {
				foreach ($mergeRows[$m]->revids as $cell) {
					if (in_array($revid, $cell)) {
						return array($m, $revid);
					}
				}
			}
		}
		return array (-1, $revid);
	}
	
	private function recordFamilyMembers($familyMembers, $role, $col, &$merges, $timestamp, $familyCount, &$mergeRows, &$seenRevids) {
		foreach ($familyMembers as $familyMember) {
			$titleString = $familyMember['title'];
			list ($row, $revid) = $this->getMergeRowRevid($titleString, $timestamp, $merges, $mergeRows);
			if ($revid) {
				if ($row < 0) {
					$row = count($mergeRows);
					$mergeRows[$row] = (object) array('role' => '', 'revids' => array());
					for ($p = 0; $p < $familyCount; $p++) {
						$mergeRows[$row]->revids[] = array();
					}
				}
				$mergeRows[$row]->role = $role;
				$mergeRows[$row]->revids[$col][] = $revid;
				$seenRevids[$revid] = 1;
			}
			else {
				$this->log("WARNING", "revid not found for $titleString", 0, $timestamp);
			}
		}
	}
	
	private function getNextRevidComment($revPage, $revid) {
		$row = $this->dbw->selectRow('revision', array('rev_id', 'rev_comment'), array('rev_page' => $revPage, 'rev_id > ' . $revid), 
										'GenerateMergeLogs::getNextRevidComment', array('ORDER BY' => 'rev_id'));
		if ($row) {
			return array($row->rev_id, $row->rev_comment);
		}
		return array(0, '');
	}
	
	private function getPrevRevid($revPage, $revid) {
		return $this->dbw->selectField('revision', 'rev_id', array('rev_page' => $revPage, 'rev_id < ' . $revid), 
										'GenerateMergeLogs::getPrevRevid', array('ORDER BY' => 'rev_id DESC'));
	}
	
	private function writeMergelog($userid, $userMerge, $mergeScore, $isTrusted, $title, $mergeRows) {
   	$pages = array();
		foreach ($mergeRows as $mergeRow) {
			if (count($mergeRow->revids) > 0) {
				$cellRevids = array();
				foreach ($mergeRow->revids as $cell) {
					$cellRevids[] = join('/', $cell);
				}
				$pages[] = $mergeRow->role.'|'.join('#', $cellRevids);
			}
		}
  	   $record = array('ml_timestamp' => $userMerge->lastTimestamp, 'ml_user' => $userid, 'ml_score' => $mergeScore, 'ml_trusted' => ($isTrusted ? 1 : 0),
  	   					 'ml_namespace' => $title->getNamespace(), 'ml_title' => $title->getDBkey(), 'ml_pages' => join("\n", $pages));
  	   if ($this->testing) {
  	   	$mergeId = 123;
  	   }
  	   else {
      	$this->dbw->insert('mergelog', $record);
      	$mergeId = $this->dbw->insertId();
  	   }
		return $mergeId;
	}

	private function updateRevidComment($revid, $comment, $title, $mainTitle, $mergeId) {
		if ($mainTitle->getNamespace() == NS_FAMILY && $title->getNamespace() != NS_FAMILY && $title->getNamespace() != NS_FAMILY_TALK) {
			$comment .= " in merge of [[{$mainTitle->getPrefixedText()}]]";
		}
		$comment .= " - [[Special:ReviewMerge/$mergeId|review/undo]]";
      if (!$this->testing) {
			$this->dbw->update( 'revision', array('rev_comment' => $comment), array('rev_id' => $revid), 'GenerateMergelogs::updateRevidComment');
      }
	}
	
	private function updateMergeComments($userMerge, $mainTitle, $mergeId) {
		foreach ($userMerge->merges as $merge) {
			if (@$merge->target->postRevid) {
				$this->updateRevidComment($merge->target->postRevid, $merge->target->comment, $merge->target->title, $mainTitle, $mergeId);
			}
			foreach ($merge->sources as $source) {
				$this->updateRevidComment($source->postRevid, $source->comment, $source->title, $mainTitle, $mergeId);
			}
		}
	}
	
	function writeMerge($userid, &$userMerge) {
		$foundFamilyTitle = null;
		$foundPeopleCount = 0;
		$familyCount = 0;
		$mergeCount = count($userMerge->merges);
		$preMergeTimestamp = wfTimestamp(TS_MW, wfTimestamp(TS_UNIX, $userMerge->firstTimestamp) - 2);
		foreach ($userMerge->merges as $merge) {
			$mergeNs = $merge->target->title->getNamespace();
			if ($mergeNs != NS_PERSON && $mergeNs != NS_FAMILY && $mergeNs != NS_PERSON_TALK && $mergeNs != NS_FAMILY_TALK) {
				return "invalid target for merge: title={$merge->target->title->getPrefixedText()}";
			}
			if ($foundFamilyTitle && ($mergeNs == NS_FAMILY)) {
				return "invalid merge sequence - {$merge->target->title->getPrefixedText()} after family: {$foundFamilyTitle->getPrefixedText()}";
			}
			if ($mergeNs == NS_FAMILY) {
				$familyCount = count($merge->sources)+1;
				$foundFamilyTitle = $merge->target->title;
			}
			else if ($mergeNs == NS_PERSON) {
				$foundPeopleCount++;
			}
			
			// get target preRevid
			$revid = StructuredData::getRevidForTimestamp($merge->target->title, $preMergeTimestamp);
			// its ok if revid was created during the merge for talk pages
			if ($revid == 0 || $revid > $userMerge->lastRevid || ($revid >= $userMerge->firstRevid && $mergeNs != NS_PERSON_TALK && $mergeNs != NS_FAMILY_TALK)) {
				return "revision not found for target={$merge->target->title->getPrefixedText()} revid=$revid firstRevid={$userMerge->firstRevid} timestamp=$preMergeTimestamp";
			}
			$merge->target->preRevid = ($revid >= $userMerge->firstRevid ? 0 : $revid); // 0 if created during the merge
			$revision = Revision::newFromId($revid);
			$merge->target->revPage = $revision->getPage();
			
			// get target postRevid and comment
			list($revid, $comment) = $this->getNextRevidComment($merge->target->revPage, $merge->target->preRevid);
			if ($revid != 0 && $revid <= $userMerge->lastRevid) { // if target was edited in the merge
				$merge->target->postRevid = $revid;
				$merge->target->comment = $comment;
			}
			
			// get source preRevid's and titles
			foreach ($merge->sources as $source) {
				$revid = $this->getPrevRevid($source->revPage, $source->postRevid);
				if (!$revid) {
					return "previous revision not found for source={$source->postRevid}";
				}
				$source->preRevid = $revid;

				$revision = Revision::newFromId($revid);
				$title = $revision->getTitle();
				if (!$title) {
					return "title not found for source={$source->postRevid}";
				}
				$source->title = $title;
				
				if ($title->getNamespace() != $mergeNs) {
						return "invalid source={$title->getPrefixedText()} target={$merge->target->title->getPrefixedText()}";
				}
			}
		}
		if ($foundPeopleCount > 1 && !$foundFamilyTitle) {
			return "invalid merge sequence: multiple merges without a family: count=$mergeCount";
		}
		if (!$foundFamilyTitle && $foundPeopleCount == 0) {
			return "invalid merge sequence: no people or families count=$mergeCount";
		}
		
		// put merge revids into their proper rows and columns, and add roles
		$mergeRows = array();
		for ($m = 0; $m < count($userMerge->merges); $m++) {
			$mergeRows[$m] = (object) array('role' => '', 'revids' => array());
			if ($foundFamilyTitle && $userMerge->merges[$m]->target->title->getNamespace() == NS_PERSON) {
				for($p = 0; $p < count($familyCount); $p++) {
					$mergeRows[$m]->revids[$p] = array();
				}
			}
		}
		
		$mainTitle = null;
		$totalScore = 0;
		$totalCount = 0;
		$seenRevids = array();
		for ($m = 0; $m < count($userMerge->merges); $m++) {
			$merge =& $userMerge->merges[$m];
			$ns = $merge->target->title->getNamespace();
			
			// read baseData and data, calc match score
			if ($ns == NS_PERSON || $ns == NS_FAMILY) {
				$baseData = $this->readData($merge->target->title, $merge->target->preRevid);
				if (!$baseData) {
					return "unable to read data for {$merge->target->title->getPrefixedText()} revid={$merge->target->preRevid}";
				}
				if ($ns == NS_FAMILY) {
					$baseStdData = MergeForm::standardizeFamilyData($baseData);
				}
				else {
					$baseStdData = MergeForm::standardizePersonData($baseData);
				}
				$data = array();
				for ($p = 0; $p < count($merge->sources); $p++) {
					$data[$p] = $this->readData($merge->sources[$p]->title, $merge->sources[$p]->preRevid);
					if (!$data[$p]) {
						return "unable to read data for {$merge->sources[$p]->title->getPrefixedText()} revid={$merge->sources[$p]->preRevid}";
					}
					if ($ns == NS_FAMILY) {
						$stdData = MergeForm::standardizeFamilyData($data[$p]);
					}
					else {
						$stdData = MergeForm::standardizePersonData($data[$p]);
					}
					$totalScore += MergeForm::calcMatchScore($baseStdData, $stdData);
					$totalCount++;
				}
			}
			
			if ($foundFamilyTitle && $ns == NS_FAMILY) {
				// use pre-merge family revisions to get roles, row numbers
				$this->recordFamilyMembers($baseData['husbands'], 'husband', 0, $userMerge->merges, $preMergeTimestamp, $familyCount, $mergeRows, $seenRevids);
				$this->recordFamilyMembers($baseData['wives'], 'wife', 0, $userMerge->merges, $preMergeTimestamp, $familyCount, $mergeRows, $seenRevids);
				$this->recordFamilyMembers($baseData['children'], 'child', 0, $userMerge->merges, $preMergeTimestamp, $familyCount, $mergeRows, $seenRevids);
				for ($p = 0; $p < count($data); $p++) {
					$this->recordFamilyMembers($data[$p]['husbands'], 'husband', $p+1, $userMerge->merges, $preMergeTimestamp, $familyCount, $mergeRows, $seenRevids);
					$this->recordFamilyMembers($data[$p]['wives'], 'wife', $p+1, $userMerge->merges, $preMergeTimestamp, $familyCount, $mergeRows, $seenRevids);
					$this->recordFamilyMembers($data[$p]['children'], 'child', $p+1, $userMerge->merges, $preMergeTimestamp, $familyCount, $mergeRows, $seenRevids);
				}
			}
			if (!$foundFamilyTitle || $ns != NS_PERSON) { // handle people in families above
				$mergeRows[$m]->revids[0] = array();
				$mergeRows[$m]->revids[0][] = $merge->target->preRevid;
				for ($p = 0; $p < count($merge->sources); $p++) {
					$mergeRows[$m]->revids[$p+1] = array();
					$mergeRows[$m]->revids[$p+1][] = $merge->sources[$p]->preRevid;
				}
				if ($ns == NS_PERSON_TALK || $ns == NS_FAMILY_TALK) {
					$mergeRows[$m]->role = 'talk';
				}
				else if ($ns == NS_PERSON) {
					$mergeRows[$m]->role = 'Person';
				}
				else if ($ns == NS_FAMILY) {
					$mergeRows[$m]->role = 'Family';
				}
			}
			if ($ns == NS_FAMILY || ($ns == NS_PERSON && !$foundFamilyTitle)) {
				$mainTitle = $merge->target->title;
			}
		}
		if ($foundFamilyTitle && $mainTitle->getPrefixedText() != $foundFamilyTitle->getPrefixedText()) {
			return "family titles not equal: {$mainTitle->getPrefixedText()} and {$foundFamilyTitle->getPrefixedText()}";
		}
		if ($foundFamilyTitle) {
			// verify that all merging people have been recorded in mergeRows somewhere
			foreach ($userMerge->merges as &$merge) {
				if ($merge->target->title->getNamespace() == NS_PERSON) {
					if (!@$seenRevids[$merge->target->preRevid]) {
						return "target person not found: {$merge->target->title->getPrefixedText()}";
					}
					foreach ($merge->sources as &$source) {
						if (!@$seenRevids[$source->preRevid]) {
							return "source person not found: {$source->title->getPrefixedText()}";
						}
					}
				}
			}
		}

		// write mergelog record
		$mergeScore = $totalCount > 0 ? ($totalScore / $totalCount) : 0;
		$isTrustedUser = CompareForm::isTrustedMerger(User::newFromName(User::whois($userid)));
		$isTrustedMerge = MergeForm::isTrustedMerge($mergeScore, $isTrustedUser);
		$mergeId = $this->writeMergelog($userid, $userMerge, $mergeScore, $isTrustedMerge, $mainTitle, $mergeRows);
		
		// update post-merge comments with ml_id
		$this->updateMergeComments($userMerge, $mainTitle, $mergeId);
		
		$this->log("INFO", "writeMerge id=$mergeId score=$mergeScore isTrusted=$isTrustedMerge merges=$mergeCount title={$mainTitle->getPrefixedText()}", $userid, $userMerge->firstTimestamp);
		return '';
	}

	function processRevisions($maxMerges) {
		$mergesCount = 0;
		$rows = $this->dbw->query("SELECT rev_id, rev_page, rev_user, rev_comment, rev_timestamp FROM revision WHERE rev_id >= ".self::START_REVID." and rev_id <= ".self::END_REVID.
								 " and (rev_comment like 'merge into [[%' or rev_comment like 'Add data from merged page(s): [[%')");
		while ($row = $this->dbw->fetchObject($rows)) {
			$seconds = wfTimestamp(TS_UNIX, $row->rev_timestamp);
			$userid = $row->rev_user;
			$revid = $row->rev_id;
			$found = false;
			if (@$this->userMerges[$userid]) {
				if ($seconds - $this->userMerges[$userid]->lastSeconds > self::MAX_INTERVAL) {
					$err = $this->writeMerge($userid, $this->userMerges[$userid]);
					if ($err) $this->log("ERROR", $err, $userid, $this->userMerges[$userid]->firstTimestamp);
					unset($this->userMerges[$userid]);
				}
				else {
					$found = true;
				}
			}
			
			if (!$found) {
				if ($maxMerges && $mergesCount == $maxMerges) {
					break;
				}
				$mergesCount++;
				$this->userMerges[$userid] = (object) array('firstTimestamp' => $row->rev_timestamp, 'firstRevid' => $revid, 'merges' => array());
			}
			
			$this->log("INFO", "revision {$row->rev_id} {$row->rev_page} {$row->rev_comment}", $row->rev_user, $row->rev_timestamp);
			$userMerge =& $this->userMerges[$userid];
			$userMerge->lastSeconds = $seconds;
			$userMerge->lastTimestamp = $row->rev_timestamp;
			$userMerge->lastRevid = $revid;
			if (preg_match('/^merge into \\[\\[(.+?)\\]\\]/', $row->rev_comment, $matches)) {
				$target = Title::newFromText($matches[1]);
				if ($target) {
					$rowNumber = -1;
					for ($i = 0; $i < count($userMerge->merges); $i++) {
						if ($target->getPrefixedText() == $userMerge->merges[$i]->target->title->getPrefixedText()) {
							$rowNumber = $i;
							break;
						}
					}
					if ($rowNumber < 0) {
						$rowNumber = count($userMerge->merges);
						$userMerge->merges[] = (object) array('target' => (object) array('title' => $target), 'sources' => array());
					}
					$userMerge->merges[$rowNumber]->sources[] = (object) array('revPage' => $row->rev_page, 'postRevid' => $revid, 'comment' => $row->rev_comment);
				}
				else {
					$this->log("ERROR", "rev_id=$revid rev_comment={$row->rev_comment}", $userid, $userMerge->firstTimestamp);
				}
			}
		}
		$this->dbw->freeResult($rows);
		
		$this->log("COUNT", "total merges=$mergesCount", 0, '');
	}
	
	function writeFinalMergelogs() {
		foreach ($this->userMerges as $userid => $userMerge) {
			$err = $this->writeMerge($userid, $userMerge);
			if ($err) $this->log("ERROR", $err, $userid, $userMerge->firstTimestamp);
		}
	}
}
?>
