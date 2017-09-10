<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

require_once("$IP/extensions/other/AutoCompleter.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialGotoPageOldSetup";

function wfSpecialGotoPageOldSetup() {
   global $wgMessageCache, $wgSpecialPages, $wgParser;
   $wgMessageCache->addMessages( array( "gotopageold" => "GotoPageOld" ) );
   $wgSpecialPages['GotoPageOld'] = array('SpecialPage','GotoPageOld');

   # register the extension with the WikiText parser
   $wgParser->setHook('gotopage', 'gotoPageHook');
}

/**
 * Callback function for converting source to HTML output
 */
function gotoPageHook( $input, $argv, $parser) {
   return getGotoPageForm($input, '', true);
}

function addGotoPageWatchlistScripts() {
   global $wgOut, $wgScriptPath;

	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.js\"></script>");
// (Main)
   $wgOut->addScript(<<< END
<script type="text/javascript">
//<![CDATA[
function changeNs() { 
 $("#titleinput").autocompleteRemove(); 
 var ns=document.getElementById('namespace');
 $("#titleinput").autocomplete({ defaultNs:ns.options[ns.selectedIndex].text, userid:userId}); 
}
//]]>
</script>
END
   );
}

// if namespace is 'watchlist', must also call addWatchlistScripts to add scripts to the page
function getGotoPageForm($namespace, $titleText, $hideNamespace = false) {
   if ($namespace == 'person') {
     	$result = <<< END
<form name="search" action="/wiki/Special:GotoPageOld" method="get">
<input type="hidden" name="namespace" value="person"/>
<table>
<tr><td align="right">First given name:</td><td><input type="text" name="personGiven" size=12 maxlength="100" value="" onfocus="select()"/></td></tr>
<tr><td align="right">Surname:</td><td><input type="text" name="personSurname" size=12 maxlength="100" value=""/></td></tr>
<tr><td></td><td align="right"><input type="submit" name="add" value="Add" /></td></tr></table>
</form>
END;
   }
   else if ($namespace == 'family') {
     	$result = <<< END
<form name="search" action="/wiki/Special:GotoPageOld" method="get">
<input type="hidden" name="namespace" value="family"/>
<table>
<tr><td></td><td align="center">First given name</td><td align="center">Surname</td></tr>
<tr><td align="right">Husband:</td><td><input type="text" name="husbandGiven" size=10 maxlength="100" value="" onfocus="select()"/></td><td><input type="text" name="husbandSurname" size=10 maxlength="100" value=""/></td></tr>
<tr><td align="right">Wife:</td><td><input type="text" name="wifeGiven" size=10 maxlength="100" value=""/></td><td><input type="text" name="wifeSurname" size=10 maxlength="100" value=""/></td></tr>
<tr><td></td><td></td><td align="right"><input type="submit" name="add" value="Add" /></td></tr></table>
</form>
END;
   }
   else {
      $addPage = true;
      $size = 24;
      $titleAttrs = '';
      $nsSelector = '';
      if ($namespace == 'watchlist') {
         $namespace = NS_PERSON;
         $titleAttrs= ' class="person_input"';
         $hideNamespace = false;
         $addPage = false;
         $size = 15;

      	$nsSelector = "<select id='namespace' name='namespace' class='namespaceselector' onChange='changeNs()'>\n\t";
      	foreach (AutoCompleter::$nsidToNs as $index => $name) {
      		if ($index === $namespace) {
      			$nsSelector .= Xml::element("option",
      					array("value" => $index, "selected" => "selected"),
      					$name);
      		} else {
      			$nsSelector .= Xml::element("option", array("value" => $index), $name);
      		}
      	}
     	   $nsSelector .= "\n</select>\n";
     	   $fieldAlign = 'left';
     	   $fieldSeparator = ' ';
      }
      else {
      	$fieldAlign = 'right';
      	$fieldSeparator = '</td><td align="left">';
	      if (!$hideNamespace || strlen($namespace) == 0) {
	         $nsSelector = HTMLnamespaceselector($namespace, null);
	      }
      }
      if (!$nsSelector) {
         $namespaceselect = '<input type="hidden" name="namespace" value="'.$namespace.'"/>';
      }
      else {
     	   $namespaceselect = "<tr><td align=\"$fieldAlign\">Namespace:$fieldSeparator$nsSelector</tr>";
      }
      if ($namespace == NS_IMAGE || $namespace == NS_USER) {
         $addPage = false;
      }
      $result = <<< END
<form name="search" action="/wiki/Special:GotoPageOld" method="get">
<table>
{$namespaceselect}<tr><td align="$fieldAlign">Title:$fieldSeparator<input id="titleinput" type="text" name="pagetitle" size="$size" maxlength="150" value="$titleText" onfocus="select()"$titleAttrs/>
</td></tr><tr><td align="right" colspan="2">
END;
      if ($addPage) {
         $result .= '<input type="submit" name="goto" value="Go to page"/>&nbsp;&nbsp;<input type="submit" name="add" value="Add new page"/>';
      }
      else {
         $result .= ' <input type="submit" name="goto" value="Go"/>';
      }
      $result .= '</td></tr></table></form>';
   }
   return "<div class=\"gotopage\">$result</div>";
}

function wrPrepareNamePiece($n) {
   $n = trim($n);
   if ($n == mb_strtolower($n) || $n == mb_strtoupper($n)) {
      $n = mb_convert_case($n, MB_CASE_TITLE);
   }
   return $n;
}

function wrConstructName($gn, $sn) {
   $gn=preg_replace('/\s.*$/', '', wrPrepareNamePiece($gn));
   $sn=wrPrepareNamePiece($sn);
   if ($gn=='' && $sn=='') {
      return '';
   }
   else if ($gn=='') {
      return ($sn=='Unknown' ? $sn : "Unknown $sn");
   }
   else if ($sn=='') {
      return ($gn=='Unknown' ? $gn : "$gn Unknown");
   }
   else {
      return "$gn $sn";
   }
}

/**
 * constructor
 */
function wfSpecialGotoPageOld() {
   global $wgOut, $wgRequest, $wgUser;

   $error = '';
   $titleList = '';
   $addPersonFamily = false;
   $title = null;

   $namespace = $wgRequest->getVal('namespace');
   $titleText = $wgRequest->getVal('pagetitle');

   if ($namespace == 'person') {
      $addPersonFamily = true;
      $titleText = wrConstructName($wgRequest->getVal('personGiven'), $wgRequest->getVal('personSurname'));
      if ($titleText) {
         $title = Title::newFromText($titleText, NS_PERSON);
      }
   }
   else if ($namespace == 'family') {
      $addPersonFamily = true;
      $husbandText = wrConstructName($wgRequest->getVal('husbandGiven'), $wgRequest->getVal('husbandSurname'));
      $wifeText = wrConstructName($wgRequest->getVal('wifeGiven'), $wgRequest->getVal('wifeSurname'));
      $titleText = '';
      if ($husbandText && $wifeText) {
         $titleText = $husbandText . ' and ' . $wifeText;
      }
      else if ($husbandText) {
         $titleText = $husbandText . ' and Unknown';
      }
      else if ($wifeText) {
         $titleText = 'Unknown and ' . $wifeText;
      }
      if ($titleText) {
         $title = Title::newFromText($titleText, NS_FAMILY);
      }
   }
   else if ($titleText) {
      $title = Title::newFromText($titleText, $namespace);
      if (is_null($title)) {
         $error = wfmsg('invalidtitle');
      }
      else if ($wgRequest->getVal('add') && ($title->getNamespace() == NS_USER || $title->getNamespace() == NS_USER_TALK)) {
         // user must exist if we're adding a page for them
   		$pos = strpos($title->getText(), '/');
		   if ($pos !== false) {
			   $userName = substr($title->getText(), 0, $pos);
		   }
		   else {
		      $userName = $title->getText();
		   }
		   if (!User::isIP($userName) && User::idFromName($userName) == 0) {
		      $error = 'User does not exist.  Is the name spelled correctly?';
		      $title = null;
		   }
		}
   }

   if ($title) {
      if ($wgRequest->getVal('goto')) {
         if ($title->getNamespace() == NS_SPECIAL || $title->exists()) {
            $wgOut->redirect($title->getFullURL());
            return;
         }
         else {
            $error = 'Title not found.';

         	$skin = $wgUser->getSkin();
      		$n = 0;
      		$moreLink = false;

      		$dbr =& wfGetDB( DB_SLAVE );
         	$res = $dbr->select('page', array('page_title', 'page_namespace', 'page_is_redirect'), 'page_namespace='.$title->getNamespace().' and page_title like "'.$title->getDBkey().'%"',
         	                    'wfSpecialGotoPageOld', array ('LIMIT' => 31, 'ORDER BY' => 'page_title') );
      		while ($row = $dbr->fetchObject($res)) {
      		   if ($n == 30) {
      		      $moreLink = true;
      		      break;
      		   }
      		   if (!$titleList) {
     		        $titleList = '<h2>Titles starting with '.$titleText.'</h2><table style="background: inherit;" border="0" width="100%">';
      		   }
      			$t = Title::makeTitle( $row->page_namespace, $row->page_title );
      			if( $t ) {
      				$link = ($row->page_is_redirect ? '<div class="allpagesredirect">' : '' ) .
      					$skin->makeKnownLinkObj( $t, htmlspecialchars( $t->getPrefixedText() )) .
      					($row->page_is_redirect ? '</div>' : '' );
      			} else {
      				$link = '[[' . htmlspecialchars( $row->page_title ) . ']]';
      			}
      			if( $n % 3 == 0 ) {
      				$titleList .= '<tr>';
      			}
      			$titleList .= "<td>$link</td>";
      			$n++;
      			if( $n % 3 == 0 ) {
      				$titleList .= '</tr>';
      			}
      		}
      		$dbr->freeResult($res);

      		if( ($n % 3) != 0 ) {
      			$titleList .= '</tr>';
      		}
      		if ($titleList) {
        		   $titleList .= '</table>';
               $error .= " All pages starting with $titleText are listed below.";
            }
            if ($moreLink) {
               $t = Title::makeTitle(NS_SPECIAL, 'Allpages');
               $titleList .= '<p>More not shown (' . $skin->makeKnownLinkObj($t, 'show all', "from={$title->getText()}&namespace={$title->getNamespace()}") . ')</p>';
            }
         }
      }
      else if ($wgRequest->getVal('add')) {
         // PERSON and FAMILY pages must have a unique id
         if (($title->getNamespace() == NS_PERSON || $title->getNamespace() == NS_FAMILY) && !StructuredData::titleStringHasId($title->getText())) {
            // standardize name case and append a unique id
            $title = StructuredData::appendUniqueId(Title::newFromText((string)StructuredData::standardizeNameCase($title->getText()), $title->getNamespace()));
         }
         if ($title != null) {
            $wgOut->redirect($title->getFullURL('action=edit'));
         }
         return;
      }
   }

   // set up page
   $wgOut->setPagetitle('Go to / Add page');
   $wgOut->setArticleRelated(false);
   $wgOut->setRobotpolicy('noindex,nofollow');

   if ($error) {
      $wgOut->addHTML("<p><font color=red>$error</font></p>");
   }

   $wgOut->addHTML("<center>".getGotoPageForm($namespace, $titleText)."</center>");

   if (!$addPersonFamily) {
      if ($titleList) {
         $wgOut->addHTML($titleList);
      }
      else {
         $wgOut->addWikiText("\n\n".wfmsg('gotopageend'));
      }
   }
}
?>
