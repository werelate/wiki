<?php
/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */
//require_once("$IP/extensions/structuredNamespaces/TipManager.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialPlaceMapSetup";

function wfSpecialPlaceMapSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "placemap" => "PlaceMap" ) );
	$wgSpecialPages['PlaceMap'] = array('SpecialPage','PlaceMap');
}

/**
 * constructor
 */
function wfSpecialPlaceMap() {
	global $wgOut, $wgRequest, $wgUser;

   $wgOut->setArticleRelated(false);
   $wgOut->setRobotpolicy('noindex,nofollow');
   $titleText = $wgRequest->getVal('pagetitle');
   $error = '';

   if ($titleText) {
      $title = Title::newFromText($titleText, NS_PLACE);
      if (is_null($title) || $title->getNamespace() != NS_PLACE || !$title->exists()) {
         $error = 'Please enter the title of a place page';
      }
      else {
   		$revision = StructuredData::getRevision($title, true);
   		if (!$revision) {
   			$error = "Place map revision not found!\n";
   		}
   		else {
				$sk = $wgUser->getSkin();
	   		$text =& $revision->getText();
	   		$xml = StructuredData::getXml('place', $text);
	      	$wgOut->setPageTitle('Map of ' . $revision->getTitle()->getText());
	      	
		      // place map
				$wgOut->addHTML('<H2>Map for ' . $sk->makeKnownLinkObj($revision->getTitle(), $revision->getTitle()->getText()) . '</H2>');
				$wgOut->addHTML('<div id="placemap-trigger">Show map</div>');
				$wgOut->addHTML('<div id="placemap" style="width: 760px; height: 520px; display: none"></div><br>');
		      $mapData = SpecialPlaceMap::getContainedPlaceMapData($xml);
		      if (!$mapData) {
		      	$mapData = SpecialPlaceMap::getSelfMapData($revision->getTitle(), $xml);
		      }
		      $wgOut->addHTML(SpecialPlaceMap::getMapScripts(1, $mapData));
		      $wgOut->addHTML('<div id="latlnginst" style="display: none">Click on the map to see the latitude and longitude at the cursor position.</div>');
		      $wgOut->addHTML('<div id="latlngbox" style="display: none">Clicked on Latitude: <span id="latbox"></span> Longitude: <span id="lngbox"></span></div><br>');
		      $unmappedPlaces = SpecialPlaceMap::getUnmappedPlaces($sk, $xml);
		      if ($unmappedPlaces) {
		      	$wgOut->addHTML("<h3>Places not on map</h3>" . $unmappedPlaces);
		      }
		      return;
   		}
      }
   }
	$wgOut->setPageTitle('Place Map');
   if ($error) {
      $wgOut->addHTML("<p><font color=red>$error</font></p>");
   }

   $queryBoxStyle = 'width:100%;text-align:center;';
   $form = <<< END
<form name="search" action="/wiki/Special:PlaceMap" method="get">
<div id="searchFormDiv" style="$queryBoxStyle">
Place title: <input type="text" name="pagetitle" size=24 maxlength="100" value="$titleText" onfocus="select()" />
<input type="submit" value="Go" />
</div>
</form>
END;

   $wgOut->addHTML($form);
}

class SpecialPlaceMap {
	public static function getMapScripts($size, $mapData) {
		global $wgGoogleMapKey, $wgScriptPath;

		$mapData = str_replace("'", "\'", $mapData);
		return
			"<script type=\"text/javascript\">/*<![CDATA[*/function getSize() { return $size; }/*]]>*/</script>".
			"<script type=\"text/javascript\">/*<![CDATA[*/function getPlaceData() { return '<places>$mapData</places>'; }/*]]>*/</script>".
			"<script type=\"text/javascript\" src=\"$wgScriptPath/placemap.10.js\"></script>".
			"<script type=\"text/javascript\">/*<![CDATA[*/".
			"document.addEventListener('DOMContentLoaded', function() {".
			"  var trigger = document.getElementById('placemap-trigger');".
			"  if (trigger) {".
			"    trigger.addEventListener('click', function() {".
			"      trigger.style.display = 'none';".
			"      document.getElementById('placemap').style.display = 'block';".
			"      var latlnginst = document.getElementById('latlnginst');".
			"      if (latlnginst) { latlnginst.style.display = 'block'; }".
			"      var script = document.createElement('script');".
			"      script.src = '//maps.googleapis.com/maps/api/js?key=$wgGoogleMapKey&callback=showPlaceMap';".
			"      document.head.appendChild(script);".
			"    });".
			"  }".
			"});".
			"/*]]>*/</script>";
	}
	
   public static function getSelfMapData($title, $xml) {
   	$result = '';
   	$lat = (string)$xml->latitude;
   	$lng = (string)$xml->longitude;
   	if ($lat && $lng && Place::isValidLatitude($lat) && Place::isValidLongitude($lng)) {
      	$titleString = (string)$title->getText();
      	$pieces = mb_split(",", $titleString, 2);
         $url = $title->getLocalURL();
         $type = (string)$xml->type;
         $result = '<p n="'.StructuredData::escapeXml(trim($pieces[0]))
                    .'" u="'.StructuredData::escapeXml($url)
                    .'" a="'.$lat
                    .'" o="'.$lng
                    .'" t="'.$type
                    ."\"/>";
   	}
      return $result;
   }
   
   public static function getContainedPlaceMapData($xml) {
   	$result = '';
      foreach ($xml->contained_place as $place) {
         $lat = (string)$place['latitude'];
         $lng = (string)$place['longitude'];
         if ($lat && $lng) {
	      	$titleString = (string)$place['place'];
	      	$pieces = mb_split(",", $titleString, 2);
	         $title = Title::newFromText($titleString, NS_PLACE);
	         $url = $title->getLocalURL();
	         $type = (string)$place['type'];
	         $result .= '<p n="'.StructuredData::escapeXml(trim($pieces[0]))
	                    .'" u="'.StructuredData::escapeXml($url)
	                    .'" a="'.$lat
	                    .'" o="'.$lng
	                    .'" t="'.$type
	                    ."\"/>";
         }
      }
      return $result;
   }
   
   public static function getUnmappedPlaces($sk, $xml) {
   	$arr = array();
      foreach ($xml->contained_place as $place) {
         $lat = (string)$place['latitude'];
         $lng = (string)$place['longitude'];
         if (!$lat || !$lng) {
         	$arr[] = (string)$place['place'];
         }
      }
      sort($arr);
   	$result = '';
      foreach ($arr as $titleString) {
      	$pieces = mb_split(",", $titleString, 2);
      	$result .= ' ' . $sk->makeKnownLink("Place:" . $titleString, trim($pieces[0]));
      }
      return $result;
   }
}
?>
