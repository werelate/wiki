<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfArticleExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfArticleExtensionSetup() {
   global $wgHooks;
   global $wgParser;

   # Register hooks for edit UI, request handling, and save features
   $wgHooks['ArticleEditShow'][] = 'renderArticleEditFields';
   $wgHooks['ImportEditFormDataComplete'][] = 'importArticleEditData';
   $wgHooks['EditFilter'][] = 'validateArticle';

   # register the extension with the WikiText parser
   $wgParser->setHook('article', 'renderArticleData');
}

/**
 * Callback function for converting Article page data to HTML output
 */
function renderArticleData( $input, $argv, $parser) {
	$titleString = $parser->getTitle()->getText();
	// don't render anything for the Main Page
	if ($titleString != 'Main Page') {
   	$article = new ArticlePage($titleString);
   	return $article->renderData($input, $parser);
	}
	else {
		return '';
	}
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderArticleEditFields( &$editPage ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_MAIN) {
      $article = new ArticlePage($editPage->mTitle->getText());
      $article->renderEditFields($editPage);
   }
   return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importArticleEditData( &$editPage, &$request ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_MAIN) {
      $article = new ArticlePage($editPage->mTitle->getText());
      $article->importEditData($editPage, $request);
   }
   return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateArticle($editPage, $textBox1, $section, &$hookError) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_MAIN) {
      $article = new ArticlePage($editPage->mTitle->getText());
      $article->validate($textBox1, $section, $hookError);
   }
   return true;
}

/**
 * Handles article pages
 */
class ArticlePage extends StructuredData {
	private $correctedPlaceTitles;

   /**
     * Construct a new article page object
     * @param string $tagName the name of the tag for this namespace
     */
   public function __construct($titleString) {
      parent::__construct('article', $titleString, NS_MAIN);
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

   protected function getLV($label, $value, $value2='', $linkNamespace=null) {
      $values = '';
      if ($value2) $value2 = " - $value2";
      foreach ($value as $v) {
         $lines = explode("\n", (string)$v);
         foreach ($lines as $line) {
            if ($linkNamespace) {
               $line = $this->formatAsLink($line, $linkNamespace);
            }
            $values .= "<dd>{$line}{$value2}";
            $value2 = '';
         }
      }
      if (!$values) return '';
      return '<dt class="wr-infobox-label">'.$label.$values;
   }

   /**
	 * Create wiki text from xml property
	 */
   protected function toWikiText($parser) {
      $result = '';
      if ($this->titleString != 'Main Page') {
//         $result = '{| id="structuredData" border=1 class="floatright" cellpadding=4 cellspacing=0 width=30%'."\n";
//			$result = "<div class=\"infobox-header\">Article Covers</div>\n{|\n";
         if (isset($this->xml) ) {
//            $result .= $this->addValuesToTableDL('Surname(s):', $this->xml->surname, 'formatAsLink', 'Surname');
//            $result .= $this->addValuesToTableDL('Place(s):', $this->xml->place, 'formatAsLink', 'Place');
//            if ((string)$this->xml->from_year || (string)$this->xml->to_year) {
//               $result .= $this->addValueToTableDL("Year range:", "{$this->xml->from_year} - {$this->xml->to_year}");
//            }
//         }
//   		$result .= $this->showWatchers();
//         $result .= "|}\n";
//         if (isset($this->xml)) {
            $surnames = $this->getLV("Surnames", $this->xml->surname, '', 'Surname');
            $places = $this->getLV("Places", $this->xml->place, '', 'Place');
            $yearRange = $this->getLV("Year range", $this->xml->from_year, (string)$this->xml->to_year);
            if ($surnames || $places || $yearRange) {
               $heading = 'Article Covers';
            }
            else {
               $heading = 'Share';
            }
            $result = "<div class=\"wr-infobox wr-infobox-article\"><div class=\"wr-infobox-heading\">$heading</div><dl>{$surnames}{$places}{$yearRange}</dl></div>";
            $result .= StructuredData::addCategories($this->xml->surname, $this->xml->place, true, $this->titleString, $this->ns);
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
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.yui.8.js\"></script>");

      $invalidStyle = ' style="background-color:#fdd;"';
      $result = '';
      $surnames = '';
      $places = '';
      $fromYear = '';
      $fromYearStyle = '';
      $toYear = '';
      $toYearStyle = '';
		$exists = isset($this->xml);
      if (isset($this->xml)) {
         foreach ($this->xml->surname as $surname) {
            $surnames .= htmlspecialchars($surname) . "\n";
         }
         foreach ($this->xml->place as $place) {
            $places.= htmlspecialchars($place) . "\n";
         }
         $fromYear = htmlspecialchars((string)$this->xml->from_year);
         $toYear = htmlspecialchars((string)$this->xml->to_year);
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
      else { // new article
         if (preg_match('/^\S+(\s+\S+)? in /', $this->titleString)) { // surname in place
            $pos = mb_strpos($this->titleString, ' in ');
            $surnames = htmlspecialchars(mb_substr($this->titleString, 0, $pos));
            $places = htmlspecialchars(mb_substr($this->titleString, $pos+4));
            if (mb_strpos($textbox1, '[[Category:Surname in place]]') === false) {
               $textbox1 .= "\n\n[[Category:Surname in place]]"; // add to category
            }
         }
         else if (preg_match('/research guide/i', $this->titleString)) { // research guide
            if (mb_strpos($textbox1, '[[Category:Research guides]]') === false) {
               $textbox1 .= "\n\n[[Category:Research guides]]"; // add to category
            }
         }
         else if (preg_match('/how to/i', $this->titleString)) { // how to
            if (mb_strpos($textbox1, '[[Category:How to]]') === false) {
               $textbox1 .= "\n\n[[Category:How to]]"; // add to category
            }
         }
      }
      $result .= "<br><label for=\"surnames\">Surnames (one per line):</label><br><textarea tabindex=\"1\" name=\"surnames\" rows=\"3\" cols=\"60\">$surnames</textarea>";
      $result .= "<br><label for=\"places\">Places (one per line):</label><br><textarea class=\"place_input\" tabindex=\"1\" name=\"places\" rows=\"3\" cols=\"60\">$places</textarea>";
      $result .= "<table><tr>";
      $result .= "<td align=left>Year range:</td><td align=left><input tabindex=\"1\" name=\"fromYear\" value=\"$fromYear\" size=\"5\"$fromYearStyle/>";
      $result .=           "&nbsp;&nbsp;-&nbsp;<input tabindex=\"1\" name=\"toYear\" value=\"$toYear\" size=\"5\"$toYearStyle/>";
      $result .= "</td></tr></table>Text:<br>";
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
      $result .= $this->addMultiLineFieldToXml($request->getVal('surnames', ''), 'formatSurname');
      $result .= $this->addMultiLineFieldToXml($request->getVal('places', ''), 'formatPlace');
      $result .= $this->addSingleLineFieldToXml($request->getVal('fromYear', ''), 'from_year');
      $result .= $this->addSingleLineFieldToXml($request->getVal('toYear', ''), 'to_year');
      return $result;
   }

   /**
     * Return true if xml property is valid
     */
   protected function validateData() {
      return true;
   }
}
?>
