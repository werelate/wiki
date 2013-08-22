<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialUnsubscribeSetup";

function wfSpecialUnsubscribeSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "unsubscribe" => "Unsubscribe" ) );
	$wgSpecialPages['Unsubscribe'] = array('SpecialPage','Unsubscribe');
}

/**
 * constructor
 */
function wfSpecialUnsubscribe($par) {

	$mr = new SpecialUnsubscribe();
	$mr->execute($par);
}

class SpecialUnsubscribe {

	public function execute($par) {
      global $wgUser, $wgCommandLineMode, $wgLang, $wgOut;

		if( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

		if ($wgUser->isLoggedIn()) {
			if ($wgUser->getName() == 'Dallan') {
				$user = User::newFromName($par);
			}
			else {
				$user = $wgUser;
			}
			$msg = '';
			if ($user->getID() > 0) {
				$user->setOption('enotifwatchlistpages', 0);
				$user->setOption('enotifusertalkpages', 0);
				$user->setOption('enotifminoredits', 0);
				$user->setOption('disablemail', 1);
				$user->saveSettings();
			}
			else {
				$msg = $user->getName().' not found';
			}
 	      $this->show($msg);
      }
   	else {
   		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
   			User::SetupSession();
   		}
   		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('Unsubscribe')));
   		require_once('includes/SpecialUserlogin.php');
   		$form = new LoginForm($request);
   		$form->mainLoginForm("You need to log in to unsubscribe<br/><br/>", '');
   	}
   }

   private function show($msg) {
   	global $wgOut;

		$wgOut->setPageTitle( 'Unsubscribe' );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

		if ($msg) {
			$wgOut->addHtml('<font color="red">'.$msg.'</font>');
		}
		else {
			$result = <<< END
<h2>You have been Unsubscribed</h2>
<p>You will no longer receive email notifications of changes to your Talk page or the pages on your watchlist, and other WeRelate users will not be able to send you email.</p>
<p>You can still see which pages have changed since you last visited them by clicking on <b>MyRelate</b> in the blue menu bar, then on <b>Watchlist</b>.</p>
<p>If you want to turn email back on, you can do so by clicking on <b>MyRelate</b>, then on <b>Preferences</b>, and checking the boxes for 
<ul>
<li>E-mail me when a page I'm watching is changed
<li>E-mail me when my user talk page is changed
<li>Enable e-mail from other users
</ul></p>
<p>If you would like to remove your account at WeRelate, please send email to <a href="mailto:dallan@werelate.org">dallan@werelate.org</a>.</p>
END;
			$wgOut->addHTML($result);
		}
   }
}
?>
