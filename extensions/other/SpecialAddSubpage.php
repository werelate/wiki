<?php
/**
 * 
 * User: dallan
 * Date: 4/26/11
 */
 
# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialAddSubpageSetup";

function wfSpecialAddSubpageSetup() {
   global $wgMessageCache, $wgSpecialPages, $wgParser;
   $wgMessageCache->addMessages( array( "addsubpage" => "Add Subpage" ) );
   $wgSpecialPages['AddSubpage'] = array('SpecialPage','AddSubpage');
}

function wfSpecialAddSubpage($par) {
   global $wgOut, $wgRequest, $wgUser;

   $super = $wgRequest->getVal('super');
   $sub = $wgRequest->getVal('sub');
   $found = false;

   if ($super && $sub && $wgUser->isLoggedIn()) {
      $sub = mb_strtoupper(mb_substr($sub, 0, 1)).mb_substr($sub, 1);
      $subTitle = Title::newFromText("$super/$sub");
      if ($subTitle) {
         // add the page to the super page if it has a listsubpages tag
         $superTitle = Title::newFromText($super);
         if ($superTitle) {
            $r = Revision::newFromTitle($superTitle);
            if ($r) {
               $text =& $r->getText();
               if ($subTitle->exists()) {
                  $dbr =& wfGetDB(DB_SLAVE);
                  $timestamp = $dbr->selectField(array('page','revision'), 'rev_timestamp',
                                                 array('page_namespace' => $subTitle->getNamespace(),
                                                       'page_title' => $subTitle->getDBkey(),
                                                       'page_id = rev_page'),
                                                 'wfSpecialAddSubpage', array('order by rev_timestamp'));
                  $d = new DateTime(substr($timestamp, 0, 8));
                  $date = $d->format('j M Y');
               }
               else {
                  $date = date('j M Y');
               }
               $cnt = 0;
               $text = preg_replace('#(<listsubpages[^>]*>.*?)(</listsubpages>)#s',
                                    '${1}'.StructuredData::protectRegexReplace("$sub|$date")."\n".'${2}', $text, 1, $cnt);
               if ($cnt) {
                  $a = StructuredData::getArticle($superTitle);
                  $a->doEdit($text, 'Added '.$sub, EDIT_UPDATE);
               }
            }
         }

         $wgOut->redirect($subTitle->getFullURL('action=edit'));
         $found = true;
      }
   }
   if (!$found) {
      $super = htmlspecialchars($super);
      $sub = htmlspecialchars($sub);
      $wgOut->addHTML("<p><font color=red>Cannot create page: $super/$sub</font></p>");
   }
}
