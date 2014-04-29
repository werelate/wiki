<?php
require_once("$IP/extensions/familytree/FamilyTreeQueryPage.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialTreeCountWatchersSetup";

function wfSpecialTreeCountWatchersSetup() {
	global $wgSpecialPages;

	$wgSpecialPages['TreeCountWatchers'] = array('SpecialPage','TreeCountWatchers');
}

/**
 * SpecialShortpages extends QueryPage. It is used to return the shortest
 * pages in the database.
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class TreeCountWatchersPage extends FamilyTreeQueryPage {

	function getName() {
		return "TreeCountWatchers";
	}

	function getPageHeader() {
        return "<h3>".wfMsg('treecountwatchersheader')."</h3>".
				parent::getPageHeader();
	}
	
	function getSQL() {
		$dbr =& wfGetDB( DB_SLAVE );
		return "SELECT fp_namespace, fp_title, count(*) as watchers".
				 " FROM familytree use index (ft_user_name), familytree_page use index (primary), watchlist use index (namespace_title)".
				 " WHERE ft_user = ".$dbr->addQuotes($this->userName)." and ft_name = ".$dbr->addQuotes($this->treeName).
				 " and ft_tree_id = fp_tree_id and fp_namespace = wl_namespace and fp_title = wl_title".
				 ($this->namespace != null ? " and fp_namespace = ".$dbr->addQuotes($this->namespace) : "").
				 " GROUP BY fp_namespace, fp_title";
	}

	function getOrder() {
		return ' ORDER BY watchers DESC';
	}

	function formatResult( $skin, $result ) {
		global $wgLang, $wgContLang;
		
		$fpTitle = Title::makeTitleSafe( $result->fp_namespace, $result->fp_title );
		$fpLink = $skin->makeKnownLinkObj($fpTitle);
        $watchers = $result->watchers . ' ' . ($result->watchers > 1 ? wfMsg('peoplewatching') : wfMsg('personwatching'));
		
		return "$fpLink ($watchers)";
	}
}

/**
 * constructor
 */
function wfSpecialTreeCountWatchers($par) {
	list( $limit, $offset ) = wfCheckLimits();

	$sp = new TreeCountWatchersPage();

	return $sp->doQuery($par, $offset, $limit );
}

?>
