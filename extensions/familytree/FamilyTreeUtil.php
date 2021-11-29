<?php

require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/familytree/DeleteFamilyTreeJob.php");

define( 'FTE_SUCCESS', 0);
define( 'FTE_INVALID_ARG', -1);
define( 'FTE_NOT_LOGGED_IN', -2);
define( 'FTE_NOT_AUTHORIZED', -3);
define( 'FTE_DB_ERROR', -4);
define( 'FTE_DUP_KEY', -5);
define( 'FTE_NOT_FOUND', -6); // page, familytree, or familytree_page not found
define( 'FTE_WIKI_ERROR', -7);
//define( 'FTE_UPLOAD_ERROR', -8);
define( 'FTE_GEDCOM_PROCESSING', -9);
define( 'FTE_GEDCOM_WAITING', -10);
define( 'FTE_GEDCOM_ERROR_START', -100);

define( 'FG_STATUS_DELETE', 0); // special status
define( 'FG_STATUS_UPLOADED', 1);
define( 'FG_STATUS_PROCESSING', 2);
define( 'FG_STATUS_READY', 3);
define( 'FG_STATUS_OPENED', 4);
define( 'FG_STATUS_PHASE2', 5);
define( 'FG_STATUS_PHASE3', 6);
define( 'FG_STATUS_IMPORTING', 7);
define( 'FG_STATUS_ADMIN_REVIEW', 8);
define( 'FG_STATUS_HOLD', 19);
define( 'FG_STATUS_ERROR_START', 100);
define( 'FG_STATUS_ERROR', 100);
define( 'FG_STATUS_OVERLAP', 101);
define( 'FG_STATUS_ERROR_NOT_GEDCOM', 102);
define( 'FG_STATUS_REGENERATE', 105);


/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
class FamilyTreeUtil {

	public static $STATUS_MESSAGES = array(
		0 => 'Error',
		1 => 'Waiting for analysis',
		2 => 'Analyzing',
		3 => 'Ready',
		4 => 'Ready',
		5 => 'Uploader review',
		6 => 'Waiting for import',
		7 => 'Importing',
		8 => 'Admin review',
      19 => 'On hold'
	);

   private static function prepareTreesCacheKey($userName) {
      return 'wrtrees:'.base64_encode($userName);
   }

   private static function getFamilyTreesFromCache($userName) {
      global $wgMemc;

      $familyTrees = null;
      $cacheKey = FamilyTreeUtil::prepareTreesCacheKey($userName);
      $trees = $wgMemc->get($cacheKey);
      if (isset($trees)) {
         $familyTrees = array();
         $rows = explode('~|~', $trees);
         foreach ($rows as $row) {
            $fields = explode('|', $row, 4);
            $familyTrees[] = array('id' => $fields[0], 'timestamp'=> $fields[1], 'checked' => $fields[2], 'name' => $fields[3]);
         }
      }
      return $familyTrees;
   }

   private static function setFamilyTreesCache($userName, $familyTrees) {
      global $wgMemc;

      $trees = array();
      $cacheKey = FamilyTreeUtil::prepareTreesCacheKey($userName);
      foreach ($familyTrees as $familyTree) {
         $trees[] = $familyTree['id'].'|'.$familyTree['timestamp'].'|'.$familyTree['checked'].'|'.$familyTree['name'];
      }
      $wgMemc->set($cacheKey, join('~|~', $trees), 1800);
   }

   public static function deleteFamilyTreesCache($userName) {
      global $wgMemc;

      $cacheKey = FamilyTreeUtil::prepareTreesCacheKey($userName);
      $wgMemc->delete($cacheKey);
   }

   /**
    * return the id, name, timestamp, and count of the number of people of all of the trees for user
    *
    * @param string $userName user name
    * @return unknown
    */
   public static function getFamilyTrees($userName, $includeCount = false, $db = null) {
      $familyTrees = null;
      if (!$includeCount) {
         $familyTrees = FamilyTreeUtil::getFamilyTreesFromCache($userName);
      }
      if (!is_array($familyTrees)) {
         if (is_null($db)) $db =& wfGetDB(DB_SLAVE);
         $db->ignoreErrors(true);
         $sql = 'SELECT ft_tree_id, ft_name, ft_owner_last_opened_timestamp, ft_checked'.
                  ($includeCount ? ', (SELECT count(*) FROM familytree_page WHERE fp_tree_id = ft_tree_id) AS cnt' : '').
                ' FROM familytree WHERE ft_user = '.$db->addQuotes($userName);
         $rows = $db->query($sql, 'getFamilyTrees');
         $errno = $db->lastErrno();
         if ($errno > 0) {
            return null;
         }
         $familyTrees = array();
         while ($row = $db->fetchObject($rows)) {
            $familyTrees[] = array('id' => $row->ft_tree_id, 'name' => $row->ft_name, 'timestamp' => $row->ft_owner_last_opened_timestamp,
                                   'checked' => $row->ft_checked, 'count' => ($includeCount ? $row->cnt : 0));
         }
         $db->freeResult($rows);

         if (!$includeCount) {
            FamilyTreeUtil::setFamilyTreesCache($userName, $familyTrees);
         }
      }
		return $familyTrees;
   }

   /**
    * return user's trees that the title is in
    *
    * @param User $user
    * @param Title $title
    * @param Boolean $returnName true to return the tree name; false to return the tree id (faster)
    */
   public static function getOwnerTrees($user, $title, $returnName) {
//wfDebug("getOwnerTrees ".$title->getNamespace().":".$title->getDBkey()."\n");
      $trees = array();
      $dbr =& wfGetDB( DB_SLAVE );
      if ($returnName) {
         $rows = $dbr->select(array('familytree', 'familytree_page'), 'ft_name',
         	     array('fp_namespace' => $title->getNamespace(), 'fp_title' => $title->getDBkey(), 'fp_user_id' => $user->getID(), 'fp_tree_id=ft_tree_id'));
      }
      else {
         $rows = $dbr->select('familytree_page', 'fp_tree_id',
         	     array('fp_namespace' => $title->getNamespace(), 'fp_title' => $title->getDBkey(), 'fp_user_id' => $user->getID()));
      }
      while ($row = $dbr->fetchObject($rows)) {
         $trees[] = $returnName ? $row->ft_name : $row->fp_tree_id;
//wfDebug("getOwnerTrees ".($returnName ? $row->ft_name : $row->fp_tree_id)."\n");
      }
      $dbr->freeResult($rows);
      return $trees;
   }

   public static function isValidTreeName($treeName) {
     // Tree name can't have | or search wildcard (* or ?) in it's name (wildcards added Nov 2021 by Janet Bjorndahl)
     return preg_match("(\||\*|\?)", $treeName) === 0 && $treeName != '[new]' && strlen(trim($treeName)) > 0;
   }

   public static function toInputName($name) {
      // if you change this, change it in wikibits.js also
      return 'tree_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name);
   }

   /**
    * Add the specified page to the specified tree
    * Remember to also call StructuredData::addWatch if you call this function
    *
    * @param unknown_type $dbw
    * @param unknown_type $treeId
    * @param unknown_type $title
    * @param unknown_type $revid
    * @param unknown_type $talkRevid
    * @param unknown_type $dataVersion
    * @param unknown_type $uid
    * @param unknown_type $flags
    * @return unknown
    */
   public static function addPage($dbw, &$user, $treeId, $title, $revid, $talkRevid, $dataVersion=0, $uid='', $flags=0) {
		$result = true;

		// should this be the primary person? 
		// TODO get rid of this test - the primary person can be set when the user opens the tree for the first time
      if ($title->getNamespace() == NS_PERSON) {
        	$res = $dbw->select('familytree_page', 'fp_title', array('fp_tree_id' => $treeId, 'fp_namespace' => NS_PERSON), 
         	                    'FamilyTreeUtil::addPage', array ('LIMIT' => 1) );
			if ( $res === false || !$dbw->numRows( $res ) ) {
	      	// make this person the primary page if it's the first person
		      $dbw->update('familytree', array('ft_primary_namespace' => $title->getNamespace(), 'ft_primary_title' => $title->getDBkey()), array('ft_tree_id' => $treeId));
            FamilyTreeUtil::deleteFamilyTreesCache($user->getName());
	         $errno = $dbw->lastErrno();
	         if ($errno > 0) {
	            $result = false;
			   }
   	   }
     		$dbw->freeResult($res);
	   }
	   
      // insert familytree_page
      $record = array('fp_tree_id' => $treeId, 'fp_user_id' => $user->getID(), 'fp_namespace' => $title->getNamespace(), 'fp_title' => $title->getDBkey(),
                        'fp_oldid' => $revid, 'fp_latest' => $revid, 'fp_talk_oldid' => $talkRevid, 'fp_talk_latest' => $talkRevid,
                        'fp_data_version' => $dataVersion, 'fp_uid' => $uid, 'fp_flags' => $flags);
      $dbw->insert('familytree_page', $record, 'FamilyTreeUtil::addPage', array('ignore'));
      $errno = $dbw->lastErrno();
      if ($errno != 0 && $errno != 1062) { // 1062 = duplicate key
         $result = false;
      }

      return $result;
   }

   public static function removePage($dbw, $treeId, $title) {
      $dbw->delete('familytree_page', array('fp_tree_id' => $treeId, 'fp_namespace' => $title->getNamespace(), 'fp_title' => $title->getDBkey()));
      $errno = $dbw->lastErrno();
      if ($errno > 0) {
         return false;
 		}
      $dbw->delete('familytree_data', array('fd_tree_id' => $treeId, 'fd_namespace' => $title->getNamespace(), 'fd_title' => $title->getDBkey()));
      $errno = $dbw->lastErrno();
      if ($errno > 0) {
         return false;
 		}
	   return true;
   }
   
   public static function generateHiddenTreeCheckboxes($allTrees, $checkedTreeIds) {
   	$result = '';
   	foreach ($allTrees as $tree) {
   		if (in_array($tree['id'], $checkedTreeIds)) {
   			$result .= '<input type="hidden" name="'.htmlspecialchars(FamilyTreeUtil::toInputName($tree['name'])).'" value="on"/>';
   		}
   	}
   	return $result;
   }

   public static function generateTreeCheckboxes($user, $title, $editPage, $allTrees = null, $treeOwnerIds = null) {
   	global $wgRequest, $wgUser;
   	
      $result = '';
      $cnt = 0;
      $proposedTrees = array();
	   // $editPage is true also for adding a page in Special:Search
      // title may be null for image upload or adding a page in Special:Search
      if ($title == null || FamilyTreeUtil::isTreePage($title->getNamespace(), $title->getDBkey())) {
         $isNew = ($title == null || $title->getArticleID() == 0);
         if (!is_array($allTrees)) {
            $allTrees = FamilyTreeUtil::getFamilyTrees($user->getName());
         }
         if (count($allTrees) > 0) {
         	if ($editPage && $wgRequest->wasPosted()) {
         		$checkedTrees = FamilyTreeUtil::readTreeCheckboxes($allTrees, $wgRequest);
         	}
         	else {
         		if (is_array($treeOwnerIds)) {
         			$checkedTrees = $treeOwnerIds;
   	      	}
      	   	else {
	         		$checkedTrees = ($title == null ? array() : FamilyTreeUtil::getOwnerTrees($user, $title, false));
         		}
         		// if we're creating the page and the user has just one tree and no tree is checked, propose the user's one tree
	            if ($editPage && $isNew && count($allTrees) == 1 && count($checkedTrees) == 0) {
	               $proposedTrees[] = $allTrees[0]['id']; // changed from checkedTrees to proposedTrees Sep 2020 by Janet Bjorndahl
	            }
         	}
            // if no trees checked or proposed, propose the default tree(s) (changed Sep 2020 by Janet Bjorndahl)
            if (count($checkedTrees) == 0 && count($proposedTrees) == 0 && !$wgRequest->wasPosted() && 
                !(($isNew && $wgUser->getIntOption('watchcreations') === 0) ||
                  (!$isNew && $wgUser->getIntOption('watchdefault') === 0))) {
               foreach ($allTrees as $tree) {
                  if ($tree['checked']) {
                     $proposedTrees[] = $tree['id'];
                  }
               }
            }
            foreach ($allTrees as $tree) {
               $cnt++;
               $class = '';
               if ($editPage && !$wgRequest->wasPosted() && ($title == null || $title->getArticleID() == 0)) {
               	if ($cnt == 1 && count($checkedTrees) == 0) {
	               	$class = ' treeCheckbox'; // defaultTreeCheckbox"';
               	}
               	else {
	               	$class = ' treeCheckbox';
               	}
               }
               $result .= " <input type=\"checkbox\" class=\"treeCheck$class\"" . /* current & proposed labels added Sep 2020 by Janet Bjorndahl */
                          ' name="'.htmlspecialchars(FamilyTreeUtil::toInputName($tree['name'])).'"' .
                          ((in_array($tree['id'], $checkedTrees) || in_array($tree['id'], $proposedTrees)) ? " checked='checked'":"") . "/> ".htmlspecialchars($tree['name']) .
                          '<span class="attn">' . (in_array($tree['id'], $checkedTrees) ? '&nbsp;(current)' : (in_array($tree['id'], $proposedTrees) ? "&nbsp;(proposed)" : "")) . '</span>' .
                          ($editPage ? '' : "<br/>\n");
            }
         }
      }
      if ($editPage && $cnt) {
         $result = wfMsg( 'addtotreecheckbox' ) . $result;
      }
      return $result;
   }

   public static function readTreeCheckboxes($allTrees, $request) {
      $checkedTreeIds = array();
      foreach ($allTrees as $tree) {
         if ($request->getCheck(FamilyTreeUtil::toInputName($tree['name']))) {
//wfDebug("readTreeCheckboxes " . FamilyTreeUtil::toInputName($tree['name']) . ':' . $request->getVal(FamilyTreeUtil::toInputName($tree['name'])) . "\n");
            $checkedTreeIds[] = $tree['id'];
         }
      }
      return $checkedTreeIds;
   }

   // update which trees are checked
   // Don't forget that AddTreePagesJob calls this function when you remove revId!
   // addWatch is false when this function is called from FamilyTreePropagator.updateTrees, since watch will be added later
   public static function updateTrees($dbw, $title, $revId, $allTrees, $treeOwnerIds, $checkedTreeIds, $updateChecked=true, $addWatch=true) {
   	global $wgUser;
   	
   	$talkRevid = -1;
   	$result = true;
   	$added = false;
   	$removed = false;
   	foreach ($allTrees as $tree) {
         $treeId = $tree['id'];
   		if (in_array($treeId, $checkedTreeIds) && !in_array($treeId, $treeOwnerIds)) {
   			// add the page to the tree
//wfDebug("updateTrees add " . $tree['name'] . "\n");
   			if ($talkRevid == -1) {
   				$talkRevision = Revision::newFromTitle($title->getTalkPage());
   				if ($talkRevision) {
   					$talkRevid = $talkRevision->getId();
   				}
   				else {
   					$talkRevid = 0;
   				}
   			}
   			$result = $result && FamilyTreeUtil::addPage($dbw, $wgUser, $treeId, $title, $revId, $talkRevid);
   			$added = true;
   		}
   		else if (!in_array($treeId, $checkedTreeIds) && in_array($treeId, $treeOwnerIds)) {
   			// remove the page from the tree
//wfDebug("updateTrees remove\n");
   			$result = $result && FamilyTreeUtil::removePage($dbw, $treeId, $title);
   			$removed = true;
   		}

         if ($updateChecked) {
            $checked = (!$tree['checked'] && in_array($treeId, $checkedTreeIds));
            $unchecked = ($tree['checked'] && !in_array($treeId, $checkedTreeIds));
            if ($checked || $unchecked) {
               $dbw->update('familytree', array('ft_checked' => ($checked ? 1 : 0)), array('ft_tree_id' => $treeId));
               FamilyTreeUtil::deleteFamilyTreesCache($wgUser->getName());
            }
         }
   	}
   	if ($added && !$wgUser->isWatched($title)) {
//wfDebug("updateTrees - addWatch: ".$title->getPrefixedText()."\n");
         if ($addWatch) {
   		   StructuredData::addWatch($wgUser, new Article($title, 0));
         }
   	}
   	else if ($added || $removed) {
   		// purge the article and re-index it
		   StructuredData::purgeTitle($title);
			StructuredData::requestIndex($title);
   	}
   	return $result;
   }

   public static function createFamilyTree($db, $userName, $treeName) {
   	global $wgUser;
   	
   	if ($wgUser->isBlocked() || wfReadOnly()) {
      	return FTE_NOT_AUTHORIZED;
   	}
   	else if (!FamilyTreeUtil::isValidTreeName($treeName)) {
      	return FTE_INVALID_ARG;
	   }
	   
   	// create family tree record in database
	   $timestamp = wfTimestampNow();
	   $record = array('ft_user' => $userName, 'ft_name' => $treeName, 'ft_owner_last_opened_timestamp' => $timestamp);
      $db->insert('familytree', $record);
      FamilyTreeUtil::deleteFamilyTreesCache($userName);
      $status = FTE_SUCCESS;
      $errno = $db->lastErrno();
      if ($errno == 1062) {
      	$status = FTE_DUP_KEY;
      }
      else if ($errno > 0) {
      	$status = FTE_DB_ERROR;
      }
      // purge user page
      StructuredData::purgeTitle(Title::makeTitle(NS_USER, $userName));
      return $status;
   }

   public static function renameFamilyTree($db, $userName, $treeName, $newTreeName, $renameExisting=false) {
   	global $wgUser;

   	$newTreeName = trim($newTreeName);
   	
   	if ($wgUser->isBlocked() || wfReadOnly()) {
      	return FTE_NOT_AUTHORIZED;
   	}
   	else if (!FamilyTreeUtil::isValidTreeName($treeName) || !FamilyTreeUtil::isValidTreeName($newTreeName)) {
      	return FTE_INVALID_ARG;
	   }
	   
	   $status = FTE_SUCCESS;
      $treeId = $db->selectField('familytree', 'ft_tree_id', array('ft_user' => $userName, 'ft_name' => $treeName));
      $errno = $db->lastErrno();
      if ($errno > 0) {
      	$status = FTE_DB_ERROR;
		}
		else if ($treeId === false) {
			$status = FTE_NOT_FOUND;
		}
		else {
         $newTreeId = $db->selectField('familytree', 'ft_tree_id', array('ft_user' => $userName, 'ft_name' => $newTreeName));
         $errno = $db->lastErrno();
         if ($errno > 0) {
         	$status = FTE_DB_ERROR;
		   }
         else if ($renameExisting && $newTreeId === false) {
            $status = FTE_NOT_FOUND;
         }
         else if (!$renameExisting && $newTreeId !== false) {
		   	$status = FTE_DUP_KEY;
		   }
		}

		if ($status == FTE_SUCCESS) {
         if ($renameExisting) {
            // move pages
            if ($status == FTE_SUCCESS) {
               $sql = "insert ignore into familytree_page (fp_tree_id, fp_namespace, fp_title, fp_oldid, fp_flags, fp_data_version, fp_latest, fp_talk_oldid, fp_talk_latest, fp_uid, fp_user_id) ".
                       "(select ".$db->addQuotes($newTreeId).", fp_namespace, fp_title, fp_oldid, fp_flags, fp_data_version, fp_latest, fp_talk_oldid, fp_talk_latest, fp_uid, fp_user_id ".
                       " from familytree_page where fp_tree_id=".$db->addQuotes($treeId).")";
               $db->query($sql, 'renameFamilyTree');
               $errno = $db->lastErrno();
               if ($errno > 0) $status = FTE_DB_ERROR;
            }
            if ($status == FTE_SUCCESS) {
               $db->delete('familytree_page', array('fp_tree_id' => $treeId));
               $errno = $db->lastErrno();
               if ($errno > 0) $status = FTE_DB_ERROR;
            }
            // move data
            if ($status == FTE_SUCCESS) {
               $sql = "insert ignore into familytree_data (fd_tree_id, fd_namespace, fd_title, fd_data) ".
                       "(select ".$db->addQuotes($newTreeId).", fd_namespace, fd_title, fd_data ".
                       " from familytree_data where fd_tree_id=".$db->addQuotes($treeId).")";
               $db->query($sql, 'renameFamilyTree');
               $errno = $db->lastErrno();
               if ($errno > 0) $status = FTE_DB_ERROR;
            }
            if ($status == FTE_SUCCESS) {
               $db->delete('familytree_data', array('fd_tree_id' => $treeId));
               $errno = $db->lastErrno();
               if ($errno > 0) $status = FTE_DB_ERROR;
            }
            // update gedcoms
            if ($status == FTE_SUCCESS) {
               $db->update('familytree_gedcom', array('fg_tree_id' => $newTreeId), array('fg_tree_id' => $treeId));
               $errno = $db->lastErrno();
               if ($errno > 0) $status = FTE_DB_ERROR;
            }
            // remove tree
            if ($status == FTE_SUCCESS) {
               $db->delete('familytree', array('ft_tree_id' => $treeId));
               FamilyTreeUtil::deleteFamilyTreesCache($userName);
               $errno = $db->lastErrno();
               if ($errno > 0) $status = FTE_DB_ERROR;
            }
         }
         else {
            $db->update('familytree', array('ft_name' => $newTreeName), array('ft_tree_id' => $treeId));
            FamilyTreeUtil::deleteFamilyTreesCache($userName);
            $errno = $db->lastErrno();
            if ($errno > 0) $status = FTE_DB_ERROR;
         }
		}

      // re-index everything
		if ($status == FTE_SUCCESS) {
			$ts = wfTimestampNow();
			$sql = "insert into index_request (ir_page_id, ir_timestamp) select page_id, ".$db->addQuotes($ts)." from familytree_page, page ".
						"where fp_tree_id = ".$db->addQuotes($renameExisting ? $newTreeId : $treeId)." and fp_namespace = page_namespace and fp_title = page_title";
			$db->query($sql, 'renameFamilyTree');
         $errno = $db->lastErrno();
         if ($errno > 0) {
         	$status = FTE_DB_ERROR;
   		}
		}
		
      // purge user page
      StructuredData::purgeTitle(Title::makeTitle(NS_USER, $userName));
		return $status;
   }
   
   public static function deleteFamilyTree($db, $userName, $treeName, $delPages) {
   	global $wgUser;
   	
   	if ($wgUser->isBlocked() || wfReadOnly()) {
      	return FTE_NOT_AUTHORIZED;
   	}

   	$status = FTE_SUCCESS;
   	$treeId = $db->selectField('familytree', 'ft_tree_id', array('ft_user' => $userName, 'ft_name' => $treeName));
      $errno = $db->lastErrno();
      if ($errno > 0) {
		   $status = FTE_DB_ERROR;
		}
		else if ($treeId === false) {
		   $status = FTE_NOT_FOUND;
		}
		else {
		   // set up delete job (the delete job should also remove familytree_page/data/gedcom eventually)
	      $job = new DeleteFamilyTreeJob(array('tree_id' => $treeId, 'user' => $userName, 'delete_pages' => ($delPages ? '1' : '0')));
	      $job->insert();
         $errno = $db->lastErrno();
         if ($errno > 0) {
   		   $status = FTE_DB_ERROR;
   		}
		}
   	if ($status == FTE_SUCCESS) {
         // remove familytree
         $db->delete('familytree', array('ft_tree_id' => $treeId));
         FamilyTreeUtil::deleteFamilyTreesCache($userName);
         $errno = $db->lastErrno();
         if ($errno > 0) {
   		   $status = FTE_DB_ERROR;
   		}
		}
      // purge user page
      StructuredData::purgeTitle(Title::makeTitle(NS_USER, $userName));
		return $status;
   }
   
   /**
    * return true if the page can be put into family trees; return false for talk namespaces
    *
    */
   public static function isTreePage($namespace, $titleDbkey) {
   	return (($namespace == NS_MAIN && $titleDbkey != 'Main_Page') || $namespace == NS_IMAGE || $namespace == NS_USER || 
   				$namespace == NS_GIVEN_NAME || $namespace == NS_SURNAME || $namespace == NS_SOURCE || $namespace == NS_PLACE || 
   				$namespace == NS_PERSON || $namespace == NS_FAMILY || $namespace == NS_MYSOURCE || $namespace == NS_REPOSITORY ||
               $namespace == NS_PORTAL || $namespace == NS_TRANSCRIPT);
   }
}
?>
