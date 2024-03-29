<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

require_once("$IP/extensions/familytree/FamilyTreeUtil.php");


# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialTreesSetup";

function wfSpecialTreesSetup() {
	global $wgMessageCache, $wgSpecialPages;
//	$wgMessageCache->addMessages( array( "trees" => "Manage Trees" ) );   // commented out Dec 2020 by Janet Bjorndahl (changed to mytrees and added to Messages.php)
	$wgSpecialPages['Trees'] = array('SpecialPage','Trees');
}

/**
 * constructor
 */
function wfSpecialTrees($par) {

	$st = new SpecialTrees();
	$st->execute($par);
}

class SpecialTrees {
   var $action;
   var $name;
   var $newName;
   var $confirmed;
   var $gedcomId;
   
   // option lists added Aug 2020 by Janet Bjorndahl
   // This list is a shorter version of SearchForm::$NAMESPACE_OPTIONS_ID (SpecialSearch.php). It excludes namespaces that cannot be added to trees.
   // This list should be kept in sync (relative order and labels) with SearchForm::$NAMESPACE_OPTIONS_ID
	 public static $EXPLORE_NAMESPACE_OPTIONS = array( 
		'All' => '',
		'Person' => NS_PERSON,
		'Family' => NS_FAMILY,
		'Article' => '0',
		'Image' => NS_IMAGE,
		'Place' => NS_PLACE,
		'MySource' => NS_MYSOURCE,
		'Source' => NS_SOURCE,
    'Transcript' => NS_TRANSCRIPT,
		'Repository' => NS_REPOSITORY,
		'User' => NS_USER,
		'Surname' => NS_SURNAME,
		'Givenname' => NS_GIVEN_NAME,
	 );

   public static $EXPLORE_ROWS_OPTIONS = array('10','15','20','30','40','50','100','200');

   public function __construct() {
		$this->action = '';
   	$this->name = '';
   	$this->newName = '';
   	$this->confirmed = '';
   	$this->gedcomId = '';
   }

   public function execute($par) {
      global $wgUser, $wgRequest, $wgCommandLineMode, $wgLang, $wgOut;

      $this->action = $wgRequest->getVal( 'action' );
      $this->name = $wgRequest->getVal('name');
      $this->newName = trim($wgRequest->getVal('newName'));
      $this->user = $wgRequest->getVal('user');
      $this->confirmed = $wgRequest->getBool('confirmed');
      $this->canceled = $wgRequest->getBool('canceled');
      $this->gedcomId = $wgRequest->getVal('gedcomId');

      if ($wgUser->isLoggedIn()) {
			if( $wgUser->isBlocked() ) {
				$wgOut->blockedPage();
				return;
			}
			if( wfReadOnly() ) {
				$wgOut->readOnlyPage();
				return;
			}
			
      	switch ($this->action) {
      	   case 'newTree':
      	      $this->newTree();
      	      break;
      	   case 'emailTree':
      	      $this->emailTree();
      	      break;
      	   case 'renameTree':
      	      $this->renameTree();
      	      break;
      	   case 'deleteTree':
      	      $this->deleteTree();
      	      break;
      	   case 'exportGedcom':
      	   	$this->exportGedcom();
      	   	break;
      	   case 'downloadExport':
      	      $this->downloadExport();
      	      break;
      	   case 'download':
      	      $this->download();
      	      break;
           case 'explore':            // added Dec 2020 by Janet Bjorndahl 
              $this->exploreTree();
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
   		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('Trees')));
   		require_once('includes/SpecialUserlogin.php');
   		$form = new LoginForm($request);
   		$form->mainLoginForm("You need to log in to manage your trees<br/><br/>", '');
   	}
   }

   private function newTree() {
   	global $wgUser;
   	
      if ($this->newName) {
      	$db =& wfGetDB( DB_MASTER );
      	$db->begin();
		   $db->ignoreErrors(true);
         $status = FamilyTreeUtil::createFamilyTree($db, $wgUser->getName(), $this->newName);
		   $db->ignoreErrors(false);
		   if ($status == FTE_SUCCESS) {
		   	$db->commit();
			}
			else {
				$db->rollback();
			}
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

   private function emailTree() {
   	global $wrHostName, $wrProtocol, $wgLang, $wgUser, $IP;

    // Link is to explore function that replaced FTE (changed Dec 2020 Janet Bjorndahl; urlencoding added Nov 2021)
    $firstTitle = SpecialTrees::getExploreFirstTitle($wgUser->getName(), $this->name);
   	$exploreLink = $wrProtocol.'://'.$wrHostName.'/w/index.php?title=' . $firstTitle->getPrefixedURL(). '&mode=explore&user=' . urlencode($wgUser->getName())
                      . '&tree=' . urlencode($this->name) . '&liststart=0&listrows=20';
   	// Added link to allow recipient to copy the tree (Dec 2020 by Janet Bjorndahl; urlencoding added Nov 2021)
    $copyLink = $wrProtocol.'://'.$wrHostName.'/w/index.php?title=Special:CopyTree&user=' . urlencode($wgUser->getname()) 
                      . '&name=' . urlencode($this->name);

/*    
   	$link = $wrProtocol.'://'.$wrHostName.'/fte/index.php?userName='. urlencode($wgUser->getName()) . '&treeName=' . urlencode($this->name);
   	$primaryPage = $this->getPrimaryPage();
   	if ($primaryPage) {
   		$link .= '&page='.urlencode($primaryPage->getPrefixedText());
   	}
*/   	
   	$subject = wfMsg('sharetreesubject');
   	$text = wfMsg('sharetreetext', $exploreLink, $copyLink);
   	$request = new FauxRequest(array('returnto' => $wgLang->specialPage('Trees'), 'wpSubject' => $subject, 'wpText' => $text));
		require_once("$IP/extensions/other/SpecialEmail.php");
		$form = new EmailForm($request);
		$form->execute();
   }

   private function renameTree() {
   	global $wgUser;

      if ($this->name && $this->newName) {
         $renameExisting = false;
         $db =& wfGetDB( DB_MASTER );
         $familyTrees = FamilyTreeUtil::getFamilyTrees($wgUser->getName(), false, $db);
         if (!is_null($familyTrees)) {
            foreach($familyTrees as $familyTree) {
               if ($familyTree['name'] == $this->newName) {
                  $renameExisting = true;
                  break;
               }
            }
         }

         if (!$renameExisting || $this->confirmed) {
            $db->begin();
            $db->ignoreErrors(true);
            $status = FamilyTreeUtil::renameFamilyTree($db, $wgUser->getName(), $this->name, $this->newName, $renameExisting);
            $db->ignoreErrors(false);
            if ($status == FTE_SUCCESS) {
               $db->commit();
            }
            else {
               $db->rollback();
            }
            if ($status != FTE_SUCCESS) {
               $msg = 'Error renaming '.$this->name;
            }
            else {
               $msg = $this->name.($renameExisting ? ' was merged into ' : ' was renamed to ').$this->newName.
                       ".  It will take about an hour to re-index the pages so that they are searchable under the new ".
                       ($renameExisting ? 'tree.' : 'name.');
            }
            $this->show($msg);
         }
         else if ($this->name == $this->newName) {
            $this->show('This tree is already named '.$this->newName);
         }
         else if ($this->canceled) {
            $this->show();
         }
         else {
            $label = 'Are you sure you want to merge <b>'.htmlspecialchars($this->name).'</b> into <b>'.htmlspecialchars($this->newName).'</b>?<br>';
            $newNameField = '<input type="hidden" name="newName" value="'.htmlspecialchars($this->newName).'"/>';
            $field = $label.$newNameField.'<input type="submit" name="confirmed" value="Yes"/>';
            $this->showInputForm('Are you sure?', $field, 'canceled', 'No');
         }
      }
      else {
         $text = wfMsgWikiHtml('TreeRenameMsg');
      	$label = 'New name';
			$field = $text.'<br>'.$label.': <input type="text" name="newName"/>';
      	$this->showInputForm('Rename/Merge tree: ' . $this->name, $field, 'go', 'Go');
      }
   }

   private function deleteTree() {
   	global $wgUser, $wrHostName, $wrAdminUserName;

	   if ($this->user && $wgUser->getName() == $wrAdminUserName) {
	   	$userName = $this->user;
	   }
	   else {
	   	$userName = $wgUser->getName();
	   }
	   
   	if ($this->name && $userName && ($userName != $wgUser->getName() || $this->confirmed)) {
      	$db =& wfGetDB( DB_MASTER );
      	$db->begin();
		   $db->ignoreErrors(true);
		   $status = FamilyTreeUtil::deleteFamilyTree($db, $userName, $this->name, true);
		   $db->ignoreErrors(false);
		   if ($status == FTE_SUCCESS) {
		   	$db->commit();
			}
			else {
				$db->rollback();
			}
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
         $label = wfMsgWikiHtml('TreeDeleteMsg', htmlspecialchars($this->name), urlencode($this->name), urlencode($userName));
      	$field = $label.'<br><input type="submit" name="confirmed" value="Yes"/>';
      	$this->showInputForm('Are you sure?', $field, 'canceled', 'No');
      }
   }
   
   private function exportGedcom() {
   	global $wgUser;
   	
   	$userName = $wgUser->getName();
		$dbr =& wfGetDB( DB_SLAVE );
   	$treeId = $dbr->selectField('familytree', 'ft_tree_id', array('ft_user' => $userName, 'ft_name' => $this->name));
   	
   	if (!$treeId) {
   		$this->show("Tree not found");
   	}
   	else {
	      $job = new GedcomExportJob(array('tree_id' => $treeId, 'user' => $userName, 'name' => $this->name));
	      $job->insert();
	   	$this->show("Your GEDCOM file is being created.  This typically takes 10-30 minutes.  You will receive a message on your talk page when the GEDCOM is ready.");
   	}
   }
   
   private function downloadFile($file, $filename) {
   	global $wgOut;
   	
   	// write out gedcom
		$wgOut->disable();
		header("Pragma: public");
		header("Expires: 0"); // set expiration time
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: private",false);
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment; filename=\"".urlencode($filename)."\";");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".filesize($file));

		@readfile($file);
   }

   private function download() {
   	global $wgUser, $wrGedcomArchiveDirectory;
   	
   	// verify gedcomId is for this user
		$dbr =& wfGetDB( DB_SLAVE );
   	$filename = $dbr->selectField(array('familytree_gedcom', 'familytree'), 'fg_gedcom_filename',
   											array('fg_id' => $this->gedcomId, 'fg_tree_id = ft_tree_id', 'ft_user' => $wgUser->getName()));
   	if (!$filename) {
   		$this->show('You cannot download this GEDCOM');
   	}
   	else {
	   	// get gedcom file
	   	$file = "$wrGedcomArchiveDirectory/{$this->gedcomId}.ged";

	   	$this->downloadFile($file, $filename);
   	}
   }
   
   private function downloadExport() {
		global $wrGedcomExportDirectory;

		// get tree id
		$dbr =& wfGetDB( DB_SLAVE );
   	$treeId = $dbr->selectField('familytree', 'ft_tree_id', array('ft_user' => $this->user, 'ft_name' => $this->name));
  		$file = "$wrGedcomExportDirectory/$treeId.ged";

   	if (!file_exists($file)) {
   		$this->show('GEDCOM file not found');
   	}
   	else {
   		$this->downloadFile($file, "{$this->name}.ged");
   	}
   }
   
   // Function exploreTree added Dec 2020 by Janet Bjorndahl
   // Handle a request to explore a tree from a User's talk page (GEDCOM import message)
   private function exploreTree() {
     global $wrHostName, $wgOut;

     // Can only explore a tree with at least one page - check count of pages in the tree
     $dbr =& wfGetDB(DB_SLAVE);
     $dbr->ignoreErrors(true);
     $sql = 'SELECT ft_tree_id, ft_name, (SELECT count(*) FROM familytree_page WHERE fp_tree_id = ft_tree_id) AS cnt' .
             ' FROM familytree WHERE ft_user = '. $dbr->addQuotes($this->user) . ' AND ft_name = ' . $dbr->addQuotes($this->name);
     $rows = $dbr->query($sql, 'exploreTree');
     $errno = $dbr->lastErrno();
     if ( $errno > 0 ) {
        $this->show();
        return;
     }
     $row = $dbr->fetchObject($rows);
     if ( !$row || $row->cnt == 0 ) {
        $this->show();
        if ($this->name) {
          $wgOut->setSubtitle("<font size=\"2\" color=\"red\"> Tree " . $this->name . " is empty. If you just imported a GEDCOM, please try again later.</font>");
        }
        return;
     }
     $dbr->freeResult($rows);

     $firstTitle = $this->getExploreFirstTitle($this->user, $this->name);
     $wgOut->redirect('http://'.$wrHostName.'/w/index.php?title=' . $firstTitle->getPrefixedURL(). '&mode=explore&user=' . urlencode($this->user)
           . '&tree=' . urlencode($this->name) . '&liststart=0&listrows=20');      // urlencoding added Nov 2021 by Janet Bjorndahl
   }
   
   private function showInputForm($title, $field, $submitName, $submitValue) {
      global $wgOut;

      $wgOut->setPagetitle(htmlspecialchars($title));
      $wgOut->setArticleRelated(false);
      $wgOut->setRobotpolicy('noindex,nofollow');
		$titleObj = Title::makeTitle( NS_SPECIAL, 'Trees' );
		$formAction = $titleObj->escapeLocalURL();
		$name = htmlspecialchars($this->name);
		$submitName = htmlspecialchars($submitName);
		$submitValue = htmlspecialchars($submitValue);
		$action = htmlspecialchars($this->action);
		
		$wgOut->addHTML(<<< END
<form method='post' action="$formAction">
<input type="hidden" name="name" value="$name"/>
<input type="hidden" name="action" value="$action"/>
{$field}
<input type='submit' name="$submitName"" value="$submitValue"/>
</form>
END
		);
	}

   private function show($msg='') {
   	global $wgUser, $wgOut;

		$wgOut->setPageTitle( 'Manage My Trees' );        // renamed Dec 2020 by Janet Bjorndahl
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );

		$wgOut->addHTML("<h2>My Trees</h2>");             // renamed Dec 2020 by Janet Bjorndahl
		if ($msg) {
			$msg = htmlspecialchars($msg);
			$wgOut->addHTML( "<p><font size=\"+1\" color=\"red\">$msg</font></p>\n");
		}
		$wgOut->addHTML($this->getTrees());
   }

   private function getGedcoms($dbr, $id, $name) {
      global $wgUser;

   	$skin =& $wgUser->getSkin();
      $gedcoms = array();
   	$rows = $dbr->select('familytree_gedcom', array('fg_id', 'fg_gedcom_filename', 'fg_status', 'fg_status_date'), array('fg_tree_id' => $id, 'fg_status > 0 and fg_status < 100'), 'getGedcoms', array('ORDER BY' => 'fg_status_date'));
   	if ($rows !== false) {
      	while ($row = $dbr->fetchObject($rows)) {
      	   $status = htmlspecialchars(@FamilyTreeUtil::$STATUS_MESSAGES[$row->fg_status]);
      	   $filename = htmlspecialchars($row->fg_gedcom_filename);
      	   $download = '';
      	   switch ($row->fg_status) {
      	      case FG_STATUS_READY:
      	      case FG_STATUS_OPENED:
      	      	$tip = 'Download the GEDCOM that you uploaded to WeRelate';
				      $download = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Trees'), 'Download', 
				      					wfArrayToCGI(array('action' => 'download', 'gedcomId' => $row->fg_id)), '', '', '', " title=\"$tip\"");
      	         $status = 'Import: '.date("d M Y",wfTimestamp(TS_UNIX,$row->fg_status_date));
      	         break;
      	      case FG_STATUS_PHASE2:
      	      	$status = '<a href="/gedcom-review/?gedcomId='.$row->fg_id.'" rel="nofollow">Waiting for review</a>';    // changed to new reviewer Dec 2020 by Janet Bjorndahl
      	      	break;
      	   }
  	         $gedcoms[] = $filename . ($status ? '<br>&nbsp;&nbsp;&nbsp;' . $status : '') . ($download ? '<br>&nbsp;&nbsp;&nbsp;' . $download : '');
      	}
			$dbr->freeResult($rows);
   	}

   	if (count($gedcoms) == 0) {
	      $tip = 'Import a GEDCOM file into this tree';
	      $gedcoms[] = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'ImportGedcom'), 'Import a GEDCOM', wfArrayToCGI(array('wrTreeName' => $name)), '', '', '', " title=\"$tip\"");
   	}
   	return join('<br/>',$gedcoms);
   }

   /** Construct SQL case statement to set namespace sort order for exploring a tree (added Aug 2020 by Janet Bjorndahl) */
   public static function getExploreTreeOrder() {
     global $wgSortedNamespaces;
   
     $case = 'CASE fp_namespace'; 
		 foreach( $wgSortedNamespaces as $namespace => $val ) {
			 $case .= ' WHEN ' . $val . ' THEN ' . $namespace;
		 }
     $case .= ' ELSE 999 END';
     return $case;
   }  

   /** Get title of first page for exploring a family tree (refactored Sep 2020 by Janet Bjorndahl) */
   public static function getExploreFirstTitle($userName, $treeName) {
     global $wgUser;
     
     // Issue db query to get the first page to display
   	 $skin =& $wgUser->getSkin();
     $dbr =& wfGetDB (DB_SLAVE);
     $sql = 'SELECT fp_namespace, fp_title FROM familytree_page USE INDEX (PRIMARY)'
		      . ' WHERE fp_tree_id = (SELECT ft_tree_id FROM familytree WHERE ft_user = ' . $dbr->addQuotes($userName) 
          . ' AND ft_name = ' . $dbr->addQuotes($treeName) . ')'
          . ' ORDER BY ' . SpecialTrees::getExploreTreeOrder() . ', fp_title'
		      . ' LIMIT 1;';
  	 $res = $dbr->query($sql, 'ExploreFamilyTree');
     $firstPage = $dbr->fetchObject( $res );
     $firstTitle = Title::newFromText($firstPage->fp_title, $firstPage->fp_namespace);
     return $firstTitle;   
   }
   
   public function getTrees() {
      global $wgUser, $wrHostName;

   	$skin =& $wgUser->getSkin();

      $ret = '<div id="familytree-table"><table width="99%" cellpadding="5" cellspacing="0" border="0">'.
              '<tr><td><b>Name</b></td><td><b>Pages</b></td><td><b>Imported GEDCOMs</b></td><td><b>Check for issues</b></td><td><b>Export a GEDCOM</b></td><td><b>Rename / Merge tree</b></td><td><b>Related pages not in tree</b></td><td><b>Other watchers</b></td><td><b>E-mail (share)</b></td><td><b>Deletion impact</b></td></tr>';   // Delete tree removed Dec 2020 by Janet Bjorndahl
/*
      $ret = '<div id="familytree-table"><table width="99%" cellpadding="5" cellspacing="0" border="0">'.
              '<tr><td><b>Name</b></td><td><b>Pages</b></td><td><b>Imported GEDCOMs</b></td><td><b>Export a GEDCOM</b></td><td><b>Rename / Merge tree</b></td><td><b>Related pages not in tree</b></td><td><b>Other watchers</b></td><td><b>E-mail (share)</b></td><td><b>Deletion impact</b></td><td><b>Delete</b></td></tr>';
*/
		$db =& wfGetDB( DB_MASTER ); // make sure we show just-added or renamed trees
		$familyTrees = FamilyTreeUtil::getFamilyTrees($wgUser->getName(), true, $db);
      if (!is_null($familyTrees)) {
         foreach($familyTrees as $familyTree) {
            $gedcom = $this->getGedcoms($db, $familyTree['id'], $familyTree['name']);
            $quality = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'DataQuality'), 'check', wfArrayToCGI(array('tree' => $familyTree['id']))); // added Jun 2022 JB
            $export = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Trees'), 'export', wfArrayToCGI(array('action'=> 'exportGedcom', 'name' => $familyTree['name'])), 
            				'', '', '', ' title="Export a GEDCOM file of the pages in this tree"');
            $rename = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Trees'), 'rename&nbsp;/&nbsp;merge', wfArrayToCGI(array('action'=> 'renameTree', 'name' => $familyTree['name'])),
            				'', '', '', ' title="Rename this tree"');
            if ($familyTree['count'] > 0) {  // email link only if tree has at least one page, because the recipient can explore only if at least 1 page (changed Dec 2020 by Janet Bjorndahl)
              $email = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Trees'), 'e-mail', wfArrayToCGI(array('action' => 'emailTree', 'name' => $familyTree['name'])));
            }
            else {
              $email ='';
            }
            $relatedPages = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'TreeRelated'), 'related pages', wfArrayToCGI(array('user' => $wgUser->getName(), 'tree' => $familyTree['name'])));
            $countWatchers = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'TreeCountWatchers'), 'watchers', wfArrayToCGI(array('user' => $wgUser->getName(), 'tree' => $familyTree['name'])));
            $deletionImpact = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'TreeDeletionImpact'), 'impact', wfArrayToCGI(array('user' => $wgUser->getName(), 'tree' => $familyTree['name'])));
            $delete = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Trees'), 'delete', wfArrayToCGI(array('action' => 'deleteTree', 'name' => $familyTree['name'])));
            $search = $skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Search'), 'search', wfArrayToCGI(array('k' => '+Tree:"'.$wgUser->getName().'/'.$familyTree['name'].'"')));
            
//            $fte = '<a href="/fte/index.php?'.wfArrayToCGI(array('userName' => $wgUser->getName(), 'treeName' => $familyTree['name'])).'">launch&nbsp;FTE</a>';  removed Dec 2020
            $ret .= '<tr><td>' . htmlspecialchars($familyTree['name']) . ' <span class="plainlinks">'
              . " (&nbsp;$search&nbsp;)";
//              . " (&nbsp;$fte&nbsp;)";   removed Dec 2020
            // Allow user to explore a tree only if the tree has at least one page, because exploring starts with displaying a page (added Aug 2020 by Janet Bjorndahl)     
		        if ($familyTree['count'] > 0) { 
              $explore = $skin->makeKnownLinkObj($this->getExploreFirstTitle($wgUser->getName(), $familyTree['name']), 'explore', 
                wfArrayToCGI(array('mode' => 'explore', 'user' => $wgUser->getName(), 'tree' => $familyTree['name'], 'liststart' => '0', 'listrows' => '20', 'listns' => '')));
              $ret .= " (&nbsp;$explore&nbsp;)";
              }
            $ret .= '</span><td>' . $familyTree['count'] . "</td><td>$gedcom</td><td>$quality</td><td>$export</td><td>$rename</td><td>$relatedPages</td><td>$countWatchers</td><td>$email</td><td>$deletionImpact</td></tr>";   // delete tree link removed Dec 2020 by Janet Bjorndahl
/*            
            $ret .= '</span><td>' . $familyTree['count'] . "</td><td>$gedcom</td><td>$export</td><td>$rename</td><td>$relatedPages</td><td>$countWatchers</td><td>$email</td><td>$deletionImpact</td><td>$delete</td></tr>";
*/            
         }
      }

      $ret .= '</table></div>';
      $ret .= '<p>'.$skin->makeKnownLinkObj(Title::makeTitle(NS_SPECIAL, 'Trees'), 'Create a new tree', wfArrayToCGI(array('action' => 'newTree')));   // link renamed Dec 2020 JB
      return $ret;
   }
}
?>
