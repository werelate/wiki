<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

require_once("$IP/extensions/familytree/FamilyTreeUtil.php");
require_once("$IP/extensions/gedcom/GedcomUtil.php");
require_once("$IP/extensions/other/SpecialGotoPageOld.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialFamilyTreeSetup";

function wfSpecialFamilyTreeSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "familytree" => "Family Tree" ) );
	$wgSpecialPages['FamilyTree'] = array('SpecialPage','FamilyTree');
}

/**
 * constructor
 */
function wfSpecialFamilyTree($par) {

	$mr = new SpecialFamilyTree();
	$mr->execute($par);
}

class SpecialFamilyTree {
   var $action;
   var $name;
   var $newName;
   var $confirmed;

   public function __construct() {
      global $wgRequest;

      $this->action = $wgRequest->getVal( 'action' );
      $this->name = $wgRequest->getVal('name');
      $this->newName = $wgRequest->getVal('newName');
      $this->user = $wgRequest->getVal('user'); // only used in delete, entered directly in URL
      $this->confirmed = $wgRequest->getBool('confirmed');
      $this->canceled = $wgRequest->getBool('canceled');
   }

   public function execute($par) {
      global $wgUser, $wgCommandLineMode, $wgLang, $wgOut;

  		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}
		if( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

      if ($wgUser->isLoggedIn()) {
      	switch ($this->action) {
      	   case 'newTree':
      	      $this->newTree();
      	      break;
      	   case 'shareTree':
      	      $this->shareTree();
      	      break;
      	   case 'renameTree':
      	      $this->renameTree();
      	      break;
      	   case 'deleteTree':
      	      $this->deleteTree();
      	      break;
      	   default:
      	      $this->show();
      	      break;
      	}
      }
   	else {
   		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
   			User::SetupSession();
   		}
   		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('FamilyTree')));
   		require_once('includes/SpecialUserlogin.php');
   		$form = new LoginForm($request);
   		$form->mainLoginForm("You need to log in before creating trees<br/><br/>", '');
   	}
   }

   private function newTree() {
   	global $wgUser;
   	
      if ($this->newName) {
      	$db =& wfGetDB( DB_MASTER );
		   $db->ignoreErrors(true);
         $status = FamilyTreeUtil::createFamilyTree($db, $wgUser->getName(), $this->newName);
		   $db->ignoreErrors(false);
	      if ($status == FTE_DUP_KEY) {
	      	$msg = 'You already have a tree named '.$this->newName;
	      }
	      else if ($status == FTE_INVALID_ARG) {
	      	$msg = $this->newName . ' is not a valid tree name';
	      }
	      else if ($status != FTE_SUCCESS) {
	      	$msg = 'Error creating '.$this->newName;
	      }
	      else {
	      	$msg = $this->newName.' was created';
	      }
         $this->show($msg);
      }
      else {
      	$label = 'Tree name';
			$field = $label.': <input type="text" name="newName"/>';
      	$this->showInputForm('New tree', $field, 'add', 'Add');
      }
   }
   
   private function getPrimaryPage() {
   	global $wgUser;
   	
     	$t = null;
   	$dbr =& wfGetDB(DB_SLAVE);
   	$rows = $dbr->select('familytree', array('ft_primary_namespace', 'ft_primary_title'), array('ft_user' => $wgUser->getName(), 'ft_name' => $this->name));
     	$row = $dbr->fetchObject($rows);
     	if ($row !== false && $row->ft_primary_title) {
     		$t = Title::makeTitle($row->ft_primary_namespace, $row->ft_primary_title);
     	}
	   $dbr->freeResult($rows);
     	return $t;
   }

   private function shareTree() {
   	global $wrHostName, $wgLang, $wgUser, $IP;

   	$link = 'http://'.$wrHostName.'/fte/index.php?userName='. urlencode($wgUser->getName()) . '&treeName=' . urlencode($this->name);
   	$primaryPage = $this->getPrimaryPage();
   	if ($primaryPage) {
   		$link .= '&page='.urlencode($primaryPage->getPrefixedText());
   	}
   	
   	$subject = wfMsg('sharetreesubject');
   	$text = wfMsg('sharetreetext', $link);

   	$request = new FauxRequest(array('returnto' => $wgLang->specialPage('FamilyTree'), 'wpSubject' => $subject, 'wpText' => $text));
		require_once("$IP/extensions/other/SpecialEmail.php");
		$form = new EmailForm($request);
		$form->execute();
   }

   private function renameTree() {
   	global $wgUser;

      if ($this->name && $this->newName) {
      	$db =& wfGetDB( DB_MASTER );
		   $db->ignoreErrors(true);
		   $status = FamilyTreeUtil::renameFamilyTree($db, $wgUser->getName(), $this->name, $this->newName);
		   $db->ignoreErrors(false);
	      if ($status == FTE_DUP_KEY) {
	      	$msg = 'You already have a tree named '.$this->newName;
	      }
	      else if ($status != FTE_SUCCESS) {
	      	$msg = 'Error renaming '.$this->name;
	      }
	      else {
	      	$msg = $this->name.' was renamed to '.$this->newName;
	      }
         $this->show($msg);
      }
      else {
      	$label = 'new name';
			$field = $label.': <input type="text" name="newName"/>';
      	$this->showInputForm('Rename tree: ' . $this->name, $field, 'go', 'Go');
      }
   }

   private function deleteTree() {
   	global $wgUser, $wrAdminUserName;

	   if ($this->user && $wgUser->getName() == $wrAdminUserName) {
	   	$userName = $this->user;
	   }
	   else {
	   	$userName = $wgUser->getName();
	   }
	   
   	if ($this->name && $userName && ($userName != $wgUser->getName() || $this->confirmed)) {
      	$db =& wfGetDB( DB_MASTER );
		   $db->ignoreErrors(true);
		   $status = FamilyTreeUtil::deleteFamilyTree($db, $userName, $this->name, true);
		   $db->ignoreErrors(false);
	      if ($status != FTE_SUCCESS) {
	      	$msg = 'Error deleting '.$this->name;
	      }
	      else {
	      	$msg = $this->name.' is being deleted';
	      }
         $this->show($msg);
      }
      else if ($this->name && $this->canceled) {
      	$this->show();
      }
      else {
			$name = htmlspecialchars($this->name);
      	$label = 'Are you sure you want to delete your <b>'.$name.'</b> family tree';
      	$field = $label.'? <input type="submit" name="confirmed" value="Yes"/>';
      	$this->showInputForm('Are you sure?', $field, 'canceled', 'No');
      }
   }

   private function showInputForm($title, $field, $submitName, $submitValue) {
      global $wgOut;

      $wgOut->setPagetitle(htmlspecialchars($title));
      $wgOut->setArticleRelated(false);
      $wgOut->setRobotpolicy('noindex,nofollow');
		$titleObj = Title::makeTitle( NS_SPECIAL, 'FamilyTree' );
		$action = $titleObj->escapeLocalURL();
		$name = htmlspecialchars($this->name);
		$submitName = htmlspecialchars($submitName);
		$submitValue = htmlspecialchars($submitValue);
		
		$wgOut->addHTML(<<< END
<form method='post' action="$action">
<input type="hidden" name="name" value="$name"/>
<input type="hidden" name="action" value="{$this->action}"/>
{$field}
<input type='submit' name="$submitName"" value="$submitValue"/>
</form>
END
		);
	}

   private function show($msg='') {
   	global $wgUser, $wgOut;

		$wgOut->setPageTitle( 'Family Tree' );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );
		addGotoPageWatchlistScripts();

		$profileRevision = Revision::newFromTitle($wgUser->getUserPage());

		if ($msg) {
			$msg = htmlspecialchars($msg);
			$wgOut->addHTML( "<h2>$msg</h2>\n");
		}
		$wgOut->addWikiText(wfMsg('familytreetext', $this->getTrees()));
   }

   private function getGedcoms($dbr, $id, $name) {
      $gedcom = '';
   	$rows = $dbr->select('familytree_gedcom', array('fg_gedcom_filename', 'fg_status', 'fg_status_date'), array('fg_tree_id' => $id), 'getGedcoms', array('ORDER BY' => 'fg_status_date'));
   	if ($rows !== false) {
      	while ($row = $dbr->fetchObject($rows)) {
      	   $status = '';
      	   switch ($row->fg_status) {
      	      case FG_STATUS_UPLOADED:
      	         $status = 'waiting';
      	         break;
      	      case FG_STATUS_PROCESSING:
      	         $status = 'in process';
      	         break;
      	      case FG_STATUS_READY:
      	      case FG_STATUS_OPENED:
      	         $status = date(" d M Y",wfTimestamp(TS_UNIX,$row->fg_status_date));
      	         break;
      	      case FG_STATUS_ERROR:
      	         $status = 'error';
      	         break;
      	      case FG_STATUS_ERROR_NOT_GEDCOM:
      	         $status = 'not GEDCOM';
      	         break;
      	      case FG_STATUS_OVERLAP:
      	         $status = 'possible overlap?';
      	         break;
      	   }
      	   if ($status) {
     	         $gedcom .= htmlspecialchars($row->fg_gedcom_filename . ": $status") . "<br/>";
      	   }
      	}
			$dbr->freeResult($rows);
   	}
//   	if (!$gedcom) {
//         global $wgUser;

//      	$skin =& $wgUser->getSkin();
//         $tip = 'Import a GEDCOM file into this tree';
//         $gedcom = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'ImportGedcom'), 'Import', wfArrayToCGI(array('wrTreeName' => $name)), '', '', '', " title=\"$tip\"");
//   	}
   	return $gedcom;
   }

   private function getTrees() {
      global $wgUser, $wrHostName;

   	$skin =& $wgUser->getSkin();

      $ret = '<div id="familytree-table"><table width="99%" cellpadding="5" cellspacing="0" border="0">'.
              '<tr><td><b>Name</b></td><td><b>People</b></td><td><b>GEDCOM</b></td><td><b>Share</b></td><td><b>Rename</b></td><td><b>Delete</b></td></tr>';
		$dbr =& wfGetDB( DB_SLAVE );
		$familyTrees = FamilyTreeUtil::getFamilyTrees($wgUser->getName());
      if (!is_null($familyTrees)) {
         foreach($familyTrees as $familyTree) {
            $gedcom = $this->getGedcoms($dbr, $familyTree['id'], $familyTree['name']);
            $share = '<span class="plainlinks">[http://'.$wrHostName.'/wiki/Special:FamilyTree?action=shareTree&name=' .urlencode($familyTree['name']) .' share]</span>';
            $rename = '<span class="plainlinks">[http://'.$wrHostName.'/wiki/Special:FamilyTree?action=renameTree&name=' .urlencode($familyTree['name']) .' rename]</span>';
            $delete = '<span class="plainlinks">[http://'.$wrHostName.'/wiki/Special:FamilyTree?action=deleteTree&name=' .urlencode($familyTree['name']) .' delete]</span>';
              $ret .= '<tr><td>' . htmlspecialchars($familyTree['name']) . ' <span class="plainlinks">'
              . ' (&nbsp;[http://'.$wrHostName.'/wiki/Special:ShowFamilyTree?user='. urlencode($wgUser->getName()) . '&name=' . urlencode($familyTree['name']) . " list]&nbsp;)"
              . ' (&nbsp;[http://'.$wrHostName.'/fte/index.php?userName='. urlencode($wgUser->getName()) . '&treeName=' . urlencode($familyTree['name']) . " launch FTE]&nbsp;)"
              . '</span><td>' . $familyTree['count'] . "</td><td>$gedcom</td><td>$share</td><td>$rename</td><td>$delete</td></tr>";
//            $ret .= '<tr><td>' . htmlspecialchars($familyTree['name']) .
//                              ' (&nbsp;' . $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'ShowFamilyTree'), 'list', wfArrayToCGI(array('user' => $wgUser->getName(), 'name' => $familyTree['name'])), '', '', '', " title=\"$listTip\"") . '&nbsp;) '.
//                              ' (&nbsp;<a href="/fte/index.php?' . wfArrayToCGI(array('userName' => $wgUser->getName(), 'treeName' => $familyTree['name'])) . '" title="'.$launchTip.'">launch FTE</a>&nbsp;)</td>'.
//                  '<td>' . $familyTree['count'] . "</td><td>$gedcom</td><td>$rename</td><td>$delete</td></tr>";
         }
      }

      $ret .= '</table></div>';
      return $ret;
   }
}
?>
