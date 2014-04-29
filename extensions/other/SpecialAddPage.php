<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialAddPageSetup";

function wfSpecialAddPageSetup() {
   global $wgMessageCache, $wgSpecialPages, $wgParser;
   $wgMessageCache->addMessages( array( "addpage" => wfMsg('addpage') ) );
   $wgSpecialPages['AddPage'] = array('SpecialPage','AddPage');
}

function wfSpecialAddPage($par) {
   global $wgOut, $wgRequest, $wgUser, $wgLang, $wgCommandLineMode, $wgScriptPath;

   $error = '';
  	$editParms = '';
   $title = null;

	$addPageForm = new AddPageForm();

	// read query parameters into variables
	$addPageForm->readQueryParms($par);

   if (!$wgUser->isLoggedIn()) {
		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
			User::SetupSession();
		}
		$request = new FauxRequest(array('returnto' => $wgLang->specialPage('AddPage') . '/' . $addPageForm->namespace));
		require_once('includes/SpecialUserlogin.php');
		$form = new LoginForm($request);
		$form->mainLoginForm("You need to log in to add pages<br/><br/>", '');
		return;
	}
	if( $wgUser->isBlocked() ) {
		$wgOut->blockedPage();
		return;
	}
	if( wfReadOnly() ) {
		$wgOut->readOnlyPage();
		return;
	}
  	
	// redirect?
	list ($redirTitle, $error) = $addPageForm->getRedirTitleOrError();
	if ($redirTitle != null) {
		$editParms = $addPageForm->getEditParms();
      $wgOut->redirect($redirTitle->getFullURL('action=edit'.$editParms));
		return;
	}
	
	// get form text
	$formHtml = $addPageForm->getFormHtml();
	$pageTitle = $addPageForm->getPageTitle();
	$pageHeading = $addPageForm->getPageHeading();
	$msgId = $addPageForm->getMessageId();
	
   // set up page
   $wgOut->setPagetitle($pageTitle);
   $wgOut->setArticleRelated(false);
   $wgOut->setRobotpolicy('noindex,nofollow');
   if ($addPageForm->namespace == NS_PERSON || $addPageForm->namespace == NS_FAMILY || $addPageForm->namespace == NS_SOURCE || $addPageForm->namespace == NS_PLACE) {
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/search.yui.30.js\"></script>");
   	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.yui.8.js\"></script>");
   }

   $wgOut->addHTML("<h2>$pageHeading</h2>");
   if ($error) {
      $wgOut->addHTML("<p><font color=red>$error</font></p>");
   }

   $wgOut->addHTML($formHtml);
   $wgOut->addWikiText("\n\n".wfmsg($msgId));
}

/**
  * Search form used in Special:Search and <search> hook
  */
class AddPageForm {
	private static $MSGIDS = array(NS_GIVEN_NAME => 'addgivennamepageend', NS_SURNAME => 'addsurnamepageend', NS_PERSON => 'addpersonpageend', 
											NS_FAMILY => 'addfamilypageend', NS_SOURCE => 'addsourcepageend', NS_MYSOURCE => 'addmysourcepageend', 
											NS_MAIN => 'addarticleend', NS_USER => 'adduserpageend', NS_PLACE => 'addplacepageend',
                                 NS_TRANSCRIPT => 'addtranscriptpageend', NS_REPOSITORY => 'addrepositorypageend');

	public $namespace;
	private $titleText;
   private $givenname;
  	private $surname;
  	private $gender;
   private $birthdate;
   private $birthplace;
   private $deathdate;
   private $deathplace;
   private $husbandGivenname;
   private $husbandSurname;
   private $wifeGivenname;
   private $wifeSurname;
   private $marriagedate;
   private $marriageplace;
   private $husbandTitle;
   private $wifeTitle;
   private $childTitle;
   private $parentFamily;
   private $spouseFamily;
   private $sourceType;
	private $sourceTitle;
	private $author;
	private $place;
	private $placeIssued;
	private $publisher;
	private $placeName;
	private $locatedIn;
	private $target;
   private $confirm;
	
	public function readQueryParms($par) {
		global $wgRequest, $wgUser, $wgLang;
		
		$this->namespace = $wgRequest->getVal('namespace');
		if (!$this->namespace && $par) {
			$this->namespace = $par;
		}
      if (!$this->namespace && $wgRequest->getVal('ns')) {
         $this->namespace = $wgRequest->getVal('ns');
      }
      if ($this->namespace && !is_numeric($this->namespace)) {
         if ($this->namespace == 'Article') {
            $this->namespace = '0';
         }
         else {
            $this->namespace = $wgLang->getNsIndex($this->namespace);
         }
      }
   	$this->titleText = $wgRequest->getVal('t');
   	$this->givenname = $wgRequest->getVal('g');
   	$this->surname = $wgRequest->getVal('s');
   	$this->gender = $wgRequest->getVal('gnd');
   	$this->birthdate = $wgRequest->getVal('bd');
   	$this->birthplace = $wgRequest->getVal('bp');
   	$this->deathdate = $wgRequest->getVal('dd');
   	$this->deathplace = $wgRequest->getVal('dp');
   	$this->husbandGivenname = $wgRequest->getVal('hg');
   	$this->husbandSurname = $wgRequest->getVal('hs');
   	$this->wifeGivenname = $wgRequest->getVal('wg');
   	$this->wifeSurname = $wgRequest->getVal('ws');
   	$this->marriagedate = $wgRequest->getVal('md');
   	$this->marriageplace = $wgRequest->getVal('mp');
      $this->husbandTitle = $wgRequest->getVal('ht');
      $this->wifeTitle = $wgRequest->getVal('wt');
      $this->childTitle = $wgRequest->getVal('ct');
      $this->parentFamily = $wgRequest->getVal('pf');
      $this->spouseFamily = $wgRequest->getVal('sf');
   	$this->sourceType = $wgRequest->getVal('sty');
   	$this->sourceTitle = $wgRequest->getVal('st');
   	$this->author = $wgRequest->getVal('a');
   	$this->place = $wgRequest->getVal('p');
   	$this->placeIssued = $wgRequest->getVal('pi');
   	$this->publisher = $wgRequest->getVal('pu');
   	$this->placeName = $wgRequest->getVal('pn');
   	$this->locatedIn = $wgRequest->getVal('li');
   	$this->target = $wgRequest->getVal('target');
   	$this->confirm = $wgRequest->getBool('confirm');

   	// prepend user name to title if user or mysource page
		if ($this->namespace == NS_USER || $this->namespace == NS_USER_TALK || $this->namespace == NS_MYSOURCE || $this->namespace == NS_MYSOURCE_TALK) {
			if ($this->titleText && mb_strpos($this->titleText, $wgUser->getName().'/') !== 0 &&
				 !(($this->namespace == NS_USER || $this->namespace == NS_USER_TALK) && $this->titleText == $wgUser->getName())) {
				$this->titleText = $wgUser->getName().'/'.$this->titleText;
			}
		}
	}
	
	public function getPageHeading() {
		global $wgLang;
		
		if ($this->namespace == NS_PLACE || $this->namespace == NS_SOURCE || $this->namespace == NS_MYSOURCE ||
          $this->namespace == NS_PERSON || $this->namespace == NS_FAMILY) {
			return wfMsg('addpageinstructionsstep01');
		}
		else {
			return '';
		}
	}
	
	public function getPageTitle() {
		global $wgLang;
		
	  	if (strlen($this->namespace) > 0) {
		   $nsText = $wgLang->getFormattedNsText($this->namespace);
		   if (!$nsText) {
		   	$nsTitle = wfMsg('addpagetitlearticle');
		   }
		   else {
		   	$nsTitle = wfMsg('addpagetitleother', $nsText);
		   }
	  	}
	  	else {
	  		$nsTitle = wfMsg('addpagetitlepage');
	  	}
 		return wfMsg('addpagetitle', $nsTitle);
	}
	
	public function getMessageId() {
		$msgid = @AddPageForm::$MSGIDS[$this->namespace];
		if (!$msgid) {
			$msgid = 'addpageend';
		}
		return $msgid;
	}

	public function getRedirTitleOrError() {
		global $wgUser;
		
		$title = null;
		$error = '';
		
		if ($this->confirm) {
			if ($this->namespace == NS_IMAGE) {
		   	$error = 'Add images by selecting Image from the Add menu';
			}
			else {
				if ($this->namespace == NS_PERSON) {
					$title = StructuredData::constructPersonTitle($this->givenname, $this->surname);
				}
				else if ($this->namespace == NS_FAMILY) {
					$title = StructuredData::constructFamilyTitle($this->husbandGivenname, $this->husbandSurname, $this->wifeGivenname, $this->wifeSurname);
				}
				else if ($this->namespace == NS_SOURCE) {
					$title = StructuredData::constructSourceTitle($this->sourceType, $this->sourceTitle, $this->author, $this->place, $this->placeIssued, $this->publisher);
					if (!$title) $error = 'Please fill in the Source type, Title, and for government / church records: Place covered';
				}
				else if ($this->namespace == NS_PLACE) {
               if (!$this->locatedIn && !$wgUser->isAllowed('patrol')) {
                  $error = 'Please enter the place in which this place is located.';
               }
               else {
					   $title = StructuredData::constructPlaceTitle($this->placeName, $this->locatedIn);
               }
				}
				else {
					$title = Title::newFromText($this->titleText, $this->namespace);
				}
			   if (!$title && !$error) {
		      	$error = wfmsg('invalidtitle');
				}
			}
		}
		
	  	return array($title, $error);
	}

   // I don't think this is ever called with any parameters
	public function getEditParms() {
      $parms = '';
		if ($this->namespace == NS_PERSON) {
			$parms = '&g='.urlencode($this->givenname)
					.'&s='.urlencode($this->surname)
					.'&gnd='.urlencode($this->gender)
					.'&bd='.urlencode($this->birthdate)
					.'&bp='.urlencode($this->birthplace)
					.'&dd='.urlencode($this->deathdate)
               .'&dp='.urlencode($this->deathplace)
               .'&pf='.urlencode($this->parentFamily)
               .'&sf='.urlencode($this->spouseFamily);
		}
		else if ($this->namespace == NS_FAMILY) {
			$parms = '&md='.urlencode($this->marriagedate)
                .'&mp='.urlencode($this->marriageplace)
                .'&hg='.urlencode($this->husbandGivenname)
                .'&hs='.urlencode($this->husbandSurname)
                .'&wg='.urlencode($this->wifeGivenname)
                .'&ws='.urlencode($this->wifeSurname)
                .'&ht='.urlencode($this->husbandTitle)
                .'&wt='.urlencode($this->wifeTitle)
                .'&ct='.urlencode($this->childTitle);
		}
		else if ($this->namespace == NS_SOURCE) {
			$parms = '&sty='.urlencode($this->sourceType)
					.'&st='.urlencode($this->sourceTitle)
					.'&a='.urlencode($this->author)
					.'&p='.urlencode($this->place)
					.'&pi='.urlencode($this->placeIssued)
					.'&pu='.urlencode($this->publisher);
		}
      else if ($this->namespace == NS_MYSOURCE) {
         $parms = '&a='.urlencode($this->author)
               .'&p='.urlencode($this->place)
               .'&s='.urlencode($this->surname);
      }
      return $parms.($this->target ? '&target='.urlencode($this->target) : '');
	}
	
	public function getFormHtml() {
		global $wgLang, $wgUser;
		
		$target = $this->target;
		if (!$target) {
			$target = 'AddPage';
		}
		$buttonValue = 'Next';
	   $nsText = $wgLang->getFormattedNsText($this->namespace);

	   if ($this->namespace == NS_PERSON) {
	   	$givenname = htmlspecialchars($this->givenname);
	   	$surname = htmlspecialchars($this->surname);
	   	$birthdate = htmlspecialchars($this->birthdate);
	   	$birthplace = htmlspecialchars($this->birthplace);
	   	$deathdate = htmlspecialchars($this->deathdate);
	   	$deathplace = htmlspecialchars($this->deathplace);
			$genderSelect = StructuredData::addSelectToHtml(1, 'gnd', Person::$GENDER_OPTIONS, $this->gender);
         $parentFamily = htmlspecialchars($this->parentFamily);
         $spouseFamily = htmlspecialchars($this->spouseFamily);
         $wifegivenname = htmlspecialchars($this->wifeGivenname); // if we're adding a father we need to remember the mother's name
         $wifesurname = htmlspecialchars($this->wifeSurname);

	     	$result = <<< END
<form name="search" action="/wiki/Special:Search" method="get">
<input type="hidden" name="target" value="$target"/>
<input type="hidden" id="ns" name="ns" value="$nsText"/>
<input type="hidden" id="input_pf" name="pf" value="$parentFamily"/>
<input type="hidden" id="input_sf" name="sf" value="$spouseFamily"/>
<input type="hidden" id="input_wg" name="wg" value="$wifegivenname"/>
<input type="hidden" id="input_ws" name="ws" value="$wifesurname"/>
<table class="searchform">
<tr><td align="right">Given name: </td><td><input type="text" id="givenname_input" name="g" size=15 maxlength="50" value="$givenname" tabindex="1"/></td>
  <td align="right">Surname: </td><td><input type="text" name="s" size=35 maxlength="50" value="$surname" tabindex="1"/></td></tr>
<tr><td align="right">Gender: </td><td>$genderSelect</td></tr>
<tr><td align="right">Birth date: </td><td><input type="text" name="bd" size=15 maxlength="25" value="$birthdate"  tabindex="1" /></td>
  <td align="right">Place: </td><td><input class="place_input" type="text" name="bp" size=35 maxlength="130" value="$birthplace" tabindex="1" /></td></tr>
<tr><td align="right">Death date: </td><td><input type="text" name="dd" size=15 maxlength="25" value="$deathdate" tabindex="1" /></td>
  <td align="right">Place: </td><td><input class="place_input" type="text" name="dp" size=35 maxlength="130" value="$deathplace" tabindex="1" /></td></tr>
<tr><td colspan=4 align="right"><input type="submit" name="add" value="$buttonValue" tabindex="1"/></td></tr></table>
</form>
END;
	   }
	   else if ($this->namespace == NS_FAMILY) {
         $gender = htmlspecialchars($this->gender);
         $husbandgivenname = htmlspecialchars($this->husbandGivenname);
			$husbandsurname = htmlspecialchars($this->husbandSurname);
			$wifegivenname = htmlspecialchars($this->wifeGivenname);
			$wifesurname = htmlspecialchars($this->wifeSurname);
			$marriagedate = htmlspecialchars($this->marriagedate);
         $marriageplace = htmlspecialchars($this->marriageplace);
         $husbandTitle = htmlspecialchars($this->husbandTitle);
         $wifeTitle = htmlspecialchars($this->wifeTitle);
         $childTitle = htmlspecialchars($this->childTitle);
	   	$result = <<< END
<form name="search" action="/wiki/Special:Search" method="get">
<input type="hidden" name="target" value="$target"/>
<input type="hidden" id = "ns" name="ns" value="$nsText"/>
<input type="hidden" id="input_gnd" name="gnd" value="$gender"/>
<input type="hidden" id="input_ht" name="ht" value="$husbandTitle"/>
<input type="hidden" id="input_wt" name="wt" value="$wifeTitle"/>
<input type="hidden" id="input_ct" name="ct" value="$childTitle"/>
<table class="searchform">
<tr id="husband_row"><td align="right">Husband given name: </td><td><input type="text" name="hg" size=15 maxlength="50" value="$husbandgivenname" tabindex="1" /></td>
  <td align="right">Surname: </td><td><input type="text" name="hs" size=25 maxlength="50" value="$husbandsurname" tabindex="1" /></td></tr>
<tr><td align="right">Wife given name: </td><td><input type="text" name="wg" size=15 maxlength="50" value="$wifegivenname" tabindex="1" /></td>
  <td align="right">Maiden name: </td><td><input type="text" name="ws" size=25 maxlength="50" value="$wifesurname" tabindex="1" /></td></tr>
<tr><td align="right">Marriage date: </td><td><input type="text" name="md" size=15 maxlength="25" value="$marriagedate" tabindex="1" /></td>
  <td align="right">Place: </td><td><input class="place_input" type="text" name="mp" size=25 maxlength="130" value="$marriageplace" tabindex="1" /></td></tr>
<tr><td colspan=4 align="right"><input type="submit" name="add" value="$buttonValue" tabindex="1" /></td></tr></table>
</form>
END;
		}
	   else if ($this->namespace == NS_SOURCE) {
	   	$title = htmlspecialchars($this->sourceTitle);
	   	$author = htmlspecialchars($this->author);
	   	$place = htmlspecialchars($this->place);
	   	$placeIssued = htmlspecialchars($this->placeIssued);
	   	$publisher = htmlspecialchars($this->publisher);
			$select = StructuredData::addSelectToHtml(1, 'sty', Source::$ADD_SOURCE_TYPE_OPTIONS, $this->sourceType);

	     	$result = <<< END
<form name="search" action="/wiki/Special:Search" method="get">
<input type="hidden" name="target" value="$target"/>
<input type="hidden" id = "ns" name="ns" value="$nsText"/>
<table class="searchform">
<tr><td align="right">Source type:</td><td align="left">$select</td><td></td></tr>
<tr id="author_row"><td align="right">Author:</td><td align="left"><input type="text" name="a" size="35" value="$author" tabindex="1" /></td><td>&nbsp;<i>surname, given name(s) of first author</i></td></tr>
<tr><td align="right">Title:</td><td align="left"><input type="text" name="st" size="35" value="$title" tabindex="1" /></td><td>&nbsp;<i>title only, no subtitle</i></td></tr>
<tr><td align="right">Place covered:</td><td align="left"><input class="place_input" type="text" name="p" size="35" value="$place" tabindex="1" /></td><td>&nbsp;<i>for government/church records</i></td></tr>
<tr><td align="right">Publisher:</td><td align="left"><input type="text" name="pu" size="35" value="$publisher" tabindex="1" /></td><td></td></tr>
<tr><td align="right">Place issued:</td><td align="left"><input type="text" name="pi" size="35" value="$placeIssued" tabindex="1" /></td><td></td></tr>
<tr><td colspan=3 align="right"><input type="submit" name="add" value="$buttonValue" tabindex="1" /></td></tr>
</table></form>
END;
	   }
	   else if ($this->namespace == NS_MYSOURCE) {
	   	$author = htmlspecialchars($this->author);
			$title = htmlspecialchars($this->titleText);
	   	
	     	$result = <<< END
<form name="search" action="/wiki/Special:Search" method="get">
<input type="hidden" name="target" value="$target"/>
<input type="hidden" id = "ns" name="ns" value="$nsText"/>
<table class="searchform">
<tr><td align="right">Title:</td><td align="left"><input type="text" name="t" size="35" value="$title" tabindex="1" /></td></tr>
<tr><td colspan=2 align="right"><input type="submit" name="add" value="$buttonValue" tabindex="1" /></td></tr>
</table></form>
END;
	   }
	   else if ($this->namespace == NS_PLACE) {
	   	$placeName = htmlspecialchars($this->placeName);
	   	$locatedIn = htmlspecialchars($this->locatedIn);
	   	
	   	$result = <<< END
<form name="search" action="/wiki/Special:Search" method="get">
<input type="hidden" name="target" value="$target"/>
<input type="hidden" id = "ns" name="ns" value="$nsText"/>
<table class="searchform">
<tr id="placename_row"><td align="right">Place name:</td><td align="left"><input type="text" name="pn" size="20" value="$placeName" tabindex="1" /></td><td>Name of the place to add</td></tr>
<tr><td align="right">Located in:</td><td align="left"><input class="place_input" type="text" name="li" size="35" value="$locatedIn" tabindex="1" /></td><td>County, District, or State in which the place is located</td></tr>
<tr><td colspan=3 align="right"><input type="submit" name="add" value="$buttonValue" tabindex="1" /></td></tr>
</table></form>
END;
		}
	   else {
			$title = htmlspecialchars($this->titleText);
	   	if (strlen($this->namespace) == 0) {
				$hiddenField = '';
	     	   $namespaceselect = "<tr><td align=\"right\">Namespace:</td><td align=\"left\">" . HTMLnamespaceselector('', null) . "</tr>";
	   	}
			else {
				$hiddenField = "<input type=\"hidden\" name=\"namespace\" value=\"{$this->namespace}\"/>";
				$namespaceselect = '';
			}

          $titlewithcolon = wfMsg('personorfamilypagetitle');
	      $result = <<< END
<form name="search" action="/wiki/Special:AddPage" method="get">
<input type="hidden" name="confirm" value="true"/>
$hiddenField
<table class="searchform">
$namespaceselect<tr id="title_row"><td align="right">$titlewithcolon</td><td align="left"><input id="titleinput" type="text" name="t" size="40" maxlength="160" value="$title" />
</td><td><input type="submit" name="add" value="$buttonValue"/>
</td></tr></table></form>
END;
	   }
//      $treeCheckboxes = '';
//      if ($this->namespace == NS_PERSON || $this->namespace == NS_FAMILY) {
//         $treeCheckboxes = '<p>'.FamilyTreeUtil::generateTreeCheckboxes($wgUser, null, true).'</p>';
//      }
      $addPageCache = (($this->namespace == NS_SOURCE || $this->namespace == NS_MYSOURCE ||
                        $this->namespace == NS_PERSON || $this->namespace == NS_FAMILY) ?
                        '<div id="addpage_cache" style="display:none"></div>' : '');  // was $treeCheckboxes inside div
	   return '<center>'.$result.'</center>'.$addPageCache;
	}
	
}
?>
