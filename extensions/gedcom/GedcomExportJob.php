<?php
require_once("$IP/LocalSettings.php");
require_once("$IP/includes/JobQueue.php");
require_once("$IP/extensions/gedcom/GedcomExporter.php");

class GedcomExportJob extends Job {

	function __construct($params, $id = 0 ) {
	   // pass up a fake title
		parent::__construct('gedcomExport', Title::makeTitle(NS_SPECIAL, 'GedcomExport'), $params, $id );
	}
	
	/**
	 * Run a refreshLinks job
	 * @return boolean success
	 */
	function run() {
		global $wgTitle, $wgUser, $wgLang, $wrGedcomExportDirectory;

		$wgTitle = $this->title;  // FakeTitle (the default) generates errors when accessed, and sometimes I log wgTitle, so set it to something else
		$wgUser = User::newFromName('WeRelate agent'); // set the user
		$treeId = $this->params['tree_id'];
		$treeName = $this->params['name'];
		$treeUser = $this->params['user'];
		$filename = "$wrGedcomExportDirectory/$treeId.ged";
		$ge = new GedcomExporter();
		$error = $ge->exportGedcom($treeId, $filename);
		if ($error) {
			$this->error = $error;
			return false;
		}
		
		// leave a message for the tree requester
		$userTalkTitle = Title::newFromText($treeUser, NS_USER_TALK);
		$article = new Article($userTalkTitle, 0);
		if ($article->getID() != 0) {
			$text = $article->getContent();
		}
		else {
			$text = '';
		}
		
		$title = Title::makeTitle(NS_SPECIAL, 'Trees');
		$msg = wfMsg('GedcomExportReady', 
								$wgLang->date(wfTimestampNow(), true, false),
								$treeName,
								$title->getFullURL(wfArrayToCGI(array('action' => 'downloadExport', 'user' => $treeUser, 'name' => $treeName))));
		$text .= "\n\n".$msg;
		$success = $article->doEdit($text, 'GEDCOM export ready');
		if (!$success) {
			$this->error = 'Unable to edit user talk page: '.$treeUser;
			return false;
		}
		
		return true;
	}
}

?>
