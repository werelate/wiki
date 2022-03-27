<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");
require_once("$IP/extensions/structuredNamespaces/ESINHandler.php");
require_once("$IP/extensions/structuredNamespaces/TipManager.php");
require_once("$IP/extensions/other/PlaceSearcher.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfFamilyExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfFamilyExtensionSetup() {
	global $wgHooks;
	global $wgParser;

	# Register hooks for edit UI, request handling, and save features
	$wgHooks['ArticleEditShow'][] = 'renderFamilyEditFields';
	$wgHooks['ImportEditFormDataComplete'][] = 'importFamilyEditData';
	$wgHooks['EditFilter'][] = 'validateFamily';
	$wgHooks['ArticleSave'][] = 'propagateFamilyEdit';
	$wgHooks['TitleMoveComplete'][] = 'propagateFamilyMove';
	$wgHooks['ArticleDeleteComplete'][] = 'propagateFamilyDelete';
	$wgHooks['ArticleUndeleteComplete'][] = 'propagateFamilyUndelete';
	$wgHooks['ArticleRollbackComplete'][] = 'propagateFamilyRollback';

	# register the extension with the WikiText parser
	$wgParser->setHook('family', 'renderFamilyData');
}

/**
 * Callback function for converting resource to HTML output
 */
function renderFamilyData( $input, $argv, $parser) {
   $title = $parser->getTitle()->getText();
   $family = new Family($title);
   if ($title == 'GedcomPage') {
      $family->setGedcomPage(true);
   }
	return $family->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderFamilyEditFields( &$editPage ) {
	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_FAMILY) {
		$family = new Family($editPage->mTitle->getText());
		$family->renderEditFields($editPage, true);
	}
	return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importFamilyEditData( &$editPage, &$request ) {
	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_FAMILY) {
		$family = new Family($editPage->mTitle->getText());
		$family->importEditData($editPage, $request);
	}
	return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateFamily($editPage, $textBox1, $section, &$hookError) {
	$ns = $editPage->mTitle->getNamespace();
	if ($ns == NS_FAMILY) {
		$family = new Family($editPage->mTitle->getText());
		$family->validate($textBox1, $section, $hookError, true);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateFamilyEdit(&$article, &$user, &$text, &$summary, $minor, $dummy1, $dummy2, &$flags) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_FAMILY) {
		$family = new Family($article->getTitle()->getText());
		$family->propagateEdit($text, $article);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateFamilyMove(&$title, &$newTitle, &$user, $pageId, $redirPageId) {
	$ns = $title->getNamespace();
	if ($ns == NS_FAMILY) {
		$family = new Family($title->getText());
		$family->propagateMove($newTitle);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateFamilyDelete(&$article, &$user, $reason) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_FAMILY) {
		$family = new Family($article->getTitle()->getText());
		$family->propagateDelete($article);
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateFamilyUndelete(&$title, &$user) {
	$ns = $title->getNamespace();
	if ($ns == NS_FAMILY) {
		$family = new Family($title->getText());
		$revision = StructuredData::getRevision($title, false, true);
		$family->propagateUndelete($revision);
	}
	return true;
}

/**
 * Callback function to propagate rollback
 * @param Article article
 * @return bool must return true or other hooks don't get called
 */
function propagateFamilyRollback(&$article, &$user) {
	$ns = $article->getTitle()->getNamespace();
	if ($ns == NS_FAMILY) {
		$family = new Family($article->getTitle()->getText());
		$family->propagateRollback($article);
	}
	return true;
}

/**
 * Handles families
 */
class Family extends StructuredData {
	const PROPAGATE_MESSAGE = 'Propagate changes to';
	// if you add more standard events, you must change the javascript function addEventFact
	public static $MARRIAGE_TAG = 'Marriage';
	public static $ALT_MARRIAGE_TAG = 'Alt Marriage';
	public static $STD_EVENT_TYPES = array('Marriage');
	// TODO Remove Marriage Notice?
	protected static $OTHER_EVENT_TYPES = array('Alt Marriage', 'Annulment', 'Census', 'Divorce', 'Divorce Filing', 'Engagement', 'Marriage Banns', 'Marriage Bond', 'Marriage Contract', 'Marriage License',
									'Marriage Notice', 'Marriage Settlement', 'Residence', 'Separation', 'Other');
   public static $EVENT_CONJUNCTIONS = array('Marriage' => 'to', 'Alt Marriage' =>'to', 'Annulment'=>'from', 'Census'=>'with',
                           'Divorce'=>'from', 'Divorce Filing'=>'from', 'Engagement'=>'to',
                           'Marriage Banns'=>'to', 'Marriage Bond'=>'to', 'Marriage Contract'=>'to', 'Marriage License'=>'to',
									'Marriage Notice'=>'to', 'Marriage Settlement'=>'to', 'Residence'=>'with', 'Separation'=>'from', 'Other'=>'with');
   private $prevFamilyMembers; // temporarily holds family member info during fromEditFields()
   protected static $FAMILY_MEMBER_ATTRS = array('given', 'surname', 'title_prefix', 'title_suffix', 'birthdate', 'birthplace', 'chrdate', 'chrplace',
                                                 'deathdate', 'deathplace', 'burialdate', 'burialplace', 'child_of_family');

   private $isMerging;
   
	/**
     * Construct a new family object
     */
	public function __construct($titleString) {
		parent::__construct('family', $titleString, NS_FAMILY, ESINHandler::ESIN_FOOTER_TAG);
		$this->isMerging = false;
	}
	
	public function isMerging($isMerging) {
		$this->isMerging = $isMerging;
	}

	protected function formatFamilyMember($value, $parms) {
		$title = (string)$value['title'];
		if (isset($parms[0])) {
		  $label = (isset($parms[1]) && $title != (string)$parms[1]['title'] ? 'Alternate ' : '') . $parms[0] . '<dd>';
		}
		else {
		   $label = '';
		}
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
		$pf = (string)$value['child_of_family'];
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
		if ($pf) {
			$pf = '<dd>Parents: [[Family:' . StructuredData::addBarToTitle($pf) . ']]';
		}
		return "<dt>{$label}[[Person:$title$fullname]]$birth$death$pf";
	}

   protected function getSpouseInfo($spouse, $width, $firstChildClass='', $given='', $surname='', $isHusband=false) {
      $parents = '&nbsp;';
      if (isset($spouse)) {
         if ((string)$spouse['child_of_family']) {
            $parentTitle = (string)$spouse['child_of_family'];
            $parentNames = preg_replace('/(.*)\(\d+\)$/', "$1", $parentTitle);
            $parents = "Parents: [[Family:$parentTitle|$parentNames]]";
         }
         list ($title, $fullname, $birthLabel, $birthDate, $birthPlace, $deathLabel, $deathDate, $deathPlace) = ESINHandler::getPersonSummary($spouse);
         $link = "[[Person:$title|$fullname]]";
      }
      else {
         $t = Title::makeTitle(NS_SPECIAL, 'AddPage');
         $stdGiven = (strtolower($given) == 'unknown' ? '' : $given);
         $stdSurname = (strtolower($surname) == 'unknown' ? '' : $surname);
         $url = $t->getFullURL('namespace=Person&sf='.urlencode($this->title->getText()).'&gnd='.($isHusband ? 'M' : 'F').'&g='.urlencode($stdGiven).'&s='.urlencode($stdSurname));
         $link = ($this->isGedcomPage() || !($stdGiven || $stdSurname) ? "$given $surname" : "$given $surname <span class=\"plainlinks addspouselink\">([$url add])</span>");
         $birthLabel = $birthDate = $birthPlace = '';
         $deathLabel = $deathDate = $deathPlace = '';
      }
      return <<<END
<td style="width: {$width}%" class="$firstChildClass">
   <div class="wr-infobox-parents">$parents</div>
   <div class="wr-infobox-fullname">$link</div>
   <div class="wr-infobox-event">$birthLabel<span class="wr-infobox-date">$birthDate</span> <span class="wr-infobox-place">$birthPlace</span></div>
   <div class="wr-infobox-event">$deathLabel<span class="wr-infobox-date">$deathDate</span> <span class="wr-infobox-place">$deathPlace</span></div>
</td>
END;
   }

   protected function getImageInfo($image, $firstChildClass='') {
      $imageText = '';
      $thumbWidth = SearchForm::THUMB_WIDTH;
      $filename = (string)$image['filename'];
      $t = Title::makeTitle(NS_IMAGE, $filename);
      if ($t && $t->exists()) {
         $img = new Image($t);
         $caption = (string)$image['caption'];
         if (!$caption) $caption = $filename;
         $maxWidth = 700;
         $maxHeight = 300;
         $width = $img->getWidth();
         $height = $img->getHeight();
         if ( $maxWidth > $width * $maxHeight / $height ) {
            $maxWidth = wfFitBoxWidth( $width, $height, $maxHeight );
         }
         $imageURL = $img->createThumb($maxWidth, $maxHeight);
         $caption = str_replace('|',' ',$caption);
         $titleAttr = StructuredData::escapeXml("$imageURL|$maxWidth|$caption");
         $imageText = <<<END
<td style="width: 1%" class="$firstChildClass">
   <div class="wr-infobox-image wr-imagehover" title="$titleAttr">[[Image:{$filename}|{$thumbWidth}x{$thumbWidth}px]]</div>
</td>
END;
      }
      return $imageText;
   }

	protected function getHeaderHTML() {
      global $wgESINHandler;

		if (isset($this->xml)) {
			$husbands = array();
			foreach ($this->xml->husband as $p) {
				$husbands[] = (string)$p['title'];
			}
			$husbands = str_replace("'", "\'", join('|', $husbands));
			$wives = array();
			foreach ($this->xml->wife as $p) {
				$wives[] = (string)$p['title'];
			}
			$wives = str_replace("'", "\'", join('|', $wives));
         $esinHeader = $wgESINHandler->getHeaderHTML();
         return "<script type=\"text/javascript\">/*<![CDATA[*/var familyHusbands='$husbands'; var familyWives='$wives';/*]]>*/</script>$esinHeader";
		}
		return '';
	}

   protected function getPlace($eventFact) {
      $place = (string)$eventFact['place'];
      return ($place ? '[[Place:' . StructuredData::addBarToTitle($place) . ']]' : '');
   }

   public static function getSummary($xml, $title) {
      // husband surname | husband given | wife surname | wife given | marriage date | marriage place

      list ($husbandGiven, $husbandSurname, $wifeGiven, $wifeSurname) =
              StructuredData::parseFamilyTitle($title->getText());
      $marriageDate = $marriagePlace = '';
      if (isset($xml)) {
         if (isset($xml->husband)) {
            foreach ($xml->husband as $spouse) {
               $husbandGiven = (string)@$spouse['given'];
               $husbandSurname = (string)@$spouse['surname'];
            }
         }
         if (isset($xml->wife)) {
            foreach ($xml->wife as $spouse) {
               $wifeGiven = (string)@$spouse['given'];
               $wifeSurname = (string)@$spouse['surname'];
            }
         }
         if (isset($xml->event_fact)) {
            foreach ($xml->event_fact as $eventFact) {
               if ($eventFact['type'] == 'Marriage') {
                  $marriageDate = DateHandler::formatDate((string)@$eventFact['date'],true);    // formatDate call added Mar 2021 by Janet Bjorndahl
                  $marriagePlace = (string)@$eventFact['place'];
               }
            }
         }
      }
      return StructuredData::removeBars($husbandSurname).
             '|'.StructuredData::removeBars($husbandGiven).
             '|'.StructuredData::removeBars($wifeSurname).
             '|'.StructuredData::removeBars($wifeGiven).
             '|'.StructuredData::removeBars($marriageDate).
             '|'.StructuredData::removePreBar($marriagePlace);
   }

	/**
	 * Create wiki text from xml property
	 */
	protected function toWikiText($parser) {
		global $wgESINHandler, $wgOut;

		$result= '';
		if (isset($this->xml)) {
//			$result = '{| id="structuredData" border=1 class="floatright" cellpadding=4 cellspacing=0 width=30%'."\n";
//			$result = "<div class=\"infobox-header\">Family Information</div>\n{|\n";
//         $result = "{|\n";
//			$hideTopBorder = false;
//			$result .= $this->addValuesToTableDL(null, $this->xml->husband, 'formatFamilyMember', array('Husband', @$this->xml->husband[0]), $hideTopBorder);
//			$hideTopBorder = $hideTopBorder || isset($this->xml->husband);
//			$result .= $this->addValuesToTableDL(null, $this->xml->wife, 'formatFamilyMember', array('Wife', @$this->xml->wife[0]), $hideTopBorder);
//			$hideTopBorder = $hideTopBorder || isset($this->xml->wife);
//			$result .= $wgESINHandler->showEventsFacts($this->xml, $hideTopBorder);
//			if (isset($this->xml->child)) {
//				$result .= "|-\n! Children\n";
//				$result .= $this->addValuesToTableDL(null, $this->xml->child, 'formatFamilyMember', array(null, null), true);
//			}
//			$result .= $this->showWatchers();
//			$result .= "|}\n";

         // add infobox
         $image = $wgESINHandler->getPrimaryImage($this->xml);
         $numHusbands = $numWives = 0;
         foreach ($this->xml->husband as $spouse) $numHusbands++;
         foreach ($this->xml->wife as $spouse) $numWives++;
         if ($numHusbands < 1) $numHusbands = 1;
         if ($numWives < 1) $numWives = 1;
         $width = floor(100 / ($numHusbands + $numWives));
         $spouses = '';
         $firstChildClass='first-child';
         $husbandName = $wifeName = '';
         if (isset($this->xml->husband)) {
            foreach ($this->xml->husband as $spouse) {
               $spouses .= $this->getSpouseInfo($spouse, $width, $firstChildClass);
               $firstChildClass='';
               if (!$husbandName) {
                  $husbandName = StructuredData::constructName(@$spouse['given'], @$spouse['surname']);
               }
            }
         }
         else {
            list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($this->titleString);
            $spouses .= $this->getSpouseInfo(null, $width, $firstChildClass, $hg, $hs, true);
            $firstChildClass='';
            $husbandName = StructuredData::constructName($hg, $hs);
         }
         if ($image) {
            $spouses .= $this->getImageInfo($image, $firstChildClass);
            $firstChildClass='';
         }
         if (isset($this->xml->wife)) {
            foreach ($this->xml->wife as $spouse) {
               $spouses .= $this->getSpouseInfo($spouse, $width, $firstChildClass);
               $firstChildClass='';
               if (!$wifeName) {
                  $wifeName = StructuredData::constructName(@$spouse['given'], @$spouse['surname']);
               }
            }
         }
         else {
            list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($this->titleString);
            $spouses .= $this->getSpouseInfo(null, $width, $firstChildClass, $wg, $ws, false);
            $firstChildClass='';
            $wifeName = StructuredData::constructName($wg, $ws);
         }

         // check rename needed
         if (!$this->isGedcomPage && mb_strpos($this->titleString, 'Unknown') !== false) {
            list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($this->titleString);
            $husbandTitle = StructuredData::constructName($hg, $hs);
            $wifeTitle = StructuredData::constructName($wg, $ws);
            if (Person::isRenameNeeded($husbandTitle, $husbandName) ||
                Person::isRenameNeeded($wifeTitle, $wifeName)) {
               $correctTitle = StructuredData::constructFamilyName($husbandName, $wifeName);
               $t = Title::makeTitle(NS_SPECIAL, 'Movepage');
               $url = $t->getLocalURL('target='.$this->title->getPrefixedURL().
                                      '&wpNewTitle='.wfUrlencode("Family:$correctTitle").
                                      '&wpReason='.wfUrlencode('make page title agree with name'));
               $parser->mOutput->mSubtitle = 'This page can be <a href="'.$url.'">renamed</a>';
               $wgOut->setSubtitle($parser->mOutput->mSubtitle);
            }
         }

         $marriageDate = $marriagePlace = '';
         $marriageFound = false;
         if (isset($this->xml->event_fact)) {
            foreach ($this->xml->event_fact as $eventFact) {
               if ($eventFact['type'] == 'Marriage') {
                  $marriageFound = true;
                  $marriageDate = DateHandler::formatDate((string)$eventFact['date'],true);    // formatDate call added Mar 2021 by Janet Bjorndahl
                  $marriagePlace = $this->getPlace($eventFact);
               }
            }
         }
         $marriage = '';
         if ($marriageFound) {
            $marriage = "<div class=\"wr-infobox-event\">m. <span class=\"wr-infobox-date\">$marriageDate</span> <span class=\"wr-infobox-place\">$marriagePlace</span></div>";
         }

         $result = <<<END
<div class="wr-infobox wr-infobox-family">
   <table class="wr-infobox-spouses">
      <tr>
         $spouses
      </tr>
   </table>
   $marriage
</div>
<wr_ad></wr_ad>
<div id="wr_familytreelink"><span class="wr-familytreelink-text">Family tree</span><span class="wr-familytreelink-arrow">▼</span></div>
END;
			// add source citations, images, notes
			$result .= $wgESINHandler->addSourcesImagesNotes($this, $parser);

			// add categories
			$surnames = array();
			foreach ($this->xml->husband as $husband) {
			   $surnames[] = (string)$husband['surname'];
			}
			foreach ($this->xml->wife as $wife) {
			   $surnames[] = (string)$wife['surname'];
			}
			$places = ESINHandler::getPlaces($this->xml);

			$result .= StructuredData::addCategories($surnames, $places, false);
		}
		return $result;
	}

	public static function getPageText($marriagedate, $marriageplace, $titleString,
                                      $pageids = NULL, $husbandTitle='', $wifeTitle='', $childTitle='') {
      // standardize marriage place
      $placeTitles = array();
      if ($marriageplace && mb_strpos($marriageplace, '|') === false) $placeTitles[] = $marriageplace;
      if ($placeTitles) {
         $correctedTitles = PlaceSearcher::correctPlaceTitles($placeTitles);
         $correctedPlace = @$correctedTitles[$marriageplace];
         if ($correctedPlace) $marriageplace = strcasecmp($marriageplace,$correctedPlace) == 0 ? $correctedPlace : $correctedPlace . '|' . $marriageplace;
      }

		$result = "<family>\n";
		$db =& wfGetDB(DB_MASTER);  // get latest version
		$imageId = 0;
      $husbands = '';
      $wives = '';
      $children = '';
      $images = '';
		if ($pageids) {
			foreach ($pageids as $pageid) {
				$revision = Revision::loadFromPageId($db, $pageid);
				if ($revision) {
					if ($revision->getTitle()->getNamespace() == NS_PERSON) {
						$text = $revision->getText();
						$xml = StructuredData::getXml('person', $text);
						if (isset($xml)) {
							$personTitle = StructuredData::escapeXml($revision->getTitle()->getText());
							$spouseTag = Person::getSpouseTagFromGender((string)$xml->gender);
							foreach ($xml->spouse_of_family as $family) {
								if ((string)$family['title'] == $titleString) {
                           if ($spouseTag == 'husband') {
									   $husbands .= "<husband title=\"".$personTitle."\"/>\n";
                           }
                           else {
                              $wives .= "<wife title=\"".$personTitle."\"/>\n";
                           }
								}
							}
							foreach ($xml->child_of_family as $family) {
								if ((string)$family['title'] == $titleString) {
									$children .= '<child title="'.$personTitle."\"/>\n";
								}
							}
						}
					}
					else if ($revision->getTitle()->getNamespace() == NS_IMAGE) {
						$text = $revision->getText();
						$xml = StructuredData::getXml('image_data', $text);
						if (isset($xml)) {
							$imageTitle = StructuredData::escapeXml($revision->getTitle()->getText());
							foreach ($xml->family as $family) {
								if ((string)$family['title'] == $titleString) {
									$imageId++;
									$images .= '<image id="I'.$imageId.'" filename="'.$imageTitle."\"/>\n";
								}
							}
						}					
					}
				}
			}
		}
      if ($husbandTitle) {
         $husbands .= "<husband title=\"".StructuredData::escapeXml($husbandTitle)."\"/>\n";
      }
      if ($wifeTitle) {
         $wives .= "<husband title=\"".StructuredData::escapeXml($wifeTitle)."\"/>\n";
      }
      if ($childTitle) {
         $children .= "<husband title=\"".StructuredData::escapeXml($childTitle)."\"/>\n";
      }
      $result .= $husbands;
      $result .= $wives;
      $result .= $children;
      if ($marriagedate || $marriageplace) {
         $result .= '<event_fact type="Marriage" date="'.StructuredData::escapeXml($marriagedate).'" place="'.StructuredData::escapeXml($marriageplace)."\"/>\n";
      }
      $result .= $images;
		$result .= "</family>\n";
		return $result;
	}		

	// construct the page text from what links here
	protected function getPageTextFromWLH($toEditFields, $request=null) {
		// don't get marriage from request if called by propagation
		if ($toEditFields && $request) {
			$marriagedate = $request->getVal('md');
			$marriageplace = $request->getVal('mp');
		}
		else {
			$marriagedate = $marriageplace = '';
		}
		$pageids = $this->getWhatLinksHere();
		return Family::getPageText($marriagedate, $marriageplace, $this->titleString, $pageids);
	}

	protected function addPersonInput($people, $name, $msgTip, $style, $tm, $invalidStyle, $given='', $surname='', $nameParam=false) {
		$rows = '';
		$i = 0;
		if ($this->isGedcomPage) {
			foreach ($people as $person) {
			   $p = htmlspecialchars($person);
				$rows .= "<tr><td><input type=\"hidden\" name=\"{$name}_id$i\" value=\"". ($i+1) ."\"/></td>".
				   "<td>$p<input id=\"$name$i\" class=\"person_input\" tabindex=\"1\" type=\"hidden\" name=\"$name$i\" value=\"$p\"/></td>".
			      "</tr>";
				$i++;
			}
			return "<h2>$msgTip</h2>"
			   ."<table id=\"{$name}_table\" border=0>"
			   ."$rows</table><br><br>";
		}
		else {
			$ns = NS_PERSON;
			foreach ($people as $person) {
				$p = htmlspecialchars($person);
			   $s = $style;
		   	if (!StructuredData::titleStringHasId($person) || !StructuredData::titleExists(NS_PERSON, $person)) {
			   	$s = $invalidStyle;
		   	}
            $editable = false;
            //$label = ($name == 'child' ? '&middot;' : '&nbsp;');
				$rows .= "<tr><td>&nbsp;<input type=\"hidden\" name=\"{$name}_id$i\" value=\"". ($i+1) ."\"/></td>".
				   "<td><input id=\"$name$i\" class=\"person_input\" tabindex=\"1\" type=\"text\" size=40 name=\"$name$i\"$s value=\"$p\"".($editable ? '' : ' readonly="readonly"')."/></td>".
					"<td><a href=\"javascript:void(0)\" onClick=\"removePersonFamily('$name',$i); return preventDefaultAction(event);\">remove</a></td>" .
			      "</tr>";
				$i++;
			}
//			if (count($people) == 0 || $name == 'child') {
//				$rows .= "<tr><td><input type=\"hidden\" name=\"{$name}_id$i\" value=\"". ($i+1) ."\"/></td>".
//				   "<td><input id=\"$name$i\" class=\"person_input\" tabindex=\"1\" type=\"text\" size=40 name=\"$name$i\" value=\"\"/></td>".
//					"<td><a href=\"javascript:void(0)\" onClick=\"choose($ns,'$name$i'); return preventDefaultAction(event);\"><b>find/add&nbsp;&raquo;</b></a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".
//                  "<a href=\"javascript:void(0)\" onClick=\"removePersonFamily('$name',$i); return preventDefaultAction(event);\">remove</a></td>" .
//			      "</tr>";
//			}
         $given = (mb_strtolower($given) == 'unknown' ? '' : htmlspecialchars($given));
         $surname = (mb_strtolower($surname) == 'unknown' ? '' : htmlspecialchars($surname));
         $style = '';
         if ($name == 'child') {
            $linkText = 'Add child';
            $display = 'block';
            $gender = '';
         }
         else {
            $personName = ($given && $surname ? "$given $surname" : $name);
            $linkText = "Add a page for $personName";
            if ($nameParam) {
               $style = 'font-weight:bold';
               $linkText = '&raquo; '.$linkText;
            }
            $display = (count($people) == 0 ? 'block' : 'none');
            $gender = ($name == 'husband' ? 'M' : 'F');
         }
			return "<h2>$msgTip<small>".$tm->addMsgTip($msgTip,400)."</small></h2>"
			   ."<table id=\"{$name}_table\" border=0>"
			   ."$rows</table>"
            ."<div id=\"{$name}_addlink\" style=\"display:$display;$style\" class=\"addMemberLink\">"
            ."<a href=\"javascript:void(0)\" onClick=\"addPage('$name','$gender','$given','$surname'); return preventDefaultAction(event);\">$linkText</a></div>";
      }
	}

   private function addRequestMembers($var, &$titles) {
      global $wgRequest;

      $title = $wgRequest->getVal($var);
      if ($title) {
         $titles[] = $title;
      }
   }

	protected function toEditFields(&$textbox1) {
		global $wgOut, $wgScriptPath, $wgESINHandler, $wgRequest;

      $result = '';
      $target = $wgRequest->getVal('target');

		// add javascript functions
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/jquery.tablednd_0_5.yui.1.js\"></script>");
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/personfamily.38.js\"></script>");
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.11.js\"></script>");

		$tm = new TipManager();

		$invalidStyle = ' style="background-color:#fdd;"';
		$husbandStyle = '';
		$wifeStyle = '';
		$childStyle = '';
		$husbands = array();
		$wives = array();
		$children = array();
		if (!isset($this->xml)) { // && !StructuredData::isRedirect($textbox1)) {
			// construct <family> text from What Links Here
			$oldText = $this->getPageTextFromWLH(true, $wgRequest);
			$this->xml = StructuredData::getXml('family', $oldText);
		}
		if (isset($this->xml)) {
		   $husbands = StructuredData::getTitlesAsArray($this->xml->husband);
		   $wives = StructuredData::getTitlesAsArray($this->xml->wife);
		   $children = StructuredData::getTitlesAsArray($this->xml->child);
		}
      $this->addRequestMembers('ht', $husbands);
      $this->addRequestMembers('wt', $wives);
      $this->addRequestMembers('ct', $children);

	   if (!$this->isGedcomPage && !StructuredData::titleStringHasId($this->titleString)) {
	      $result .= "<p><font color=red>The page title does not have an ID; please create a page with an ID using <a href='/wiki/Special:AddPage/Family'>Add page</a></font></p>";
	   }
	   if (StructuredData::titlesOverlap($husbands,$wives)) {
	   	$result .= "<p><font color=red>The same person cannot be both husband and wife</font></p>";
	   	$husbandStyle = $invalidStyle;
	   	$wifeStyle = $invalidStyle;
	   }
	   if (StructuredData::titlesOverlap($husbands,$children)) {
	   	$result .= "<p><font color=red>The same person cannot be both husband and child</font></p>";
	   	$husbandStyle = $invalidStyle;
	   }
	   if (StructuredData::titlesOverlap($wives,$children)) {
	   	$result .= "<p><font color=red>The same person cannot be both wife and child</font></p>";
	   	$wifeStyle = $invalidStyle;
	   }
	   if (!$this->isGedcomPage && (StructuredData::titlesMissingId($husbands) || !StructuredData::titlesExist(NS_PERSON, $husbands))) {
	   		$result .= "<p><font color=red>Husband page not found; please click remove and add a new one</font></p>";
	   }
	   if (!$this->isGedcomPage && (StructuredData::titlesMissingId($wives) || !StructuredData::titlesExist(NS_PERSON, $wives))) {
	   		$result .= "<p><font color=red>Wife page not found; please click remove and add a new one</font></p>";
	   }
	   if (!$this->isGedcomPage && (StructuredData::titlesMissingId($children) || !StructuredData::titlesExist(NS_PERSON, $children))) {
	   		$result .= "<p><font color=red>Child page not found; please click remove and add a new one</font></p>";
	   }
//   Message for all date errors (not just ambiguous ones) - changed Nov 2021 by Janet Bjorndahl
     if (ESINHandler::hasInvalidDates($this->xml)) {
       $result .= "<p><font color=red>Please correct invalid dates. Dates should be in \"<i>D MMM YYYY</i>\" format (ie 5 Jan 1900) with optional modifiers (eg, bef, aft).</font></p>";
     }
     if (ESINHandler::hasReformatedDates($this->xml)) {                                              // added Nov 2021 by Janet Bjorndahl
       $result .= "<p><font color=red>One or more dates was changed to WeRelate standard. Please compare to original value to ensure no loss of meaning. If the standard date is OK, no further action is required - you may save the page.</font></p>";
     }

      // add spouse input
      list ($hg, $hs, $wg, $ws) = StructuredData::parseFamilyTitle($this->titleString);
      $g = @$wgRequest->getVal('hg');
      $s = @$wgRequest->getVal('hs');
      $husbandParam = ($g || $s);
      if ($husbandParam) {
         $hg = $g;
         $hs = $s;
      }
      $g = @$wgRequest->getVal('wg');
      $s = @$wgRequest->getVal('ws');
      $wifeParam = ($g || $s);
      if ($wifeParam) {
         $wg = $g;
         $ws = $s;
      }
		$result .= $this->addPersonInput($husbands, 'husband', 'Husband', $husbandStyle, $tm, $invalidStyle, $hg, $hs, $husbandParam);
		$result .= $this->addPersonInput($wives, 'wife', 'Wife', $wifeStyle, $tm, $invalidStyle, $wg, $ws, $wifeParam);

		// add event_fact input table
		$result .= $wgESINHandler->addEventsFactsInput($this->xml, self::$STD_EVENT_TYPES, self::$OTHER_EVENT_TYPES);

		// add children input
		$result .= $this->addPersonInput($children, 'child', 'Children', $childStyle, $tm, $invalidStyle);

		// add sources, images, notes input tables
		$result .= $wgESINHandler->addSourcesImagesNotesInput($this->xml);

		$result .= $tm->getTipTexts();

		$result .= '<h2>Family History</h2>';

		return $result;
	}

	// return an array of person attributes given an xml element
	public static function getFamilyMemberAttributes($member) {
		$result = array();
		foreach (self::$FAMILY_MEMBER_ATTRS as $attr) {
			$value = (string)$member[$attr];
			if ($value) {
				$result[$attr] = $value;
			}
		}
		return $result;
	}

	// return an array of person attributes given a page title
	// title must not be a redirect
	public static function loadFamilyMember($title) {
		$result = array();
		$revision = StructuredData::getRevision(Title::newFromText((string)$title, NS_PERSON), false, true); // get the latest version of the page
		if ($revision) {
			$content =& $revision->getText();
			$xml = StructuredData::getXml('person', $content);
			if (isset($xml)) {
				list ($birthDate, $birthPlace, $chrDate, $chrPlace, $deathDate, $deathPlace, $burDate, $burPlace, $deathDesc, $burDesc)
				  = ESINHandler::getBirthChrDeathBurDatePlaceDesc($xml);
				$result['birthdate'] = $birthDate;
				$result['birthplace'] = $birthPlace;
				$result['chrdate'] = $chrDate;
				$result['chrplace'] = $chrPlace;
				$result['deathdate'] = $deathDate;
				$result['deathplace'] = $deathPlace;
				$result['burialdate'] = $burDate;
				$result['burialplace'] = $burPlace;
				foreach ($xml->child_of_family as $pf) {
					$result['child_of_family'] = (string)$pf['title'];
					break;
				}
				$name = $xml->name;
				if (isset($name)) {
					$result['given'] = (string)$name['given'];
					$result['surname'] = (string)$name['surname'];
					$result['title_prefix'] = (string)$name['title_prefix'];
					$result['title_suffix'] = (string)$name['title_suffix'];
				}
			}
		}
		return $result;
	}

	// create the xml element for a family member
	// titleString must not be a redirect
	public static function formatFamilyMemberElement($tag, $titleString, $prevFamilyMembers = array()) {
		// TODO If the family member used to be a child of this family and now it is a spouse of this family, and it has another set of parents,
		// those parents won't appear in this person's spouse element in this family.  Have to include this check in our periodic audit.
		$title = Title::newFromText($titleString,NS_PERSON);
		if ($title) {
			if (isset($prevFamilyMembers[$title->getText()])) {
				$person = $prevFamilyMembers[$title->getText()];
			}
			else {
				$person = Family::loadFamilyMember($title->getText());
			}
			$result = "<$tag title=\"".StructuredData::escapeXml($title->getText()).'"';
			foreach (self::$FAMILY_MEMBER_ATTRS as $attr) {
				if (isset($person[$attr]) && !($tag == 'child' && $attr == 'child_of_family')) {
					$attrValue = trim($person[$attr]);
					if (strlen($attrValue) > 0) {
						$result .= " $attr=\"".StructuredData::escapeXml($attrValue).'"';
					}
				}
			}
			$result .= "/>\n";
		}
		else {
			$result = '';
		}
		return $result;
	}

	// load family member data from previous version of the article
	private function loadFamilyMembers() {
		$xml = null;
		$this->prevFamilyMembers = array();
		if ($this->isGedcomPage) {
			$dataString = GedcomUtil::getGedcomDataString();
   		if ($dataString) {
	   		$xml = simplexml_load_string($dataString);
   		}
		}
		else {
	      $revision = StructuredData::getRevision($this->title);
			if ($revision) {
	         $content =& $revision->getText();
	         $xml = StructuredData::getXml('family', $content);
			}
		}
      if (isset($xml)) {
         foreach ($xml->husband as $member) {
         	$title = (string)$member['title'];
          	$this->prevFamilyMembers[$title] = Family::getFamilyMemberAttributes($member);
         }
         foreach ($xml->wife as $member) {
         	$title = (string)$member['title'];
          	$this->prevFamilyMembers[$title] = Family::getFamilyMemberAttributes($member);
         }
         foreach ($xml->child as $member) {
         	$title = (string)$member['title'];
          	$this->prevFamilyMembers[$title] = Family::getFamilyMemberAttributes($member);
         }
		}
	}

	private function clearFamilyMembers() {
		$this->prevFamilyMembers = null;
	}

	protected function fromPerson($request, $name) {
	   $result = '';
	   $seenTitles = array();
		for ($i = 0; $request->getVal("{$name}_id$i"); $i++) {
		   $titleString = urldecode($request->getVal("$name$i"));
		   if (!$this->isGedcomPage && $titleString) {
		   	$title = Title::newFromText($titleString, NS_PERSON);
		   	if ($title) {
		   		$title = StructuredData::getRedirectToTitle($title); // ok to read from slave here; mistakes will be corrected in propagate
		   		$titleString = $title->getText();
		   	}
		   	else {
		   		$titleString = '';
		   	}
		   }
		   if ($titleString && !in_array($titleString, $seenTitles)) {
		   	$seenTitles[] = $titleString;
		      if (!$this->isGedcomPage && !StructuredData::titleStringHasId($titleString)) {
		         $titleString = StructuredData::standardizeNameCase($titleString);
		      }
		      $result .= Family::formatFamilyMemberElement($name, $titleString, $this->prevFamilyMembers);
		   }
		}
	   return $result;
	}
	
	public static function addPersonToRequestData(&$requestData, $name, $i, $titleString) {
		$requestData["{$name}_id$i"] = $i+1;
		$requestData["$name$i"] = $titleString;
	}
	
	/**
     * Return xml elements from data in request
     * @param unknown $request
     */
	protected function fromEditFields($request) {
		global $wgESINHandler;
		//		wfDebug("WR:FromEditFields\n");
		$result = '';
		// load attrs for family members
		if (!$this->isMerging) {
			$this->loadFamilyMembers();
		}
		$result .= $this->fromPerson($request, 'husband');
		$result .= $this->fromPerson($request, 'wife');
		$children = $this->fromPerson($request, 'child');
		ESINHandler::sortChildren($children);
		$result .= $children;
		if (!$this->isMerging) {
			$this->clearFamilyMembers();
		}
		
		$wgESINHandler->generateSINMap($request); // must be called before fromEventsFacts or fromSourcesImagesNotes

		$result .= $wgESINHandler->fromEventsFacts($request, self::$STD_EVENT_TYPES);

		$result .= $wgESINHandler->fromSourcesImagesNotes($request);
		
		$wgESINHandler->clearSINMap();

		return $result;
	}

	/**
     * Return true if xml property is valid
     */
	protected function validateData(&$textbox1) {
    global $wgUser;
 	  if (!StructuredData::titleStringHasId($this->titleString)) {
	  	return false;
	  }
//   All date errors (not just ambiguous dates) have to be fixed - changed Nov 2021 by Janet Bjorndahl
    if (ESINHandler::hasInvalidDates($this->xml)) {
        return false;
    }
		if (!StructuredData::isRedirect($textbox1)) {
			$husbands = StructuredData::getTitlesAsArray($this->xml->husband);
			$wives = StructuredData::getTitlesAsArray($this->xml->wife);
			$children = StructuredData::getTitlesAsArray($this->xml->child);
			return (!StructuredData::titlesOverlap($husbands,$wives) &&
					  !StructuredData::titlesOverlap($husbands,$children) &&
					  !StructuredData::titlesOverlap($wives,$children) &&
					  ($this->isGedcomPage || !StructuredData::titlesMissingId($husbands)) &&
					  ($this->isGedcomPage || !StructuredData::titlesMissingId($wives)) &&
					  ($this->isGedcomPage || !StructuredData::titlesMissingId($children)) &&
            !ESINHandler::hasReformatedDates($this->xml) &&                                    // added Nov 2021 by Janet Bjorndahl
                 ($wgUser->isAllowed('patrol') || StructuredData::titlesExist(NS_PERSON, $husbands)) &&
                 ($wgUser->isAllowed('patrol') || StructuredData::titlesExist(NS_PERSON, $wives)) &&
                 ($wgUser->isAllowed('patrol') || StructuredData::titlesExist(NS_PERSON, $children))
         );
		}
		return true;
	}
	
	public static function getPropagatedElement($tag, $title, &$pd) {
		$title = StructuredData::escapeXml($title);
		return "<$tag title=\"$title\"/>\n";
	}

	private static function getPropagatedData($xml) {
		$o = array();
		if (isset($xml)) {
			$o['husbands'] = StructuredData::getTitlesAsArray($xml->husband);
			$o['wives'] = StructuredData::getTitlesAsArray($xml->wife);
			$o['children'] = StructuredData::getTitlesAsArray($xml->child);
			$o['images'] = array();
			foreach($xml->image as $i) {
				$o['images'][] = array('filename' => (string)$i['filename'], 'caption' => (string)$i['caption']);
			}
		}
		else {
			$o['husbands'] = array();
			$o['wives'] = array();
			$o['children'] = array();
			$o['images'] = array();
		}
		return $o;
	}

	// update a person link on a family page
	// tag is husband, wife, or child
	public static function updatePersonLink($tag, $oldTitle, $newTitle, &$pd, $familyTag, &$text, &$textChanged) {
		if ($newTitle) {
			$new = Person::getPropagatedElement($tag, $newTitle, $pd, $familyTag);
		}
		else {
			$new = '';
		}
		$old = "<{$tag}[^>]*? title=\"" . StructuredData::protectRegexSearch(StructuredData::escapeXml($oldTitle)) . "\".*?/>\n";
		$found = false;
		if (preg_match('$'.$old.'$', $text)) {
			$found = true;
		}
		else if ($tag == 'husband' || $tag == 'wife') {
			// check the other tag just in case
			$old = '<'. ($tag == 'husband' ? 'wife' : 'husband') . '[^>]*? title="' .
			         StructuredData::protectRegexSearch(StructuredData::escapeXml($oldTitle)) . "\".*?/>\n";
			if (preg_match('$'.$old.'$', $text)) {
				$found = true;
			}
		}
		if (!$found) {
         $old = ESINHandler::findRelationshipInsertionPointTag($tag, $text);
			$new .= $old;
		}

		// limit to 1 replacement, sine there could be multiple <child tags and we don't want to insert the new child more than once
		$result = preg_replace('$'.$old.'$', StructuredData::protectRegexReplace($new), $text, 1);
		// keep children sorted
		if ($tag == 'child') {
   		$childBegin = mb_strpos($result, '<child ');
   		if ($childBegin !== false) {
   		   $childEnd = $childBegin;
   		   while (mb_substr($result, $childEnd, strlen('<child ')) == '<child ') {
   		      $childEnd = mb_strpos($result, "/>\n", $childEnd) + strlen("/>\n");
   		   }
   		   $children = mb_substr($result, $childBegin, $childEnd - $childBegin);
   		   ESINHandler::sortChildren($children);
   		   $result = mb_substr($result, 0, $childBegin) . $children . mb_substr($result, $childEnd);
   		}
		}

		if ($result != $text) {
			$text = $result;
			$textChanged = true;
		}
	}

	// update the family link on the person page
	// tag is child_of_family or spouse_of_family
	private function updatePerson($personTitle, $personTag, $familyTag, $newTitle, &$text, &$textChanged) {
	   if (!PropagationManager::isPropagatablePage($personTitle)) {
	      return true;
	   }

		$result = true;
		$article = StructuredData::getArticle($personTitle, true);
		if ($article) {
			$content =& $article->fetchContent();
			$updated = false;
			Person::updateFamilyLink($familyTag, $this->titleString, $newTitle, $content, $updated);
			if ($updated) {
				$result = $article->doEdit($content, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
            StructuredData::purgeTitle($personTitle, +1); // purge person with a fudge factor so family link will be blue
			}
			else {
			   error_log("propagating family {$this->titleString} nothing changed in {$personTitle->getPrefixedText()}");
			}

			// if we're not deleting this entry (newTitle is not empty), and the person article is a redirect (article title != personTitle),
			// we need to update the person page title in the family page text
			if ($newTitle && $personTitle->getText() != $article->getTitle()->getText()) {
				$old = 'title="' . StructuredData::escapeXml($personTitle->getText()) . '"';
				$new = 'title="' . StructuredData::escapeXml($article->getTitle()->getText()) . '"';
				$text = str_replace($old, $new, $text);
				$textChanged = true;
			}
		}
		return $result;
	}

	private function propagateFamilyMemberEditData($members, $origMembers, $memberTag, $familyTag, &$text, &$textChanged) {
		$result = true;
		$addMembers = array_diff($members, $origMembers);
		$delMembers = array_diff($origMembers, $members);
		
		// remove from deleted members
		foreach ($delMembers as $p) {
			$personTitle = Title::newFromText($p, NS_PERSON);
			PropagationManager::addPropagatedAction($this->title, 'del'.($memberTag == 'child' ? 'child' : 'spouse'), $personTitle);
			if (PropagationManager::isPropagatableAction($personTitle, 'del'.$familyTag, $this->title)) {
				$result = $result && $this->updatePerson($personTitle, $memberTag, $familyTag, null, $text, $textChanged);
			}
		}

		// add to new members
		foreach ($addMembers as $p) {
			$personTitle = Title::newFromText($p, NS_PERSON);
			PropagationManager::addPropagatedAction($this->title, 'add'.($memberTag == 'child' ? 'child' : 'spouse'), $personTitle);
			if (PropagationManager::isPropagatableAction($personTitle, 'add'.$familyTag, $this->title)) {
				$result = $result && $this->updatePerson($personTitle, $memberTag, $familyTag, $this->titleString, $text, $textChanged);
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
		global $wrIsGedcomUpload, $wgESINHandler;

      $result = true;

      // cache new xml - it's used right away to generate family badges on the related person pages,
      // if you don't cache it, the badges pick up the old html
      $this->cachePageXml();

      // update people that link to this family, because the family-badge contents could have changed
      // TODO this could be made more efficient by only invalidating if names, birthdates, or deathdates have changed
      $u = new HTMLCacheUpdate( $this->title, 'pagelinks' );
      $u->doUpdate();

		// get current info
		$propagatedData = Family::getPropagatedData($this->xml);
		$redirTitle = Title::newFromRedirect($text);

		// get original info
		$origPropagatedData = Family::getPropagatedData(null);
		// don't bother construction page text from WLH in a gedcom upload because nothing will link to this new page
		if (!@$wrIsGedcomUpload && (!$oldText || mb_strpos($oldText, '<family>') === false)) { // oldText contains MediaWiki:noarticletext if the article is being created
			// construct <family> text from What Links Here
			$oldText = $this->getPageTextFromWLH(false);
		}
		$origXml = null;
		if ($oldText) {
			$origXml = StructuredData::getXml('family', $oldText);
			if (isset($origXml)) {
				$origPropagatedData = Family::getPropagatedData($origXml);
			}
		}

		// TODO!!!
		// Revert, Unmerge, and eventually Undo should be getting the current attrs for existing people from origPropagatedData 
		// and getting the current attrs and redirect-titles for newly-added people from the Person pages when adding the family title to them
		// then unmerge wouldn't need to get them in unmerge, and revert wouldn't be broken, and undo won't break things.
		// This duplicates the functionality found in fromEditFields, but it allows us to update the pages without going through fromEditFields
		// and it doesn't require reading any pages that we weren't reading already.
		// Also, instead of isMerging, if this Family page is on the propagation manager blacklist, then you can't trust the prior version
		// and we should get the person attrs from the Person pages for _all_ family members.
		// Finally, make sure that after redirects we don't have 2 links to the same Person (and also two links to the same Family on Person pages).
		
		// ignore changes of the husband <-> wife role for the same person
		$temp = array_diff($propagatedData['husbands'], $origPropagatedData['wives']);
		$origPropagatedData['wives'] = array_diff($origPropagatedData['wives'], $propagatedData['husbands']);
		$propagatedData['husbands'] = $temp;
		$temp = array_diff($propagatedData['wives'], $origPropagatedData['husbands']);
		$origPropagatedData['husbands'] = array_diff($origPropagatedData['husbands'], $propagatedData['wives']);
		$propagatedData['wives'] = $temp;

		$result = $result && $this->propagateFamilyMemberEditData($propagatedData['husbands'], $origPropagatedData['husbands'], 'husband', 'spouse_of_family', $text, $textChanged);
		$result = $result && $this->propagateFamilyMemberEditData($propagatedData['wives'], $origPropagatedData['wives'], 'wife', 'spouse_of_family', $text, $textChanged);
		$result = $result && $this->propagateFamilyMemberEditData($propagatedData['children'], $origPropagatedData['children'], 'child', 'child_of_family', $text, $textChanged);

		if (StructuredData::removeDuplicateLinks('husband|wife|child', $text)) {
			$textChanged = true;
		}
		
		$result = $result && $wgESINHandler->propagateSINEdit($this->title, 'family', $this->titleString, $propagatedData, $origPropagatedData, $text, $textChanged);
		
      // ensure footer tag is still there (might have been removed by editing the last section)
		if ($redirTitle == null && strpos($text, ESINHandler::ESIN_FOOTER_TAG) === false) {
		   if (strlen($text) > 0 && substr($text, strlen($text) - 1) != "\n") {
		      $text .= "\n";
		   }
		   $text .= ESINHandler::ESIN_FOOTER_TAG;
		   $textChanged = true;
		}

      // update watchlist summary if changed
      $summary = Family::getSummary($this->xml, $this->title);
      $origSummary = Family::getSummary($origXml, $this->title);
      if ($summary != $origSummary) {
         StructuredData::updateWatchlistSummary($this->title, $summary);
      }

		// if it's a redirect, add the people, families, and images that were on this page to the redirect target
		// but don't bother updating the redir target during a merge
		if ($redirTitle != null && PropagationManager::isPropagatablePage($redirTitle)) { 
		   // get the text of the redir page
			$article = StructuredData::getArticle($redirTitle, true);
			if ($article) {
				$content =& $article->fetchContent();
				$updated = false;
		   	// add husbands from this page to the redir page
				foreach ($origPropagatedData['husbands'] as $p) {
					// get propagated data for p
					$pd = Person::getPropagatedData(StructuredData::getXmlForTitle('person', Title::newFromText($p, NS_PERSON)));
					Family::updatePersonLink('husband', $p, $p, $pd, 'spouse_of_family', $content, $updated);
				}
			   // add wives from this page to the redir page
				foreach ($origPropagatedData['wives'] as $p) {
					$pd = Person::getPropagatedData(StructuredData::getXmlForTitle('person', Title::newFromText($p, NS_PERSON)));
					Family::updatePersonLink('wife', $p, $p, $pd, 'spouse_of_family', $content, $updated);
				}
			   // add children from this page to the redir page
				foreach ($origPropagatedData['children'] as $p) {
					$pd = Person::getPropagatedData(StructuredData::getXmlForTitle('person', Title::newFromText($p, NS_PERSON)));
					Family::updatePersonLink('child', $p, $p, $pd, 'child_of_family', $content, $updated);
				}
			   // add images from this page to the redir page
			   foreach ($origPropagatedData['images'] as $i) {
					ESINHandler::updateImageLink('family', $i['filename'], $i['filename'], $i['caption'], $content, $updated);
			   }
			   // update the redir page if necessary
				if ($updated) {
					$result = $result && $article->doEdit($content, 'Copy data from [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
				}
			}
		}
		
		if (!$result) {
			error_log("ERROR! Family edit/rollback not propagated: $this->titleString\n");
		}
		return $result;
	}

	private function getPersonData($wlhPeople, $thisPeople, $tag) {
		$people = array();
      foreach ($wlhPeople as $p) {
         $people[] = (string)$p['title'];
      }
      foreach ($thisPeople as $p) {
         $personTitle = (string)$p['title'];
         $t = Title::newFromText($personTitle, NS_PERSON);
         $t = StructuredData::getRedirectToTitle($t, true);
         $personTitle = $t->getText();
         if (!in_array($personTitle, $people)) {
            $people[] = $personTitle;
         }
      }
      $peopleText = '';
      foreach ($people as $p) {
         $peopleText .= Family::formatFamilyMemberElement($tag, $p);
      }
	   return $peopleText;
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
	   global $wgESINHandler;

	   $result = true;
		$newTitle = ($newTitleString ? Title::newFromText($newTitleString, NS_FAMILY) : null);

		// if we're undeleting, add additional people from WLH, getting updated person data
      if ($this->titleString == $newTitleString) {
			$wlh = simplexml_load_string($this->getPageTextFromWLH(false));
         // get text for all people
         // TODO is propagateEditData called after Undelete? if so, then we could get updated person attributes there
			$personText = $this->getPersonData($wlh->husband, $this->xml->husband, 'husband') .
			              $this->getPersonData($wlh->wife, $this->xml->wife, 'wife') .
			              $this->getPersonData($wlh->child, $this->xml->child, 'child') .
				           $wgESINHandler->getImageData($wlh->image, $this->xml->image);
			// update text: replace old family information with new
         $text = preg_replace("$<(husband|wife|child|image) [^>]*>\n$", '', $text);
         $text = preg_replace('$</family>$', StructuredData::protectRegexReplace($personText . '</family>'), $text, 1);
			$this->xml = StructuredData::getXml($this->tagName, $text);
         $textChanged = true;
      }

      // get data to propagate
		$propagatedData = Family::getPropagatedData($this->xml);

		foreach ($propagatedData['husbands'] as $p) {
		   $personTitle = Title::newFromText((string)$p, NS_PERSON);
			PropagationManager::addPropagatedAction($this->title, 'delspouse', $personTitle);
			if ($newTitle) PropagationManager::addPropagatedAction($newTitle, 'addspouse', $personTitle);
			// don't need to check propagated action before calling updatePerson, because propagateMoveDeleteUndelete is never part of a loop
  			$result = $result && $this->updatePerson($personTitle, 'husband', 'spouse_of_family', $newTitleString, $text, $textChanged);
		}

		foreach ($propagatedData['wives'] as $p) {
		   $personTitle = Title::newFromText((string)$p, NS_PERSON);
			PropagationManager::addPropagatedAction($this->title, 'delspouse', $personTitle);
			if ($newTitle) PropagationManager::addPropagatedAction($newTitle, 'addspouse', $personTitle);
			// don't need to check propagated action before calling updatePerson, because propagateMoveDeleteUndelete is never part of a loop
     		$result = $result && $this->updatePerson($personTitle, 'wife', 'spouse_of_family', $newTitleString, $text, $textChanged);
		}

		foreach ($propagatedData['children'] as $p) {
		   $personTitle = Title::newFromText((string)$p, NS_PERSON);
			PropagationManager::addPropagatedAction($this->title, 'delchild', $personTitle);
			if ($newTitle) PropagationManager::addPropagatedAction($newTitle, 'addchild', $personTitle);
			// don't need to check propagated action before calling updatePerson, because propagateMoveDeleteUndelete is never part of a loop
  			$result = $result && $this->updatePerson($personTitle, 'child', 'child_of_family', $newTitleString, $text, $textChanged);
		}
		
		if (StructuredData::removeDuplicateLinks('husband|wife|child', $text)) {
			$textChanged = true;
		}
		
		$result = $result && $wgESINHandler->propagateSINMoveDeleteUndelete($this->title, 'family', $this->titleString, $newTitleString, $propagatedData, $text, $textChanged);

		if (!$result) {
			error_log("ERROR! Family move/delete/undelete not propagated: $this->titleString -> " .
			($newTitleString ? $newTitleString : "[delete]") . "\n");
		}
		return $result;
	}
}
?>
