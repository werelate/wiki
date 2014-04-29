<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
require_once("$IP/extensions/other/SpecialSearch.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialBrowseSetup";

function wfSpecialBrowseSetup() {
   global $wgMessageCache, $wgSpecialPages, $wgParser;
   $wgMessageCache->addMessages( array( "browse" => "Browse" ) );
   $wgSpecialPages['Browse'] = array('SpecialPage','Browse');
}

function wfSpecialBrowse() {
   global $wgOut, $wgRequest, $wgUser, $wgScriptPath;

   $scope = $wgRequest->getVal('scope');
   $namespace = $wgRequest->getVal('namespace');
   $pageTitle = $wgRequest->getVal('pagetitle');

   // default namespace
   if (strlen($namespace) == 0) {
   	$namespace = NS_MAIN;
   }
   // default scope
   if (!$scope) {
  		$scope = 'all';
   }
   
   // get title
   $title = null;
   if ($pageTitle) {
		$title = Title::newFromText($pageTitle, $namespace);
		if ($title) {
			$pageTitle = $title->getText();
			$namespace = $title->getNamespace();
		}
   }

   // set up page
	$wgOut->setPagetitle(wfMsg('browsepages'));
   $wgOut->setArticleRelated(false);
   $wgOut->setRobotpolicy('noindex,nofollow');

	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/browse.5.js\"></script>");
	
   // set up alpha links
   $alphaLinks = '';
   $c = 'A';
   for ($i = 0; $i < 26; $i++) {
   	$alphaLinks .= "&nbsp;<a title=\"$c\" href =\"javascript:void(0)\" onClick=\"browseGo('$c'); return preventDefaultAction(event);\">$c</a>\n";
   	$c++;
   }
   
   $tableRows = '';
  	$sk = $wgUser->getSkin();
	$dbr =& wfGetDB( DB_SLAVE );
	if ($scope == 'all') {
	  	$rows = $dbr->select('page', array('page_title'), array('page_namespace' => $namespace, 'page_is_redirect' => '0', 'page_title >= ' . ($title ? $dbr->addQuotes($title->getDBkey()) : "''")),
	  								'wfSpecialBrowse', array('LIMIT' => 11, 'ORDER BY' => 'page_title'));
	}
	else {
	  	$rows = $dbr->select('watchlist', array('wl_title'), array('wl_user' => $wgUser->getID(), 'wl_namespace' => $namespace, 'wl_title >= ' . ($title ? $dbr->addQuotes($title->getDBkey()) : "''")),
	  								'wfSpecialBrowse', array('LIMIT' => 11, 'ORDER BY' => 'wl_title'));
	}
	$nextDisplay = 'none';
	$prevDisplay = ($pageTitle ? 'inline' : 'none');
	$cnt = 0;
   while ($row = $dbr->fetchRow($rows)) {
   	if ($cnt == 10) {
   		$nextDisplay = 'inline';
   		break;
   	}
   	$t = Title::makeTitleSafe($namespace, $row[0]);
   	if ($t) {
  			$link = $sk->makeKnownLinkObj($t, htmlspecialchars($t->getText()));
   		$tableRows .= "<tr><td align=\"left\">$link</td></tr>\n";
   	}
   	$cnt++;
   }
   $dbr->freeResult($rows);
   while ($cnt < 10) {
   	$tableRows .= "<tr><td></td></tr>\n";
   	$cnt++;
   }
	
   // set up scope
   if ($scope == 'all') {
   	$watchedSelected = '';
   	$allSelected = ' checked';
   }
   else {
   	$watchedSelected = ' checked';
   	$allSelected = '';
   }
    $browsepagestext = wfMsg('browsepagestext');
   // set up namespace selector
	$nsSelector = str_replace('<select ', "<select onChange=\"browseChangeNs('')\" ", HTMLnamespaceselector($namespace));
	
	// add form
		$sideText = $wgOut->parse(<<< END
$browsepagestext
END
		);
    $watchedpages = wfMsg('watchedpages');
    $pleasewait = wfMsg('pleasewait');
    $titlecasesensitive = wfMsg('titlecasesensitive');
    $pagefromlist = wfMsg('pagefromlist');
    $threespaces = wfMsg('threespaces');
   $wgOut->addHTML(<<< END
<center>
<div class="browse">
<form name="browse" action="/wiki/Special:Browse" method="get">
<table style="margin: 0 .5em 1em .5em">
<tr><td></td><td align="left"><input type="radio" name="scope" value="watched"$watchedSelected onChange="browseGo('')"> $watchedpages
<input type="radio" name="scope" value="all"$allSelected onChange="browseGo('')"> All pages</td></tr>
<tr><td align="right">Namespace:</td><td align="left">$nsSelector
<span id="pleasewait" style="display: none"><span style="padding: 0 .2em; color: #fff; background-color: #888">$pleasewait</span></span>
</td></tr>
<tr><td align="right">Title:</td><td align="left"><input id="titleinput" type="text" name="pagetitle" size="25" maxlength="150" value="$pageTitle"/>
<input type="submit" name="go" value="Go"/>
</td></tr>
<tr><td></td><td style="margin: 0; padding: 0; line-height: .7em; font-size: 80%;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$titlecasesensitive</td></tr>
</table>
</form>
</div>
$alphaLinks
<div id="message">$pagefromlist</div>
<table id="results" align="center">
<tr><td align="center"><span id="prevlink" style="display: $prevDisplay"><a title="Previous page" href="javascript:void(0)" onClick="browsePrev(); return preventDefaultAction(event);">< Prev</a></span> &nbsp;&nbsp;&nbsp;
<span id="nextlink" style="display: $nextDisplay"><a title="Next page" href="javascript:void(0)" onClick="browseNext(); return preventDefaultAction(event);">Next ></a></span></td></tr>
$tableRows
<tr><td align="center"><span id="prevlink2" style="display: $prevDisplay"><a title="Previous page" href="javascript:void(0)" onClick="browsePrev(); return preventDefaultAction(event);">< Prev</a></span> &nbsp;&nbsp;&nbsp;
<span id="nextlink2" style="display: $nextDisplay"><a title="Next page" href="javascript:void(0)" onClick="browseNext(); return preventDefaultAction(event);">Next ></a></span></td></tr>
</table>
</center>
END
   );
}
?>
