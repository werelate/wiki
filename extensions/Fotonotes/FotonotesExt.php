<?php
// Copyright 2006 by Dallan Quass
// Released under the GPL.
require_once("Fotonotes.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfFotonotesExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfFotonotesExtensionSetup() {
	global $wgHooks;
	global $wgParser;

	# Register hook for edit UI - requires modification to
	$wgHooks['ArticleEditShow'][] = 'renderImageEditFields';

	# register the extension with the WikiText parser
	$wgParser->setHook('fotonotes', 'renderImageData');
}

/**
 * Callback function for converting notes to HTML output
 */
function renderImageData( $input, $argv, $parser) {
	$image = new Fotonotes($parser->getTitle());
	return $image->renderImageNotes($input);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderImageEditFields( &$editPage ) {
   global $wgOut, $wgScriptPath;

	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_IMAGE && !$editPage->section) {
		$image = new Fotonotes($editPage->mTitle);
		// get notes
		$notes = '';
   	$start = stripos($editPage->textbox1, "<fotonotes>");
      if ($start !== false) {
   	   $start += strlen("<fotonotes>");
	  	   $end = stripos($editPage->textbox1, "</fotonotes>", $start);
		   if ($end !== false) {
		      $notes = substr($editPage->textbox1, $start, $end - $start);
		   }
		}
		$wgOut->addHTML($image->renderEditableImage($notes));
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/fnclientwiki.yui.1.js\"></script>");
	}
	return true;
}
?>
