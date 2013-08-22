<?php
/**
 * @package MediaWiki
 * @subpackage familytree
 */
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfFamilyTreeExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfFamilyTreeExtensionSetup() {
	global $wgHooks;

	# Register hooks for edit UI, request handling, and save features
	$wgHooks['ArticleSaveComplete'][] = 'propagateFamilyTreeEdit';  // do this on save complete, so the page has the latest revid
	$wgHooks['TitleMoveComplete'][] = 'propagateFamilyTreeMove';
	$wgHooks['ArticleDeleteComplete'][] = 'propagateFamilyTreeDelete';
//	$wgHooks['ArticleUndeleteComplete'][] = 'propagateFamilyTreeUndelete'; // not needed, since deleted pages are removed from all trees
	$wgHooks['ArticleRollbackComplete'][] = 'propagateFamilyTreeRollback';
	$wgHooks['UnwatchArticleComplete'][] = 'propagateFamilyTreeUnwatch';
}

function propagateFamilyTreeEdit(&$article, &$user, $text) { //}, &$summary, $isMinorEdit, $dummy1, $dummy2, &$flags) {
   global $wgRequest;
//wfDebug("propagateFamilyTreeEdit".$article->getTitle()->getPrefixedText()."\n");
   $ftp = new FamilyTreePropagator($article->getTitle());
//wfDebug("propagateFamilyTreeEdit text=$text\n");
//wfDebug("propagateFamilyTreeEdit article=".$article->getContent()."\n");
   $ftp->propagateEdit($article, $wgRequest, $text);
   return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateFamilyTreeMove(&$title, &$newTitle, &$user, $pageId, $redirPageId) {
   $ftp = new FamilyTreePropagator($title);
   $ftp->propagateMove($newTitle);
   return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateFamilyTreeDelete(&$article, &$user, $reason) {
   $ftp = new FamilyTreePropagator($article->getTitle());
   $ftp->propagateDelete();
   return true;
}

function propagateFamilyTreeUnwatch(&$user, &$article) {
   $ftp = new FamilyTreePropagator($article->getTitle());
   $ftp->propagateUnwatch($user->getID());
   return true;
}

/**
 * Callback function to propagate rollback
 * @param Article article
 * @return bool must return true or other hooks don't get called
 */
function propagateFamilyTreeRollback(&$article, &$user) {
   $ftp = new FamilyTreePropagator($article->getTitle());
   $ftp->propagateRollback();
	return true;
}

class FamilyTreePropagator {
   private $title;
   private $db;
   private $trxLevel;
   private $isPropagatingData;

   /**
     * Construct a new family object
     */
	public function __construct($title) {
	   global $wgTitle;

		$this->title = $title;
  	   $this->db =& wfGetDB( DB_MASTER );
  	   $this->trxLevel = 0;
  	   $this->isPropagatingData = ($title->getDBkey() != $wgTitle->getDBkey() || $title->getNamespace() != $wgTitle->getNamespace());
	}

	private function needsPropagation() {
		$ns = $this->title->getNamespace();
		if ($ns % 2 == 1) $ns -= 1; // prop talk pages too
	   return FamilyTreeUtil::isTreePage($ns, $this->title->getDBkey());
	}

	private function begin() {
	   $this->trxLevel = $this->db->trxLevel();
	   if (!$this->trxLevel) {
	      $this->db->begin();
	   }
	}

	private function end($result) {
	   if (!$this->trxLevel) {
	      if ($result) {
	        $this->db->commit();
	      }
	      else {
	         $this->db->rollback();
	      }
	   }
	   if (!$result) {
	      error_log("ERROR FamilyTreePropagator rollback on {$this->title->getText()}\n");
	   }
	}

	private function query($sql) {
	   return $this->db->query($sql, 'FamilyTreePropagator');
	}

	private function propagateEditMoveRollback($revid, $ns, $titleDBkey) {
	   global $wgUser, $wgTitle;

//wfDebug("propagateEditMoveRollback {$wgUser->getName()} $revid $ns:$titleDBkey {$wgTitle->getNamespace()}:{$wgTitle->getDBkey()}\n");

      $isTalk = ($ns % 2 == 1);
      if ($isTalk) $ns -= 1;
      $quotedTitleDBkey = $this->db->addQuotes($titleDBkey);
	   if ($this->isPropagatingData) { // propagating data is also true for move
	      if ($wgUser->isLoggedIn()) {
      	   $sql = 'UPDATE IGNORE familytree_page '.
      	           'SET ' . ($isTalk ? 'fp_talk_oldid' : 'fp_oldid') . '=' . $revid . ', ' . ($isTalk ? 'fp_talk_latest' : 'fp_latest') . '=' . $revid .
      		       ' WHERE fp_namespace = ' . $ns . ' AND fp_title = ' . $quotedTitleDBkey .
      		       ' AND fp_user_id = ' . $wgUser->getID() . ' AND fp_oldid = fp_latest';
      		$res = $this->query($sql);
	      }
	      else {
	         $res = true;
	      }
   		if ($res) {
      	   $sql = 'UPDATE IGNORE familytree_page '.
   	           'SET ' . ($isTalk ? 'fp_talk_latest' : 'fp_latest') . '=' . $revid .
   		       ' WHERE fp_namespace = ' . $ns . ' AND fp_title = ' . $quotedTitleDBkey;
   		   if ($wgUser->isLoggedIn()) {
   		       $sql .= ' AND (fp_user_id <> ' . $wgUser->getID() . ' OR fp_oldid <> fp_latest)';
   		   }
      		$res = $this->query($sql);
   		}
	   }
	   else {
         if ($wgUser->isLoggedIn()) {
      	   $sql = 'UPDATE IGNORE familytree_page '.
      	           'SET ' . ($isTalk ? 'fp_talk_oldid' : 'fp_oldid') . '=' . $revid . ', ' . ($isTalk ? 'fp_talk_latest' : 'fp_latest') . '=' . $revid .
	       ' WHERE fp_namespace = ' . $ns . ' AND fp_title = ' . $quotedTitleDBkey .
      		       ' AND fp_user_id = ' . $wgUser->getID();
      		$res = $this->query($sql);
         }
         else {
            $res = true;
         }
   		if ($res) {
      	   $sql = 'UPDATE IGNORE familytree_page '.
   	           'SET ' . ($isTalk ? 'fp_talk_latest' : 'fp_latest') . '=' . $revid .
   		       ' WHERE fp_namespace = ' . $ns . ' AND fp_title = ' . $quotedTitleDBkey;
   		   if ($wgUser->isLoggedIn()) {
   		       $sql .= ' AND fp_user_id <> ' . $wgUser->getID();
   		   }
      		$res = $this->query($sql);
   		}
	   }

//wfDebug("propagateEditMoveRollback result $res\n");
      return $res;
	}

	public function propagateEdit($article, $request, &$text) {
	   global $wgUser, $wgArticle;

	   if ($this->needsPropagation()) {
//wfDebug("propagateEdit {$article->getTitle()->getNamespace()} {$article->getTitle()->getDBkey()} {$article->mRevIdEdited}/{$article->getRevIdFetched()}\n");
//wfDebug(" -> {$wgArticle->getTitle()->getPrefixedText()}\n");
	      $this->begin();
	      $res = $this->propagateEditMoveRollback($article->mRevIdEdited, $this->title->getNamespace(), $this->title->getDBkey());
	      if ($res && ($wgArticle->getTitle()->getPrefixedText() == $article->getTitle()->getPrefixedText() || // don't need this for propagated changes
	      				 ($wgArticle->getTitle()->getNamespace() == NS_SPECIAL && $wgArticle->getTitle()->getText() == 'Merge'))) { // do this also for merges
				$newTitle = Title::newFromRedirect($text); // can't use $article->content or SD:getRedirectToTitle because they return the wrong results
//wfDebug("propagateEdit title=".$this->title->getPrefixedText()." newTitle=" . ($newTitle == null ? '' : $newTitle->getPrefixedText()) . "\n");
	         if ($newTitle != null && $newTitle->getPrefixedText() != $this->title->getPrefixedText()) { // if this article is a redirect, add the new page to the users' tree(s)
					$newTitle = StructuredData::getRedirectToTitle($newTitle, true); // get final redirect; unreliable on the current title for some reason
	            $res = $this->handleRedirect($newTitle);
	         }
	      }
	      // only update trees for the article being edited (no propagation but allow image uploads) and if it's a primary namespace
	      if ($res && FamilyTreeUtil::isTreePage($article->getTitle()->getNamespace(), $article->getTitle()->getDBkey()) &&
		  (($wgArticle->getTitle()->getNamespace() == NS_SPECIAL && $wgArticle->getTitle()->getText() == 'Upload') ||
                   $wgArticle->getTitle()->getPrefixedText() == $article->getTitle()->getPrefixedText())) {
	         $res = $this->updateTrees($wgUser, $article->getTitle(), $article->mRevIdEdited, $request);
	      }
//wfDebug("propagateEdit result $res\n");
	      $this->end($res);
	   }
	}
	
	public function handleRedirect($newTitle) {
      $res = true;
	   // we don't need to do anything for talk page redirects
	   if ($this->needsPropagation() && !$this->title->isTalkPage()) {
//wfDebug("handleRedirect {$this->title->getNamespace()} {$this->title->getDBkey()} : {$newTitle->getDBkey()}\n");
      	// get main revid
		   $latest = $newTitle->getLatestRevID(GAID_FOR_UPDATE);
	      // get talk revid
		   $talkLatest = $newTitle->getTalkPage()->getLatestRevID(GAID_FOR_UPDATE);
	      // insert familytree_page
	      if ($res) {
	      	$sql = 'INSERT IGNORE INTO familytree_page'.
	      		' (fp_tree_id, fp_namespace, fp_title, fp_oldid, fp_flags, fp_data_version, fp_latest, fp_talk_oldid, fp_talk_latest, fp_uid, fp_user_id)' .
	      	   ' (SELECT fp_tree_id'.
	      	   ', '.$this->db->addQuotes($newTitle->getNamespace()).
	      	   ', '.$this->db->addQuotes($newTitle->getDBkey()).
	      	   ', '.$this->db->addQuotes($latest).
	      	   ', 0'.
	      	   ', fp_data_version'.
	      	   ', '.$this->db->addQuotes($latest).
	      	   ', '.$this->db->addQuotes($talkLatest).
	      	   ', '.$this->db->addQuotes($talkLatest).
	      	   ', fp_uid'.
	      	   ', fp_user_id'.
	      	   ' FROM familytree_page WHERE fp_namespace = ' . $this->db->addQuotes($this->title->getNamespace()) .
	      		' AND fp_title = ' . $this->db->addQuotes($this->title->getDBkey()) . ')';
	      	$res = $this->query($sql);
	      }
	      // insert familytree_data
	      if ($res) {
	      	$sql = 'INSERT IGNORE INTO familytree_data (fd_tree_id, fd_namespace, fd_title, fd_data)' .
	      	   ' (SELECT fd_tree_id'.
	      	   ', '.$this->db->addQuotes($newTitle->getNamespace()).
	      	   ', '.$this->db->addQuotes($newTitle->getDBkey()).
	      	   ', fd_data'.
	      	   ' FROM familytree_data WHERE fd_namespace = ' . $this->db->addQuotes($this->title->getNamespace()) .
	      		' AND fd_title = ' . $this->db->addQuotes($this->title->getDBkey()) . ')';
	      	$res = $this->query($sql);
	      }
	   }
//wfDebug("handleRedirect result $res\n");
	   return $res;
	}

	// update the trees this page belongs to based upon the checkboxes
	private function updateTrees($user, $title, $revId, $request) {
//wfDebug("updateTrees " . $title->getPrefixedText() . " " . $revId . "\n");
         $allTrees = FamilyTreeUtil::getFamilyTrees($user->getName());
         $treeOwnerIds = FamilyTreeUtil::getOwnerTrees($user, $title, false);
         $checkedTreeIds = FamilyTreeUtil::readTreeCheckboxes($allTrees, $request);
         return FamilyTreeUtil::updateTrees($this->db, $title, $revId, $allTrees, $treeOwnerIds, $checkedTreeIds, true, false);
	}

	public function propagateMove($newTitle) {
	   if ($this->needsPropagation()) {
//wfDebug("propagateMove {$this->title->getNamespace()} {$this->title->getDBkey()} : {$newTitle->getDBkey()}\n");
	      $this->begin();
	      $res = true;
	      if ($this->title->getNamespace() % 2 == 0) { // not a talk page
      		$sql = 'UPDATE IGNORE familytree_page SET fp_title = ' . $this->db->addQuotes($newTitle->getDBkey()) .
      		       ', fp_namespace = ' . $this->db->addQuotes($newTitle->getNamespace()) .
   		          ' WHERE fp_namespace = ' . $this->db->addQuotes($this->title->getNamespace()) . ' AND fp_title = ' . $this->db->addQuotes($this->title->getDBkey());
      		$res = $this->query($sql);
      		if ($res) {
         		$sql = 'UPDATE IGNORE familytree_data SET fd_title = ' . $this->db->addQuotes($newTitle->getDBkey()) .
         		       ', fd_namespace = ' . $this->db->addQuotes($newTitle->getNamespace()) .
   	  	             ' WHERE fd_namespace = ' . $this->db->addQuotes($this->title->getNamespace()) . ' AND fd_title = ' . $this->db->addQuotes($this->title->getDBkey());
      	     	$res = $this->query($sql);
      		}
	      }
	      if ($res) {
   	      $revid = $this->db->selectField('page', 'page_latest', array('page_namespace' => $newTitle->getNamespace(), 'page_title' => $newTitle->getDBkey()));
	        $res = ($revid !== false);
	      }
	      if ($res) {
	         $res = $this->propagateEditMoveRollback($revid, $newTitle->getNamespace(), $newTitle->getDBkey());
	      }
	      // just in case we're moving the talk page only (or the talk page before the main page, update the old title as well
	      //   BUG: if just the talk page is moved and the page is in another user's tree, 
	      //     then latest in that tree will end up pointing to the latest rev (which is the newly-created redirect page), but oldid in that tree will be bogus
	      if ($res) {
   	      $revid = $this->db->selectField('page', 'page_latest', array('page_namespace' => $this->title->getNamespace(), 'page_title' => $this->title->getDBkey()));
	        $res = ($revid !== false);
	      }
	      if ($res) {
	         $res = $this->propagateEditMoveRollback($revid, $this->title->getNamespace(), $this->title->getDBkey());
	      }
//wfDebug("propagateMove result $res\n");
	      $this->end($res);
	   }
	}
	
	public function propagateDelete() {
	   global $wrIsTreeDeletion;
      // don't delete pages from familytree_page when deleting an entire tree; the tree deletion code will take care of that
      if ($this->needsPropagation() && @!$wrIsTreeDeletion) {
//   	   wfDebug("propagateDelete {$this->title->getNamespace()} {$this->title->getDBkey()}\n");
	      $this->begin();
	      if ($this->title->getNamespace() % 2 == 0) {
   		   $sql = 'DELETE from familytree_page' .
	     	       ' WHERE fp_namespace = ' . $this->db->addQuotes($this->title->getNamespace()) . ' AND fp_title = ' . $this->db->addQuotes($this->title->getDBkey());
            $res = $this->query($sql);
            if ($res) {
      		   $sql = 'DELETE from familytree_data' .
	     	       ' WHERE fd_namespace = ' . $this->db->addQuotes($this->title->getNamespace()) . ' AND fd_title = ' . $this->db->addQuotes($this->title->getDBkey());
            }
	      }
	      else {
   		   $sql = 'UPDATE IGNORE familytree_page SET fp_talk_oldid = 0, fp_talk_latest = 0' .
		       ' WHERE fp_namespace = ' . $this->db->addQuotes($this->title->getNamespace()-1) . ' AND fp_title = ' . $this->db->addQuotes($this->title->getDBkey());
	      }
         $res = $this->query($sql);
	      $this->end($res);
	   }
	}

	public function propagateUnwatch($userId) {
//wfDebug("propagateUnwatch user=$userId\n");
		if ($this->needsPropagation() && $this->title->getNamespace() % 2 == 0) {
	      $this->begin();
		   $sql = 'DELETE from familytree_page' .
     	       ' WHERE fp_namespace = ' . $this->db->addQuotes($this->title->getNamespace()) . ' AND fp_title = ' . $this->db->addQuotes($this->title->getDBkey()) .
     	       ' AND fp_user_id = ' . $this->db->addQuotes($userId);
         $res = $this->query($sql);
         // !!! should really delete familytree_data too, but there isn't a quick way to do this
	      $this->end($res);
	   }
	}

	public function propagateRollback() {
	   if ($this->needsPropagation()) {
//   	   wfDebug("propagateRollback {$this->title->getNamespace()} {$this->title->getDBkey()}\n");
	      $this->begin();
  	      $revid = $this->db->selectField('page', 'page_latest', array('page_namespace' => $this->title->getNamespace(), 'page_title' => $this->title->getDBkey()));
         $res = ($revid !== false);
         if ($res) {
  	         $res = $this->propagateEditMoveRollback($revid, $this->title->getNamespace(), $this->title->getDBkey());
         }
	      $this->end($res);
	   }
	}
}
?>
