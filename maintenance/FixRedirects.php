<?php
require_once("commandLine.inc");
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/familytree/FamilyTreePropagator.php");
require_once("$IP/includes/Title.php");

$db =& wfGetDB( DB_MASTER );
$rows = $db->query("select page_namespace, page_title from page where page_is_redirect > 0 and (page_namespace = 108 or page_namespace = 110) and exists ".
							"(select * from familytree_page where fp_namespace = page_namespace and fp_title = page_title)");
while ($row = $db->fetchObject($rows)) {
	$title = Title::makeTitle($row->page_namespace, $row->page_title);
	$newTitle = StructuredData::getRedirectToTitle($title);
	if ($title->getPrefixedText() != $newTitle->getPrefixedText()) {
//		print "title=" . $title->getPrefixedText() . " newTitle=" . $newTitle->getPrefixedText() . "\n";  
		$wgTitle = $title;
	   $ftp = new FamilyTreePropagator($title);
	   $result = $ftp->handleRedirect($newTitle);
	   if (!$result) {
	     	print "Error handling redirect: ". $title->getPrefixedText() . "\n";
	   }
  	}
}
$db->freeResult($rows);

?>
