<?php
require_once("$IP/includes/JobQueue.php");
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

class WatchTreePagesJob extends Job {
	function __construct($params, $id = 0 ) {
	   // pass up a fake title
		parent::__construct('watchTreePages', Title::makeTitle(NS_SPECIAL, 'WatchTreePages'), $params, $id );
	}

	/**
	 * Run a refreshLinks job
	 * @return boolean success
	 */
	function run() {
		global $wgUser, $wgTitle;

		$status = FTE_SUCCESS;
		$user = $this->params['user'];
		$wgUser = User::newFromName($user);
		$treeId = $this->params['tree_id'];
		$wgTitle = $this->title;  // FakeTitle (the default) generates errors when accessed, and sometimes I log wgTitle, so set it to something else

	   $dbw =& wfGetDB( DB_MASTER );
	   $dbw->begin();
	   $dbw->ignoreErrors(true);

   	$rows = $dbw->select('familytree_page', array('fp_namespace', 'fp_title'), array('fp_tree_id' => $treeId));
  		$errno = $dbw->lastErrno();
  		if ($errno > 0) {
  		   $status = FTE_DB_ERROR;
  		}
  		else if ($rows !== false) {
  		   while ($row = $dbw->fetchObject($rows)) {
	         $title = Title::makeTitle($row->fp_namespace, $row->fp_title);
	         if ($title) {
	       	  	// watch article
	       	  	StructuredData::addWatch($wgUser, new Article($title, 0));
	         }
  		   }
  		   $dbw->freeResult($rows);
  		}
      if ($errno == 0) {
		   $dbw->commit();
		   return true;
		}
		else {
		   $dbw->rollback();
		   $this->error = "Error watching pages; db_errno=$errno\n";
		   return false;
		}
	}
}

?>
