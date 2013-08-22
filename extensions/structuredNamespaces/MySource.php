<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfMySourceExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfMySourceExtensionSetup() {
   global $wgHooks;
   global $wgParser;

   # Register hooks for edit UI, request handling, and save features
   $wgHooks['ArticleEditShow'][] = 'renderMySourceEditFields';
   $wgHooks['ImportEditFormDataComplete'][] = 'importMySourceEditData';
   $wgHooks['EditFilter'][] = 'validateMySource';
   $wgHooks['ArticleSave'][] = 'updateMySourceWLH';
   $wgHooks['TitleMoveComplete'][] = 'propagateMySourceMove';

   # register the extension with the WikiText parser
   $wgParser->setHook('mysource', 'renderMySourceData');
}

/**
 * Callback function for converting source to HTML output
 */
function renderMySourceData( $input, $argv, $parser) {
   $source = new MySource($parser->getTitle()->getText());
   return $source->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderMySourceEditFields( &$editPage ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_MYSOURCE) {
      $source = new MySource($editPage->mTitle->getText());
      $source->renderEditFields($editPage);
   }
   return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importMySourceEditData( &$editPage, &$request ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_MYSOURCE) {
      $source = new MySource($editPage->mTitle->getText());
      $source->importEditData($editPage, $request);
   }
   return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateMySource($editPage, $textBox1, $section, &$hookError) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_MYSOURCE) {
      $source = new MySource($editPage->mTitle->getText());
      $source->validate($textBox1, $section, $hookError);
   }
   return true;
}

/**
 * Callback function to update what-links-here
 * @return bool must return true or other hooks don't get called
 */
function updateMySourceWLH(&$article, &$user, &$text, &$summary, $minor, $dummy1, $dummy2, &$flags) {
   $ns = $article->getTitle()->getNamespace();
   if ($ns == NS_MYSOURCE) {
      // update people and families that link to this source, because source citation could have changed
      $u = new HTMLCacheUpdate( $article->getTitle(), 'pagelinks' );
      $u->doUpdate();
	}
	return true;
}

/**
 * Callback function to propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateMySourceMove(&$title, &$newTitle, &$user, $pageId, $redirPageId) {
	$ns = $title->getNamespace();
	if ($ns == NS_MYSOURCE) {
      // update people and families that link to this mysource, because source citation has changed
      $u = new HTMLCacheUpdate( $title, 'pagelinks' );
      $u->doUpdate();
      $u = new HTMLCacheUpdate( $newTitle, 'pagelinks' );
      $u->doUpdate();
      StructuredData::copyPageLinks($title, $newTitle);
	}
	return true;
}

/**
 * Handles mysources
 */
class MySource extends StructuredData {
	private $correctedPlaceTitles;

	/**
     * Construct a new mysource object
     */
   public function __construct($titleString) {
      parent::__construct('mysource', $titleString, NS_MYSOURCE);
   }

//   protected function formatAltName($value, $dummy) {
//      return $value;
//   }

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

   private function getTitleAuthorValue($value, $titleAuthor) {
      if ($titleAuthor) {
         return "<span class=\"wr-infobox-title\">$value</span>";
      }
      return $value;
   }

   protected function getLV($label, $value, $titleAuthor=false, $value2=null, $linkNamespace=null) {
      $values = '';
      if (is_string($value)) {
         $values .= $this->getTitleAuthorValue($value, $titleAuthor);
      }
      else {
         foreach ($value as $v) {
            if ($values) $values .= '<br>';
            $v = str_replace("\n", '<br>', (string)$v);
            if ($linkNamespace) {
               $v = $this->formatAsLink($v, $linkNamespace);
            }
            $values .= $this->getTitleAuthorValue($v, $titleAuthor);
            $titleAuthor = false;
         }
      }
      if (isset($value2)) {
         $values .= ' - '.(string)$value2;
      }
      if (!$values) return '';
      return <<<END
<tr>
   <td class="wr-infobox-label">$label</td>
   <td>$values</td>
</tr>
END;
   }

   protected function getMySourceTitle() {
      $pos = mb_strpos($this->titleString, '/');
      if ($pos !== false) {
         return mb_substr($this->titleString, $pos+1);
      }
      return $this->titleString;
   }

   protected function getTitleAuthor() {
      $result = '';
      $result .= $this->getLV("MySource", $this->getMySourceTitle(), true);
      $result .= $this->getLV("Author", $this->xml->author, true);
      $result .= $this->getLV("Abreviation", $this->xml->abbrev);
      if (!$result) return '';
      return <<<END
<table>
$result
</table>
END;
   }

   protected function getCoverage() {
      $result = '';
      $highlight = true;
      foreach ($this->xml->author as $a) {
         $highlight = false;
         break;
      }
      $result .= $this->getLV("Place", $this->xml->place);
      $result .= $this->getLV("Year range", $this->xml->from_year, false, $this->xml->to_year);
      $result .= $this->getLV("Surname", $this->xml->surname);
      if (!$result) return '';
      return <<<END
<div class="wr-infobox-heading">Coverage</div>
<table>
$result
</table>
END;
   }

   protected function getPublicationInfo() {
      $result = '';
      $result .= $this->getLV("Type", $this->xml->type);
      $result .= $this->getLV("Publication", $this->xml->publication_info);
      if (!$result) return '';
      return <<<END
<div class="wr-infobox-heading">Publication information</div>
<table>
$result
</table>
END;
   }

   public function getCitationText($makeLink=false) {
      $result = '';
      if (isset($this->xml->author)) {
         $result .= trim((string)$this->xml->author);
      }
      if ($result) $result = StructuredData::chomp($result,'.').'. ';
      $result .= '<i>'.$this->getMySourceTitle().'</i>';
      if ($makeLink) {
         $result = preg_replace("/\[\[(.+?\|(.+?)|([^\|]+?))\]\]/", "$2$3", $result);
         $result = "[[MySource:{$this->titleString}|$result]]";
      }
      if (isset($this->xml->publication_info)) {
         $result = StructuredData::chomp($result,'.').'. ('.trim((string)$this->xml->publication_info).')';
      }
      return StructuredData::chomp($result,'.');
   }

   protected function getCitation() {
      $result = $this->getCitationText();
      if (!$result) return '';
      return <<<END
<div class="wr-infobox-heading">Citation</div>
<table><tr><td>$result.</td></tr></table>
END;
   }

   protected function getRepo() {
      $result = '';
      $result .= $this->getLV("Name", $this->xml->repository_name);
      $result .= $this->getLV("Address", $this->xml->repository_addr);
      $result .= $this->getLV("Call #", $this->xml->call_number);
      $result .= $this->getLV("URL", $this->xml->url);
      if (!$result) return '';
      return <<<END
<div class="wr-infobox-heading">Repository</div>
<table>
$result
</table>
END;
   }

   /**
	 * Create wiki text from xml property
	 */
   protected function toWikiText($parser) {
      $result = '';
      if (isset($this->xml)) {
         // add infobox
         $titleAuthor = $this->getTitleAuthor();
         $coverage = $this->getCoverage();
         $publicationInfo = $this->getPublicationInfo();
         $citation = $this->getCitation();
         $repo = $this->getRepo();

         $infobox = <<<END
<div class="wr-infobox wr-infobox-mysource">
$titleAuthor
$coverage
$publicationInfo
$citation
$repo
</div>
END;

			$result = $infobox.StructuredData::addCategories($this->xml->surname, $this->xml->place);
      }
      return $result;
   }

   public static function getPageText($author, $place, $surname) {
   	$result = "<mysource>\n";
   	if ($author) {
   		$result .= '<author>'.StructuredData::escapeXml($author)."</author>\n";
   	}
   	if ($place) {
   		$result .= '<place>'.StructuredData::escapeXml($place)."</place>\n";
   	}
   	if ($surname) {
   		$result .= '<surname>'.StructuredData::escapeXml($surname)."</surname>\n";
   	}
   	$result .= "</mysource>\n";
   	return $result;
   }
   
   private static function splitGedcomMySourceTitle($titleString) {
		$lpos = mb_strpos($titleString, '/');
		$mysourcePrefix = mb_substr($titleString, 0, $lpos+1);
		$rpos = mb_strrpos($titleString, '(');
		$mysourceSuffix = mb_substr($titleString, $rpos-1);
		$mysourceTitle = mb_substr($titleString, $lpos+1, $rpos-$lpos-2);
   	return array($mysourcePrefix, $mysourceTitle, $mysourceSuffix);
   }

   /**
     * Create edit fields from xml property
     */
   protected function toEditFields(&$textbox1) {
		global $wgOut, $wgScriptPath, $wgRequest;

      $result = '';
      $target = $wgRequest->getVal('target');

		// add javascript functions
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.yui.8.js\"></script>");

      $invalidStyle = ' style="background-color:#fdd;"';
//      $altNames = '';
      $surnames = '';
      $places = '';
      $fromYearStyle = '';
      $toYearStyle= '';
      $urlStyle = '';
      $fromYear = '';
      $toYear = '';
      $url = '';
      $abbrev = '';
      $author = '';
      $pubInfo = '';
      $callNumber = '';
      $type = '';
      $repoName = '';
      $repoAddr = '';
		$exists = isset($this->xml);
		if (!$exists) {
      	global $wgRequest;

      	// construct <source> text from request
			$text = MySource::getPageText($wgRequest->getVal('a'), $wgRequest->getVal('p'), $wgRequest->getVal('s'));
			$this->xml = StructuredData::getXml('mysource', $text);
		}
		if (isset($this->xml)) {
         $fromYear = htmlspecialchars((string)$this->xml->from_year);
         $toYear = htmlspecialchars((string)$this->xml->to_year);
         $url = htmlspecialchars((string)$this->xml->url);
         $abbrev = htmlspecialchars((string)$this->xml->abbrev);
         $author = htmlspecialchars((string)$this->xml->author);
         $pubInfo = htmlspecialchars((string)$this->xml->publication_info);
         $callNumber = htmlspecialchars((string)$this->xml->call_number);
         $type = htmlspecialchars((string)$this->xml->type);
         $repoName = htmlspecialchars((string)$this->xml->repository_name);
         $repoAddr = htmlspecialchars((string)$this->xml->repository_addr);
//         foreach ($this->xml->alternate_name as $altName) {
//            $altNames .= $altName . "\n";
//         }
         foreach ($this->xml->surname as $surname) {
            $surnames .= htmlspecialchars($surname) . "\n";
         }
         foreach ($this->xml->place as $place) {
            $places.= htmlspecialchars($place) . "\n";
         }
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
      if (!StructuredData::isValidUrl($url)) {
         $urlStyle = $invalidStyle;
         $result .= "<p><font color=red>The URL is not valid</font></p>";
      }
		if ($this->isGedcomPage) {
			list ($mysourcePrefix, $mysourceTitle, $mysourceSuffix) = MySource::splitGedcomMySourceTitle($this->titleString);
			$result .= '<br><label for="title">MySource title</label><input tabindex="1" size="80" name="mysource_title" value="'.htmlspecialchars($mysourceTitle).'"/>';
		}
      
      $result .= "<br>";
//      $result .= "<label for=\"altNames\">Alternate names (one per line):</label><br><textarea tabindex=\"1\" name=\"altNames\" rows=\"3\" cols=\"40\">".htmlspecialchars($altNames)."</textarea>";
      $result .= "<br><label for=\"surnames\">Surnames covered(one per line):</label><br><textarea tabindex=\"1\" name=\"surnames\" rows=\"3\" cols=\"60\">$surnames</textarea>"
      ."<br><label for=\"places\">Places covered (one per line):</label><br><textarea class=\"place_input\" tabindex=\"1\" name=\"places\" rows=\"3\" cols=\"60\">$places</textarea>"
      ."<table>"
      ."<tr><td align=right>Year range:</td><td align=left><input tabindex=\"1\" name=\"fromYear\" value=\"$fromYear\" size=\"5\"$fromYearStyle/>"
         ."&nbsp;&nbsp;-&nbsp;<input tabindex=\"1\" name=\"toYear\" value=\"$toYear\" size=\"5\"".htmlspecialchars($toYearStyle)."/></td></tr>";
      $result .= "<tr><td align=right>URL:</td><td align=left><input tabindex=\"1\" name=\"url\" value=\"$url\" size=\"60\"$urlStyle/></td></tr>"
         ."<tr><td align=right>Author:</td><td align=left><input tabindex=\"1\" name=\"author\" value=\"$author\" size=\"20\"/></td></tr>"
         ."<tr><td align=right>Publication info:</td><td align=left><input tabindex=\"1\" name=\"pubInfo\" value=\"$pubInfo\" size=\"60\"/></td></tr>"
         ."<tr><td align=right>Call number:</td><td align=left><input tabindex=\"1\" name=\"callNumber\" value=\"$callNumber\" size=\"20\"/></td></tr>"
         ."<tr><td align=right>Type:</td><td align=left><input tabindex=\"1\" name=\"type\" value=\"$type\" size=\"20\"/></td></tr>"
         ."<tr><td align=right>Repository name:</td><td align=left><input tabindex=\"1\" name=\"repoName\" value=\"$repoName\" size=\"60\"/></td></tr>"
         ."<tr><td align=right>Repository addr:</td><td align=left><input tabindex=\"1\" name=\"repoAddr\" value=\"$repoAddr\" size=\"60\"/></td></tr>"
         ."<tr><td align=right>Abbreviation:</td><td align=left><input tabindex=\"1\" name=\"abbrev\" value=\"$abbrev\" size=\"10\"/></td></tr>"
         ."</table>Text:<br>";
      return $result;
   }

//   protected function formatAltNameXML($value) {
//			$escapedValue =& StructuredData::escapeXml($value);
//      return "<alternate_name>$escapedValue</alternate_name>";
//   }

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

   private function cleanUrl($url) {
      $url = trim($url);
      if($url != '' && !stristr($url, "http://")) {
         return "http://" . $url;
      }
      return $url;
   }

   /**
     * Return xml elements from data in request
     * @param unknown $request
     */
   protected function fromEditFields($request) {
   	$this->correctedPlaceTitles = PlaceSearcher::correctPlaceTitlesMultiLine($request->getVal('places', ''));
      $result = '';
      // !!! +title, +date_issued, -publication_info, -call_number, type->format, -abbrev, +ethnicity, +religion, +occupation, +category
      // !!! author format=lastname, given name(s)
		if ($this->isGedcomPage) {
			list($mysourcePrefix, $mysourceTitle, $mysourceSuffix) = MySource::splitGedcomMySourceTitle($this->titleString);
			$mysourceTitle = $request->getVal('mysource_title', '') ? $request->getVal('mysource_title', '') : $mysourceTitle;
			$mysourceTitle = mb_strtoupper(mb_substr($mysourceTitle, 0, 1)).mb_substr($mysourceTitle, 1);
			$result .= $this->addSingleLineFieldToXml($mysourceTitle, 'title');
			$this->titleString = $mysourcePrefix.$mysourceTitle.$mysourceSuffix;
			$this->title = Title::newFromText($this->titleString, NS_MYSOURCE);
		}
      
      $result .= $this->addSingleLineFieldToXml($this->cleanUrl($request->getVal('url', '')), 'url');
//      $result .= $this->addMultiLineFieldToXml($request->getVal('altNames', ''), 'formatAltNameXML');
      $result .= $this->addMultiLineFieldToXml($request->getVal('places', ''), 'formatPlace');
      $result .= $this->addMultiLineFieldToXml($request->getVal('surnames', ''), 'formatSurname');
      $result .= $this->addSingleLineFieldToXml($request->getVal('fromYear', ''), 'from_year');
      $result .= $this->addSingleLineFieldToXml($request->getVal('toYear', ''), 'to_year');
      $result .= $this->addSingleLineFieldToXml($request->getVal('abbrev', ''), 'abbrev');
      $result .= $this->addSingleLineFieldToXml($request->getVal('author', ''), 'author');
      $result .= $this->addSingleLineFieldToXml($request->getVal('pubInfo', ''), 'publication_info');
      $result .= $this->addSingleLineFieldToXml($request->getVal('callNumber', ''), 'call_number');
      $result .= $this->addSingleLineFieldToXml($request->getVal('type', ''), 'type');
      $result .= $this->addSingleLineFieldToXml($request->getVal('repoName', ''), 'repository_name');
      $result .= $this->addSingleLineFieldToXml($request->getVal('repoAddr', ''), 'repository_addr');
      return $result;
   }

   /**
     * Return true if xml property is valid
     */
   protected function validateData() {
      if (isset($this->xml)) {
         return (StructuredData::isValidYear((string)$this->xml->from_year) &&
         StructuredData::isValidYear((string)$this->xml->to_year) &&
         StructuredData::isValidUrl((string)$this->xml->url));
      }
      return true;
   }
}
?>
