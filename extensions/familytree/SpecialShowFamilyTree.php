<?php
/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialShowFamilyTreeSetup";

function wfSpecialShowFamilyTreeSetup() {
   global $wgSpecialPages;
   $wgSpecialPages['ShowFamilyTree'] = array('SpecialPage','ShowFamilyTree');
}

/**
 * Entry point : initialise variables and call subfunctions.
 * @param $par String: becomes "FOO" when called like Special:Allpages/FOO (default NULL)
 * @param $specialPage @see SpecialPage object.
 */
function wfSpecialShowFamilyTree() {
	global $wgRequest, $wgOut, $wgContLang;

	# GET values
	$user = $wgRequest->getVal('user');
	$name = $wgRequest->getVal('name');
	$from = $wgRequest->getVal('from');

	if ($user && $name) {
	   $wgOut->setPageTitle(htmlspecialchars("$user:$name family tree"));
	   $sft = new ShowFamilyTree($user, $name);
	   $sft->showChunk($from);
	}
	else {
	   $wgOut->setPagetitle('Family Trees');
	   $sft = new ShowFamilyTrees();
	   $sft->showChunk($from);
	}
}

class ShowFamilyTree {
	var $maxPerPage=960;
   var $user;
   var $name;

	public function __construct($user, $name) {
	   $this->user = $user;
	   $this->name = $name;
	}

	function getForm ($from = '') {
   	global $wgScript;
   	$t = Title::makeTitle( NS_SPECIAL, 'ShowFamilyTree');

   	$frombox = "<input type='text' size='50' name='from' id='nsfrom' value=\""
   	            . htmlspecialchars ( $from ) . '"/>';
   	$submitbutton = '<input type="submit" value="' . wfMsgHtml( 'allpagessubmit' ) . '" />';

   	$out = "<form method='get' action='{$wgScript}'>"
   	  . '<input type="hidden" name="user" value="'.htmlspecialchars($this->user).'" />'
   	  . '<input type="hidden" name="name" value="'.htmlspecialchars($this->name).'" />'
   	  . '<input type="hidden" name="title" value="'.htmlspecialchars($t->getPrefixedText()).'" />'
   	  . "
   <table>
   	<tr>
   		<td align='right'>Display family tree pages starting at</td>
   		<td align='left'><label for='nsfrom'>$frombox</label></td>
   	</tr>
   </table>
   ";
   	$out .= '</form></div>';
   		return $out;
   }

	function showChunk($from) {
	   global $wgUser, $wgContLang, $wgOut;

	   $sk = $wgUser->getSkin();
	   $title = null;
	   if ($from) {
	     $title = Title::newFromText($from);
	   }

      // issue db query to get the family trees
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = 'SELECT fp_namespace, fp_title, fp_oldid, fp_latest FROM familytree_page USE INDEX (PRIMARY)'
		   . ' WHERE fp_tree_id = (SELECT ft_tree_id FROM familytree WHERE ft_user = ' . $dbr->addQuotes($this->user) . ' AND ft_name = ' . $dbr->addQuotes($this->name) . ')';
		if ($title) {
		   $sql .= ' AND ((fp_namespace = ' . $dbr->addQuotes($title->getNamespace()) . ' AND fp_title >= ' . $dbr->addQuotes($title->getDBkey()) . ')'
		              . ' OR fp_namespace > ' . $dbr->addQuotes($title->getNamespace()) . ')';
		}
		$sql .= ' ORDER BY fp_tree_id, fp_namespace, fp_title'
		      . ' LIMIT ' . ($this->maxPerPage + 1);
		$res = $dbr->query($sql, 'ShowFamilyTree');

		### FIXME: side link to previous

		$n = 0;
		$out = '<table style="background: inherit;" border="0" width="100%">';

		while( ($n < $this->maxPerPage) && ($s = $dbr->fetchObject( $res )) ) {
		   $title = Title::makeTitle($s->fp_namespace, $s->fp_title);
		   $oldid = (int) $s->fp_oldid;
		   $latest = (int) $s->fp_latest;
         if ($oldid == $latest) {
		      $link = $sk->makeKnownLinkObj($title, htmlspecialchars($title->getPrefixedText()));
         }
         else {
            $link = $sk->makeKnownLinkObj($title, htmlspecialchars($title->getPrefixedText()), 'oldid=' . $oldid)
               . ' (' . $sk->makeKnownLinkObj($title, htmlspecialchars('latest version')) . ')';
         }
			if( $n % 3 == 0 ) {
				$out .= '<tr>';
			}
			$out .= "<td>$link</td>";
			$n++;
			if( $n % 3 == 0 ) {
				$out .= '</tr>';
			}
		}
		if( ($n % 3) != 0 ) {
			$out .= '</tr>';
		}
		$out .= '</table>';

		$form = $this->getForm($from);
		$userName = 'user=' . wfUrlEncode($this->user) . '&name=' . wfUrlEncode($this->name);
		$out2 = '<table style="background: inherit;" width="100%" cellpadding="0" cellspacing="0" border="0">';
		$out2 .= '<tr valign="top"><td align="left">' . $form;
		if ( isset($dbr) && $dbr && ($n == $this->maxPerPage) && ($s = $dbr->fetchObject( $res )) ) {
		   $title = Title::makeTitle($s->fp_namespace, $s->fp_title);
			$out2 .= '</td><td align="right" style="font-size: smaller; margin-bottom: 1em;">' .
				$sk->makeKnownLink( $wgContLang->specialPage("ShowFamilyTree"), wfMsgHtml('nextpage', $title->getPrefixedText()),
			   $userName . '&from=' . wfUrlEncode($title->getPrefixedText()));
		}
		$out2 .= "</td></tr></table><hr />";

   	$wgOut->addHtml( $out2 . $out );
		$dbr->freeResult($res);
   }
}

class ShowFamilyTrees {
	var $maxPerPage=960;

   function getForm ($from = '') {
   	global $wgScript;
   	$t = Title::makeTitle( NS_SPECIAL, 'ShowFamilyTree');

   	$frombox = "<input type='text' size='20' name='from' id='nsfrom' value=\""
   	            . htmlspecialchars ( $from ) . '"/>';
   	$submitbutton = '<input type="submit" value="' . wfMsgHtml( 'allpagessubmit' ) . '" />';

   	$out = "<form method='get' action='{$wgScript}'>";
   	$out .= '<input type="hidden" name="title" value="'.$t->getPrefixedText().'" />';
   	$out .= "
   <table>
   	<tr>
   		<td align='right'>Display family trees starting at</td>
   		<td align='left'><label for='nsfrom'>$frombox</label></td>
   	</tr>
   </table>
   ";
   	$out .= '</form></div>';
   	return $out;
   }

	function showChunk($from) {
	   global $wgUser, $wgContLang, $wgOut;

	   $sk = $wgUser->getSkin();

      // issue db query to get the family trees
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select('familytree', array('ft_user', 'ft_name'), $from ? array( 'ft_user >= ' . $dbr->addQuotes($from)) : '', 'ShowFamilyTrees',
			array(
				'ORDER BY'  => 'ft_user, ft_name',
				'LIMIT'     => $this->maxPerPage + 1,
			)
		);

		### FIXME: side link to previous

		$n = 0;
		$out = '<table style="background: inherit;" border="0" width="100%">';
		$title = Title::makeTitle(NS_SPECIAL, 'ShowFamilyTree');

		while( ($n < $this->maxPerPage) && ($s = $dbr->fetchObject( $res )) ) {
			$link = $sk->makeKnownLinkObj($title, htmlspecialchars("{$s->ft_user}:{$s->ft_name}"), 'user=' . wfUrlEncode($s->ft_user) . '&name=' . wfUrlEncode($s->ft_name));
			if( $n % 3 == 0 ) {
				$out .= '<tr>';
			}
			$out .= "<td>$link</td>";
			$n++;
			if( $n % 3 == 0 ) {
				$out .= '</tr>';
			}
		}
		if( ($n % 3) != 0 ) {
			$out .= '</tr>';
		}
		$out .= '</table>';

		$form = $this->getForm($from);
		$out2 = '<table style="background: inherit;" width="100%" cellpadding="0" cellspacing="0" border="0">';
		$out2 .= '<tr valign="top"><td align="left">' . $form;
		$out2 .= '</td><td align="right" style="font-size: smaller; margin-bottom: 1em;">' .
				$sk->makeKnownLink( $wgContLang->specialPage("ShowFamilyTree"), 'Show Family Trees');
		if ( isset($dbr) && $dbr && ($n == $this->maxPerPage) && ($s = $dbr->fetchObject( $res )) ) {
			$out2 .= " | " . $sk->makeKnownLink( $wgContLang->specialPage("ShowFamilyTree"), wfMsgHtml('nextpage', $s->ft_user), "from=" . wfUrlEncode($s->ft_user));
		}
		$out2 .= "</td></tr></table><hr />";

   	$wgOut->addHtml( $out2 . $out );
		$dbr->freeResult($res);
   }
}
?>
