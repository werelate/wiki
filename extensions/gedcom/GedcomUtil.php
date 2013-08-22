<?php

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/ESINHandler.php");
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");

class GedcomUtil {
	
	//
	// gedcom titles
	// 
	
	public static function isGedcomTitle($titleString) {
		return StructuredData::endsWith($titleString, " gedcom)");
	}
	
	public static function getKeyFromTitle($titleString) {
		$posStart = mb_strrpos($titleString, '(');
		$posEnd = mb_strrpos($titleString, ' gedcom)');
		if ($posStart !== false && $posEnd !== false) {
			return mb_substr($titleString, $posStart+1, $posEnd-$posStart-1);
		}
		else {
			return '';
		}
	}
	
	public static function makeGedcomTitle($key) {
		return "($key gedcom)";		
	}
	
	//
	// gedcomData structure
	//

	public static function getGedcomXml(&$gedcomData, $titleString) {
		$pageId = GedcomUtil::getKeyFromTitle($titleString);
		return $gedcomData[$pageId];
	}
	
	public static function getGedcomContents(&$gedcomData, $titleString) {
		$pageId = GedcomUtil::getKeyFromTitle($titleString);
		return trim(str_replace(ESINHandler::ESIN_FOOTER_TAG, '', $gedcomData[$pageId]->content));
	}
	
	public static function getGedcomId($s) {
		if (preg_match('/^<gedcom [^>]*gedcomId="([^"]+)"/', $s, $matches)) {
			return $matches[1];
		}
		return 0;
	}
	
	public static function getGedcomDataMap($s) {
   	$result = array();
   	$xml = simplexml_load_string($s);
   	foreach ($xml->person as $node) {
   		$result[(string)$node['id']] = $node;
   	}
   	foreach ($xml->family as $node) {
   		$result[(string)$node['id']] = $node;
   	}
		return $result;
	}
	
	public static function getGedcomDataMapFromFile($gedcomId, $pageIds) {
		global $wrGedcomInprocessDirectory, $wrGedcomXMLDirectory;
		
		$filename = $wrGedcomXMLDirectory . '/' . $gedcomId . '.xml';
		if (!file_exists($filename)) {
			$filename = $wrGedcomInprocessDirectory . '/' . $gedcomId . '.xml';
		}
		if (!file_exists($filename)) {
			error_log("gedcom file not found $filename");
			return null;
		}
		
		$gedcomData = array();
		$keepPage = false;
		$inContent = false;
		$inXML = false;
		$currentId = null;
		$currentTag = '';
		$xmlLines = null;
		$contentLines = null;
		$handle = fopen($filename, 'r');
	   while (!feof($handle)) {
		   $line = fgets($handle);
		   if ($inContent) {
		   	if (strpos($line, ']]></content>') === 0) {
		   		$inContent = false;
		   		if ($keepPage) {
		   			// finalize page
		   			$xml = simplexml_load_string(join("", $xmlLines));
		   			$xml->content = join("", $contentLines);
		   			$gedcomData[$currentId] = $xml;
		   			$keepPage = false;
		   		}
		   	}
		   	else if ($keepPage) {
		   		// gather page lines
		   		if ($inXML) {
		   			$xmlLines[] = $line;
		   			if (strpos($line, "</$currentTag>") === 0) {
		   				$inXML = false;
		   				$contentLines = array();
		   			}
		   		}
		   		else {
		   			$contentLines[] = $line;
		   		}
		   	}
		   }
		   else if (strpos($line, '<content><![CDATA[') === 0) {
		   	$inContent = true;
		   	if ($keepPage) {
		   		$inXML = true;
		   		$line = substr($line, strlen('<content><![CDATA[')); // skip past CDATA[
		   		$xmlLines = array();
		   		$xmlLines[] = $line;
		   		if (preg_match('/<([^>]+)>/', $line, $matches)) {
		   			$currentTag = $matches[1];
		   		}
		   		else {
		   			error_log("can't find tag in gedcom $gedcomId for id $currentId");
		   		}
		   	}
		   }
	   	else if (strpos($line, '<page ') === 0) {
   			if (preg_match('/ id="([^"]+)"/', $line, $matches) && in_array($matches[1], $pageIds)) {
   				$currentId = $matches[1];
	   			$keepPage = true;
   			}
	   	}
	 	}
	 	fclose($handle);
	 	
	 	return $gedcomData;
	}
		
	//
	// getting and putting gedcom data in memc
	//
	
	private static function getGedcomMemcKey() {
		global $wgUser;
		
		return 'gedcomdata:'.$wgUser->getName();
	}
	
	// the string is a single <person... or <family... for SpecialGedcomPage, or is <gedcom><person|family...>*</gedcom> otherwise
	public static function getGedcomDataString() {
		global $wgMemc;
		
  		return $wgMemc->get(GedcomUtil::getGedcomMemcKey());
	}
	
	// default to save for 15 minutes
	public static function putGedcomDataString($s, $seconds=1200) {
		global $wgMemc;
		
   	$wgMemc->set(GedcomUtil::getGedcomMemcKey(), $s, $seconds);
	}
	
	//
	// gedcom pageids in mergelog records
	//
	
	public static function getGedcomMergeLogKey($revid) {
		if (substr($revid, 0, 1) == 'g') {
			return substr($revid, 1);
		}
		return '';
	}
	
   public static function generateGedcomMergeLogKey($titleString) {
   	return 'g'.GedcomUtil::getKeyFromTitle($titleString);
   }
   
	public static function updateGedcomMatch($dbw, $treeId, $gedcomId, $key, $matchTitle, $merged, $gedcomUser) {
		$newTitle = ($matchTitle == '#nomatch#' ? null : Title::newFromText($matchTitle));
		$oldTitle = null;
		
		// read old match_namespace and title
		$row = $dbw->selectRow('familytree_gedcom_data', array('fgd_match_namespace', 'fgd_match_title'), 
										array('fgd_gedcom_id' => $gedcomId, 'fgd_gedcom_key' => $key));
		if ($row && $row->fgd_match_namespace > 0) {
			$oldTitle = Title::makeTitle($row->fgd_match_namespace, $row->fgd_match_title);
			if ($oldTitle && 
			    ($oldTitle->getNamespace() == NS_PERSON || $oldTitle->getNamespace() == NS_FAMILY || $oldTitle->getNamespace() == NS_MYSOURCE) &&
			    (!$newTitle || $oldTitle->getPrefixedText() != $newTitle->getPrefixedText())) {
				// remove match title from tree
				if (!FamilyTreeUtil::removePage($dbw, $treeId, $oldTitle)) {
					return false;
				}
				// unwatch match title if no other trees contain it
				if (!$dbw->selectField('familytree_page', 'fp_tree_id', 
												array('fp_namespace' => $row->fgd_match_namespace, 'fp_title' => $row->fgd_match_title, 'fp_user_id' => $gedcomUser->getID()))) {
	         	$article = new Article($oldTitle, 0);
	         	StructuredData::removeWatch($gedcomUser, $article);
				}
			}
		}
			
		if ($newTitle) {
			$matchTitle = $newTitle->getDBkey();
			$matchNamespace = $newTitle->getNamespace();
			$potentialMatchesAttr='';
			$potentialMatchesValue='';
			$potentialMatchesUpdate='';
			
			if (($newTitle->getNamespace() == NS_PERSON || $newTitle->getNamespace() == NS_FAMILY || $newTitle->getNamespace() == NS_MYSOURCE) &&
			    (!$oldTitle || $oldTitle->getPrefixedText() != $newTitle->getPrefixedText())) {
				// add match title to tree
	   	   if (!FamilyTreeUtil::addPage($dbw, $gedcomUser, $treeId, $newTitle, 0, 0)) {
	   	      return false;
	   	   }
		      // watch match title
	         $article = new Article($newTitle, 0);
	   		StructuredData::addWatch($gedcomUser, $article);
			}
		}
		else {
			$matchTitle = '';
			$matchNamespace = -1;
			$merged = 0;
			$potentialMatchesAttr=', fgd_potential_matches';  // reset potential matches to what's in the gedcom
			$potentialMatchesValue=", ''";
			$potentialMatchesUpdate=", fgd_potential_matches=''";
		}
		
		$matchTitle = $dbw->addQuotes($matchTitle);
		$sql = "INSERT INTO familytree_gedcom_data (fgd_gedcom_id, fgd_gedcom_key, fgd_match_namespace, fgd_match_title, fgd_merged$potentialMatchesAttr)".
					" VALUES($gedcomId,".$dbw->addQuotes($key).",$matchNamespace,$matchTitle,{$merged}{$potentialMatchesValue})".
					" ON DUPLICATE KEY UPDATE fgd_match_namespace=$matchNamespace, fgd_match_title=$matchTitle, fgd_merged={$merged}{$potentialMatchesUpdate}";
		$dbw->query($sql);
		return ($dbw->lastErrno() == 0);
	}
}
?>