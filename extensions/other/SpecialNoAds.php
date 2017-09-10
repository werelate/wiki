<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

if (!defined('MEDIAWIKI')) die();

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialNoAdsSetup";

$wgAvailableRights[] = 'noads';
$wgGroupPermissions['bureaucrat']['noads'] = true;

function wfSpecialNoAdsSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "noads" => "NoAds" ) );
	$wgSpecialPages['NoAds'] = array('SpecialPage','NoAds');
}

/**
 * constructor
 */
function wfSpecialNoAds($par) {

	$mr = new SpecialNoAds();
	$mr->execute($par);
}

class SpecialNoAds {

	public function execute($par) {
      global $wgUser, $wgCommandLineMode, $wgLang, $wgOut, $wrAdminUserName;

		if( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

		if ($wgUser->isLoggedIn() && $wgUser->getName() == $wrAdminUserName) {
         $pieces = explode('/', $par);
         if (count($pieces) > 1 && strlen($pieces[1]) == 8) {
            $pieces[1] .= '000000';
         }
   		$user = User::newFromName($pieces[0]);
			$msg = '';
			if (count($pieces) == 2 && $user->getID() > 0 && strlen($pieces[1]) == 14) {
				$user->setOption('wrnoads', $pieces[1]);
				$user->saveSettings();
			}
			else {
				$msg = $pieces[0].' not found or date incorrect';
			}
 	      $this->show($msg);
      }
   	else {
   		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
   			User::SetupSession();
   		}
   		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('NoAds')));
   		$form = new LoginForm($request);
   		$form->mainLoginForm("You need to log in<br/><br/>", '');
   	}
   }

   private function show($msg) {
   	global $wgOut, $wgPasswordSender;

		$wgOut->setPageTitle( 'NoAds' );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

		if ($msg) {
			$wgOut->addHtml('<font color="red">'.$msg.'</font>');
		}
		else {
			$result = <<< END
<h2>User will not be shown ads</h2>
END;
			$wgOut->addHTML($result);
		}
   }
}
?>
