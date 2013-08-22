<?php

/*
* index.php?action=ajax&rs=functionName&rsargs=a=v|b=v|c=v
*/

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");


# Register with AjaxDispatcher as a function
# call in this order to index
$wgAjaxExportList[] = "wfGetWatchlist";
$wgAjaxExportList[] = "wfGetTrees";
$wgAjaxExportList[] = "wfGetTree";

function wfGetTrees() {
   list ($user, $ns, $callback) = readListParams();

   $result = array();
   $error = null;

   if ($user->getID()) {
      $dbr =& wfGetDB(DB_SLAVE);
      $rows = $dbr->select('familytree', 'ft_name',
                           array('ft_user' => $user->getName()),
                           'wfGetTrees');
      $errno = $dbr->lastErrno();
      if ($errno == 0) {
         while ($row = $dbr->fetchObject($rows)) {
            $result[] = $row->ft_name;
         }
         $dbr->freeResult($rows);
      }
      else {
         $error = 'Can\'t read the database';
      }
   }
   else {
      $error = 'Unknown user';
   }

   if ($error) {
      $result = array('error' => $error);
   }
   $json = StructuredData::json_encode($result);
   if ($callback) {
      $json = $callback.'('.$json.');';
   }
   return $json;
}

function wfGetTree() {
   global $wgRequest;

   list ($user, $ns, $callback) = readListParams();
   $tree = $wgRequest->getVal('tree');

   $result = array();
   $error = null;

   if ($user->getID()) {
      $dbr =& wfGetDB(DB_SLAVE);
      $rows = $dbr->select(array('familytree','familytree_page'), 'fp_title',
                           array('ft_user' => $user->getName(), 'ft_name' => $tree,
                                 'fp_namespace' => $ns, 'ft_tree_id = fp_tree_id'),
                           'wfGetTree');
      $errno = $dbr->lastErrno();
      if ($errno == 0) {
         while ($row = $dbr->fetchObject($rows)) {
            $result[] = $row->fp_title;
         }
         $dbr->freeResult($rows);
      }
      else {
         $error = 'Can\'t read the database';
      }
   }
   else {
      $error = 'Unknown user';
   }

   if ($error) {
      $result = array('error' => $error);
   }
   $json = StructuredData::json_encode($result);
   if ($callback) {
      $json = $callback.'('.$json.');';
   }
   return $json;
}

function wfGetWatchlist() {
   list ($user, $ns, $callback) = readListParams();

   $result = array();
   $error = null;

   if ($user->getID()) {
      $dbr =& wfGetDB(DB_SLAVE);
      // map titles to trees
      $trees = array();
      $rows = $dbr->select(array('familytree','familytree_page'), array('ft_name','fp_title'),
                           array('ft_user' => $user->getName(),
                                 'fp_namespace' => $ns, 'ft_tree_id = fp_tree_id'),
                           'wfGetTree');
      $errno = $dbr->lastErrno();
      if ($errno == 0) {
         while ($row = $dbr->fetchObject($rows)) {
            $tree = @$trees[$row->fp_title];
            if ($tree) {
               $tree .= ', '.$row->ft_name;
            }
            else {
               $tree = $row->ft_name;
            }
            $trees[$row->fp_title] = $tree;
         }
         $dbr->freeResult($rows);

         // read watchlist
         $rows = $dbr->select('watchlist', array('wl_title', 'wr_flags', 'wr_summary'),
                              array('wl_user' => $user->getID(), 'wl_namespace' => $ns),
                              'wfGetWatchlist');
         $errno = $dbr->lastErrno();
         if ($errno == 0) {
            while ($row = $dbr->fetchObject($rows)) {
               $o = array('title' => $row->wl_title, 'flags' => $row->wr_flags, 'trees' => @$trees[$row->wl_title]);
               $fields = explode('|', $row->wr_summary);
               if ($ns == NS_PERSON) {
                  if ($fields[0]) $o['surname'] = $fields[0];
                  if ($fields[1]) $o['given'] = $fields[1];
                  if ($fields[2]) $o['gender'] = $fields[2];
                  if ($fields[3]) $o['birthDate'] = $fields[3];
                  if ($fields[4]) $o['birthPlace'] = $fields[4];
                  if ($fields[5]) $o['deathDate'] = $fields[5];
                  if ($fields[6]) $o['deathPlace'] = $fields[6];
               }
               else if ($ns == NS_FAMILY) {
                  if ($fields[0]) $o['husbandSurname'] = $fields[0];
                  if ($fields[1]) $o['husbandGiven'] = $fields[1];
                  if ($fields[2]) $o['wifeSurname'] = $fields[2];
                  if ($fields[3]) $o['wifeGiven'] = $fields[3];
                  if ($fields[4]) $o['marriageDate'] = $fields[4];
                  if ($fields[5]) $o['marriagePlace'] = $fields[5];
               }
               $result[] = $o;
            }
            $dbr->freeResult($rows);
         }
         else {
            $error = 'Can\'t read the database';
         }
      }
      else {
         $error = 'Can\'t read the database';
      }
   }
   else {
      $error = 'Unknown user';
   }

   if ($error) {
      $result = array('error' => $error);
   }
   $json = StructuredData::json_encode($result);
   if ($callback) {
      $json = $callback.'('.$json.');';
   }
   return $json;
}

function readListParams() {
   global $wgRequest, $wgUser;

   $userName = $wgRequest->getVal('user');
   if ($userName) {
      $user = User::newFromName($userName);
   }
   else {
      $user = $wgUser;
   }
   $ns = Namespac::getCanonicalIndex(strtolower($wgRequest->getVal('namespace')));
   if (!$ns) {
      $ns = NS_PERSON;
   }
   $callback = $wgRequest->getVal('callback');

   return array($user, $ns, $callback);
}