<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialDashboardSetup";

function wfSpecialDashboardSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "dashboard" => "Dashboard" ) );
	$wgSpecialPages['Dashboard'] = array('SpecialPage','Dashboard');
}

/**
 * constructor
 */
function wfSpecialDashboard($par) {

	$mr = new SpecialDashboard();
	$mr->execute($par);
}

class SpecialDashboard {

	public function execute($par) {
      global $wgUser, $wgCommandLineMode, $wgLang;

      if ($wgUser->isLoggedIn()) {
  	      $this->show();
      }
   	else {
   		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
   			User::SetupSession();
   		}
   		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('Dashboard')));
   		require_once('includes/SpecialUserlogin.php');
   		$form = new LoginForm($request);
   		$form->mainLoginForm("You need to log in to view your dashboard<br/><br/>", '');
   	}
   }

   private function show() {
   	global $wgUser, $wgOut;

		$wgOut->setPageTitle( 'Dashboard' );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

		$profileRevision = Revision::newFromTitle($wgUser->getUserPage());

		$result = <<< END
<table id="myrelate-table" cellspacing="10">
<tr valign="top"><td colspan="2" width="32%" class="myrelate-cell">
{$this->getNetwork()}
</td><td colspan="2" width="32%" class="myrelate-cell">
{$this->getWatchlist()}
</td><td colspan="2" width="32%" class="myrelate-cell">
{$this->getContribs()}
</td></tr>
<tr valign="top"><td colspan="3" width="48%" class="myrelate-cell">
{$this->getProfMsgs($profileRevision)}
</td><td colspan="3" width="48%" class="myrelate-cell">
{$this->getTrees()}
</td></tr>
<tr valign="top"><td colspan="6" width="99%" class="myrelate-cell">
{$this->getPRPs()}
</td></tr></table>
END;
		$wgOut->addHTML($result);
   }
   
   private function getInfoboxText() {
   	return '<table><tr><td>'.wfMsgWikiHtml('myrelate-infoboxtext').'</td></tr></table><p> </p>';
   }

   private function getProfMsgs($profileRevision) {
      global $wgUser, $wgLang;

   	$skin =& $wgUser->getSkin();

      $ret = '<div class="myrelate-header">Profile &amp; Messages</div>';
      if ($profileRevision) {
      	$tip = wfMsgHTML('viewprofiletip');
	      $ret .= $skin->makeKnownLinkObj($wgUser->getUserPage(), 'View your profile', '', '', '', '', " title=\"$tip\"") . ' (&nbsp;';
      }
      $tip=wfMsgHTML('editprofiletip');
      $ret .= $skin->makeKnownLinkObj($wgUser->getUserPage(), $profileRevision ? 'edit' : '<b>Create your profile</b>', "action=edit", '', '', '', " title=\"$tip\"");
      if ($profileRevision) {
      	$ret .= '&nbsp;)';
      }
      $ret .= '<br/>';
      $tip=wfMsgHTML('checkmessagestip');
		$msgsRevision = Revision::newFromTitle($wgUser->getTalkPage());
		if ($msgsRevision) {
		   $msgsText = '<dl><dd>(last message: ' . $wgLang->timeanddate(wfTimestamp(TS_MW, $msgsRevision->getTimestamp()), true) . ' by ' .
		                $skin->makeKnownLinkObj(Title::makeTitle(NS_USER, $msgsRevision->getUserText()), $msgsRevision->getUserText()) . ')</dl></dd>';
		}
		else {
		   $msgsText = '<br/>';
		}
      $ret .= $skin->makeKnownLinkObj($wgUser->getTalkPage(), "Read messages", '', '', '', '', " title=\"$tip\"") . $msgsText;
      $tip=wfMsgHTML('preferencestip');
      $ret .= $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Preferences'), 'Edit preferences', '', '', '', '', " title=\"$tip\"");
      return $ret;
   }

   private function getPRPs() {
      global $wgUser;

   	$skin =& $wgUser->getSkin();
      $ret = '<div class="myrelate-header">User Pages</div>';

      $dbr =& wfGetDB( DB_SLAVE );
		$sql = 'select page_title from page where page_namespace='.NS_USER .
		          ' and page_title like '.$dbr->addQuotes($wgUser->getUserPage()->getDBkey().'/%');
		$res = $dbr->query( $sql, 'getPRPs' );
		if ($res !== false) {
		   $found = false;
		   while ($row = $dbr->fetchObject($res)) {
		      $t = Title::makeTitle(NS_USER, $row->page_title);
		      if (!$found) {
		         $ret .= '<ul>';
		         $found = true;
		      }
		      $ret .= '<li>' . $skin->makeKnownLinkObj($t, $t->getText()) . '</li>';
		   }
			$dbr->freeResult($res);
		   if ($found) {
		      $ret .= '</ul>';
		   }
		}
		$tip = 'Add a new user page';
		$ret .= $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'AddPage'), 'Add user page', "namespace=".NS_USER, '', '', '', " title=\"$tip\"");
		return $ret;
   }

   private function getNetwork() {
      global $wgUser;

   	$skin =& $wgUser->getSkin();

      $ret = '<div class="myrelate-header">Network</div>';

      $ret .= '<dl><dd>' . wfMsgWikiHTML('networkmsg') . '</dd></dl>';

      $tip=wfMsgHTML('networktip');
		$ret .= $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Network'), 'View network', '', '', '', '', " title=\"$tip\"");
		return $ret;
   }

   private function getWatchlist() {
      global $wgUser;

   	$skin =& $wgUser->getSkin();

		$dbr =& wfGetDB( DB_SLAVE );
		$sql = 'select COUNT(*) from watchlist where wl_user='.$wgUser->getID().' and wl_notificationtimestamp > \'0\'';
		$res = $dbr->query( $sql, 'wfSpecialDashboard' );
		if ($res !== false) {
			$row = $dbr->fetchRow($res);
			if ($row !== false) {
				$cnt = $row[0];
			}
			$dbr->freeResult($res);
		}

      $ret = '<div class="myrelate-header">Watchlist</div>';

      $ret .= '<dl><dd>' . wfMsgWikiHTML('watchlistchanged', $cnt) . '</dd></dl>';

      $tip=wfMsgHTML('watchlisttip');
		$ret .= $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Watchlist'), 'View watchlist', '', '', '', '', " title=\"$tip\"");
		return $ret;
   }

   private function getContribs() {
      global $wgUser;

   	$skin =& $wgUser->getSkin();

   	$numContribs = 0;
   	// get timestamp as of 90 days ago
   	$numDays = 90;
      $timestamp = wfTimestamp( TS_MW, time() - ($numDays * 24 * 60 * 60));
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = 'select COUNT(*) from revision where rev_user='.$wgUser->getID().' and rev_timestamp >= ' . $dbr->addQuotes($timestamp);
		$res = $dbr->query( $sql, 'wfSpecialDashboard' );
		if ($res !== false) {
			$row = $dbr->fetchRow($res);
			if ($row !== false) {
				$numContribs = $row[0];
			}
			$dbr->freeResult($res);
		}

      $ret = '<div class="myrelate-header">Contributions</div>';

      $ret .= '<dl><dd>' . wfMsgWikiHTML('NumberOfContributions', $numContribs, $numDays) . '</dd></dl>';

      $tip=wfMsgHTML('contributionstip');
		$ret .= $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Contributions/' . $wgUser->getName()), 'View contributions', '', '', '', '', " title=\"$tip\"");
		return $ret;
   }
   
   private function getTrees() {
      global $wgUser;

   	$skin =& $wgUser->getSkin();

		$dbr =& wfGetDB( DB_SLAVE );
		$gedcoms = array();
   	$rows = $dbr->select(array('familytree_gedcom', 'familytree'), 
   								array('fg_id', 'fg_tree_id', 'fg_gedcom_filename'), 
   								array('fg_tree_id = ft_tree_id', 'ft_user' => $wgUser->getName(), 'fg_status' => FG_STATUS_PHASE2));
     	while ($row = $dbr->fetchObject($rows)) {
   		$gedcoms[] = array('fg_id' => $row->fg_id, 'fg_tree_id' => $row->fg_tree_id, 'fg_gedcom_filename' => $row->fg_gedcom_filename);
     	}
		$dbr->freeResult($rows);
     	
   	$ret = '<div class="myrelate-header">Tree(s)</div><ul>';
		$familyTrees = FamilyTreeUtil::getFamilyTrees($wgUser->getName(), true, $dbr);  // changed Sep 2020 by Janet Bjorndahl
      foreach($familyTrees as $familyTree) {
      	$ret .= '<li>' . htmlspecialchars($familyTree['name']) . ' (&nbsp;' .
					$skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Search'), 'search', 'k='.urlencode('+Tree:"'.$wgUser->getName().'/'.$familyTree['name'].'"')) .  // link renamed Dec 2020
					'&nbsp;)';
//					'<a href="/fte/index.php?userName='. urlencode($wgUser->getName()) . '&treeName=' . urlencode($familyTree['name']) . '">launch FTE</a>&nbsp;)';   removed Dec 2020
        // Allow user to explore a tree only if the tree has at least one page, because exploring starts with displaying a page (added Sep 2020 by Janet Bjorndahl)     
        if ($familyTree['count'] > 0) { 
          $explore = $skin->makeKnownLinkObj(SpecialTrees::getExploreFirstTitle($wgUser->getName(), $familyTree['name']), 'explore', 
                     wfArrayToCGI(array('mode' => 'explore', 'user' => $wgUser->getName(), 'tree' => $familyTree['name'], 'liststart' => '0', 'listrows' => '20', 'listns' => '')));
          $ret .= " (&nbsp;$explore&nbsp;)";
          }
			$found = false;
			foreach ($gedcoms as $gedcom) {
				if ($gedcom['fg_tree_id'] == $familyTree['id']) {
					if (!$found) {
						$ret .= '<ul>';
					}
					$found = true;
					$ret .= '<li>'.htmlspecialchars($gedcom['fg_gedcom_filename']).' <a href="/gedcom/index.php?gedcomId='.$gedcom['fg_id'].'" rel="nofollow">Waiting for review</a></li>';

				}
			}
			if ($found) {
				$ret .= '</ul>';
			}
			$ret .= '</li>';
      }
      $tip = 'Create, rename, delete, and email trees';
		$ret .= '</ul>'.$skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Trees'), 'Manage trees', '', '', '', '', " title=\"$tip\"");

      return $ret;
   }
}
?>
