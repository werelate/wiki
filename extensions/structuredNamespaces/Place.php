<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");
require_once("$IP/extensions/structuredNamespaces/SpecialPlaceMap.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfPlaceExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfPlaceExtensionSetup() {
	global $wgHooks;
	global $wgParser;

	# Register hooks for edit UI, request handling, and save features
	$wgHooks['ArticleEditShow'][] = 'renderPlaceEditFields';
	$wgHooks['ImportEditFormDataComplete'][] = 'importPlaceEditData';
	$wgHooks['EditFilter'][] = 'validatePlace';
	$wgHooks['ArticleSave'][] = 'propagatePlaceEdit';
   $wgHooks['TitleMoveComplete'][] = 'propagatePlaceMove';
	$wgHooks['ArticleDeleteComplete'][] = 'propagatePlaceDelete';
	$wgHooks['ArticleUndeleteComplete'][] = 'propagatePlaceUndelete';
	$wgHooks['ArticleRollbackComplete'][] = 'propagatePlaceRollback';

	# register the extension with the WikiText parser
	$wgParser->setHook('place', 'renderPlaceData');
}

/**
 * Callback function for converting resource to HTML output
 */
function renderPlaceData( $input, $argv, $parser) {
    $place = new Place($parser->getTitle()->getText());
    return $place->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderPlaceEditFields( &$editPage ) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_PLACE) {
        $place = new Place($editPage->mTitle->getText());
        $place->renderEditFields($editPage, true);
    }
    return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importPlaceEditData( &$editPage, &$request ) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_PLACE) {
        $place = new Place($editPage->mTitle->getText());
        $place->importEditData($editPage, $request);
    }
    return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validatePlace($editPage, $textBox1, $section, &$hookError) {
	$ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_PLACE) {
      $place = new Place($editPage->mTitle->getText());
      $place->validate($textBox1, $section, $hookError, true);
   }
   return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagatePlaceEdit(&$article, &$user, &$text, &$summary, $minor, $dummy1, $dummy2, &$flags) {
   $ns = $article->getTitle()->getNamespace();
   if ($ns == NS_PLACE) {
      $place = new Place($article->getTitle()->getText());
      $place->propagateEdit($text, $article);
	}
    return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagatePlaceMove(&$title, &$newTitle, &$user, $pageId, $redirPageId) {
   $ns = $title->getNamespace();
   if ($ns == NS_PLACE) {
      $place = new Place($title->getText());
      $place->propagateMove($newTitle);
	}
    return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagatePlaceDelete(&$article, &$user, $reason) {
   $ns = $article->getTitle()->getNamespace();
   if ($ns == NS_PLACE) {
      $place = new Place($article->getTitle()->getText());
      $place->propagateDelete($article);
	}
    return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagatePlaceUndelete(&$title, &$user) {
   $ns = $title->getNamespace();
   if ($ns == NS_PLACE) {
      $place = new Place($title->getText());
	   $revision = StructuredData::getRevision($title, false, true);
      $place->propagateUndelete($revision);
	}
    return true;
}

/**
 * Callback function to propagate rollback
 * @param Article article
 * @return bool must return true or other hooks don't get called
 */
function propagatePlaceRollback(&$article, &$user) {
	$ns = $article->getTitle()->getNamespace();
   if ($ns == NS_PLACE) {
		$place = new Place($article->getTitle()->getText());
		$place->propagateRollback($article);
	}
	return true;
}


/**
 * Handles places
 */
class Place extends StructuredData {
    const PROPAGATE_MESSAGE = 'Propagate changes to';
	protected $prefName; // from title
	protected $locatedIn; // from title

	public static function getPlaceCategory($titleString) {
		$level = Place::getPlaceLevel($titleString);
		$pieces = mb_split(",", $titleString);
		if ($level < count($pieces)) {
			$result = '';
			$start = count($pieces) - $level;
			for ($i = $start; $i < count($pieces); $i++) {
	         if ($result) {
	            $result .= ', ';
	         }
	         $result .= trim($pieces[$i]);
			}
			return $result;
		}
		else {
			return $titleString;
		}
	}
	
	private static function getPlaceLevel($titleString) {
		if (StructuredData::endsWith($titleString, ", United States") ||
			 StructuredData::endsWith($titleString, ", Preußen, Germany") ||
//			 StructuredData::endsWith($titleString, ", France") ||  // categorize at Dept (state) level
			 StructuredData::endsWith($titleString, ", Greece") ||
			 StructuredData::endsWith($titleString, ", Italy") ||
			 StructuredData::endsWith($titleString, ", Spain") ||
			 StructuredData::endsWith($titleString, ", Philippines") ||
			 StructuredData::endsWith($titleString, ", Scotland")
		) {
			return 3;
		}
		else if (StructuredData::endsWith($titleString, ", Anguilla") ||
					StructuredData::endsWith($titleString, ", American Samoa") ||
					StructuredData::endsWith($titleString, ", Antigua and Barbuda") ||
					StructuredData::endsWith($titleString, ", Aruba") ||
					StructuredData::endsWith($titleString, ", Bahrain") ||
					StructuredData::endsWith($titleString, ", Bermuda") ||
					StructuredData::endsWith($titleString, ", Bhutan") ||
					StructuredData::endsWith($titleString, ", British Virgin Islands") ||
					StructuredData::endsWith($titleString, ", Cayman Islands") ||
					StructuredData::endsWith($titleString, ", Croatia") ||
					StructuredData::endsWith($titleString, ", Estonia") ||
					StructuredData::endsWith($titleString, ", Falkland Islands") ||
					StructuredData::endsWith($titleString, ", French Polynesia") ||
					StructuredData::endsWith($titleString, ", Gibraltar") ||
					StructuredData::endsWith($titleString, ", Guam") ||
					StructuredData::endsWith($titleString, ", Guernsey") ||
					StructuredData::endsWith($titleString, ", Haiti") ||
					StructuredData::endsWith($titleString, ", Isle of Man") ||
					StructuredData::endsWith($titleString, ", Jersey") ||
					StructuredData::endsWith($titleString, ", Kiribati") ||
					StructuredData::endsWith($titleString, ", Latvia") ||
					StructuredData::endsWith($titleString, ", Lithuania") ||
					StructuredData::endsWith($titleString, ", Moldova") ||
					StructuredData::endsWith($titleString, ", Maldives") ||
					StructuredData::endsWith($titleString, ", Malta") ||
					StructuredData::endsWith($titleString, ", Marshall Islands") ||
					StructuredData::endsWith($titleString, ", Mauritius") ||
					StructuredData::endsWith($titleString, ", Mayotte") ||
					StructuredData::endsWith($titleString, ", Monaco") ||
					StructuredData::endsWith($titleString, ", Nauru") ||
					StructuredData::endsWith($titleString, ", Netherlands Antilles") ||
					StructuredData::endsWith($titleString, ", New Caledonia") ||
					StructuredData::endsWith($titleString, ", Niue") ||
					StructuredData::endsWith($titleString, ", Norfolk Island") ||
					StructuredData::endsWith($titleString, ", Northern Cyprus") ||
					StructuredData::endsWith($titleString, ", Northern Mariana Islands") ||
					StructuredData::endsWith($titleString, ", Palau") ||
					StructuredData::endsWith($titleString, ", Palestinian Territories") ||
					StructuredData::endsWith($titleString, ", Pitcairn Islands") ||
					StructuredData::endsWith($titleString, ", Réunion") ||
					StructuredData::endsWith($titleString, ", Saint Helena") ||
					StructuredData::endsWith($titleString, ", Saint Kitts and Nevis") ||
					StructuredData::endsWith($titleString, ", Saint Lucia") ||
					StructuredData::endsWith($titleString, ", Saint Pierre et Miquelon") ||
					StructuredData::endsWith($titleString, ", Samoa") ||
					StructuredData::endsWith($titleString, ", San Marino") ||
					StructuredData::endsWith($titleString, ", Seychelles") ||
					StructuredData::endsWith($titleString, ", Singapore") ||
					StructuredData::endsWith($titleString, ", Slovenia") ||
					StructuredData::endsWith($titleString, ", São Tomé and Príncipe") ||
					StructuredData::endsWith($titleString, ", Tokelau") ||
					StructuredData::endsWith($titleString, ", Trinidad and Tobago") ||
					StructuredData::endsWith($titleString, ", Turks and Caicos Islands") ||
					StructuredData::endsWith($titleString, ", Tuvalu") ||
					StructuredData::endsWith($titleString, ", Uganda") ||
					StructuredData::endsWith($titleString, ", United States Virgin Islands") ||
					StructuredData::endsWith($titleString, ", Wallis and Futuna") ||
					StructuredData::endsWith($titleString, ", Yemen")
		) {
			return 1;
		}
		else {
			return 2;
		}
	}
	
    /**
     * Return true if the latitude is valid or empty
     */
	public static function isValidLatitude($latitude) {
		return empty($latitude) || (is_numeric($latitude) && ($latitude >= -90) && ($latitude <= 90));
	}

    /**
     * Return true if the longitude is valid or empty
     */
	public static function isValidLongitude($longitude) {
		return empty($longitude) || (is_numeric($longitude) && ($longitude >= -180) && ($longitude <= 180));
	}
	
	// expects lat/lng after htmlspecialchars has been applied
	private static function displayLatLng($l, $isLat) {
		if (substr($l, 0, 1) == '-') {
			return substr($l, 1).($isLat ? ' S' : ' W');
		}
		else {
			return $l.($isLat ? ' N' : ' E');
		}
	}

    /**
     * Returns an error if there is something wrong with one of the previous parents in the array
     * Returns null if everything is fine
     * @return string
     */
	private static function isInvalidAlsoLocatedIn($values) {
     foreach ($values as $value) {
     		$title = Title::newFromText((string)$value['place'], NS_PLACE);
			if (!$title || !$title->exists()) {
             return "Also located in place: {$value['place']} not found";
         }
         if (!StructuredData::isValidYear((string)$value['from_year'], true) || !StructuredData::isValidYear((string)$value['to_year'], true)) {
             return "Also located in year range not valid: enter year, ?, or present";
         }
     }
     return null;
	}

	/**
	 * Returns true if the element is equivalent to a member of the list
	 *
	 * @param unknown_type $elm should be a string
	 * @param unknown_type $xmllist should be an xml list of elements with 'place' attribute
	 * @return boolean
	 */
	private static function isInAlsoLocatedIn($elm, $xmllist) {
	   if ($elm && isset($xmllist)) {
	      foreach ($xmllist as $x) {
	         if ($elm == (string)$x['place']) {
	            return true;
	         }
	      }
	   }
	   return false;
	}
	
	private static function getPrefNameLocatedIn($titleString) {
		$pieces = mb_split(",", $titleString, 2);
		$prefName = trim($pieces[0]);
		if (count($pieces) > 1) {
			$locatedIn = trim($pieces[1]);
		}
		else {
			$locatedIn = "";
		}
		return array($prefName, $locatedIn);
	}

    /**
     * Construct a new place object
     */
    public function __construct($titleString) {
		parent::__construct('place', $titleString, NS_PLACE);
		$arr = Place::getPrefNameLocatedIn($titleString);
		$this->prefName = $arr[0];
		$this->locatedIn = $arr[1];
		$this->mapData = '';
	}

    protected function formatAltName($value, $dummy) {
        $source = '';
        if ((string)$value['source']) {
            $source = " &nbsp;&nbsp;&nbsp; (''{$value['source']}'')";
        }
        return "{$value['name']}$source";
    }

    protected function formatAlsoLocatedIn($value, $dummy) {
        $yearRange = '';
        if ((string)$value['from_year'] || (string)$value['to_year']) {
            $yearRange = " &nbsp;&nbsp;&nbsp; ({$value['from_year']} - {$value['to_year']})";
        }
        return "[[Place:{$value['place']}|{$value['place']}]]$yearRange";
    }

    protected function formatSeeAlso($value, $dummy) {
        $reason = '';
        if ((string)$value['reason']) {
            $reason = " &nbsp;&nbsp;&nbsp; ({$value['reason']})";
        }
        return "[[Place:{$value['place']}|{$value['place']}]]$reason";
    }

    protected function formatContainedPlace($value, $dummy) {
        $fields = explode('|', $value);
        if (@$fields[1] || @$fields[2]) {
        	 $dateRange = " ( " . (@$fields[1] ? $fields[1] : '') . " - " . (@$fields[2] ? $fields[2] : "") . " )";
        }
        else {
        	$dateRange = "";
        }
        $pos = mb_strpos($fields[0], ',');
        if ($pos === false) {
           $name = $fields[0];
        }
        else {
           $name = mb_substr($fields[0], 0, $pos);
        }
        return "[[Place:{$fields[0]}|$name]]" . $dateRange;
    }

    private function addContainedPlaces($values) {
       $places = array();
       foreach ($values as $value) {
            $types = mb_split(",", mb_strtolower((string)$value['type']));
            foreach ($types as $type) {
            	$type = trim($type);
	            if (!$type) {
	                $type = 'Unknown';
	            }
	            else if ($type == 'city' || $type == 'town' || $type == 'community' || $type == 'village' || $type == 'city or town' || $type == 'town or village') {
	            	$type = 'inhabited place';
	            }
	            $containedPlaceTypes[$type][] = "{$value['place']}|{$value['from_year']}|{$value['to_year']}";
            }
//          $places[] = "{$value['place']}|{$value['previous']}";
       }
        ksort($containedPlaceTypes, SORT_STRING);
//       sort($places, SORT_STRING);
       $result = array();
       foreach ($containedPlaceTypes as $containedPlaceType => $places) {
            sort($places, SORT_STRING);
            $containedPlaceType = mb_strtoupper(mb_substr($containedPlaceType, 0, 1)) . mb_substr($containedPlaceType, 1);
            $result[] = "<dt>$containedPlaceType";
            foreach ($places as $place) {
               $fields = explode('|', $place);
               if (@$fields[1] || @$fields[2]) {
                   $dateRange = " ( " . (@$fields[1] ? $fields[1] : '') . " - " . (@$fields[2] ? $fields[2] : "") . " )";
               }
               else {
                  $dateRange = "";
               }
               $pos = mb_strpos($fields[0], ',');
               if ($pos === false) {
                  $name = $fields[0];
               }
               else {
                  $name = mb_substr($fields[0], 0, $pos);
               }
               $result[] = "<dd>[[Place:{$fields[0]}|$name]]$dateRange";
            }
        }
//        $result = $this->addValuesToTable(null, $places, 'formatContainedPlace', null, true);
       if (count($result) == 0) return '';
       $result = join('', $result);
       return "<div class=\"wr-infobox wr-infobox-containedplaces\"><div class=\"wr-infobox-heading\">Contained Places</div><dl>$result</dl></div>";
    }
    
    protected function getHeaderHTML() {
       $result = '';
       if ($this->mapData) {
          $result .= SpecialPlaceMap::getMapScripts(0, $this->mapData);
       }
       return $result;
    }
    
    private function addMap() {
    	global $wrHostName; 
    	
	   return '<div id="placemap"></div>' .
				 '<div class="plainlinks">[http://'.$wrHostName.'/wiki/Special:PlaceMap?pagetitle=' . wfUrlEncode($this->titleString) . ' Larger map]</div>';
    }

   protected function getLV($label, $values) {
      $result = '';
      if (is_string($values)) {
         if ($values) {
            $result = "<tr><td class=\"wr-infobox-label\">$label</td><td colspan=\"2\">$values</td></tr>";
         }
      }
      else {
         foreach ($values as $v) {
            if (is_string($v)) {
               $val = "<td colspan=\"2\">$v</td>";
            }
            else {
               $val = "<td>{$v[0]}</td><td class=\"wr-infobox-note\">{$v[1]}</td>";
            }
            if ($label) {
               $lab = "<td class=\"wr-infobox-label\">$label</td>";
            }
            else {
               $lab = "<td></td>";
            }
            $label = '';
            $result .= "<tr>$lab$val</tr>";
         }
      }
      return $result;
   }

	/**
	 * Create wiki text from xml property
	 */
    protected function toWikiText($parser) {
        if (isset($this->xml)) {
           $name = $this->getLV('Name', "<span class=\"wr-infobox-title\">{$this->prefName}</span>");
           $values = array();
           foreach ($this->xml->alternate_name as $altName) {
              $source = '';
              if ((string)$altName['source']) {
                  $source = 'source: '.(string)$altName['source'];
              }
              $values[] = array($altName['name'],$source);
           }
           $altNames = $this->getLV('Alt names', $values);
           $type = $this->getLV('Type', (string)$this->xml->type);
           $coordinates = '';
           $lat = (string)$this->xml->latitude;
           $lng = (string)$this->xml->longitude;
           if ($lat && $lng) {
               if ($lat >= 0) {
                 $ns = 'N';
               }
               else {
                 $lat = $lat * -1.0;
                 $ns = 'S';
               }
               if ($lng >= 0) {
                 $ew = 'E';
               }
               else {
                 $lng = $lng * -1.0;
                 $ew = 'W';
               }
               $coordinates = $this->getLV('Coordinates', "$lat&deg;$ns $lng&deg;$ew");
           }
           $fromYear = (string)$this->xml->from_year;
           $toYear = (string)$this->xml->to_year;
           $yearRange = '';
           if ($fromYear || $toYear) {
              $yearRange = " &nbsp;&nbsp;&nbsp; ($fromYear - $toYear)";
           }
           if ($this->locatedIn) {
              $locatedIn = $this->getLV('Located in', "[[Place:{$this->locatedIn}|{$this->locatedIn}]]$yearRange");
           }
           else {
              $locatedIn = '';
           }
           $values = array();
           foreach ($this->xml->also_located_in as $ali) {
              $yearRange = '';
              if ((string)$ali['from_year'] || (string)$ali['to_year']) {
                  $yearRange = "({$ali['from_year']} - {$ali['to_year']})";
              }
              $place = (string)$ali['place'];
              $values[] = "[[Place:$place|$place]] &nbsp;&nbsp;&nbsp; $yearRange";
           }
           $alsoLocatedIn = $this->getLV('Also located in', $values);
           $values = array();
           foreach ($this->xml->see_also as $seeAlso) {
              $reason = '';
              if ((string)$seeAlso['reason']) {
                  $reason = (string)$seeAlso['reason'];
              }
              $place = (string)$seeAlso['place'];
              $values[] = array("[[Place:$place|$place]]", $reason);
           }
           $seeAlso = $this->getLV('See also', $values);
           $map = '';
           $this->mapData = SpecialPlaceMap::getContainedPlaceMapData($this->xml);
			   if ($this->mapData) {
					$showContainedPlacesMap = true;
				}
				else {
					$showContainedPlacesMap = false;
					$this->mapData = SpecialPlaceMap::getSelfMapData($this->title, $this->xml);
					if ($this->mapData) {
						$map = $this->addMap();
					}
				}
            $containedPlaces = '';
            if (isset($this->xml->contained_place)) {
//                $result .= "|-\n| align=\"center\" | Contained Places\n";
//                $result .= "|-\n!Contained Places\n";
                if ($showContainedPlacesMap) {
					 	$map = $this->addMap();
                }
                $containedPlaces = $this->addContainedPlaces($this->xml->contained_place);
            }
        }
		$placeCat = Place::getPlaceCategory($this->titleString);

      $result = <<<END
<div class="wr-infobox wr-infobox-place clearfix">
<div class="wr-infobox-map">$map</div><table class="wr-infobox-content">
$name
$altNames
$type
$coordinates
$locatedIn
$alsoLocatedIn
$seeAlso
</table></div>
<wr_ad></wr_ad>
$containedPlaces
END;
		$result .= "[[Category:$placeCat" . ($placeCat == $this->titleString ? "|*" : "") . "]]";
		return $result;
   }
    
   public static function getPageText() {
   	return "<place>\n</place>\n";
   }

   public static function readPlaceTypes() {
      $msg = wfMsgForContent('Place_types');
      $lines = explode( "\n", $msg );
      $types = array();
      foreach ( $lines as $line ) {
         $line = trim($line);
         if ( strpos( $line, '*' ) === 0 ) {
            $line = trim(substr($line, 1));
            if ($line) {
               $types[] = $line;
            }
         }
      }
      return $types;
   }

   public static function capitalizeType($type) {
      $types = explode(',', $type);
      for ($i = 0; $i < count($types); $i++) {
         $type = trim($types[$i]);
         $types[$i] = mb_strtoupper(mb_substr($type,0,1)).mb_strtolower(mb_substr($type,1));
      }
      return join(', ', $types);
   }

   public static function isValidType($type, $validTypes) {
      $types = explode(',', $type);
      foreach ($types as $type) {
         if (!in_array(trim($type), $validTypes)) {
            return false;
         }
      }
      return true;
   }

    /**
     * Create edit fields from xml property
     */
   protected function toEditFields(&$textbox1) {
		global $wgOut, $wgScriptPath, $wgUser, $wgRequest, $wrAdminUserName;

      $result = '';
      $target = $wgRequest->getVal('target');

      // get place types
      $placeTypes = Place::readPlaceTypes();
      $placeTypesBuf = 'var wrPlaceTypes=["';
      $placeTypesBuf .= join('","',$placeTypes);
      $placeTypesBuf .= '"];';

		// add javascript functions
      $wgOut->addScript("<script type=\"text/javascript\">$placeTypesBuf</script>");
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/place.1.js\"></script>");
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.9.js\"></script>");
//      if ($target == 'gedcom'&& $target != 'AddPage') {
//         $result .= "<p><font color=red>Add any additional information you have about this place".
//                     ($target == 'gedcom' ? ' and save the page' : ', save the page, then close this window').
//                    ".</font></p>";
//      }

		$alternateNames = '';
		$alsoLocatedIn = '';
		$placeType = '';
		$latitude = '';
		$longitude = '';
		$fromYear = '';
		$toYear = '';
		$seeAlso = '';
		$invalidStyle = ' style="background-color:#fdd;"';
		$latitudeStyle = '';
		$longitudeStyle = '';
      $placeTypeStyle = '';
		$fromYearStyle = '';
		$toYearStyle = '';
		$alsoLocatedInStyle = '';
      if (isset($this->xml)) {
			$placeType = htmlspecialchars(Place::capitalizeType((string)$this->xml->type));
			$latitude = htmlspecialchars((string)$this->xml->latitude);
			$longitude = htmlspecialchars((string)$this->xml->longitude);
			$fromYear = htmlspecialchars((string)$this->xml->from_year);
			$toYear = htmlspecialchars((string)$this->xml->to_year);
         foreach ($this->xml->alternate_name as $alternateName) {
             $alternateNames .= htmlspecialchars($alternateName['name']) . '|' . htmlspecialchars($alternateName['source']) . "\n";
         }
         foreach ($this->xml->also_located_in as $pli) {
             $alsoLocatedIn .= htmlspecialchars($pli['place']) . '|' . htmlspecialchars($pli['from_year']) . '|' . htmlspecialchars($pli['to_year']) . "\n";
         }
         foreach ($this->xml->see_also as $sa) {
             $seeAlso .= htmlspecialchars($sa['place']) . '|' . htmlspecialchars($sa['reason']) . "\n";
         }
      }
      if (!$this->isValidLocatedIn()) {
         $result .= "<p><font color=red>A place cannot be added with this title because it is not located within an existing place (country, state, district, etc).</font></p>";
      }
      if (!Place::isValidType($placeType, $placeTypes)) {
         $placeTypeStyle = $invalidStyle;
         $result .= "<p><font color=red>The place type must now be chosen from a drop-down list. The list will appear as you enter the type.</font></p>";
      }
      if (!Place::isValidLatitude($latitude)) {
         $latitudeStyle = $invalidStyle;
         $result .= "<p><font color=red>The latitude is not valid: if you're entering in DD MM' SS\" format, don't forget the spaces between Degrees, Minutes, and Seconds</font></p>";
      }
      if (!Place::isValidLongitude($longitude)) {
         $longitudeStyle = $invalidStyle;
         $result .= "<p><font color=red>The longitude is not valid: if you're entering in DDD MM' SS\" format, don't forget the spaces between Degrees, Minutes, and Seconds</font></p>";
      }
      if (StructuredData::isRedirect($textbox1)) {
         if ($this->getContainedPlacesElements()) {
            $sk = $wgUser->getSkin();
            $link = $sk->makeLink("User:$wrAdminUserName", "$wrAdminUserName");
            $result .= "<p><font color=red>Places with contained places can't be redirected; (rename contained places first or leave a message for $link)</font></p>";
         }
      }
      else {
        if (!StructuredData::isEmptyOrExists($this->locatedIn, NS_PLACE)) {
            $result .= "<p><font color=red>The &quot;{$this->locatedIn}&quot; place page does not exist (you must create it before creating this page unless you're going to make this page a redirect)</font></p>";
        }
        if (mb_substr($this->titleString, 0, 1) == ',') {
            $result .= "<p><font color=red>The page title cannot start with a comma unless it's a redirect to another page</font></p>";
        }
      }
      if (isset($this->xml)) {
         $error = Place::isInvalidAlsoLocatedIn($this->xml->also_located_in);
         if ($error) {
               $alsoLocatedInStyle = $invalidStyle;
               $result .= "<p><font color=red>$error</font></p>";
         }
      }
//        if (!StructuredData::isValidYear($fromYear, true) || !StructuredData::isValidYear($toYear, true)) {
//            if (!StructuredData::isValidYear($fromYear, true)) {
//                $fromYearStyle = $invalidStyle;
//            }
//            if (!StructuredData::isValidYear($toYear, true)) {
//                $toYearStyle = $invalidStyle;
//            }
//            $result .= "<p><font color=red>The year range is not valid</font></p>";
//        }
      $result .= "<br><label for=\"input_alternateNames\">Alternate names (one per line): Name | Source</label><br><textarea id=\"input_alternateNames\" tabindex=\"1\" name=\"alternateNames\" rows=\"3\" cols=\"45\">$alternateNames</textarea>";
      $result .= "<table><tr>";
      $result .= "<td align=left>Type:</td><td align=left><input tabindex=\"1\" id=\"input_type\" name=\"type\" value=\"$placeType\" size=\"20\"$placeTypeStyle/></td>";
      $result .= "</tr><tr>";
      $latitude = Place::displayLatLng($latitude, true);
      $result .= "<td align=left>Latitude:</td><td align=left><input tabindex=\"1\" name=\"latitude\" value=\"$latitude\" size=\"20\"$latitudeStyle/>" .
                  " (enter <i>DD MM' SS\"</i> or <i>D.DD</i> followed by <i>N</i> or <i>S</i>)";
      $result .= "</tr><tr>";
      $longitude = Place::displayLatLng($longitude, false);
      $result .= "<td align=left>Longitude:</td><td align=left><input tabindex=\"1\" name=\"longitude\" value=\"$longitude\" size=\"20\"$longitudeStyle/>" .
                  " (enter <i>DDD MM' SS\"</i> or <i>D.DD</i> followed by <i>E</i> or <i>W</i>)";
      $result .= "</td>";
      $result .= "</tr><tr>";
      if ($this->locatedIn) {
         $sk = $wgUser->getSkin();
         $liLink = $sk->makeLink("Place:" . $this->locatedIn, $this->locatedIn);
         $liText = "Located in $liLink from";
      }
      else {
         $liText = "From";
      }
      $result .= "<td align=left colspan=2>$liText year: <input tabindex=\"1\" name=\"fromYear\" value=\"$fromYear\" size=\"5\"$fromYearStyle/>" .
                                                " to year: <input tabindex=\"1\" name=\"toYear\" value=\"$toYear\" size=\"5\"$toYearStyle/></td>";
		$result .= '</tr></table>';
      $result .= "<br><label for=\"input_alsoLocatedIn\">Also located in (one per line): Place | From year | To year</label><br><textarea id=\"input_alsoLocatedIn\" class=\"place_input\" tabindex=\"1\" name=\"alsoLocatedIn\" rows=\"3\" cols=\"45\"$alsoLocatedInStyle>$alsoLocatedIn</textarea>";
      $result .= "<br><label for=\"input_seeAlso\">See also (one per line): Place | Reason</label><br><textarea id=\"input_seeAlso\" class=\"place_input\" tabindex=\"1\" name=\"seeAlso\" rows=\"3\" cols=\"45\">$seeAlso</textarea>";
		$result .= '<br>Text:<br>';
      return $result;
   }

    protected function formatAlternateNameElement($value) {
      $fields = explode('|', $value, 2);
		$name = trim($fields[0]);
		$source = '';
      if (@$fields[1]) {
         $source = ' source="' . StructuredData::escapeXml(trim($fields[1])) . '"';
      }
		$escapedName =& StructuredData::escapeXml($name);
      return "<alternate_name name=\"$escapedName\"$source/>";
    }

    protected function formatAlsoLocatedInElement($value) {
      $fields = explode('|', $value);
		$place = trim($fields[0]);
		$fromYear = '';
		$toYear = '';
      if (@$fields[1]) {
         $fromYear = ' from_year="' . StructuredData::escapeXml(trim($fields[1])) . '"';
      }
      if (@$fields[2]) {
         $toYear = ' to_year="' . StructuredData::escapeXml(trim($fields[2])) . '"';
      }
		$escapedPlace =& StructuredData::escapeXml($place);
      return "<also_located_in place=\"$escapedPlace\"$fromYear$toYear/>";
    }

    protected function formatSeeAlsoElement($value) {
        $fields = explode('|', $value, 2);
		$place = trim($fields[0]);
		$reason = '';
        if (@$fields[1]) {
            $reason = ' reason="' . StructuredData::escapeXml(trim($fields[1])) . '"';
        }
		  $escapedPlace =& StructuredData::escapeXml($place);
        return "<see_also place=\"$escapedPlace\"$reason/>";
    }

    /**
     * Add contained places from the current version of the article as a string of XML elements
     */
    private function getContainedPlacesElements() {
    	$result = '';
    	$revision = StructuredData::getRevision($this->title);
    	if ($revision) {
    		// read the existing article for the contained places
    		$content =& $revision->getText();
    		$xml = StructuredData::getXml('place', $content);
    		if (isset($xml)) {
    			foreach ($xml->contained_place as $containedPlace) {
    				$place = (string)$containedPlace['place'];
    				$place = StructuredData::escapeXml(trim($place));
    				$type = (string)$containedPlace['type'];
    				$type = StructuredData::escapeXml(trim($type));
    				$lat = (string)$containedPlace['latitude'];
    				$lat = StructuredData::escapeXml(trim($lat));
    				$lng = (string)$containedPlace['longitude'];
    				$lng = StructuredData::escapeXml(trim($lng));
    				$fromYear = (string)$containedPlace['from_year'];
    				$fromYear = StructuredData::escapeXml(trim($fromYear));
    				$toYear = (string)$containedPlace['to_year'];
    				$toYear = StructuredData::escapeXml(trim($toYear));
    				$result .= "<contained_place place=\"$place\"" .
    				($lng ? " longitude=\"$lng\"" : '') .
    				($lat ? " latitude=\"$lat\"" : '') .
    				($type ? " type=\"$type\"" : '') .
    				($fromYear ? " from_year=\"$fromYear\"" : '') .
    				($toYear ? " to_year=\"$toYear\"" : '') .
    				"/>\n";
    			}
    		}
    	}
    	return $result;
    }

//	protected function cleanPrefName($name) {
//		return str_replace(",", "", $name);
//	}

    /**
     * Return xml elements from data in request
     * @param unknown $request
     */
    protected function fromEditFields($request) {
//		wfDebug("WR:FromEditFields\n");
        $result = '';
//        $result .= $this->addSingleLineFieldToXml($this->cleanPrefName($request->getVal('preferredName', '')), 'preferred_name');
        $result .= $this->addMultiLineFieldToXml($request->getVal('alternateNames', ''), 'formatAlternateNameElement');
        $placeType = Place::capitalizeType($request->getVal('type', ''));
        if (!$placeType) { 
        		$placeType = 'Unknown'; 
        }
        $result .= $this->addSingleLineFieldToXml($placeType, 'type');
        $lat = $this->parseLatLng($request->getVal('latitude', ''), true);
        $lng = $this->parseLatLng($request->getVal('longitude', ''), false);
        $result .= $this->addSingleLineFieldToXml($lat, 'latitude');
        $result .= $this->addSingleLineFieldToXml($lng, 'longitude');
        $result .= $this->addSingleLineFieldToXml($request->getVal('fromYear', ''), 'from_year');
        $result .= $this->addSingleLineFieldToXml($request->getVal('toYear', ''), 'to_year');
        $result .= $this->addMultiLineFieldToXml($request->getVal('alsoLocatedIn', ''), 'formatAlsoLocatedInElement');
        $result .= $this->addMultiLineFieldToXml($request->getVal('seeAlso', ''), 'formatSeeAlsoElement');
        $result .= $this->getContainedPlacesElements();
        return $result;
    }
    
    private function parseLatLng($input, $lat) {
    	$negate = !$lat;
    	$input = trim(mb_convert_case($input, MB_CASE_UPPER));
 		if ($lat && mb_strpos($input, 'N') !== false) {
 			$negate = false;
 			$input = trim(mb_substr($input, 0, mb_strpos($input, 'N')));
 		}
 		else if ($lat && mb_strpos($input, 'S') !== false) {
 			$negate = true;
 			$input = trim(mb_substr($input, 0, mb_strpos($input, 'S')));
 		}
 		else if (!$lat && mb_strpos($input, 'E') !== false) {
 			$negate = false;
 			$input = trim(mb_substr($input, 0, mb_strpos($input, 'E')));
 		}
 		else if (!$lat && mb_strpos($input, 'W') !== false) {
 			$negate = true;
 			$input = trim(mb_substr($input, 0, mb_strpos($input, 'W')));
 		}
 		if (mb_substr($input, 0, 1) == '-') {
 			$negate = true;
 			$input = mb_substr($input, 1);
 		}
    	if (mb_strpos($input, ' ') !== false || mb_strpos($input, '°')) {
    		$deg = '';
    		$min = '';
    		$sec = '';
    		$pos = mb_strpos($input, '°');
    		if ($pos === false) {
    			$pos = mb_strpos($input, ' ');
    		}
    		if ($pos === false) $pos = mb_strlen($input);
    		$deg = mb_substr($input, 0, $pos);
    		$input = trim(mb_substr($input, $pos+1));
    		$pos = mb_strpos($input, "'");
    		if ($pos === false) {
    			$pos = mb_strpos($input, ' ');
    		}
    		if ($pos === false) $pos = mb_strlen($input);
  			$min = mb_substr($input, 0, $pos);
  			$input = trim(mb_substr($input, $pos+1));
    		$pos = mb_strpos($input, '"');
    		if ($pos === false) {
    			$pos = mb_strpos($input, ' ');
    		}
    		if ($pos === false) $pos = mb_strlen($input);
  			$sec = mb_substr($input, 0, $pos);
  			
    		$input = round(floatval($deg)*1.0 + floatval($min)/60.0 + floatval($sec)/3600.0, 6);
    	}
    	else if (strpos($input, ':') !== false) {
    		// parse as d:m:s
    		$pieces = explode(':', $input);
    		$input = floatval($pieces[0])*1.0;
    		if (count($pieces) > 1) {
    			$input += floatval($pieces[1]) / 60.0;
    		}
    		if (count($pieces) > 2) {
    			$input += floatval($pieces[2]) / 3600.0;
    		}
    		$input = round($input, 6);
    	}
    	else {
    		// parse as d.dd
    		$input = floatval($input);
    	}
    	if ($negate) {
    		$input = $input * -1.0;
    	}
    	return $input;
    }

   private function isValidLocatedIn() {
      if (!$this->locatedIn) {
         global $wgUser;
         // only admins can create new top-level countries
         if (!$this->title->exists() && !$wgUser->isAllowed('patrol')) {
            return false;
         }
      }
      return StructuredData::isEmptyOrExists($this->locatedIn, NS_PLACE);
   }

    /**
     * Return true if xml property is valid
     */
    protected function validateData(&$textbox1) {
       if (!StructuredData::isRedirect($textbox1)) {
          $placeTypes = Place::readPlaceTypes();
          return (Place::isValidLatitude((string)$this->xml->latitude) &&
                  Place::isValidLongitude((string)$this->xml->longitude) &&
                  Place::isValidType((string)$this->xml->type, $placeTypes) &&
                  $this->isValidLocatedIn() &&
						mb_substr($this->titleString, 0, 1) != ',' &&
                  !Place::isInvalidAlsoLocatedIn($this->xml->also_located_in));
       }
       else {
       	return (strlen($this->getContainedPlacesElements()) == 0);
       }
    }

	private static function getPropagatedData($xml) {
      $alsoLocatedIn= array();
      $type = null;
		$lat = null;
		$lng = null;
		$fromYear = null;
		$toYear = null;
      if (isset($xml)) {
         $type= (string)$xml->type;
			$lat = (string)$xml->latitude;
			$lng = (string)$xml->longitude;
			$fromYear = (string)$xml->from_year;
			$toYear = (string)$xml->to_year;
         foreach ($xml->also_located_in as $pli) {
            $alsoLocatedIn[] = array('place' => (string)$pli['place'], 'from_year' => (string)$pli['from_year'], 'to_year' => (string)$pli['to_year']);
         }
      }
		return array($alsoLocatedIn, $type, $lat, $lng, $fromYear, $toYear);
	}

    /**
     * Replace an old contained place with a new one
     * @param string $newTitle if null, removes the old contained place
     * @param string $newType
     * @param string $newLat
     * @param string $newLng
     * @param bool $newPrev
     * @param string $text
     * @return string is empty string if nothing changed
     */
    private function updateContainedPlace($newTitle, $newType, $newLat, $newLng, $newFromYear, $newToYear, &$text) {
        // it's easy to update contained places by doing a search and replace directly in the xml string
        if ($newTitle) {
            $escapedTitle = StructuredData::escapeXml($newTitle);
            $escapedType = StructuredData::escapeXml($newType);
				$escapedLat = StructuredData::escapeXml($newLat);
				$escapedLng = StructuredData::escapeXml($newLng);
				$escapedFromYear = StructuredData::escapeXml($newFromYear);
				$escapedToYear = StructuredData::escapeXml($newToYear);
            $new = "<contained_place place=\"$escapedTitle\"" .
					($newLng ? " longitude=\"$escapedLng\"" : '') .
					($newLat ? " latitude=\"$escapedLat\"" : '') .
					($newType ? " type=\"$escapedType\"" : '') .
					($newFromYear ? " from_year=\"$escapedFromYear\"" : '') .
					($newToYear ? " to_year=\"$escapedToYear\"" : '') .
					"/>\n";
        }
        else {
            $new = '';
        }
        $containedPlace = '<contained_place place="' . StructuredData::escapeXml($this->titleString) . '"';
        if (strpos($text, $containedPlace) === false) {
            $old = "</$this->tagName>";
            $new .= $old;
        }
        else {
	        $old = StructuredData::protectRegexSearch($containedPlace) . ".*?/>\n";
        }

		$result = preg_replace('$'.$old.'$', StructuredData::protectRegexReplace($new), $text);
		// if nothing changed, return empty string
		if ($result == $text) {
			$result = '';
		}
		return $result;
    }

   private static function placeAbbrevsGetData($xml) {
       $alsoLocatedIn = array();
       $altNames = array();
       if (isset($xml)) {
          foreach ($xml->also_located_in as $pli) {
             $alsoLocatedIn[] = (string)$pli['place'];
          }
          foreach ($xml->alternate_name as $an) {
            $altNames[] = (string)$an['name'];
          }
      }
      return array($alsoLocatedIn, $altNames);
   }


    protected function placeAbbrevsChanged($origXml, $xml) {
      list ($origAlis, $origAltNames) = Place::placeAbbrevsGetData($origXml);
      list ($alis, $altNames) = Place::placeAbbrevsGetData($xml);

      if (count($origAlis) != count($alis) || count($origAltNames) != count($altNames)) {
         return true;
      }
      foreach ($origAlis as $origAli) {
         if (!in_array($origAli, $alis)) {
            return true;
         }
      }
      foreach ($origAltNames as $origAltName) {
         if (!in_array($origAltName, $altNames)) {
            return true;
         }
      }

      return false;
    }

    protected function placeAbbrevsDelete($titleString) {
      $title = Title::newFromText($titleString, NS_PLACE);
      $dbkey = $title->getDBkey();
      //error_log("placeAbbrevsDelete dbkey=$dbkey\n");
		$dbw =& wfGetDB( DB_MASTER );
      $dbw->delete( 'place_abbrevs', array( 'title' => $dbkey ), 'placeAbbrevsDelete');
    }

    protected function placeAbbrevsGetParents($titleString) {
      $parents = array();
      $dbr =& wfGetDB(DB_SLAVE);
      $title = Title::newFromText($titleString, NS_PLACE);
      $dbkey = $title->getDBkey();

      // read all place_abbrev rows for $titleString
		$rows = $dbr->select('place_abbrevs', array('primary_name','priority'), array('title' => $dbkey), 'placeAbbrevsGetParents');
      $errno = $dbr->lastErrno();
      while ($row = $dbr->fetchObject($rows)) {
         // keep distinct primary_name's with lowest priorities
         if (!@$parents[$row->primary_name] || $parents[$row->primary_name] > $row->priority) {
            $parents[$row->primary_name] = $row->priority;
         }
      }
      $dbr->freeResult($rows);

      return $parents;
    }

    protected function placeAbbrevsInsertAbbrev($abbrev, $name, $primaryName, $titleString, $priority) {
      $key = $abbrev . '|' . $titleString;
      if (@$this->placeAbbrevsSeenPlaces[$key]) {
         return;
      }
      $this->placeAbbrevsSeenPlaces[$key] = true;

      //error_log("placeAbbrevsInsertAbbrev abbrev=$abbrev name=$name primaryName=$primaryName titleString=$titleString priority=$priority\n");
		$dbw =& wfGetDB( DB_MASTER );
		$dbw->insert('place_abbrevs',
		   array(
		      'abbrev' => $abbrev,
		      'name' => $name,
		      'primary_name' => $primaryName,
		      'title' => $titleString,
		      'priority' => $priority
		));
    }

   // keep in sync with Autocompleter.php
    protected function placeAbbrevsClean($s) {
      $s = StructuredData::mb_str_replace($s, '_', ' '); // convert _'s back to spaces
      $s = preg_replace_callback(
         '/\(([^)]*)/',
         function ($matches) {
            return "(" . mb_convert_case($matches[1], MB_CASE_TITLE);
         },
         $s);
      $s = preg_replace('/\(Independent City\)|\(City Of\)/', "(City)", $s);
      $s = StructuredData::mb_str_replace($s, '(', ' ');
      $s = StructuredData::mb_str_replace($s, ')', ' ');
      $s = preg_replace('/\s+/', ' ', $s);
      $s = StructuredData::mb_str_replace($s, ' ,', ',');
      $s = trim($s);
      return $s;
    }

   // keep in sync with Autocompleter.php
    protected function placeAbbrevsCleanAbbrev($s) {
      // remove accents must be called before mb_strtolower; otherwise the German SS isn't handled correctly
      $s = StructuredData::removeAccents($s);
      $s = Place::placeAbbrevsClean($s);
      $s = mb_strtolower($s);
      $s = StructuredData::mb_str_replace($s, "'", '');
      $s = preg_replace('/[^a-z0-9]/', ' ', $s);
      $s = preg_replace('/\s+/', ' ', $s);
      $s = trim($s);
      return $s;
    }

    protected function placeAbbrevsInsertAbbrevs($name, $primaryName, $suffix, $titleString, $priority) {
      // clean names
      if (!$suffix) {
         Place::placeAbbrevsInsertAbbrev(Place::placeAbbrevsCleanAbbrev($name), Place::placeAbbrevsClean($name),
                                          Place::placeAbbrevsClean($primaryName), $titleString, $priority);
         return;
      }

      $primaryFullName = Place::placeAbbrevsClean($primaryName . ", " . $suffix);
      $fullName = Place::placeAbbrevsClean($name . ", " . $suffix);
      $levels = explode(",", $suffix);
      for ($i = 0; $i < count($levels); $i++) {
         $levels[$i] = trim($levels[$i]);
      }
      for ($i = 0; $i < count($levels); $i++) {
         // construct abbrevs
         $abbrevSuffix = join(", ", array_slice($levels, $i));
         $abbrev = Place::placeAbbrevsCleanAbbrev($name . ", " . $abbrevSuffix);
         Place::placeAbbrevsInsertAbbrev($abbrev, $fullName, $primaryFullName, $titleString, $priority);
         $pos = mb_strpos($name, "(");
         if ($pos > 0) {
            $abbrev = Place::placeAbbrevsCleanAbbrev(mb_substr($name, 0, $pos) . ", " . $abbrevSuffix);
            Place::placeAbbrevsInsertAbbrev($abbrev, $fullName, $primaryFullName, $titleString, $priority);
         }
      }
    }

    protected function placeAbbrevsAdd($titleString, $xml) {
      $this->placeAbbrevsSeenPlaces = array();
      $title = Title::newFromText($titleString, NS_PLACE);
      $titleString = $title->getDBkey();
		list($prefName, $locatedIn) = Place::getPrefNameLocatedIn($titleString);
		list($alis, $altNames) = Place::placeAbbrevsGetData($xml);
		$parents = Place::placeAbbrevsGetParents($locatedIn);
		foreach ($parents as $parentName => $priority) {
   		Place::placeAbbrevsInsertAbbrevs($prefName, $prefName, $parentName, $titleString, $priority + 10);
	   	foreach ($altNames as $altName) {
		      Place::placeAbbrevsInsertAbbrevs($altName, $prefName, $parentName, $titleString, $priority + 24);
		   }
		}
		foreach ($alis as $ali) {
   		$parents = Place::placeAbbrevsGetParents($ali);
   		foreach ($parents as $primaryName => $priority) {
            Place::placeAbbrevsInsertAbbrevs($prefName, $prefName, $primaryName, $titleString, $priority + 11);
            foreach ($altNames as $altName) {
               Place::placeAbbrevsInsertAbbrevs($altName, $prefName, $primaryName, $titleString, $priority + 25);
            }
         }
		}
    }

    /**
     * Propagate data in xml property to other articles if necessary
     * @param string $oldText contains text being replaced
     * @param String $text which we never touch when propagating places
     * @param bool $textChanged which we never touch when propagating places
     * @return bool true if propagation was successful
     */
    protected function propagateEditData($oldText, &$text, &$textChanged) {
       $result = true;

        // determine current parents and type and lat and lng
        list ($alsoLocatedIn, $type, $lat, $lng, $fromYear, $toYear) = Place::getPropagatedData($this->xml);

		// determine original parents by retrieving current version
		list ($origAlsoLocatedIn, $origType, $origLat, $origLng, $origFromYear, $origToYear) = array(array(), null, null, null, null, null);
		if ($oldText) {
         $origXml = StructuredData::getXml('place', $oldText);
         if (isset($origXml)) {
            list ($origAlsoLocatedIn, $origType, $origLat, $origLng, $origFromYear, $origToYear) = Place::getPropagatedData($origXml);

            // maintain place_abbrevs
            if (!isset($this->xml) || Place::placeAbbrevsChanged($origXml, $this->xml)) {
               Place::placeAbbrevsDelete($this->titleString);
               if (isset($this->xml)) {
                  Place::placeAbbrevsAdd($this->titleString, $this->xml);
               }
            }
         }
         else {
            // new place comes here or below
            // add to place_abbrevs
            Place::placeAbbrevsDelete($this->titleString);
            Place::placeAbbrevsAdd($this->titleString, $this->xml);
         }
		}
		else {
		   // add to place_abbrevs
		   Place::placeAbbrevsDelete($this->titleString);
		   Place::placeAbbrevsAdd($this->titleString, $this->xml);
		}

//		$delAlsoLocatedIn = StructuredData::mapDiff($origAlsoLocatedIn, $alsoLocatedIn, 'place');
		// don't remove from X if this place is locatedIn X
//		if ($this->locatedIn) {
//		   $key = StructuredData::mapSearch($this->locatedIn, $delAlsoLocatedIn, 'place');
//		   if ($key!==false) {
//		      array_splice($delAlsoLocatedIn, $key, 1);
//		   }
//		}
//      if ($type != $origType || $lat != $origLat || $lng != $origLng) {
//         $updAlsoLocatedIn = $alsoLocatedIn;
//      }
//      else {
//         $updAlsoLocatedIn = array_diff($alsoLocatedIn, $origAlsoLocatedIn);
//      }

		// remove from original parent if necessary (don't remove from X if this place is now prevLocatedIn X)
//		if ($origLocatedIn && $origLocatedIn != $locatedIn && array_search($origLocatedIn, $alsoLocatedIn)===false) {
//            $parentArticle = StructuredData::getArticle(Title::newFromText((string)$origLocatedIn, NS_PLACE), true);
//            if ($parentArticle) {
//                $content =& $parentArticle->fetchContent();
//                $updatedContent =& $this->updateContainedPlace(null, null, null, null, $content);
//					if ($updatedContent) {
//						$updateResult = $parentArticle->doEdit($updatedContent, self::PROPAGATE_MESSAGE, PROPAGATE_EDIT_FLAGS);
//						$result = $result && $updateResult;
//					}
//            }
//		}

	  $isRedirect = StructuredData::isRedirect($text);
	  // add alt name to redirect target
	  if ($isRedirect) {
   		list($prefName, $locatedIn) = Place::getPrefNameLocatedIn($this->titleString);
         $prefName = StructuredData::mb_str_replace($prefName, '_', ' '); // convert _'s back to spaces
	      $targetArticle = StructuredData::getArticle(Title::newFromRedirect($text), true);
	      $targetContent =& $targetArticle->fetchContent();
	      $targetXml = StructuredData::getXml('place', $targetContent);
	      $found = false;
         foreach ($targetXml->alternate_name as $an) {
            $altName = (string)$an['name'];
            if ($altName == $prefName) {
              $found = true;
              break;
            }
         }
         if (!$found) {
            $altName = '<alternate_name name="'.StructuredData::escapeXml($prefName).'" source="from redirect"/>';
            $targetContent = StructuredData::mb_str_replace($targetContent, "<place>", "<place>\n".$altName);
            $targetArticle->doEdit($targetContent, 'Add alternate name from redirect', PROPAGATE_EDIT_FLAGS);
         }
	  }

     // update type, lat, or lng if changed, or this place has been redirected or this place is new
     if ($this->locatedIn && ($isRedirect || !isset($origXml) || $type != $origType || $lat != $origLat || $lng != $origLng || $fromYear != $origFromYear || $toYear != $origToYear)) {
         $parentArticle = StructuredData::getArticle(Title::newFromText($this->locatedIn, NS_PLACE), true);
         if ($parentArticle) {
             $content =& $parentArticle->fetchContent();
             if ($isRedirect) { // remove from parent
	   	       $updatedContent =& $this->updateContainedPlace(null, null, null, null, null, null, $content);
             }
             else {
   	          $updatedContent =& $this->updateContainedPlace($this->titleString, $type, $lat, $lng, $fromYear, $toYear, $content);
             }
				if ($updatedContent) {
					$updateResult = $parentArticle->doEdit($updatedContent, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
					$result = $result && $updateResult;
				}
         }
     }

     // remove from deleted previous parents
     foreach ($origAlsoLocatedIn as $pli) {
     	if ($this->locatedIn != (string)$pli['place'] && StructuredData::mapArraySearch((string)$pli['place'], $alsoLocatedIn, 'place') === false) {
        $parentArticle = StructuredData::getArticle(Title::newFromText((string)$pli['place'], NS_PLACE), true);
        if ($parentArticle) {
           $content =& $parentArticle->fetchContent();
           $updatedContent =& $this->updateContainedPlace(null, null, null, null, null, null, $content);
			  if ($updatedContent) {
				 $updateResult = $parentArticle->doEdit($updatedContent, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
             $result = $result && $updateResult;
			  }
         }
     	 }
     }

     // add to new previous parents or update type, lat, lng on same previous-parents
     foreach ($alsoLocatedIn as $pli) {
     		$origKey = StructuredData::mapArraySearch((string)$pli['place'], $origAlsoLocatedIn, 'place');
     		if ($origKey !== false) {
     			$origPli = $origAlsoLocatedIn[$origKey];
     		}
     		if ($this->locatedIn != (string)$pli['place'] && 
     				($origKey === false || $type != $origType || $lat != $origLat || $lng != $origLng || 
     				 (string)$pli['from_year'] != (string)$origPli['from_year'] || (string)$pli['to_year'] != (string)$origPli['to_year'])) {
	         $parentArticle = StructuredData::getArticle(Title::newFromText((string)$pli['place'], NS_PLACE), true);
	         if ($parentArticle) {
	             $content =& $parentArticle->fetchContent();
	             $updatedContent =& $this->updateContainedPlace($this->titleString, $type, $lat, $lng, (string)$pli['from_year'], (string)$pli['to_year'], $content);
					if ($updatedContent) {
						$updateResult = $parentArticle->doEdit($updatedContent, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
						$result = $result && $updateResult;
					}
	         }
         }
      }

      if (!$result) {
			error_log("ERROR! Place edit/rollback not propagated: $this->titleString\n");
		}
      return $result;
   }

    /**
     * Propagate move, delete, or undelete to other articles if necessary
     *
     * @param String $newTitleString null in case of delete; same as this title string in case of undelete
     * @param String $text text of article
     * @param bool $textChanged we never change the text, so this is never set to true
     * @return bool true if success
     */
    protected function propagateMoveDeleteUndelete($newTitleString, $newNs, &$text, &$textChanged) {
    	$result = true;

      // maintain place abbrevs
      if ($newTitleString && $newTitleString != $this->titleString) {
		   Place::placeAbbrevsDelete($this->titleString);
		   Place::placeAbbrevsDelete($newTitleString);
		   Place::placeAbbrevsAdd($newTitleString, $this->xml);
      }
      else if (!$newTitleString) {
		   Place::placeAbbrevsDelete($this->titleString);
      }

    	// determine parents and type and lat and lng
    	list ($alsoLocatedIn, $type, $lat, $lng, $fromYear, $toYear) = Place::getPropagatedData($this->xml);

    	// get new name pieces
    	$newName = Place::getPrefNameLocatedIn($newTitleString);

    	// follow redirects
    	$t = ((string)$this->locatedIn ? Title::newFromText((string)$this->locatedIn, NS_PLACE) : null);
    	if ($t) $t = StructuredData::getRedirectToTitle($t);
    	$oldLocatedIn = ($t ? $t->getText() : (string)$this->locatedIn);
    	$t = ($newName[1] ? Title::newFromText($newName[1], NS_PLACE) : null);
    	if ($t) $t = StructuredData::getRedirectToTitle($t);
    	$newLocatedIn = ($t ? $t->getText() : $newName[1]);
    	foreach ($alsoLocatedIn as $ali) {
    		$t = ($ali['place'] ? Title::newFromText($ali['place'], NS_PLACE) : null);
    		if ($t) $t = StructuredData::getRedirectToTitle($t);
    		if ($t) $ali['place'] = $t->getText();
    	}
    	
    	// if current located-in place is not also an also-located-in place
    	if (StructuredData::mapArraySearch($oldLocatedIn, $alsoLocatedIn, 'place') === false) {
	    	if ($oldLocatedIn == $newLocatedIn) {
	    		// update title in located-in place
	    		$article = StructuredData::getArticle(Title::newFromText($oldLocatedIn, NS_PLACE), true);
	    		if ($article) {
	    			$content =& $article->fetchContent();
	    			$updatedContent =& $this->updateContainedPlace($newTitleString, $type, $lat, $lng, $fromYear, $toYear, $content);
	    			if ($updatedContent) {
	    				$updateResult = $article->doEdit($updatedContent, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
	    				$result = $result && $updateResult;
	    			}
	    		}
	    	}
	    	else {
		    	// remove from current parent
	    		$parentArticle = StructuredData::getArticle(Title::newFromText($oldLocatedIn, NS_PLACE), true);
	    		if ($parentArticle) {
	    			$content =& $parentArticle->fetchContent();
	    			$updatedContent =& $this->updateContainedPlace(null, null, null, null, null, null, $content);
	    			if ($updatedContent) {
	    				$updateResult = $parentArticle->doEdit($updatedContent, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
	    				$result = $result && $updateResult;
	    			}
	    		}
	    	}
    	}

    	// add to new parent if necessary
    	if ($oldLocatedIn != $newLocatedIn && StructuredData::mapArraySearch($newLocatedIn, $alsoLocatedIn, 'place') === false) {
    		$article = StructuredData::getArticle(Title::newFromText($newLocatedIn, NS_PLACE), true);
    		if ($article) {
    			$content =& $article->fetchContent();
    			$updatedContent =& $this->updateContainedPlace($newTitleString, $type, $lat, $lng, $fromYear, $toYear, $content);
    			if ($updatedContent) {
    				$updateResult = $article->doEdit($updatedContent, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
    				$result = $result && $updateResult;
    			}
    		}
    	}

    	// update title in also-located-in places
    	foreach ($alsoLocatedIn as $pli) {
    		$article = StructuredData::getArticle(Title::newFromText((string)$pli['place'], NS_PLACE), true);
    		if ($article) {
    			$content =& $article->fetchContent();
    			$updatedContent =& $this->updateContainedPlace($newTitleString, $type, $lat, $lng, (string)$pli['from_year'], (string)$pli['to_year'], $content);
    			if ($updatedContent) {
    				$updateResult = $article->doEdit($updatedContent, self::PROPAGATE_MESSAGE.' [['.$this->title->getPrefixedText().']]', PROPAGATE_EDIT_FLAGS);
    				$result = $result && $updateResult;
    			}
    		}
    	}

    	if (!$result) {
    		error_log("ERROR! Place move/delete/undelete not propagated: $this->titleString -> " .
    		($newTitleString ? $newTitleString : "[delete]") . "\n");
    	}
    	return $result;
    }
}
?>
