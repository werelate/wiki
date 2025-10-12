<?php
/**
 * MonoBook nouveau
 *
 * Translated from gwicke's previous TAL template version to remove
 * dependency on PHPTAL.
 *
 * @todo document
 * @package MediaWiki
 * @subpackage Skins
 */

if( !defined( 'MEDIAWIKI' ) )
	die( -1 );

/** */
require_once('includes/SkinTemplate.php');

/**
 * Inherit main code from SkinTemplate, set the CSS and template filter.
 * @todo document
 * @package MediaWiki
 * @subpackage Skins
 */
class SkinMonoBook extends SkinTemplate {
	/** Using monobook. */
	function initPage( &$out ) {
		SkinTemplate::initPage( $out );
		$this->skinname  = 'monobook';
		$this->stylename = 'monobook';
		$this->template  = 'MonoBookTemplate';
	}

// WERELATE - copied from SkinTemplate
	function buildPersonalUrls() {
		global $wgTitle, $wgShowIPinHeader;

		$fname = 'SkinTemplate::buildPersonalUrls';
		$pageurl = $wgTitle->getLocalURL();
		wfProfileIn( $fname );

		/* set up the default links for the personal toolbar */
		$personal_urls = array();
		if ($this->loggedin) {
			$personal_urls['userpage'] = array(
				'text' => $this->username,
				'href' => &$this->userpageUrlDetails['href'],
				'class' => $this->userpageUrlDetails['exists']?false:'new',
				'active' => ( $this->userpageUrlDetails['href'] == $pageurl )
			);
         $personal_urls['preferences'] = array(
            'text' => 'Settings',
            'href' => $this->makeNSUrl('Preferences', '', NS_SPECIAL),
         );
		}
      else {
			if( $wgShowIPinHeader && isset(  $_COOKIE[ini_get("session.name")] ) ) {
				$personal_urls['anonlogin'] = array(
					'text' => wfMsg('userlogin'),
					'href' => $this->makeSpecialUrl('Userlogin', 'returnto=' . $this->thisurl ),
					'active' => ( NS_SPECIAL == $wgTitle->getNamespace() && 'Userlogin' == $wgTitle->getDBkey() )
				);
			} else {
				$personal_urls['login'] = array(
					'text' => wfMsg('userlogin'),
					'href' => $this->makeSpecialUrl('Userlogin', 'returnto=' . $this->thisurl ),
					'active' => ( NS_SPECIAL == $wgTitle->getNamespace() && 'Userlogin' == $wgTitle->getDBkey() )
				);
			}
			$personal_urls['newuser'] = array(
					'text' => wfMsg('createaccount'),
					'href' => $this->makeSpecialUrl('Userlogin', 'type=signup&returnto=' . $this->thisurl )
			);
		}
      $personal_urls['donate'] = array(
         'text' => 'Donate',
         'href' => $this->makeNSUrl('Donate', '', NS_PROJECT)
      );
      $personal_urls['volunteer'] = array(
         'text' => 'Volunteer',
         'href' => $this->makeNSUrl('Maintenance', '', NS_PORTAL)
      );
      $personal_urls['help'] = array();
      if ($this->loggedin) {
			$personal_urls['logout'] = array(
				'text' => wfMsg('userlogout'),
				'href' => $this->makeSpecialUrl( 'Userlogout',
					$wgTitle->getNamespace() === NS_SPECIAL && $wgTitle->getText() === 'Preferences' ? '' : "returnto={$this->thisurl}"
				)
			);
      }
		wfRunHooks( 'PersonalUrls', array( &$personal_urls, &$wgTitle ) );
		wfProfileOut( $fname );
		return $personal_urls;
	}
}

/**
 * @todo document
 * @package MediaWiki
 * @subpackage Skins
 */
class MonoBookTemplate extends QuickTemplate {

    /**
     * Get a local URL given any page title (optionally with namespace prefix).
     *
     * This is a rather verbose workaround to avoid Title calling
     * Namespac::getCanonicalIndex() statically and so throwing an error in
     * newer PHP versions. It can be replaced by Title::newFromText($text)->getLocalURL()
     * one day.
     *
     * @param string $title Page title, possibly with colon-separated NS prefix
     * @return string The local URL as given by Title::getLocalURL()
     */
    function getLocalUrl($title) {
        global $wgSitename;
        $targetParts = explode(':', $title);
        $ns = (count($targetParts)==2) ? $targetParts[0] : '';
        if ($ns == $wgSitename) {
          $ns = 'Project';
        }
        else if ($ns == $wgSitename.'_talk') {
          $ns = 'Project_talk';
        }
        $title = (count($targetParts)==2) ? $targetParts[1] : $targetParts[0];
        $namespace = new Namespac();
        $nsIndex = $namespace->getCanonicalIndex(strtolower($ns));
        $url = Title::makeTitle($nsIndex, $title)->getLocalURL();
        return $url;
    }

   function getMenuText($text, $target) {
      $msg = str_replace(' ','&nbsp;',htmlspecialchars(wfMsg($text)));
      $value = "<li id=\"menu-{$text}\">";
      if (is_array($target)) {
         $value .= "<span class=\"wr-menu-more\">$msg</span>▼<ul>";
         foreach ($target as $innerText => $innerTarget) {
            $hdr = '';
            if (substr($innerText,0,1) == '-') {
               $value .= "<li class=\"wr-menu-sep\"><span class=\"wr-menu-sep\">&nbsp;</span></li>";
               $innerText = substr($innerText,1);
            }
            $msg = str_replace(' ','&nbsp;',htmlspecialchars(wfMsg($innerText)));
            $value .= "<li id=\"menu-{$innerText}\"><a href=\"".
               (substr($innerTarget, 0, 1) === "/" || substr($innerTarget, 0, 4) === "http" ? $innerTarget : $this->getLocalUrl($innerTarget)).
               "\">$msg</a></li>";
         }
         $value .= "</ul>";
      }
      else {
         $value .= "<a href=\"".(substr($target, 0, 1) === "/" || substr($target, 0, 4) === "http" ? $target : $this->getLocalUrl($target))."\">$msg</a>";
      }
      $value .= "</li>";
      return $value;
   }

   function getActionMenuText($key, $action) {
      $key = htmlspecialchars($key);
      $class = (string)$action['class'] ? ' class="'.htmlspecialchars($action['class']).'"' : '';
      $href = $action['href'];
      $title = (string)$action['title'] ? ' title="'.htmlspecialchars($action['title']).'"' : '';
      $text = str_replace(' ','&nbsp;',htmlspecialchars($action['text']));
      return <<<END
<li id="actions-$key"$class>
  <a href="$href" rel="nofollow"$title>$text</a>
</li>
END;
   }
	
  /** Function initExploreTree added Aug 2020 by Janet Bjorndahl
    * to determine whether we are displaying a page in the context of exploring a tree, and 
    * if so, to get the parameters for the page list to be incorporated in the page
    */
  public function initExploreTree() {
    global $wgRequest, $wgUser;
     
   /** Initialize isExploreContext if it has no value (first structured page to be displayed in this session). */   
    if ( !isset($_SESSION['isExploreContext']) ) {
      $_SESSION['isExploreContext'] = false;
    }
    
   /** If the page has parameters for exploring a user tree, capture them and set isExploreContext to true.
     * Parameters are stored as session global variables so that all pages display in the context of exploring a tree until the 
     * user selects to exit.
     */
    $mode = $wgRequest->getVal('mode');
    if ( $mode == 'explore' ) {
      $_SESSION['listParms']['tree'] = $wgRequest->getVal('tree');
      $_SESSION['listParms']['user'] = $wgRequest->getVal('user');
      $_SESSION['listParms']['start'] = $wgRequest->getVal('liststart');
      $_SESSION['listParms']['rows'] = $wgRequest->getVal('listrows');
      $_SESSION['listParms']['ns'] = $wgRequest->getVal('listns');
      $_SESSION['isExploreContext'] = true;
      
      /* If the user is exploring their own tree, set the default for tree checkboxes to just this tree (added Sep 2020 by Janet Bjorndahl) */
      if ( $wgUser->getName() == $_SESSION['listParms']['user'] ) {
        $dbw =& wfGetDB( DB_MASTER );
        $allTrees = FamilyTreeUtil::getFamilyTrees($wgUser->getName(), false, $dbw);
        foreach ( $allTrees as $tree ) {
          $check = !$tree['checked'] && ($tree['name'] == $_SESSION['listParms']['tree']);
          $uncheck = $tree['checked'] && ($tree['name'] != $_SESSION['listParms']['tree']);
          if ($check || $uncheck) {
             $dbw->update('familytree', array('ft_checked' => ($check ? 1 : 0)), array('ft_tree_id' => $tree['id']));
             FamilyTreeUtil::deleteFamilyTreesCache($wgUser->getName());
          }
        }
			$dbw->commit();
      }  
    }
    
    if ( $wgRequest->getVal('exitexplore') == 'yes' ) {
      $_SESSION['isExploreContext'] = false;
    }    
  }

  /** Function createInPageList added Aug 2020 by Janet Bjorndahl
    * to create page title links to display as a list within another page.
    * This function also sets a listParms variable to indicate if there are more pages to display.
    */
	public static function createInPageList($title) {
    global $wgUser;
    
    $sk =& $wgUser->getSkin();

    $linkList = array();
    // issue db query to get pages in the user's tree
    $dbr =& wfGetDB( DB_SLAVE );
    $sql = 'SELECT fp_namespace, fp_title FROM familytree_page USE INDEX (PRIMARY)'
      . ' WHERE fp_tree_id = (SELECT ft_tree_id FROM familytree WHERE ft_user = ' . $dbr->addQuotes($_SESSION['listParms']['user']) 
      . ' AND ft_name = ' . $dbr->addQuotes($_SESSION['listParms']['tree']) . ')';
    if ($_SESSION['listParms']['ns'] != null) {
      $sql .= ' AND fp_namespace = ' . $_SESSION['listParms']['ns'];
		}
		$sql .= ' ORDER BY ' . SpecialTrees::getExploreTreeOrder() . ', fp_title'
         . ' LIMIT ' . ($_SESSION['listParms']['start'] + $_SESSION['listParms']['rows'] + 1);
		$res = $dbr->query($sql, 'ExploreFamilyTree');

    // add page links to $linkList, ignoring pages before the value of 'start'
		$n = 0;
		while( ($n < ($_SESSION['listParms']['start'] + $_SESSION['listParms']['rows'])) && ($s = $dbr->fetchObject( $res )) ) {
      if ($n >= $_SESSION['listParms']['start']) {
        $listTitle = Title::makeTitle($s->fp_namespace, $s->fp_title);
        $linkList[$n-$_SESSION['listParms']['start']] = array('link'=>($sk->makeKnownLinkObj($listTitle, htmlspecialchars($listTitle->getPrefixedText()) )));
      } 
      $n++; 
    }
    $_SESSION['listParms']['end'] = $n; // number in list of last record read
    
    // determine if there is another page (list should allow scrolling to next page)
    if ($s = $dbr->fetchObject( $res )) {
      $_SESSION['listParms']['more'] = true;
    }
    else {  
      $_SESSION['listParms']['more'] = false;
    }
    return $linkList;
  }

	/**
	 * Template filter callback for MonoBook skin.
	 * Takes an associative array of data set from a SkinTemplate-based
	 * class, and a wrapper for MediaWiki's localization database, and
	 * outputs a formatted page.
	 *
	 * @access private
	 */
	function execute() {
		// Suppress warnings to prevent notices about missing indexes in $this->data
		wfSuppressWarnings();
// WERELATE - added vml namespace
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xml:lang="<?php $this->text('lang') ?>" lang="<?php $this->text('lang') ?>" dir="<?php $this->text('dir') ?>">
  <?php
  global $wgTitle, $wgUser, $wgUseGoogleAnalytics, $wgOut, $wgScriptPath, $wgArticle, $wgRequest, $wrProtocol;

  $now = wfTimestampNow(); // WERELATE
  $sk = $wgUser->getSkin();
  $ns = $wgTitle->getNamespace();
  $titleString = $wgTitle->getText();
  $noTitlePage = false;
  $overrideTitle = '';
  $suppressAds = ($wgRequest->getVal('action') == 'edit' || !$this->data['notspecialpage'] || ($ns == NS_MAIN && $titleString == 'Main Page'));
  if ($wgRequest->getVal('msg')) {
     $this->data['msg'] = wfMsg($wgRequest->getVal('msg'));
  }
  $mainPage = ($ns == NS_MAIN && $titleString == 'Main Page');
  if ($mainPage) {
  		$overrideTitle = '<span class="wr-maintitle">WeRelate.org</span>';
  }
  $menulinks = array(
        'home' => 'Main_Page',
        'search' => array(
            'all' => 'Special:Search',
            'people' => 'Special:Search/Person',
            'families' => 'Special:Search/Family',
            'articles' => 'Special:Search/Article',
            'images' => 'Special:Search/Image',
            'places' => 'Special:Search/Place',
            'sources' => 'Special:Search/Source'
        ),
        'list' => array(
            'people' => ($wgUser->isLoggedIn() ? 'Special:ListPages/' . urlencode($wgUser->getName()) : 'Special:Userlogin'),
            'contributions' => ($wgUser->isLoggedIn() ? 'Special:Contributions/' . urlencode($wgUser->getName()) : 'Special:Userlogin')
        ),
        'add' => array(
            'person' => 'Special:AddPage/Person',
            'family' => 'Special:AddPage/Family',
            'article' => 'Special:AddPage/Article',
            'image' => 'Special:Upload',
            'place' => 'Special:AddPage/Place',
            'mysource' => 'Special:AddPage/MySource',
            'source' => 'Special:AddPage/Source',
            'transcript' => 'Special:AddPage/Transcript',
            'repository' => 'Special:AddPage/Repository',
            'userpage' => 'Special:AddPage/User',
            'otherpage' => 'Special:AddPage',
            '-importgedcom' => 'Special:ImportGedcom'
        ),
        'myrelate' => array(
            'dashboard' => 'Special:Dashboard',
            'network' => ($wgUser->isLoggedIn() ? 'Special:Network/' . urlencode($wgUser->getName()) : 'Special:Userlogin'), 
            'watchlist' => ($wgUser->isLoggedIn() ? 'Special:Watchlist/' . urlencode($wgUser->getName()) : 'Special:Userlogin'),
            'userprofile' => ($wgUser->isLoggedIn() ? 'User:' . urlencode($wgUser->getName()) : 'Special:Userlogin'),
            'talkpage' => ($wgUser->isLoggedIn() ? 'User_talk:' . urlencode($wgUser->getName()) : 'Special:Userlogin'), 
            'mytrees' => 'Special:Trees',   // changed to mytrees Dec 2020 by Janet Bjorndahl
            'showduplicates' => ($wgUser->isLoggedIn() ? 'Special:ShowDuplicates/' . urlencode($wgUser->getName()) : 'Special:Userlogin'),  // Launch FTE removed Dec 2020 
            'DQissues' => ($wgUser->isLoggedIn() ? 'Special:DataQuality/' . urlencode($wgUser->getName()) : 'Special:Userlogin')            // added Jun 2022 by JB
        ),
        'admin' => array(
            'recentchanges' => 'Special:Recentchanges',
            'nominate' => 'WeRelate:Featured_page_nominations',
            'logs' => 'Special:Log',
            'newimages' => 'Special:Newimages',
            'reviewneeded' => 'Category:Review_needed',
            'gedcomreview' => 'Special:Gedcoms',
            'speedydelete' => 'Category:Speedy_Delete',
            'nameslog' => 'Special:NamesLog',
            'browseall' => 'Special:Browse',
            'comparepages' => 'Special:Compare',
            'specialpages' => 'Special:Specialpages'
        ),
  );
  $helpLinks = array(
      'contents' => 'Help:Contents',
      'search' => 'Special:Search/Help',
      'faq' => 'Help:FAQ',
      'support' => 'WeRelate_talk:Support',
      'portals' => 'Portal:Community',
      'watercooler' => 'WeRelate_talk:Watercooler',
      'suggestions'=>'WeRelate:Suggestions'
  );
  // 'requestdelete' added Jan 2021 by Janet Bjorndahl
  $moreActions = array('protect', 'unprotect','delete', 'requestdelete', 'share','pedigree', 'match', 'compare-parents', 'compare-spouses', 'compare-husbands', 'compare-wives');
  ?>
<head>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-251952-1"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'UA-61266128-1');
    </script>
	<meta http-equiv="Content-Type" content="<?php $this->text('mimetype') ?>; charset=<?php $this->text('charset') ?>" />
	<?php $this->html('headlinks') ?>
	<title><?php $this->text('pagetitle') ?></title>
   <link rel="stylesheet" type="text/css" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.12/themes/redmond/jquery-ui.css">
   <style type="text/css" media="all">/*<![CDATA[*/ @import "<?php $this->text('stylepath') ?>/<?php $this->text('stylename') ?>/main.87.css"; /*]]>*/</style>
	<link rel="stylesheet" type="text/css" <?php if(empty($this->data['printable']) ) { ?>media="print"<?php } ?> href="<?php $this->text('stylepath') ?>/common/commonPrint.8.css" />
   <script type="<?php $this->text('jsmimetype') ?>" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
   <script type="<?php $this->text('jsmimetype') ?>" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js"></script>
   <script type="<?php $this->text('jsmimetype') ?>" src="<?php echo $wgScriptPath; ?>/jquery.json-2.3.min.1.js"></script>
   <script type="<?php $this->text('jsmimetype') ?>" src="<?php echo $wgScriptPath; ?>/jquery.ezpz_hint.min.2.js"></script>
   <script type="<?php $this->text('jsmimetype') ?>" src="<?php echo $wgScriptPath; ?>/jquery.plugins.4.js"></script>
   <?php if ($wgTitle->getNamespace() === NS_SPECIAL && $wgTitle->getText() === 'Userlogin') { ?>
   <script src='https://www.google.com/recaptcha/api.js'></script>
   <?php } ?>

<?php
//   jquery.bgiframe.min.1.js separator.js
//   jquery.clickmenu.yui.3.js separator.js
//   jquery.cluetip.yui.1.js separator.js
//   jstorage.min.1.js separator.js
?>
   <script type="text/javascript">var addthis_config = {pubid: 'ra-4dac29732a79195f', services_exclude: 'print'};</script>
   <script type="text/javascript" src="https://s7.addthis.com/js/250/addthis_widget.js#acync=1"></script>
   <script type="<?php $this->text('jsmimetype') ?>" src="<?php $this->text('stylepath' ) ?>/common/wikibits.yui.30.js"></script>
<!-- Head Scripts -->
<?php $this->html('headscripts') ?>
   <?php	if($this->data['trackbackhtml']) print $this->data['trackbackhtml']; ?>
</head>
<body id="www-werelate-org" <?php if($this->data['body_ondblclick']) { ?>ondblclick="<?php $this->text('body_ondblclick') ?>"<?php }
   if ($this->data['body_onload'] || $wgOut->getOnloadHandler()) { ?>onload="<?php if ($this->data['body_onload']) { $this->text('body_onload'); ?>;<?php }
	if ($wgOut->getOnloadHandler()) { echo htmlspecialchars($wgOut->getOnloadHandler()); } ?>;"<?php } ?>>
<div id="wr-header">
   <div id="wr-logo">
       <a href="<?php echo Title::makeTitle('', 'Main_Page')->getLocalURL()?>" title="<?php $this->msg('mainpage') ?>">
           <img src="<?php $this->text('logopath') ?>"/>
       </a>
       <?php
         // determine if page is being displayed in the context of exploring a tree (added Aug 2020 by Janet Bjorndahl)
         $this->initExploreTree();
         if ( $_SESSION['isExploreContext'] ) {    // This check added Aug 2020 by Janet Bjorndahl
           echo '<div class="wr-sidebar-separator-wide"></div>';
         }
         else {
           echo '<div class="wr-sidebar-separator"></div>';
         }
       ?>  
   </div>
       <?php
         if ( $_SESSION['isExploreContext'] ) {    // This check added Aug 2020 by Janet Bjorndahl
           echo '<div id="wr-navtitle-wide">';
         }
         else {
           echo '<div id="wr-navtitle">';
         }
       ?>  
      <div id="wr-navigation">
         <div class="portlet" id="wr-menu">
            <h5><?php echo 'Menu'; ?></h5>
            <div class="nav-body">
               <ul id="wr-menulist">
               <?php
               foreach( $menulinks as $text => $target) {
                  echo $this->getMenuText($text, $target);
               }
               ?>
               </ul>
            </div>
         </div>
         <div class="portlet" id="wr-personal">
            <h5><?php $this->msg('personaltools') ?></h5>
            <div class="nav-body">
               <ul id="wr-personallist">
               <?php
               foreach($this->data['personal_urls'] as $key => $item) {
                  if ($key == 'help') {
                     echo $this->getMenuText($key, $helpLinks);
                  }
                  else {
               ?>
                  <li id="personal-<?php echo htmlspecialchars($key) ?>"<?php if ($item['active']) { ?> class="active"<?php } ?>>
                     <a href="<?php	echo htmlspecialchars($item['href']) ?>"<?php if(!empty($item['class'])) { ?> class="<?php
                        echo htmlspecialchars($item['class']) ?>"<?php } ?>><?php	echo htmlspecialchars($item['text']) ?></a>
                  </li>
               <?php
                  }
               }
               ?>
               </ul>
            </div>
         </div>
      </div>
      <div id="wr-titlearea">
         <div id="wr-searchbox">
             <form method="get" action="<?php echo Title::makeTitle(NS_SPECIAL, 'Search')->getLocalURL() ?>">
               <input type="hidden" name="go" value="true"/>
               <input class="wr-searchbox-text" type="text" title="Search" name="k"/>
               <button type="submit" title="Search WeRelate">&nbsp;</button>
            </form>
         </div>
         <?php if(!$noTitlePage) {
            $firstHeadingClass = ($ns == NS_PERSON || $ns == NS_FAMILY ? "firstHeadingPersonFamily" : "firstHeading");
         ?>
            <div id="wr-title">
                <h1 class="<?php echo $firstHeadingClass; ?>"><?php if ($overrideTitle) { echo $overrideTitle; } else { $this->data['displaytitle']!=""?$this->text('title'):$this->html('title'); } ?></h1>
            </div>
         <?php } ?>
         <div id="contentSub"><?php
         $this->html('subtitle');
         if ($wgRequest->getVal('target') && !$wgRequest->getVal('action') && $this->data['notspecialpage']) {
            echo '<div style="color:red; margin:4px 8px">To select this page: click the <i>Back</i> button on your browser (if using Chrome, right-click and select <i>Back</i>), then click <i>Select</i></div>';
         }
         if ($wgTitle->getNamespace() == NS_USER && strpos($wgTitle->getText(), '/') === false) {
            $userName = $wgTitle->getText();
            $userTalk = Title::newFromText($userName, NS_USER_TALK);
              echo '<div id="wr-leavemessage"><ul><li>'.$sk->makeKnownLinkObj($userTalk, "Leave a message for $userName", "action=edit&section=new").'</li></ul></div>';
         }
         else if (!$mainPage && !$wgTitle->isTalkPage() && $wgTitle->getTalkPage()->getArticleId() != 0) {
              echo '<div id="wr-leavemessage"><ul><li>'.$sk->makeKnownLinkObj($wgTitle->getTalkPage(), "View the talk page").'</li></ul></div>';
         }
         ?></div>
         <?php if($this->data['sitenotice']) { ?><div id="siteNotice"><?php $this->html('sitenotice') ?></div><?php } ?>
         <?php if($this->data['undelete']) { ?><div id="contentSub2"><?php $this->html('undelete') ?></div><?php } ?>
         <?php if($this->data['newtalk'] ) { ?><div class="usermessage"><?php $this->html('newtalk')  ?></div><?php } ?>
         <?php if($this->data['msg'] ) { ?><div class="usermessage"><?php $this->html('msg')  ?></div><?php } ?>
      </div>
   </div>
</div><div id="wr-content"<?php if ($this->data['nav_urls']['print']) { echo ' class="wr-articleview"';} ?>>
   <div id="bodyContent">
      <table id="sdlayout">
         <tr>
            <?php
            if ( $_SESSION['isExploreContext'] ) {    // This check added Aug 2020 by Janet Bjorndahl
              echo '<td id="pagelistbox">';
            }
            else {  
              echo '<td id="infobox">';
            }
            if ($this->data['notspecialpage']) {
               ?><div class="portlet" id="wr-actions">
                  <h5><?php $this->msg('views') ?></h5>
                  <div class="nav-body">
                     <ul id="wr-actionslist">
                     <?php
                     $moreActionsList = array();
                     foreach($this->data['content_actions'] as $key => $tab) {
                        if (in_array($key, $moreActions)) {
                           $moreActionsList[$key] = $tab;
                        }
                        else {
                           echo $this->getActionMenuText($key, $tab);
                        }
                     }
            //         	$moreActionsList['recentchangeslinked'] = array('href' => $this->data['nav_urls']['recentchangeslinked']['href'], 'text' => wfMsg('recentchangeslinked'));
            //          $moreActionsList['whatlinkshere'] = array('href' => $this->data['nav_urls']['whatlinkshere']['href'], 'text' => wfMsg('whatlinkshere'));
                     foreach (array('whatlinkshere', 'emailuser', 'contributions', 'blockip', 'print') as $special) { // 'permalink'
                        if ($this->data['nav_urls'][$special]) {
                           if ($special == 'contributions' || $special == 'whatlinkshere') {
                              echo $this->getActionMenuText($special, array('href'=>$this->data['nav_urls'][$special]['href'], 'text' => wfMsg($special)));
                           }
                           else {
                              $moreActionsList[$special] = array('href' => $this->data['nav_urls'][$special]['href'], 'text' => wfMsg($special));
                           }
                        }
                     }
                     ?>
                     <li id="actions-more"><span class="wr-menu-more">more</span>▼<ul>
                     <?php foreach($moreActionsList as $key => $tab) {
                        echo $this->getActionMenuText($key, $tab);
                     } ?>
                     </ul></li>
                     </ul>
                  </div>
               </div>
               <?php
                 if ( $_SESSION['isExploreContext'] ) {    // This check added Aug 2020 by Janet Bjorndahl
                   echo '<div class="wr-sidebar-separator-wide"></div>';
                 }
                 else {
                   echo '<div class="wr-sidebar-separator"></div>';
                 }
               ?>  
               <div class="portlet" id="wr-watchers">
                  <h5>Watchers</h5>
                  <div class="nav-body"><?php
                     $watchers = StructuredData::getWatchers($wgTitle, 0, 6);
                     if (count($watchers) == 0) {
                        $watchers[] = '<li>no watchers</li>';
                     }
                     $firstFive = array_slice($watchers, 0, 5);
                     echo '<ul>'.join('',$firstFive).'</ul>';
                     if (count($watchers) > 5) {
                        $watchersUrl = '/w/index.php?'.http_build_query(
                           array('action' => 'ajax', 'rs'=>'wfGetWatchers',
                                 'rsargs' => 'title='.$wgTitle->getPrefixedText().'|offset=5'));
                        echo "<ul class=\"wr-watchers-showall\"><li><a href=\"$watchersUrl\">[show all]</a></li></ul>";
                     }
                  ?></div>
               </div>
               
               <?php
               /* In lower left portlet for a structured data page, display either an in-page list or browse facets,
                * depending on the context. */
                 if ( $_SESSION['isExploreContext'] ) {
                   
                   /* In-page list (code added Aug 2020 by Janet Bjorndahl) */
                   $currentTitleText = $wgRequest->getVal('title');
                   echo '<div class="portlet" id="page-list">';
                   echo '<table><tr><td><h5>Tree: ' . $_SESSION['listParms']['tree'] . '</h5></td>';
                   echo '<td class="td-right">' . $sk->makeKnownLink($currentTitleText, 'Exit', wfArraytoCGI(array('exitexplore' => 'yes')));
                   echo '</td></tr></table>';
                   
                   // if user selected/entered an option, update parms then (re)display options
                   $newRows = $wgRequest->getVal('listrows');
                   if ( $newRows > 0 ) {
                     $_SESSION['listParms']['ns'] = $wgRequest->getVal('listns');
                     $_SESSION['listParms']['rows'] = $newRows;
                     $jump = $wgRequest->getVal('listjump');
                     if ( $jump > 0 ) {
                       $_SESSION['listParms']['start'] = $jump - 1;
                     }
                   }    
                   echo '<form method="get" action="/wiki/' . $wgTitle->getPrefixedURL($currentTitleText) . '">';
              	   $namespace = 'listns';
                   $rows = 'listrows';
                   echo StructuredData::addSelectToHtml(0, $namespace, SpecialTrees::$EXPLORE_NAMESPACE_OPTIONS, 
                     $_SESSION['listParms']['ns'], 'onchange="this.form.submit()"', false);
                   echo '<label for="listrows"> Show </label>' . StructuredData::addSelectToHtml(0, $rows, SpecialTrees::$EXPLORE_ROWS_OPTIONS, 
                     $_SESSION['listParms']['rows'], 'onchange="this.form.submit()"', false);
                   echo '<label for="listjump"> Jump to # </label><input type="number" id="listjump" name="listjump"/>';
                   echo '</form>';
                   
                   // create list of page links
                   $linkList = $this->createInPageList($wgTitle);
                   // start can be past the end when user jumps to a particular row or changes namespace filter - if so, reset 'start' and create page links again
                   if ( $_SESSION['listParms']['start'] > 0 && ($_SESSION['listParms']['end'] <= $_SESSION['listParms']['start']) ) {
                     if ( $jump > 0 ) {
                       $_SESSION['listParms']['start'] = max(0,  $_SESSION['listParms']['end'] - $_SESSION['listParms']['rows']); // if jumped beyond end, go to last full page of list
                     }
                     else {
                       $_SESSION['listParms']['start'] = 0; // if namespace filter changed, go to beginning of list
                     }
                     $linkList = $this->createInPageList($wgTitle);
                   }
                   
                   // scrolling info
                   echo '<div class="prev_next_links_top">';
                   if ( $_SESSION['listParms']['start'] > 0 ) { 
                     $newStart = max(0 , $_SESSION['listParms']['start'] - $_SESSION['listParms']['rows']);
                     echo $sk->makeKnownLink($currentTitleText, '&laquo;&nbsp;Prev', 
                       wfArrayToCGI(array('mode' => 'explore', 'user' => $_SESSION['listParms']['user'], 'tree' => $_SESSION['listParms']['tree'], 
                       'liststart' => $newStart, 'listrows' => $_SESSION['listParms']['rows'], 'listns' => $_SESSION['listParms']['ns']))) . ' | ';
                   }
                   echo 'Viewing <b>';
                   echo $_SESSION['listParms']['start']+1 . '-' . $_SESSION['listParms']['end'] . '</b>'; 
                   if ( $_SESSION['listParms']['more'] ) {
                     $newStart = $_SESSION['listParms']['start'] + $_SESSION['listParms']['rows'];
                     echo ' | ' . $sk->makeKnownLink($currentTitleText, 'Next&nbsp;&raquo;', 
                       wfArrayToCGI(array('mode' => 'explore', 'user' => $_SESSION['listParms']['user'], 'tree' => $_SESSION['listParms']['tree'], 
                       'liststart' => $newStart, 'listrows' => $_SESSION['listParms']['rows'], 'listns' => $_SESSION['listParms']['ns'])));
                   }
                   echo '</div>';

                   // the list of pages
                   echo '<div class="page-list-main"><ul>'; 
                   foreach ($linkList as $pageLink) {
                     echo '<li>' . $pageLink['link'] . '</li>';
                   }
                   echo '</ul></div></div>';
                 }
                 else {

                   /* Browse facets */
                   echo '<div class="portlet" id="wr-morelikethis">';
                     $moreLikeThis = StructuredData::getMoreLikeThis($wgTitle);
                     if (count($moreLikeThis) > 0) {
                        echo '<h5>Browse</h5><div class="nav-body morelikethis"><ul>';
                        foreach ($moreLikeThis as $more) {
                           echo '<li>'.$more['link'];
                           if (count($more['places']) > 0) {
                              echo '<ul>';
                              foreach ($more['places'] as $place) {
                                 echo '<li>in '.$place.'</li>';
                              }
                              echo '</ul>';
                           }
                           echo '</li>';
                        }
                        echo '</ul></div>';
                     }
                   echo '</div>';
                 }                     
               ?>
               
               <?php
               if ($mainPage) {
                  if ($wgUser->getOption('wrnoads') < $now) {
               ?><div id="mh_ad" style="margin: 32px 0 0 16px"><a href="https://www.myheritage.com/?utm_source=partner_werelate&utm_medium=partner&utm_campaign=werelate_homepageweb&tr_funnel=web">
                  <img src="/w/skins/common/images/mh_ad_100x300-2023.png"/>
               </a></div><?php
                  }
               ?><div id="awards"><?php
                  echo wfMsg('awards');
               ?></div><?php
               }
               else if (false && $wgUser->getOption('wrnoads') < $now) { // don't show this ad
               ?><div id="lhsadbox">
<div style="text-align: right; border-bottom: 1px solid #aaa; padding-right: 3px"><a href="/wiki/WeRelate:Donate">Don't want ads?</a></div>
<!-- remove or replace -->
<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<!-- lhs -->
<ins class="adsbygoogle"
     style="display:inline-block;width:160px;height:600px"
     data-ad-client="ca-pub-5042243421154153"
     data-ad-slot="4780960811"></ins>
<script>
(adsbygoogle = window.adsbygoogle || []).push({});
</script>
               </div><?php
               }
            }
            else {
               global $wrSidebarHtml;
               echo $wrSidebarHtml;
            } ?>
            </td>
            <td id="contentbox">
               <!-- start content -->
               <a name="top" id="top"></a>
               <?php $this->html('bodytext') ?>
               <a name="bottom" id="bottom"></a>
               <?php if($this->data['catlinks']) { ?><div id="catlinks"><?php $this->html('catlinks') ?></div><?php } ?>
               <!-- end content -->
            </td>
            <?php if (!$suppressAds && $wgUser->getOption('wrnoads') < $now && $wgTitle->getArticleID() != 0) { ?>
               <td id="rhsadbox">
                  <div id="rhsads">
<div style="text-align: right; padding-right: 3px"><a href="/wiki/WeRelate:Donate">Don't want ads?</a></div>
<div style="margin-top: 16px">
<?php
if ($wgTitle->getNamespace() == NS_PERSON) {
?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5042243421154153"
     crossorigin="anonymous"></script>
<!-- rhs300 -->
<ins class="adsbygoogle"
     style="display:inline-block;width:300px;height:250px"
     data-ad-client="ca-pub-5042243421154153"
     data-ad-slot="5881944012"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>
<?php
  $namePieces = explode(" ", $wgTitle->getText());
  $firstName = "";
  $lastName = "";
  if (count($namePieces) > 0 && $namePieces[0] != "Unknown") {
    $firstName = urlencode($namePieces[0]);
  }
  if (count($namePieces) > 1 && $namePieces[1] != "Unknown" && substr($namePieces[1], 0, 1) != "(") {
    $lastName = urlencode($namePieces[1]);
  }
//  echo '<div style="margin-top: 20px"><iframe height="600" width="300" src="https://www.myheritage.com/FP/partner-widget.php?partnerName=werelate&clientId=3401&campaignId=Mixed_Records_300x600&widget=tree&width=300&height=600&onSitePlacement=Mixed_Records_300x600&tr_ifid=werelate_281825853&firstName='.$firstName.'&lastName='.$lastName.'&tr_device="></iframe></div>';
  echo <<<EOL
<div style="margin-top: 20px">
<ins class='dcmads' style='display:inline-block; width:300px; height:600px'
    data-dcm-placement='N217801.2353305WERELATE.ORG/B9799048.368900133'
    data-dcm-rendering-mode='iframe'
    data-dcm-https-only
    data-dcm-api-frameworks='[APIFRAMEWORKS]'
    data-dcm-omid-partner='[OMIDPARTNER]'
    data-dcm-gdpr-applies='gdpr=${GDPR}'
    data-dcm-gdpr-consent='gdpr_consent=${GDPR_CONSENT_755}'
    data-dcm-addtl-consent='addtl_consent=${ADDTL_CONSENT}'
    data-dcm-ltd='false'
    data-dcm-resettable-device-id=''
    data-dcm-app-id=''>
  <script src='https://www.googletagservices.com/dcm/dcmads.js'></script>
</ins>
</div>
EOL;
?>
<?php
} else {
?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5042243421154153"
     crossorigin="anonymous"></script>
<!-- rhs600 -->
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-5042243421154153"
     data-ad-slot="7402015214"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>
<?php
}
?>
</div>
                  </div>
               </td>
            <?php } ?>
         </tr>
      </table>
   </div>
</div>
<div class="visualClear"></div>
<div id="wr-footer">
	<div id="wr-footer-bg"></div>
   <div id="wr-poweredby">
      <?php /* WERELATE - added */ if ($ns == NS_SOURCE) { ?>
		<div id="f-opendirectoryico">
		<table border="0" cellpadding="2"><tr><td align=center><font face="sans-serif, Arial, Helvetica" size="2">Portions from the<br>Open Directory Project</font></td>
		<td><table border="0" cellpadding="3" cellspacing="0" style="background-color: #336600;">
		<tr>
		<td>
		<table width="100%" cellpadding="2" cellspacing="0" border="0" style="background-color: #336600;">
		<tr align="center">
		<td><font face="sans-serif, Arial, Helvetica" size="2" color="#FFFFFF">Help build the largest human-edited directory on the web.</font></td>
		</tr>
		<tr bgcolor="#CCCCCC" align="center">
		<td><font face="sans-serif, Arial, Helvetica" size="2">
		<a href="http://dmoz.org/cgi-bin/add.cgi?where=Society/Genealogy" style="text-decoration: underline">Submit a Site</a> -
		<a href="http://dmoz.org/about.html" style="text-decoration: underline"><b>Open Directory Project</b></a> -
		<a href="http://dmoz.org/cgi-bin/apply.cgi?where=Society/Genealogy" style="text-decoration: underline">Become an Editor</a> </font>
		</td></tr>
		</table>
		</td>
		</tr>
		</table>
		</td></tr></table>
		</div>
	   <?php } elseif($this->data['poweredbyico']) { ?><div id="f-poweredbyico"><?php $this->html('poweredbyico') ?></div><?php } ?>
   </div>
   <div id="wr-footer-content">
		<ul id="f-list">
      <?php
      $footerlinks = array(
			array('lastmod', 'viewcount', 'numberofwatchingusers', 'credits'),
         array('copyright'),
			array('privacy', 'about', 'disclaimer', 'tagline'),
		);
      foreach ($footerlinks as $links) {
         echo '<ul>';
		   foreach( $links as $aLink ) {
			   if( $this->data[$aLink] ) { ?>
				   <li id="<?php echo$aLink?>"><?php $this->html($aLink) ?></li>
            <?php }
         }
         echo '</ul>';
		} ?>
		</ul>
      <?php $this->html('reporttime') ?>
   </div>
</div>
<script type="<?php $this->text('jsmimetype') ?>">
var pageRevid=<?php echo ($wgArticle != null ? $wgArticle->getRevIdFetched() : 0) ?>;
var treeNames="<?php echo htmlspecialchars(implode('|', $this->data['trees'])); ?>";
var userName="<?php echo ($wgUser->isLoggedIn() ? htmlspecialchars($wgUser->getName()) : ''); ?>";
var userId="<?php echo ($wgUser->isLoggedIn() ? htmlspecialchars($wgUser->getID()) : ''); ?>";
var skin='<?php $this->text('skinname')?>';
var stylepath='<?php $this->text('stylepath')?>';</script>
<?php
        if (false) { //!$this->loggedin) {
?>
<!--
<script id="_webengage_script_tag" type="text/javascript">
var webengage; !function(w,e,b,n,g){function o(e,t){e[t[t.length-1]]=function(){r.__queue.push([t.join("."),arguments])}}var i,s,r=w[b],z=" ",l="init options track screen onReady".split(z),a="feedback survey notification".split(z),c="options render clear abort".split(z),p="Open Close Submit Complete View Click".split(z),u="identify login logout setAttribute".split(z);if(!r||!r.__v){for(w[b]=r={__queue:[],__v:"6.0",user:{}},i=0;i<l.length;i++)o(r,[l[i]]);for(i=0;i<a.length;i++){for(r[a[i]]={},s=0;s<c.length;s++)o(r[a[i]],[a[i],c[s]]);for(s=0;s<p.length;s++)o(r[a[i]],[a[i],"on"+p[s]])}for(i=0;i<u.length;i++)o(r.user,["user",u[i]]);setTimeout(function(){var f=e.createElement("script"),d=e.getElementById("_webengage_script_tag");f.type="text/javascript",f.async=!0,f.src=("https:"==e.location.protocol?"https://ssl.widgets.webengage.com":"http://cdn.widgets.webengage.com")+"/js/webengage-min-v-6.0.js",d.parentNode.insertBefore(f,d)})}}(window,document,"webengage");
webengage.init("aa1323db");
</script>
-->
<?php
        }
?>
<?php
        if (false && !$this->loggedin && !$mainPage) {
?>
<!-- Start of Survicate (www.survicate.com) code -->
                <script type="text/javascript">
                  (function (w) {
                              var s = document.createElement('script');
                                  s.src = 'https://survey.survicate.com/workspaces/fb51e58a3ee96a16df89a23f9ac22f12/web_surveys.js';
                                  s.async = true;
                                      var e = document.getElementsByTagName('script')[0];
                                      e.parentNode.insertBefore(s, e);
                                        })(window);
                  </script>
<!-- End of Survicate code -->
<?php
        }
?>
</body></html>
<?php
	wfRestoreWarnings();
	} // end of execute() method
} // end of class
?>
