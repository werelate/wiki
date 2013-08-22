<?php

/*
* index.php?action=ajax&rs=functionName&rsargs=a=v|b=v|c=v
*/

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfShowWatchersSetup";

# Register with AjaxDispatcher as a function
# call in this order to index
$wgAjaxExportList[] = "wfShowWatchers";

function wfShowWatchersSetup() {
	global $wgHooks;

	# Register hooks for edit UI, request handling, and save features
   $wgHooks['SkinTemplateTabs'][] = 'wrAddShowWatchers';
}

function wrAddShowWatchers(&$template, &$content_actions) {
   global $wgTitle;
   
   $pageTitle = $wgTitle->getFullText();
   $content_actions['watchers'] = array(
      'class=' => false,
      'text' => "Who's watching",
      'title' => "Who's watching this page",
      'href' => '/w/index.php?'.http_build_query(array('action' => 'ajax', 'rs'=>'wfShowWatchers',
                                                         'rsargs' => $pageTitle)),
   );
}

function wfShowWatchers($args) {
   $title = Title::newFromText($args);
   $watchers = StructuredData::getWatchers($title);
   return '<ol>'.join('',$watchers).'</ol>';
}
?>
