<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

/* Written Dec 2020 by Janet Bjorndahl to replace FTE flash functionality */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialCopyTreeSetup";

function wfSpecialCopyTreeSetup() {
  global $wgMessageCache, $wgSpecialPages, $wgParser;
  $wgMessageCache->addMessages( array( "copytree" => "Copy Tree" ) );
  $wgSpecialPages['CopyTree'] = array('SpecialPage','CopyTree');
}

function wfSpecialCopyTree() {
  global $wgOut, $wgRequest, $wgUser, $wgCommandLineMode, $wgLang;

  // Get user and name of tree to copy
  $user = $wgRequest->getVal('user');
  $name = $wgRequest->getVal('name');

  if (!$wgUser->isLoggedIn()) {
		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
			User::SetupSession();
		}
		$request = new FauxRequest(array('returnto' => 'User:' . $user));
		require_once('includes/SpecialUserlogin.php');
		$form = new LoginForm($request);
		$form->mainLoginForm("You need to log in to copy trees<br/><br/>", '');
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

  // Get user and name of tree to create
  $newUser = $wgUser->getName();
  $newName = $wgRequest->getVal('newtree');
  
  // If user has already entered the tree name, copy the tree and display message. Else display the input form.
  $wgOut->setPagetitle("Copy Tree");
  if ( $newName ) {
  	if (!FamilyTreeUtil::isValidTreeName($newName)) {        // added Nov 2021 by Janet Bjorndahl
      $msg = $newName . ' is not a valid tree name';
	  }
    else {
      $status = wfCopyFamilyTree("user=$user|name=$name|newuser=$newUser|newname=$newName");
      if ($status != '<copy status="' . FTE_SUCCESS . '"></copy>') {
        $msg = htmlspecialchars("Error copying tree $user/$name");
      }
	    else {
        $msg = htmlspecialchars("Tree $user/$name is being copied to $newUser/$newName");
	    }
    }
	  $wgOut->addHTML( "<p><font size=\"+1\" color=\"red\">$msg</font></p>\n");
  }
  else {
	  $formHtml = '<form method="get" action="/wiki/Special:CopyTree">';
    $formHtml .= '<input type = "hidden" name="user" value="' . $user . '"/>';
    $formHtml .= '<input type = "hidden" name="name" value="' . $name . '"/>';
    $formHtml .= '<label for="newtree">Name of new tree </label><input type="text" id="newtree" name="newtree"/></form>';
    $wgOut->addHTML($formHtml);
  }
}
?>
