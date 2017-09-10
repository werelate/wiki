<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
require_once("$IP/extensions/gedcom/GedcomUtil.php");
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialGedcomsSetup";

function wfSpecialGedcomsSetup() {
   global $wgMessageCache, $wgSpecialPages, $wgParser;
   $wgMessageCache->addMessages( array( "gedcoms" => "Gedcoms" ) );
   $wgSpecialPages['Gedcoms'] = array('SpecialPage','Gedcoms');
}

/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class GedcomsPage extends QueryPage {
	var $status;

   public static $USER_REVIEW = 5;
   public static $ADMIN_REVIEW = 8;
   
	public static $STATUS_OPTIONS = array(
		'All' => '0',
		'Waiting for analysis' => '1',
		'Analyzing' => '2',
		'Uploader review' => '5',
		'Admin review' => '8',
		'Waiting for import' => '6',
		'Importing' => '7',
      'On hold' => '19'
	);

   public static function getInProcessCondition() {
      return "fg_status in (1,2,5,6,7,8,19)";
   }
	
	function GedcomsPage( $status = 0 ) {
		$this->status = $status;
	}

	function getName() {
		return 'Gedcoms';
	}

	function isExpensive() {
		return false;
	}
	
	function isCached() {
		return false;
	}
	
	function isSyndicated() {
		return false;
	}
	
	function getOrder() {
		return ' ORDER BY fg_id desc';
	}
	
	function getSQL() {
		return "SELECT fg_id, fg_status, fg_status_date, ft_user, fg_gedcom_filename, fg_file_size, fg_status_reason, fg_reviewer FROM familytree, familytree_gedcom".
		 		' WHERE fg_tree_id = ft_tree_id'.($this->status ? " AND fg_status={$this->status}" : ' AND '.GedcomsPage::getInProcessCondition());
	}
	
	/**
	 * Format a row, providing the timestamp, links to the page/history, size, user links, and a comment
	 *
	 * @param $skin Skin to use
	 * @param $result Result row
	 * @return string
	 */
	function formatResult( $skin, $result ) {
		global $wgLang, $wgContLang;
		
		$dm = $wgContLang->getDirMark();
		$statusMsg = @FamilyTreeUtil::$STATUS_MESSAGES[$result->fg_status];
		if (!$statusMsg) $statusMsg = "Error $result->fg_status";
		$statusDate = $wgLang->timeAndDate( $result->fg_status_date, true );
		$statusReason = ($result->fg_status_reason ? htmlspecialchars(': '.$result->fg_status_reason) : '');
		$userid = User::idFromName($result->ft_user);
		$ulink = $skin->userLink( $userid, $result->ft_user ) . $skin->userToolLinks( $userid, $result->ft_user );
		if (($result->fg_status >= FG_STATUS_READY && $result->fg_status <= FG_STATUS_ADMIN_REVIEW) ||
          $result->fg_status == FG_STATUS_HOLD) {
			$filename = '<a href="http://www.werelate.org/gedcom/index.php?gedcomId='.$result->fg_id.'" rel="nofollow">'.htmlspecialchars($result->fg_gedcom_filename).'</a>';
		}
		else {
			$filename = htmlspecialchars($result->fg_gedcom_filename);
		}
		$filesize = wfMsgHtml( 'nbytes', $wgLang->formatNum( htmlspecialchars( $result->fg_file_size ) ) );
		$reviewerid = User::idFromName($result->fg_reviewer);
		if ($result->fg_reviewer) {
			$rlink = " reviewer: ".$skin->userLink( $reviewerid, $result->fg_reviewer ) . $skin->userToolLinks( $reviewerid, $result->fg_reviewer );
		}
		else if ($result->fg_status == FG_STATUS_ADMIN_REVIEW) {
			$rlink = ' <b>needs admin review</b>';
		}
		else {
			$rlink = '';
		}
		
		return "$statusDate <i>{$statusMsg}{$statusReason}</i> {$dm}{$filename} [$filesize] $ulink {$dm}{$rlink}";
	}
	
	/**
	 * Show a namespace selection form for filtering
	 *
	 * @return string
	 */	
	function getPageHeader() {
		$thisTitle = Title::makeTitle( NS_SPECIAL, $this->getName() );
		$form  = wfOpenElement( 'form', array(
			'method' => 'get',
			'action' => $thisTitle->getLocalUrl() ) );
		$form .= wfElement( 'label', array( 'for' => 'status' ),	'Status') . ' ';
		$form .= StructuredData::addSelectToHtml(0, 'status', self::$STATUS_OPTIONS, $this->status, '', false);
		# Preserve the offset and limit
		$form .= wfElement( 'input', array(
			'type' => 'hidden',
			'name' => 'offset',
			'value' => $this->offset ) );
		$form .= wfElement( 'input', array(
			'type' => 'hidden',
			'name' => 'limit',
			'value' => $this->limit ) );
		$form .= wfElement( 'input', array(
			'type' => 'submit',
			'name' => 'submit',
			'id' => 'submit',
			'value' => wfMsg( 'allpagessubmit' ) ) );
		$form .= wfCloseElement( 'form' );
		return( $form );
	}
	
	/**
	 * Link parameters
	 *
	 * @return array
	 */
	function linkParameters() {
		return( array( 'status' => $this->status ) );
	}
	
}

/**
 * constructor
 */
function wfSpecialGedcoms($par, $specialPage) {
	global $wgRequest, $wgContLang;

	list( $limit, $offset ) = wfCheckLimits();
	$status = 0;

	if ( $par ) {
		$bits = preg_split( '/\s*,\s*/', trim( $par ) );
		foreach ( $bits as $bit ) {
			if ( 'shownav' == $bit )
				$shownavigation = true;
			if ( is_numeric( $bit ) )
				$limit = $bit;

			if ( preg_match( '/^limit=(\d+)$/', $bit, $m ) )
				$limit = intval($m[1]);
			if ( preg_match( '/^offset=(\d+)$/', $bit, $m ) )
				$offset = intval($m[1]);
			if ( preg_match( '/^status=(\d+)$/', $bit, $m ) ) {
				$status = (int)$m[1];
			}
		}
	} else {
		$status = (int)$wgRequest->getInt( 'status', 0 );
	}
	
	if ( ! isset( $shownavigation ) )
		$shownavigation = ! $specialPage->including();

	$rg = new GedcomsPage( $status );
	$rg->doQuery( $offset, $limit, $shownavigation );
}

?>
