<?php

/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');

# Register with AjaxDispatcher as a function
$wgAjaxExportList[] = "wfGetTreeData";

function wfGetTreeData($rsargs = null) {
	global $wgAjaxCachePolicy, $wgRequest;

   $callback = $wgRequest->getVal('callback');
   $orn = $wgRequest->getVal('orn');
   $titleText = $wgRequest->getVal('id');
   $setRoot = $wgRequest->getBool('setRoot');
   $numDescLevels = $wgRequest->getVal('descLevels');
   if ($numDescLevels > 10) $numDescLevels = 10;
   $numAncLevels = $wgRequest->getVal('ancLevels');
   if ($numAncLevels > 20) $numAncLevels = 20;

   // set cache policy
	$wgAjaxCachePolicy->setPolicy(0);
   // set content type
   $wgAjaxCachePolicy->setContentType($callback ? 'application/javascript' : 'application/json');

   $data = null;
   $treeData = new TreeData();
	if ($titleText) {
		$title = Title::newFromText($titleText);
      if ($title) {
         if ($setRoot) {
            // we can get a person initially and when someone sets a new root
            if ($orn == 'left') {
               $numDescLevels = 0;
               if (!$numAncLevels) $numAncLevels = $title->getNamespace() == NS_PERSON ? 7 : 6;
            }
            else {
               if (!$numDescLevels) $numDescLevels = $title->getNamespace() == NS_PERSON ? 1 : 2;
               $numAncLevels = 0;
            }
         }
         else if ($orn == 'left') {
            $numDescLevels = 0;
            if (!$numAncLevels) $numAncLevels = 2;
         }
         else if ($orn == 'right') {
            if (!$numDescLevels) $numDescLevels = 2;
            $numAncLevels = 0;
         }
         else {
            // make sure we have the final redirect
            $title = StructuredData::getRedirectToTitle($title);
            $orn = 'center';
            if ($title->getNamespace() == NS_PERSON) {
               if (!$numDescLevels) $numDescLevels = 1;
               if (!$numAncLevels) $numAncLevels = 7;
            }
            else {
               if (!$numDescLevels) $numDescLevels = 2;
               if (!$numAncLevels) $numAncLevels = 6;
            }
         }
         if ($title->getNamespace() == NS_PERSON) {
            $data = $treeData->getPersonData($title->getText(), $orn, $numDescLevels, $numAncLevels);
         }
         else if ($title->getNamespace() == NS_FAMILY) {
            $data = $treeData->getFamilyData($title->getText(), $orn, $numDescLevels, $numAncLevels);
         }
      }
   }
   if (!$data) {
      $data = array('error' => 'invalid title');
   }
   $json = StructuredData::json_encode($data);
   if ($callback) {
      $json = $callback.'('.$json.');';
   }
   return $json;
}

class TreeData {
   const PERSON_ICON_WIDTH = 32;
   const PERSON_ICON_HEIGHT = 32;
   const FAMILY_ICON_WIDTH = 16;
   const FAMILY_ICON_HEIGHT = 16;
   const MAN_ICON = '/common/images/man-icon.png';
   const WOMAN_ICON = '/common/images/woman-icon.png';
   const MAN_THUMB = '/common/images/man.png';
   const WOMAN_THUMB = '/common/images/woman.png';
   const FAMILY_ICON = '/common/images/family-icon.png';

	public function __construct() {
	}

   public function getPersonData($titleString, $orn, $numDescLevels, $numAncLevels) {
      global $wgServer, $wgStylePath;

      $person = new Person($titleString);
      $xml = $person->getPageXml(true);
      $obj = array();
      if (isset($xml)) {
         $fullname = StructuredData::getFullname($xml->name);
         $obj['name'] = $fullname;
         $obj['id'] = $person->getTitle()->getPrefixedDBkey();

         // populate data
         $data = array();
         list($birthYear, $birthDate, $birthPlace, $eventTypeIndex) = $this->getEventData($xml, array('Birth', 'Christening', 'Baptism'));
         if ($eventTypeIndex >= 0) $data['birthlabel'] = ($eventTypeIndex === 0 ? 'b' : 'chr');
         list($deathYear, $deathDate, $deathPlace, $eventTypeIndex) = $this->getEventData($xml, array('Death', 'Burial'));
         if ($eventTypeIndex >= 0) $data['deathlabel'] = ($eventTypeIndex === 0 ? 'd' : 'bur');
         $thumbURL = $this->getPrimaryImage($xml, true);
         $data['url'] = $person->getTitle()->getFullURL();
         $data['gender'] = (string)$xml->gender;
         if ($birthYear || $deathYear) $data['yearrange'] = "$birthYear - $deathYear";
         if ($birthDate) $data['birthdate'] = $birthDate;
         if ($birthPlace) $data['birthplace'] = $birthPlace;
//         if ($birthPlaceUrl) $data['birthplaceurl'] = $birthPlaceUrl;
         if ($deathDate) $data['deathdate'] = $deathDate;
         if ($deathPlace) $data['deathplace'] = $deathPlace;
//         if ($deathPlaceUrl) $data['deathplaceurl'] = $deathPlaceUrl;
         //$data['gender'] = (string)$xml->gender;
         $data['type'] = 'Person';
         $data['$orn'] = $orn;
         if ($thumbURL) $data['thumb'] = $this->makeImgTag($thumbURL, $fullname);
//         $defaultIcon = (string)$xml->gender == 'F' ? TreeData::WOMAN_ICON : TreeData::MAN_ICON;
//         $data['icon'] = $this->makeImgTag($iconURL ? $iconURL : $wgServer.$wgStylePath.$defaultIcon, $fullname);

         /* Code to add message about user trees added Sep 2020 by Janet Bjorndahl */
         $pageInExploreTree = $this->getUserTreesForPage(NS_PERSON, $titleString, 10, $treeLabel, $data['treestring']); // note: sets values of $treeLabel and $data['treestring']
         $data['treelabel'] = $treeLabel;
         if ( $_SESSION['isExploreContext'] && !$pageInExploreTree ) {
           $data['treemsg'] = 'not in tree';
         }
         else {
           $data['treemsg'] = '';
         }  

         $obj['data'] = $data;

         $children = array();
         if ($numAncLevels > 0) {
            foreach ($xml->child_of_family as $f) {
               $data = $this->getFamilyData((string)$f['title'], 'left', 0, $numAncLevels-1);
               if ($data) $children[] = $data;
            }
         }
         if ($numDescLevels > 0) {
            // sort by marriage date
            $sort = array();
            $ix = 0;
            $prevKey = 0;
            foreach ($xml->spouse_of_family as $f) {
               $family = $this->getFamilyData((string)$f['title'], 'right', $numDescLevels-1, 0);
               if ($family) {
                  $key = DateHandler::getDateKey(@$family['data']['marriagedate'], true);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl
                  if ($key) {
                     $prevKey = $key;
                  }
                  else {
                     $key = $prevKey;
                  }
                  $sort[$key*50+$ix] = $family;
                  $ix++;
               }
            }
            ksort($sort, SORT_NUMERIC);
            foreach ($sort as $key => $family) {
               $children[] = $family;
            }
         }
         $obj['children'] = $children;
      }

      return $obj;
   }

   public function getFamilyData($titleString, $orn, $numDescLevels, $numAncLevels) {
      global $wgServer, $wgStylePath;

      $family = new Family($titleString);
      $xml = $family->getPageXml(true);
      $obj = array();
      if (isset($xml)) {
         $obj['name'] = 'Family';
         $obj['id'] = $family->getTitle()->getPrefixedDBkey();

         // populate data
         list($marriageYear, $marriageDate, $marriagePlace, $eventTypeIndex) = $this->getEventData($xml, array('Marriage'));
         $thumbURL = $this->getPrimaryImage($xml, false);
         $data = array();
         $data['url'] = $family->getTitle()->getFullURL();
         $found = false;
         list($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($titleString);
         foreach ($xml->husband as $p) {
            $person = $this->getPersonSummary($p);
            if ($person['name']) $data['husbandname'] = $person['name'];
            if ($person['url']) $data['husbandurl'] = $person['url'];
            if ($person['yearrange']) $data['husbandyearrange'] = $person['yearrange'];
            $found = true;
            break;
         }
         if (!$found) {
            $data['husbandname'] = "$hg $hs";
         }
         $found = false;
         foreach ($xml->wife as $p) {
            $person = $this->getPersonSummary($p);
            if ($person['name']) $data['wifename'] = $person['name'];
            if ($person['url']) $data['wifeurl'] = $person['url'];
            if ($person['yearrange']) $data['wifeyearrange'] = $person['yearrange'];
            $found = true;
            break;
         }
         if (!$found) {
            $data['wifename'] = "$wg $ws";
         }

         $children = array();
         // sort by birth date -- use sort for fetching children as well
         $sort = array();
         $ix = 0;
         $prevKey = 0;
         foreach ($xml->child as $p) {
            $person = $this->getPersonSummary($p);
            $key = DateHandler::getDateKey($person['birthdate'], true);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl
            if ($key) {
               $prevKey = $key;
            }
            else {
               $key = $prevKey;
            }
            $sort[$key*50+$ix] = $person;
            $ix++;
         }
         ksort($sort, SORT_NUMERIC);
         $ix = 0;
         foreach ($sort as $key => $person) {
            if ($ix++ == 12) {
               $data['morechildren'] = 'true';
               break;
            }
            $child = array();
            if ($person['name']) $child['name'] = $person['name'];
            if ($person['url']) $child['url'] = $person['url'];
            if ($person['yearrange']) $child['yearrange'] = $person['yearrange'];
            $children[] = $child;
         }
         $data['children'] = $children;

         if ($marriageDate) $data['marriagedate'] = $marriageDate;
         if ($marriagePlace) $data['marriageplace'] = $marriagePlace;
//         if ($marriagePlaceUrl) $data['marriageplaceurl'] = $marriagePlaceUrl;
         $data['type'] = 'Family';
         $data['$orn'] = $orn;
         if ($thumbURL) $data['thumb'] = $this->makeImgTag($thumbURL, $titleString);
//         $data['icon'] = $this->makeImgTag($wgServer.$wgStylePath.TreeData::FAMILY_ICON, 'Family', TreeData::FAMILY_ICON_WIDTH, TreeData::FAMILY_ICON_HEIGHT);

         /* Code to add list of user trees added Sep 2020 by Janet Bjorndahl */
         $pageInExploreTree = $this->getUserTreesForPage(NS_FAMILY, $titleString, 10, $treeLabel, $data['treestring']); // note: sets values of $treeLabel and $data['treestring']
         $data['treelabel'] = $treeLabel;
         
         $obj['data'] = $data;

         // populate children
         $children = array();
         if ($numAncLevels > 0) {
            foreach ($xml->husband as $p) {
               $data = $this->getPersonData((string)$p['title'], 'left', 0, $numAncLevels-1);
               if ($data) $children[] = $data;
            }
            foreach ($xml->wife as $p) {
               $data = $this->getPersonData((string)$p['title'], 'left', 0, $numAncLevels-1);
               if ($data) $children[] = $data;
            }
         }
         if ($numDescLevels > 0) {
            foreach ($sort as $key => $person) {
               $data = $this->getPersonData($person['title'], 'right', $numDescLevels-1, 0);
               if ($data) $children[] = $data;
            }
         }
         $obj['children'] = $children;
      }

      return $obj;
   }

   private function makeImgTag($url, $name, $width=0, $height=0) {
      return "<img src=\"${url}\" alt=\"${name}\"".($width ? " width=\"${width}px\"" : '').($height ? " height=\"${height}px\"" : '').'/>';
   }

   private function getPersonSummary($member) {
      $yearrange = '';
      $url = '';
      $title = (string)$member['title'];
      $t = Title::newFromText($title, NS_PERSON);
      if ($t) {
         $url = $t->getFullURL();
      }
      $fullname = StructuredData::getFullname($member);
      $birthDate = (string)$member['birthdate'] ? (string)$member['birthdate'] : (string)$member['chrdate'];
      $beginYear = DateHandler::getYear($birthDate, true);       // changed to DateHandler function Oct 2020 by Janet Bjorndahl
      $endYear = DateHandler::getYear((string)$member['deathdate'] ? (string)$member['deathdate'] : (string)$member['burialdate'], true);
      if ($beginYear || $endYear) {
         $yearrange = "$beginYear - $endYear";
      }

      return array('name' => $fullname, 'url' => $url, 'yearrange' => $yearrange, 'birthdate' => $birthDate, 'title' => $title);
   }

	private function getEventData($xml, $eventTypes) {
      $eventTypeIndex = 0;
		foreach ($eventTypes as $type) {
			foreach ($xml->event_fact as $event_fact) {
				if ((string)$event_fact['type'] == $type) {
					$eventDate = (string)$event_fact['date'];
					$eventYear = substr(DateHandler::getDateKey($eventDate), 0, 4);     // changed to DateHandler function Oct 2020 by Janet Bjorndahl
               $eventPlace = '';
//               $eventPlaceUrl = '';
               $place = (string)$event_fact['place'];
               if ($place) {
                  $pos = mb_strpos($place, '|');
                  if ($pos !== false) {
                     $eventPlace = mb_substr($place, $pos+1);
//                     $placeTitle = mb_substr($place, 0, $pos);
                  }
                  else {
                     $eventPlace = $place;
//                     $placeTitle = $place;
                  }
//                  $t = Title::newFromText($placeTitle, NS_PLACE);
//                  if ($t) {
//                     $eventPlaceUrl = $t->getFullURL();
//                  }
               }

					return array($eventYear, $eventDate, $eventPlace, $eventTypeIndex);
				}
			}
         $eventTypeIndex++;
		}
		return array('', '', '', -1);
	}

   private function getPrimaryImage($xml, $isPerson) {
      global $wgServer, $wgStylePath;

      foreach ($xml->image as $image) {
         if ($image['primary'] == 'true') {
            $t = Title::makeTitle(NS_IMAGE, (string)$image['filename']);
            if ($t && $t->exists()) {
               $image = new Image($t);
               $thumbURL = $wgServer.$image->createThumb(SearchForm::THUMB_WIDTH, SearchForm::THUMB_HEIGHT);
//               if ($isPerson) {
//                  $iconURL = $wgServer.$image->createThumb(TreeData::PERSON_ICON_WIDTH, TreeData::PERSON_ICON_HEIGHT);
//               }
//               else {
//                  $iconURL = '';
//               }
               return $thumbURL;
            }
         }
      }
      if ($isPerson) {
         return $wgServer.$wgStylePath.((string)$xml->gender == 'F' ? TreeData::WOMAN_THUMB : TreeData::MAN_THUMB);
      }
      else {
         return '';
      }
   }
   
   /** Function getUserTreesForPage added Sep 2020 by Janet Bjorndahl
     * Sets parameter $treeString to a string of the names of the user's trees that the page is in (if the user is signed in). 
     * Returns an indicator of whether the page is in the tree the user is exploring, if the user is exploring a tree (false otherwise).
     */
   private function getUserTreesForPage($ns, $titleText, $maxTrees=10, &$treeLabel, &$treeString) {
     global $wgUser;
     
     $treeLabel = '';
     $dbr =& wfGetDB( DB_SLAVE );
     $dbTitle = str_replace(' ', '_', Sanitizer::decodeCharReferences($titleText));
     
     /* If the user is exploring a tree, determine whether this page is in the tree being explored. */ 
     $pageInExploreTree = false;
     if ( $_SESSION['isExploreContext'] ) {
       $sql = 'SELECT ft_name FROM familytree INNER JOIN familytree_page ON ft_tree_id = fp_tree_id'
         . ' WHERE ft_user = ' . $dbr->addQuotes($_SESSION['listParms']['user'])
         . ' AND fp_namespace = ' . $ns
         . ' AND fp_title = ' . $dbr->addQuotes($dbTitle)
         . ' ORDER BY ft_name';
       $res = $dbr->query($sql, 'getUserTreesforPage');
       $treeList = array();
	     while( $s = $dbr->fetchObject($res) ) {
         $treeList[] = $s->ft_name;
       }
       if ( in_array($_SESSION['listParms']['tree'], $treeList) ) {
         $pageInExploreTree = true;
       }  
     }
     
     /* If the user is signed in, generate a list of the user's trees that the page is in (limited to $maxTrees). 
        This can reuse the $treeList constructed above if the user is exploring one of their own user trees. Otherwise, 
        it needs to (re)read the database and (re)construct $treeList. */
     if ( $wgUser->isLoggedIn() ) {
       if ( !$_SESSION['isExploreContext'] || ($_SESSION['listParms']['user'] != $wgUser->getName()) ) {
         $sql = 'SELECT ft_name FROM familytree INNER JOIN familytree_page ON ft_tree_id = fp_tree_id'
           . ' WHERE ft_user = ' . $dbr->addQuotes($wgUser->getName())
           . ' AND fp_namespace = ' . $ns
           . ' AND fp_title = ' . $dbr->addQuotes($dbTitle)
           . ' ORDER BY ft_name LIMIT ' . ($maxTrees+1);
		     $res = $dbr->query($sql, 'getUserTreesforPage');
         $treeList = array();
		     while( $s = $dbr->fetchObject($res) ) {
           $treeList[] = $s->ft_name;
         }
       }
       if ( count($treeList) > $maxTrees ) {
         $treeList[$maxTrees] = 'etc.';
         for ($n=$maxTrees+1; $n<count($treeList); $n++) { // needed if reusing $treeList from above, which is not limited to $maxTrees+1 
           unset($treeList[$n]);
         }
       }
       if ( count($treeList) == 0 ) {
         $treeList[0] = 'none';
       }
       $treeLabel = 'My Trees:';
     $treeString = implode(", ", $treeList);
     }
     
     return $pageInExploreTree;  
   }
     
}
?>