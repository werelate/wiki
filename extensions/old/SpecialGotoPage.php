<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialGotoPageSetup";

function wfSpecialGotoPageSetup() {
   global $wgMessageCache, $wgSpecialPages, $wgParser;
   $wgMessageCache->addMessages( array( "gotopage" => "GotoPage" ) );
   $wgSpecialPages['GotoPage'] = array('SpecialPage','GotoPage');
}

function wfSpecialGotoPage() {
   global $wgOut, $wgRequest, $wgUser, $wgScriptPath;

   $scope = $wgRequest->getVal('scope');
   $namespace = $wgRequest->getVal('namespace');
   $pageTitle = $wgRequest->getVal('pagetitle');
   $id = $wgRequest->getVal('id');
   if (!$scope) {
   	if ($wgUser->isLoggedIn()) {
	   	$scope = 'watched';
   	}
   	else {
   		$scope = 'all';
   	}
   }
   if (strlen($namespace) == 0) {
   	$namespace = NS_MAIN;
   }
   $title = null;
   if ($pageTitle) {
		$title = Title::newFromText($pageTitle, $namespace);
		if ($title) {
			$pageTitle = $title->getText();
			$namespace = $title->getNamespace();
			// if not choosing and the title exists, go there now
			if (!$id && $title->exists()) {
	         $wgOut->redirect($title->getFullURL());
				return;
			}
		}
   }
   $nsSelector = HTMLnamespaceselector($namespace);

   // set up page
   $wgOut->setPagetitle($id ? 'Choose page' : 'Go to page');
   $wgOut->setArticleRelated(false);
   $wgOut->setRobotpolicy('noindex,nofollow');

   // add javascript functions
	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/gotopage.js\"></script>");
	//!!! must turn this into a document ready call
	$wgOut->setOnloadHandler("gpInit('$id')");
	
   // set up alpha links
   $alphaLinks = '';
   $c = 'A';
   for ($i = 0; $i < 26; $i++) {
   	$alphaLinks .= "&nbsp;<a title=\"$c\" href=\"javascript:gpGo('$c')\">$c</a>\n";
   	$c++;
   }
   
   // !!! set up table
   $tableRows = '';
  	$sk = $wgUser->getSkin();
	$dbr =& wfGetDB( DB_SLAVE );
	if ($scope == 'all') {
	  	$rows = $dbr->select('page', array('page_title'), array('page_namespace' => $namespace, 'page_title >= ' . ($title ? $dbr->addQuotes($title->getDBkey()) : "''")),
	  								'wfSpecialGotoPage', array('LIMIT' => 11, 'ORDER BY' => 'page_title'));
	}
	else {
	  	$rows = $dbr->select('watchlist', array('wl_title'), array('wl_user' => $wgUser->getID(), 'wl_namespace' => $namespace, 'wl_title >= ' . ($title ? $dbr->addQuotes($title->getDBkey()) : "''")),
	  								'wfSpecialGotoPage', array('LIMIT' => 11, 'ORDER BY' => 'wl_title'));
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
   		if ($id) {
   			$link = "<a href=\"javascript:gpReturn($cnt)\">" . htmlspecialchars($t->getText()) . '</a>';
   		}
   		else {
   			$link = $sk->makeKnownLinkObj($t, htmlspecialchars($t->getText()));
   		}
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
   
   // set up namespace selector
   $nsDisabled = ($id ? ' disabled' : '');
	$nsSelector = str_replace('<select ', "<select onChange=\"gpGo('')\"$nsDisabled ", $nsSelector);
	
	// set up form action
	$onSubmit = '';
	if ($id) {
		$onSubmit = ' onSubmit="gpGo(\'\'); return false;"';
	}
	
	// add form
   $wgOut->addHTML(<<< END
<center>
<div class="gotopage">
<form name="goto" action="/wiki/Special:GotoPage" method="get"$onSubmit>
<table style="margin: 0 .5em 1em .5em">
<tr><td></td><td align="left"><input type="radio" name="scope" value="watched"$watchedSelected onChange="gpGo('')"> Watched pages
<input type="radio" name="scope" value="all"$allSelected onChange="gpGo('')"> All pages</td></tr>
<tr><td align="right">Namespace:</td><td align="left">$nsSelector
<span id="pleasewait" style="display: none"><span style="padding: 0 .2em; color: #fff; background-color: #888">Please Wait</span></span>
</td></tr>
<tr><td align="right">Title:</td><td align="left"><input id="titleinput" type="text" name="pagetitle" size="25" maxlength="150" value="$pageTitle"/>
<input type="submit" name="go" value="Go"/>
</td></tr>
<tr><td></td><td style="margin: 0; padding: 0; line-height: .7em; font-size: 80%;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Title is case-sensitive</td></tr>
</table>
</form>
</div>
$alphaLinks
<div id="message">Choose a page from the list</div>
<table id="results" align="center">
<tr><td align="center"><span id="prevlink" style="display: $prevDisplay"><a title="Previous page" href="javascript:gpPrev()">< Prev</a></span> &nbsp;&nbsp;&nbsp;
<span id="nextlink" style="display: $nextDisplay"><a title="Next page" href="javascript:gpNext()">Next ></a></span></td></tr>
$tableRows
<tr><td align="center"><span id="prevlink2" style="display: $prevDisplay"><a title="Previous page" href="javascript:gpPrev()">< Prev</a></span> &nbsp;&nbsp;&nbsp;
<span id="nextlink2" style="display: $nextDisplay"><a title="Next page" href="javascript:gpNext()">Next ></a></span></td></tr>
</table>
</center>
END
   );
}
?>
