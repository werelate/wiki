<?php

/*
* index.php?action=ajax&rs=functionName&rsargs=a=v|b=v|c=v
*/

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");


# Register with AjaxDispatcher as a function
# call in this order to index
$wgAjaxExportList[] = "wfGetNewMoveLogEntries"; // call before index requests
$wgAjaxExportList[] = "wfGetNewIndexRequests";    // call revisions after index requests, because an
$wgAjaxExportList[] = "wfGetNewRevisions";        //   index request might not change the revision timestamp
$wgAjaxExportList[] = "wfGetNewDeleteLogEntries"; // call after the other two
$wgAjaxExportList[] = "wfGetAllPageIds";
$wgAjaxExportList[] = "wfGetPageIndexContents";
$wgAjaxExportList[] = "wfGetPageRedirects";

define( 'IAF_SUCCESS', 0);
define( 'IAF_INVALID_ARG', 1);
define( 'IAF_DB_ERROR', 2);

define( 'IAF_MAX_COUNT_IDS', 10000);
define( 'IAF_MAX_COUNT_CONTENTS', 100);

/**
 * Get Id's of pages with an index request > specified index request id
 *
 * @param unknown_type $args ir_id=index request id|max=maximum to return (defaults to IAF_MAX_COUNT_IDS)
 * @return IAF_SUCCESS, IAF_INVALID_ARG
 */
function wfGetNewIndexRequests($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = IAF_SUCCESS;
   $result = array();
   $args = iafGetArgs($args);
   $irid = iafGetArg($args, 'ir_id', null);
   $max = iafGetArg($args, 'max', IAF_MAX_COUNT_IDS);
   if (is_null($irid)) {
      $status = IAF_INVALID_ARG;
   }
   else {
	   $db =& wfGetDB( DB_SLAVE );
	   $db->ignoreErrors(true);
   	$rows = $db->select('index_request', array('ir_id', 'ir_page_id'), array('ir_id > '.$db->addQuotes($irid)), 
   								'wfGetNewIndexRequests', array('LIMIT' => intval($max), 'ORDER BY' => 'ir_id'));
	   $errno = $db->lastErrno();
   	if ($errno != 0) {
	     $status = IAF_DB_ERROR;
	   }
	   else {
   	   while ($row = $db->fetchObject($rows)) {
  	   		$result[] = '<row ir_id="'.$row->ir_id.'" page_id="'.$row->ir_page_id.'"/>';
   	   }
   	   $db->freeResult($rows);
	   }
   }

	// return status
	return "<result status=\"$status\">".join("\n",$result).'</result>';
}

/**
 * Get Id's of pages with rev_id > specified rev_id
 *
 * @param unknown_type $args rev_id=revision id|max=maximum to return (defaults to IAF_MAX_COUNT_IDS)
 * @return IAF_SUCCESS, IAF_INVALID_ARG
 */
function wfGetNewRevisions($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = IAF_SUCCESS;
   $result = array();
   $args = iafGetArgs($args);
   $revid = iafGetArg($args, 'rev_id', null);
   $max = iafGetArg($args, 'max', IAF_MAX_COUNT_IDS);
   if (is_null($revid)) {
      $status = IAF_INVALID_ARG;
   }
   else {
	   $db =& wfGetDB( DB_SLAVE );
	   $db->ignoreErrors(true);
   	$rows = $db->select('revision', array('rev_id', 'rev_page', 'rev_timestamp'), array('rev_id > '.$db->addQuotes($revid)), 
   								'wfGetNewRevisions', array('LIMIT' => intval($max), 'ORDER BY' => 'rev_id'));
	   $errno = $db->lastErrno();
   	if ($errno != 0) {
	     $status = IAF_DB_ERROR;
	   }
	   else {
   	   while ($row = $db->fetchObject($rows)) {
   	   	$result[] = '<row rev_id="'.$row->rev_id.'" page_id="'.$row->rev_page.'" rev_timestamp="'.$row->rev_timestamp.'"/>';
   	   }
   	   $db->freeResult($rows);
	   }
   }

	// return status
	return "<result status=\"$status\">".join("\n",$result).'</result>';
}

/**
 * Get source and target id's of moved pages with timestamp >= specified timestamp
 *
 * @param unknown_type $args timestamp=timestamp|max=maximum to return (defaults to IAF_MAX_COUNT_IDS)
 * @return IAF_SUCCESS, IAF_INVALID_ARG
 */
function wfGetNewMoveLogEntries($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = IAF_SUCCESS;
   $result = array();
   $args = iafGetArgs($args);
   $ts = iafGetArg($args, 'timestamp', null);
   $max = iafGetArg($args, 'max', IAF_MAX_COUNT_IDS);
   if (is_null($ts)) {
      $status = IAF_INVALID_ARG;
   }
   else {
	   $db =& wfGetDB( DB_SLAVE );
	   $db->ignoreErrors(true);
   	$rows = $db->select('logging', array('log_timestamp', 'log_namespace', 'log_title', 'log_params'), array('log_type'=>'move', 'log_timestamp >= '.$db->addQuotes($ts)),
   								'wfGetNewMoveLogEntries', array('LIMIT' => intval($max), 'ORDER BY' => 'log_timestamp'));
	   $errno = $db->lastErrno();
   	if ($errno != 0) {
	     $status = IAF_DB_ERROR;
	   }
	   else {
   	   while ($row = $db->fetchObject($rows)) {
            $t = Title::makeTitle($row->log_namespace, $row->log_title);
            $sourceTitle = $t ? StructuredData::escapeXml($t->getPrefixedText()) : "";
            $sourceId = $t ? $t->getArticleID() : 0;
            $t = Title::newFromText($row->log_params);
            $targetId = $t ? $t->getArticleID() : 0;
   	   	$result[] = '<row timestamp="'.$row->log_timestamp.'" source_id="'.$sourceId.'" source_title="'.$sourceTitle.'" target_id="'.$targetId.'"/>';
   	   }
   	   $db->freeResult($rows);
	   }
   }

	// return status
	return "<result status=\"$status\">".join("\n",$result).'</result>';
}

/**
 * Get Id's of deleted/undeleted pages with timestamp >= specified timestamp
 *
 * @param unknown_type $args timestamp=timestamp|max=maximum to return (defaults to IAF_MAX_COUNT_IDS)
 * @return IAF_SUCCESS, IAF_INVALID_ARG
 */
function wfGetNewDeleteLogEntries($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = IAF_SUCCESS;
   $result = array();
   $args = iafGetArgs($args);
   $ts = iafGetArg($args, 'timestamp', null);
   $max = iafGetArg($args, 'max', IAF_MAX_COUNT_IDS);
   if (is_null($ts)) {
      $status = IAF_INVALID_ARG;
   }
   else {
	   $db =& wfGetDB( DB_SLAVE );
	   $db->ignoreErrors(true);
   	$rows = $db->select('logging', array('log_timestamp', 'log_action', 'log_params'), array('log_type'=>'delete', 'log_timestamp >= '.$db->addQuotes($ts)),
   								'wfGetNewDeleteLogEntries', array('LIMIT' => intval($max), 'ORDER BY' => 'log_timestamp'));
	   $errno = $db->lastErrno();
   	if ($errno != 0) {
	     $status = IAF_DB_ERROR;
	   }
	   else {
   	   while ($row = $db->fetchObject($rows)) {
   	   	$result[] = '<row timestamp="'.$row->log_timestamp.'" action="'.$row->log_action.'" page_id="'.$row->log_params.'"/>';
   	   }
   	   $db->freeResult($rows);
	   }
   }

	// return status
	return "<result status=\"$status\">".join("\n",$result).'</result>';
}

/**
 * Get Id's of pages with page_id > specified page_id; skip redirects
 *
 * @param unknown_type $args page_id=page id|max=maximum to return (defaults to IAF_MAX_COUNT_IDS)
 * @return IAF_SUCCESS, IAF_INVALID_ARG
 */
function wfGetAllPageIds($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = IAF_SUCCESS;
   $result = array();
   $args = iafGetArgs($args);
   $pageid = iafGetArg($args, 'page_id', null);
   $max = iafGetArg($args, 'max', IAF_MAX_COUNT_IDS);
   $namespaces = iafGetArg($args, "namespaces", null); // multiple namespaces use commas; e.g., 100,102,106
   if (is_null($pageid) || $max > IAF_MAX_COUNT_IDS) {
      $status = IAF_INVALID_ARG;
   }
   else {
	   $db =& wfGetDB( DB_SLAVE );
	   $db->ignoreErrors(true);
      $conds = array();
      $conds[] = 'page_id > '.$db->addQuotes($pageid);
      $conds[] = 'page_is_redirect = 0';
      if ($namespaces) {
         $conds[] = 'page_namespace in ('.preg_replace('/[^0-9,]/', '', $namespaces).')';
      }
   	$rows = $db->select('page', array('page_id'), $conds,
   								'wfGetAllPageIds', array('LIMIT' => intval($max), 'ORDER BY' => 'page_id'));
	   $errno = $db->lastErrno();
   	if ($errno != 0) {
	     $status = IAF_DB_ERROR;
	   }
	   else {
   	   while ($row = $db->fetchObject($rows)) {
   	   	$result[] = '<row page_id="'.$row->page_id.'"/>';
   	   }
   	   $db->freeResult($rows);
	   }
   }

	// return status
	return "<result status=\"$status\">".join("\n",$result).'</result>';
}

/**
 * Get index contents for specified page_id's
 *
 * @param unknown_type $args page_id=id,id,id,... (up to IAF_MAX_COUNT_CONTENTS)|index=1 to return watchers (defaults to 0)
 * @return IAF_SUCCESS, IAF_INVALID_ARG
 */
function wfGetPageIndexContents($args) {
	global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = IAF_SUCCESS;
   $result = array();
   $args = iafGetArgs($args);
   $pageidString = iafGetArg($args, 'page_id', null);
   $index = iafGetArg($args, 'index', false);
  	$pageids = explode(',', $pageidString);
   if (!$pageidString || count($pageids) > IAF_MAX_COUNT_CONTENTS) {
      $status = IAF_INVALID_ARG;
   }
   else {
	   $db =& wfGetDB( DB_SLAVE );
	   $db->ignoreErrors(true);
		foreach ($pageids as $pageid) {
			$ns = 0;
			$fullTitle = '';
			$timestamp = '';
			$revid = '';
			$text = '';
			$popularity = '';
			$users = array();
			$trees = array();

			$revision = Revision::loadFromPageId($db, $pageid);
		   $errno = $db->lastErrno();
	   	if ($errno != 0) {
		     $status = IAF_DB_ERROR;
		     break;
		   }
			if ($revision) {
				$ns = $revision->getTitle()->getNamespace();
				$fullTitle = $revision->getTitle()->getPrefixedText();
				$timestamp = $revision->getTimestamp();
				$revid = $revision->getId();
				$text =& $revision->getText();
			   $errno = $db->lastErrno();
		   	if ($errno != 0) {
			     $status = IAF_DB_ERROR;
			     break;
			   }
			   
			   if ($index) {
			   	$watcherCount = 0;
			   	
			   	$dbkey = $revision->getTitle()->getDBkey();
					$rows = $db->select(array('watchlist','user'), array('user_name'), array('wl_user=user_id', 'wl_namespace' => $ns, 'wl_title' => $dbkey));
				   $errno = $db->lastErrno();
			   	if ($errno != 0) {
				     $status = IAF_DB_ERROR;
				     break;
				   }
					while ($row = $db->fetchObject($rows)) {
					   $users[] = '<user>'.StructuredData::escapeXml($row->user_name).'</user>';
					   $watcherCount++;
					}
					$db->freeResult($rows);

					$rows = $db->select(array('familytree_page','familytree'), array('ft_user','ft_name'), array('fp_tree_id=ft_tree_id', 'fp_namespace' => $ns, 'fp_title' => $dbkey));
				   $errno = $db->lastErrno();
			   	if ($errno != 0) {
				     $status = IAF_DB_ERROR;
				     break;
				   }
					while ($row = $db->fetchObject($rows)) {
						$temp = $row->ft_user.'/'.$row->ft_name;
					   $trees[] = '<tree>'.StructuredData::escapeXml($temp).'</tree>';
					}
					$db->freeResult($rows);
					
					$wlhCount = 0;
					$sns = Namespac::getSubject($ns);
					if ($sns == NS_MAIN || $sns == NS_IMAGE || $sns == NS_CATEGORY || $sns == NS_PROJECT || $sns == NS_USER || $sns == NS_HELP ||
					    $sns == NS_SOURCE || $sns == NS_REPOSITORY || $sns == NS_PORTAL || $sns == NS_PLACE || $sns == NS_TRANSCRIPT) {
						if (($ns == NS_SOURCE || $ns == NS_REPOSITORY) && ($dbkey == 'Family_History_Library' || $dbkey == 'Family_History_Center')) {
							$wlhCount = 100000;
						}
						else if ($ns == NS_IMAGE) {
							$wlhCount = $db->selectField('imagelinks', 'count(*)', array('il_to' => $dbkey));
						}
						else {
							$wlhCount = $db->selectField('pagelinks', 'count(*)', array('pl_namespace' => $ns, 'pl_title' => $dbkey));
						}
						if ($ns == NS_PLACE || $ns == NS_USER || $ns == NS_CATEGORY) { // discount links to place/user pages
							$wlhCount = $wlhCount / 128;
						}
					}
					
					$popularity = ($watcherCount + $wlhCount > 0 ? max(20, min(384, floor(128 * log10($watcherCount * 4 + $wlhCount)))) : 0);
			   }
			}

			$result[] = '<page page_id="'.$pageid.'" namespace="'.$ns.'" title="'.StructuredData::escapeXml($fullTitle).
								'" rev_id="'.$revid.'" rev_timestamp="'.$timestamp.'" popularity="'.$popularity.'">'
   	   			  .'<contents>'.StructuredData::escapeXml($text).'</contents>'
   	   			  .join("\n",$users)
   	   			  .join("\n",$trees)
   	   			  .'</page>';
		}
   }

	// return status
	return "<result status=\"$status\">".join("\n",$result).'</result>';
}

/**
 * Return whether the specified page titles have been redirected
 *
 * @param unknown_type $args title|title|title,... (up to IAF_MAX_COUNT_CONTENTS)
 * @return IAF_SUCCESS, IAF_INVALID_ARG
 */
function wfGetPageRedirects($args) {
   global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);

	// validate input arguments
   $status = IAF_SUCCESS;
   $result = array();
  	$pageTitles = explode('|', $args);
   if (!$args || count($pageTitles) > IAF_MAX_COUNT_CONTENTS) {
      $status = IAF_INVALID_ARG;
   }
   else {
	   $db =& wfGetDB( DB_SLAVE );
	   $db->ignoreErrors(true);
      $result = array();
		foreach ($pageTitles as $pageTitle) {
         $ot = Title::newFromText($pageTitle);
         if ($ot) {
            $nt = StructuredData::getRedirectToTitle($ot, false, $db);
            $errno = $db->lastErrno();
            if ($errno != 0) {
              $status = IAF_DB_ERROR;
              break;
            }
            if ($nt && $ot->getPrefixedDBkey() != $nt->getPrefixedDBkey()) {
               $result[] = '<page source="'.StructuredData::escapeXml($ot->getPrefixedText()).
                           '" target="'.StructuredData::escapeXml($nt->getPrefixedText()).'"/>';
            }
         }
      }
   }

	// return status
	return "<result status=\"$status\">".join("\n",$result).'</result>';
}

function iafGetArgs($args) {
//	global $wgContLang;
	$result = array();
	$args = explode('|', $args); // doesn't appear necessary: $wgContLang->recodeInput(js_unescape($args)));
	foreach ($args as $arg) {
	   $pieces = explode('=', $arg);
	   if (count($pieces) == 2) {
         $result[$pieces[0]] = $pieces[1];
	   }
	}
	return $result;
}

function iafGetArg($args, $name, $default) {
	if (isset($args[$name])) {
		return $args[$name];
	}
	else {
		return $default;
	}
}
?>
