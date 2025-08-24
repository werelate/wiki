<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfRepositoryExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfRepositoryExtensionSetup() {
   global $wgHooks;
   global $wgParser;

   # Register hooks for edit UI, request handling, and save features
   $wgHooks['ArticleEditShow'][] = 'renderRepositoryEditFields';
   $wgHooks['ImportEditFormDataComplete'][] = 'importRepositoryEditData';
   $wgHooks['EditFilter'][] = 'validateRepository';

   # register the extension with the WikiText parser
   $wgParser->setHook('repository', 'renderRepositoryData');
}

/**
 * Callback function for converting repository to HTML output
 */
function renderRepositoryData( $input, $argv, $parser) {
   $repository = new Repository($parser->getTitle()->getText());
   return $repository->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderRepositoryEditFields( &$editPage ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_REPOSITORY) {
      $repository = new Repository($editPage->mTitle->getText());
      $repository->renderEditFields($editPage);
   }
   return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importRepositoryEditData( &$editPage, &$request ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_REPOSITORY) {
      $repository = new Repository($editPage->mTitle->getText());
      $repository->importEditData($editPage, $request);
   }
   return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateRepository($editPage, $textBox1, $section, &$hookError) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_REPOSITORY) {
      $repository = new Repository($editPage->mTitle->getText());
      $repository->validate($textBox1, $section, $hookError);
   }
   return true;
}

/**
 * Handles Repositories
 */
class Repository extends StructuredData {
	
	  /**
     * Construct a new source object
     */
   public function __construct($titleString) {
      parent::__construct('repository', $titleString, NS_REPOSITORY);
   }

   protected function formatUrl($url) {
   	if ($url) {
	      if (strlen($url) > 22) {
	         $urlShow = substr($url, 0, 20) . '..';
	     	}
	     	else {
	     		$urlShow = $url;
	     	}
	      return "[$url $urlShow]";
   	}
   	return '';
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

   protected function formatAddress($addr) {
   	return str_replace("\n", '<br>', $addr);
   }
   
   protected function showField($label, $node, &$hideTop) {
   	$value = (string)$node;
   	if ($value) {
   		$result = $this->addValueToTableDL($label, $value, $hideTop);
   		$hideTop = true;
   		return $result;
   	}
   	return "";
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

   /**
	 * Create wiki text from xml property
	 */
   protected function toWikiText($parser) {
      $result = '';
      if (isset($this->xml)) {
//         $result .= '{| id="structuredData" border=1 class="floatright" cellpadding=4 cellspacing=0 width=30%'."\n"
//         $result = "{|\n";
//         $result .=
//         $hideTop = false;
//			$result = "<div class=\"infobox-header\">Repository Information</div>\n{|\n"
//				.$this->showField("Place", $this->formatAsLink((string)$this->xml->place, 'Place'), $hideTop)
//				.$this->showField("URL", $this->formatUrl((string)$this->xml->url), $hideTop)
//				.$this->showField("Phone", $this->xml->phone, $hideTop)
//				.$this->showField("Postal Address", $this->formatAddress((string)$this->xml->postal_address), $hideTop)
//				$this->showWatchers()
//				."|}\n"

         // add infobox
         $result .= $this->getLV("Repository", $this->titleString, true);
         $result .= $this->getLV("Postal Address", $this->xml->postal_address);
         $result .= $this->getLV("Place", $this->xml->place, false, null, "Place");
         $result .= $this->getLV("Phone", $this->xml->phone);
         $result .= $this->getLV("URL", $this->xml->url);
         $infobox = <<<END
<div class="wr-infobox wr-infobox-repository">
<table>
$result
</table>
</div>
<wr_ad></wr_ad>
END;
			$result = $infobox.StructuredData::addCategories(array(), $this->xml->place);
      }
      return $result;
   }

   /**
     * Create edit fields from xml property
     */
   protected function toEditFields(&$textbox1) {
		global $wgOut, $wgScriptPath;

		// add javascript functions
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.11.js\"></script>");

//		$tm = new TipManager();

		$place = '';
		$url = '';
		$phone = '';
		$address = '';
		
      $result = '';
		$invalidStyle = ' style="background-color:#fdd;"';
      $urlStyle = '';

      if (isset($this->xml)) {
      	$place = htmlspecialchars((string)$this->xml->place);
      	$url = htmlspecialchars((string)$this->xml->url);
      	$phone = htmlspecialchars((string)$this->xml->phone);
      	$address = htmlspecialchars((string)$this->xml->postal_address);
      }

      if (!StructuredData::isValidUrl($url)) {
      	$urlStyle = $invalidStyle;
      	$result .= "<p><font color=red>Please enter a valid URL</font></p>";
      }
      
		// display edit fields
      $result .= "<h2>Repository information</h2><table>"
         . "<tr><td align=right>Place:</td><td align=left><input tabindex=\"1\" class=\"nocemetery_input\" name=\"place\" value=\"$place\" size=\"60\"/></td></tr>"
         . "<tr><td align=right>URL:</td><td align=left><input tabindex=\"1\" name=\"url\" value=\"$url\" size=\"60\"$urlStyle/></td></tr>"
         . "<tr><td align=right>Phone:</td><td align=left><input tabindex=\"1\" name=\"phone\" value=\"$phone\" size=\"20\"/></td></tr>"
      	. "<tr><td align=right>Postal<br/>Address</td><td align=left><textarea tabindex=\"1\" name=\"postal_address\" rows=\"3\" cols=\"60\">$address</textarea></td></tr>"
        	. "</table>"
//		   . $tm->getTipTexts()
      	."<br/>Text:<br/>";
      return $result;
   }

	private function correctPlaceTitle($place) {
//		if ($place && mb_strpos($place, '|') === false) {
		if ($place) {
			$titles = array();
			$titles[] = $place;
		   $correctedPlaces = PlaceSearcher::correctPlaceTitles($titles);
			$correctedPlace = @$correctedPlaces[$place];
			if ($correctedPlace) {
//				$place = strcasecmp($place,$correctedPlace) == 0 ? $correctedPlace : $correctedPlace . '|' . $place;
				$place = $correctedPlace;
			}
		}
		return $place;
	}

   /**
     * Return xml elements from data in request
     * @param unknown $request
     */
   protected function fromEditFields($request) {
      $result = 
           $this->addSingleLineFieldToXml($this->correctPlaceTitle($request->getVal('place')), 'place')
         . $this->addSingleLineFieldToXml($request->getVal('url'), 'url')
         . $this->addSingleLineFieldToXml($request->getVal('phone'), 'phone')
         . $this->addSingleLineFieldToXml($request->getVal('postal_address'), 'postal_address');
      return $result;
   }

   /**
     * Return true if xml property is valid
     */
   protected function validateData(&$textbox1) {
      if (!StructuredData::isRedirect($textbox1)) {
         return (StructuredData::isValidUrl((string)$this->xml->url));
      }
      return true;
   }
}
?>
