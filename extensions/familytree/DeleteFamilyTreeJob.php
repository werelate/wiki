<?php
require_once("$IP/includes/JobQueue.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");

class DeleteFamilyTreeJob extends Job {
   function __construct($params, $id = 0 ) {
      // pass up a fake title
      parent::__construct('deleteFamilyTree', Title::makeTitle(NS_SPECIAL, 'DeleteFamilyTree'), $params, $id );
   }

   /**
    * Run a refreshLinks job
    * @return boolean success
    */
   function run() {
      global $wgUser, $wgTitle, $wrIsTreeDeletion;

      $status = FTE_SUCCESS;
      $user = $this->params['user'];
      $wgUser = User::newFromName($user);
      $treeId = $this->params['tree_id'];
      if ($treeId == 9662) return false;
      $delPages = ($this->params['delete_pages'] == 1);
      $wgTitle = $this->title;  // FakeTitle (the default) generates errors when accessed, and sometimes I log wgTitle, so set it to something else

      $dbw =& wfGetDB( DB_MASTER );
      $dbw->begin();
      $dbw->ignoreErrors(true);

      if ($delPages) {
         // Delete the page if it is in this tree
         //   and is in one of the 4 deletable namespaces
         //   and nobody else is watching the page
         //   and it is not in another of the users trees
         // Keep this query in sync with SpecialTreeDeletionImpact.php
         $sql = 'SELECT fp_namespace, fp_title FROM familytree_page AS fp1'.
                 ' WHERE fp_tree_id='.$dbw->addQuotes($treeId).
                 ' and fp_namespace in ('.NS_IMAGE.','.NS_PERSON.','.NS_FAMILY.','.NS_MYSOURCE.')'.
                 ' and not exists (SELECT 1 FROM watchlist WHERE wl_namespace = fp_namespace and wl_title = fp_title and wl_user <> fp_user_id)'.
                 ' and not exists (SELECT 1 FROM familytree_page AS fp2 WHERE fp2.fp_namespace = fp1.fp_namespace and fp2.fp_title = fp1.fp_title and fp2.fp_user_id = fp1.fp_user_id and fp2.fp_tree_id <> fp1.fp_tree_id)';
         $rows = $dbw->query($sql);
         $errno = $dbw->lastErrno();
         if ($errno > 0) {
            $status = FTE_DB_ERROR;
         }
         else if ($rows !== false) {
            $titles = array();
            while ($row = $dbw->fetchObject($rows)) {
               $title = Title::makeTitle($row->fp_namespace, $row->fp_title);
               $talkTitle = $title->getTalkPage();
               if ($title->exists()) {
                  $titles[] = $title;
                  PropagationManager::addBlacklistPage($title);
               }
               if ($talkTitle->exists()) {
                  $titles[] = $talkTitle;
               }
            }
            $dbw->freeResult($rows);
            $wrIsTreeDeletion = true;
            foreach ($titles as $title) {
               $status = ftDelPage($title, false);
               if ($status == FTE_NOT_AUTHORIZED) {
                  $this->error = "While deleting $treeId tried to delete a page not authorized to delete: ".$title->getPrefixedText()."\n";
               }
               if ($status != FTE_SUCCESS) {
                  break;
               }
            }
         }

         // remove remaining pages from watchlist
         if ($status == FTE_SUCCESS) {
            $sql = 'SELECT fp_namespace, fp_title FROM familytree_page AS fp1'.
                    ' WHERE fp_tree_id='.$dbw->addQuotes($treeId).
                    ' and not exists (SELECT 1 FROM familytree_page AS fp2 WHERE fp2.fp_namespace = fp1.fp_namespace and fp2.fp_title = fp1.fp_title and fp2.fp_user_id = fp1.fp_user_id and fp2.fp_tree_id <> fp1.fp_tree_id)';
            $rows = $dbw->query($sql);
            $errno = $dbw->lastErrno();
            if ($errno > 0) {
               $status = FTE_DB_ERROR;
            }
            else if ($rows !== false) {
               while ($row = $dbw->fetchObject($rows)) {
                  $title = Title::makeTitle($row->fp_namespace, $row->fp_title);
                  $wgUser->removeWatch($title);
               }
               $dbw->freeResult($rows);
            }
         }

         // remove familytree_page's
         //   If we delete pages that are unwatched by others but in someone else's tree, then this code won't delete them from the others' trees
         //   We need to ensure that all pages in trees are watched.
         if ($status == FTE_SUCCESS) {
            $dbw->delete('familytree_page', array('fp_tree_id' => $treeId));
            $errno = $dbw->lastErrno();
            if ($errno > 0) {
               $status = FTE_DB_ERROR;
            }
         }
         if ($status == FTE_SUCCESS) {
            // remove familytree_data's
            $dbw->delete('familytree_data', array('fd_tree_id' => $treeId));
            $errno = $dbw->lastErrno();
            if ($errno > 0) {
               $status = FTE_DB_ERROR;
            }
// keep familytree_gedcom in case we want to look at it later
         }

         if ($status == FTE_SUCCESS) {
            $dbw->commit();
            return true;
         }
         else {
            $dbw->rollback();
            if (!$this->error) {
               $this->error = "Error deleting tree; status=$status\n";
            }
            return false;
         }
      }
   }
}
?>
