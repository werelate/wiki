<?php

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');

# Register with AjaxDispatcher as a function
$wgAjaxExportList[] = "wfStoreSource";
$wgAjaxExportList[] = "wfRetrieveSource";
$wgAjaxExportList[] = "wfAddVerifiedTemplate";
$wgAjaxExportList[] = "wfAddDeferredTemplate";

/* wfStoreSource and wfRetrieveSource added Sep 2020 by Janet Bjorndahl,
 * to persist and then retrieve source citation data across multiple windows/tabs
 */

// Source data is received and stored as JSON, so that any new/changed fields can be handled in the Javascript
function wfStoreSource() {
  global $wgRequest;

  $_SESSION['copiedSource'] = trim($wgRequest->getVal('source'),'"');
}

function wfRetrieveSource() {
  global $wgRequest;

  if ( isset($_SESSION['copiedSource']) ) {
    $json = $_SESSION['copiedSource'];
    $callback = $wgRequest->getVal('callback');
    if ($callback) {
      $json = $callback.'('.$json.');';
    }
  }
  else {
    $json = '';
  }    
  return $json;
}

// Add a template to a talk page to indicate that an anomaly has been verified, and update the DQ table accordingly.
function wfAddVerifiedTemplate() {
  global $wgRequest, $wgUser;
  
  $namespace = $wgRequest->getVal('ns');
  $titleString = $wgRequest->getVal('title');
  $templateName = $wgRequest->getVal('template');
  $pageId = $wgRequest->getVal('pid'); 
  $callback = $wgRequest->getVal('callback');
  $desc = urldecode($wgRequest->getVal('desc'));
  $addWatches = $wgUser->getOption( 'watchdefault' );

  $pageTitle = Title::newFromText($titleString, $namespace);
	$article = new Article($pageTitle->getTalkPage(), 0);
  $success = false;
	if ( $wgUser->isLoggedIn() && $article ) {
	  $targetTalkContents = $article->fetchContent();
	  if ($targetTalkContents) {
			$targetTalkContents = rtrim($targetTalkContents) . "\n\n";
		}
		$success = $article->doEdit($targetTalkContents . '{{' . $templateName . '|' . date("j M Y") . '|[[User:' . $wgUser->getName() . '|' . $wgUser->getName() . ']]}}', "Add $templateName template");
    if ($success) {
		  if ($addWatches) {
   		  StructuredData::addWatch($wgUser, $article, true);
		  }
        
      // Update the DQ table to indicate that this issue has been verified 
   		$dbw =& wfGetDB( DB_MASTER );
      $dbw->safeQuery('UPDATE dq_issue SET dqi_verified_by = IF(dqi_verified_by IS NULL, ?, CONCAT(dqi_verified_by, ", ", ?)) ' . 
                      'WHERE dqi_page_id=' . $pageId . ' AND dqi_job_id = (SELECT MAX(dq_job_id) FROM dq_page) AND dqi_issue_desc = ?',
                      $wgUser->getName(), $wgUser->getName(), $desc);   
    
      if ($dbw->lastErrno() == 0) {
         $dbw->commit();
      }
      else {
         $dbw->rollback();
      }
    }
	}
  if ($callback) {
    return $callback.'(' . $success . ');';
  }
}

// Add a template to a talk page to indicate that the user doesn't want to see the Person/Family page on the DQ report for now, and update the DQ table accordingly.
function wfAddDeferredTemplate() {
  global $wgRequest, $wgUser;
  
  $namespace = $wgRequest->getVal('ns');
  $titleString = $wgRequest->getVal('title');
  $templateName = "DeferredIssues";
  $pageId = $wgRequest->getVal('pid'); 
  $callback = $wgRequest->getVal('callback');
  $comments = urldecode($wgRequest->getVal('comments'));
  $addWatches = $wgUser->getOption( 'watchdefault' );

  $pageTitle = Title::newFromText($titleString, $namespace);
	$article = new Article($pageTitle->getTalkPage(), 0);
  $success = false;
	if ( $wgUser->isLoggedIn() && $article ) {
	  $targetTalkContents = $article->fetchContent();
	  if ($targetTalkContents) {
			$targetTalkContents = rtrim($targetTalkContents) . "\n\n";
		}
		$success = $article->doEdit($targetTalkContents . '{{' . $templateName . '|' . date("j M Y") . '|[[User:' . $wgUser->getName() . '|' . $wgUser->getName() . ']]|' . 
                $comments . '}}', "Add $templateName template");
    if ($success) {
		  if ($addWatches) {
   		  StructuredData::addWatch($wgUser, $article, true);
		  }
        
      // Update the DQ table to indicate that the user wants to hide this page for now 
   		$dbw =& wfGetDB( DB_MASTER );
      $dbw->safeQuery('UPDATE dq_page SET dq_viewed_by = IF(dq_viewed_by IS NULL, CONCAT("|", ?, "|"), CONCAT(dq_viewed_by, ?, "|")) ' . 
                      'WHERE dq_page_id=' . $pageId . ' AND dq_job_id = (SELECT MAX(dqs_job_id) FROM dq_stats)',
                      $wgUser->getName(), $wgUser->getName());   
    
      if ($dbw->lastErrno() == 0) {
         $dbw->commit();
      }
      else {
         $dbw->rollback();
      }
    }
	}
  if ($callback) {
    return $callback.'(' . $success . ');';
  }
}


?>