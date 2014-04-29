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
      global $wgUser, $wgCommandLineMode, $wgLang, $wgOut, $wrAdminUserName;

		if( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

		if ($wgUser->isLoggedIn()) {
			if ($wgUser->getName() == $wrAdminUserName) {
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
   	global $wgOut, $wgPasswordSender;

		$wgOut->setPageTitle( 'Unsubscribe' );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

		if ($msg) {
			$wgOut->addHtml('<font color="red">'.$msg.'</font>');
		}
		else {
            $unsubscribedtext = wfMsg('unsubscribedemailtext', htmlspecialchars($wgPasswordSender));
			$result = <<< END
            $unsubscribedtext
END;
			$wgOut->addHTML($result);
		}
   }
}
?>
