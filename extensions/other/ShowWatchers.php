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
      'text' => wfMsg('watcherarraytext'),
      'title' => wfMsg('watcherarraytitle'),
      'href' => '/w/index.php?'.http_build_query(array('action' => 'ajax', 'rs'=>'wfShowWatchers',
                                                         'rsargs' => $pageTitle)),
   );
}

function wfShowWatchers($args) {
   global $wgUser;

   $title = Title::newFromText($args);
   $result = array();
   $skin = $wgUser->getSkin();
   $dbr =& wfGetDB(DB_SLAVE);
   $sql = 'SELECT user_name FROM user, watchlist where wl_namespace=' .  $dbr->addQuotes($title->getNamespace()) .
          ' AND wl_title=' .  $dbr->addQuotes($title->getDBkey()) . ' AND wl_user=user_id';
   $rows = $dbr->query($sql);
   $errno = $dbr->lastErrno();
   while ($row = $dbr->fetchObject($rows)) {
      $title = Title::newFromText($row->user_name, NS_USER); 
      $result[] = '<li>'.$skin->makeLinkObj($title, htmlspecialchars($title->getText())).'</li>';
   }
   $dbr->freeResult($rows);
   if (count($result) == 0) {
      $result[] = '<li>no watchers</li>';
   }
   return '<ol>'.join('',$result).'</ol>';
}
?>
