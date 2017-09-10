<?php

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialNamesLogSetup";

function wfSpecialNamesLogSetup() {
	global $wgMessageCache, $wgSpecialPages;
	
	$wgMessageCache->addMessages( array( "nameslog" => "Names Log" ) );
	$wgSpecialPages['NamesLog'] = array('SpecialPage','NamesLog');
}

/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class NamesLog extends QueryPage {

	function NamesLog() {
	}

	function getName() {
		return 'NamesLog';
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
		return ' ORDER BY log_id desc';
	}

	function getSQL() {
		return "SELECT log_timestamp, log_user_text, log_name, log_type, log_adds, log_deletes, log_comment from names_log where log_flags = 0";
	}

   function getPageHeader() {
      return wfMsgWikiHtml('nameslogend');
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

		$statusDate = $wgLang->timeAndDate( $result->log_timestamp, true );
		$userid = User::idFromName($result->log_user_text);
      $type = ($result->log_type == 'surname' ? 'S' : 'G');
		$ulink = $skin->userLink( $userid, $result->log_user_text ) . $skin->userToolLinks( $userid, $result->log_user_text );
      $name = $skin->makeKnownLinkObj( Title::makeTitle( NS_SPECIAL, 'Names' ), htmlspecialchars($result->log_name),
                                       'name='.urlencode($result->log_name).'&type='.urlencode(strtolower($type)));
      $comment = $result->log_comment ? ' <em>('.htmlspecialchars($result->log_comment).')</em>' : '';
      $adds = '';
      $deletes = '';
      if ($result->log_adds) {
         $adds = '<dd>+ <span class="wr-nameslog-adds">'.htmlspecialchars($result->log_adds).'</span></dd>';
      }
      if ($result->log_deletes) {
         $deletes = '<dd>- <span class="wr-nameslog-deletes">'.htmlspecialchars($result->log_deletes).'</span></dd>';
      }
		return "<dl class=\"wr-nameslog\"><dt>$type $name $statusDate {$ulink} $comment</dt>$deletes$adds</dl>";
	}
}

/**
 * constructor
 */
function wfSpecialNamesLog($par, $specialPage) {

	list( $limit, $offset ) = wfCheckLimits();

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
		}
	}

	if ( ! isset( $shownavigation ) )
		$shownavigation = ! $specialPage->including();

	$rg = new NamesLog();
	$rg->doQuery( $offset, $limit, $shownavigation );
}
?>
