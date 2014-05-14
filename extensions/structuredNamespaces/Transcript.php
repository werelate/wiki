<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfTranscriptExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfTranscriptExtensionSetup() {
   global $wgHooks;
   global $wgParser;

   # Register hooks for edit UI, request handling, and save features
   $wgHooks['ArticleEditShow'][] = 'renderTranscriptEditFields';
   $wgHooks['ImportEditFormDataComplete'][] = 'importTranscriptEditData';
   $wgHooks['EditFilter'][] = 'validateTranscript';

   # register the extension with the WikiText parser
   $wgParser->setHook('transcript', 'renderTranscriptData');
}

/**
 * Callback function for converting transcript to HTML output
 */
function renderTranscriptData( $input, $argv, $parser) {
   $transcript = new Transcript($parser->getTitle()->getText());
   return $transcript->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderTranscriptEditFields( &$editPage ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_TRANSCRIPT) {
      $transcript = new Transcript($editPage->mTitle->getText());
      $transcript->renderEditFields($editPage);
   }
   return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importTranscriptEditData( &$editPage, &$request ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_TRANSCRIPT) {
      $transcript = new Transcript($editPage->mTitle->getText());
      $transcript->importEditData($editPage, $request);
   }
   return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateTranscript($editPage, $textBox1, $section, &$hookError) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_TRANSCRIPT) {
      $transcript = new Transcript($editPage->mTitle->getText());
      $transcript->validate($textBox1, $section, $hookError);
   }
   return true;
}

/**
 * Handles Repositories
 */
class Transcript extends StructuredData {
   private $correctedPlaceTitles;

	  /**
     * Construct a new source object
     */
   public function __construct($titleString) {
      parent::__construct('transcript', $titleString, NS_TRANSCRIPT);
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
      if (is_string($value)) {
         if ($linkNamespace) {
            $values = $this->formatAsLink($value, $linkNamespace);
         }
         else {
            $values = $value;
         }
      }
      else {
         foreach ($value as $v) {
            if ($values) $values .= '<br>';
            $v = str_replace("\n", '<br>', (string)$v);
            if ($linkNamespace) {
               $v = $this->formatAsLink($v, $linkNamespace);
            }
            $values .= $v;
         }
      }
      if (!$values) return '';
      return <<<END
<tr>
   <td class="wr-infobox-label">$label</td>
   <td>$values</td>
</tr>
END;
   }

   /**
	 * Create wiki text from xml property
	 */
   protected function toWikiText($parser) {
      $result = '';
      if (isset($this->xml)) {
         // add infobox
         $source = $this->getLV("Source", $this->xml->source, "Source");
         $surnames = $this->getLV("Surnames", $this->xml->surname, 'Surname');
         $places = $this->getLV("Places", $this->xml->place, 'Place');
         $yearRange = (string)$this->xml->from_year;
         if ((string)$this->xml->to_year) {
            $yearRange .= ' - '.(string)$this->xml->to_year;
         }
         $yearRange = $this->getLV("Year range", $yearRange);
         $result = <<<END
<div class="wr-infobox wr-infobox-transcript">
<table>
$source
$surnames
$places
$yearRange
</table>
</div>
END;
         $result .= StructuredData::addCategories($this->xml->surname, $this->xml->place, true, $this->titleString, $this->ns);
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

		//$tm = new TipManager();

		$source = '';

      $result = '';
		$invalidStyle = ' style="background-color:#fdd;"';
      $surnames = '';
      $places = '';
      $fromYear = '';
      $fromYearStyle = '';
      $toYear = '';
      $toYearStyle = '';
      $sourceStyle = '';

      if (isset($this->xml)) {
      	$source = htmlspecialchars((string)$this->xml->source);
         if ($source && !StructuredData::titleExists(NS_SOURCE, $source)) {
            $sourceStyle = $invalidStyle;
            $result .= "<p><font color=red>".wfMsg('entertitlesource')."</font></p>";
         }
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


		// display edit fields
      $result .= "<h2>".wfMsg('transcriptinformation')."</h2><table>"
         . "<tr><td align=right>".wfMsg('source').":</td><td align=left><input tabindex=\"1\" class=\"source_input\" name=\"source\" value=\"$source\" size=\"60\"$sourceStyle/></td></tr></table>"
         . "<br><label for=\"surnames\">".wfMsg('surnamesperline')."</label><br><textarea tabindex=\"1\" name=\"surnames\" rows=\"3\" cols=\"60\">$surnames</textarea>"
         . "<br><label for=\"places\">".wfMsg('placesperline')."</label><br><textarea class=\"place_input\" tabindex=\"1\" name=\"places\" rows=\"3\" cols=\"60\">$places</textarea>"
         . "<table><tr>"
         . "<td align=left>".wfMsg('yearrange')."</td><td align=left><input tabindex=\"1\" name=\"fromYear\" value=\"$fromYear\" size=\"5\"$fromYearStyle/>"
         . "&nbsp;&nbsp;-&nbsp;<input tabindex=\"1\" name=\"toYear\" value=\"$toYear\" size=\"5\"$toYearStyle/>"
         . "</td></tr></table>"
		   //. $tm->getTipTexts()
      	."<br/>".wfMsg('text:')."<br/>";
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
      $result .= $this->addSingleLineFieldToXml($request->getVal('source'), 'source');
      $result .= $this->addMultiLineFieldToXml($request->getVal('surnames', ''), 'formatSurname');
      $result .= $this->addMultiLineFieldToXml($request->getVal('places', ''), 'formatPlace');
      $result .= $this->addSingleLineFieldToXml($request->getVal('fromYear', ''), 'from_year');
      $result .= $this->addSingleLineFieldToXml($request->getVal('toYear', ''), 'to_year');
      return $result;
   }

   /**
     * Return true if xml property is valid
     */
   protected function validateData(&$textbox1) {
      $source = (string)$this->xml->source;
      return !$source || StructuredData::titleExists(NS_SOURCE, $source);
   }
}
?>
