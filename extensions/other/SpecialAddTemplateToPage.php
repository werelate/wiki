<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

// Created Oct 2025 by Janet Bjorndahl for WeRelate

//require_once("$IP/includes/Title.php");
//require_once("$IP/LocalSettings.php");
require_once("$IP/extensions/other/MiscAjaxFunctions.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialAddTemplateToPageSetup";

function wfSpecialAddTemplateToPageSetup() {
  global $wgMessageCache, $wgSpecialPages;
  $wgSpecialPages['AddTemplateToPage'] = array('SpecialPage','AddTemplateToPage');
}

function wfSpecialAddTemplateToPage() {
  global $wgOut, $wgRequest, $wgUser, $wgCommandLineMode, $wgLang;

  if (!$wgUser->isLoggedIn()) {
		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
			User::SetupSession();
		}
		$request = new FauxRequest(array('returnto' => 'Special:AddTemplateToPage'));
		require_once('includes/SpecialUserlogin.php');
		$form = new LoginForm($request);
		$form->mainLoginForm("You need to log in to verify issues.<br/><br/>", '');
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

  $templateType = $wgRequest->getVal('type');
  $namespace = $wgRequest->getVal('ns');
  $titleString = $wgRequest->getVal('titlestring');
  $template = $wgRequest->getVal('template');
  $desc = $wgRequest->getVal('desc');
  $comments = rawurlencode(htmlspecialchars($wgRequest->getVal('comments')));
  
  // If the user entered comments, add the template. Otherwise, (re)display the form.
  if ( $comments ) {
    $title = Title::makeTitle($namespace, $titleString);
    $pageId = $title->getArticleID();
    if ( $templateType == "verify" ) {
      markAsVerified($title, $pageId, $template, $desc, $comments);
    }
    $wgOut->redirect($title->getFullURL());
  }
  else {
    $comments = "See sources/notes.";  // default comments
    $wgOut->setPagetitle("Add Template To Talk Page");
    $wgOut->addWikiText( wfMsg("Add" . ucfirst($templateType) . "TemplateMsg") );
	  $formHtml = '<form method="post" action="/wiki/Special:AddTemplateToPage">';
    $formHtml .= '<label for="comments">Enter comments for the Talk page regarding "' . $desc . '". You may accept the default comments or add your own.<br><br>' .
          'Comments </label><input class="input_long" type="text" id="comments" name="comments" value="' . $comments . '"/>';
    $formHtml .= ' <input type="submit" name="submit" value="Go"/>';
    $formHtml .= ' <input type="hidden" name="type" value="' . $templateType . '"/>';
    $formHtml .= ' <input type="hidden" name="ns" value="' . $namespace . '"/>';
    $formHtml .= ' <input type="hidden" name="titlestring" value="' . Sanitizer::safeEncodeAttribute($titleString) . '"/>';
    $formHtml .= ' <input type="hidden" name="template" value="' . $template . '"/>';
    $formHtml .= ' <input type="hidden" name="desc" value="' . $desc . '"/>';
    $formHtml .= '</form>';
    $wgOut->addHTML($formHtml);
  }
}
?>
