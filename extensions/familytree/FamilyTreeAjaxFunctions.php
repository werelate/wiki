<?php

/*
* index.php?action=ajax&rs=functionName&rsargs=a=v|b=v|c=v
*/

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");
require_once("$IP/extensions/familytree/DeleteFamilyTreeJob.php");
require_once("$IP/extensions/familytree/WatchTreePagesJob.php");
require_once("$IP/extensions/other/Hooks.php");
require_once("$IP/extensions/AjaxUtil.php");

# Register with AjaxDispatcher as a function
$wgAjaxExportList[] = "wfOpenFamilyTreeExplorer";
$wgAjaxExportList[] = "wfListFamilyTrees";
$wgAjaxExportList[] = "wfCreateFamilyTree";
$wgAjaxExportList[] = "wfRenameFamilyTree";
$wgAjaxExportList[] = "wfCopyFamilyTree";
$wgAjaxExportList[] = "wfDeleteFamilyTree";
$wgAjaxExportList[] = "wfDeleteFamilyTreeId";
$wgAjaxExportList[] = "wfAddFamilyTreePage";
$wgAjaxExportList[] = "wfRemoveFamilyTreePage";
$wgAjaxExportList[] = "wfAcceptFamilyTreePage";
$wgAjaxExportList[] = "wfCreateFamilyTreePage";
$wgAjaxExportList[] = "wfBookmarkFamilyTreePage";
$wgAjaxExportList[] = "wfUnbookmarkFamilyTreePage";
$wgAjaxExportList[] = "wfSetPrimaryFamilyTreePage";
$wgAjaxExportList[] = "wfOpenFamilyTree";
$wgAjaxExportList[] = "wfDownloadFamilyTreePages";
$wgAjaxExportList[] = "wfUpdateData";
$wgAjaxExportList[] = "wfBrowse";
$wgAjaxExportList[] = "wfLog";

define( 'FTE_BOOKMARK_FLAG', 1);
define( 'FTE_UNBOOKMARK_FLAG', 254);

/**
 * Call when first opening FTE.  Returns logged-in user, any messages
 *
 * @return FTE_SUCCESS, FTE_NOT_LOGGED_IN
 */
function wfOpenFamilyTreeExplorer() {
	global $wgAjaxCachePolicy, $wgUser, $wrFTENotice;

   // set cache policy
   $wgAjaxCachePolicy->setPolicy(0);

   // validate arguments
   $status = FTE_SUCCESS;
   $user = '';
   $notice = '';
   if ($wgUser->isLoggedIn()) {
      $user = $wgUser->getName();
   }
   if ($wrFTENotice) {
      $notice = '<notice>' . StructuredData::escapeXml($wrFTENotice) . '</notice>';
   }
   $user = StructuredData::escapeXml($user);
   return "<open status=\"$status\"><user>$user</user>$notice</open>";
}

/**
 * List all family trees for a user
 *
 * @param unknown_type $args user
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_DB_ERROR
 */
function wfListFamilyTrees($args) {
	global $wgAjaxCachePolicy, $wgLang;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = FTE_SUCCESS;
   $result = '';
   $args = AjaxUtil::getArgs($args);
   if (!@$args['user']) {
      $status = FTE_INVALID_ARG;
   }
   else {
      $familyTrees = FamilyTreeUtil::getFamilyTrees($args['user'], true);
      if (is_null($familyTrees)) {
         $status = FTE_DB_ERROR;
      }
      else {
         foreach($familyTrees as $familyTree) {
            $treeName = StructuredData::escapeXml($familyTree['name']);
      	   $timestamp = StructuredData::escapeXml($wgLang->timeanddate(wfTimestamp(TS_MW, $familyTree['timestamp']), true));
            $result .= "<tree name=\"$treeName\" timestamp=\"$timestamp\" count=\"{$familyTree['count']}\"/>\n";
         }
      }
   }

	// return status
	return "<list status=\"$status\">$result</list>";
}

/**
 * Create a new family tree
 *
 * @param unknown_type $args user, name
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_DUP_KEY, FTE_DB_ERROR, FTE_INVALID_ARG
 */
function wfCreateFamilyTree($args) {
	global $wgAjaxCachePolicy, $wgUser;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = FTE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
   if (!@$args['user'] || !@$args['name']) {
      $status = FTE_INVALID_ARG;
   }
   else if (!$wgUser->isLoggedIn()) {
      $status = FTE_NOT_LOGGED_IN;
   }
   else if ($wgUser->isBlocked() || wfReadOnly() || $args['user'] != $wgUser->getName()) {
      $status = FTE_NOT_AUTHORIZED;
   }
   else {
	   $db =& wfGetDB( DB_MASTER );
	   $db->begin();
	   $db->ignoreErrors(true);
      $status = FamilyTreeUtil::createFamilyTree($db, $args['user'], $args['name']);
      if ($status == FTE_SUCCESS) {
         $db->commit();
      }
      else {
         $db->rollback();
      }
   }

	// return status
	return "<create status=\"$status\"></create>";
}

/**
 * Rename an existing family tree
 *
 * @param unknown_type $args user, name, newname
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfRenameFamilyTree($args) {
	global $wgAjaxCachePolicy, $wgUser;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = FTE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
   if (!@$args['user'] || !@$args['name'] || !@$args['newname']) {
      $status = FTE_INVALID_ARG;
   }
   else if (!$wgUser->isLoggedIn()) {
      $status = FTE_NOT_LOGGED_IN;
   }
   else if ($wgUser->isBlocked() || wfReadOnly() || $args['user'] != $wgUser->getName()) {
      $status = FTE_NOT_AUTHORIZED;
   }
   else {
	   $db =& wfGetDB( DB_MASTER );

	   $db->begin();
	   $db->ignoreErrors(true);
	   $status = FamilyTreeUtil::renameFamilyTree($db, $args['user'], $args['name'], $args['newname']);
		if ($status == FTE_SUCCESS) {
		   $db->commit();
		}
		else {
		   $db->rollback();
		}
   }

	// return status
	return "<rename status=\"$status\"></rename>";
}

/**
 * Copy a family tree
 *
 * @param unknown_type $args user, name, newuser, newname
 * @return FTE_SUCCESS, FT_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfCopyFamilyTree($args) {
	global $wgAjaxCachePolicy, $wgUser;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = FTE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
   if (!@$args['user'] || !@$args['name'] || !@$args['newuser'] || !@$args['newname']) {
      $status = FTE_INVALID_ARG;
   }
   else if (!$wgUser->isLoggedIn()) {
      $status = FTE_NOT_LOGGED_IN;
   }
   else if ($wgUser->isBlocked() || wfReadOnly() || $args['newuser'] != $wgUser->getName()) {
      $status = FTE_NOT_AUTHORIZED;
   }
   else {
	   $db =& wfGetDB( DB_MASTER );

	   $db->begin();
	   $db->ignoreErrors(true);
   	$rows = $db->select('familytree', array('ft_tree_id'), array('ft_user' => $args['user'], 'ft_name' => $args['name']));
	   $errno = $db->lastErrno();
   	if ($errno != 0) {
	     $status = FTE_DB_ERROR;
	   }
	   else if ($rows === false) {
		   $status = FTE_NOT_FOUND;
 	   }
	   else {
   	   // should only be one row in the result
   	   while ($row = $db->fetchObject($rows)) {
   	     $treeId = $row->ft_tree_id;
   	   }
   	   $db->freeResult($rows);
     	   $timestamp = wfTimestampNow();
     	   $record = array('ft_user' => $args['newuser'], 'ft_name' => $args['newname'], 'ft_owner_last_opened_timestamp' => $timestamp);
         if (!$db->insert('familytree', $record)) {
            // MYSQL specific
            $status = ($db->lastErrno() == 1062 ? FTE_DUP_KEY : FTE_DB_ERROR);
         }
         else {
            FamilyTreeUtil::deleteFamilyTreesCache($args['newuser']);
            // could use last_insert_id, but this would be mysql specific
      	   $newTreeId = $db->selectField('familytree', 'ft_tree_id', array('ft_user' => $args['newuser'], 'ft_name' => $args['newname']));
            $errno = $db->lastErrno();
            if ($errno > 0) {
      		   $status = FTE_DB_ERROR;
      		}
      		// copy familytree_page
				if ($status == FTE_SUCCESS) {      	   
	      	   $sql = 'INSERT into familytree_page (fp_tree_id, fp_namespace, fp_title, fp_oldid, fp_flags, fp_data_version, fp_latest, fp_talk_oldid, fp_talk_latest, fp_uid, fp_user_id) ' .
	      	          'SELECT '.$db->addQuotes($newTreeId).', fp_namespace, fp_title, fp_oldid, fp_flags, fp_data_version, fp_latest, fp_talk_oldid, fp_talk_latest, fp_uid, '.$db->addQuotes($wgUser->getID()).' '.
	      	          'FROM familytree_page WHERE fp_tree_id='.$db->addQuotes($treeId);
	   	   	$db->query($sql, 'wfCopyFamilyTree');
	            $errno = $db->lastErrno();
	            if ($errno > 0) {
	      		   $status = FTE_DB_ERROR;
	      		}
				}
				// copy familytree_data
				if ($status == FTE_SUCCESS) {      	   
	      	   $sql = 'INSERT into familytree_data (fd_tree_id, fd_namespace, fd_title, fd_data) ' .
	      	          'SELECT '.$db->addQuotes($newTreeId).', fd_namespace, fd_title, fd_data '.
	      	          'FROM familytree_data WHERE fd_tree_id='.$db->addQuotes($treeId);
	   	   	$db->query($sql, 'wfCopyFamilyTree');
	            $errno = $db->lastErrno();
	            if ($errno > 0) {
	      		   $status = FTE_DB_ERROR;
	      		}
				}
      		if ($status == FTE_SUCCESS) {
      		   // watch all pages
      		   $job = new WatchTreePagesJob(array('tree_id' => $newTreeId, 'user' => $wgUser->getName()));
	            $job->insert();
      		}
		   }
		}
		if ($status == FTE_SUCCESS) {
		   $db->commit();
		}
		else {
		   $db->rollback();
		}
   }

	// return status
	return "<copy status=\"$status\"></copy>";
}

// back-door for bot to delete family trees: pass in tree_id and user (name)
function wfDeleteFamilyTreeId($args) {
	global $wgAjaxCachePolicy, $wgUser, $wrBotUserID;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = FTE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
   if (!@$args['tree_id'] || !@$args['user']) {
      $status = FTE_INVALID_ARG;
   }
	else if (!$wgUser->isLoggedIn()) {
	   $status = FTE_NOT_LOGGED_IN;
	}
   else if ($wgUser->getID() != $wrBotUserID || $wgUser->isBlocked() || wfReadOnly()) {
      $status = FTE_NOT_AUTHORIZED;
   }
   else {
	   $db =& wfGetDB( DB_MASTER );
	   $db->begin();

	   // set up delete job (the delete job should also remove familytree_page/data/gedcom eventually)
	   $treeId = $args['tree_id'];
      $job = new DeleteFamilyTreeJob(array('tree_id' => $treeId, 'user' => $args['user'], 'delete_pages' => '1'));
      $job->insert();

      // remove familytree if exists
      $db->delete('familytree', array('ft_tree_id' => $treeId));
      FamilyTreeUtil::deleteFamilyTreesCache($args['user']);
	   $db->commit();
   }

	// return status
	return "<delete status=\"$status\"></delete>";
}

/**
 * Delete an existing family tree
 *
 * @param unknown_type $args user, name
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfDeleteFamilyTree($args) {
	global $wgAjaxCachePolicy, $wgUser;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = FTE_SUCCESS;
   $delPages = false;
   $args = AjaxUtil::getArgs($args);
   if (!@$args['user'] || !@$args['name']) {
      $status = FTE_INVALID_ARG;
   }
   else if (!$wgUser->isLoggedIn()) {
      $status = FTE_NOT_LOGGED_IN;
   }
   else if ($wgUser->isBlocked() || wfReadOnly() || $args['user'] != $wgUser->getName()) {
      $status = FTE_NOT_AUTHORIZED;
   }
   else {
      $delPages = (@$args['delete_pages'] == 1);
	   $db =& wfGetDB( DB_MASTER );

	   $db->begin();
	   $db->ignoreErrors(true);
	   $status = FamilyTreeUtil::deleteFamilyTree($db, $args['user'], $args['name'], $delPages);
		if ($status == FTE_SUCCESS) {
		   $db->commit();
		}
		else {
		   $db->rollback();
		}
   }

	// return status
	return "<delete status=\"$status\"></delete>";
}

/**
 * Open family tree
 *
 * @param unknown_type $args user, name
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_FOUND, FTE_DB_ERROR
 */
function wfOpenFamilyTree($args) {
	global $wgAjaxCachePolicy, $wgUser;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = FTE_SUCCESS;
   $result = '';
   $treeId = 0;
   $primary = '';
   $primaryNamespace = 0;
   $primaryTitle = '';
   $args = AjaxUtil::getArgs($args);
   if (!@$args['user'] || !@$args['name']) {
      $status = FTE_INVALID_ARG;
   }
   else {
		$dbr =& wfGetDB(DB_SLAVE);
		$dbr->ignoreErrors(true);
		$row = $dbr->selectRow('familytree', array('ft_tree_id', 'ft_primary_namespace', 'ft_primary_title'), array('ft_user' => $args['user'], 'ft_name' => $args['name']));
      $errno = $dbr->lastErrno();
		if ($errno > 0) {
		   $status = FTE_DB_ERROR;
		}
		else if ($row === false) {
		   $status = FTE_NOT_FOUND;
		}
		else {
			$treeId = $row->ft_tree_id;
			$primaryNamespace = $row->ft_primary_namespace;
			$primaryTitle = $row->ft_primary_title;
		}
   }
   //TODO - don't report error statuses here - do it on their user page or by email
//   if ($status == FTE_SUCCESS) {
//      $sql = "SELECT fg_status FROM familytree_gedcom WHERE fg_tree_id=$treeId AND ".
//             "(fg_status=".FG_STATUS_UPLOADED." OR fg_status=".FG_STATUS_PROCESSING." OR fg_status=".FG_STATUS_READY." OR fg_status >=".FG_STATUS_ERROR_START.")";
//      $rows = $dbr->query($sql);
//		$errno = $dbr->lastErrno();
//		if ($errno != 0) {
//		   $status = FTE_DB_ERROR;
//		}
//		else {
//     	while ($row = $dbr->fetchObject($rows)) {
//      	   $fgStatus = $row->fg_status;
//      	   if ($status == FTE_SUCCESS && $fgStatus == FG_STATUS_UPLOADED) {
//      	      $status = FTE_GEDCOM_WAITING;
//     	   }
//      	   else if ($fgStatus == FG_STATUS_PROCESSING) {
//      	      $status = FTE_GEDCOM_PROCESSING;
//      	      break;
//      	   }
//      	   else if ($fgStatus == FG_STATUS_READY && $wgUser->isLoggedIn() && $args['user'] == $wgUser->getName()) {
//      	      $gedcomReady = true;
//      	   }
//      	   else if ($fgStatus >= FG_STATUS_ERROR  && $wgUser->isLoggedIn() && $args['user'] == $wgUser->getName()) {
//      	      $status = -$fgStatus;
//      	   }
//	      }
//		   $dbr->freeResult($rows);
//		}
//   }
   if ($status == FTE_SUCCESS || $status == FTE_GEDCOM_WAITING || $status <= FTE_GEDCOM_ERROR_START) {
  		$rows = $dbr->select('familytree_page',
  		  array('fp_namespace', 'fp_title', 'fp_oldid', 'fp_latest', 'fp_flags', 'fp_data_version', 'fp_talk_oldid', 'fp_talk_latest'),
  		  array('fp_tree_id' => $treeId));
  		$errno = $dbr->lastErrno();
  		if ($errno > 0) {
  		   $status = FTE_DB_ERROR;
  		}
  		else if ($rows !== false) {
  			if ($primaryTitle) {
  				$t = Title::newFromText($primaryTitle, $primaryNamespace);
  				if ($t) {
  					$primary = " primary=\"".StructuredData::escapeXml($t->getPrefixedText()).'"';
  				}
  			}
  		   $result = ftWriteRows($dbr, $rows);
     		$dbr->freeResult($rows);
  		}
   }
   // update fg_status
   if ($wgUser->isLoggedIn() && $args['user'] == $wgUser->getName() && !wfReadOnly() && ($status == FTE_SUCCESS || $status == FTE_GEDCOM_WAITING || $status <= FTE_GEDCOM_ERROR_START)) {
      $dbw =& wfGetDB(DB_MASTER);
	   $dbw->begin();
	   $dbw->ignoreErrors(true);

	   // update timestamp
  	   $timestamp = wfTimestampNow();
      $dbw->update('familytree', array('ft_owner_last_opened_timestamp' => $timestamp), array('ft_tree_id' => $treeId));
      FamilyTreeUtil::deleteFamilyTreesCache($args['user']);
      if ($dbw->lastErrno() != 0) {
         $status = FTE_DB_ERROR;
      }

	   if ($status == FTE_DB_ERROR) {
	      $dbw->rollback();
	   }
	   else {
		   $dbw->commit();
		}
   }

   // return status
	return "<open status=\"$status\"$primary>$result</open>";
}

/**
 * Download text of one or more pages
 *
 * @param unknown_type $args ns, title, oldid (optional - if omitted or 0, gets current page)
 * @return unknown FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_FOUND
 */
function wfDownloadFamilyTreePages($args) {
   global $wgAjaxCachePolicy;

	$wgAjaxCachePolicy->setPolicy(0);
   $status = FTE_SUCCESS;
   $result = '';

  	$xml = simplexml_load_string($args);
  	$userName = (string)$xml['user'];
  	$treeName = (string)$xml['name'];
   if (!$userName || !$treeName) {
      $status = FTE_INVALID_ARG;
   }
   else {
  		$dbr =& wfGetDB(DB_SLAVE);
  		$dbr->ignoreErrors(true);
    	foreach ($xml->page as $page) {
     	   $reqNs = (string)$page['ns'];
     	   $reqTitle = (string)$page['title'];
     	   $reqOldid = (string)$page['oldid'];
     	   $reqData = (string)$page['data'];
         if (!$reqTitle) {
            $status = FTE_INVALID_ARG;
            $result = '';
            break;
         }
         $oldid = 0;
         $oldidString = '';
         $revid = '';
         $titleString = '';
         $ns = '';
         $data = '';
         $dataString = '';
         $title = Title::newFromText($reqTitle, $reqNs);
         if ($title && $title->exists()) {
            $revision = Revision::newFromTitle($title);
            if ($revision) {
               $titleString = $revision->getTitle()->getText();
               $ns = $revision->getTitle()->getNamespace();
               $revid = $revision->getId();
            }
            else {
               $status = FTE_NOT_FOUND;
               continue;
            }
         }
         else {
            $status = FTE_NOT_FOUND;
            continue;
         }
         // get oldid and/or data?
         if ($reqOldid || $reqData) {
            $isTalk = ($ns % 2 == 1);
            $nsMain = ($isTalk ? $ns - 1 : $ns);
            $quotedNs = $dbr->addQuotes($nsMain);
            $quotedTitle = $dbr->addQuotes($revision->getTitle()->getDBkey());
      		$query = 'SELECT ' .
      		   ($reqOldid ? ($isTalk ? 'fp_talk_oldid' : 'fp_oldid') : '') .
      		   ($reqOldid && $reqData ? ', ' : '') .
      		   ($reqData ? 'fd_data' : '') .
      		   ' FROM familytree ' .
      		   ($reqOldid ? ', familytree_page' : '') .
      		   ($reqData ? ', familytree_data' : '') .
      		   ' WHERE ft_user=' . $dbr->addQuotes($userName) . ' AND ft_name=' . $dbr->addQuotes($treeName) .
      		   ($reqOldid ? " AND ft_tree_id = fp_tree_id AND fp_namespace=$quotedNs AND fp_title=$quotedTitle" : '') .
      		   ($reqData ?  " AND ft_tree_id = fd_tree_id AND fd_namespace=$quotedNs AND fd_title=$quotedTitle" : '');
            $res = $dbr->query($query);
            $errno = $dbr->lastErrno();
      		if ($errno > 0) {
      		   $status = FTE_DB_ERROR;
      		   $result = '';
      		   break;
      		}
      		else if ($res !== false) {
      		   $row = $dbr->fetchObject($res);
      		   if ($row !== false) {
      		      if ($reqOldid) {
      		         $oldid = ($isTalk ? $row->fp_talk_oldid : $row->fp_oldid);
      		      }
      		      if ($reqData) {
      		         $data = $row->fd_data;
      		      }
      		   }
      		   $dbr->freeResult($res);
            }
         }

         // generate output
        	$titleString = StructuredData::escapeXml($titleString);
   	   $text = structuredData::escapeXml($revision->getText());
      	if ($reqOldid) {
      	   $oldidString = " oldid=\"$oldid\"";
      	   if ($oldid != $revid) {
      	      global $wgLang;
      	      $lastmodUser = StructuredData::escapeXml($revision->getUserText());
      	      $lastmodComment = StructuredData::escapeXml($revision->getComment());
      	      $lastmodTimestamp = StructuredData::escapeXml($wgLang->timeanddate($revision->getTimestamp(), true));
      	      $oldidString .= " user=\"$lastmodUser\" comment=\"$lastmodComment\" date=\"$lastmodTimestamp\"";
      	   }
      	}
      	if ($reqData) {
      	   $dataString = ' data="' . StructuredData::escapeXml($data) . '"';
      	}

         $result .= "<page ns=\"$ns\" title=\"$titleString\"$oldidString revid=\"$revid\"$dataString>$text</page>";
      }
   }

	return "<download status=\"$status\">$result</download>";
}

/**
 * Add the specified page to the specified tree
 *
 * @param unknown_type $args user, name, ns, title      else if ($args['user'] != $wgUser->getUserName()) {
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfAddFamilyTreePage($args) {
	global $wgAjaxCachePolicy, $wgUser;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$ns = '';
	$titleString = '';
	$revid = '';
	$talkRevid = '';

	// validate args
   $args = AjaxUtil::getArgs($args);
	$status = ftValidateUpdateFamilyTreePageArgs($args);
	if ($status == FTE_SUCCESS) {
	   // get tree id
	   $treeId = 0;
	   $status = ftGetTreeId($args['user'], $args['name'], $treeId);
	}
	if ($status == FTE_SUCCESS) {
   	// get page
      $ns = $args['ns'];
   	$titleString = $args['title'];
      $title = Title::newFromText($titleString, $ns);
	   if (!$title || $title->isTalkPage()) {
	      $status = FTE_INVALID_ARG;
	   }
	   else {
	      $revision = Revision::newFromTitle($title);
   	   if ($revision) {
   	      $revid = $revision->getId();
   	   }
   	   else {
   	      $status = FTE_NOT_FOUND;
   	   }
   	   $talkRevision = Revision::newFromTitle($title->getTalkPage());
   	   if ($talkRevision) {
   	      $talkRevid = $talkRevision->getId();
   	   }
   	   else {
   	      $talkRevid = 0;
   	   }
	   }
	}
	if ($status == FTE_SUCCESS) {
      $dbw =& wfGetDB( DB_MASTER );
      $dbw->ignoreErrors(true);
      $dbw->begin();
	   if (!FamilyTreeUtil::addPage($dbw, $wgUser, $treeId, $title, $revid, $talkRevid)) {
	      $status = FTE_DB_ERROR;
	   }
	   if ($status == FTE_SUCCESS) {
   		StructuredData::addWatch($wgUser, new Article($title, 0));
	   }
	   if ($status == FTE_SUCCESS) {
   	   $dbw->commit();
   	}
   	else {
   	   $dbw->rollback();
   	}
   }

	// return status
   $titleString = StructuredData::escapeXml($titleString);
  	return "<add status=\"$status\" ns=\"$ns\" title=\"$titleString\" revid=\"$revid\" talk_revid=\"$talkRevid\"></add>";
}

/**
 * Remove family tree page
 *
 * @param unknown_type $args user, name, ns, title
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfRemoveFamilyTreePage($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$ns = '';
	$titleString = '';
	$delete = false;
	$deleteStatus = '';

	// validate args
   $args = AjaxUtil::getArgs($args);
	$status = ftValidateUpdateFamilyTreePageArgs($args);
	if ($status == FTE_SUCCESS) {
	   // get tree id
	   $treeId = 0;
	   $status = ftGetTreeId($args['user'], $args['name'], $treeId);
	   $delete = (@$args['delete_page'] == 1);
	}
	if ($status == FTE_SUCCESS) {
   	// get page
      $ns = $args['ns'];
   	$titleString = $args['title'];
      $title = Title::newFromText($titleString, $ns);
	   if (!$title || $title->isTalkPage()) {
	      $status = FTE_INVALID_ARG;
	   }
	}
	if ($status == FTE_SUCCESS) {
  	   $dbw =& wfGetDB( DB_MASTER );
  	   $dbw->ignoreErrors(true);
  	   $dbw->begin();
  	   if (!FamilyTreeUtil::removePage($dbw, $treeId, $title)) {
  	      $status = FTE_DB_ERROR;
  	   }

	   if ($delete && $status == FTE_SUCCESS) {
	      $status = ftDelPage($title);
	      if ($status == FTE_NOT_AUTHORIZED) {
	         $deleteStatus = FTE_NOT_AUTHORIZED;
	         $status = FTE_SUCCESS;
	      }
	      else if ($status == FTE_SUCCESS) {
	         $deleteStatus = FTE_SUCCESS;
	      }
	      $deleteStatus = " delete_status=\"$deleteStatus\"";
	   }
  		if ($status == FTE_SUCCESS) {
 		   $dbw->commit();
 		}
 		else {
 		   $dbw->rollback();
 		}
   }

	// return status
   $titleString = StructuredData::escapeXml($titleString);
  	return "<remove status=\"$status\" ns=\"$ns\" title=\"$titleString\"$deleteStatus></remove>";
}

/**
 * Accept changes for family tree page
 *
 * @param unknown_type $args user, name, ns, title
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfAcceptFamilyTreePage($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$ns = '';
	$titleString = '';
	$revid = '';

	// validate args
   $args = AjaxUtil::getArgs($args);
	$status = ftValidateUpdateFamilyTreePageArgs($args);
	if ($status == FTE_SUCCESS) {
	   // get tree id
	   $treeId = 0;
	   $status = ftGetTreeId($args['user'], $args['name'], $treeId);
	}
	if ($status == FTE_SUCCESS) {
   	// get page
      $ns = $args['ns'];
      if ($ns % 2 == 1) {
         $mainNs = $ns - 1;
         $isTalk = true;
      }
      else {
         $mainNs = $ns;
         $isTalk = false;
      }
   	$titleString = $args['title'];
      $title = Title::newFromText($titleString, $ns);
	   if (!$title) {
	      $status = FTE_INVALID_ARG;
	   }
	}
	if ($status == FTE_SUCCESS) {
  	   $dbw =& wfGetDB( DB_MASTER );
  	   $dbw->ignoreErrors(true);
  	   $dbw->begin();
  	   // read revision
  	   $revision = Revision::newFromTitle($title);
 		if ($revision === null) {
 		   $status = FTE_NOT_FOUND;
 		}
 		if ($status == FTE_SUCCESS) {
     	   $revid = $revision->getId();
         $dbw->update('familytree_page', ($isTalk ? array('fp_talk_oldid' => $revid) : array('fp_oldid' => $revid)),
                   array('fp_tree_id' => $treeId, 'fp_namespace' => $mainNs, 'fp_title' => $title->getDBkey()));
         $errno = $dbw->lastErrno();
         if ($errno > 0) {
            $status = FTE_DB_ERROR;
    		}
 		}
// saving a #redirect now causes redirected page to be removed from all trees and redirected-to page to be put in all trees (and watched) in FamilyTreePropagator
// 		if ($status == FTE_SUCCESS) {
//         $newTitle = Title::newFromRedirect( $revision->getText() );
//         if ($newTitle !== null) { // if this is a redirect, then remove the old title and replace it with the new title in this tree
// 		      $status = ftRedirect($dbw, $wgUser, $treeId, $title, $newTitle);
//         }
// 		}
  		if ($status == FTE_SUCCESS) {
 		   $dbw->commit();
 		}
 		else {
 		   $dbw->rollback();
 		}
   }

	// return status
   $titleString = StructuredData::escapeXml($titleString);
  	return "<accept status=\"$status\" ns=\"$ns\" title=\"$titleString\" revid=\"$revid\"></accept>";
}

/**
 * Update Data for a page
 *
 * @param unknown_type $args ns, title, oldid (optional - if omitted or 0, gets current page)
 * @return unknown FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_FOUND
 */
function wfUpdateData($args) {
   global $wgAjaxCachePolicy;

	$wgAjaxCachePolicy->setPolicy(0);
   $status = FTE_SUCCESS;
   $dataVersion = 0;

  	$xml = simplexml_load_string($args);
  	$args = array();
  	$args['user'] = (string)$xml['user'];
  	$args['name'] = (string)$xml['name'];
  	$args['ns'] = (string)$xml['ns'];
  	$args['title'] = (string)$xml['title'];
  	$data = $xml->data->asXML();
	$status = ftValidateUpdateFamilyTreePageArgs($args);
	if ($status == FTE_SUCCESS) {
	   // get tree id
	   $treeId = 0;
	   $status = ftGetTreeId($args['user'], $args['name'], $treeId);
	}
	if ($status == FTE_SUCCESS) {
   	$nsMain = ($args['ns'] % 2 == 0 ? $args['ns'] : $args['ns'] - 1);
      $title = Title::newFromText($args['title'], $nsMain);
  		$dbw =& wfGetDB(DB_MASTER);
  		$dbw->ignoreErrors(true);
  	   $dbw->begin();
//      wfDebug("wfUpdateData treeId=$treeId namespace=$nsMain title={$title->getDBkey()}\n");
	   $query = 'UPDATE familytree_page SET fp_data_version=' . ($data ? 'last_insert_id(fp_data_version+1)' : '0') .
        		     ' WHERE fp_tree_id=' . $treeId . ' AND fp_namespace=' . $dbw->addQuotes($nsMain) . ' AND fp_title=' . $dbw->addQuotes($title->getDBkey());
  		$dbw->query($query, 'wfUpdateData');
      $errno = $dbw->lastErrno();
      if ($errno == 0 && $data) {
			$dataVersion = $dbw->selectField('', 'last_insert_id()', null);
//         wfDebug("wfUpdateData dataVersion=$dataVersion\n");
			$errno = $dbw->lastErrno();
      }
      if ($errno > 0) {
 		   $status = FTE_DB_ERROR;
  		}
      else {
     		if ($data && $dataVersion == 1) {
     		   $record = array('fd_tree_id' => $treeId, 'fd_namespace' => $nsMain, 'fd_title' => $title->getDBkey(), 'fd_data' => $data);
            $dbw->insert('familytree_data', $record);
//            wfDebug("wfUpdateData insert\n");
     		}
     		else if ($data && $dataVersion > 1) {
            $dbw->update('familytree_data', array('fd_data' => $data), array('fd_tree_id' => $treeId, 'fd_namespace' => $nsMain, 'fd_title' => $title->getDBkey()));
//            wfDebug("wfUpdateData update\n");
     		}
     		else {
            $dbw->delete('familytree_data', array('fd_tree_id' => $treeId, 'fd_namespace' => $nsMain, 'fd_title' => $title->getDBkey()));
//            wfDebug("wfUpdateData delete\n");
     		}
         $errno = $dbw->lastErrno();
         if ($errno > 0) {
    		   $status = FTE_DB_ERROR;
     		}
      }
		if ($status == FTE_SUCCESS) {
		   $dbw->commit();
		}
		else {
		   $dbw->rollback();
		}
	}

	return "<updateData status=\"$status\" dataVersion=\"$dataVersion\"></updateData>";
}

/**
 * Create family tree page
 *
 * @param unknown_type $args user, name, ns, title
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfCreateFamilyTreePage($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrBotUserID, $wgArticle, $wgTitle;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = FTE_SUCCESS;
	$ns = '';
	$titleString = '';
	$revid = 0;

  	$xml = simplexml_load_string($args);
   $userName = (string)$xml['user'];
   $treeName = (string)$xml['name'];
  	$ns = (int)$xml['ns'];
  	$titleString = (string)$xml['title'];
  	$text = (string)$xml;
   if (!$userName || !$treeName || !$titleString || !($ns == NS_PERSON || $ns == NS_FAMILY)) {
      $status = FTE_INVALID_ARG;
   }
   else if (!$wgUser->isLoggedIn()) {
      $status = FTE_NOT_LOGGED_IN;
   }
   else if ($userName != $wgUser->getName() || $wgUser->isBlocked() || wfReadOnly()) {
      $status = FTE_NOT_AUTHORIZED;
   }
	if ($status == FTE_SUCCESS) {
	   // get tree id
	   $treeId = 0;
	   $status = ftGetTreeId($userName, $treeName, $treeId);
	   if ($status == FTE_SUCCESS && !$treeId) {
	      $status = FTE_NOT_FOUND;
	   }
	}
	if ($status == FTE_SUCCESS) {
      $dbw =& wfGetDB( DB_MASTER );
      $dbw->ignoreErrors(true);
      $dbw->begin();

      $title = Title::newFromText($titleString, $ns);
      if ($title == null) {
         $status = FTE_INVALID_ARG;
      }
      else {
   	   if (!StructuredData::titleStringHasId($titleString)) {
   	      $title = StructuredData::appendUniqueId($title, $dbw);
   	   }
         $article = new Article($title, 0);
         if ($article->exists()) {
            $status = FTE_DUP_KEY;
         }
      }
      if ($status == FTE_SUCCESS) {
         // set the global article and title to this, so that propagation works ok
         $wgArticle = $article;
         $wgTitle = $article->getTitle();
         // NOTE: This doesn't execute the code in FamilyTreePropagator to update familytree_page and add page to tree, but we don't want that to be called
         // because we add the page to the tree below
         if (!$article->doEdit($text, 'creating initial family tree', EDIT_NEW)) {
            $status = FTE_WIKI_ERROR;
         }
         else {
            $revid = $article->mRevIdEdited;
         }
      }
      // add the page to the tree
      if ($status == FTE_SUCCESS) {
   	   if (!FamilyTreeUtil::addPage($dbw, $wgUser, $treeId, $title, $revid, 0)) {
   	      $status = FTE_DB_ERROR;
   	   }
      }
      
      // watch the page
	   if ($status == FTE_SUCCESS) {
   		StructuredData::addWatch($wgUser, $article, true);
	   }
	   
   	if ($status == FTE_SUCCESS) {
     	   $dbw->commit();
     	}
     	else {
     	   $dbw->rollback();
     	}
   }

	// return status
   $titleString = StructuredData::escapeXml($title->getText());
  	return "<create status=\"$status\" ns=\"$ns\" title=\"$titleString\" latest=\"$revid\"></create>";
}

/**
 * Bookmark family tree page
 *
 * @param unknown_type $args user, name, ns, title
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfBookmarkFamilyTreePage($args) {
   $args = AjaxUtil::getArgs($args);
   $status = ftSetFlags($args, FTE_BOOKMARK_FLAG, true);

	// return status
	$ns = @$args['ns'];
   $titleString = StructuredData::escapeXml(@$args['title']);
  	return "<bookmark status=\"$status\" ns=\"$ns\" title=\"$titleString\"></bookmark>";
}

/**
 * Unbookmark family tree page
 *
 * @param unknown_type $args user, name, ns, title
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfUnbookmarkFamilyTreePage($args) {
   $args = AjaxUtil::getArgs($args);
   $status = ftSetFlags($args, FTE_UNBOOKMARK_FLAG, false);

	// return status
	$ns = @$args['ns'];
   $titleString = StructuredData::escapeXml(@$args['title']);
  	return "<unbookmark status=\"$status\" ns=\"$ns\" title=\"$titleString\"></unbookmark>";
}

/**
 * Bookmark family tree page
 *
 * @param unknown_type $args user, name, ns, title
 * @return FTE_SUCCESS, FTE_INVALID_ARG, FTE_NOT_LOGGED_IN, FTE_NOT_AUTHORIZED, FTE_NOT_FOUND, FTE_DUP_KEY, FTE_DB_ERROR
 */
function wfSetPrimaryFamilyTreePage($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$ns = '';
	$titleString = '';

	// validate args
   $args = AjaxUtil::getArgs($args);
	$status = ftValidateUpdateFamilyTreePageArgs($args);
	if ($status == FTE_SUCCESS) {
	   // get tree id
	   $treeId = 0;
	   $status = ftGetTreeId($args['user'], $args['name'], $treeId);
	}
	if ($status == FTE_SUCCESS) {
   	// get page
      $ns = $args['ns'];
   	$titleString = $args['title'];
      $title = Title::newFromText($titleString, $ns);
	   if (!$title) {
	      $status = FTE_INVALID_ARG;
	   }
	}
	if ($status == FTE_SUCCESS) {
  	   $dbw =& wfGetDB( DB_MASTER );
  	   $dbw->ignoreErrors(true);
  	   $dbw->begin();
      $dbw->update('familytree', array('ft_primary_namespace' => $title->getNamespace(), 'ft_primary_title' => $title->getDBkey()), 
	      					array('ft_tree_id' => $treeId));
      FamilyTreeUtil::deleteFamilyTreesCache($args['user']);
      $errno = $dbw->lastErrno();
      if ($errno > 0) {
         $status = FTE_DB_ERROR;
 		}
  		if ($status == FTE_SUCCESS) {
 		   $dbw->commit();
 		}
 		else {
 		   $dbw->rollback();
 		}
   }
	// return status
	$ns = @$args['ns'];
   $titleString = StructuredData::escapeXml(@$args['title']);
  	return "<setprimary status=\"$status\" ns=\"$ns\" title=\"$titleString\"></setprimary>";
}

function wfBrowse($args) {
	global $wgAjaxCachePolicy, $wgUser, $wgContLang;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// init vars
   $status = FTE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$ns = @$args['ns'];
	$nsText = $wgContLang->getNsText($ns);
	$scope = @$args['scope'];
	$titleString = @$args['title'];
	$titleKey = @$args['title'];
	$dir = @$args['dir'];
	$prev = '';
	$next = '';
   $result = '';

	// validate input arguments
   if ($scope != 'all' && !$wgUser->isLoggedIn()) {
      $status = FTE_NOT_LOGGED_IN;
   }
   
   if ($status == FTE_SUCCESS) {
   	if ($titleString) {
			$title = Title::newFromText($titleString, $ns);
			if ($title) {
				$ns = $title->getNamespace();
				$nsText = $title->getNsText();
				$titleString = $title->getText();
				$titleKey = $title->getDBkey();
			}
   	}

		// issue query
	   $status = ftBrowseResults($scope, $ns, $titleKey, $dir, $cnt, $result);

	   // if paging up and not enough results, start over from the beginning
	   if ($status == FTE_SUCCESS && $dir == -1 && $cnt < 10) {
	   	$dir = 1;
	   	$titleKey = '';
	   	$titleString = '';
	   	$status = ftBrowseResults($scope, $ns, $titleKey, $dir, $cnt, $result);
	   }
   }
   
   if ($status == FTE_SUCCESS) {
	   if ($dir == -1) {
	   	if ($cnt == 11) {
	   		$prev = '1';
	   	}
	   	$next = '1';
	   }
	   else {
		   if ($titleKey) {
		   	$prev = '1';
		   }
		   if ($cnt == 11) {
		   	$next = '1';
		   }
	   }
	}
	
	// return
   $titleString = StructuredData::escapeXml($titleString);
	return "<browse status=\"$status\" ns=\"$ns\" nsText=\"$nsText\" title=\"$titleString\" dir=\"$dir\" prev=\"$prev\" next=\"$next\">$result</browse>";
}	

function wfLog($args) {
   global $wgContLang;

   wfDebug("log1:$args\n");
   $args = $wgContLang->recodeInput(js_unescape($args));
   wfDebug("log2:$args\n");
}

function ftBrowseResults($scope, $ns, $titleKey, $dir, &$cnt, &$result) {
	global $wgUser;
	
	$cnt = 0;
	$result = '';
	$status = FTE_SUCCESS;
	$comp = '>=';
	$order = '';
	if ($dir == 1) {
		$comp = '>';
	}
	else if ($dir == -1) {
		$comp = '<';
		$order = 'DESC';
	}

	// construct SQL and issue query
	$dbr =& wfGetDB( DB_SLAVE );
	if ($scope == 'all') {
		$sql = 'SELECT page_title FROM page USE INDEX(name_title) WHERE page_namespace = ' . $dbr->addQuotes($ns) . 
					" AND page_is_redirect=0 AND page_title $comp " . $dbr->addQuotes($titleKey) . " ORDER BY page_title $order LIMIT 11";
	}
	else {
		$sql = 'SELECT wl_title FROM watchlist USE INDEX(wl_user) WHERE wl_user = ' . $dbr->addQuotes($wgUser->getID()) . ' AND wl_namespace = ' . $dbr->addQuotes($ns) . 
					" AND wl_title $comp " . $dbr->addQuotes($titleKey) . " ORDER BY wl_title $order LIMIT 11";
	}
	$dbr->ignoreErrors(true);
	$rows = $dbr->query($sql);
   $errno = $dbr->lastErrno();
	if ($errno == 0) {
	   while ($row = $dbr->fetchRow($rows)) {
	   	$cnt++;
	   	if ($cnt == 11) {
	   		break;
	   	}
	   	$t = Title::makeTitle($ns, $row[0]);
	   	if ($dir == -1) {
		   	$result = '<result>' . StructuredData::escapeXml($t->getText()) . '</result>' . $result;
	   	}
	   	else {
		   	$result = $result . '<result>' . StructuredData::escapeXml($t->getText()) . '</result>';
	   	}
	   }
	   $dbr->freeResult($rows);
	}
	else {
		$status = FTE_DB_ERROR;
	}
	
   return $status;
}

function ftSetFlags($args, $flags, $bitOr) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$ns = '';
	$titleString = '';

	// validate args
	$status = ftValidateUpdateFamilyTreePageArgs($args);
	if ($status == FTE_SUCCESS) {
	   // get tree id
	   $treeId = 0;
	   $status = ftGetTreeId($args['user'], $args['name'], $treeId);
	}
	if ($status == FTE_SUCCESS) {
   	// get page
      $ns = $args['ns'];
   	$titleString = $args['title'];
      $title = Title::newFromText($titleString, $ns);
	   if (!$title) {
	      $status = FTE_INVALID_ARG;
	   }
	}
	if ($status == FTE_SUCCESS) {
  	   $dbw =& wfGetDB( DB_MASTER );
  	   $dbw->ignoreErrors(true);
  	   $dbw->begin();
   	$dbw->query('update familytree_page set fp_flags = fp_flags ' . ($bitOr ? '| ' : '& ') . $flags . ' where fp_tree_id=' . $dbw->addQuotes($treeId) .
   	              ' and fp_namespace=' . $dbw->addQuotes($title->getNamespace()) . ' and fp_title=' . $dbw->addQuotes($title->getDBkey()), 'ftSetFlags');
      $errno = $dbw->lastErrno();
      if ($errno > 0) {
         $status = FTE_DB_ERROR;
 		}
  		if ($status == FTE_SUCCESS) {
 		   $dbw->commit();
 		}
 		else {
 		   $dbw->rollback();
 		}
   }

   return $status;
}

function ftValidateUpdateFamilyTreePageArgs($args) {
   global $wgUser;

   $status = FTE_SUCCESS;
   if (!@$args['user'] || !@$args['name'] || !isset($args['title']) || !isset($args['ns'])) {
      $status = FTE_INVALID_ARG;
   }
   else if (!$wgUser->isLoggedIn()) {
      $status = FTE_NOT_LOGGED_IN;
   }
   else if ($args['user'] != $wgUser->getName() || $wgUser->isBlocked() || wfReadOnly()) {
      $status = FTE_NOT_AUTHORIZED;
   }
   return $status;
}

function ftGetTreeId($user, $name, &$treeId) {
   $status = FTE_SUCCESS;
	$dbr =& wfGetDB( DB_SLAVE );

	$dbr->ignoreErrors(true);
	$treeId = $dbr->selectField('familytree', 'ft_tree_id', array('ft_user' => $user, 'ft_name' => $name));
   $errno = $dbr->lastErrno();
   if ($errno > 0) {
     $status = FTE_DB_ERROR;
	}
	else if ($treeId === false) {
	   $status = FTE_NOT_FOUND;
	}
   return $status;
}

function ftWriteRows($dbr, $rows) {
   $result = array();
	while ($row = $dbr->fetchObject($rows)) {
	   $title = Title::makeTitle($row->fp_namespace, $row->fp_title);
	   $titleString = StructuredData::escapeXml($title->getText());
      $lastmod = '';
	   if ($row->fp_oldid != $row->fp_latest) {
	      $status = FTE_SUCCESS;
	      list ($lastmodUser, $lastmodDate, $lastmodComment) = ftGetLastmod($dbr, $row->fp_latest, $status);
	      if ($status == FTE_SUCCESS) {
	         $lastmodUser = StructuredData::escapeXml($lastmodUser);
	         $lastmodDate = StructuredData::escapeXml($lastmodDate);
	         $lastmodComment = StructuredData::escapeXml($lastmodComment);
   	      $lastmod .= " u=\"$lastmodUser\" d=\"$lastmodDate\" c=\"$lastmodComment\"";
	      }
	   }
	   if ($row->fp_talk_oldid != $row->fp_talk_latest) {
	      $status = FTE_SUCCESS;
	      list ($lastmodUser, $lastmodDate, $lastmodComment) = ftGetLastmod($dbr, $row->fp_talk_latest, $status);
	      if ($status == FTE_SUCCESS) {
	         $lastmodUser = StructuredData::escapeXml($lastmodUser);
	         $lastmodDate = StructuredData::escapeXml($lastmodDate);
	         $lastmodComment = StructuredData::escapeXml($lastmodComment);
   	      $lastmod .= " tu=\"$lastmodUser\" td=\"$lastmodDate\" tc=\"$lastmodComment\"";
	      }
	   }
      $flags = $row->fp_flags;
      $oldid = $row->fp_oldid;
      $talkOldid = $row->fp_talk_oldid;
      $dataVersion = $row->fp_data_version;
	   $result[] = "<p n=\"{$title->getNamespace()}\" t=\"$titleString\" o=\"{$oldid}\" l=\"{$row->fp_latest}\" dv=\"{$dataVersion}\" f=\"{$flags}\" to=\"{$talkOldid}\" tl=\"{$row->fp_talk_latest}\"$lastmod/>\n";
	}
   return join('',$result);
}

// also called by DeleteFamilyTreeJob
function ftDelPage($title, $rc=true) {
   global $wgUser;

   // check authorized
   if(!$wgUser->isAllowed('delete', $title) || $wgUser->isBlocked() || wfReadOnly()) {
      return FTE_NOT_AUTHORIZED;
   }
	if( wfReadOnly() ) {
		return FTE_WIKI_ERROR;
	}
	// Better double-check that it hasn't been deleted yet!
	$dbw =& wfGetDB( DB_MASTER );
	$conds = $title->pageCond();
	$latest = $dbw->selectField( 'page', 'page_latest', $conds);
	if ( $latest === false ) {
	  // already deleted
		return FTE_SUCCESS;
	}

   $reason = 'deleting tree';

   // delete the image file if this is an image
	if ($title->getNamespace() == NS_IMAGE) {
		$img = new Image( $title );
		$ok = $img->delete( $reason );
		if( !$ok ) {
			return FTE_WIKI_ERROR;
		}
	}
	
   // do the deletion
   $article = new Article($title, 0);
	if (wfRunHooks('ArticleDelete', array(&$article, &$wgUser, &$reason))) {
		if ( $article->doDeleteArticle( $reason, $rc ) ) {
			wfRunHooks('ArticleDeleteComplete', array(&$article, &$wgUser, $reason));
			return FTE_SUCCESS;
		} else {
		   return FTE_WIKI_ERROR;
		}
	}
   else {
      return FTE_WIKI_ERROR;
   }
}

function ftGetLastmod($db, $revid, &$status) {
   global $wgLang;

   $userName = '';
   $timestamp = '';
   $comment = '';
	$rows = $db->select('revision', array('rev_user_text', 'rev_timestamp', 'rev_comment'), array('rev_id' => $revid));
	$errno = $db->lastErrno();
	if ($errno != 0 || $rows === false) {
	   $status = FTE_DB_ERROR;
	}
	else {
	   // should only be one row in the result
   	while ($row = $db->fetchObject($rows)) {
   	   $userName = $row->rev_user_text;
   	   $timestamp = $wgLang->timeanddate(wfTimestamp(TS_MW, $row->rev_timestamp), true);
   	   $comment = $row->rev_comment;
   	}
	   $db->freeResult($rows);
	}
   return array($userName, $timestamp, $comment);
}

?>
