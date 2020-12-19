<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/familytree/FamilyTreeUtil.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfUserExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfUserExtensionSetup() {
	global $wgHooks;
	global $wgParser;

	# Register hooks for edit UI, request handling, and save features
	$wgHooks['ArticleEditShow'][] = 'renderUserEditFields';
	$wgHooks['ImportEditFormDataComplete'][] = 'importUserEditData';
	$wgHooks['EditFilter'][] = 'validateUserPage';

	# register the extension with the WikiText parser
	$wgParser->setHook('user', 'renderUserData');
}

/**
 * Callback function for converting user page data to HTML output
 */
function renderUserData( $input, $argv, $parser) {
   global $wgUser;

   if ($parser->getTitle()->getNamespace() == NS_SPECIAL && $parser->getTitle()->getText() == 'MyRelate') {
      $titleString = $wgUser->getName();
   }
   else {
      $titleString = $parser->getTitle()->getText();
   }
   $user = new UserPage($titleString);
   return $user->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderUserEditFields( &$editPage ) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_USER) {
        $user = new UserPage($editPage->mTitle->getText());
        $user->renderEditFields($editPage);
    }
    return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importUserEditData( &$editPage, &$request ) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_USER) {
        $user = new UserPage($editPage->mTitle->getText());
        $user->importEditData($editPage, $request);
    }
    return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateUserPage($editPage, $textBox1, $section, &$hookError) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_USER) {
        $user = new UserPage($editPage->mTitle->getText());
        $user->validate($textBox1, $section, $hookError);
    }
    return true;
}

/**
 * Handles user pages and those below the user page
 */
class UserPage extends StructuredData {
	protected $userName;
	protected $isSubpage;
	private $correctedPlaceTitles;

    /**
     * Construct a new name object
     * @param string $tagName the name of the tag for this namespace
     */
    public function __construct($titleString) {
		parent::__construct('user', $titleString, NS_USER);
		$this->userName = $titleString;
		$this->isSubpage = false;
		$pos = strpos($this->userName, '/');
		if ($pos !== false) {
			$this->userName = substr($this->userName, 0, $pos);
			$this->isSubpage = true;
		}
	}

    protected function formatPersonalResearchPageLink($value, $dummy) {
       $t = Title::newFromText($value, NS_USER);
       if ($t) {
         return '[['.$t->getPrefixedText().'|'.$t->getText().']]';
       }
       else {
          return '';
       }
    }

	protected function formatResearchLinks($value, $dummy) {
		$surname = StructuredData::standardizeNameCase((string)$value['surname'], false);

		$fields = explode('|',(string)$value['place']);
      $titleText = trim($fields[0]);
      $t = Title::newFromText($titleText, NS_PLACE);
      if ($t) {
         $t = StructuredData::getRedirectToTitle($t);
         $titleText = $t->getText();
      }

      $place = $titleText;
      $surnames = array();
      $places = array();
      if ($surname) $surnames[] = $surname;
      if ($place) $places[] = $place;
      $categories = StructuredData::addCategories($surnames, $places, true, '', NS_MAIN, true);
		$ret = '';
		if ($surname && $place) {
			$ret .= "<dl><dt>[[$surname in $place]]<dd><small>Categories: $categories</small></dl>";
		}
		else if ($surname) {
			$ret .= "<dl><dt>[[Surname:$surname]]<dd><small>Category: $categories</small></dl>";
		}
		else if ($place) {
//			if ($surname) {
//				$ret .= "\n|- style=\"border-top-style:hidden\"\n|&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
//			}
			$ret .= "<dl><dt>[[Place:$place]]<dd><small>Category: $categories</small></dl>";
		}
		return $ret;
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

    private function getPersonalResearchPages() {
       $prps = array();

       $dbr =& wfGetDB( DB_SLAVE );
       $t = Title::makeTitle(NS_USER, $this->userName);
		 $sql = 'select page_title from page where page_namespace='.NS_USER .
		          ' and page_title like '.$dbr->addQuotes($t->getDBkey().'/%');
		 $res = $dbr->query( $sql, 'getPRPs' );
		 if ($res !== false) {
		   while ($row = $dbr->fetchObject($res)) {
		      $prps[] = $row->page_title;
     	   }
			$dbr->freeResult($res);
		 }
       return $prps;
    }

   protected function getLV($label, $values) {
      $result = '';
      if (is_string($values)) {
         if ($values) {
            $result = '<dd>'.$values;
         }
      }
      else {
         foreach ($values as $v) {
            $result .= '<dd>'.(string)$v;
         }
      }
      if ($result) $result = '<dt class="wr-infobox-label">'.$label.$result;
      return $result;
   }

	/**
	 * Create wiki text from xml property
	 */
    protected function toWikiText($parser) {
       global $wrHostName;

		 $result = '';
		 if (!$this->isSubpage) {
		    $familyTrees = FamilyTreeUtil::getFamilyTrees($this->userName, true);
          $personalResearchPages = $this->getPersonalResearchPages();
		 }
		 else {
		    $familyTrees = array();
          $personalResearchPages = array();
		 }
//		 $result = '{| id="structuredData" border=1 class="floatright"    cellpadding=4 cellspacing=0 width=40%'."\n";
//		 $result = "<div class=\"infobox-header\">Profile</div>\n{|\n";
//		 if (count($familyTrees) > 0) {
//		    $result .= $this->addRowsToTable('Family Tree(s)', $familyTrees, 'formatFamilyTree', null);
//		 }
		 if (isset($this->xml)) {
//   		 if ($this->isSubpage) {
//               $result .= $this->addValuesToTableDL('Surname(s):', $this->xml->surname, 'formatAsLink', 'Surname');
//               $result .= $this->addValuesToTableDL('Place(s):', $this->xml->place, 'formatAsLink', 'Place');
//               if ((string)$this->xml->from_year || (string)$this->xml->to_year) {
//                   $result .= $this->addValueToTableDL("Year range:", "{$this->xml->from_year} - {$this->xml->to_year}");
//               }
//   		 }
//   		 else {
//               $result .= $this->addRowsToTable('Researching', $this->xml->researching, 'formatResearchLinks', null);
//               $result .= $this->addRowsToTable('User Pages', $this->getPersonalResearchPages(), 'formatPersonalResearchPageLink', null);
//   		 }
          $values = array();
          foreach ($this->xml->researching as $researching) {
             $surname = StructuredData::standardizeNameCase((string)$researching['surname'], false);
//             $fields = explode('|',(string)$researching['place']);
//             $place = trim($fields[0]);
//             $surnames = array();
//             $places = array();
//             if ($surname) $surnames[] = $surname;
//             if ($place) $places[] = $place;
//             $categories = StructuredData::addCategories($surnames, $places, true, '', NS_MAIN, true);
//             $ret = '';
//             if ($surname && $place) {
//                $ret = "<dl><dt>[[$surname in $place]]<dd>Categories: $categories</dl>";
//             }
//             else if ($surname) {
//                $ret = "<dl><dt>[[Surname:$surname]]<dd>Category: $categories</dl>";
//             }
//             else if ($place) {
//                $ret = "<dl><dt>[[Place:$place]]<dd>Category: $categories</dl>";
//             }
//             if ($ret) $values[] = $ret;
             if ($surname) {
                $values[] = '[http://'.$wrHostName.'/wiki/Special:Search?ns=User&s='.$surname.' '.$surname.']';
             }
          }
          $researching = $this->getLV('Users Researching', array_unique($values));

          $values = array();
          foreach ($this->xml->surname as $surname) {
             $values[] = $this->formatAsLink((string)$surname, 'Surname');
          }
          $surnames = $this->getLV('Surnames', $values);

          $values = array();
          foreach ($this->xml->place as $place) {
             $values[] = $this->formatAsLink((string)$place, 'Place');
          }
          $places = $this->getLV('Places', $values);

          $fromYear = (string)$this->xml->from_year;
          $toYear = (string)$this->xml->to_year;
          $yearRange = '';
          if ($fromYear || $toYear) {
             $yearRange = $this->getLV('Year range', "$fromYear - $toYear");
          }

          $values = array();
          $n=0;
          foreach ($familyTrees as $familyTree) {
            $values[$n] = '<dl><dt>'.$familyTree['name'].' <span class="plainlinks">'
                   . ' ([http://'.$wrHostName.'/wiki/Special:Search?k='. urlencode('+Tree:"'.$this->userName.'/'.$familyTree['name'].'"') . " search])";  // link renamed Dec 2020
//                   . ' ([http://'.$wrHostName.'/fte/index.php?userName='. urlencode($this->userName) . '&treeName=' . urlencode($familyTree['name']) . " launch FTE])";  removed Dec 2020
            if ( $familyTree['count'] > 0 ) { // Add explore link, but only for trees with at least one page (added Sep 2020 by Janet Bjorndahl)
              $firstTitle = SpecialTrees::getExploreFirstTitle($this->userName, $familyTree['name']);
              $values[$n] .= ' ([http://'.$wrHostName.'/w/index.php?title=' . $firstTitle->getPrefixedURL(). '&mode=explore&user=' . $this->userName. '&tree='
                      . str_replace(' ','+',$familyTree['name']) . '&liststart=0&listrows=20 explore])';   // bug fixed Dec 2020
              $values[$n] .= ' ([http://'.$wrHostName.'/w/index.php?title=Special:CopyTree&user=' . $this->userName
                      . '&name=' . str_replace(' ','+',$familyTree['name']) . ' copy])';   // added Dec 2020 
            }
            $values[$n++] .= "</span><dd>pages: {$familyTree['count']}</dl>";     // renamed from people to pages Dec 2020 by Janet Bjorndahl
          }
          $familyTrees = $this->getLV('Family Trees', $values);

          $values = array();
          foreach ($personalResearchPages as $personalResearchPage) {
             $t = Title::newFromText($personalResearchPage, NS_USER);
             if ($t) {
               $values[] = '[['.$t->getPrefixedText().'|'.$t->getText().']]';
             }
          }
          $personalResearchPages = $this->getLV('User pages', $values);

          $heading = ($surnames || $places || $yearRange ? 'Page Covers' : '');
          $result = "<div class=\"wr-infobox wr-infobox-userpage\"><div class=\"wr-infobox-heading\">$heading</div><dl>{$surnames}{$places}{$yearRange}{$familyTrees}{$researching}{$personalResearchPages}</dl></div>";
		 }
//		 $result .= $this->showWatchers();
//		 $result .= "|}\n";
		 if (isset($this->xml)) {
   		 if ($this->isSubpage) {
   		   $result .= StructuredData::addCategories($this->xml->surname, $this->xml->place);
   		 }
   		 else {
   		    foreach ($this->xml->researching as $researching) {
   			    $surnames = array();
     		       $places = array();
   		       $surnames[] = (string)$researching['surname'];
   		       $places[] = (string)$researching['place'];
   			    $result .= StructuredData::addCategories($surnames, $places);
   		    }
   		 }
		 }
		 return $result;
    }

    /**
     * Create edit fields from xml property
     */
    protected function toEditFields(&$textbox1) {
		global $wgOut, $wgScriptPath;

		// add javascript functions
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.10.js\"></script>");
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/userpage.4.js\"></script>");

   	$tm = new TipManager();

		$invalidStyle = ' style="background-color:#fdd;"';
      $result = '';
		if ($this->isSubpage) {
			$surnames = '';
			$places = '';
			$fromYear = '';
			$toYear = '';
			$fromYearStyle = '';
			$toYearStyle = '';
	      if (isset($this->xml)) {
				$fromYear = htmlspecialchars((string)$this->xml->from_year);
				$toYear = htmlspecialchars((string)$this->xml->to_year);
	         foreach ($this->xml->surname as $surname) {
	            $surnames .= htmlspecialchars($surname) . "\n";
	         }
	         foreach ($this->xml->place as $place) {
	            $places.= htmlspecialchars($place) . "\n";
	         }
		      if (!StructuredData::isValidYear($fromYear) || !StructuredData::isValidYear($toYear)) {
		         if (!StructuredData::isValidYear($fromYear)) {
		            $fromYearStyle = $invalidStyle;
		         }
		         if (!StructuredData::isValidYear($toYear)) {
		            $toYearStyle = $invalidStyle;
		         }
		         $result .= "<p><font color=red>The year range is not valid</font></p>";
		      }
	      }
	      $result .= "<br><label for=\"surnames\">Surnames (one per line):</label><br><textarea tabindex=\"1\" name=\"surnames\" rows=\"3\" cols=\"60\">$surnames</textarea>";
	      $result .= "<br><label for=\"places\">Places (one per line):</label><br><textarea class=\"place_input\" tabindex=\"1\" name=\"places\" rows=\"3\" cols=\"60\">$places</textarea>";
	      $result .= "<table><tr>";
	      $result .= "<td align=left>Year range:</td><td align=left><input tabindex=\"1\" name=\"fromYear\" value=\"$fromYear\" size=\"5\"$fromYearStyle/>";
	      $result .=           "&nbsp;&nbsp;-&nbsp;<input tabindex=\"1\" name=\"toYear\" value=\"$toYear\" size=\"5\"$toYearStyle/>";
			$result .= "</td></tr></table>Text:<br>";
		}
		else {
   		$display = 'none';
   		$rows = '';
   		$i = 0;
   		if (isset($this->xml)) {
      		foreach ($this->xml->researching as $researching) {
   			   $display = 'block';
   			   $rows .= "<tr><td><input type=\"hidden\" name=\"researching_id$i\" value=\"". ($i+1) ."\"/>"
   			      ."<input tabindex=\"1\" type=\"text\" size=20 name=\"researching_surname$i\" value=\"".htmlspecialchars((string)$researching['surname'])."\"/></td>"
   			      ."<td><input class=\"place_input\" tabindex=\"1\" type=\"text\" size=30 name=\"researching_place$i\" value=\"".htmlspecialchars((string)$researching['place'])."\"/></td></tr>\n";
      			$i++;
      		}
   		}
   		$result .= '<h2>Surnames and/or places you are researching' . $tm->addMsgTip('UserResearching') . '</h2>'
   		   ."<table id=\"researching_table\" border=0 width=\"500px\" style=\"display:$display\"><tr><th>Surname</th><th>Place (state, province, or country)</th></tr>"
   		   ."$rows</table><a href=\"javascript:void(0)\" onClick=\"addResearchInput(); return preventDefaultAction(event);\">Add Surname and/or Place</a>";

			$result .= '<br><br><h2>Text' . $tm->addMsgTip('UserText') . '</h2>';

			$result .= $tm->getTipTexts();
		}
        return $result;
    }

    protected function formatSurname($value) {
		$value = StructuredData::standardizeNameCase(trim($value), false);
		$escapedValue =& StructuredData::escapeXml($value);
      return "<surname>$escapedValue</surname>";
    }

    protected function formatPlace($value) {
		$correctedPlace = @$this->correctedPlaceTitles[$value];
		if ($correctedPlace) {
			$value = strcasecmp($value,$correctedPlace) == 0 ? $correctedPlace : $correctedPlace . '|' . $value;
		}
		$escapedValue =& StructuredData::escapeXml($value);
      return "<place>$escapedValue</place>";
    }

    /**
     * Return xml elements from data in request
     * @param unknown $request
     */
    protected function fromEditFields($request) {
   	$this->correctedPlaceTitles = PlaceSearcher::correctPlaceTitlesMultiLine($request->getVal('places', ''));
      $result = '';
		if ($this->isSubpage) {
	        $result .= $this->addMultiLineFieldToXml($request->getVal('surnames', ''), 'formatSurname');
	        $result .= $this->addMultiLineFieldToXml($request->getVal('places', ''), 'formatPlace');
			$result .= $this->addSingleLineFieldToXml($request->getVal('fromYear', ''), 'from_year');
			$result .= $this->addSingleLineFieldToXml($request->getVal('toYear', ''), 'to_year');
		}
		else {
			$placesToCorrect = array();
   		for ($i = 0; $request->getVal("researching_id$i"); $i++) {
   		   $place = trim($request->getVal("researching_place$i"));
				if ($place && mb_strpos($place, '|') === false) {
   		   	$placesToCorrect[] = $place;
				}
   		}
   		$correctedPlaceTitles = PlaceSearcher::correctPlaceTitles($placesToCorrect);
   		for ($i = 0; $request->getVal("researching_id$i"); $i++) {
   		   $surname = StructuredData::standardizeNameCase(trim($request->getVal("researching_surname$i")), false);
   		   $place = trim($request->getVal("researching_place$i"));
   		   if ($surname || $place) {
         		// if you change this, you must also change FamilyTreeAjaxFunctions
         		if ($place) {
						$correctedPlace = @$correctedPlaceTitles[$place];
						if ($correctedPlace) {
							$place = $correctedPlace;
						}
         		}
      		   $result .= $this->addMultiAttrFieldToXml(array('surname' => $surname, 'place' => $place), 'researching');
   		   }
   		}
		}
        return $result;
    }

	/**
     * Return true if xml property is valid
     */
    protected function validateData() {
		$result = true;
        if (isset($this->xml)) {
			foreach ($this->xml->personal_research_page as $page) {
		        if (!StructuredData::isValidPageTitle((string)$page)) {
					$result = false;
		        }
			}
        }
		return $result;
    }
}
?>
