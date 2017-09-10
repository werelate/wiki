<?php
require_once("commandLine.inc");
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/familytree/FamilyTreePropagator.php");
require_once("$IP/includes/Title.php");

$db =& wfGetDB( DB_MASTER );
$rows = $db->query("select page_namespace, page_title from page where page_is_redirect > 0 and (page_namespace = 108 or page_namespace = 110)");
while ($row = $db->fetchObject($rows)) {
	$title = Title::makeTitle($row->page_namespace, $row->page_title);
	$newTitle = StructuredData::getRedirectToTitle($title);
	if ($title->getPrefixedText() != $newTitle->getPrefixedText()) {
//		print "title=" . $title->getPrefixedText() . " newTitle=" . $newTitle->getPrefixedText() . "\n";  
		// from wrArticleSave
		WatchedItem::duplicateEntries($title, $newTitle);
	}
}
$db->freeResult($rows);
?>
