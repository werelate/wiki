<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfNameExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfNameExtensionSetup() {
	global $wgHooks;
	global $wgParser;

	# Register hooks for edit UI, request handling, and save features
	$wgHooks['ArticleEditShow'][] = 'renderNameEditFields';
	$wgHooks['ImportEditFormDataComplete'][] = 'importNameEditData';
	$wgHooks['EditFilter'][] = 'validateName';

	# register the extension with the WikiText parser
	$wgParser->setHook('givenname', 'renderGivenData');
	$wgParser->setHook('surname', 'renderSurnameData');
}

/**
 * Callback function for converting given name data to HTML output
 */
function renderGivenData( $input, $argv, $parser) {
    $name = new Name(NS_GIVEN_NAME, $parser->getTitle()->getText());
    return $name->renderData($input, $parser);
}

/**
 * Callback function for converting surname data to HTML output
 */
function renderSurnameData( $input, $argv, $parser) {
    $name = new Name(NS_SURNAME, $parser->getTitle()->getText());
    return $name->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderNameEditFields( &$editPage ) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_GIVEN_NAME || $ns == NS_SURNAME) {
        $name = new Name($ns, $editPage->mTitle->getText());
        $name->renderEditFields($editPage);
    }
    return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importNameEditData( &$editPage, &$request ) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_GIVEN_NAME || $ns == NS_SURNAME) {
        $name = new Name($ns, $editPage->mTitle->getText());
        $name->importEditData($editPage, $request);
    }
    return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateName($editPage, $textBox1, $section, &$hookError) {
	$ns = $editPage->mTitle->getNamespace();
    if ($ns == NS_GIVEN_NAME || $ns == NS_SURNAME) {
        $name = new Name($ns, $editPage->mTitle->getText());
        $name->validate($textBox1, $section, $hookError);
    }
    return true;
}

/**
 * Handles given names and surnames
 */
class Name extends StructuredData {

    /**
     * Construct a new name object
     * @param string $tagName the name of the tag for this namespace
     */
    public function __construct($ns, $titleString) {
		parent::__construct(StructuredData::getNamespaceTag($ns), $titleString, $ns);
	}
	
	protected function formatName($value, $dummy) {
	   return "<dt>[[$this->tagName:{$value['name']}|{$value['name']}]]<dd>{$value['source']}\n";
	}

	/**
	 * Create wiki text from xml property
	 */
    protected function toWikiText($parser) {
       $result = '';
//		$result = '{| id="structuredData" border=1 class="floatright" cellpadding=4 cellspacing=0 width=40%'."\n";
//		$result = "<div class=\"infobox-header\">Related Names</div>\n{|\n";
      if (isset($this->xml)) {
//				$result .= $this->addValuesToTableDL(' ', $this->xml->related, 'formatName', null);
//            $result .= "|-\n|\n{| border=0\n";
//	        foreach ($this->xml->related as $related) {
//	            $result .= "|-\n|[[$this->tagName:{$related['name']}|{$related['name']}]]||{$related['source']}\n";
//	        }
//            $result .= "|}\n";
         $names = '';
         foreach ($this->xml->related as $related) {
            $names .= "<li>[[{$this->tagName}:{$related['name']}|{$related['name']}]]";
         }
         $result = "<div class=\"wr-infobox wr-infobox-name\"><div class=\"wr-infobox-heading\">Related Names</div><ul>$names</ul></div>";
      }
//   	$result .= $this->showWatchers();
//		$result .= "|}\n";
		if ($this->tagName == 'surname') {
			$result .= "[[Category:$this->titleString surname|*]]\n";
		}
		return $result;
    }

    /**
     * Create edit fields from xml property
     */
    protected function toEditFields(&$textbox1) {
        $relatedNames = '';
        if (isset($this->xml)) {
	        foreach ($this->xml->related as $related) {
	            $relatedNames .= htmlspecialchars($related['name']) .'|' . htmlspecialchars($related['source']) ."\n";
	        }
        }

		return "<br>Related names (one per line): Name | Source<br>".
		"<div style=\"margin: 8px 0\"><strong>Important:</strong> Instead of editing related names here, ".
		"please edit them on the <a href=\"https://www.werelate.org/wiki/WeRelate:Variant_names_project\">Variant names project</a>, ".
		"where they will be used as alternate names in search.</strong></div>".
		"<textarea tabindex=\"1\" name=\"related\" rows=\"20\" cols=\"50\">$relatedNames</textarea><br>Text:<br>";
    }

    /**
     * Return xml elements from data in request
     * @param unknown $request
     */
    protected function fromEditFields($request) {
		$related = $request->getVal('related', '');
		$search = array('/ *\r?\n */', '/\\|/');
		// add space before bar so things sort right
		$replace = array("\n", ' |');
		$related = trim(preg_replace($search, $replace, $related));
		$relNames = preg_split('/[\n]+/', $related, -1, PREG_SPLIT_NO_EMPTY);
		sort($relNames, SORT_STRING);
        $prevName = '';
        $result = '';
        foreach ($relNames as $relName) {
			$fields = explode('|', $relName, 2);
            $name = StructuredData::escapeXml(StructuredData::standardizeNameCase(trim(@$fields[0]), false));
            $source = StructuredData::escapeXml(trim(@$fields[1]));
            // remove any space before bar in source: links that would have been added above
            $source = str_replace(' |', '|', $source);
            if ($name != $prevName) {
                $result .= "<related name=\"$name\" source=\"$source\"/>\n";
            }
            $prevName = $name;
        }
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
