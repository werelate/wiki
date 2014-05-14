<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialNetworkSetup";

function wfSpecialNetworkSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "network" => "Network" ) );
	$wgSpecialPages['Network'] = array('SpecialPage','Network');
}

/**
 * constructor
 */
function wfSpecialNetwork($par) {

	$mr = new SpecialNetwork($par);
	$mr->execute();
}

function wrCmpNetwork($a, $b) {
	if ($a['nw_page_count'] > $b['nw_page_count']) {
		return -1;
	}
	else if ($a['nw_page_count'] < $b['nw_page_count']) {
		return 1;
	}
	return strcmp($a['user_name'], $b['user_name']);
}

class SpecialNetwork {
	var $maxPerPage=960;
	var $username;
	var $otherUsername;
	var $from;
	var $user;
	
   public function __construct($par) {
   	global $wgRequest, $wgUser;
   	
   	$this->username = $par;
   	if (!$this->username) {
			$this->username = $wgRequest->getVal('user');
   	}
   	if (!$this->username) {
   		$this->username = $wgUser->getName();
   	}
   	if ($this->username) {
		   $this->user = User::newFromName($this->username);
   	}
   	else {
   		$this->user = null;
   	}
		$this->otherUsername = $wgRequest->getVal('relateduser');
		$this->from = $wgRequest->getVal('from');
   }

   public function execute() {
      global $wgCommandLineMode, $wgLang, $wgOut;

      if ($this->username) {
		   if (!$this->user || !$this->user->getID()) {
		   	$wgOut->addHtml('<p><font color="red">User not found</font></p>');
		   	return;
		   }
	   
      	if ($this->otherUsername) {
      		$this->showUserNetwork();
      	}
      	else {
      		$this->showFullNetwork();
      	}
      }
   	else {
   		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
   			User::SetupSession();
   		}
   		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('Network')));
   		require_once('includes/SpecialUserlogin.php');
   		$form = new LoginForm($request);
   		$form->mainLoginForm("You need to log in to view your network<br/><br/>", '');
   	}
   }

   private function showFullNetwork() {
	   global $wgUser, $wgOut, $wgMemc;

		$wgOut->setPageTitle( wfMsg('Networkfor', $this->username));
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

		$wgOut->addHtml('<p>');
		$wgOut->addWikiText(wfMsg('networkcontent', $this->username));
		$wgOut->addHtml('</p>');
		
	   $sk = $wgUser->getSkin();

	   $users = array();
		$cnt = 0;
      
      // look up in cache first
      $cacheKey = 'network:'.$this->user->getID();
      $network = $wgMemc->get($cacheKey);
      if ($network) {
      	// add users, set cnt
      	$network = explode('|', $network);
      	foreach($network as $line) {
      		$fields = explode('/', $line);
      		$users[] = array('nw_rel_user' => $fields[0], 'nw_page_count' => $fields[1], 'user_name' => $fields[2]);
      		$cnt++;
      	}
      }
      else {
	      // issue db query to get the related users, sort afterward
	      
			$dbr =& wfGetDB( DB_SLAVE );
			$sql = 'SELECT nw_rel_user, nw_page_count, user_name FROM user, '.
						'(SELECT w2.wl_user AS nw_rel_user, count(*) AS nw_page_count FROM watchlist w1, watchlist w2 where w1.wl_user = '.$this->user->getID().
							' AND w1.wl_namespace IN (108,110) AND w1.wl_namespace = w2.wl_namespace AND w1.wl_title = w2.wl_title AND w2.wl_user <> '.$this->user->getID().
							' GROUP BY w2.wl_user) AS tmp WHERE nw_rel_user = user_id';
			$res = $dbr->query($sql, 'ShowUserNetwork');
			$network = array();
			while ($row = $dbr->fetchObject($res)) {
				$users[] = array('nw_rel_user' => $row->nw_rel_user, 'nw_page_count' => $row->nw_page_count, 'user_name' => $row->user_name);
				$cnt++;
				$network[] = "{$row->nw_rel_user}/{$row->nw_page_count}/{$row->user_name}";
			}
			$dbr->freeResult($res);
			$wgMemc->set($cacheKey, join('|', $network), 1800);
      }
		usort($users, 'wrCmpNetwork');

		$title = Title::makeTitle(NS_SPECIAL, 'Network');
		$wgOut->addHtml('<table cellpadding="0" cellspacing="0" class="network-table"><tr><th>User</th><th>Watched Pages in Common</th></tr>');
		foreach ($users as $user) {
			$wgOut->addHtml('<tr><td>' . $sk->userLink($user['nw_rel_user'], $user['user_name']) . $sk->userToolLinks($user['nw_rel_user'], $user['user_name']) .
			                '</td><td><center>' . $sk->makeKnownLinkObj($title, $user['nw_page_count'], 'user='.urlencode($this->username).'&relateduser='.urlencode($user['user_name'])) .
			                '</center></td></tr>');
		}
		$wgOut->addHtml('</table>');
		$wgOut->addHtml("<p><b>Total: $cnt</b></p>");
   }

	private function getForm ($from = '') {
   	global $wgScript;
   	$t = Title::makeTitle( NS_SPECIAL, 'Network');

   	$frombox = "<input type='text' size='50' name='from' id='nsfrom' value=\""
   	            . htmlspecialchars ( $from ) . '"/>';
   	$submitbutton = '<input type="submit" value="' . wfMsgHtml( 'allpagessubmit' ) . '" />';

   	$out = "<form method='get' action='{$wgScript}'>"
   	  . '<input type="hidden" name="user" value="'.htmlspecialchars($this->username).'" />'
   	  . '<input type="hidden" name="relateduser" value="'.htmlspecialchars($this->otherUsername).'" />'
   	  . '<input type="hidden" name="title" value="'.htmlspecialchars($t->getPrefixedText()).'" />'
   	  . "
   <table>
   	<tr>
   		<td align='right'>Display pages starting at</td>
   		<td align='left'><label for='nsfrom'>$frombox</label></td>
   	</tr>
   </table>
   ";
   	$out .= '</form></div>';
   		return $out;
   }

   private function showUserNetwork() {
	   global $wgUser, $wgOut, $wgContLang;

		$wgOut->setPageTitle( wfMsg('networkusercontent', $this->username, $this->otherUsername) );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

	   $sk = $wgUser->getSkin();

	   $wgOut->addHtml('<p>&nbsp;&nbsp;&nbsp;&lt; ' . $sk->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'boarNetwork'), ' Show full network') . '</p>');

	   $title = null;
	   if ($this->from) {
	     $title = Title::newFromText($this->from);
	   }

      // issue db query to get the family trees
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = 'SELECT w1.wl_namespace AS namespace, w1.wl_title AS title FROM watchlist AS w1, watchlist AS w2, user WHERE user_name='.$dbr->addQuotes($this->otherUsername).
					' AND w1.wl_user='.$this->user->getID().
					' AND w2.wl_user=user_id AND w1.wl_namespace in (108,110) AND w1.wl_namespace=w2.wl_namespace AND w1.wl_title=w2.wl_title';
		if ($title) {
		   $sql .= ' AND ((w1.wl_namespace = ' . $dbr->addQuotes($title->getNamespace()) . ' AND w1.wl_title >= ' . $dbr->addQuotes($title->getDBkey()) . ')'
		              . ' OR w1.wl_namespace > ' . $dbr->addQuotes($title->getNamespace()) . ')';
		}
		$sql .= ' ORDER BY w1.wl_namespace, w1.wl_title LIMIT '.($this->maxPerPage + 1);
		$res = $dbr->query($sql, 'ShowUserNetwork');

		### FIXME: side link to previous

		$n = 0;
		$out = '<table style="background: inherit;" border="0" width="100%">';

		while( ($n < $this->maxPerPage) && ($s = $dbr->fetchObject( $res )) ) {
		   $title = Title::makeTitle($s->namespace, $s->title);
	      $link = $sk->makeKnownLinkObj($title, htmlspecialchars($title->getPrefixedText()));
			if( $n % 3 == 0 ) {
				$out .= '<tr>';
			}
			$out .= "<td>$link</td>";
			$n++;
			if( $n % 3 == 0 ) {
				$out .= '</tr>';
			}
		}
		if( ($n % 3) != 0 ) {
			$out .= '</tr>';
		}
		$out .= '</table>';

		$form = $this->getForm($this->from);
		$users = 'user=' . wfUrlEncode($this->username) . 'relateduser=' . wfUrlEncode($this->otherUsername);
		$out2 = '<table style="background: inherit;" width="100%" cellpadding="0" cellspacing="0" border="0">';
		$out2 .= '<tr valign="top"><td align="left">' . $form;
		if ( isset($dbr) && $dbr && ($n == $this->maxPerPage) && ($s = $dbr->fetchObject( $res )) ) {
		   $title = Title::makeTitle($s->namespace, $s->title);
			$out2 .= '</td><td align="right" style="font-size: smaller; margin-bottom: 1em;">' .
				$sk->makeKnownLink( $wgContLang->specialPage("Network"), wfMsgHtml('nextpage', $title->getPrefixedText()),
			   $users . '&from=' . wfUrlEncode($title->getPrefixedText()));
		}
		$out2 .= "</td></tr></table><hr />";

   	$wgOut->addHtml( $out2 . $out );
		$dbr->freeResult($res);
   }
}
?>
