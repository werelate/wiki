<?php
require_once("$IP/extensions/familytree/FamilyTreeQueryPage.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialTreeRelatedSetup";

function wfSpecialTreeRelatedSetup() {
	global $wgMessageCache, $wgSpecialPages;
	
	$wgMessageCache->addMessages( array( "treerelated" => "Related Pages" ) );
	$wgSpecialPages['TreeRelated'] = array('SpecialPage','TreeRelated');
}

/**
 * SpecialShortpages extends QueryPage. It is used to return the shortest
 * pages in the database.
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class TreeRelatedPage extends FamilyTreeQueryPage {

	function getName() {
		return "TreeRelated";
	}

	function getPageHeader() {
		return "<h3>Pages not in your tree that are linked to by pages in your tree</h3>
		<p>You may want to <a href=\"/wiki/Help:FAQ#How_do_I_add_pages_to_my_tree.3F\">add some of these pages to your tree</a>.  Select the <b>Person</b> or <b>Family</b> namespace to see related people or families.</p>".
				parent::getPageHeader();
	}
	
	function getSQL() {
		$dbr =& wfGetDB( DB_SLAVE );
		return "SELECT pl_namespace, pl_title, fp_namespace, fp_title".
				 " FROM familytree use index (ft_user_name), familytree_page use index (primary), page use index (name_title), pagelinks use index (pl_from)".
				 " WHERE ft_user = ".$dbr->addQuotes($this->userName)." and ft_name = ".$dbr->addQuotes($this->treeName).
				 " and ft_tree_id = fp_tree_id and fp_namespace = page_namespace and fp_title = page_title and page_id = pl_from".
				 ($this->namespace != null ? " and pl_namespace = ".$dbr->addQuotes($this->namespace) : " and pl_namespace % 2 = 0").
				 " and not exists (select 1 from familytree_page use index (fp_namespace_title_user) where fp_namespace = pl_namespace and fp_title = pl_title)";
	}

	function getOrder() {
		return ' ORDER BY pl_namespace, pl_title ';
	}

	function preprocessResults( &$dbo, $res ) {
		$batch = new LinkBatch();
		while( $row = $dbo->fetchObject( $res ) )
			$batch->addObj( Title::makeTitleSafe( $row->pl_namespace, $row->pl_title ) );
		$batch->execute();
		if( $dbo->numRows( $res ) > 0 ) {
			$dbo->dataSeek( $res, 0 );
		}
	}

	function formatResult( $skin, $result ) {
		global $wgLang, $wgContLang;
		
		$plTitle = Title::makeTitleSafe( $result->pl_namespace, $result->pl_title );
		$fpTitle = Title::makeTitleSafe( $result->fp_namespace, $result->fp_title );
		$plLink = $skin->makeLinkObj($plTitle);
		$fpLink = $skin->makeKnownLinkObj($fpTitle);
		
		return "$plLink linked by $fpLink";
	}
}

/**
 * constructor
 */
function wfSpecialTreeRelated($par) {
	list( $limit, $offset ) = wfCheckLimits();

	$sp = new TreeRelatedPage();

	return $sp->doQuery($par, $offset, $limit );
}

?>
