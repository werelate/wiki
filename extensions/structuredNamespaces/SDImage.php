<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");
require_once("$IP/extensions/structuredNamespaces/TipManager.php");
require_once("$IP/extensions/Fotonotes/Fotonotes.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfImageExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfImageExtensionSetup() {
	global $wgHooks;
	global $wgParser;

	# Register hooks for edit UI, request handling, and save features
	$wgHooks['ArticleEditShow'][] = 'renderImageEditFields';
	$wgHooks['ImportEditFormDataComplete'][] = 'importImageEditData';
	$wgHooks['EditFilter'][] = 'validateImage';
	$wgHooks['ArticleSave'][] = 'propagateImageEdit';
	$wgHooks['TitleMoveComplete'][] = 'propagateImageMove';
	$wgHooks['ArticleDeleteComplete'][] = 'propagateImageDelete';
	$wgHooks['ArticleUndeleteComplete'][] = 'propagateImageUndelete';
	$wgHooks['ArticleRollbackComplete'][] = 'propagateImageRollback';
	
	# register the extension with the WikiText parser
	$wgParser->setHook('image_data', 'renderImageData');
}

/**
 * Callback function for converting resource to HTML output
 */
function renderImageData( $input, $argv, $parser) {
	$image = new SDImage($parser->getTitle()->getText());
	return $image->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderImageEditFields( &$editPage ) {
	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_IMAGE) {
		$image = new SDImage($editPage->mTitle->getText());
		$image->renderEditFields($editPage);
	}
	return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importImageEditData( &$editPage, &$request ) {
	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_IMAGE) {
		$image = new SDImage($editPage->mTitle->getText());
		$image->importEditData($editPage, $request);
	}
	return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateImage($editPage, $textBox1, $section, &$hookError) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_IMAGE) {
        $image = new SDImage($editPage->mTitle->getText());
        $image->validate($textBox1, $section, $hookError, true);
    }
    return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateImageEdit(&$article, &$user, &$text, &$summary, $minor, $dummy1, $dummy2, &$flags) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_IMAGE) {
		$image = new SDImage($article->getTitle()->getText());
		$image->propagateEdit($text, $article);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateImageMove(&$title, &$newTitle, &$user, $pageId, $redirPageId) {
	$ns = $title->getNamespace();
	if ($ns == NS_IMAGE) {
		$image = new SDImage($title->getText());
		$image->propagateMove($newTitle);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateImageDelete(&$article, &$user, $reason) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_IMAGE) {
		$image = new SDImage($article->getTitle()->getText());
		$image->propagateDelete($article);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateImageUndelete(&$title, &$user) {
	$ns = $title->getNamespace();
	if ($ns == NS_IMAGE) {
		$image = new SDImage($title->getText());
		$revision = StructuredData::getRevision($title, false, true);
		$image->propagateUndelete($revision);
	}
	return true;
}

/**
 * Callback function to propagate rollback
 * @param Article article
 * @return bool must return true or other hooks don't get called
 */
function propagateImageRollback(&$article, &$user) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_IMAGE) {
		$image = new SDImage($article->getTitle()->getText());
		$image->propagateRollback($article);
	}
	return true;
}

/**
 * Handles image metadata
 */
class SDImage extends StructuredData {
   const PROPAGATE_MESSAGE = 'Propagate changes to';
   protected static $PERSON_ATTRS = array('given', 'surname', 'title_prefix', 'title_suffix', 'birthdate', 'birthplace', 'chrdate', 'chrplace',
                                          'deathdate', 'deathplace', 'burialdate', 'burialplace');
   protected static $FAMILY_ATTRS = array();
	private $prevPeople, $prevFamilies;
	
	private static function isValidLicense($license) {
	   return $license != '';
	}

	/**
     * Construct a new SDImage object
     */
	public function __construct($titleString) {
		parent::__construct('image_data', $titleString, NS_IMAGE);
		$this->prevPeople = null;
		$this->prevFamilies = null;
	}

	protected function formatPerson($value, $dummy) {
		$title = (string)$value['title'];
		$fullname = StructuredData::getFullname($value);
		if ($fullname) {
			$fullname = '|'.$fullname;
		}
		$bdate = (string)$value['birthdate'];
		$bplace = (string)$value['birthplace'];
		$cdate = (string)$value['chrdate'];
		$cplace = (string)$value['chrplace'];
		$ddate = (string)$value['deathdate'];
		$dplace = (string)$value['deathplace'];
		$udate = (string)$value['burialdate'];
		$uplace = (string)$value['burialplace'];
		$birth = '';
		if ($bdate || $bplace) {
			if ($bplace) {
				$bplace = ', [[Place:' . StructuredData::addBarToTitle($bplace) . ']]';
			}
			$birth = "<dd>Birth: $bdate$bplace";
		}
		else if ($cdate || $cplace) {
			if ($cplace) {
				$cplace = ', [[Place:' . StructuredData::addBarToTitle($cplace) . ']]';
			}
			$birth = "<dd>Chr: $cdate$cplace";
		}
		$death = '';
		if ($ddate || $dplace) {
			if ($dplace) {
				$dplace = ', [[Place:' . StructuredData::addBarToTitle($dplace) . ']]';
			}
			$death = "<dd>Death: $ddate$dplace";
		}
		else if ($udate || $uplace) {
			if ($uplace) {
				$uplace = ', [[Place:' . StructuredData::addBarToTitle($uplace) . ']]';
			}
			$death = "<dd>Burial: $udate$uplace";
		}
		return "[[Person:$title$fullname]]<dl>$birth$death</dl>";
	}

	protected function formatFamily($value, $dummy) {
		$title = (string)$value['title'];
		return "[[Family:" . StructuredData::addBarToTitle($title) . ']]';
	}

   protected function getFamilyMember($member) {
      $title = (string)$member['title'];
      $fullname = StructuredData::getFullname($member);
      if (!$fullname) $fullname = trim(preg_replace('/\(\d+\)\s*$/', '', $title));
      $link = "[[Person:$title|$fullname]]";
      $beginYear = StructuredData::getYear((string)$member['birthdate'] ? (string)$member['birthdate'] : (string)$member['chrdate'], true);
      $endYear = StructuredData::getYear((string)$member['deathdate'] ? (string)$member['deathdate'] : (string)$member['burialdate'], true);
      $yearrange = '';
      if ($beginYear || $endYear) {
         $yearrange = "<span class=\"wr-infobox-yearrange\">$beginYear - $endYear</span>";
      }
      return '<span class="wr-infobox-fullname">'.$link.'</span>'.$yearrange;
   }

   protected function formatAsLink($value, $nameSpace) {
      $valueString = (string)$value;
      if ($valueString) {
     		$fields = explode('|', $valueString);
     		if (count($fields) == 1) {
     			$fields[1] = $fields[0];
     		}
         return "[[$nameSpace:{$fields[0]}|{$fields[1]}]]";
      }
      return '';
   }

   protected function getLV($label, $value, $linkNamespace=null) {
      $values = '';
      foreach ($value as $v) {
         if ($linkNamespace == 'Person') {
            $line = $this->getFamilyMember($v);
         }
         else if ($linkNamespace == 'Family') {
            $line = $this->formatAsLink((string)$v['title'], $linkNamespace);
         }
         else if ($linkNamespace) {
            $line = $this->formatAsLink($v, $linkNamespace);
         }
         else {
            $line = (string)$v;
         }
         $values .= "<dd>{$line}";
      }
      if (!$values) return '';
      return '<dt class="wr-infobox-label">'.$label.$values;
   }

   /**
	 * Create wiki text from xml property
	 */
	protected function toWikiText($parser) {
		$result= '';
		if (isset($this->xml)) {
//			$result = "<div class=\"infobox-header\">Image Information</div>\n{|\n";
//			$date = (string)$this->xml->date;
//			$hideTopBorder = false;
//			if ($date) {
//				$result .= $this->addValueToTableDL('Date', $date);
//				$hideTopBorder = true;
//			}
//		   $place = (string)$this->xml->place;
//		   if ($place) {
//   			$result .= $this->addValueToTableDL('Place', "[[Place:$place]]", $hideTopBorder);
//				$hideTopBorder = true;
//		   }
//         $result .= $this->addValuesToTableDL('People', $this->xml->person, 'formatPerson', null, $hideTopBorder);
//			$hideTopBorder = $hideTopBorder || isset($this->xml->person[0]);
//         $result .= $this->addValuesToTableDL('Families', $this->xml->family, 'formatFamily', null, $hideTopBorder);
//			$hideTopBorder = $hideTopBorder || isset($this->xml->family[0]);
//			if ((string)$this->xml->copyright_holder) {
//			   $result .= $this->addValueToTableDL('Copyright holder', (string)$this->xml->copyright_holder, $hideTopBorder);
//			}
//			$license = (string)$this->xml->license;
//			if ($license) {
//			   $license = "{{".$license."}}";
//			}
//			else {
//			   $license = "<font color=red>None selected</font>";
//			}
//			$result .= $this->addValueToTableDL('License', $license, true);
//			$result .= $this->showWatchers();
//   		$result .= "|}\n";

			// clear left
//			$result .= "<div class=\"visualClearLeft\"></div>";

         $date = $this->getLV("Date", $this->xml->date);
         $place = $this->getLV("Place", $this->xml->place, 'Place');
         $people = $this->getLV("People", $this->xml->person, 'Person');
         $families = $this->getLV("Families", $this->xml->family, 'Family');
         $copyrightHolder = $this->getLV("Copyright holder", $this->xml->copyright_holder);
         $result = "<div class=\"wr-infobox wr-infobox-imagepage\"><div class=\"wr-infobox-heading\">Image Information</div><dl>{$date}{$place}{$people}{$families}{$copyrightHolder}</dl></div>";

			// add fotonotes
			$fn = new Fotonotes($this->title);
			$result .= $fn->renderImageNotes($this->xml->note);

			// add categories
			$places = array();
		   $places[] = (string)$this->xml->place;
			$surnames = array();
			foreach ($this->xml->person as $person) {
			   $surnames[] = (string)$person['surname'];
			}
			foreach ($this->xml->family as $family) {
				$matches = array();
				if (preg_match('/.*?([^ ]+) +and .*?([^ ]+) +\(\\d+\)$/', (string)$family['title'], $matches)) {
					if ($matches[1] != 'Unknown') {
						$surnames[] = $matches[1];
					}
					if ($matches[2] != 'Unknown') {
						$surnames[] = $matches[2];
					}
				}
			}
			$result .= StructuredData::addCategories($surnames, $places);
			
			// Add license category(ies) as whatever is in between comments
			// This is pretty ugly because we add the entire template again in ImagePage, but there it's added to late 
			// at that stage for the parser to add this page to categories
			$license = (string)$this->xml->license;
			if ($license) {
				$licenseTitle = Title::makeTitleSafe(NS_TEMPLATE, $license);
				if ($licenseTitle) {
					$licenseRevision = Revision::newFromTitle($licenseTitle);
					if ($licenseRevision) {
						$licenseText =& $licenseRevision->getText();
						$matches = array();
						if (preg_match('#<!--(.*?)-->#', $licenseText, $matches)) {
							$result .= $matches[1];
						}
					}
				}
			}
		}
		else {
			// clear left
//			$result .= "<div class=\"visualClearLeft\"></div>";
		}
		return $result;
	}
	
	private function addTitle(&$titles, $tag, $pageTitleString, &$text) {
		$xml = $this->getXml($tag, $text);
		if (isset($xml)) {
			foreach ($xml->image as $img) {
				if ((string)$img['filename'] == $this->title->getText() && !in_array($pageTitleString, $titles)) {
					$titles[] = $pageTitleString;
				}
			}
		}
	}

	// append WLH titles to people and families arrays
	private function addWhatLinksHere(&$people, &$families) {
		$pageids = $this->getWhatLinksHere();
		$db =& wfGetDB(DB_MASTER);
		foreach ($pageids as $pageid) {
			$revision = Revision::loadFromPageId($db, $pageid);
			if ($revision) {
				$text = $revision->getText();
				if ($revision->getTitle()->getNamespace() == NS_PERSON) {
					$this->addTitle($people, 'person', $revision->getTitle()->getText(), $text);
				}
				else if ($revision->getTitle()->getNamespace() == NS_FAMILY) {
					$this->addTitle($families, 'family', $revision->getTitle()->getText(), $text);
				}
			}
		}
	}
	
	private function addPageInput($titles, $name) {
		if (count($titles) == 0) { // ensure go through loop at least once
			$titles[] = '';
		}
		$result = "<table id='image_{$name}_table' cellspacing=0 cellpadding=0>";
		$i = 0;
		foreach($titles as $title) {
			$result .= "<tr><td>" .
				"<input type=\"hidden\" name=\"{$name}_id$i\" value=\"".($i+1)."\"/>".
				"<input class=\"{$name}_input\" type=\"text\" size=40 name=\"$name$i\" value=\"" . htmlspecialchars( $title ) . "\"/>".
				"</td></tr>\n";
			$i++;
		}
		$result .= '</table>';
		return $result;
	}

   /**
    * Create edit fields from xml property
    */
   protected function toEditFields(&$textbox1) {
		global $wgStylePath, $wgScriptPath, $wgOut, $wgUser, $wgRequest;

   	if (!$this->title->exists()) {
   		return '<p><font color=red>Image does not exist.  You must</font> <a href="/wiki/Special:Upload"><b>upload it.</b></a></p>';
   	}

      // add javascript functions
		$wgOut->addScript("<script type='text/javascript' src=\"$wgScriptPath/autocomplete.9.js\"></script>");
		if ($wgRequest->getVal('action') != 'submit') {
	      $wgOut->addScript("<script type='text/javascript' src=\"$wgScriptPath/fnclientwiki.yui.1.js\"></script>");
		}
		$wgOut->addScript("<script type='text/javascript' src=\"$wgScriptPath/image.1.js\"></script>");
		$wgOut->addScript("<script type='text/javascript' src=\"$wgStylePath/common/upload.2.js\"></script>");

	   $date = '';
      $place = '';
      $copyright = '';
      $license = '';
      $notes = '';
		$invalidStyle = ' style="background-color:#fdd;"';
		$licenseStyle = '';
		$people = array();
		$families = array();
      if (!isset($this->xml)) {
		   $this->addWhatLinksHere($people, $families);
      }
      else {
			$date = htmlspecialchars((string)$this->xml->date);
			$place = htmlspecialchars((string)$this->xml->place);
		   $people = StructuredData::getTitlesAsArray($this->xml->person);
		   $families = StructuredData::getTitlesAsArray($this->xml->family);
			$copyright = htmlspecialchars((string)$this->xml->copyright_holder);
			$license = (string)$this->xml->license;
         if (isset($this->xml->note)) {
            foreach ($this->xml->note as $note) {
               $title = htmlspecialchars((string)$note['title']);
               $content = htmlspecialchars((string)$note);
               $notes .= '<note left="'.(string)$note['left'].'" top="'.(string)$note['top'].'" right="'.(string)$note['right'].'" bottom="'.(string)$note['bottom'].'" title="'.$title."\">$content</note>\n";
            }
         }
      }

      // get license options
		$licenses = new Licenses();
		$licenseLabel = wfMsgHtml( 'license' );
		$sk = $wgUser->getSkin();
		$licenseHelpUrl = $sk->makeInternalOrExternalUrl( wfMsgForContent( 'licensehelppage' ));
		$licenseHelp = '<a target="helpwindow" href="'.$licenseHelpUrl.'">'.htmlspecialchars( wfMsg( 'licensehelp' ) ).'</a>';
		$nolicense = wfMsgHtml( 'nolicense' );
		$licenseshtml = $licenses->getHTML();
		if ($license) {
		   $licenseshtml = preg_replace('$value="'.StructuredData::protectRegexSearch($license).'"$',
  		                               'value="'.StructuredData::protectRegexReplace($license).'" selected="selected"', $licenseshtml);
		}

		$result = '';

      // add fotonotes
	   $fn = new Fotonotes($this->title);
	   $result .= $fn->renderEditableImage(isset($this->xml) ? $this->xml->note : null) . '<br>';

      // error messages
      if (!SDImage::isValidLicense($license)) {
          $licenseStyle = $invalidStyle;
            $result .= "<p><font color=red>You must select a license</font></p>";
      }

	   // add fields
		$personTbl = $this->addPageInput($people, 'person');
		$familyTbl = $this->addPageInput($families, 'family');
		$notes = htmlspecialchars($notes);
	   $result .= <<< END
<input type="hidden" id="notesField" name="notes" value="$notes"/>
<table border=0>
<tr><td>&nbsp;</td><td><b>License and copyright</b></td></tr>
<tr><td align='right'><label for='wpLicense'>$licenseLabel (&nbsp;$licenseHelp&nbsp;):</label></td>
	<td align='left'><select name='license' id='wpLicense' tabindex='1' onchange='licenseSelectorCheck()'>
		<option value=''>$nolicense</option>$licenseshtml</select></td></tr>
<tr><td align='right'><label for="copyright_holder">Name of copyright holder:</label></td><td align='left'><input tabindex="1" name="copyright_holder" value="$copyright" size="30"/></td></tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td><td><b>Time place and people</td></tr>
<tr><td align='right'><label for="date">Image date:</label></td><td align='left'><input tabindex="1" name="date" value="$date" size="15"/></td></tr>
<tr><td align='right'><label for="place">Place:</label></td><td align='left'><input class="place_input" tabindex="1" name="place" value="$place" size="30"/></td></tr>
<tr><td align='right' valign='top'>Person page:</td><td align='left'>$personTbl</td></tr>
<tr><td>&nbsp;</td><td align='left'><a id='person_link' href='javascript:void(0)' onClick='addImagePage("person"); return preventDefaultAction(event);'>Add another person</a></td></tr>
<tr><td align='right' valign='top'>Family page:</td><td align='left'>$familyTbl</td></tr>
<tr><td>&nbsp;</td><td align='left'><a id='family_link' href='javascript:void(0)' onClick='addImagePage("family"); return preventDefaultAction(event);'>Add another family</a></td></tr>
</table><h2>Text</h2>
END;
		return $result;
   }

	// create the xml element for a family member
	private function formatPageElement($tag, $titleString, $ns) {
		$title = Title::newFromText($titleString,$ns);
		if (!$title) {
			return '';
		}
		$title = StructuredData::getRedirectToTitle($title); // ok to read from slave here; mistakes will get corrected in propagate
		if ($ns == NS_PERSON) {
			if (isset($this->prevPeople[$title->getText()])) {
				$page = $this->prevPeople[$title->getText()];
			}
			else {
				$page = Family::loadFamilyMember($title->getText());
			}
			$attrs = self::$PERSON_ATTRS;
		}
		else { // NS_FAMILY
			if (isset($this->prevFamilies[$title->getText()])) {
				$page = $this->prevFamilies[$title->getText()];
			}
			else {
				$page = array();
			}
			$attrs = self::$FAMILY_ATTRS;
		}
		$result = "<$tag title=\"".StructuredData::escapeXml($title->getText()).'"';
		foreach ($attrs as $attr) {
			if (isset($page[$attr])) {
				$attrValue = trim($page[$attr]);
				if (strlen($attrValue) > 0) {
					$result .= " $attr=\"".StructuredData::escapeXml($attrValue).'"';
				}
			}
		}
		$result .= "/>\n";
		return $result;
	}

	// load person/family data from previous version of the article
	private function loadPages() {
		$this->prevPeople = array();
		$this->prevFamilies = array();
      $revision = StructuredData::getRevision($this->title);
		if ($revision) {
         $content =& $revision->getText();
         $xml = StructuredData::getXml($this->tagName, $content);
         if (isset($xml)) {
            foreach ($xml->person as $person) {
            	$title = (string)$person['title'];
             	$this->prevPeople[$title] = Family::getFamilyMemberAttributes($person);
            }
            foreach ($xml->family as $family) {
            	$title = (string)$family['title'];
             	$this->prevFamilies[$title] = array();
            }
         }
		}
	}

	private function clearPages() {
		$this->prevPeople = null;
		$this->prevFamilies = null;
	}

	private function fromPage($request, $name, $ns) {
	   $result = '';
	   $titles = array();
		for ($i = 0; $request->getVal("{$name}_id$i"); $i++) {
		   $titleString = $request->getVal("$name$i");
		   if ($titleString) {
		      if (!StructuredData::titleStringHasId($titleString)) {
		         $titleString = StructuredData::standardizeNameCase($titleString, true);
		      }
		      if (!in_array($titleString, $titles)) {
		      	$result .= $this->formatPageElement($name, $titleString, $ns);
		      	$titles[] = $titleString;
		      }
		   }
		}
	   return $result;
	}
	
	private function correctPlaceTitle($place) {
		if ($place && mb_strpos($place, '|') === false) {
			$titles = array();
			$titles[] = $place;
		   $correctedPlaces = PlaceSearcher::correctPlaceTitles($titles);
			$correctedPlace = @$correctedPlaces[$place];
			if ($correctedPlace) {
				$place = strcasecmp($place,$correctedPlace) == 0 ? $correctedPlace : $correctedPlace . '|' . $place;
			}
		}
		return $place;
	}

    /**
     * Return xml elements from data in request
     * @param unknown $request
     */
   protected function fromEditFields($request) {
      $result = '';
		$this->loadPages();
      $result .= $this->addSingleLineFieldToXml($request->getVal('license', ''), 'license');
      $result .= $this->addSingleLineFieldToXml($request->getVal('copyright_holder', ''), 'copyright_holder');
      $result .= $this->addSingleLineFieldToXml($request->getVal('date', ''), 'date');
      $result .= $this->addSingleLineFieldToXml($this->correctPlaceTitle($request->getVal('place')), 'place');
      $result .= $this->fromPage($request, 'person', NS_PERSON);
      $result .= $this->fromPage($request, 'family', NS_FAMILY);
      $result .= preg_replace('/\r?\n/', "\n", $request->getVal('notes', ''))."\n";
		$this->clearPages();
      return $result;
   }

    /**
     * Return true if xml property is valid
     */
    protected function validateData() {
      return $this->title->exists() && SDImage::isValidLicense((string)$this->xml->license);
    }
    
	private static function getPropagatedData($xml) {
		if (isset($xml)) {
			$people = Family::getTitlesAsArray($xml->person);
			$families = Family::getTitlesAsArray($xml->family);
		}
		else {
			$people = array();
			$families = array();
		}
		return array($people, $families);
	}
	
	private function updatePage($linkTitle, $tag, $newTitle, &$text, &$textChanged) {
	   if (!PropagationManager::isPropagatablePage($linkTitle)) {
	      return true;
	   }

		$result = true;
		$article = StructuredData::getArticle($linkTitle, true);
		if ($article) {
			$content =& $article->fetchContent();
			$updated = false;
			ESINHandler::updateImageLink($tag, $this->titleString, $newTitle, '', $content, $updated);
			if ($updated) {
				$result = $article->doEdit($content, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
			}
			else {
			   error_log("propagating image " . $this->titleString . " nothing changed in ".$linkTitle->getPrefixedText());
			}
			// if we're not deleting this entry (newTitle is not empty), and the page to update is a redirect (article title != linkTitle),
			// we need to update the page title in the image page text
			if ($newTitle && $linkTitle->getText() != $article->getTitle()->getText()) {
				$old = 'title="' . StructuredData::escapeXml($linkTitle->getText()) . '"';
				$new = 'title="' . StructuredData::escapeXml($article->getTitle()->getText()) . '"';
				$text = str_replace($old, $new, $text);
				$textChanged = true;
			}
		}
		return $result;
	}

	private function propagatePageEditData($members, $origMembers, $tag, $ns, &$text, &$textChanged) {
		$result = true;
		$addMembers = array_diff($members, $origMembers);
		$delMembers = array_diff($origMembers, $members);

		// remove from deleted members
		foreach ($delMembers as $p) {
			$linkTitle = Title::newFromText($p, $ns);
			PropagationManager::addPropagatedAction($this->title, 'dellink', $linkTitle);
			if (PropagationManager::isPropagatableAction($linkTitle, 'delimage', $this->title)) {
				$result = $result && $this->updatePage($linkTitle, $tag, null, $text, $textChanged);
			}
		}

		// add to new members
		foreach ($addMembers as $p) {
			$linkTitle = Title::newFromText($p, $ns);
			PropagationManager::addPropagatedAction($this->title, 'addlink', $linkTitle);
			if (PropagationManager::isPropagatableAction($linkTitle, 'addimage', $this->title)) {
				$result = $result && $this->updatePage($linkTitle, $tag, $this->titleString, $text, $textChanged);
			}
		}
		return $result;
	}

	/**
     * Propagate data in xml property to other articles if necessary
     * @param string $oldText contains text being replaced
     * @param String $text which we never touch when propagating places
     * @param bool $textChanged which we never touch when propagating places
     * @return bool true if propagation was successful
     */
	protected function propagateEditData($oldText, &$text, &$textChanged) {
		global $wrIsGedcomUpload;

		$result = true;
		// get current info
		list ($people, $families) = SDImage::getPropagatedData($this->xml);

		// get original info
		list ($origPeople, $origFamilies) = array(array(), array());
		// don't bother construction page text from WLH in a gedcom upload because nothing will link to this new page
		if (!@$wrIsGedcomUpload && (!$oldText || mb_strpos($oldText, '<'.$this->tagName.'>') === false)) { // oldText contains MediaWiki:noarticletext if the article is being created
			// generate origPeople and origFamilies from What Links Here
			$this->addWhatLinksHere($origPeople, $origFamilies);
		}
		else {
			$origXml = StructuredData::getXml($this->tagName, $oldText);
			if (isset($origXml)) {
				list ($origPeople, $origFamilies) = SDImage::getPropagatedData($origXml);
			}
		}

		$result = $result && $this->propagatePageEditData($people, $origPeople, 'person', NS_PERSON, $text, $textChanged);
		$result = $result && $this->propagatePageEditData($families, $origFamilies, 'family', NS_FAMILY, $text, $textChanged);

		if (StructuredData::removeDuplicateLinks('person|family', $text)) {
			$textChanged = true;
		}
		
		if (!$result) {
			error_log("ERROR! Image edit/rollback not propagated: $this->titleString\n");
		}
		return $result;
	}

	private function getPageElements($titles, $tag, $ns) {
      $text = '';
      foreach ($titles as $title) {
         $text .= $this->formatPageElement($tag, $title, $ns);
      }
	   return $text;
	}

	/**
     * Propagate move, delete, or undelete to other articles if necessary
     *
     * @param String $newTitleString null in case of delete; same as this title string in case of undelete
     * @param String $text text of article
     * @param bool $textChanged set to true if we change the text
     * @return bool true if success
     */
	protected function propagateMoveDeleteUndelete($newTitleString, $newNs, &$text, &$textChanged) {
	   $result = true;
		$newTitle = ($newTitleString ? Title::newFromText($newTitleString, NS_IMAGE) : null);

		// if we're undeleting, add additional people from WLH, getting updated person data
      if ($this->titleString == $newTitleString) {
      	list ($people, $families) = SDImage::getPropagatedData($this->xml);
			$this->addWhatLinksHere($people, $families);
			$pageText = $this->getPageElements($people, 'person', NS_PERSON) .
							$this->getPageElements($families, 'family', NS_FAMILY);
			// update text: replace old family information with new
         $text = preg_replace("$<(person|family) [^>]*>\n$", '', $text);
         $text = preg_replace('$</'.$this->tagName.'>$', StructuredData::protectRegexReplace($pageText . '</'.$this->tagName.'>'), $text, 1);
         $this->xml = StructuredData::getXml($this->tagName, $text);
         $textChanged = true;
      }

      // get data to propagate
		list ($people, $families) = SDImage::getPropagatedData($this->xml);

		foreach ($people as $p) {
		   $linkTitle = Title::newFromText((string)$p, NS_PERSON);
			PropagationManager::addPropagatedAction($this->title, 'dellink', $linkTitle);
			if ($newTitle) PropagationManager::addPropagatedAction($newTitle, 'addlink', $linkTitle);
			// don't need to check propagated action before calling updatePage, because propagateMoveDeleteUndelete is never part of a loop
  			$result = $result && $this->updatePage($linkTitle, 'person', $newTitleString, $text, $textChanged);
		}

		foreach ($families as $p) {
		   $linkTitle = Title::newFromText((string)$p, NS_FAMILY);
			PropagationManager::addPropagatedAction($this->title, 'dellink', $linkTitle);
			if ($newTitle) PropagationManager::addPropagatedAction($newTitle, 'addlink', $linkTitle);
			// don't need to check propagated action before calling updatePage, because propagateMoveDeleteUndelete is never part of a loop
  			$result = $result && $this->updatePage($linkTitle, 'family', $newTitleString, $text, $textChanged);
		}

		if (!$result) {
			error_log("ERROR! Family move/delete/undelete not propagated: $this->titleString -> " .
			($newTitleString ? $newTitleString : "[delete]") . "\n");
		}
		return $result;
	}
}
?>
