<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialListFamiliesSetup";

function wfSpecialListFamiliesSetup() {
   global $wgMessageCache, $wgSpecialPages, $wgParser;
   $wgMessageCache->addMessages( array( "listfamilies" => "ListFamilies" ) );
   $wgSpecialPages['ListFamilies'] = array('SpecialPage','ListFamilies');
}

function wfSpecialListFamilies($par) {
   global $wgOut, $wgUser, $wgScriptPath;


   if(!$par && !$wgUser->isLoggedIn() ) {
      $wgOut->showErrorPage( 'listnologin', 'listnologintext' );
      return;
   }

   $userName = (!$par || ($wgUser->isLoggedIn() && $par == $wgUser->getName()) ? 'your' : "$par's");

   // set up page
	$wgOut->setPagetitle('List Families');
   $wgOut->setSubtitle("&nbsp; in $userName watchlist");
   $wgOut->setArticleRelated(false);
   $wgOut->setRobotpolicy('noindex,nofollow');

   $wgOut->addScript("<script src=\"$wgScriptPath/jquery.event.drag-2.0.min.js\"></script>");
   $wgOut->addScript("<script src=\"$wgScriptPath/slick.core.js\"></script>");
   $wgOut->addScript("<script src=\"$wgScriptPath/slick.grid.js\"></script>");
   $wgOut->addScript("<script src=\"$wgScriptPath/slick.dataview.js\"></script>");
   $wgOut->addScript("<link href=\"$wgScriptPath/skins/common/jquery.loadmask.css\" rel=\"stylesheet\" type=\"text/css\"/>");
   $wgOut->addScript("<link href=\"$wgScriptPath/skins/common/jquery.multiselect.css\" rel=\"stylesheet\" type=\"text/css\"/>");
   $wgOut->addScript("<script src=\"$wgScriptPath/jquery.loadmask.min.js\"></script>");
   $wgOut->addScript("<script src=\"$wgScriptPath/jquery.multiselect.min.js\"></script>");
   $wgOut->addScript("<script src=\"$wgScriptPath/listfamilies.1.js\"></script>");

   $wgOut->addHTML(<<< END
<div class="listPagesForm" style="width:835px;">
   <div class="listPagesSearch">
     <label>Search: </label><input type="text" id="txtSearch">
   </div>
   <div class="listPagesFilter">
      <span id="rowCount"></span>
      <span id="treeFilter"></span>
   </div>
   <div class="visualClear"></div>
</div>
<div id="myGrid" style="width:850px;height:500px;"></div>
END
   );
}
?>
