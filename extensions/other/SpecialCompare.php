<?php
/**
 * @package MediaWiki
 */
if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once("$IP/extensions/gedcom/GedcomUtil.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialCompareSetup";

function wfSpecialCompareSetup() {
	global $wgMessageCache, $wgSpecialPages, $wgHooks;
	
	$wgMessageCache->addMessages( array( "compare" => "Compare pages" ) );
	$wgSpecialPages['Compare'] = array('SpecialPage','Compare');
}

/**
 * Called to display the Special:Compare page
 *
 * @param unknown_type $par
 * @param unknown_type $specialPage
 */
function wfSpecialCompare( $par=NULL, $specialPage ) {
	global $wgOut, $wgScriptPath, $wgUser, $wrSidebarHtml;
	
	$compareForm = new CompareForm();

	$wgOut->setPageTitle('Compare pages');

	// read query parameters into variables
	if (!$compareForm->readQueryParms($par)) {
		$sideText = '';
		$results = $compareForm->getCompareForm();
	}
	else {
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/compare.8.js\"></script>");
		$isGedcom = $compareForm->isGedcom();
		$sideText = '<p>'.($isGedcom ? 'Matching GEDCOM families' : 'Merge').' is a two-step process.  In this compare step, check the boxes above the matching pages.</p>'.
						($compareForm->getNamespace() == 'Family' ? '<p>To match children, choose the child number to match with.</p>' : '').
						($isGedcom ? '<p>Then scroll to the bottom of the page and click "Match" to match this family.</p>' .
											'<p>In the next step you\'ll be given a chance to update the matched pages with information from your GEDCOM</p>'
									  : '<p>Then click "Prepare to merge" at the bottom of the page.</p>'.
									  		'<p>In the next step you\ll be given a chance to decide what information to keep on the merged page.</p>').
						'<p><font color="green">Green</font> boxes mean the information is specific and matches exactly.</p>'.
						'<p><font color="yellow">Yellow</font> boxes mean the information is non-specific (missing some pieces) or is a partial match.</p>'.
						'<p><font color="red">Red</font> boxes mean the information differs.</p>'.
						'<p>(<a href="/wiki/Help:Merging_pages">more help</a>)</p>';
		$results = $compareForm->getCompareResults();
	}

   $skin = $wgUser->getSkin();
   //$wrSidebarHtml = $skin->makeKnownLink('Help:Merging pages', "Help", '', '', '', 'class="popup"');
   $wrSidebarHtml = wfMsgWikiHtml('CompareHelp');
	$wgOut->addHTML($results);
}

 /**
  * Compare form used in Special:Compare
  */
class CompareForm {
	private static $PERSON_COMPARE_LABELS = array('Title', 'Given', 'Surname', 'Prefix', 'Suffix', 'Gender', 'Birthdate', 'Birthplace', 'Christeningdate', 'Christeningplace', 'Deathdate', 'Deathplace', 'Burialdate', 'Burialplace',
																'fatherTitle', 'fatherGiven', 'fatherSurname', 'fatherPrefix', 'fatherSuffix', 'fatherBirthdate', 'fatherBirthplace', 'fatherChristeningdate', 'fatherChristeningplace', 'fatherDeathdate', 'fatherDeathplace', 'fatherBurialdate', 'fatherBurialplace',
																'motherTitle', 'motherGiven', 'motherSurname', 'motherPrefix', 'motherSuffix', 'motherBirthdate', 'motherBirthplace', 'motherChristeningdate', 'motherChristeningplace', 'motherDeathdate', 'motherDeathplace', 'motherBurialdate', 'motherBurialplace',
																'spouseTitle', 'spouseGiven', 'spouseSurname', 'spousePrefix', 'spouseSuffix', 'spouseBirthdate', 'spouseBirthplace', 'spouseChristeningdate', 'spouseChristeningplace', 'spouseDeathdate', 'spouseDeathplace', 'spouseBurialdate', 'spouseBurialplace');
	private static $FAMILY_COMPARE_LABELS = array('familyTitle', 'Marriagedate', 'Marriageplace',
																'husbandTitle', 'husbandGiven', 'husbandSurname', 'husbandPrefix', 'husbandSuffix', 'husbandBirthdate', 'husbandBirthplace', 'husbandChristeningdate', 'husbandChristeningplace', 'husbandDeathdate', 'husbandDeathplace', 'husbandBurialdate', 'husbandBurialplace', 'husbandParentFamilyTitle',
																'wifeTitle', 'wifeGiven', 'wifeSurname', 'wifePrefix', 'wifeSuffix', 'wifeBirthdate', 'wifeBirthplace', 'wifeChristeningdate', 'wifeChristeningplace', 'wifeDeathdate', 'wifeDeathplace', 'wifeBurialdate', 'wifeBurialplace', 'wifeParentFamilyTitle');
	private static $CHILD_COMPARE_LABELS = array('childTitle', 'childGiven', 'childSurname', 'childPrefix', 'childSuffix', 'childGender', 'childBirthdate', 'childBirthplace', 'childChristeningdate', 'childChristeningplace', 'childDeathdate', 'childDeathplace', 'childBurialdate', 'childBurialplace', 'childSpouseFamilyTitle');
	private static $OPTIONAL_LABELS = array('Prefix', 'Suffix', 'Christeningdate', 'Christeningplace', 'Burialdate', 'Burialplace',
																'fatherPrefix', 'fatherSuffix', 'fatherChristeningdate', 'fatherChristeningplace', 'fatherBurialdate', 'fatherBurialplace',
																'motherPrefix', 'motherSuffix', 'motherChristeningdate', 'motherChristeningplace', 'motherBurialdate', 'motherBurialplace',
																'spousePrefix', 'spouseSuffix', 'spouseChristeningdate', 'spouseChristeningplace', 'spouseBurialdate', 'spouseBurialplace',
																'husbandPrefix', 'husbandSuffix', 'husbandChristeningdate', 'husbandChristeningplace', 'husbandBurialdate', 'husbandBurialplace', 'husbandParentFamilyTitle',
																'wifePrefix', 'wifeSuffix', 'wifeChristeningdate', 'wifeChristeningplace', 'wifeBurialdate', 'wifeBurialplace', 'wifeParentFamilyTitle',
																'childPrefix', 'childSuffix', 'childChristeningdate', 'childChristeningplace', 'childBurialdate', 'childBurialplace', 'childSpouseFamilyTitle');
	
	public static $COMPARE_PAGE_CLASS = 'compare_page';
	public static $COMPARE_DEFAULT_CLASS = 'compare_default';
	private static $COMPARE_MATCH_CLASS = 'compare_match';
	private static $COMPARE_PARTIAL_CLASS = 'compare_partial';
	private static $COMPARE_NOMATCH_CLASS = 'compare_nomatch';
	private static $COMPARE_SUPERLABEL_CLASS = 'compare_superlabel';
	private static $COMPARE_LABEL_CLASS = 'compare_label';
	private static $COMPARE_ULC_CLASS = 'compare_ulc';
	private static $COMPARE_SEPARATOR_CLASS = 'compare_separator';
	
	private static $EXACT_MATCH_SCORE = 2;
	private static $PARTIAL_MATCH_SCORE = 1;
   private static $PARTIAL_NON_MATCH_SCORE = -1;
   private static $NON_MATCH_SCORE = -2;
   private static $NAME_BOOST = 1.25;
   private static $PLACE_BOOST = 0.45;
   private static $SPOUSE_FAMILY_BOOST = 0.3;
	private static $MAX_WATCHERS = 5;
	
	private static $SPOUSE_MATCH_THRESHOLD = 0;
	
	private $namespace;
	private $compareTitles;
	private $gedcomData;
	private $gedcomDataString;
	private $gedcomTab;
	private $gedcomKey;
	
	public static function getNomergeTitleStrings($title) {
		$result = array();
		if ($title) {
			$revision = Revision::newFromTitle($title->getTalkPage());
			if ($revision) {
				$text =& $revision->getText();
				if ($text) {
					$matches = array();
					if (preg_match_all('/{{nomerge\s*\|([^}]*)}}/i', $text, $matches, PREG_SET_ORDER)) {
						foreach ($matches as $match) {
							$t = Title::newFromText(urldecode($match[1]), $title->getNamespace()); // urldecode translates + into space; %28 into (, etc.
							if ($t && $t->getText() != $title->getText()) {  // ignore the case of {{nomerge|self title}}
								$result[] = $t->getText();
							}
						}
					}
				}
			}
		}
		return $result;
	}
	
   public static function isTrustedMerger($user, $isGedcom) {
      if ($user->isAllowed('patrol')) {
         return true;
      }
      return in_array($user->getName(), explode('|', wfMsg($isGedcom ? 'trustedgedcomuploaders' : 'trustedmergers')));
   }
   
   public static function isUpdatable($title, &$contents) {
	   $dbr =& wfGetDB(DB_SLAVE);
	   
  	   $count = $dbr->selectField('watchlist', 'count(*)', array('wl_namespace' => $title->getNamespace(), 
		   	   																 'wl_title' => $title->getDBkey()));
		if ($count > self::$MAX_WATCHERS || 	   			                                     // count watchers
			 preg_match('/{{(source-wikipedia|wikipedia-notice|moreinfo wikipedia)\s*\|/i', $contents)) { // check content for wp template
			return false;
		}
		return true;
   }
	
   public static function getSemiprotectedMessage($isTrusted) {
		$output = '<p>Some of the pages are <font color="red"><b>semi-protected</b></font>';
		if ($isTrusted) {
			$output .= '.  As someone who is trusted you can go ahead and update them, but please remember that semi-protected pages are likely to have good content already.';
		}
		else {
			$output .= ", meaning they cannot be changed here. You can match them, but if you want to add information to them you will need to edit them later.";
		}
		$output .= '</p>';
		return $output;
   }
   
	// this function ignores "unknown" name pieces
   public static function standardizeValues($label, $values) {
   	$stdValues = array();
   	if (is_array(@$values)) {
			foreach ($values as $value) {
				$stdValue = '';
				if (strpos($label, 'Surname') !== false || strpos($label, 'Given') !== false) {
					$pieces = explode(' ', $value);
					$sdxValue = '';
					foreach ($pieces as $piece) {
						$lowerPiece = mb_strtolower($piece);
						if ($piece && $lowerPiece != 'unknown' && $lowerPiece != '?'
                          && $lowerPiece != 'fnu' && $lowerPiece != 'lnu' &&
                          $lowerPiece != 'nn' && $lowerPiece != 'n.n.') {
							if ($stdValue) { $stdValue .= ' '; $sdxValue .= ' '; }
							$stdValue .= $lowerPiece;
							$sdxValue .= soundex($piece);
						}
					}
					$stdValue .= '|' . $sdxValue;
				}
				else if (strpos($label, 'date') !== false) {
					$stdValue = DateHandler::getDateKey($value);       // changed to DateHandler function Oct 2020 by Janet Bjorndahl
				}
				else if (strpos($label, 'place') !== false) {
					$stdValue = StructuredData::getPlaceKey($value);
					$pos = mb_strpos($stdValue, ', united states');
					if ($pos !== false) {
						$stdValue = mb_substr($stdValue, 0, $pos); // remove united states for ,-check in scoreMatch below
					}
				}
				else if (strpos($label, 'ParentFamilyTitle') !== false || strpos($label, 'SpouseFamilyTitle') !== false) {
					$pos = mb_strpos($value, '(');
					if ($pos !== false) {
						$stdValue = mb_substr($value, 0, $pos);
					}
					else {
						$stdValue = $value;
					}
					$stdValue = trim(mb_convert_case($stdValue, MB_CASE_LOWER));
				}
				else {
					$stdValue = $value;
				}
				if ($stdValue) {
					$stdValues[] = $stdValue;
				}
			}
   	}
   	else if ($label == 'childGedcomMatchTitle' && @$values) { // keep this for matching
   		$stdValues[] = $values;
   	}
   	return $stdValues;
   }
   
   public static function getLabelClass($label) {
   	if (strpos($label, 'Title') !== false) {
   		if ($label == 'fatherTitle' || $label == 'motherTitle' || $label == 'spouseTitle') {
   			return self::$COMPARE_SUPERLABEL_CLASS;
   		}
   		else if (StructuredData::endsWith($label, 'FamilyTitle')) {
   			return self::$COMPARE_LABEL_CLASS;
   		}
   		else {
   			return self::$COMPARE_ULC_CLASS;
   		}
   	}
   	else {
   		return self::$COMPARE_LABEL_CLASS;
   	}
   }
   
	public static function formatLabel($label, $childNum = 0) {
		if ($label == 'Title' || $label == 'familyTitle') {
			return '';
		}
		else if ($label == 'childTitle') {
			return "Child $childNum";
		}
		else if (strpos($label, 'Title') !== false) {
			$label = substr($label, 0, strlen($label) - 5);
		}
		else {
			if (strpos($label, 'father') !== false || strpos($label, 'mother') !== false || strpos($label, 'spouse') !== false) {
				$label = substr($label, 6);
			}
			else if (strpos($label, 'husband') !== false) {
				$label = substr($label, 7);
			}
			else if (strpos($label, 'wife') !== false) {
				$label = substr($label, 4);
			}
			else if (strpos($label, 'child') !== false) {
				$label = substr($label, 5);
			}
		}
		return wfMsg($label);
	}
   
   public static function insertEmptyRow($cols) {
		return '<tr><td colspan="'.$cols.'" class="'.self::$COMPARE_SEPARATOR_CLASS.'">&nbsp;</td></tr>';
   }

   public static function formatValue($label, $value, $query = '') {
   	global $wgUser;
   	
   	if (!$value) {
   		return '&nbsp;';
   	}
   	else if (strpos($label, 'place') !== false) {
   		return StructuredData::getPlaceLink($value);
   	}
   	else if (strpos($label, 'Title') !== false) {
   		if (GedcomUtil::isGedcomTitle($value)) {
   			$title = htmlspecialchars($value);
   		}
   		else {
		   	$skin =& $wgUser->getSkin();
		   	$t = Title::newFromText($value, StructuredData::endsWith($label, 'familyTitle', true) ? NS_FAMILY : NS_PERSON);
				$title = $skin->makeLinkObj($t, htmlspecialchars($value), $query);		   		
   		}
   		return (StructuredData::endsWith($label, 'FamilyTitle') ? $title : "<b>$title</b>");
   	}
   	else {
   		return htmlspecialchars($value);
   	}
   }
   
   public static function scoreMatch($label, $stdValues1, $stdValues2) {
   	$score = 0;
      $class = CompareForm::$COMPARE_DEFAULT_CLASS;
      $closeYears = false;
   	if (is_array($stdValues1) && is_array($stdValues2)) {
	   	foreach ($stdValues1 as $sv1) {
	   		foreach ($stdValues2 as $sv2) {
	   			if ($sv1 && $sv2) {
						if (strpos($label, 'Surname') !== false || strpos($label, 'Given') !== false) {
							if ($sv1 != '|' && $sv2 != '|') {
								if ($score == 0) {
                           $score = CompareForm::$NON_MATCH_SCORE * CompareForm::$NAME_BOOST;
                           $class = CompareForm::$COMPARE_NOMATCH_CLASS;
                        }
								if ($score < (CompareForm::$EXACT_MATCH_SCORE * CompareForm::$NAME_BOOST) && $sv1 == $sv2) {
									$score = CompareForm::$EXACT_MATCH_SCORE * CompareForm::$NAME_BOOST;
                           $class= CompareForm::$COMPARE_MATCH_CLASS;
								}
								else if ($score < (CompareForm::$PARTIAL_MATCH_SCORE * CompareForm::$NAME_BOOST)) {
									$pieces1 = explode('|', $sv1);
									$pieces2 = explode('|', $sv2);
									if (($pieces2[0] && $pieces1[0] && strpos($pieces1[0], $pieces2[0]) !== false) || 
										 ($pieces1[0] && $pieces2[0] && strpos($pieces2[0], $pieces1[0]) !== false) ||
										 ($pieces2[1] && $pieces1[1] && strpos($pieces1[1], $pieces2[1]) !== false) || 
										 ($pieces1[1] && $pieces2[1] && strpos($pieces2[1], $pieces1[1]) !== false)) {
										$score = CompareForm::$PARTIAL_MATCH_SCORE * CompareForm::$NAME_BOOST;
                              $class = CompareForm::$COMPARE_PARTIAL_CLASS;
									}
								}
							}
						}
						else if (strpos($label, 'date') !== false) {
                     $l1 = strlen($sv1);
                     $l2 = strlen($sv2);
                     $y1 = (int)substr($sv1, 0, 4);
                     $y2 = (int)substr($sv2, 0, 4);
                     $diff = abs($y1-$y2);
                     // full match
                     if ($score < CompareForm::$EXACT_MATCH_SCORE && $l1 == 8 && $sv1 == $sv2) {
                        $score = CompareForm::$EXACT_MATCH_SCORE;
                        $class= CompareForm::$COMPARE_MATCH_CLASS;
                     }
                     // full, same year
                     else if (!$closeYears && ($score == 0 || $score < CompareForm::$PARTIAL_NON_MATCH_SCORE) && $l1 == 8 && $l2 == 8 && $y1 == $y2) {
                        $score = CompareForm::$PARTIAL_NON_MATCH_SCORE;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
                     }
                     // full, diff year
                     else if (!$closeYears && $score == 0 && $l1 == 8 && $l2 == 8 && $y1 != $y2) {
                        $score = CompareForm::$NON_MATCH_SCORE;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
                     }
                     // yearonly, same year
                     else if ($score < CompareForm::$PARTIAL_MATCH_SCORE && $l1 >= 4 && $l2 >= 4 && $y1 == $y2) {
                        $score = CompareForm::$PARTIAL_MATCH_SCORE;
                        $class = CompareForm::$COMPARE_PARTIAL_CLASS;
                     }
                     // yearonly, diff year 1..2
                     else if ($score <= 0 && $l1 >= 4 && $l2 >= 4 && $diff >= 1 && $diff <= 2) {
                        $score = 0;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
                        $closeYears = true;
                     }
                     // yearonly, diff year 3..5
                     else if (!$closeYears && ($score == 0 || $score < CompareForm::$PARTIAL_NON_MATCH_SCORE) && $l1 >= 4 && $l2 >= 4 && $diff >= 3 && $diff <= 5) {
                        $score = CompareForm::$PARTIAL_NON_MATCH_SCORE;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
                     }
                     // yearonly, diff year > 5
                     else if (!$closeYears && $score == 0 && $l1 >= 4 && $l2 >= 4 && $diff > 5) {
                        $score = CompareForm::$NON_MATCH_SCORE;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
                     }
						}
						else if (strpos($label, 'place') !== false) {
							if ($score < (CompareForm::$EXACT_MATCH_SCORE * CompareForm::$PLACE_BOOST) && strpos($sv1, ',') !== false && $sv1 == $sv2) {
								$score = CompareForm::$EXACT_MATCH_SCORE * CompareForm::$PLACE_BOOST;
                        $class= CompareForm::$COMPARE_MATCH_CLASS;
							}
							else if ($score < (CompareForm::$PARTIAL_MATCH_SCORE * CompareForm::$PLACE_BOOST) &&
										(strpos($sv1, $sv2) !== false || strpos($sv2, $sv1) !== false)) {
								$score = CompareForm::$PARTIAL_MATCH_SCORE * CompareForm::$PLACE_BOOST;
                        $class = CompareForm::$COMPARE_PARTIAL_CLASS;
							}
							else if ($score == 0) {
								$score = CompareForm::$NON_MATCH_SCORE * CompareForm::$PLACE_BOOST;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
							}
						}
						else if (strpos($label, 'Gender') !== false) {
							if ($sv1 != $sv2 && $sv1 != '?' && $sv2 != '?') {
								$score = CompareForm::$NON_MATCH_SCORE;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
							}
						}
						else if (strpos($label, 'ParentFamilyTitle') !== false || strpos($label, 'SpouseFamilyTitle') !== false) {
							$pos = mb_strrpos($sv1, '('); if ($pos !== false) $sv1 = trim(mb_substr($sv1, 0, $pos));
							$pos = mb_strrpos($sv2, '('); if ($pos !== false) $sv2 = trim(mb_substr($sv2, 0, $pos));
							if ($sv1 == $sv2) {
								$score = CompareForm::$EXACT_MATCH_SCORE;
                        $class= CompareForm::$COMPARE_MATCH_CLASS;
							}
                     else if ($score == 0 && strpos($label, 'ParentFamilyTitle') !== false) {
                        $score = CompareForm::$PARTIAL_NON_MATCH_SCORE;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
                     }
                     else if ($score == 0 && strpos($label, 'SpouseFamilyTitle') !== false) {
                        $score = CompareForm::$PARTIAL_NON_MATCH_SCORE * CompareForm::$SPOUSE_FAMILY_BOOST;
                        $class = CompareForm::$COMPARE_NOMATCH_CLASS;
                     }
						}
	   			}
	   		}
	   	}
   	}
   	return array($score, $class);
   }
   
   private static function readRelativeData($pfx, $elms, &$data, &$gedcomData, 
   														$saveParentTitle = false, $saveSpouseTitle = false, $includeNonCompareData = false, 
   														$timestamp = '') {
   	$found = false;
   	foreach ($elms as $elm) {
   		$relativeTitle = (string)$elm['title'];
   		if ($relativeTitle) {
	   		$found = true;
	   		if (GedcomUtil::isGedcomTitle($relativeTitle)) {
	   			$revid = 0;
	   		}
	   		else {
					$t = Title::newFromText($relativeTitle, NS_PERSON);
					$revid = $timestamp ? StructuredData::getRevidForTimestamp($t, $timestamp) : 0;
	   		}
	   		CompareForm::readPersonData($pfx, $relativeTitle, $data, $gedcomData, false, $saveParentTitle, $saveSpouseTitle, $includeNonCompareData, $revid, $timestamp);
   		}
   	}
   	return $found;
   }
   
   private static function getDatePlace($ef, $dateTag, $placeTag, &$data) {
		$date = (string)@$ef['date'];
		if ($date) $data[$dateTag][] = $date;
		$place = (string)@$ef['place'];
		if ($place) $data[$placeTag][] = $place;
   }
   
   private static function getOtherEvent($ef, $pfx, &$data) {
   	$desc = (string)$ef['description'];
   	$sources = (string)$ef['sources'];
   	$notes = (string)$ef['notes'];
   	$images = (string)$ef['images'];
   	$place = (string)$ef['place'];
		$pos = mb_strpos($place, '|');
		if ($pos !== false) {
			$place = mb_substr($place, $pos+1);
		}
   	$data[$pfx.'OtherEvents'][] = (string)$ef['type'].': '.(string)$ef['date'].', '.$place.
   											($desc ? " ($desc)" : '').
   											($sources ? ", sources: $sources" : '').
   											($notes ? ", notes: $notes" : '').
   											($images ? ", images: $images" : '');
	}
	
	private static function getSINContents($xml, $contents, $pfx, &$data) {
   	foreach ($xml->source_citation as $o) {
   		$recordName = (string)$o['record_name'];
   		$page = (string)$o['page'];
   		$quality = (string)$o['quality'];
   		$date = (string)$o['date'];
   		$notes = (string)$o['notes'];
   		$images = (string)$o['images'];
   		$text = ((string)$o['text'] . (string)$o);
   		$data[$pfx.'Sources'][] = (string)$o['id'].'. '.(string)$o['title'].
   											($recordName ? ", $recordName," : '').
   											($page ? ", page $page" : '').
   											($quality ? ", quality $quality" : '').
   											($date ? ", date $date" : '').
   											($notes ? ", notes: $notes" : '').
   											($images ? ", images: $images" : '').
   											($text ? ", $text" : '');
   	}
   	foreach ($xml->image as $o) {
   		$caption = (string)$o['caption'];
   		$data[$pfx.'Images'][] = (string)$o['id'].'. '.(string)$o['filename'].
   											($caption ? " ($caption)" : '');
   	}
   	foreach ($xml->note as $o) {
   		$text = ((string)$o['text'] . (string)$o);
   		$data[$pfx.'Notes'][] = (string)$o['id'].'. '.$text;
   	}
   	
   	if ($contents) {
			$data[$pfx.'Contents'][] = $contents;
   	}
	}
   
   public static function initPersonData($pfx, &$data) {
		$data[$pfx.'Redirect'] = false;
		$data[$pfx.'Nomerge'] = array();
		$data[$pfx.'Title'] = array();
		$data[$pfx.'Revid'] = array();
		$data[$pfx.'Given'] = array();
		$data[$pfx.'Surname'] = array();
		$data[$pfx.'Prefix'] = array();
		$data[$pfx.'Suffix'] = array();
		$data[$pfx.'Gender'] = array();
		$data[$pfx.'Birthdate'] = array();
		$data[$pfx.'Birthplace'] = array();
		$data[$pfx.'Christeningdate'] = array();
		$data[$pfx.'Christeningplace'] = array();
		$data[$pfx.'Deathdate'] = array();
		$data[$pfx.'Deathplace'] = array();
		$data[$pfx.'Burialdate'] = array();
		$data[$pfx.'Burialplace'] = array();
		$data[$pfx.'ParentFamilyTitle'] = array();
		$data[$pfx.'SpouseFamilyTitle'] = array();
		$data[$pfx.'OtherEvents'] = array();
		$data[$pfx.'Sources'] = array();
		$data[$pfx.'Images'] = array();
		$data[$pfx.'Notes'] = array();
		$data[$pfx.'Contents'] = array();
   }
   
   // if includeRelativeData is false, then we'll include the title if saveParentTitle or saveSpouseTitle are true
   // if includeRelativeData is true, pfx is assumed to be empty
   public static function readPersonData($pfx, $titleString, &$data, &$gedcomData, 
   													$includeRelativeData = false, $saveParentTitle = false, $saveSpouseTitle = false, $includeNonCompareData = false,
   													$revid = 0, $timestamp = '') {
   	$data[$pfx.'Title'][] = $titleString;
   	if (GedcomUtil::isGedcomTitle($titleString)) {
	   	$title = null;
			$data[$pfx.'Exists'] = true;
   		$xml = GedcomUtil::getGedcomXml($gedcomData, $titleString);
			$data[$pfx.'GedcomMatchTitle'] = (string)$xml['match'];
   		$contents = GedcomUtil::getGedcomContents($gedcomData, $titleString);
   	}
   	else {
			$title = Title::newFromText($titleString, NS_PERSON);
			$data[$pfx.'Exists'] = $title->exists();
	      $p = new Person($titleString);
	      $p->loadPage($revid);
	      if ($revid) $data[$pfx.'Revid'][] = $revid; // !!! this function can get called multiple times with the same pfx from SpecialReviewMerge
	      $xml = $p->getPageXml();
	      $contents = $p->getPageContents();
   	}
 		if (isset($xml)) {
			$found = true;
			// add match string
			$v = (string)@$xml->name['given']; if ($v) $data[$pfx.'Given'][] = $v;
			$v = (string)@$xml->name['surname']; if ($v) $data[$pfx.'Surname'][] = $v;
			$v = (string)@$xml->name['title_prefix']; if ($v) $data[$pfx.'Prefix'][] = $v;
			$v = (string)@$xml->name['title_suffix']; if ($v) $data[$pfx.'Suffix'][] = $v;
			if (!$pfx || $pfx == 'child') {
				$gender = (string)$xml->gender;
				$data[$pfx.'Gender'][] = $gender;
			}
			foreach ($xml->alt_name as $an) {
				$v = (string)@$an['given']; if ($v) $data[$pfx.'Given'][] = $v;
				$v = (string)@$an['surname']; if ($v) $data[$pfx.'Surname'][] = $v;
				$v = (string)@$an['title_prefix']; if ($v) $data[$pfx.'Prefix'][] = $v;
				$v = (string)@$an['title_suffix']; if ($v) $data[$pfx.'Suffix'][] = $v;
			}
			
			foreach ($xml->event_fact as $ef) {
				$type = (string)$ef['type'];
				if ($type == Person::$BIRTH_TAG || $type == PERSON::$ALT_BIRTH_TAG) {
					CompareForm::getDatePlace($ef, $pfx.'Birthdate', $pfx.'Birthplace', $data);
				}
				else if ($type == Person::$CHR_TAG || $type == PERSON::$ALT_CHR_TAG) {
					CompareForm::getDatePlace($ef, $pfx.'Christeningdate', $pfx.'Christeningplace', $data);
				}
				else if ($type == Person::$DEATH_TAG || $type == PERSON::$ALT_DEATH_TAG) {
					CompareForm::getDatePlace($ef, $pfx.'Deathdate', $pfx.'Deathplace', $data);
				}
				else if ($type == Person::$BUR_TAG || $type == PERSON::$ALT_BUR_TAG) {
					CompareForm::getDatePlace($ef, $pfx.'Burialdate', $pfx.'Burialplace', $data);
				}
				else if ($includeNonCompareData) {
					CompareForm::getOtherEvent($ef, $pfx, $data);
				}
			}
			
			if ($includeNonCompareData) {
				CompareForm::getSINContents($xml, $contents, $pfx, $data);
			}
	
			if ($includeRelativeData) {
				CompareForm::initPersonData('father', $data);
				CompareForm::initPersonData('mother', $data);
			}
			foreach ($xml->child_of_family as $f) {
				$familyTitle = (string)$f['title'];
				if ($familyTitle && $includeRelativeData) {
					$f = new Family($familyTitle);
					$familyRevid = $timestamp ? StructuredData::getRevidForTimestamp($f->getTitle(), $timestamp) : 0;
					$f->loadPage($familyRevid);
					$famXml = $f->getPageXml();
					$fatherFound = $motherFound = false;
					if (isset($famXml)) {
						$fatherFound = CompareForm::readRelativeData('father', $famXml->husband, $data, $gedcomData, false, false, $includeNonCompareData, $timestamp);
						$motherFound = CompareForm::readRelativeData('mother', $famXml->wife, $data, $gedcomData, false, false, $includeNonCompareData, $timestamp);
					}
					if (!$fatherFound || !$motherFound) {
						list($fg, $fs, $mg, $ms) = StructuredData::parseFamilyTitle($familyTitle);
						if (!$fatherFound) {
							if ($fg) $data['fatherGiven'][] = $fg;
							if ($fs) $data['fatherSurname'][] = $fs;
						}
						if (!$motherFound) {
							if ($mg) $data['motherGiven'][] = $mg;
							if ($ms) $data['motherSurname'][] = $ms;
						}
					}
				}
				else if ($saveParentTitle) {
					$data[$pfx.'ParentFamilyTitle'][] = $familyTitle;
				}
			}
	
			if ($includeRelativeData) {
				CompareForm::initPersonData('spouse', $data);
			}
			foreach ($xml->spouse_of_family as $f) {
				$familyTitle = (string)$f['title'];
				if ($familyTitle && $includeRelativeData) {
					if (GedcomUtil::isGedcomTitle($familyTitle)) {
						$famXml = GedcomUtil::getGedcomXml($gedcomData, $familyTitle);
					}
					else {
						$f = new Family($familyTitle);
						$familyRevid = $timestamp ? StructuredData::getRevidForTimestamp($f->getTitle(), $timestamp) : 0;
						$f->loadPage($familyRevid);
						$famXml = $f->getPageXml();
					}
					$spouseFound = false;
					if (isset($famXml)) {
						if ($gender == 'M') {
							$spouseFound = CompareForm::readRelativeData('spouse', $famXml->wife, $data, $gedcomData, false, false, $includeNonCompareData, $timestamp);
						}
						else {
							$spouseFound = CompareForm::readRelativeData('spouse', $famXml->husband, $data, $gedcomData, false, false, $includeNonCompareData, $timestamp);
						}
					}
					if (!$spouseFound) {
						list($fg, $fs, $mg, $ms) = StructuredData::parseFamilyTitle($familyTitle);
						if ($gender == 'M') {
							if ($mg) $data['spouseGiven'][] = $mg;
							if ($ms) $data['spouseSurname'][] = $ms;
						}
						else {
							if ($fg) $data['spouseGiven'][] = $fg;
							if ($fs) $data['spouseSuranme'][] = $fs;
						}
					}
				}
				else if ($saveSpouseTitle) {
					$data[$pfx.'SpouseFamilyTitle'][] = $familyTitle;
				}
			}
    	}
		// title is not set for gedcom pages
		else {
			if ($title && StructuredData::isRedirect($contents)) {
				$data[$pfx.'Redirect'] = true;
			}
			list($g, $s) = StructuredData::parsePersonTitle($titleString);
			if ($g) $data[$pfx.'Given'][] = $g;
			if ($s) $data[$pfx.'Surname'][] = $s;
    	}
  		$data[$pfx.'Nomerge'] = CompareForm::getNomergeTitleStrings($title);
  		if ($title) {
  			$data[$pfx.'Updatable'] = CompareForm::isUpdatable($title, $contents);
  		}
	}
   
   public static function initFamilyData(&$data) {
   	$data['Redirect'] = false;
   	$data['Nomerge'] = array();
   	$data['familyTitle'] = array();
		$data['Revid'] = array();
   	$data['Marriagedate'] = array();
   	$data['Marriageplace'] = array();
		$data['OtherEvents'] = array();
		$data['Sources'] = array();
		$data['Images'] = array();
		$data['Notes'] = array();
		$data['Contents'] = array();
   }
   
   public static function readFamilyData($titleString, &$data, &$gedcomData, 
   													$includeRelativeData = false, $includeNonCompareData = false,
   													$revid = 0, $timestamp = '') {
   	$children = array();
   	$data['familyTitle'][] = $titleString;
   	if (GedcomUtil::isGedcomTitle($titleString)) {
			$title = null;
   		$data['Exists'] = true;
			$xml = GedcomUtil::getGedcomXml($gedcomData, $titleString);
			$data['GedcomMatchTitle'] = (string)$xml['match'];
   		$contents = GedcomUtil::getGedcomContents($gedcomData, $titleString);
   	}
   	else {
			$title = Title::newFromText($titleString, NS_FAMILY);
			$data['Exists'] = $title->exists();
	   	$p = new Family($titleString);
	   	$p->loadPage($revid);
	      if ($revid) $data['Revid'][] = $revid; // for consistency with readPersonData
	   	$xml = $p->getPageXml();
	   	$contents = $p->getPageContents();
   	}
   	$husbandFound = $wifeFound = false;
   	if ($includeRelativeData) {
			CompareForm::initPersonData('husband', $data);
			CompareForm::initPersonData('wife', $data);
   	}
 		if (isset($xml)) {
			foreach ($xml->event_fact as $ef) {
				$type = (string)$ef['type'];
				if ($type == Family::$MARRIAGE_TAG || $type == Family::$ALT_MARRIAGE_TAG) {
					CompareForm::getDatePlace($ef, 'Marriagedate', 'Marriageplace', $data);
				}
				else if ($includeNonCompareData) {
					CompareForm::getOtherEvent($ef, '', $data);
				}
			}
			
			if ($includeNonCompareData) {
				CompareForm::getSINContents($xml, $contents, '', $data);
			}

			if ($includeRelativeData) {
				$husbandFound = CompareForm::readRelativeData('husband', $xml->husband, $data, $gedcomData, true, false, $includeNonCompareData, $timestamp);
				$wifeFound = CompareForm::readRelativeData('wife', $xml->wife, $data, $gedcomData, true, false, $includeNonCompareData, $timestamp);
				$i = 0;
				foreach ($xml->child as $c) {
					$childTitle = (string)$c['title'];
					if ($childTitle) {
						$children[$i] = array();
						CompareForm::initPersonData('child', $children[$i]);
						if (GedcomUtil::isGedcomTitle($childTitle)) {
							$childRevid = 0;
						}
						else {
							$t = Title::newFromText($childTitle, NS_PERSON);
							$childRevid = $timestamp ? StructuredData::getRevidForTimestamp($t, $timestamp) : 0;
						}
						CompareForm::readPersonData('child', $childTitle, $children[$i], $gedcomData, false, false, true, $includeNonCompareData, $childRevid);
						$i++;
					}
				}
			}
			else {
				$data['husbandTitle'] = array();
				foreach ($xml->husband as $m) {
					$data['husbandTitle'][] = (string)$m['title'];
				}
				$data['wifeTitle'] = array();
				foreach ($xml->wife as $m) {
					$data['wifeTitle'][] = (string)$m['title'];
				}
				$data['childTitle'] = array();
				foreach ($xml->child as $m) {
					$data['childTitle'][] = (string)$m['title'];
				}
			}
 		}
 		// title is not set for gedcom pages
		else if ($title && StructuredData::isRedirect($contents)) {
			$data['Redirect'] = true;
		}
		if ($includeRelativeData && (!$husbandFound || !$wifeFound)) {
			list($fg, $fs, $mg, $ms) = StructuredData::parseFamilyTitle($titleString);
			if (!$husbandFound) {
				if ($fg) $data['husbandGiven'][] = $fg;
				if ($fs) $data['husbandSurname'][] = $fs;
			}
			if (!$wifeFound) {
				if ($mg) $data['wifeGiven'][] = $mg;
				if ($ms) $data['wifeSurname'][] = $ms;
			}
 		}
 		$data['Nomerge'] = CompareForm::getNomergeTitleStrings($title);
 		if ($title) {
  			$data['Updatable'] = CompareForm::isUpdatable($title, $contents);
 		}
   	return $children;
	}

	public static function getCompareScoreClass($firstTitle, $label, $baseValues, $compareValues) {
		$class = self::$COMPARE_DEFAULT_CLASS;
		$score = 0;
		if ($label == 'Title' || $label == 'familyTitle' || $label == 'husbandTitle' || $label == 'wifeTitle' || $label == 'childTitle') {
			$class = self::$COMPARE_PAGE_CLASS;
		}
		else if ($firstTitle) {
			$class = self::$COMPARE_DEFAULT_CLASS;
		}
		else {
			list ($score, $class) = CompareForm::scoreMatch($label, $baseValues, $compareValues);
		}
		return array($score, $class);
	}
	
	public function __construct($namespace = '', $compareTitles = array(), $gedcomData = null, $gedcomDataString = '') {
		$this->namespace = $namespace;
		$this->compareTitles = $compareTitles;
		$this->gedcomData = $gedcomData;
		$this->gedcomDataString = $gedcomDataString;
		$this->gedcomTab = '';
		$this->gedcomKey = '';
	}

   public function readQueryParms($par) {
      global $wgRequest;

      $this->namespace = $wgRequest->getVal('ns');
      $this->gedcomTab = $wgRequest->getVal('gedcomtab');
      $this->gedcomKey = $wgRequest->getVal('gedcomkey');
      $compare = $wgRequest->getVal('compare');
      if ($compare) {
      	$this->compareTitles = explode('|', $compare);
      }
      else {
	      for ($i = 0; $i <= 200; $i++) {
	      	$t = $wgRequest->getVal('compare_'.$i);
	      	if ($t) {
	      		if (strpos($t, 'Person:') === 0 || strpos($t, 'Family:') === 0) {
	      			$t = substr($t, 7);
					}
	      		$this->compareTitles[] = $t;
	      	}
	      }
      }
      $isGedcomTitle = false;
      foreach ($this->compareTitles as &$t) { // fix up titles just in case
      	$t = str_replace('_', ' ', $t);
     		if (GedcomUtil::isGedcomTitle($t)) $isGedcomTitle = true;
      }
      
      if ($isGedcomTitle) {
      	$this->gedcomDataString = GedcomUtil::getGedcomDataString();
   		$this->gedcomData = GedcomUtil::getGedcomDataMap($this->gedcomDataString);
		}
      
      return ($this->namespace == 'Person' || $this->namespace == 'Family') && count($this->compareTitles) > 0 && (!$isGedcomTitle || $this->gedcomDataString);
   }
   
   public function getNamespace() {
   	return $this->namespace;
   }
   
   public function isGedcom() {
   	return strlen($this->gedcomDataString) > 0;
   }
   
   private function setMergeTargets() {
   	$lowPageColumn = -1;
   	$lowPageId = 0;
   	for ($i = 0; $i < count($this->compareTitles); $i++) {
   		$titleString = $this->compareTitles[$i];
   		if (GedcomUtil::isGedcomTitle($titleString)) {
   			$lowPageColumn = $i;
   			$lowPageId = 0;
   		}
   		else {
	   		$title = Title::newFromText($titleString, $this->namespace == 'Family' ? NS_FAMILY : NS_PERSON);
	   		$pageId = ($title ? $title->getArticleID() : 0);
	   		if ($pageId > 0 && ($lowPageColumn == -1 || $pageId < $lowPageId)) {
	   			$lowPageColumn = $i;
	   			$lowPageId = $pageId;
	   		}
   		}
   	}
   	if ($lowPageColumn < 0) {
   		// run away! - no valid pages
   		return;
   	}
   	$reorder = array();
  		$reorder[] = $this->compareTitles[$lowPageColumn];
  		for ($i = count($this->compareTitles) - 1; $i >= 0; $i--) {
  			if ($i != $lowPageColumn) {
   			$reorder[] = $this->compareTitles[$i];
   		}
   	}
   	$this->compareTitles =& $reorder;
   }
   
   private function standardizeRelative(&$relative) {
   	$stdRelative = array();
   	foreach ($relative as $label => $values) {
			$stdRelative[$label] = CompareForm::standardizeValues($label, $values);
   	}
   	return $stdRelative;
   }
   
   private function standardizeChildren(&$children) {
   	$stdChildren = array();
   	foreach ($children as $child) {
   		$stdChildren[] =& $this->standardizeRelative($child);
   	}
   	return $stdChildren;
   }
   
   private function matchChild(&$stdChild1, &$stdChild2) {
   	$score = 0;
   	foreach ($stdChild1 as $label => $values1) {
   		$values2 = @$stdChild2[$label];
   		if ($label != 'childSurname' && $label != 'childGedcomMatchTitle') {
            list ($s, $class) = CompareForm::scoreMatch($label, $values1, $values2);
            $score += $s;
            if ($s) {
              //wfDebug("COMPARE CHILD label=$label values1=".join(',',$values1)." values2=".join(',',$values2)." score=$s\n");
            }
   		}
   	}
   	// already matched
		if ((@$stdChild1['childGedcomMatchTitle'][0] && $stdChild1['childGedcomMatchTitle'][0] == $stdChild2['childTitle'][0]) ||
		    (@$stdChild2['childGedcomMatchTitle'][0] && $stdChild2['childGedcomMatchTitle'][0] == $stdChild1['childTitle'][0])) {
			$score += 10;
		}
   	return $score;
   }
   
   private function orderChildren(&$stdChildren1, &$children, $nextEmptyRow) {
   	$orderedChildren = array();
   	
   	// standardize incoming children
   	$stdChildren2 =& $this->standardizeChildren($children);
   	
   	// construct match matrix
   	$scores = array();
   	$matched1 = array();
   	$matched2 = array();
   	$maxScore = 0;
   	for ($i = 0; $i < count($stdChildren1); $i++) {
   		$scores[$i] = array();
   		for ($j = 0; $j < count($stdChildren2); $j++) {
   			$score = $this->matchChild($stdChildren1[$i], $stdChildren2[$j]);
				//wfDebug("COMPARE CHILD row={$stdChildren1[$i]['childTitle'][0]} col={$stdChildren2[$j]['childTitle'][0]} score=$score\n");
   			if ($score > $maxScore) {
   				$maxScore = $score;
   			}
				$scores[$i][$j] = $score;
   		}
   	}

   	// match each child with the best match for it
   	while ($maxScore > 0) {
   		$newMaxScore = 0;
   		for ($i = 0; $i < count($stdChildren1); $i++) {
   			if (!@$matched1[$i]) {
   				for ($j = 0; $j < count($children); $j++) {
   					if (!@$matched2[$j] && !@$matched1[$i]) {
   						$score = $scores[$i][$j];
   						if ($score == $maxScore) {
   							$orderedChildren[$i] = $children[$j];
								//wfDebug("COMPARE CHILD row=$i score=$score title={$children[$j]['childTitle'][0]}\n");
   							$matched1[$i] = true;
   							$matched2[$j] = true;
   						}
   						else if ($score > $newMaxScore) {
   							$newMaxScore = $score;
   						}
   					}
   				}
   			}
   		}
   		$maxScore = $newMaxScore;
   	}
   	
   	// fill in empty slots so count(children) works as expected
   	for ($j = 0; $j < $nextEmptyRow; $j++) {
   		if (!isset($orderedChildren[$j])) {
   			$orderedChildren[$j] = array();
   		}
   	}
   	
   	// append unmatched children to end of array
   	for ($j = 0; $j < count($children); $j++) {
   		if (!@$matched2[$j]) {
   			$orderedChildren[$nextEmptyRow] = $children[$j];
   			$nextEmptyRow++;
   		}
   	}
   	
   	return $orderedChildren;
   }
   
   private function getNomergeTitleMatches(&$data, $t, $c, $pfx, $glue) {
   	$result = array();
   	if ($c < 0) {  // compare person/family, husband/wife
if (!is_array($data[$t][$pfx.'Nomerge'])) error_log("nomerge not array: t=$t c=$c pfx=$pfx");
   		foreach ($data[$t][$pfx.'Nomerge'] as $nomergeTitle) {
	   		foreach ($this->compareTitles as $title) {
	   			if ((!$pfx && $nomergeTitle == $title) ||
	   				 ($pfx &&  in_array($nomergeTitle, $data[$title][$pfx.'Title']))) {
   					$result[] = htmlspecialchars($nomergeTitle);
	   			}
	   		}
	   	}
   	}
   	else { // children
			foreach ($data[$t][$c][$pfx.'Nomerge'] as $nomergeTitle) {
				foreach ($this->compareTitles as $title) {
					foreach ($data[$title] as $child) {
						if ($nomergeTitle == @$child[$pfx.'Title'][0]) {
							$result[] = htmlspecialchars($nomergeTitle);
						}
					}
				}
			}
		}
		return join($glue, $result);
	}
	
	private function getMergeChildSelectOptions($childNumber, $maxChildren) {
		$mergeChildSelectOptions = array();
		for($c = 0; $c < $maxChildren; $c++) {
			$c1 = $c+1;
			$mergeChildSelectOptions[$c == $childNumber ? "Child $c1" : "Match with child $c1"] = $c1;
		}
		$mergeChildSelectOptions["Not a match"] = 0;
		return $mergeChildSelectOptions;
	}
	
	public function readCompareData() {
		$compareData = array();
		$compareChildren = array();
		$maxChildren = 0;

		$stdChildren = null;
		$firstTitle = true;
		foreach ($this->compareTitles as $t) {
			if ($this->namespace == 'Person') {
				$compareData[$t] = array();
				CompareForm::initPersonData('', $compareData[$t]);
				CompareForm::readPersonData('', $t, $compareData[$t], $this->gedcomData, true);
			}
			else {
				$compareData[$t] = array();
				CompareForm::initFamilyData($compareData[$t]);
				$children = CompareForm::readFamilyData($t, $compareData[$t], $this->gedcomData, true);
				if ($firstTitle) {
					$compareChildren[$t] = $children;
					$stdChildren =& $this->standardizeChildren($children);
					$firstTitle = false;
				}
				else {
					$compareChildren[$t] =& $this->orderChildren($stdChildren, $children, $maxChildren);
				}
				if (count($compareChildren[$t]) > $maxChildren) {
					$maxChildren = count($compareChildren[$t]);
				}
			}
		}
		return array($compareData, $compareChildren, $maxChildren);
	}
	
	public function getDataLabels() {
		if ($this->namespace == 'Person') {
			return CompareForm::$PERSON_COMPARE_LABELS;
		}
		else {
			return CompareForm::$FAMILY_COMPARE_LABELS;
		}
	}
	
	public function scoreCompareData($dataLabels, $compareData) {
		$compareClass = array();
		$husbandScores = array();
		$wifeScores = array();
		$totalScores = array();
		for ($i = 0; $i < count($this->compareTitles); $i++) {
			$husbandScores[$i] = $wifeScores[$i] = $totalScores[$i] = 0;
		}
		foreach ($dataLabels as $label) {
			$compareClass[$label] = array();
			for ($i = 0; $i < count($this->compareTitles); $i++) {
				$t = $this->compareTitles[$i];
				$stdValues =& CompareForm::standardizeValues($label, @$compareData[$t][$label]);
				if ($i == 0) {
					$baseStdValues = $stdValues;
				}
				list($score, $compareClass[$label][$i]) = CompareForm::getCompareScoreClass($i == 0, $label, $baseStdValues, $stdValues);
				$totalScores[$i] += $score;
				if (strpos($label, 'husband') !== false) {
					//wfDebug("COMPARE HUSBAND $label $score\n");
					$husbandScores[$i] += $score;
				}
				else if (strpos($label, 'wife') !== false) {
					//wfDebug("COMPARE WIFE $label $score\n");
					$wifeScores[$i] += $score;
				}
			}
		}
		if ($this->namespace == 'Family') {
			$gedcomId = $this->gedcomDataString ? GedcomUtil::getGedcomId($this->gedcomDataString) : '';
			$titleString = $compareData[$this->compareTitles[0]]['familyTitle'][0];
			for ($i = 1; $i < count($this->compareTitles); $i++) {
				wfDebug("COMPARE title1=$titleString title2=".$compareData[$this->compareTitles[$i]]['familyTitle'][0].
							" gedcomID=$gedcomId totalScore={$totalScores[$i]}  husbandScore={$husbandScores[$i]}  wifeScore={$wifeScores[$i]}\n");
			}
		}
		return array($compareClass, $husbandScores, $wifeScores, $totalScores);
	}
   
	/**
	 * Return HTML for displaying search results
	 * @return string HTML
	 */
	public function getCompareResults() {
		global $wgUser;
		
		$this->setMergeTargets();
		
		list($compareData, $compareChildren, $maxChildren) = $this->readCompareData();
		
		$dataLabels = $this->getDataLabels();
		
		list($compareClass, $husbandScores, $wifeScores, $totalScores) = $this->scoreCompareData($dataLabels, $compareData);
		
		$output = '';
		$semiProtected = false;
		$isLoggedIn = $wgUser->isLoggedIn();
		if (!$isLoggedIn) {
			$output .= '<p><font color=red>You need to sign in to match and merge pages</font></p>';
		}
		$gedcomExtra = ($this->gedcomDataString ? '?gedcomtab='.htmlspecialchars($this->gedcomTab).'&gedcomkey='.htmlspecialchars($this->gedcomKey) : '');
		$output .= "<form id=\"compare\" name=\"compare\" action=\"/wiki/Special:Merge$gedcomExtra\" method=\"post\">"
					."<input type=\"hidden\" id=\"ns\" name=\"ns\" value=\"{$this->namespace}\">"
					."<input type=\"hidden\" id=\"maxpages\" name=\"maxpages\" value=\"".count($this->compareTitles)."\">"
					.($this->namespace == 'Family' ? "<input id=\"maxchildren\" type=\"hidden\" name=\"maxchildren\" value=\"$maxChildren\">" : '')
//					.($this->gedcomDataString ? "<input type=\"hidden\" id=\"gedcomdata\" name=\"gedcomdata\" value=\"".htmlspecialchars($this->gedcomDataString)."\">" : '')
					.'<table border="0" cellspacing="0" cellpadding="4">';
					
		foreach ($dataLabels as $label) {
			$labelClass = CompareForm::getLabelClass($label);
			if ($labelClass == self::$COMPARE_ULC_CLASS) {
				$output .= CompareForm::insertEmptyRow(count($this->compareTitles)+1);
			}
			$found = !in_array($label, self::$OPTIONAL_LABELS);
			if (!$found) {
				foreach ($this->compareTitles as $t) {
					if (is_array(@$compareData[$t][$label]) && count($compareData[$t][$label]) > 0) {
						$found = true;
						break;
					}
				}
			}
			if ($found) {
				$output .= "<tr><td class=\"$labelClass\">" . CompareForm::formatLabel($label) ."</td>";
				// show target left if gedcom; right otherwise
				$i = ($this->gedcomDataString ? 0 : count($this->compareTitles)-1);
				while (($this->gedcomDataString && $i < count($this->compareTitles)) || 
						 (!$this->gedcomDataString && $i >= 0)) {
					$t = $this->compareTitles[$i];
					$class = $compareClass[$label][$i];
					$output .= "<td class=\"$class\">";
					$first = true;
					if (is_array(@$compareData[$t][$label])) {
						foreach ($compareData[$t][$label] as $value) {
							if (!$first) $output .= '<br>';
							$output .= CompareForm::formatValue($label, $value);
							$first = false;
						}
					}
					if ($first) $output .= '&nbsp;';
					if ($class == self::$COMPARE_PAGE_CLASS) {
						$titlesCnt = count(@$compareData[$t][$label]);
						$baseTitlesCnt = count(@$compareData[$this->compareTitles[0]][$label]);
						if ($titlesCnt > 0) {
							$value = htmlspecialchars($compareData[$t][$label][0]);
							if ($label == 'husbandTitle') {
								$relative = 'husband';
								$relativeAbbrev = 'h';
								$score = $husbandScores[$i];
							}
							else if ($label == 'wifeTitle') {
								$relative = 'wife';
								$relativeAbbrev = 'w';
								$score = $wifeScores[$i];
							}
							else {
								$relative = '';
								$relativeAbbrev = '';
								$score = 0;
							}
							if (!$compareData[$t]['Exists']) { // base person/family not found
								if (!$relative) {
									$output .= '<br>Not found';
								}
							}
							else if ($compareData[$t]['Redirect']) { // base person/family has been merged
								if (!$relative) {
									$output .= '<br>Already merged';
								}
							}
							else if ($compareData[$t][$relative.'Redirect']) {  // husband/wife has been merged
								$output .= '<br>Already merged';
							}
//							else if ($i == 0) {
//								$output .= "<input type=\"hidden\" name=\"m{$relativeAbbrev}_0\" value=\"$value\">";
//							}
							else if ($i == 0 && $this->gedcomDataString) {
								if (@$compareData[$t][$relative.'GedcomMatchTitle']) {
							   	$skin =& $wgUser->getSkin();
							   	$gedcomMatchTitle = $compareData[$t][$relative.'GedcomMatchTitle'];
							   	$temp = Title::newFromText($gedcomMatchTitle, StructuredData::endsWith($label, 'familyTitle', true) ? NS_FAMILY : NS_PERSON);
									$title = $skin->makeLinkObj($temp, htmlspecialchars($gedcomMatchTitle));
									$output .= "<br>Matched with $title";
								}
								$output .= "<input type=\"hidden\" id=\"m{$relativeAbbrev}_$i\" name=\"m{$relativeAbbrev}_$i\" value=\"$value\">";
							}
							else if ($titlesCnt == 1 && $baseTitlesCnt <= 1) {
//								if ($i == 0 || $baseTitlesCnt == 0 || $compareData[$this->compareTitles[0]][$label][0] != $compareData[$t][$label][0]) { // don't allow merge if same title
									if ($i == 0) {
										$extra = " checked";
									}
									else if ($relative) {
                              // always check spouses
                              $extra = 'disabled checked';
										//if ($score > self::$SPOUSE_MATCH_THRESHOLD) $extra .= ' checked';
									}
									else {
										$extra = '';
									}
									if ($this->namespace == 'Family' && !$relative) {
										$extra .= " onClick=\"compareClick($i)\"";
									}
									$output .= "<br><input id=\"m{$relativeAbbrev}_$i\" type=\"checkbox\" name=\"m{$relativeAbbrev}_$i\" value=\"$value\" $extra>&nbsp;Match";
//								}
							}
							else {
								$output .= "<br>Multiple spouses - merge after merging family";
							}
							$nomergeTitles = $this->getNomergeTitleMatches($compareData, $t, -1, $relative, '<br>');
							if ($nomergeTitles) {
								$output .= '<br><b>Do not merge with</b><br>'.$nomergeTitles;
							}
							if ((!$this->gedcomDataString || $i > 0) && !$compareData[$t][$relative.'Updatable']) {
								$output .= "<br><font color=\"red\">Semi-protected</font> (see below)";
								$semiProtected = true;
							}
						}
					}
					$output .= '</td>';
					$i += ($this->gedcomDataString ? 1 : -1);	
				}
				$output .= '</tr>';
			}
		}
		if ($this->namespace == 'Family') {
			for ($c = 0; $c < $maxChildren; $c++) {
				foreach (CompareForm::$CHILD_COMPARE_LABELS as $label) {
					$labelClass = CompareForm::getLabelClass($label);
					if ($labelClass == self::$COMPARE_ULC_CLASS) {
						$output .= CompareForm::insertEmptyRow(count($this->compareTitles)+1);
					}
					$found = !in_array($label, self::$OPTIONAL_LABELS);
					if (!$found) {
						foreach ($this->compareTitles as $t) {
							if (is_array(@$compareChildren[$t][$c][$label]) && count($compareChildren[$t][$c][$label]) > 0) {
								$found = true;
								break;
							}
						}
					}
					if ($found) {
						$output .= "<tr><td class=\"$labelClass\">" . CompareForm::formatLabel($label, $c+1) . "</td>";
						$baseStdValues =& CompareForm::standardizeValues($label, @$compareChildren[$this->compareTitles[0]][$c][$label]);
						$i = ($this->gedcomDataString ? 0 : count($this->compareTitles)-1);
						while (($this->gedcomDataString && $i < count($this->compareTitles)) || 
								 (!$this->gedcomDataString && $i >= 0)) {
							$t = $this->compareTitles[$i];
							if ($i == 0) {
								$stdValues = $baseStdValues;
							}
							else {
								$stdValues =& CompareForm::standardizeValues($label, @$compareChildren[$t][$c][$label]);
							}
							list($score, $class) = CompareForm::getCompareScoreClass($i == 0, $label, $baseStdValues, $stdValues);
							$output .= "<td class=\"$class\">";
							$children =& $compareChildren[$t];
							$first = true;
							if (count($children) > $c) {
								if (is_array(@$children[$c][$label])) {
									foreach ($children[$c][$label] as $value) {
										if (!$first) $output .= '<br>';
										$output .= CompareForm::formatValue($label, $value);
										$first = false;
									}
								}
							}
							if ($first) $output .= '&nbsp;';
							if ($class == self::$COMPARE_PAGE_CLASS) {
								if (count(@$compareChildren[$t][$c][$label]) == 1) {
									$value = htmlspecialchars($compareChildren[$t][$c][$label][0]);
									$output .= "<input type=\"hidden\" id=\"mc_{$i}_$c\" name=\"mc_{$i}_$c\" value=\"$value\">";
									if ($compareData[$t]['Redirect']) {
										// family has already been merged
									}
									else if ($compareChildren[$t][$c]['childRedirect']) {  // child has already been merged
										$output .= '<br>Already merged';
									}
									else if ($i == 0 && $this->gedcomDataString) {
										if (@$compareChildren[$t][$c]['childGedcomMatchTitle']) {
									   	$skin =& $wgUser->getSkin();
									   	$gedcomMatchTitle = $compareChildren[$t][$c]['childGedcomMatchTitle'];
									   	$temp = Title::newFromText($gedcomMatchTitle, StructuredData::endsWith($label, 'familyTitle', true) ? NS_FAMILY : NS_PERSON);
											$title = $skin->makeLinkObj($temp, htmlspecialchars($gedcomMatchTitle));
											$output .= "<br>Matched with $title";
										}
										$c1 = $c+1;
										$output .= "<input type=\"hidden\" id=\"mcr_0_$c\" name=\"mcr_0_$c\" value=\"$c1\">";
									}
									else {
//									else if (@$compareChildren[$this->compareTitles[0]][$c][$label][0] != $compareChildren[$t][$c][$label][0]) { // don't allow merge if same title
//										$mergeChild = (count(@$compareChildren[$this->compareTitles[0]][$c][$label]) == 1 ? $c+1 : 0);
										$mergeChild = $c+1;
										$extra = ($i == 0 ? '' : 'disabled');
										$mergeChildSelectOptions = $this->getMergeChildSelectOptions($c, $maxChildren);
										$output .= '<br>'.StructuredData::addSelectToHtml(0, "mcr_{$i}_$c", $mergeChildSelectOptions, $mergeChild, $extra, false);
									}
									$nomergeTitles = $this->getNomergeTitleMatches($compareChildren, $t, $c, 'child', '<br>');
									if ($nomergeTitles) {
										$output .= '<br><b>Do not merge with</b><br>'.$nomergeTitles;
									}
									if ((!$this->gedcomDataString || $i > 0) && !$compareChildren[$t][$c]['childUpdatable']) {
										$output .= "<br><font color=\"red\">Semi-protected</font> (see below)";
										$semiProtected = true;
									}
								}
							}
							$output .= '</td>';
							$i += ($this->gedcomDataString ? 1 : -1);	
						}
						$output .= '</tr>';
					}
				}
			}
		}
		if ($this->gedcomDataString) {
			$mergeLabel = 'Prepare to update';
			$mergeFunction = 'doGedcomPrepareToMerge()';
			$notMatchFunction = 'doGedcomNotMatch()';
			$notMatchTitle = 'GEDCOM family does not match any of the families shown';
		}
		else {
			$mergeLabel = 'Prepare to merge';
			$mergeFunction = 'doPrepareToMerge()';
			$notMatchFunction = 'doNotMatch()';
			$notMatchTitle = 'Notify others not to merge the selected '.($this->namespace == 'Family' ? 'families' : 'people');
		}
		$mergeTitle = 'Prepare to combine the selected '.($this->namespace == 'Family' ? 'families' : 'people');
		$output .= '<tr><td align=right colspan="'.(count($this->compareTitles)+1).'"><input type="hidden" name="formAction">'.
					($this->gedcomDataString ? '<input type="button" title="Match people in your GEDCOM to people in the selected family" value="Match" onClick="doGedcomMatch()"/> &nbsp; ' : '').
					($this->gedcomDataString ? '' : "<input type=\"button\" title=\"$mergeTitle\" value=\"$mergeLabel\" onClick=\"$mergeFunction\"/> &nbsp; ").
					//($this->gedcomDataString ? '<input type="button" title="Match the selected family and all matching related families automatically" value="Match Related" onClick="doGedcomMatchAll()"/> &nbsp; ' : '').
					"<input type=\"button\" title=\"$notMatchTitle\" value=\"Not a match\" onClick=\"$notMatchFunction\"/>";
					'</td></tr></table></form>';
		if ($semiProtected) {
			$output .= CompareForm::getSemiprotectedMessage(CompareForm::isTrustedMerger($wgUser, $this->gedcomDataString));
		}
		return $output;
	}

	/**
	 * Return HTML for displaying search results
	 * @return string HTML
	 */
	public function getCompareForm() {
		$inputFields = '';
		for ($i = 0; $i < 5; $i++) {
			$inputFields .= "<tr><td><input type=\"text\" size=\"50\" name=\"compare_$i\"/></td></tr>";
		}

		return <<<END
<H3>Compare Families</H3>
<p>Enter the titles of Family pages to compare:</p>
<form name="compareFamilies" action="/wiki/Special:Compare" method="post">
<input type="hidden" name="ns" value="Family"/>
<table>
$inputFields
<tr><td align="right"><input type="submit" name="formAction" value="Compare"/></td></tr>
</table>
</form>
<H2>Or</H2>
<H3>Compare People</H3>
<p>Enter the titles of Person pages to compare:</p>
<form name="comparePeople" action="/wiki/Special:Compare" method="post">
<input type="hidden" name="ns" value="Person"/>
<table>
$inputFields
<tr><td align="right"><input type="submit" name="formAction" value="Compare"/></td></tr>
</table>
</form>
END;
	}
}
?>
