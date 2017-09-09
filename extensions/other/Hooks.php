<?php
/**
 * @package MediaWiki
 * @subpackage other
 */

if( !defined( 'MEDIAWIKI' ) )
		  die( 1 );

require_once("$IP/extensions/familytree/FamilyTreeUtil.php");
require_once("$IP/extensions/familytree/AddTreePagesJob.php");
require_once("$IP/extensions/recaptchalib.php");
require_once("$IP/extensions/Mobile_Detect.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfHooksSetup";

# Register ajax functions -- these should go into their own file
$wgAjaxExportList[] = "wfGetWatchers";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfHooksSetup() {
	global $wgHooks;

	# Register hooks for edit UI, request handling, and save features
	$wgHooks['AddNewAccount'][] = 'wrAddNewAccount';
	$wgHooks['EmailComplete'][] = 'wrLogEmail';
   $wgHooks['AbortNewAccount'][] = 'wrAbortNewAccount';
   $wgHooks['SkinTemplateTabs'][] = 'wrAddContentActions';
   $wgHooks['UnknownAction'][] = 'wrDoContentActions';
   $wgHooks['AbortNewAccount'][] = 'wrValidateUser';
   $wgHooks['EditFilter'][] = 'wrCheckSpam';
   $wgHooks['ArticleSaveComplete'][] = 'wrArticleSave';
   $wgHooks['WatchArticle'][] = 'wrSetWatchlistSummary';
	$wgHooks['WatchArticleComplete'][] = 'wrIndexPurgeArticle';
	$wgHooks['UnwatchArticleComplete'][] = 'wrIndexPurgeArticle';
	$wgHooks['ArticlePurge'][] = 'wrIndexArticle';
   $wgHooks['TitleMoveComplete'][] = 'wrMovePage';
   $wgHooks['UserCreateForm'][] = 'wrAddCaptcha';

   # Add new log types
   global $wgLogTypes, $wgLogNames, $wgLogHeaders, $wgLogActions;

	$wgLogTypes[]							  = 'newuser';
	$wgLogNames['newuser']				  = 'newuserlogpage';
	$wgLogHeaders['newuser']				= 'newuserlogpagetext';
	$wgLogActions['newuser/newuser']	= 'newuserlogentry';

	$wgLogTypes[]							= 'email';
	$wgLogNames['email']				  = 'emaillogpage';
	$wgLogHeaders['email']				= 'emaillogpagetext';
	$wgLogActions['email/email']		= 'emaillogentry';

	# register tag extensions with the WikiText parser
	global $wgParser;

	$wgParser->setHook('wr_img', 'wrImgHook');
	$wgParser->setHook('wr_a', 'wrAnchorHook');
	$wgParser->setHook('wr_paypal', 'wrDonateHook');
	$wgParser->setHook('wr_screencast', 'wrScreencast');
	$wgParser->setHook('youtube', 'wrYouTube');
   $wgParser->setHook('googlemap', 'wrGoogleMap');
//   $wgParser->setHook('getsatisfaction', 'wrGetSatisfaction');
   $wgParser->setHook('addsubpage', 'wrAddSubpage');
   $wgParser->setHook('listsubpages', 'wrListSubpages');
   $wgParser->setHook('wr_ad', 'wrAd');
   $wgParser->setHook('mh_ad', 'mhAd');
}

function wfGetWatchers($args) {
   $args = AjaxUtil::getArgs($args);
   $title = Title::newFromText($args['title']);
   $offset = (isset($args['offset']) ? (int)$args['offset'] : false);
   $limit = (isset($args['limit']) ? (int)$args['limit'] : false);
   $watchers = StructuredData::getWatchers($title, $offset, $limit);
   return join('',$watchers);
}

/**
 * Callback function for converting <wr_img>filename[|url]</wr_img> into HTML
 */
function wrImgHook( $input, $argv, $parser) {
	global $wgStylePath;

	$filename = '';
	$link = '';
	
	// split into image filename and url
	$fields = explode('|', $input, 2);
	// validate the image filename
	if (count($fields) > 0 && strlen($fields[0]) > 0) {
//		if (substr($fields[0], 0, 7) == 'http://') {  // if we enable this, we open ourselves to people including copyrighted images on WeRelate
//			$filename = htmlspecialchars($fields[0]);
//		}
//		else 
		if (substr($fields[0], 0, 6) == 'Image:' && substr($fields[0], 6, 1) != '.' && substr($fields[0], 6, 1) != '/') {
			$filename = Image::imageUrl(substr($fields[0], 6));
		}
		else if (substr($fields[0], 0, 1) != '.' && !preg_match('/[^A-Za-z0-9._\-]/', $fields[0])) {
			$filename = "$wgStylePath/common/images/".htmlspecialchars($fields[0]);
		}

		// construct the html to pass back
		if (count($fields) == 2 && strlen($fields[1]) > 0) {
			$link = htmlspecialchars($fields[1]);
		}
	}

	if ($filename) {
		return ($link ? "<a href=\"$link\">" : '') . "<img src=\"$filename\"/>" . ($link ? '</a>' : '');
	}
	else {
		return '';
	}
}

/**
 * Callback function for converting <wr_a>href|text[|target[|class]]</wr_a> into HTML
 *  alternatively, works just like a tag with href and/or name attributes
 */
function wrAnchorHook( $input, $argv, $parser) {

	// split into anchor, text, target
	$fields = explode('|', $input);
	// validate the image filename
	if (count($fields) >= 2 && strlen($fields[0]) > 0 && strlen($fields[1]) > 0 ) {
		// construct the html to pass back
		$target = '';
		$link = htmlspecialchars($fields[0]);
		$text = htmlspecialchars($fields[1]);
		if (count($fields) >= 3 && strlen($fields[2]) > 0) {
			$target = ' target="'.htmlspecialchars($fields[2]).'"';
		}

		return "<a href=\"$link\"$target>$text</a>";
	}
	else if (count($argv) > 0) {
      $attrs = '';
      foreach ($argv as $key => $value) {
         $key = htmlspecialchars($key);
         $value = htmlspecialchars($value);
         $attrs .= " $key=\"$value\"";
      }
      $input = htmlspecialchars($input);
      return "<a $attrs>$input</a>";
   }
   else {
		return '';
	}
}

function wrAd() {
   global $wgTopAdCode;
   // disable top ad
   //return $wgTopAdCode;
   return "";
}

function mhAd($input, $argv, $parser) {
   global $wgUser;

   // ignore people without ads
   $now = wfTimestampNow();
   if ($wgUser->getOption('wrnoads') >= $now) {
       return "";
   }

    // detect mobile/tablet/desktop
    $detect = new Mobile_Detect;
    $device = 'c';
    if ($detect->isMobile()) {
        if ($detect->isTablet()) {
            $device = 't';
        }
        else {
            $device = 'm';
        }
    }
    else if ($detect->isTablet()) {
        $device = 't';
    }

   $firstName = '';
   $lastName = '';
   foreach ($argv as $key => $value) {
     if ($key == 'firstname') {
        $firstName = urlencode($value);
     }
     if ($key == 'lastname') {
        $lastName = urlencode($value);
     }
   }
   return <<< END
<div style="margin: -23px 0 16px 0;">
<iframe src="https://www.myheritage.com/FP/partner-widget.php?firstName=$firstName&lastName=$lastName&clientId=3401&partnerName=werelate&widget=records&tr_device=$device" frameborder="0" scrolling="no" width="728" height="90"></iframe></div>
END;
}

/**
 * process <googlemap height="" width="">http://...</googlemap>
  */
function wrGoogleMap($input, $argv, $parser) {
   $width=425;
   $height=350;
   $src = htmlspecialchars($input);

   foreach ($argv as $key => $value) {
      if ($key == 'width') {
         $width = htmlspecialchars($value);
      }
      else if ($key == 'height') {
         $height = htmlspecialchars($value);
      }
   }

   return <<< END
<iframe width="$width" height="$height" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="$src&amp;output=embed"></iframe>
<br /><small><a href="$src&amp;source=embed" style="color:#0000FF;text-align:left">Larger map</a></small>
END;
}

function wrDonateHook($input, $argv, $parser) {
	return <<< END
<div id="wr-paypal">
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="3HCYZLU4R4YAN">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
</div>
END;
}

function wrScreencast($input, $argv, $parser) {
	global $wgOut;
	
	// get input variables
	$thumb = '';
	$video = '';
	$width = '';
	$height = '';
	$fields = explode('|', $input);
	foreach ($fields as $field) {
		$nameValue = explode('=', $field);
		if (count($nameValue) == 2) {
			$name = trim($nameValue[0]);
			$value = htmlspecialchars(trim($nameValue[1]));
			if ($name == 'thumb') {
				$thumb = $value;
			}
			else if ($name == 'video') {
				$video = $value;
			}
			else if ($name == 'width') {
				$width = $value;
			}
			else if ($name == 'height') {
				$height = $value;
			}
		}
	}
	if ($thumb && $video && $width && $height) {
		return '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="'.$width.'" height="'.$height.'">'.
			'<param name="movie" value="http://content.screencast.com/bootstrap.swf"></param>'.
			'<param name="quality" value="high"></param>'.
			'<param name="bgcolor" value="#FFFFFF"></param>'.
			'<param name="flashVars" value="thumb=http://content.screencast.com/media/'.$thumb.'&content=http://content.screencast.com/media/'.$video.'&width='.$width.'&height='.$height.'"></param>'.
			'<param name="allowFullScreen" value="true"></param>'.
			'<param name="scale" value="showall"></param>'.
			'<param name="allowScriptAccess" value="always"></param>'.
			'<embed src="http://content.screencast.com/bootstrap.swf" quality="high" bgcolor="#FFFFFF" width="'.$width.'" height="'.$height.'" type="application/x-shockwave-flash" allowScriptAccess="always" flashVars="thumb=http://content.screencast.com/media/'.$thumb.'&content=http://content.screencast.com/media/'.$video.'&width='.$width.'&height='.$height.'" allowFullScreen="true" scale="showall"></embed>'.
			'</object>';
	}
	else {
		return '';
	}
}

/**
 * Callback function for converting <youtube>width|height|id</youtube> into HTML
 */
function wrYouTube( $input, $argv, $parser) {
	// split into anchor, text, target
	$fields = explode('|', $input, 3);
	// validate
	if (count($fields) == 3 && strlen($fields[0]) > 0 && strlen($fields[1]) > 0 && strlen($fields[2]) > 0) {
	   $width = htmlspecialchars($fields[0]);
	   $height = htmlspecialchars($fields[1]);
	   $id = htmlspecialchars($fields[2]);
		return "<object width=\"$width\" height=\"$height\"><param name=\"movie\" value=\"https://www.youtube.com/v/$id&hl=en&fs=1&rel=0\"></param>".
					"<param name=\"allowFullScreen\" value=\"true\"></param><param name=\"allowscriptaccess\" value=\"always\"></param>".
					"<embed src=\"https://www.youtube.com/v/$id&hl=en&fs=1&rel=0\" type=\"application/x-shockwave-flash\" allowscriptaccess=\"always\" allowfullscreen=\"true\" width=\"$width\" height=\"$height\"></embed</object>";
	}
	else {
		return '';
	}
}

//function wrGetSatisfaction($input, $argv, $parser) {
//   // get topics
//   $result = <<<END
//<div id='gsfn_search_widget'>
//<div class='gsfn_content clearfix'>
//<form accept-charset='utf-8' action='http://getsatisfaction.com/werelate' id='gsfn_search_form' method='get' onsubmit='gsfn_search(this); return false;'>
//<div>
//<input name='style' type='hidden' value='?' />
//<input name='limit' type='hidden' />
//<input name='utm_medium' type='hidden' value='widget_search' />
//<input name='utm_source' type='hidden' value='widget_werelate' />
//<input name='callback' type='hidden' value='gsfnResultsCallback' />
//<input name='format' type='hidden' value='widget' />
//<label class='gsfn_label' for='gsfn_search_query'>Ask a question, share an idea, or report a problem...</label>
//<input id='gsfn_search_query' maxlength='120' name='query' type='text' value='' />
//<input id='continue' type='submit' value='Continue' />
//</div>
//</form>
//<div id='gsfn_search_results' style='height: auto;'></div>
//</div>
//<a href="http://getsatisfaction.com/werelate" class="widget_title">Browse all questions and ideas</a>
//<div class='powered_by'>
//<a href="http://getsatisfaction.com/"><img alt="Burst16" src="http://getsatisfaction.com/images/burst16.png" style="vertical-align: middle;" /></a>
//<a href="http://getsatisfaction.com/">Service and support by Satisfaction</a>
//</div>
//</div>
//<script src="http://getsatisfaction.com/werelate/widgets/javascripts/a04da23bc4/widgets.js" type="text/javascript"></script>
//END;
//   return str_replace("\n","", $result);
//}

// log external email sent
function wrLogEmail( $to, $from, $subject, $text ) {
	global $wgTitle;

	$addrs = explode(',', $to->address);
	$n = count($addrs);
	$log = new LogPage( 'email', false );
	$log->addEntry( 'email', $wgTitle, "Sent to $n addresses");
	return true;
}

function wrAddNewAccount( $user = NULL ) {
   global $wgUser, $wrBotUserID;

	// log new users
//	wfDebug("wrLogNewUser ". $user->getName() . ":" . $user->getUserPage()->getPrefixedText() . "\n");
	$log = new LogPage( 'newuser', false );
	$ip = wfGetIP();
	$log->addEntry( 'newuser', $user->getTalkPage(), wfMsgForContent( 'newuserlog', $ip ), $ip );

	// create default tree for new users
//	wfDebug("wrCreateDefaultTree " . $user->getName() . "\n");
	$db =& wfGetDB( DB_MASTER );
	FamilyTreeUtil::createFamilyTree($db, $user->getName(), 'Default');

   // add a welcome message
   // read the text from Template:Welcome1
   $title = Title::newFromText('Welcome1', NS_TEMPLATE);
   if ($title && $title->exists()) {
      $article = new Article($user->getTalkPage(), 0);
      $talkContents = '';
      if ($article) {
         $talkContents = $article->fetchContent();
         if ($talkContents) {
            $talkContents = rtrim($talkContents) . "\n\n";
         }
      }
      $saveUser = $wgUser;
      $wgUser = User::newFromName(User::whoIs($wrBotUserID));
      $article->doEdit($talkContents . '{{Subst:Welcome1}}', 'Welcome!');
      $wgUser = $saveUser;
   }

	return true;
}

// restrict max accounts created per time period
function wrAbortNewAccount($user=NULL, &$abortError) {
	if ($user->pingLimiter('createaccount')) {
		$abortError = wfMsg('acct_creation_throttleall_hit');
		return false;
	}

   // retrict names to not case-insensitive match existing names
   $dbr =& wfGetDB(DB_SLAVE);
   if ($dbr->selectField('user', 'user_name', array('LOWER(user_name)' => mb_strtolower($user->mName))) !== false) {
      $abortError = wfMsg( 'userexists' );
      return false;
   }
	return true;
}

function wrAddContentActions(&$template, &$content_actions) {
	global $wgRequest;

   // if this is a family tree namespace and the user is logged in and the page exists, then add an action for add to / remove from tree
   if ($template->mTitle->getArticleId() && ($template->mTitle->getNamespace() == NS_PERSON || $template->mTitle->getNamespace() == NS_FAMILY)) {
		$t = Title::makeTitle( NS_SPECIAL, 'ShowPedigree' );
		$content_actions['pedigree'] = array(
			'class' => false,
			'text' => wfMsg('pedigree'),
			'title' => wfMsg('pedigreetip'),
			'href' => $t->getLocalUrl( 'pagetitle='.$template->mTitle->getPrefixedURL()),
		);
   }
   	
   if ($template->mUser->isLoggedIn() && $template->mTitle->getArticleId() 
   	&& FamilyTreeUtil::isTreePage($template->mTitle->getNamespace(), $template->mTitle->getDBkey())) {
   	// add share button
		$t = Title::makeTitle( NS_SPECIAL, 'Email' );
		$content_actions['share'] = array(
			'class' => false,
			'text' => wfMsg('share'),
			'title' => wfMsg('emailtip'),
			'href' => $t->getLocalUrl( 'returnto='.$template->mTitle->getPrefixedURL()),
		);
  	}

   if ($template->mTitle->getArticleId() && ($template->mTitle->getNamespace() == NS_PERSON || $template->mTitle->getNamespace() == NS_FAMILY)) {
		$t = Title::makeTitle( NS_SPECIAL, 'Search' );
		if ($template->mTitle->getNamespace() == NS_PERSON) {
			$ns = 'Person';
		}
		else {
			$ns = 'Family';
		}
		$content_actions['match'] = array(
			'class' => false,
			'text' => wfMsg('Findduplicates'),
			'href' => $t->getLocalUrl("match=on&ns=$ns&pagetitle=".$template->mTitle->getPartialURL()),
		);
		// compare duplicate parents/spouses husbands/wives - the URLs will get fixed up or the actions deleted in wikibits.js
		$t = Title::makeTitle(NS_SPECIAL, 'Compare');
		if ($ns == 'Person') {
			$content_actions['compare-parents'] = array(
				'class' => false,
				'text' => wfMsg('CompareParents'),
				'href' => $t->getLocalUrl('ns=Family&compare='),
			);
			$content_actions['compare-spouses'] = array(
				'class' => false,
				'text' => wfMsg('CompareSpouses'),
				'href' => $t->getLocalUrl('ns=Family&compare='),
			);
		}
		else {
			$content_actions['compare-husbands'] = array(
				'class' => false,
				'text' => wfMsg('CompareHusbands'),
				'href' => $t->getLocalUrl('ns=Person&compare='),
			);
			$content_actions['compare-wives'] = array(
				'class' => false,
				'text' => wfMsg('CompareWives'),
				'href' => $t->getLocalUrl('ns=Person&compare='),
			);
		}
   }
   	
   if ($template->mUser->isLoggedIn() && $template->mTitle->getArticleId() 
   	&& FamilyTreeUtil::isTreePage($template->mTitle->getNamespace(), $template->mTitle->getDBkey())) {
	  	// add tree +/- button
		$action = $wgRequest->getText( 'action' );
		$content_actions['treeUpdate'] = array(
			'class' => $action == 'treeUpdate'  ? 'selected' : false,
			'text' => wfMsg('treeupdate'),
			'title' => wfMsg('treeupdatetip'),
			'href' => $template->mTitle->getLocalUrl( 'action=treeUpdate' )
		);
	}
	return true;
}

function wrDoContentActions($action, $article) {
	global $wgUser, $wgOut, $wgRequest;

	if ($action != 'treeUpdate' && $action != 'treeUpdateConfirm') {
	  return true; // handle by another content action handler
	}

	if ( $wgUser->isAnon() ) {
		$wgOut->showErrorPage( 'treenologintitle', 'treenologintext' );
		return false;
	}
	if (!FamilyTreeUtil::isTreePage($article->getTitle()->getNamespace(), $article->getTitle()->getDBkey())) {
		$wgOut->showErrorPage( 'treenonamespacetitle', 'treenonamespacetext' );
		return false;
	}
	if ( wfReadOnly() ) {
		$wgOut->readOnlyPage();
		return false;
	}

	$allTrees = FamilyTreeUtil::getFamilyTrees($wgUser->getName());
	$treeOwnerIds = FamilyTreeUtil::getOwnerTrees($wgUser, $article->getTitle(), false);
	$checkedTreeIds = FamilyTreeUtil::readTreeCheckboxes($allTrees, $wgRequest);
	$ancGenerations = $wgRequest->getVal('ancestorgenerations');
	$includeAncestorChildren = $wgRequest->getCheck('includeancestorchildren');
	$descGenerations = $wgRequest->getVal('descendantgenerations');

	$wgOut->setRobotpolicy( 'noindex,nofollow' );

	if ($action == 'treeUpdateConfirm' && (($ancGenerations == 0 && $descGenerations == 0) || count($checkedTreeIds) > 0)) {
  		$dbw =& wfGetDB( DB_MASTER );
  		$result = FamilyTreeUtil::updateTrees($dbw, $article->getTitle(), $article->getRevIdFetched(), $allTrees, $treeOwnerIds, $checkedTreeIds);

		if ($result) {
			$dbw->commit();
		}
		else {
			$dbw->rollback();
		}
		if ($ancGenerations > 0 || $descGenerations > 0) {
	      $job = new AddTreePagesJob(array('trees' => join(',', $checkedTreeIds), 'user' => $wgUser->getName(), 'title' => $article->getTitle()->getPrefixedText(),
										'ancGenerations' => $ancGenerations, 'includeAncestorChildren' => $includeAncestorChildren, 'descGenerations' => $descGenerations));
	      $job->insert();
			$treesUpdatedMsg = 'treesupdatedtextdeferred';
		}
		else {
			$treesUpdatedMsg = 'treesupdatedtext';
		}
		$wgOut->setPagetitle( wfMsg( 'treesupdatedtitle' ) );
		$wgOut->addWikiText( wfMsg( $treesUpdatedMsg ) );
		$wgOut->returnToMain( true, $article->getTitle()->getPrefixedText() );
	}
	else if (count($allTrees) == 0) {
		$wgOut->showErrorPage( 'treenonetitle', 'treenonetext' );
	}
	else { // show form
		$wgOut->setPagetitle( wfMsg( 'treeupdatetitle' ) );
		if ($action == 'treeUpdateConfirm') {
			$wgOut->addHTML('<font color="red">Please check the box next to the tree(s) you want to add the pages to.</font>');
		}
		$wgOut->addWikiText('==Trees==');
		$wgOut->addWikiText(wfMsg('treeupdatetext'));
			// TODO show form
		$action = $article->getTitle()->escapeLocalURL( 'action=treeUpdateConfirm' );
  		$wgOut->addHTML( <<<END
<form id="treeUpdate" method="post" action="$action" enctype="multipart/form-data">
END
);
		$wgOut->addHTML(FamilyTreeUtil::generateTreeCheckboxes($wgUser, $article->getTitle(), false, $allTrees, $treeOwnerIds));
		
		if ($article->getTitle()->getNamespace() == NS_PERSON || $article->getTitle()->getNamespace() == NS_FAMILY) {
			$wgOut->addWikiText('==Include relatives==');
			$wgOut->addWikiText(":'''''Warning''': including relatives can add potentially many people to your tree and to your watchlist.''");
			$wgOut->addWikiText(':Add the following relatives to the checked trees.');
			$options = array(); for ($i = 0; $i <= 20; $i++) $options["$i"] = $i;
			$ancGenerations = StructuredData::addSelectToHtml(0, 'ancestorgenerations', $options, 0);
			$options = array(); for ($i = 0; $i <= 5; $i++) $options["$i"] = $i;
			$descGenerations = StructuredData::addSelectToHtml(0, 'descendantgenerations', $options, 0);
			
			$wgOut->addHTML( <<<END
<dl><dd>Include ancestors for $ancGenerations generations
	<dl><dd><input type="checkbox" name="includeancestorchildren"/>Also include ancestors' children</dd></dl></dd>
<dd>&nbsp;</dd>
<dd>Include descendants for $descGenerations generations</dd>
</dl>
END
);
		}
		
		$updateButton = array(
			'id'		=> 'wrTreeUpdate',
			'name'		=> 'wrTreeUpdate',
			'type'		=> 'submit',
			'value'	  => wfMsg('treeupdatebutton'),
			'title'	  => wfMsg('treeupdatebuttontip')
		);
		$wgOut->addHTML(wfElement('input', $updateButton, ''));
		$wgOut->addHTML( "</form>\n" );
	}

	return false; // stop processing
}

function wrAddCaptcha(&$template) {
   global $wrRecaptchaPublicKey;

   if ($wrRecaptchaPublicKey) {
      $template->set('captcha', '<tr><td></td><td>'.recaptcha_get_html($wrRecaptchaPublicKey, null, true).'</td></tr>');
   }
}

function wrValidateUser($user, &$errorMsg, $email) {
   global $wrRecaptchaPrivateKey;

   if ($wrRecaptchaPrivateKey) {
      $resp = recaptcha_check_answer($wrRecaptchaPrivateKey,
                                   $_SERVER["REMOTE_ADDR"],
                                   $_POST["recaptcha_challenge_field"],
                                   $_POST["recaptcha_response_field"]);
      if (!$resp->is_valid) {
         $errorMsg = "The reCAPTCHA wasn't entered correctly. Please try again.";
         return false;
      }
   }

   if ($email == '') {
		$errorMsg = wfMsg( 'emailrequired' );
		return false;
	}
   if (mb_strpos($user->getName(), '@') !== false) {
      $errorMsg = wfMsg('invalidusername');
      return false;
   }
	return true;
}

function wrArticleSave(&$article, &$user, $text) {

	$newTitle = Title::newFromRedirect($text); // can't use $article->content or SD:getRedirectToTitle because they return the wrong results
	if ($newTitle != null) { // if this article is a redirect
		$newTitle = StructuredData::getRedirectToTitle($newTitle, true); // get final redirect; unreliable on the article title for some reason
      $newRevision = StructuredData::getRevision($newTitle, false, true);
      $newText = ($newRevision ? $newRevision->getText() : '');
      $summary = StructuredData::getWatchlistSummary($newTitle, $newText);
		WatchedItem::duplicateEntries($article->getTitle(), $newTitle, $summary);
	   StructuredData::purgeTitle($newTitle, +1);
		StructuredData::requestIndex($newTitle);

      // remove watchers from redirected titles
      StructuredData::removeAllWatchers($article->getTitle());
	}
	return true;
}

function wrSetWatchlistSummary(&$user, &$article, $newText=null) {
   global $wrWatchlistSummary;

   if (is_null($newText)) {
      $newText = $article->getContent();
   }
   $wrWatchlistSummary = StructuredData::getWatchlistSummary($article->getTitle(), $newText);
   return true;
}

function wrIndexPurgeArticle(&$user, &$article, $newRevision = false) {
	global $wgMemc;

   StructuredData::purgeTitle($article->getTitle(), +1); // purge the article so the watcher list is correct
	if (!$newRevision) {
		StructuredData::requestIndex($article->getTitle());
	}
	// also purge network cache for user
   $cacheKey = 'network:'.$user->getID();
 	$wgMemc->delete($cacheKey);
 	
	return true;
}

function wrIndexArticle(&$article) {
	StructuredData::requestIndex($article->getTitle());
	return true;
}

function wrMovePage(&$oldTitle) {
   // remove watchers from old title
   StructuredData::removeAllWatchers($oldTitle);

   if ($oldTitle->getNamespace() == NS_PLACE) {
      StructuredData::requestIndex($oldTitle); // moves are indexed separately, but force place to be re-indexed before indexing wlh
      $dbw =& wfGetDB(DB_MASTER);
      $ts = wfTimestampNow();
      $sql = 'insert ignore into index_request (ir_page_id, ir_timestamp) '.
              '(select pl_from,'.$dbw->addQuotes($ts).' from pagelinks where'.
              ' pl_namespace='.$dbw->addQuotes($oldTitle->getNamespace()).
              ' and pl_title='.$dbw->addQuotes($oldTitle->getDBkey()).')';
      $dbw->query($sql);
   }
   return true;
}

function wrAddSubpage( $input, $argv, $parser) {
   global $wgTitle, $wgUser;

   if (!$wgUser->isLoggedIn()) {
      return '<p><font color="red">Sign in to add suggestions</font></p>';
   }

   $super = @$argv['super'];
   if (!$super) {
      $super = $wgTitle->getPrefixedText();
   }

   return <<<END
<form name="search" action="/wiki/Special:AddSubpage" method="get">
<input type="hidden" name="super" value="$super"/>
<label for="sub">Title </label><input id="input_sub" type="text" name="sub" size="40" value=""/>
<input type="submit" value="Add"/>
</form>
END;
}

function wrListSubpages( $input, $argv, $parser) {
   global $wgTitle, $wgUser;

   $input = trim($input);
   $super = @$argv['super'];
   if (!$super) {
      $super = $wgTitle->getPrefixedText();
   }
   $superTitle = Title::newFromText($super);
   if (!$superTitle || !$input) {
      return '';
   }
   $titleDates = explode("\n", $input);
   $watchers = $argv['watchers'];
   $sort = $argv['sort'];
   if ($sort == 'watchers') {
      $watchers = true;
   }
   $dir = $argv['direction'];

   // read titles
   $titleData = array();
   $dbr =& wfGetDB(DB_SLAVE);
   if ($watchers) {
      $sql = 'select page_namespace, page_title, rev_user_text, rev_timestamp, count(*) as watchers from page, revision, watchlist'.
             ' where page_namespace='.$dbr->addQuotes($superTitle->getNamespace()).
             ' and page_title like '.$dbr->addQuotes($superTitle->getDBKey().'/%').
             ' and page_latest = rev_id and page_is_redirect = 0 and page_namespace = wl_namespace and page_title = wl_title'.
             ' group by page_namespace, page_title, rev_user_text, rev_timestamp';
   }
   else {
      $sql = 'select page_namespace, page_title, rev_user_text, rev_timestamp from page, revision'.
             ' where page_namespace='.$dbr->addQuotes($superTitle->getNamespace()).
             ' and page_title like '.$dbr->addQuotes($superTitle->getDBKey().'/%').
             ' and page_latest = rev_id and page_is_redirect = 0';
   }
   $rows = $dbr->query($sql, 'wrListSubpages');
   while ( $row = $dbr->fetchObject( $rows ) ) {
      $t = Title::makeTitle($row->page_namespace, $row->page_title);
      $key = mb_substr($t->getText(), mb_strlen($superTitle->getText())+1);
      $titleData[$key] = array('title' => $t, 'lastmod' => $row->rev_timestamp, 'user' => $row->rev_user_text, 'watchers' => @$row->watchers);
   }
   $dbr->freeResult( $rows );

   // gather data
   $sortTitles = array();
   for ($i = 0; $i < count($titleDates); $i++) {
      $titleDate = $titleDates[$i];
      $fields = explode('|', $titleDate);
      $fields[0] = trim($fields[0]);
      // get other parameters from titleData
      $data = @$titleData[$fields[0]];
      if (!$data) {
         $t = Title::newFromText("$super/${fields[0]}");
         $data = array('title' => $t, 'lastmod' => '', 'user' => '', 'watchers' => '');
      }
      $data['subtitle'] = $fields[0];
      $d = null;
      if (count($fields) > 1) {
         try {
            $d = new DateTime(trim($fields[1])); // 'j M Y',
         }
         catch (Exception $e) {
         }
      }
      if ($d) {
         $data['created'] = $d->format('Ymd');
      }
      else {
         $data['created'] = '';
      }
      if ($sort == 'created') {
         $key = $data['create'].$i;
      }
      else if ($sort == 'lastmod') {
         $key = $data['lastmod'].$i;
      }
      else if ($sort == 'watchers') {
         $key = str_pad($data['watchers'],4,'0',STR_PAD_LEFT).$i;
      }
      else {
         $key = $fields[0].$i;
      }
      $sortTitles[$key] = $data;
   }

   if ($dir == 'desc') {
      krsort($sortTitles);
   }
   else {
      ksort($sortTitles);
   }

   // construct table
   $twoWeeksAgo = new DateTime();
   $twoWeeksAgo->modify('-2 weeks');
   $skin =& $wgUser->getSkin();
   $result = '<table class="wikitable sortable"><tr><th>Title</th><th>Created</th><th>Last modified</th><th>by</th>'.
               ($watchers ? '<th>Watchers</th>' : '').'</tr>';
   foreach ($sortTitles as $key => $data) {
      if ($data['lastmod']) {
         $title = $skin->makeKnownLinkObj($data['title'], $data['subtitle']);
      }
      else {
         $title = $skin->makeBrokenLinkObj($data['title'], $data['subtitle']);
      }
      $d = null;
      if ($data['lastmod'] && $data['created']) {
         $d = new DateTime($data['created']); //'Ymd',
      }
      if ($d) {
         $created = $d->format('j M Y');
      }
      else {
         $created = '';
      }
      $d = null;
      if ($data['lastmod']) {
         $d = new DateTime(substr($data['lastmod'], 0, 8)); // 'Ymd',
      }
      if ($d) {
         $lastmod = $skin->makeKnownLinkObj($data['title'], $d->format('j M Y'), 'action=history');
      }
      else {
         $lastmod = '';
      }
      if ($data['user']) {
         $t = Title::makeTitle(NS_USER, $data['user']);
         $user = $skin->makeLinkObj($t);
      }
      else {
         $user = '';
      }
      $watchersCnt = $data['watchers'];
      $result .= "<tr><td>$title</td><td>$created</td><td>$lastmod</td><td>$user</td>".
               ($watchers ? "<td align=\"right\">$watchersCnt</td>" : '').'</tr>';
   }
   $result .= '</table>';

   // don't cache this page, because its content depends upon things not in the page
   $parser->disableCache();

   return $result;
}

function wrCheckSpam($editPage, $textBox1, $section, &$hookError) {
   global $wgUser, $wgMemc;

   $title = $editPage->mTitle;
   // if user has made fewer than 5 edits, the title is not a person, family, or mysource (don't mess up gedcom uploads), and the page is new,
   // don't allow offsite links
   if ($title->getNamespace() != NS_PERSON &&
       $title->getNamespace() != NS_FAMILY &&
       $title->getNamespace() != NS_MYSOURCE &&
       (mb_stripos($textBox1, 'http:/') !== false || mb_stripos($textBox1, 'https:/') !== false)) {
      // how many edits has this user made?
      $dbr =& wfGetDB(DB_SLAVE);
      $cacheKey = 'wreditcnt:'+$wgUser->getID();
      $cnt = $wgMemc->get($cacheKey);
      if (!$cnt) {
         $cnt = $dbr->selectField('revision', 'count(*)', array('rev_user' => $wgUser->getID()));
         $wgMemc->set($cacheKey, $cnt, 3600*4);
      }
      if ($cnt < 5) {
         $hookError = '<b>Links to other websites are not allowed</b>';
      }
   }
	return true;
}
?>
