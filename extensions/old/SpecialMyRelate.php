<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialMyRelateSetup";

function wfSpecialMyRelateSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "myrelate" => "My Relate" ) );
	$wgSpecialPages['MyRelate'] = array('SpecialPage','MyRelate');
}

/**
 * constructor
 */
function wfSpecialMyRelate($par) {

	$mr = new SpecialMyRelate();
	$mr->execute($par);
}

class SpecialMyRelate {
   var $action;
   var $name;
   var $newName;
   var $confirmed;

   public function __construct() {
      global $wgRequest;

      $this->action = $wgRequest->getVal( 'action' );
      $this->name = $wgRequest->getVal('name');
   }

   public function execute($par) {
      global $wgUser, $wgCommandLineMode, $wgLang;

      if ($wgUser->isLoggedIn()) {
      	switch ($this->action) {
      	   case 'newPage':
      	      $this->newPage();
      	      break;
      	   default:
      	      $this->show();
      	      break;
      	}
      }
   	else {
   		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
   			User::SetupSession();
   		}
   		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('MyRelate')));
   		require_once('includes/SpecialUserlogin.php');
   		$form = new LoginForm($request);
   		$form->mainLoginForm("You need to log in before using MyRelate<br/><br/>", '');
   	}
   }

   private function newPage() {
      global $wgOut, $wgUser;

      if ($this->name) {
      	if (mb_strpos($this->name, $wgUser->getName()."/") !== 0 &&
      	    mb_strpos($this->name, "User:".$wgUser->getName()."/") !== 0) {
      		$this->name = $wgUser->getName()."/".$this->name;
      	}
      	$t = Title::newFromText($this->name, NS_USER);
         $wgOut->redirect($t->getFullURL('action=edit'));
         // purge user page
		   StructuredData::purgeTitle($wgUser->getUserPage());
      }
      else {
         $wgOut->setPagetitle('Create a new user page');
         $wgOut->setArticleRelated(false);
         $wgOut->setRobotpolicy('noindex,nofollow');
			$titleObj = Title::makeTitle( NS_SPECIAL, 'MyRelate' );
			$action = $titleObj->escapeLocalURL();
			$wgOut->addHTML(<<< END
<form method='post' action="$action">
<input type="hidden" name="action" value="{$this->action}"/>
Page title: <input type="text" name="name"/>
<input type='submit' name="add"" value="Add"/>
</form>
END
			);
      }
   }

   private function show() {
   	global $wgUser, $wgOut;

		$wgOut->setPageTitle( 'My Relate' );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

		$profileRevision = Revision::newFromTitle($wgUser->getUserPage());

		$result = <<< END
<table class="fullwidth"><tr><td id="infobox">
{$this->getInfoboxText()}
</td><td id="contentbox">
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
{$this->getPRPs()}
</td></tr></table>\n
</td></tr></table>\n
END;
		$wgOut->addHTML($result);
//		$this->showProfile($profileRevision);
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
		$tip = 'Create a new user page';
		$ret .= $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'MyRelate'), 'New user page', "action=newPage", '', '', '', " title=\"$tip\"");
		return $ret;
   }

   private function getNetwork() {
      global $wgUser;

   	$skin =& $wgUser->getSkin();

   	$numChanges = 0;
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = 'select COUNT(*) from network where nw_user='.$wgUser->getID().' and nw_page_count > \'0\'';
		$res = $dbr->query( $sql, 'wfSpecialMyRelate' );
		if ($res !== false) {
			$row = $dbr->fetchRow($res);
			if ($row !== false) {
				$cnt = $row[0];
			}
			$dbr->freeResult($res);
		}

      $ret = '<div class="myrelate-header">Network</div>';

      $ret .= '<dl><dd>' . wfMsgWikiHTML('networkmsg', $cnt) . '</dd></dl>';

      $tip=wfMsgHTML('networktip');
		$ret .= $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Network'), 'View network', '', '', '', '', " title=\"$tip\"");
		return $ret;
   }

   private function getWatchlist() {
      global $wgUser;

   	$skin =& $wgUser->getSkin();

   	$numChanges = 0;
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = 'select COUNT(*) from watchlist where wl_user='.$wgUser->getID().' and wl_notificationtimestamp > \'0\'';
		$res = $dbr->query( $sql, 'wfSpecialMyRelate' );
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
		$res = $dbr->query( $sql, 'wfSpecialMyRelate' );
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

   private function showProfile($profileRevision) {
      global $wgOut, $wgParser;

      if ($profileRevision) {
//         $wgOut->addHTML('<p> </p>');
			$parserOutput = $wgParser->parse($profileRevision->getText(), $wgParser->mTitle, $wgParser->mOptions, true, false);
			$text = $parserOutput->getText();
         wrFixupLayout($text);
		   $wgOut->addHTML($text);
      }
  	}
}
?>
