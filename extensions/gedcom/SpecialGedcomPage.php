<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );
require_once("$IP/extensions/gedcom/GedcomUtil.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialGedcomPageSetup";

function wfSpecialGedcomPageSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "gedcompage" => "Gedcom Page" ) );
	$wgSpecialPages['GedcomPage'] = array('SpecialPage','GedcomPage');
}

/**
 * constructor
 */
function wfSpecialGedcomPage($par) {
	global $wgOut;
	
	$gp = new GedcomPage();
	$gp->readQueryParms();
	$gp->outputPage();
}

class GedcomPage {
	var $gedcomid;
	var $gedcomkey;
	var $gedcomtab;
	var $editable;
	var $action;
	var $data;
	var $xml;
	
   public function __construct() {
   	$this->gedcomid = '';
   	$this->gedcomkey = '';
   	$this->gedcomtab = '';
   	$this->editable = false;
   	$this->action = '';
   	$this->data = null;
   	$this->xml = null;
   }

   public function readQueryParms() {
   	global $wgRequest;
		
      $this->gedcomid = $wgRequest->getVal('gedcomid');
      $this->gedcomkey = $wgRequest->getVal('gedcomkey');
      $this->gedcomtab = $wgRequest->getVal('gedcomtab');
      $this->editable = $wgRequest->getCheck('editable');
   	$this->action = $wgRequest->getVal('action');
   }
   
   public function outputPage() {
   	global $wgOut;
   	
   	if ($this->action == 'edit') {
  			$this->editPage();
  		}
   	else if ($this->action == 'submit') {
   		$this->submitPage();
   	}
  		else {
  			$this->showPage();
  		}
   }
   
	private function readXmlData() {
		$dataString = GedcomUtil::getGedcomDataString();
   	if ($dataString) {
	   	$this->xml = simplexml_load_string($dataString); // if you need attributes other than id, ns, title, save them in submitPage()
	   	if ($this->xml && $this->xml['id'] == $this->gedcomkey) {
		   	// set data
		   	$content = (string)$this->xml->content;
		   	unset($this->xml->content);
		   	$xmlString = $this->xml->asXML();
		   	$xmlString = substr($xmlString, strpos($xmlString, '<', 1)); // skip past the <?xml version="1.0"? > header
				// remove attributes from the opening tag because StructuredData::getXml doesn't expect them		   	
		   	$xmlString = preg_replace('/<(\S+)[^>]*>/', '<$1>', $xmlString, 1); 
		  		$this->data = $xmlString.$content; // append content
	   	}
	   	return true;
   	}
		else {
			return false;
		}
	}
	
	private function pageExpired() {
		global $wgOut;
		
		$wgOut->setPageTitle("Loading ($this->gedcomkey gedcom)");
		$wgOut->addWikiText("If this window does not refresh automatically in a few seconds, please click on the page in the top part of your browser");
	}

   private function cleanName($name, $firstOnly) {
      if ($firstOnly) {
         $names = explode(' ',$name,2);
         $name = $names[0];
      }
      // keep in sync with Name.cleanName in gedcom project                                + used to be below, but I had to remove it
      $name = preg_replace('`(?<=[^?~!@#$%^\&*\.()\_+=/*<>{}\[\];:"\\,/|])\s*([\[{<(].*?($|[\]}>)])|[\\/,].*$)`', '', $name);
      $name = preg_replace('`[?~!@#$%^\&*\.()\_+=/*<>{}\[\];:"\\,/|]+`', ' ', $name);
      $name = preg_replace('/\s+/', ' ', $name);
      if (preg_match("/^[-.'`]*$/", $name)) {
         $name = '';
      }
      return trim($name);
   }

   private function getPageTitle() {
      $ns = (int)$this->xml['ns'];
      if ($ns == NS_PERSON) {
         $pageTitle = StructuredData::constructName($this->cleanName(@$this->xml->name['given'], true),
                                                    $this->cleanName(@$this->xml->name['surname'], false));
      }
      else if ($ns == NS_FAMILY) {
         $husbandName = $wifeName = 'Unknown';
         foreach ($this->xml->husband as $spouse) {
            $husbandName = StructuredData::constructName($this->cleanName(@$spouse['given'], true),
                                                         $this->cleanName(@$spouse['surname'], false));
            break;
         }
         foreach ($this->xml->wife as $spouse) {
            $wifeName = StructuredData::constructName($this->cleanName(@$spouse['given'], true),
                                                      $this->cleanName(@$spouse['surname'], false));
            break;
         }
         $pageTitle = StructuredData::constructFamilyName($husbandName, $wifeName);
      }
      else {
         $pageTitle = $this->xml['title'];
      }
      return $pageTitle;
   }

   private function showPage() {
   	global $wgOut, $wgUser, $wgTitle;

   	if (!$this->readXmlData()) {
   		$this->pageExpired();
   		return;
   	}

      $ns = (int)$this->xml['ns'];
      $pageTitle = $this->getPageTitle();
		$wgOut->setPageTitle($pageTitle);
		$skin = $wgUser->getSkin();
     	$gedcomid = htmlspecialchars($this->gedcomid);
     	$gedcomtab = htmlspecialchars($this->gedcomtab);
     	$gedcomkey = htmlspecialchars($this->gedcomkey);
		if ($this->editable && ($ns == NS_PERSON || $ns == NS_FAMILY || $ns == NS_MYSOURCE)) {
         $editLink = $skin->makeKnownLinkObj($wgTitle, 'Edit', "action=edit&editable=true&gedcomid={$gedcomid}&gedcomtab={$gedcomtab}&gedcomkey={$gedcomkey}");
         if ($ns == NS_MYSOURCE) {
            $nsSrc = NS_SOURCE;
            $title = htmlspecialchars($this->xml->title);
            $author = htmlspecialchars($this->xml->author);
            $matchButton = <<<END
<div id="matchButton" class="inline-block">
<form name="search" action="/wiki/Special:Search" method="get">
<input type="hidden" name="ns" value="Source"/>
<input type="hidden" name="target" value="gedcom"/>
<input type="hidden" name="add" value="next"/>
<input type="hidden" name="st" value="$title"/>
<input type="hidden" name="a" value="$author"/>
<input type="submit" value="Find a matching Source"/>
</form>
</div>
END;
         }
         else {
            $matchButton = '';
         }
			$wgOut->setSubtitle($editLink.$matchButton);
		}
		$wgOut->addScript(<<< END
<script type="text/javascript">
//<![CDATA[
function loadGedcomPage(id){
	try {
	   if (parent && parent.review) {
	      var swf=!parent.review.window && !parent.review.document ? parent.review : (navigator.appName.indexOf("Microsoft")!=-1) ? parent.review.window["gedcom"] : parent.review.document["gedcom"];
	      if (swf && swf.loadGedcomId) swf.loadGedcomId(id);
	   }
	} catch (e) {}
}
$(document).ready(function(){
  $("#wr-content a[href$='_gedcom%29']").removeClass('new').each(function(){
    var href=$(this).attr('href'); var start=href.lastIndexOf('%28')+3; href=href.substring(start, href.indexOf('_gedcom%29', start));
    $(this).attr('href','javascript:void(0)').click(function(event) { event.preventDefault(); loadGedcomPage(href); });
  });
});
//]]>
</script>
END
		);
		$wgOut->addWikiText($this->data);
	}
	
	private function getPageObj($ns, $titleString) {
   	if ($ns == NS_PERSON) {
   		$obj = new Person($titleString);
   	}
   	else if ($ns == NS_FAMILY) {
   		$obj = new Family($titleString);
   	}
   	else {
   		$obj = new MySource($titleString);
		}
   	$obj->setGedcomPage(true);
		return $obj;
	}
	
   private function editPage() {
   	global $wgOut, $wgUser, $wgTitle;

   	if (!$this->readXmlData()) {
   		$this->pageExpired();
   		return;
   	}
   	
     	// show edit form
		$skin = $wgUser->getSkin();
      $pageTitle = $this->getPageTitle();
		$wgOut->setPageTitle("Editing ".$pageTitle);
     	$action = $wgTitle->escapeLocalURL('action=submit');
     	$gedcomid = htmlspecialchars($this->gedcomid);
     	$gedcomtab = htmlspecialchars($this->gedcomtab);
     	$gedcomkey = htmlspecialchars($this->gedcomkey);
     	$ns = htmlspecialchars((int)$this->xml['ns']);
     	$titleString = htmlspecialchars((string)$this->xml['title']);
		$cancelLink = $skin->makeKnownLinkObj($wgTitle, 'Cancel', "editable=true&gedcomid={$gedcomid}&gedcomtab={$gedcomtab}&gedcomkey={$gedcomkey}");
      $timestamp = wfTimestamp(TS_MW);

		$wgOut->addHTML(<<< END
<form id="editform" name="editform" method="post" action="$action" enctype="multipart/form-data">
<input type="hidden" name="gedcomid" value="$gedcomid"/>
<input type="hidden" name="gedcomtab" value="$gedcomtab"/>
<input type="hidden" name="gedcomkey" value="$gedcomkey"/>
<input type="hidden" name="gedcomns" value="$ns"/>
<input type="hidden" name="gedcomtitle" value="$titleString"/>
<input type="hidden" name="editable" value="true"/>
<input type='hidden' name="wpEdittime" value="$timestamp"/>
END
		);
		$obj = $this->getPageObj((int)$this->xml['ns'], (string)$this->xml['title']);
   	$editPage = (object)array('textbox1' => $this->data, 'section' => '');
   	$obj->renderEditFields($editPage);
   	$content = htmlspecialchars($editPage->textbox1);
		$wgOut->addHTML(<<< END
<textarea tabindex='1' accesskey="," name="wpTextbox1" id="wpTextbox1" rows='10' cols='80' >
$content
</textarea>  
<input id="wpSave" name="wpSave" type="submit" tabindex="5" value="Save page" accesskey="s" title="Save your changes [alt-s]"/>
$cancelLink
</form>
END
		);
   }
   
   private function submitPage() {
   	global $wgRequest, $wgOut;
   	
   	if (!$wgRequest->wasPosted() || !$wgRequest->getVal('wpEdittime')) {
   		$this->showPage();
   		return;
   	}
   	
   	// from form
   	$textbox1 = $wgRequest->getText('wpTextbox1');
   	$ns = $wgRequest->getVal('gedcomns');
   	$titleString = $wgRequest->getVal('gedcomtitle');
   	
   	$editPage = (object)array('textbox1' => $textbox1, 'section' => '');
   	$obj = $this->getPageObj($ns, $titleString);
		$obj->importEditData($editPage, $wgRequest);
		if ($obj->getTitle()) {
			$titleString = $obj->getTitle()->getPrefixedText(); // get updated title in case it was updated
		}
		$text = $editPage->textbox1;

		// grab xml and content from edited data
   	$tagName = $obj->getTagName();
		$xml = StructuredData::getXml($tagName, $text);
		$content = trim(mb_substr($text, mb_strpos($text, '</'.$tagName.'>') + strlen("</$tagName>\n")));
		
		// add id's back and remove titles
		$saveTitles = array();
		foreach ($xml->child_of_family as $r) {
			$r['id'] = GedcomUtil::getKeyFromTitle((string)$r['title']);
			if ((string)$r['id']) { $saveTitles[(string)$r['id']] = (string)$r['title']; unset($r['title']); }
		}
		foreach ($xml->spouse_of_family as $r) {
			$r['id'] = GedcomUtil::getKeyFromTitle((string)$r['title']);
			if ((string)$r['id']) { $saveTitles[(string)$r['id']] = (string)$r['title']; unset($r['title']); }
		}
		foreach ($xml->husband as $r) {
			$r['id'] = GedcomUtil::getKeyFromTitle((string)$r['title']);
			if ((string)$r['id']) { $saveTitles[(string)$r['id']] = (string)$r['title']; unset($r['title']); }
		}
		foreach ($xml->wife as $r) {
			$r['id'] = GedcomUtil::getKeyFromTitle((string)$r['title']);
			if ((string)$r['id']) { $saveTitles[(string)$r['id']] = (string)$r['title']; unset($r['title']); }
		}
		foreach ($xml->child as $r) {
			$r['id'] = GedcomUtil::getKeyFromTitle((string)$r['title']);
			if ((string)$r['id']) { $saveTitles[(string)$r['id']] = (string)$r['title']; unset($r['title']); }
		}
		foreach ($xml->source_citation as $r) {
			$r['source_id'] = GedcomUtil::getKeyFromTitle((string)$r['title']);
			if ((string)$r['source_id']) { $saveTitles[(string)$r['source_id']] = (string)$r['title']; unset($r['title']); }
		}

		// save data to db
   	$text = $xml->asXML();
   	$text = substr($text, strpos($text, '<', 1)).$content; // skip past the <?xml version="1.0"? > header and add content
      $dbw =& wfGetDB( DB_MASTER );
      $dbw->begin();
      $qtext = $dbw->addQuotes($text);
		$sql = 'INSERT INTO familytree_gedcom_data (fgd_gedcom_id, fgd_gedcom_key, fgd_text)'.
				 ' VALUES('.$dbw->addQuotes($this->gedcomid).','.$dbw->addQuotes($this->gedcomkey).','.$qtext.')'.
				 ' ON DUPLICATE KEY UPDATE fgd_text='.$qtext;
		$dbw->query($sql);
  	   $dbw->commit();
   	
		// convert to in-memory format and save to memory
		// add titles back
		foreach ($xml->child_of_family as $r) {
			if ((string)$r['id']) $r['title'] = $saveTitles[(string)$r['id']];
		}
		foreach ($xml->spouse_of_family as $r) {
			if ((string)$r['id']) $r['title'] = $saveTitles[(string)$r['id']];
		}
		foreach ($xml->husband as $r) {
			if ((string)$r['id']) $r['title'] = $saveTitles[(string)$r['id']];
		}
		foreach ($xml->wife as $r) {
			if ((string)$r['id']) $r['title'] = $saveTitles[(string)$r['id']];
		}
		foreach ($xml->child as $r) {
			if ((string)$r['id']) $r['title'] = $saveTitles[(string)$r['id']];
		}
		foreach ($xml->source_citation as $r) {
			if ((string)$r['source_id']) $r['title'] = $saveTitles[(string)$r['source_id']];
		}
		$xml->content = $content;
		$xml['id'] = $this->gedcomkey;
		$xml['ns'] = $ns;
		$xml['title'] = $titleString;
   	$text = $xml->asXML();
   	$text = substr($text, strpos($text, '<', 1)); // skip past the <?xml version="1.0"? > header
		GedcomUtil::putGedcomDataString($text);
   	
   	// add script to notify swf of update
   	$gedcomkey = htmlspecialchars($this->gedcomkey);
		$wgOut->addScript(<<< END
<script type="text/javascript">
//<![CDATA[
$(document).ready(function(){
	try {
	   if (parent && parent.review) {
	      var swf=!parent.review.window && !parent.review.document ? parent.review : (navigator.appName.indexOf("Microsoft")!=-1) ? parent.review.window["gedcom"] : parent.review.document["gedcom"];
	      if (swf && swf.pageUpdated) swf.pageUpdated("$gedcomkey");
	   }
	} catch (e) {}
});
//]]>
</script>
END
		);
   	
   	// show page
   	$this->showPage();
   }
   
}
?>