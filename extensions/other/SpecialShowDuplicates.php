<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialShowDuplicatesSetup";

function wfSpecialShowDuplicatesSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "showduplicates" => "Show duplicates" ) );
	$wgSpecialPages['ShowDuplicates'] = array('SpecialPage','ShowDuplicates');
}

/**
 * constructor
 */
function wfSpecialShowDuplicates($par) {
	global $wgOut, $wgScriptPath, $wgUser, $wrSidebarHtml;
	
	$duplicates = new ShowDuplicates();
	$duplicates->readQueryParms($par);
	$sideText = '<p><b>Connect your tree to others</b> by clicking on a person or family in the list to compare it to others that are likely to be duplicates.</p>'.
					'<p>Once you find possible duplicates you can compare them in more detail and then merge them together.</p>'.
					'<p>When you\'ve finished, click on <a href="/wiki/Special:Network">Network</a> in the <i>MyRelate</i> menu to find out which users you are now related to!</p>'.
					'<p>(<a href="/wiki/Help:Merging_pages">more help</a>)</p>';
	$wgOut->setPageTitle($duplicates->getTitle());
   $skin = $wgUser->getSkin();
   $wrSidebarHtml = $skin->makeKnownLink('Help:Merging pages', "Help", '', '', '', 'class="popup"');
	$results = $duplicates->getResults();
	if ($results) {
		$wgOut->addHTML($results);
	}
}

class ShowDuplicates {
	var $userName;
	
   public function __construct() {
   	$this->userName = '';
   }

   public function readQueryParms($par) {
   	global $wgRequest, $wgUser;
   	
   	if ($par) {
   		$this->userName = $par;
   	}
   	else if ($wgRequest->getVal('user')) {
   		$this->userName = $wgRequest->getVal('user');
   	}
   	else if ($wgUser->isLoggedIn()) {
   		$this->userName = $wgUser->getName();
   	}
   }
   
	public function getTitle() {
		return wfMsg('ShowDuplicatesTitle', $this->userName);
	}
   
   public function getResults() {
   	global $wgUser, $wgCommandLineMode, $wgLang;
   	

		if ($this->userName) {
			$results = '<p>Changes made in the past 24 hours are not reflected</p>';
			
	   	$u = User::newFromName($this->userName);
			if (!$u || !$u->getID()) {
				$results .= '<font color="red">User not found</font>';
			}
			else {
		   	$sk = $wgUser->getSkin();
	
		   	
		      // issue db query to get the family trees, sort afterward
				$dbr =& wfGetDB( DB_SLAVE );
				$res = $dbr->select(array('watchlist','duplicates'), array('dp_namespace', 'dp_title', 'dp_match_titles'), 
											array( 'wl_user' => $u->getID(), 'wl_namespace=dp_namespace', 'wl_title=dp_title'));
				$compareTitle = Title::makeTitle(NS_SPECIAL, 'Compare');
				$searchTitle = Title::makeTitle(NS_SPECIAL, 'Search');
				$found = false;
				while ($row = $dbr->fetchObject($res)) {
					if (!$found) {
				   	$results .= '<ul>';
				   	$found = true;
					}
					$title = Title::makeTitle($row->dp_namespace, $row->dp_title);
					$namespace = ($row->dp_namespace == NS_PERSON ? 'Person' : 'Family');
					if ($row->dp_match_titles) {
						$baseTitle = $compareTitle;
						$query='ns=' . $namespace . '&compare=' . urlencode($row->dp_title.'|'.$row->dp_match_titles);
					}
					else {
						$baseTitle = $searchTitle;
						$query='match=on&ns=' . $namespace . '&pagetitle=' . urlencode($row->dp_title);
					}
					$results .= '<li>'.$sk->makeKnownLinkObj($baseTitle, htmlspecialchars($title->getPrefixedText()), $query).
											' &nbsp; (<i>'.$sk->makeKnownLinkObj($baseTitle, 'open in new window', $query, '', '', 'target="_blank"').'</i>)</li>';
				}
				$dbr->freeResult($res);
				if ($found) {
					$results .= '</ul>';
				}
				else {
					$results .= '<p>No possible duplicates found.</p>';
				}
			}
			return $results;
		}
   	else {
   		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
   			User::SetupSession();
   		}
   		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('ShowDuplicates')));
   		require_once('includes/SpecialUserlogin.php');
   		$form = new LoginForm($request);
   		$form->mainLoginForm("You need to log in to view your possible duplicates<br/><br/>", '');
   		return '';
   	}
   }
}
?>