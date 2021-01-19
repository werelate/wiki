<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

// Created Jan 2021 by Janet Bjorndahl

require_once("$IP/includes/Title.php");
require_once("$IP/LocalSettings.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialRequestDeleteSetup";

function wfSpecialRequestDeleteSetup() {
  global $wgMessageCache, $wgSpecialPages, $wgParser;
  $wgMessageCache->addMessages( array( "requestdelete" => "Request Delete" ) );
  $wgSpecialPages['RequestDelete'] = array('SpecialPage','RequestDelete');
}

function wfSpecialRequestDelete() {
  global $wgOut, $wgRequest, $wgUser, $wgCommandLineMode, $wgLang;

  if (!$wgUser->isLoggedIn()) {
		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
			User::SetupSession();
		}
		$request = new FauxRequest(array('returnto' => 'Special:RequestDelete'));
		require_once('includes/SpecialUserlogin.php');
		$form = new LoginForm($request);
		$form->mainLoginForm("You need to log in to request delete<br/><br/>", '');
		return;
	}
	if( $wgUser->isBlocked() ) {
		$wgOut->blockedPage();
		return;
	}
	if( wfReadOnly() ) {
		$wgOut->readOnlyPage();
		return;
	}

  $pageTitle = $wgRequest->getVal('pagetitle');
  $deleteReason = $wgRequest->getVal('reason');

  // If the user entered a reason, pass the reason to the edit page. Otherwise, (re)display the form.
  if ( $deleteReason ) {
    $title = Title::newFromText($pageTitle);
    $wgOut->redirect($title->getFullURL() . '?action=edit&deletereason=' . rawurlencode(htmlspecialchars($deleteReason)));
  }
  else {
    $wgOut->setPagetitle("Request Delete");
    $wgOut->addWikiText( wfMsg("RequestDeleteMsg") );
	  $formHtml = '<form method="post" action="/wiki/Special:RequestDelete">';
    $formHtml .= '<label for="reason">Reason page should be deleted (mandatory) </label><input class="input_long" type="text" id="reason" name="reason"/>';
    $formHtml .= ' <input type="submit" name="submit" value="Go"/>';
    $formHtml .= ' <input type="hidden" name="pagetitle" value="' . $pageTitle . '"/>';
    $formHtml .= '</form>';
    $wgOut->addHTML($formHtml);
  }
}
?>
