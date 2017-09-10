<?php
require_once("$IP/extensions/familytree/FamilyTreeQueryPage.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialTreeDeletionImpactSetup";

function wfSpecialTreeDeletionImpactSetup() {
	global $wgMessageCache, $wgSpecialPages;
	
	$wgMessageCache->addMessages( array( "treedeletionimpact" => "Tree Deletion Impact" ) );
	$wgSpecialPages['TreeDeletionImpact'] = array('SpecialPage','TreeDeletionImpact');
}

/**
 * SpecialShortpages extends QueryPage. It is used to return the shortest
 * pages in the database.
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class TreeDeletionImpactPage extends FamilyTreeQueryPage {

	function getName() {
		return "TreeDeletionImpact";
	}

	function getPageHeader() {
		return "<h3>Pages that link to pages that would be deleted if the tree were deleted</h3>".
					"<p>If the tree is deleted, these pages will lose their links.</p>".
				parent::getPageHeader();
	}
	
	// Return pages linked to by pages in tree that would be deleted if tree was deleted
	//   and where the pages would not be deleted if tree was deleted
	// Keep this query in sync with DeleteFamilyTreeJob.php
	function getSQL() {
		$dbr =& wfGetDB( DB_SLAVE );
		return 'SELECT page_namespace, page_title, page_is_redirect, fp_namespace, fp_title'.
				 ' FROM familytree use index (ft_user_name), familytree_page as fp1 use index (primary), pagelinks use index (pl_namespace), page use index (primary)'.
				 ' WHERE ft_user = '.$dbr->addQuotes($this->userName).' and ft_name = '.$dbr->addQuotes($this->treeName).' and ft_tree_id = fp_tree_id'.
				 ' and fp_namespace in ('.NS_IMAGE.','.NS_PERSON.','.NS_FAMILY.','.NS_MYSOURCE.')'.
				 ' and not exists (select 1 from watchlist use index (namespace_title) where wl_namespace = fp_namespace and wl_title = fp_title and wl_user <> fp_user_id)'.
             ' and not exists (SELECT 1 FROM familytree_page AS fp2 use index (fp_namespace_title_user) WHERE fp2.fp_namespace = fp1.fp_namespace and fp2.fp_title = fp1.fp_title and fp2.fp_user_id = fp1.fp_user_id and fp2.fp_tree_id <> fp1.fp_tree_id)'.
				 ' and pl_namespace = fp_namespace and pl_title = fp_title and pl_from = page_id'.
				 ' and (not exists (select 1 from familytree_page as fp2 use index (fp_namespace_title_user) WHERE fp2.fp_namespace = page_namespace and fp2.fp_title = page_title and fp2.fp_tree_id = fp1.fp_tree_id)'.
				 '   or page_namespace not in ('.NS_IMAGE.','.NS_PERSON.','.NS_FAMILY.','.NS_MYSOURCE.')'.
				 '   or exists (select 1 from watchlist use index (namespace_title) where wl_namespace = page_namespace and wl_title = page_title and wl_user <> fp_user_id)'.
				 '   or exists (select 1 from familytree_page AS fp2 use index (fp_namespace_title_user) WHERE fp2.fp_namespace = page_namespace and fp2.fp_title = page_title and fp2.fp_user_id = fp1.fp_user_id and fp2.fp_tree_id <> fp1.fp_tree_id))';
				 ($this->namespace != null ? ' and page_namespace = '.$dbr->addQuotes($this->namespace) : '');
	}

	function getOrder() {
		return ' ORDER BY page_namespace, page_title ';
	}

	function formatResult( $skin, $result ) {
		global $wgLang, $wgContLang;
		
		$pageTitle = Title::makeTitleSafe( $result->page_namespace, $result->page_title );
		$fpTitle = Title::makeTitleSafe( $result->fp_namespace, $result->fp_title );
		$pageLink = $skin->makeKnownLinkObj($pageTitle);
		$fpLink = $skin->makeKnownLinkObj($fpTitle);
		$msg = ($result->page_is_redirect ? "redirects to" : "links to");
		
		return "$pageLink $msg $fpLink";
	}
}

/**
 * constructor
 */
function wfSpecialTreeDeletionImpact($par) {
	list( $limit, $offset ) = wfCheckLimits();

	$sp = new TreeDeletionImpactPage();

	return $sp->doQuery($par, $offset, $limit );
}
?>
