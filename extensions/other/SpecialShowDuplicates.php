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
	$sideText = wfMsg('connecttreeothers');
	$wgOut->setPageTitle($duplicates->getTitle());
   $skin = $wgUser->getSkin();
   $wrSidebarHtml = $skin->makeKnownLink('Help:Merging pages', wfMsg('help'), '', '', '', 'class="popup"');
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
			$results = wfMsg('pastnotreflected');
			
	   	$u = User::newFromName($this->userName);
			if (!$u || !$u->getID()) {
				$results .= '<font color="red">'.wfMsg('usernotfound').'</font>';
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
											' &nbsp; (<i>'.$sk->makeKnownLinkObj($baseTitle, wfMsg('opennewwindow'), $query, '', '', 'target="_blank"').'</i>)</li>';
				}
				$dbr->freeResult($res);
				if ($found) {
					$results .= '</ul>';
				}
				else {
					$results .= wfMsg('noduplicatesfound');
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
   		$form->mainLoginForm(wfMsg('logonviewduplicates'), '');
   		return '';
   	}
   }
}
?>