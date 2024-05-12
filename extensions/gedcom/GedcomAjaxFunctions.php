<?php

/*
* index.php?action=ajax&rs=functionName&rsargs=a=v|b=v|c=v
*/

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');
require_once("$IP/extensions/AjaxUtil.php");
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");
require_once("$IP/extensions/gedcom/GedcomUtil.php");

# Register with AjaxDispatcher as a function
$wgAjaxExportList[] = "wfReadGedcom";
$wgAjaxExportList[] = "wfReadGedcomData";
$wgAjaxExportList[] = "wfReadGedcomPageData";
$wgAjaxExportList[] = "wfReserveIndexNumbers";
$wgAjaxExportList[] = "wfAddPagesToTree";
$wgAjaxExportList[] = "wfGenerateFamilyTreePage";
$wgAjaxExportList[] = "wfSetGedcomPage";
$wgAjaxExportList[] = "wfIsTrustedUploader";
$wgAjaxExportList[] = "wfUpdateGedcomPrimary";
$wgAjaxExportList[] = "wfUpdateTreePrimary";
$wgAjaxExportList[] = "wfUpdateGedcomFlag";
$wgAjaxExportList[] = "wfUpdateGedcomMatches";
$wgAjaxExportList[] = "wfUpdateGedcomPotentialMatches";
$wgAjaxExportList[] = "wfUpdateGedcomStatus";
$wgAjaxExportList[] = "wfMatchFamily";
$wgAjaxExportList[] = "wfAddPage"; // used by Special:AddPage, not gedcom
$wgAjaxExportList[] = "wfAddGedcomSourceMatches";
$wgAjaxExportList[] = "wfMatchSource";


define( 'GE_SUCCESS', 0);
define( 'GE_INVALID_ARG', -1);
define( 'GE_NOT_LOGGED_IN', -2);
define( 'GE_NOT_AUTHORIZED', -3);
define( 'GE_DB_ERROR', -4);
define( 'GE_DUP_KEY', -5);
define( 'GE_NOT_FOUND', -6); 
define( 'GE_WIKI_ERROR', -7);
define( 'GE_ON_HOLD', -8);

function wfReadGedcom($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrGedcomInprocessDirectory;
	
   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$gedcomId = (int)$args['gedcom_id'];
	$filename = $wrGedcomInprocessDirectory . '/' . $gedcomId . '.xml';

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
   else if (!$gedcomId) {
      $status = GE_INVALID_ARG;
	}
	else if (!fgIsAuthorized($gedcomId, false, $status)) {
		//
	}
	else if (!file_exists($filename)) {
		$status = GE_NOT_FOUND;
	}
	else {
		$dbr =& wfGetDB( DB_SLAVE );
		$dbr->ignoreErrors(true);
		$gedcomStatus = $dbr->selectField(array('familytree', 'familytree_gedcom'), array('fg_status'), 
													array('ft_tree_id = fg_tree_id', 'fg_id' => $gedcomId));
		if ($gedcomStatus != FG_STATUS_PHASE2 &&
			 $gedcomStatus != FG_STATUS_PHASE3 &&
			 $gedcomStatus != FG_STATUS_IMPORTING &&
			 $gedcomStatus != FG_STATUS_ADMIN_REVIEW &&
          $gedcomStatus != FG_STATUS_HOLD) {
			$status = GE_NOT_FOUND;
		}
	}
	if ($status == GE_SUCCESS) {
		// read the file from disk
		$handle = fopen($filename, 'rb');
		$contents = fread($handle, filesize($filename));
		fclose($handle);		
		// return the file
		return $contents;
	}
  	return "<readGedcom status=\"$status\"/>";
}

function wfSetGedcomPage($args) {
	global $wgUser, $wgAjaxCachePolicy;
	
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
	
	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (!$args) {
	   $status = GE_INVALID_ARG;
	}
	else {
		// cache data for SpecialGedcomPage
		GedcomUtil::putGedcomDataString($args);
	}
	return "<setgedcompage status=\"$status\"/>";
}

function wfIsTrustedUploader($args) {
   global $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$userName = $args['user_name'];
   $isTrusted = false;

   if (!$userName) {
      $status = GE_INVALID_ARG;
   }
   else {
      $isTrusted = in_array($userName, explode('|', wfMsg('trustedgedcomuploaders')));
   }
   $trustedFlag = ($isTrusted ? 'true' : 'false');

  	return "<trustedUploader trusted=\"$trustedFlag\" status=\"$status\"/>";
}

function wfReadGedcomData($args) {
	global $wgUser, $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$gedcomId = (int)$args['gedcom_id'];
	$result = '';
	$userName = '';
	$isOwner = '';
	$isAdmin = false;
	$gedcomStatus = '';
	$reviewer = '';
	$primary = '';

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (!$gedcomId) {
	   $status = GE_INVALID_ARG;
	}
	else if (!fgIsAuthorized($gedcomId, false, $status)) {
		//
	}
	else {
		$dbr =& wfGetDB( DB_SLAVE );
		$dbr->ignoreErrors(true);
		$fg = $dbr->selectRow(array('familytree', 'familytree_gedcom'), array('ft_user', 'fg_status', 'fg_reviewer', 'fg_primary'),
					array('ft_tree_id = fg_tree_id', 'fg_id' => $gedcomId));
		if (!$fg) {
			$status = GE_NOT_FOUND;
		}
	}
	if ($status == GE_SUCCESS) {
		$isOwner = ($fg->ft_user == $wgUser->getName() ? 'true' : 'false');
		$isAdmin = $wgUser->isAllowed('patrol');
		$gedcomStatus = $fg->fg_status;
		$userName = StructuredData::escapeXml($fg->ft_user);
		$reviewer = ($fg->fg_reviewer == $wgUser->getName() ? '' : StructuredData::escapeXml($fg->fg_reviewer));
		$primary = StructuredData::escapeXml($fg->fg_primary);
		$rows = $dbr->select('familytree_gedcom_data',
									array('fgd_gedcom_key', 'fgd_exclude', 'fgd_living', 'fgd_merged', 'fgd_match_namespace', 'fgd_match_title', 'fgd_potential_matches', 'fgd_text'),
									array('fgd_gedcom_id' => $gedcomId));
		if ($dbr->lastErrno() == 0) {
			$result = array();
		   while ($row = $dbr->fetchObject($rows)) {
		   	if ($row->fgd_match_namespace > 0) {
			   	$title = Title::makeTitle($row->fgd_match_namespace, $row->fgd_match_title);
			   	$titleString = $title->getPrefixedText();
		   	}
		   	else if ($row->fgd_match_namespace < 0) {
		   		$titleString = '#nomatch#';
		   	}
		   	else {
		   		$titleString = '';
		   	}
		   	$result[] = '<result key="'.StructuredData::escapeXml($row->fgd_gedcom_key).
		   		($row->fgd_exclude ? '" exclude="'.StructuredData::escapeXml($row->fgd_exclude) : '').
		   		($row->fgd_living ? '" living="'.StructuredData::escapeXml($row->fgd_living) : '').
		   		($row->fgd_merged ? '" merged="'.StructuredData::escapeXml($row->fgd_merged) : '').
		   		($titleString ? '" match="'.StructuredData::escapeXml($titleString) : '').
		   		($row->fgd_potential_matches ? '" matches="'.StructuredData::escapeXml($row->fgd_potential_matches) : '').
		   		'">'.StructuredData::escapeXml($row->fgd_text).'</result>';
		   }
		   $dbr->freeResult($rows);
		   $result = join("\n", $result);
		}
		else {
			$status = GE_DB_ERROR;
		}
		if ($status == GE_SUCCESS && $gedcomStatus == FG_STATUS_ADMIN_REVIEW && $isAdmin && $fg->fg_reviewer == '') {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->begin();
	      $dbw->update('familytree_gedcom', array('fg_reviewer' => $wgUser->getName()), array('fg_id' => $gedcomId));
		   if ($dbw->lastErrno()) {
		   	$status = GE_DB_ERROR;
		   	$dbw->rollback();
		   }
		   else {
		   	$dbw->commit();
		   }
		}
	}
	
   $adminFlag = ($isAdmin ? 'true' : 'false');

  	return "<readGedcomData username=\"$userName\" primary=\"$primary\" owner=\"$isOwner\" admin=\"$adminFlag\" gedcomStatus=\"$gedcomStatus\" reviewer=\"$reviewer\" status=\"$status\">$result</readGedcomData>";
}

function wfReadGedcomPageData($args) {
	global $wgUser, $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$gedcomId = (int)$args['gedcom_id'];
	$gedcomKey = $args['key'];

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (!$gedcomId || !$gedcomKey) {
	   $status = GE_INVALID_ARG;
	}
	else if (!fgIsAuthorized($gedcomId, false, $status)) {
		//
	}
	else {
		$dbr =& wfGetDB( DB_SLAVE );
		$dbr->ignoreErrors(true);
		$text = $dbr->selectField('familytree_gedcom_data', 'fgd_text', 
										array('fgd_gedcom_id' => $gedcomId, 'fgd_gedcom_key' => $gedcomKey));
		if ($dbr->lastErrno() != 0) {
			$status = GE_DB_ERROR;
		}
	}
	$gedcomKey = StructuredData::escapeXml($gedcomKey);
  	return "<readGedcomPageData status=\"$status\" key=\"$gedcomKey\">".StructuredData::escapeXml($text)."</readGedcomPageData>";
}

function wfUpdateGedcomPrimary($args) {
	global $wgUser, $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$gedcomId = (int)$args['gedcom_id'];
	$primary = $args['primary'];
	$result = '';

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (!$gedcomId) {
	   $status = GE_INVALID_ARG;
	}
	else if (!fgIsAuthorized($gedcomId, true, $status)) {
		//
	}
	else {
		$dbw =& wfGetDB( DB_MASTER );
		$dbw->ignoreErrors(true);
		$dbw->begin();
      $dbw->update('familytree_gedcom', array('fg_primary' => $primary), array('fg_id' => $gedcomId));
	   if ($dbw->lastErrno()) {
	   	$status = GE_DB_ERROR;
	   	$dbw->rollback();
	   }
	   else {
	   	$dbw->commit();
	   }
   }
	
  	return "<updateGedcomPrimary status=\"$status\"/>";
}

function wfUpdateGedcomFlag($args) {
	global $wgUser, $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$gedcomId = (int)$args['gedcom_id'];
	$attr = $args['attr'];
	$value = $args['value'];
	
	if ($attr == 'exclude') {
		$attr = 'fgd_exclude';
	}
	else if ($attr == 'living') {
		$attr = 'fgd_living';
	}
	else if ($attr == 'merged') {
		$attr = 'fgd_merged';
	}
	else {
		$attr = '';
	}
	$keys = explode(strpos($args['key'], '/') !== false ? '/' : "\n", $args['key']);
	$result = '';

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (!$gedcomId || !$attr || !$value || !$args['key']) {
	   $status = GE_INVALID_ARG;
	}
	else if (!fgIsAuthorized($gedcomId, true, $status)) {
		//
	}
	else {
		$dbw =& wfGetDB( DB_MASTER );
		$dbw->ignoreErrors(true);
		$dbw->begin();
		$value = $dbw->addQuotes($value);
		$gedcomId = $dbw->addQuotes($gedcomId);
		foreach ($keys as $key) {
			$sql = 'INSERT INTO familytree_gedcom_data (fgd_gedcom_id, fgd_gedcom_key, '.$attr.')'.
						' VALUES('.$gedcomId.','.$dbw->addQuotes($key).','.$value.')'.
						' ON DUPLICATE KEY UPDATE '.$attr.'='.$value;
			$dbw->query($sql);
			if ($dbw->lastErrno()) {
				$status = GE_DB_ERROR;
				break;
			}
		}
	   if ($status != GE_SUCCESS) {
	   	$dbw->rollback();
	   }
	   else {
	   	$dbw->commit();
	   }
   }
	
  	return "<updateGedcomData status=\"$status\"/>";
}

function wfUpdateGedcomMatches($args) {
	global $wgUser, $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$gedcomId = (int)$args['gedcom_id'];
	$merged = explode(strpos($args['merged'], '/') !== false ? '/' : "\n", $args['merged']);
	$keys = explode(strpos($args['key'], '/') !== false ? '/' : "\n", $args['key']);
	$matches = explode(strpos($args['match'], '/') !== false ? '/' : "\n", $args['match']);
	$result = '';

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (!$gedcomId || !$args['key'] || !$args['match']) {
	   $status = GE_INVALID_ARG;
	}
	else if (!fgIsAuthorized($gedcomId, true, $status)) {
		//
	}
	else {
		$status = fgUpdateMatches($gedcomId, $keys, $matches, $merged);
   }
   
	// get related matches
	if ($status == GE_SUCCESS) {
		$result = fgGetRelatedMatches($keys, $matches);
	}
	
  	return "<updateGedcomMatches status=\"$status\">$result</updateGedcomMatches>";
}

function wfUpdateGedcomPotentialMatches($args) {
	global $wgUser, $wgAjaxCachePolicy;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$gedcomId = (int)$args['gedcom_id'];
	$keys = explode(strpos($args['key'], '/') !== false ? '/' : "\n", $args['key']);
	$matches = explode(strpos($args['matches'], '/') !== false ? '/' : "\n", $args['matches']);
	$result = '';

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (!$gedcomId || !$args['key'] || count($keys) != count($matches)) {
	   $status = GE_INVALID_ARG;
	}
	else if (!fgIsAuthorized($gedcomId, true, $status)) {
		//
	}
	else {
		$status = fgUpdatePotentialMatches($gedcomId, $keys, $matches);
   }
	
  	return "<updateGedcomPotentialMatches status=\"$status\"/>";
}

function wfMatchFamily($args) {
	global $wgUser, $wgAjaxCachePolicy;
	
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
	$result = '';
	$gedcomId = GedcomUtil::getGedcomId($args);
	
	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
   else if ($wgUser->isBlocked() || wfReadOnly()) {
      $status = GE_NOT_AUTHORIZED;
   }
	else if (!$args || !$gedcomId ||
				!preg_match('/^<gedcom [^>]*id="([^"]+)"/', $args, $idMatches) || 
				!preg_match('/^<gedcom [^>]*match="([^"]+)"/', $args, $titleMatches)) {
	   $status = GE_INVALID_ARG;
	}
	else {
		// calc family match scores
		$id = $idMatches[1];
		$matchTitle = Title::newFromText($titleMatches[1], NS_FAMILY);
		$matchTitleString = $matchTitle->getText();
		$gedcomData = GedcomUtil::getGedcomDataMap($args);
		$gedcomTitleString = $gedcomData[$id]['title'];
		if (strpos($gedcomTitleString, 'Person:') === 0 || strpos($gedcomTitleString, 'Family:') === 0) {
			$gedcomTitleString = substr($gedcomTitleString, 7);
		}
		$compare = new CompareForm('Family', array($gedcomTitleString, $matchTitleString), $gedcomData, $args);
		list($compareData, $compareChildren, $maxChildren) = $compare->readCompareData();
		$dataLabels = $compare->getDataLabels();
		list($compareClass, $husbandScores, $wifeScores, $totalScores) = $compare->scoreCompareData($dataLabels, $compareData);

		// matched if not multiple husbands/wives, total score > threshold, husband + wife scores > threshold, and all gedcom children matched
		$gedcomHusbandCount = count($compareData[$gedcomTitleString]['husbandTitle']);
		$gedcomWifeCount = count($compareData[$gedcomTitleString]['wifeTitle']);
		$matchHusbandCount = count($compareData[$matchTitleString]['husbandTitle']);
		$matchWifeCount = count($compareData[$matchTitleString]['wifeTitle']);
		$matched = $gedcomHusbandCount <= 1 && $gedcomWifeCount <= 1 && $matchHusbandCount <= 1 && $matchWifeCount <= 1 &&
						$totalScores[1] >= 4 &&
						($gedcomHusbandCount == 0 || $matchHusbandCount == 0 || $husbandScores[1] > 0) &&
						($gedcomWifeCount == 0 || $matchWifeCount == 0 || $wifeScores[1] > 0);
//wfDebug("MATCHCOMPARE gedcomTitle=$gedcomTitleString matchTitle=$matchTitleString ghc=$gedcomHusbandCount gwc=$gedcomWifeCount mhc=$matchHusbandCount mwc=$matchWifeCount".
//			" totalScore={$totalScores[1]} husbandScore={$husbandScores[1]} wifeScore={$wifeScores[1]} matched=$matched\n");
		if ($matched) {
			$unmatchedGedcomChild = false;
			$unmatchedWikiChild = false;
			for ($i = 0; $i < $maxChildren; $i++) {
				if (count(@$compareChildren[$gedcomTitleString][$i]['childTitle']) == 1 &&
					 count(@$compareChildren[$matchTitleString][$i]['childTitle']) != 1) {
				 	$unmatchedGedcomChild = true;
				}
				else if (count(@$compareChildren[$gedcomTitleString][$i]['childTitle']) != 1 &&
							count(@$compareChildren[$matchTitleString][$i]['childTitle']) == 1) {
				 	$unmatchedWikiChild = true;
				}
			}
			if ($unmatchedGedcomChild && $unmatchedWikiChild) { // could be a mismatched child
				$matched = false;
//wfDebug("MATCHCOMPARE failed on children\n");
			}
		}
		
		if ($matched) {
			// save matches and return related matches
			$keys = array();
			$keys[] = $id;
			$matches = array();
			$matches[] = $matchTitle->getPrefixedText();
			$merged = array();
			$merged[] = 'false';
			if ($gedcomHusbandCount == 1 && $matchHusbandCount == 1) {
				$key = GedcomUtil::getKeyFromTitle($compareData[$gedcomTitleString]['husbandTitle'][0]);
				if ($key) { // not already merged
					$keys[] = $key;
					$matches[] = 'Person:'.$compareData[$matchTitleString]['husbandTitle'][0];
					$merged[] = 'false';
				}
			}
			if ($gedcomWifeCount == 1 && $matchWifeCount == 1) {
				$key = GedcomUtil::getKeyFromTitle($compareData[$gedcomTitleString]['wifeTitle'][0]);
				if ($key) {
					$keys[] = $key;
					$matches[] = 'Person:'.$compareData[$matchTitleString]['wifeTitle'][0];
					$merged[] = 'false';
				}
			}
			for ($i = 0; $i < $maxChildren; $i++) {
				if (count(@$compareChildren[$gedcomTitleString][$i]['childTitle']) == 1 &&
					 count(@$compareChildren[$matchTitleString][$i]['childTitle']) == 1) {
					$key = GedcomUtil::getKeyFromTitle($compareChildren[$gedcomTitleString][$i]['childTitle'][0]);
					if ($key) {
						$keys[] = $key;
						$matches[] = 'Person:'.$compareChildren[$matchTitleString][$i]['childTitle'][0];
						$merged[] = 'false';
					}
				}
			}
			
			$status = fgUpdateMatches($gedcomId, $keys, $matches, $merged);
			if ($status == GE_SUCCESS) {
				$result = fgGetRelatedMatches($keys, $matches);
			}
		}
		else { 
			// save as potential match and return
			$keys = array($id);
			$matches = array($matchTitleString);
			$status = fgUpdatePotentialMatches($gedcomId, $keys, $matches);
			if ($status == GE_SUCCESS) {
				$result = '<match id="'.StructuredData::escapeXml($id).'" matches="'.StructuredData::escapeXml($matchTitleString).'"/>';
			}
		}
	}
	return "<matchfamily status=\"$status\">$result</matchfamily>";
}

function wfUpdateGedcomStatus($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrGedcomInprocessDirectory, $wrHostName;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$gedcomId = (int)$args['gedcom_id'];
	$targetStatus = (int)$args['gedcomStatus']; // 0 is a special status: means delete
	$statusReason = @$args['warning'];
	$reviewer = '';
	$filename = $wrGedcomInprocessDirectory . '/' . $gedcomId . '.xml';
	$isAdmin = $wgUser->isAllowed('patrol');
	$gedcomStatus = 0;

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (!$gedcomId) {
	   $status = GE_INVALID_ARG;
	}
   else if (wfReadOnly() || $wgUser->isBlocked()) {
      $status = GE_NOT_AUTHORIZED;
   }
	else if (!fgIsAuthorized($gedcomId, false, $status)) {
		//
	}
	else if (!($targetStatus == FG_STATUS_PHASE3 ||
              $targetStatus == FG_STATUS_DELETE ||
				 ($isAdmin && ($targetStatus == FG_STATUS_PHASE2 || $targetStatus == FG_STATUS_HOLD)))) {
		$status = GE_INVALID_ARG;
	}
	else if (!file_exists($filename)) {
		$status = GE_NOT_FOUND;
	}
	else {
		$dbw =& wfGetDB( DB_MASTER );
		$dbw->ignoreErrors(true);
		$dbw->begin();
		$row = $dbw->selectRow( 'familytree_gedcom', array('fg_status', 'fg_tree_id', 'fg_reviewer'), array('fg_id' => $gedcomId));
		if (!$row || $dbw->lastErrno()) {
			$status = GE_DB_ERROR;
		}
	}
	if ($status == GE_SUCCESS) {
		$gedcomStatus = $row->fg_status;
		$treeId = $row->fg_tree_id;
		$reviewer = $row->fg_reviewer;
		
		if ($targetStatus == FG_STATUS_PHASE2 &&
          ($gedcomStatus == FG_STATUS_ADMIN_REVIEW || $gedcomStatus == FG_STATUS_HOLD) &&
          $isAdmin) { // return to user review
			$gedcomStatus = $targetStatus;
		}
      else if ($targetStatus == FG_STATUS_HOLD && $gedcomStatus == FG_STATUS_PHASE2 && $isAdmin) { // put on hold
         $gedcomStatus = $targetStatus;
      }
	   else if ($targetStatus == FG_STATUS_PHASE3 && $gedcomStatus == FG_STATUS_PHASE2) { // ready to import
	   	if ($statusReason) $statusReason .= '; ';
			$statusReason .= fgReviewNeeded($gedcomId, $filename, $dbw);
	   	if (!$isAdmin && $wrHostName != 'sandbox.werelate.org') {
	   		$gedcomStatus = FG_STATUS_ADMIN_REVIEW;
	   	}
	   	else {
	   		$statusReason = '';
	   		$gedcomStatus = FG_STATUS_PHASE3;
	   	}
	   }
	   else if ($targetStatus == FG_STATUS_PHASE3 && $gedcomStatus == FG_STATUS_ADMIN_REVIEW && $isAdmin) { // ready to import
	   	$gedcomStatus = FG_STATUS_PHASE3;
	   	$reviewer = $wgUser->getName();
	   }
	   else if ($targetStatus == FG_STATUS_DELETE && ($gedcomStatus == FG_STATUS_PHASE2 || $gedcomStatus == FG_STATUS_HOLD || ($gedcomStatus == FG_STATUS_ADMIN_REVIEW && $isAdmin))) { // delete
	   	$gedcomStatus = FG_STATUS_DELETE;
	   }
	   else {
	   	$status = GE_INVALID_ARG;
	   }
	   
	   if ($status == GE_SUCCESS) {
	   	if ($gedcomStatus == FG_STATUS_DELETE) {
	   		//$dbw->delete('familytree_gedcom_data', array('fgd_gedcom_id' => $gedcomId));
	   		$dbw->delete('familytree_gedcom', array('fg_id' => $gedcomId));
	   		// don't delete the tree because other in-process gedcom's may be linked to it
//	   		if (!$dbw->selectField('familytree_page', 'fp_tree_id', array('fp_tree_id' => $treeId))) {
//		   		$dbw->delete('familytree', array('ft_tree_id' => $treeId));
//	   		}
	   	}
	   	else {
		   	// update status, reviewer
		      $dbw->update('familytree_gedcom', array('fg_status' => $gedcomStatus, 'fg_status_reason' => $statusReason, 'fg_reviewer' => $reviewer), 
	   	   				array('fg_id' => $gedcomId));
	   	}
	   	
			if ($dbw->lastErrno()) {
				$status = GE_DB_ERROR;
			}
	   }
	   
	   if ($status != GE_SUCCESS) {
	   	$dbw->rollback();
	   }
	   else {
	   	$dbw->commit();
	   }
	}
	
	$statusReason = StructuredData::escapeXml($statusReason);
  	return "<readytoimport gedcomStatus=\"$gedcomStatus\" statusReason=\"$statusReason\" status=\"$status\"/>";
}

/**
 * Reserve index numbers
 *
 * @param unknown_type $args XML: <reserve><page namespace="" title=""/>...</reserve> => <reserve><page namespace="" title="" titleix=""/>...</reserve>
 * @return GE_SUCCESS, GE_INVALID_ARG, GE_NOT_LOGGED_IN, GE_NOT_AUTHORIZED, GE_DB_ERROR
 */
function wfReserveIndexNumbers($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrBotUserID;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
  	$result = '';

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (wfReadOnly() || $wgUser->getID() != $wrBotUserID) {
	   $status = GE_NOT_AUTHORIZED;
	}
	else {
   	$xml = simplexml_load_string($args);
      $dbw =& wfGetDB( DB_MASTER );
      $dbw->ignoreErrors(true);
      $dbw->begin();
   	foreach ($xml->page as $page) {
   	   $ns = (int)$page['namespace'];
   	   $titleString = (string)$page['title'];
   	   $title = Title::newFromText($titleString, $ns);
   	   if (!$title) {
//wfDebug("wfReserve error $ns $titleString\n");
   	      $status = GE_INVALID_ARG;
   	      $result = '';
   	      break;
   	   }
         $titleId = StructuredData::appendUniqueId($title, $dbw);
         if ($titleId == null) {
//wfDebug("wfReserve iderror $ns $titleString\n");
            $status = GE_DB_ERROR;
            $result = '';
            break;
         }
         $titleString = StructuredData::escapeXml($titleString);
         $result .= "<page namespace=\"$ns\" title=\"$titleString\" titleix=\"" . StructuredData::escapeXml($titleId->getText()) . '"/>';
   	}
   	if ($status == GE_SUCCESS) {
   	   $dbw->commit();
   	}
   	else {
   	   $dbw->rollback();
   	}
	}

	// return status
  	return "<reserve status=\"$status\">" . $result . '</reserve>';
}

function wfUpdateTreePrimary($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrBotUserID;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
   $args = AjaxUtil::getArgs($args);
	$treeId = (int)$args['tree_id'];
	$title = Title::newFromText($args['primary'], NS_PERSON);

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (wfReadOnly() || $wgUser->getID() != $wrBotUserID) {
	   $status = GE_NOT_AUTHORIZED;
	}
	else if (!$title || !$treeId) {
		$status = GE_INVALID_ARG;
	}
	else {
      $dbw =& wfGetDB( DB_MASTER );
      $dbw->ignoreErrors(true);
      $dbw->begin();
      $dbw->update('familytree', array('ft_primary_namespace' => $title->getNamespace(), 'ft_primary_title' => $title->getDBkey()), 
      					array('ft_tree_id' => $treeId));
      $errno = $dbw->lastErrno();
		if ($errno > 0) {
		   $status = GE_DB_ERROR;
		}
   	if ($status == GE_SUCCESS) {
   	   $dbw->commit();
   	}
   	else {
   	   $dbw->rollback();
   	}
	}

	// return status
  	return "<updateTreePrimary status=\"$status\"/>";
}


/**
 * Generate family tree page
 *
 * @param unknown_type $args user, name, ns, title
 * @return GE_SUCCESS, GE_INVALID_ARG, GE_NOT_LOGGED_IN, GE_NOT_AUTHORIZED, GE_NOT_FOUND, GE_DUP_KEY, GE_DB_ERROR
 */
function wfGenerateFamilyTreePage($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrBotUserID, $wrIsGedcomUpload;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
	$ns = '';
   $text = '';
   $oldText = '';
	$titleString = '';
	$editFlags = 0;
  	$wrIsGedcomUpload = true;

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (wfReadOnly() || $wgUser->getID() != $wrBotUserID) {
	   $status = GE_NOT_AUTHORIZED;
	}
	else {
   	$xml = simplexml_load_string($args);
   	$ns = (int)$xml['namespace'];
   	$titleString = (string)$xml['title'];
   	PropagationManager::setWhitelist(); // only pages to propagate to are on the whitelist
   	$existingTitles = (string)$xml['existing_titles'];
   	if ($existingTitles) {
	   	$existingTitles = explode('|', $existingTitles);
	   	foreach ($existingTitles as $existingTitle) {
	   		PropagationManager::addWhitelistPage(Title::newFromText($existingTitle));
	   	}
   	}
   	$treeId = (int)$xml['tree_id'];
   	$uid = (string)$xml['uid'];
//   	wfDebug("wfGenerateFamilyTreePage ns=$ns title=$titleString treeId=$treeId\n");
   	if (!$titleString || !$treeId) {
//wfDebug("wfGenerate parmerr $treeId:$titleString\n");
   	   $status = GE_INVALID_ARG;
   	}
	}
	if ($status == GE_SUCCESS) {
   	$dbr =& wfGetDB(DB_SLAVE);
		$dbr->ignoreErrors(true);
	   $userName = $dbr->selectField('familytree', 'ft_user', array('ft_tree_id' => $treeId));
      $errno = $dbr->lastErrno();
		if ($errno > 0) {
		   $status = GE_DB_ERROR;
		}
		else if ($userName === false) {
		   $status = GE_NOT_FOUND;
		}
		else {
         $wgUser = User::newFromName($userName, false); // switch the global user
         if (!$wgUser) {
            $status = GE_NOT_FOUND;
         }
		}
	}
	if ($status == GE_SUCCESS) {
   	$title = Title::newFromText($titleString, $ns);
      $text = $xml->content;
      if ($title == null || !$treeId) {
//wfDebug("wfGenerate error $treeId $ns $titleString\n");
         $status = GE_INVALID_ARG;
      }
      else {
         $article = new Article($title, 0);
         if (!$article->exists()) {
            $editFlags = EDIT_NEW;
         }
         else {
            $oldText = $article->getContent();
            $editFlags = EDIT_UPDATE;
         }
//         else if ($ns == NS_MYSOURCE) {
//            $existingMysource = true;
//            $revid = $title->getLatestRevID(GAID_FOR_UPDATE);
//         }
//         // TODO during re-upload, we need to notify users of changes if others are watching; should we not suppress RC in this case?
//         // also, decide whether FamilyTreePropagator should update ftp or not
//         // (FamilyTreePropagator also processes the tree checkboxes, so we probably don't want it called)
//         else {
////            $editFlags = EDIT_UPDATE;
//            $status = GE_DUP_KEY;
//         }
      }
	}
   if ($status == GE_SUCCESS && ($editFlags == EDIT_NEW || $text != $oldText)) {
      $isUpdatable = true;

      if ($editFlags == EDIT_UPDATE) {
         $revision = Revision::newFromId( $article->getLatest());
         if ($revision && $revision->getComment() != 'gedcom upload') {
            $isUpdatable = false;
            error_log("Cannot update existing user-edited page: ".$article->getTitle()->getPrefixedText());
         }
      }

      if ($isUpdatable) {
         // NOTE: This doesn't execute the code in FamilyTreePropagator to update familytree_page, so if you edit a page, you'll have to update
         // the familytree_page.fp_latest yourself.  Also, FamilyTreePropagator adds the page to the tree (based upon request checkboxes), but we do this below
         if (!$article->doEdit($text, 'gedcom upload', $editFlags | EDIT_SUPPRESS_RC)) {
            $status = GE_WIKI_ERROR;
         }
         // TODO remove this
         if ($ns == NS_PERSON) {
            $xml = StructuredData::getXml('person', $text);
            $summaryFields = explode('|', Person::getSummary($xml, $title));
            $birthDate = $summaryFields[3];
            $deathDate = $summaryFields[5];
            $birthYear = '';
            $deathYear = '';
            if (preg_match('/\d\d\d\d/', $birthDate, $matches)) {
               $birthYear = $matches[0];
            }
            if (preg_match('/\d\d\d\d/', $deathDate, $matches)) {
               $deathYear = $matches[0];
            }
            if ((($birthYear && $birthYear < 1750) || ($deathYear && $deathYear < 1750)) &&
                !in_array($wgUser->getName(), explode('|', wfMsg('trustedgedcomuploaders')))) {
               error_log($title->getPrefixedText()."\n", 3, '/opt/wr/logs/earlypeople.txt');
            }
         }
      }
   }

   if ($status == GE_SUCCESS) {
      $dbw =& wfGetDB( DB_MASTER );
      $dbw->ignoreErrors(true);
      $dbw->begin();

//      if ($status == GE_SUCCESS) {
//         // save the data
//         $data = $xml->data->asXML();
//         if ($data) {
//            $dataVersion = 1;
//            $status == fgSaveData($dbw, $treeId, $title, $data, true);
//         }
//         else {
//            $dataVersion = 0;
//         }
//      }

      // add the page to the tree
      if ($status == GE_SUCCESS) {
   	   if (!FamilyTreeUtil::addPage($dbw, $wgUser, $treeId, $title, 0, 0, 0, $uid, 0)) {
   	      $status = GE_DB_ERROR;
   	   }
      }
      
      // watch the page
	   if ($status == GE_SUCCESS) {
   		StructuredData::addWatch($wgUser, $article, true);
	   }

   	if ($status == GE_SUCCESS) {
     	   $dbw->commit();
     	}
     	else {
     	   $dbw->rollback();
     	}
   }

	// return status
   $titleString = StructuredData::escapeXml($titleString);
  	return "<generate status=\"$status\" ns=\"$ns\" title=\"$titleString\"></generate>";
}

/**
 * Add pages to tree and watchlist
 *
 * @param unknown_type $args XML: <pages tree_id="nnn"><page namespace="108" title="John Doe (1)" uid="..."/>...</pages>
 * @return unknown
 */
function wfAddPagesToTree($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrBotUserID, $wrIsGedcomUpload;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
  	$wrIsGedcomUpload = true;

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (wfReadOnly() || $wgUser->getID() != $wrBotUserID) {
	   $status = GE_NOT_AUTHORIZED;
	}
	else {
   	$xml = simplexml_load_string($args);
   	$treeId = (int)$xml['tree_id'];
   	if (!$treeId) {
   		$status = GE_INVALID_ARG;
   	}
	}
	if ($status == GE_SUCCESS) {
   	$dbr =& wfGetDB(DB_SLAVE);
		$dbr->ignoreErrors(true);
	   $userName = $dbr->selectField('familytree', 'ft_user', array('ft_tree_id' => $treeId));
      $errno = $dbr->lastErrno();
		if ($errno > 0) {
		   $status = GE_DB_ERROR;
		}
		else if ($userName === false) {
		   $status = GE_NOT_FOUND;
		}
		else {
         $wgUser = User::newFromName($userName, false); // switch the global user
         if (!$wgUser) {
            $status = GE_NOT_FOUND;
         }
		}
	}
	if ($status == GE_SUCCESS) {
      $dbw =& wfGetDB( DB_MASTER );
      $dbw->ignoreErrors(true);
      $dbw->begin();

		foreach ($xml->page as $page) {
	   	$ns = (int)$page['namespace'];
	   	$titleString = (string)$page['title'];
	   	$uid = (string)$page['uid'];
	   	$title = Title::newFromText($titleString, $ns);
   	   if (!$titleString || $title == null) {
//wfDebug("wfAddPagesToTree error $ns $titleString\n");
	         $status = GE_INVALID_ARG;
   	   }
   	   if ($status == GE_SUCCESS) {
	         $article = new Article($title, 0);
		      // add the page to the tree
	   	   if (!FamilyTreeUtil::addPage($dbw, $wgUser, $treeId, $title, $title->getLatestRevID(), 0, 0, $uid, 0)) {
	   	      $status = GE_DB_ERROR;
	   	   }
	   	   else {
		   		StructuredData::addWatch($wgUser, $article, true);
			   }
   	   }
		}
   	if ($status == GE_SUCCESS) {
     	   $dbw->commit();
     	}
     	else {
     	   $dbw->rollback();
     	}
   }

	// return status
  	return "<add status=\"$status\"/>";
}

/**
 * Create family tree page without an immediate edit mode
 *
 * This is also called by search.js
 *
 * @param unknown_type $args
 * @return GE_SUCCESS, GE_INVALID_ARG, GE_NOT_LOGGED_IN, GE_NOT_AUTHORIZED, GE_NOT_FOUND, GE_DUP_KEY, GE_DB_ERROR
 */
function wfAddPage($args) {
	global $wgUser, $wgAjaxCachePolicy, $wgArticle, $wgTitle, $wgLang;
 
	// Message to display when attempting to add a page instead of the issue description that displays in other contexts. Only needed for a few issue types.
	// Note that there is a similar list in DQHandler. Some messages should be kept in sync (but do not use special formating in these messages). 
	$DATA_QUALITY_ADD_MESSAGE = array(
		"Invalid date(s)" => "Invalid date(s). Dates should be in D MMM YYYY format (ie 5 Jan 1900) with optional modifiers (eg, bef, aft).",
		"Considered living" => "This person was born/christened less than 110 years ago and does not have a death/burial date.  Living people cannot be added to WeRelate.",
		"Potentially living" => "This person may have been born/christened less than 110 years ago and does not have a death/burial date.  Living people cannot be added to WeRelate.",
		"Missing gender" => "You must select a gender (right-click and select 'Back' if necessary)"
 	);

	// set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;
	$title = null;
	$titleString = null;
	$error = '';
	$args = AjaxUtil::getArgs($args);
	$ns = $wgLang->getNsIndex($args['ns']);
	$titleString = @$args['title'];
	$update = ($args['update'] == 'true');
	if (!$wgUser->isLoggedIn()) {
    	$status = GE_NOT_LOGGED_IN;
	}
	else if ($wgUser->isBlocked() || wfReadOnly()) {
    	$status = GE_NOT_AUTHORIZED;
	}
	if ($status == GE_SUCCESS) {
    	$dbw =& wfGetDB( DB_MASTER );
    	$dbw->ignoreErrors(true);
    	$dbw->begin();
    	$text = '';

    	if ($titleString) {
        // user passed in existing title; just add to watchlist and trees
        $title = Title::newFromText($titleString, $ns);
    	}
			else if ($ns == NS_PERSON) {
				if (!$title) $title = StructuredData::constructPersonTitle($args['g'], $args['s']);
				if ($title) {
					if ($args['bt'] == 'chr') {
								$bird = '';
								$birp = '';
								$chrd = $args['bd'];
								$chrp = $args['bp'];
					}
					else {
								$bird = $args['bd'];
								$birp = $args['bp'];
								$chrd = '';
								$chrp = '';
					}
					if ($args['dt'] == 'bur') {
								$dead = '';
								$deap = '';
								$burd = $args['dd'];
								$burp = $args['dp'];
					}
					else {
								$dead = $args['dd'];
								$deap = $args['dp'];
								$burd = '';
								$burp = '';
					}
					$text = Person::getPageText($args['g'], $args['s'], $args['gnd'], $bird, $birp, $dead, $deap,
                                           $title->getText(), null, @$args['pf'], @$args['sf'],
                                           $chrd, $chrp, $burd, $burp);
					// Get issues and see if any are serious enough to prevent the page from being created.
					// Note that only some types of issues can be found at this point. Detecting issues in relation to other family members 
					// relies on the family page being linked to their pages (which it isn't yet).
					$issues = DQHandler::getUnverifiedIssues($text, $title, 'person');
					for ($i=0; $i<sizeof($issues); $i++) {
						if ($issues[$i][0] != "Anomaly") {
							$status = GE_INVALID_ARG;
							// By default, use the issue description in the error message. 
							$msg = $issues[$i][1];
													 
							// But use a different message as appropriate.
							foreach ( array_keys($DATA_QUALITY_ADD_MESSAGE) as $partialIssueDesc ) {
								if ( substr($issues[$i][1], 0, strlen($partialIssueDesc)) == $partialIssueDesc ) {
									$msg = $DATA_QUALITY_ADD_MESSAGE[$partialIssueDesc];
									break;  
								}
							}
							$error = ($issues[$i][0] == "Error" ? "Please correct this error: " : "") . $msg;
							break;
						}
					}
				}
			}
			else if ($ns == NS_FAMILY) {
				// The only error that can be detected when adding a new family page is an invalid marriage date.
				// Detecting issues in relation to family members relies on the family page being saved (which it isn't yet).
				if (ESINHandler::isInvalidDate($args['md'])) {
					$status = GE_INVALID_ARG;
					$error = "Please correct this error: " . $DATA_QUALITY_ADD_MESSAGE["Invalid date(s)"];
				}
				else {
					$title = StructuredData::constructFamilyTitle($args['hg'], $args['hs'], $args['wg'], $args['ws']);
					if ($title) {
						$text = Family::getPageText($args['md'], $args['mp'],
										$title->getText(), null, @$args['ht'], @$args['wt'], @$args['ct']);
					}
				}
			}
			else if ($ns == NS_SOURCE) {
				$title = StructuredData::constructSourceTitle($args['sty'], $args['st'], $args['a'], $args['p'], $args['pi'], $args['pu']);
				if ($title) {
					$text = Source::getPageText($args['sty'], $args['st'], $args['a'], $args['p'], $args['pi'], $args['pu']);
				}
				else {
					$error = 'Required source fields are missing; please press the Back button on your browser to enter the required fields.';
				}
			}
			else if ($ns == NS_MYSOURCE) {
				$t = $args['t'];
				if (mb_strpos($t, $wgUser->getName().'/') != 0) {
					$t = $wgUser->getName().'/'.$t;
				}
				$title = Title::newFromText($t, NS_MYSOURCE);
				if ($title) {
					$text = MySource::getPageText($args['a'], $args['p'], $args['s']);
				}
			}
			else if ($ns == NS_PLACE) {
				$title = StructuredData::constructPlaceTitle($args['pn'], $args['li']);
				$text = Place::getPageText();
				// check existing located-in, not root
				$titleText = $title->getFullText();
				$pos = mb_strpos($titleText, ',');
				if ($pos === false) {
					$title = null;
					$error = 'You need to fill in the country';
        }
        else {
					$locatedIn = Title::newFromText(trim(mb_substr($titleText, $pos+1)), NS_PLACE);
					if (!$locatedIn->exists()) {
            		$title = null;
            		$error = 'Before you can add this place, you must first add '.$locatedIn->getFullText();
            	}
				}
			}

			if ($status == GE_SUCCESS && $title == null) {
	  	 	$status = GE_INVALID_ARG;
	   		if (!$error) $error = 'Invalid page title; unable to create page';
			}
    	// don't update in the case of the user passing in a non-existing titleString
    	if ($update && !($titleString && !$title->exists())) {
        	if ($status == GE_SUCCESS) {
        		$article = new Article($title, 0);
            	// don't set the global article and title to this; we don't need to propagate -- but we do for  places
            	// NOTE: This doesn't execute the code in FamilyTreePropagator to update familytree_page and add page to tree, but we don't want that to be called
            	// because we add the page to the tree below
            	if ($title->exists()) { // don't update the page if it already exists, except to add a spouse-family
            		if ($ns == NS_PERSON && @$args['sf']) {
                		$content =& $article->fetchContent();
                		$updated = false;
                		Person::updateFamilyLink('spouse_of_family', '', $args['sf'], $content, $updated);
                		if ($updated) {
                    		$article->doEdit($content, 'Add spouse family: [[Family:'.$args['sf'].']]', EDIT_UPDATE);
                    		StructuredData::purgeTitle($title, +1); // purge person with a fudge factor so family link will be blue
                		}
            		}
            		$revid = 0;  // ok for revid to be 0 if page exists because we no longer use revid
        		}
            	else {
            		if (!$article->doEdit($text, '')) {
                		$status = GE_WIKI_ERROR;
            		}
            		else {
                		$revid = $article->mRevIdEdited;
            		}
            	}
        	}

        	if ($status == GE_SUCCESS) {
            	// add the page to the trees (or to no trees)
            	$allTrees = FamilyTreeUtil::getFamilyTrees($wgUser->getName());
            	$trees = explode('|',@$args['tree']);
            	$checkedTreeIds = array();
            	foreach ($allTrees as $tree) {
            		if (in_array(FamilyTreeUtil::toInputName($tree['name']), $trees)) {
                		$checkedTreeIds[] = $tree['id'];
//                  if (!FamilyTreeUtil::addPage($dbw, $wgUser, $tree['id'], $title, $revid, 0)) {
//                     $status = GE_DB_ERROR;
//                  }
            		}
            	}
            	// update which trees are checked
            	FamilyTreeUtil::updateTrees($dbw, $title, $revid, $allTrees, array(), $checkedTreeIds);
        	}

        	// watch the page
        	if ($status == GE_SUCCESS) {
        		StructuredData::addWatch($wgUser, $article, true);
        	}
    	}

		if ($status == GE_SUCCESS) {
     		$dbw->commit();
    	}
     	else {
     		$dbw->rollback();
        	if (!$error) $error = 'Unable to create page';
     	}
	}

	// return status
	$titleString = '';
	if ($title) {
		$titleString = StructuredData::escapeXml($title->getText());
	}
  	return "<addpage status=\"$status\" title=\"$titleString\" error=\"$error\"></addpage>";
}

function fgGetSourcePageTitle($dbr, $minCount, $sql) {
	$rows = $dbr->query($sql);
	$errno = $dbr->lastErrno();
	if ($errno > 0) {
		return '';
	}
	else if ($rows !== false) {
		$titles = array();
		while ($row = $dbr->fetchObject($rows)) {
			$title = $row->title;
         if (@$titles[$title]) {
         	$titles[$title] = $titles[$title] + 1;
         }
         else {
         	$titles[$title] = 1;
         }
		}
		$dbr->freeResult($rows);

		$maxTitle = '';
		$maxCount = 0;
		$countMaxCount = 0;
		foreach($titles as $title => $count) {
			if ($count > $maxCount) {
				$maxCount = $count;
				$countMaxCount = 1;
				$maxTitle = $title;
			}
			else if ($count == $maxCount) {
				$countMaxCount++;
			}
		}
		if ($maxCount >= $minCount && $countMaxCount == 1) {
			return $maxTitle;
		}
	}
	return '';
}

function wfMatchSource($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrBotUserID;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (wfReadOnly() || $wgUser->getID() != $wrBotUserID) {
	   $status = GE_NOT_AUTHORIZED;
	}

   $args = AjaxUtil::getArgs($args);

	if ($status == GE_SUCCESS) {
   	$dbr =& wfGetDB(DB_SLAVE);
		$dbr->ignoreErrors(true);
		$maxLen = 50;
		$pageTitle = '';

		// lookup on userid, (AT, AA, T, A)
		if (!$pageTitle && $args['author'] && $args['title']) {
			$pageTitle = fgGetSourcePageTitle($dbr, 1, 'SELECT DISTINCT title, user_id FROM gedcom_source_matches '.
					 'where user_id = '.$dbr->addQuotes($args['userID']).
					 ' AND source LIKE '.$dbr->addQuotes(substr($args['author'].$args['title'],0,$maxLen).'%'));
		}
		if (!$pageTitle && $args['author'] && $args['abbrev']) {
			$pageTitle = fgGetSourcePageTitle($dbr, 1, 'SELECT DISTINCT title, user_id FROM gedcom_source_matches '.
					 'where user_id = '.$dbr->addQuotes($args['userID']).
					 ' AND source LIKE '.$dbr->addQuotes(substr($args['author'].$args['abbrev'],0,$maxLen).'%'));
		}
		if (!$pageTitle && $args['title']) {
			$pageTitle = fgGetSourcePageTitle($dbr, 1, 'SELECT DISTINCT title, user_id FROM gedcom_source_matches '.
					 'where user_id = '.$dbr->addQuotes($args['userID']).
					 ' AND source LIKE '.$dbr->addQuotes(substr($args['title'],0,$maxLen).'%'));
		}
		if (!$pageTitle && $args['abbrev']) {
			$pageTitle = fgGetSourcePageTitle($dbr, 1, 'SELECT DISTINCT title, user_id FROM gedcom_source_matches '.
					 'where user_id = '.$dbr->addQuotes($args['userID']).
					 ' AND source LIKE '.$dbr->addQuotes(substr($args['abbrev'],0,$maxLen).'%'));
		}

		// lookup without userid (AT, AA, T, A)
		if (!$pageTitle && $args['author'] && $args['title']) {
			$pageTitle = fgGetSourcePageTitle($dbr, 2, 'SELECT DISTINCT title, user_id FROM gedcom_source_matches '.
					 'where source LIKE '.$dbr->addQuotes(substr($args['author'].$args['title'],0,$maxLen).'%'));
		}
		if (!$pageTitle && $args['author'] && $args['abbrev']) {
			$pageTitle = fgGetSourcePageTitle($dbr, 2, 'SELECT DISTINCT title, user_id FROM gedcom_source_matches '.
					 'where source LIKE '.$dbr->addQuotes(substr($args['author'].$args['abbrev'],0,$maxLen).'%'));
		}
		if (!$pageTitle && $args['title']) {
			$pageTitle = fgGetSourcePageTitle($dbr, 2, 'SELECT DISTINCT title, user_id FROM gedcom_source_matches '.
					 'where source LIKE '.$dbr->addQuotes(substr($args['title'],0,$maxLen).'%'));
		}
		if (!$pageTitle && $args['abbrev']) {
			$pageTitle = fgGetSourcePageTitle($dbr, 2, 'SELECT DISTINCT title, user_id FROM gedcom_source_matches '.
					 'where source LIKE '.$dbr->addQuotes(substr($args['abbrev'],0,$maxLen).'%'));
		}

		// if find match, add it to db
		if ($pageTitle) {
			$dbw =& wfGetDB( DB_MASTER );
			$dbw->ignoreErrors(true);
			$dbw->begin();

		   $record = array(
		   	'fgd_gedcom_id' => $args['gedcomID'],
		   	'fgd_gedcom_key' => $args['gedcomKey'],
		   	'fgd_exclude' => 0,
		   	'fgd_living' => 0,
		   	'fgd_merged' => -1,
		   	'fgd_match_namespace' => 104,
		   	'fgd_match_title' => $pageTitle);
	      if (!$dbw->insert('familytree_gedcom_data', $record)) {
	         // MYSQL specific
	         $status = ($dbw->lastErrno() == 1062 ? GE_SUCCESS : GE_DB_ERROR);
			}
			if ($status == GE_SUCCESS) {
				$dbw->commit();
			}
			else {
				$dbw->rollback();
			}
      }
      else {
         $status = GE_NOT_FOUND;
      }
   }

	// return status
  	return "<match status=\"$status\"/>";
}

function fgAddGedcomSourceMatch($dbw, $userID, $source, $sourceType, $pageTitle) {
	$status = GE_SUCCESS;
	$record = array(
		'user_id' => $userID,
		'source' => substr($source, 0, 255),
		'source_type' => $sourceType,
		'title' => $pageTitle);
	if (!$dbw->insert('gedcom_source_matches', $record)) {
		// MYSQL specific
		$status = ($dbw->lastErrno() == 1062 ? GE_SUCCESS : GE_DB_ERROR);
	}
	return $status;
}

function wfAddGedcomSourceMatches($args) {
	global $wgUser, $wgAjaxCachePolicy, $wrBotUserID;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
	$status = GE_SUCCESS;

	if (!$wgUser->isLoggedIn()) {
	   $status = GE_NOT_LOGGED_IN;
	}
	else if (wfReadOnly() || $wgUser->getID() != $wrBotUserID) {
	   $status = GE_NOT_AUTHORIZED;
	}

   $args = AjaxUtil::getArgs($args);

	if ($status == GE_SUCCESS) {
		$dbw =& wfGetDB( DB_MASTER );
		$dbw->ignoreErrors(true);
		$dbw->begin();

		if ($args['author'] && $args['title']) {
			$stat = fgAddGedcomSourceMatch($dbw, $args['userID'], $args['author'].$args['title'], 'AT', $args['pageTitle']);
			if ($stat != GE_SUCCESS) {
				$status = $stat;
			}
		}
		if ($args['author'] && $args['abbrev']) {
			$stat = fgAddGedcomSourceMatch($dbw, $args['userID'], $args['author'].$args['abbrev'], 'AA', $args['pageTitle']);
			if ($stat != GE_SUCCESS) {
				$status = $stat;
			}
		}
		if ($args['title']) {
			$stat = fgAddGedcomSourceMatch($dbw, $args['userID'], $args['title'], 'T', $args['pageTitle']);
			if ($stat != GE_SUCCESS) {
				$status = $stat;
			}
		}
		if ($args['abbrev']) {
			$stat = fgAddGedcomSourceMatch($dbw, $args['userID'], $args['abbrev'], 'A', $args['pageTitle']);
			if ($stat != GE_SUCCESS) {
				$status = $stat;
			}
		}

		if ($status != GE_SUCCESS) {
			$dbw->rollback();
		}
		else {
			$dbw->commit();
		}
   }

	// return status
  	return "<addGedcomSourceMatches status=\"$status\"/>";
}

//////////////////
// Support functions
//////////////////

function fgIsAuthorized($gedcomId, $update, &$status) {
	global $wgUser, $wrBotUserID;
	
	$isAdmin = $wgUser->isAllowed('patrol');
	$dbr =& wfGetDB(DB_SLAVE);
	$fg = $dbr->selectRow(array('familytree', 'familytree_gedcom'), array('ft_user', 'fg_status'), 
					array('ft_tree_id = fg_tree_id', 'fg_id' => $gedcomId));
	if (!$fg) {
		$status = GE_NOT_FOUND;
		return false;
	}
	
	$isOwner = $wgUser->getName() == $fg->ft_user;
	
	if ($update) {
		if ($wgUser->isBlocked() || wfReadOnly()) {
         $status = GE_NOT_AUTHORIZED;
         return false;
      }
      if ($isOwner && $fg->fg_status == FG_STATUS_HOLD) {
         $status = GE_ON_HOLD;
         return false;
      }
		if (($isAdmin && ($fg->fg_status == FG_STATUS_ADMIN_REVIEW || $fg->fg_status == FG_STATUS_PHASE2 || $fg->fg_status == FG_STATUS_HOLD)) ||
			 ($isOwner && $fg->fg_status == FG_STATUS_PHASE2) ||
			 ($wgUser->getID() == $wrBotUserID && $fg->fg_status == FG_STATUS_PHASE3)) {
         return true;
      }
	}
	else if ($isAdmin || $isOwner || ($wgUser->getID() == $wrBotUserID && $fg->fg_status == FG_STATUS_PHASE3)) {
      return true;
   }
   $status = GE_NOT_AUTHORIZED;
   return false;
}

function fgSaveData($dbw, $treeId, $title, $data, $insert) {
   $status = GE_SUCCESS;
   if ($insert) {
	   $record = array('fd_tree_id' => $treeId, 'fd_namespace' => $title->getNamespace(), 'fd_title' => $title->getDBkey(), 'fd_data' => $data);
      if (!$dbw->insert('familytree_data', $record)) {
         // MYSQL specific
         $status = ($dbw->lastErrno() == 1062 ? GE_DUP_KEY : GE_DB_ERROR);
      }
   }
   else {
      $dbw->update('familytree_data', array('fd_data' => $data),
                     array('fd_tree_id' => $treeId, 'fd_namespace' => $title->getNamespace(), 'fd_title' => $title->getDBkey()));
   }
   return $status;
}

// merged array of 'true' or 'false'
function fgUpdateMatches($gedcomId, &$keys, &$matches, &$merged) {
	global $wgUser;
	
	$status = GE_SUCCESS;
	$dbw =& wfGetDB( DB_MASTER );
	$dbw->ignoreErrors(true);
	$dbw->begin();
	$row = $dbw->selectRow(array('familytree','familytree_gedcom'), array('fg_tree_id', 'ft_user'), 
										array('fg_id' => $gedcomId, 'fg_tree_id = ft_tree_id'));
	if ($row != null) {
		$gedcomUser = User::newFromName($row->ft_user, false);
		for ($i = 0; $i < count($keys); $i++) {
			if (!GedcomUtil::updateGedcomMatch($dbw, $row->fg_tree_id, $gedcomId, $keys[$i], $matches[$i], $merged[$i] == 'true' ? 1 : -1, $gedcomUser)) {
				$status = GE_DB_ERROR;
				break;
			}
		}
	}
	else {
		$status = GE_INVALID_ARG;
	}
	
   if ($status != GE_SUCCESS) {
   	$dbw->rollback();
   }
   else {
   	$dbw->commit();
   }
   return $status;
}

function fgUpdatePotentialMatches($gedcomId, &$keys, &$matches) {
	$status = GE_SUCCESS;
	$dbw =& wfGetDB( DB_MASTER );
	$dbw->ignoreErrors(true);
	$dbw->begin();
	$gedcomId = $dbw->addQuotes($gedcomId);
	for ($i = 0; $i < count($keys); $i++) {
		$potentialMatches = $dbw->addQuotes($matches[$i]);
		$sql = 'INSERT INTO familytree_gedcom_data (fgd_gedcom_id, fgd_gedcom_key, fgd_potential_matches)'.
					' VALUES('.$gedcomId.','.$dbw->addQuotes($keys[$i]).','.$potentialMatches.')'.
					' ON DUPLICATE KEY UPDATE fgd_potential_matches='.$potentialMatches;
		$dbw->query($sql);
		if ($dbw->lastErrno()) {
			$status = GE_DB_ERROR;
			break;
		}
	}
   if ($status != GE_SUCCESS) {
   	$dbw->rollback();
   }
   else {
   	$dbw->commit();
   }
	return $status;
}

function fgGetRelatedMatches(&$keys, &$matches) {
	$result = array();
	for ($i = 0; $i < count($keys); $i++) {
		if ($matches[$i] != '#nomatch#') {
			$title = Title::newFromText($matches[$i]);
			$result[] = '<match id="'.StructuredData::escapeXml($keys[$i]).'" match="'.StructuredData::escapeXml($matches[$i]).'">';
			if ($title->getNamespace() == NS_PERSON) {
				// get potential matches
		      $person = new Person($title->getText());
		      $xml = $person->getPageXml();
		      if (isset($xml)) {
   				foreach ($xml->child_of_family as $f) {
   					$familyTitleString = (string)$f['title'];
						$result[] = '<child_of_family title="'.StructuredData::escapeXml($familyTitleString).'"/>';
   				}
   				foreach ($xml->spouse_of_family as $f) {
   					$familyTitleString = (string)$f['title'];
						$result[] = '<spouse_of_family title="'.StructuredData::escapeXml($familyTitleString).'"/>';
   				}
		      }
			}
			$result[] = '</match>';
		}
	}
	return join("\n", $result);
}

function fgReviewNeeded($gedcomId, $filename, $dbw) {
	$reasons = array();
	$personCnt = 0;
	$problemsCnt = 0;
	$potentialMatches = array();
	$unmatchedCnt = 0;
	
	// read gedcom file
	$handle = fopen($filename, 'r');
   while (!feof($handle)) {
	   $line = fgets($handle);
   	if (strpos($line, '<page ') === 0) {
   		if (strpos($line, ' namespace="108"') !== false) {
   			$personCnt++;
   		}
   		if (strpos($line, ' problems="') !== false) {
   			$problemsCnt++;
   		}
   		if (strpos($line, ' potentialMatches="') !== false) {
   			if (preg_match('/ id="([^"]+)"/', $line, $matches)) {
   				$potentialMatches[$matches[1]] = 1;
   			}
   			else {
   				error_log("invalid gedcom=$filename line=$line");
   			}
   		}
   	}
 	}
 	fclose($handle);

 	// read database for matches
	$sql = 'SELECT fgd_gedcom_key, fgd_exclude, fgd_match_namespace FROM familytree_gedcom_data WHERE fgd_gedcom_id = ' . $dbw->addQuotes($gedcomId).
			 " AND (fgd_match_namespace > 0 OR fgd_potential_matches > '' OR fgd_exclude = 1)";
	$rows = $dbw->query($sql);
	if ($dbw->lastErrno() == 0) {
		$result = array();
	   while ($row = $dbw->fetchObject($rows)) {
	   	if ($row->fgd_match_namespace > 0 || $row->fgd_exclude == 1) {
				if (@$potentialMatches[$row->fgd_gedcom_key]) {
		   		$potentialMatches[$row->fgd_gedcom_key] = 0;
				}
	   	}
	   	else {
	   		// add
	   		$potentialMatches[$row->fgd_gedcom_key] = 1;
	   	}
	   }
	   $dbw->freeResult($rows);
	}
	else {
		error_log("error reading familytree_gedcom for gedcomId=$gedcomId");
	}
 	foreach ($potentialMatches as $match) {
 		if ($match) {
 			$unmatchedCnt++;
 		}
 	}
 	
 	// log reasons
 	if ($unmatchedCnt > 10) {
 		$reasons[] = "$unmatchedCnt unmatched families";
 	}
 	if ($problemsCnt > 10) {
 		$reasons[] = "$problemsCnt warnings";
 	}
 	if ($personCnt == 0 || $personCnt > 1000) {
 		$reasons[] = "$personCnt people";
 	}
	$reasons[] = 'initial launch';
 	
 	$reason = join('; ', $reasons);
// 	wfDebug("fgReviewGedcom gedcomId=$gedcomId reason=$reason\n");
 	return $reason;
}

