<?php
/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialShowPedigreeSetup";

function wfSpecialShowPedigreeSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "showpedigree" => "ShowPedigree" ) );
	$wgSpecialPages['ShowPedigree'] = array('SpecialPage','ShowPedigree');
}

/**
 * constructor
 */
function wfSpecialShowPedigree() {
	global $wgOut, $wgRequest, $wgScriptPath, $wgGoogleMapKey, $wrHostName, $wrSidebarHtml;

	$wgOut->setArticleRelated(false);
	$wgOut->setRobotpolicy('noindex,nofollow');
	$titleText = $wgRequest->getVal('pagetitle');
	$error = '';

	if ($titleText) {
		$title = Title::newFromText($titleText);
      if (!$title->exists() && !StructuredData::endsWith($titleText, ')')) {
         $title = Title::newFromText($titleText.')');
      }
		if (is_null($title) || ($title->getNamespace() != NS_PERSON && $title->getNamespace() != NS_FAMILY) || !$title->exists()) {
			$error = 'Please enter the title of a person or family page (include the "Person:" or "Family:")';
		}
		else {
			$wgOut->setPageTitle('Pedigree for ' . $title->getText());
			$wgOut->addLink(array('href' => $wgScriptPath.'/index.php?action=ajax&rs=wfGetPedigreeData&rsargs='.$title->getPrefixedURL(),
										 'type' => 'application/json', 'rel' => 'exhibit/data' ));
		  	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/exhibit/src/webapp/api/exhibit-api.2.js\"></script>");
		  	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/exhibit/src/webapp/extensions/time/time-extension.1.js\"></script>");
		  	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/exhibit/src/webapp/extensions/map/map-extension.1.js?gmapkey=$wgGoogleMapKey\"></script>");
		  	$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/exhibit.6.js\"></script>");
         $wrSidebarHtml = <<<END
<div id="wr-pedigreemap-sidebar">
   <div ex:role="facet" ex:facetClass="TextSearch"></div>
   <div ex:role="facet" ex:height="165px" ex:expression=".Line" ex:facetLabel="Line"></div>
   <div ex:role="facet" ex:height="150px" ex:expression=".Generation" ex:facetLabel="Generation"></div>
   <div ex:role="facet" ex:height="75px" ex:facetClass="NumericRange" ex:expression=".BirthYear" ex:interval="10" ex:facetLabel="Birth Year"></div>
   <div ex:role="facet" ex:height="75px" ex:facetClass="NumericRange" ex:expression=".DeathYear" ex:interval="10" ex:facetLabel="Death Year"></div>
</div>   
END;

			$wgOut->addHtml(<<< END
<div ex:role="viewPanel">
   <table ex:role="lens" ex:itemTypes="Person" class="exhibit-lens">
       <tr>
           <td><span ex:if-exists=".ImageURL"><img ex:src-content=".ImageURL" /></span></td>
           <td>
            <div>
               <span ex:if=".FullName <> ''">
                  <span ex:content=".FullName" class="exhibit-name"></span>
                  <span ex:content=".label" class="exhibit-name"></span>
               </span>
            </div>
            <div>
               <span ex:if=".Parents <> ''">
                  <span ex:content=".Parents">
                     <span ex:content="value" ex:formats="item { title: expression('Birth') }"></span>
                  </span>
                  <span>Birth</span>
               </span>:
               <span ex:content=".BirthDate"></span><span ex:if-exists=".BirthPlaceText">,</span>
               <span ex:if=".BirthPlace <> ''">
                  <span><a ex:href-subcontent="http://$wrHostName/wiki/{{.BirthPlace}}"><span ex:content=".BirthPlaceText" class="exhibit-place"></span></a></span>
                  <span ex:content=".BirthPlaceText" class="exhibit-place"></span>
               </span>
            </div>
            <span ex:content=".Spouse">
               <div>
                  <span ex:content="value" ex:formats="item { title: expression('Marriage') }"></span>:
                  <span ex:content=".MarriageDate"></span><span ex:if-exists=".MarriagePlaceText">,</span>
                  <span ex:if=".MarriagePlace <> ''">
                     <span><a ex:href-subcontent="http://$wrHostName/wiki/{{.MarriagePlace}}"><span ex:content=".MarriagePlaceText" class="exhibit-place"></span></a></span>
                     <span ex:content=".MarriagePlaceText" class="exhibit-place"></span>
                  </span>
               </div>
            </span>
            <div>Death:
               <span ex:content=".DeathDate"></span><span ex:if-exists=".DeathPlaceText">,</span>
               <span ex:if=".DeathPlace <> ''">
                  <span><a ex:href-subcontent="http://$wrHostName/wiki/{{.DeathPlace}}"><span ex:content=".DeathPlaceText" class="exhibit-place"></span></a></span>
                  <span ex:content=".DeathPlaceText" class="exhibit-place"></span>
               </span>
            </div>
            <span ex:content=".OtherEvents">
               <div>
                  <span ex:content=".EventType"></span>:
                  <span ex:content=".Date"></span><span ex:if-exists=".PlaceText">,</span>
                  <span ex:if=".Place <> ''">
                     <span><a ex:href-subcontent="http://$wrHostName/wiki/{{.Place}}"><span ex:content=".PlaceText" class="exhibit-place"></span></a></span>
                     <span ex:content=".PlaceText" class="exhibit-place"></span>
                  </span>
               </div>
            </span>
            <div class="exhibit-more">
               ( <a ex:href-subcontent="http://$wrHostName/wiki/{{.label}}">more...</a> )
            </div>
        </td>
       </tr>
   </table>
   <table ex:role="lens" ex:itemtypes="Family" class="exhibit-lens">
       <tr>
         <td><span ex:if-exists=".ImageURL"><img ex:src-content=".ImageURL" /></span></td>
         <td>
            <span ex:content=".Husband">
               <div>Husband:
                  <span ex:if=".FullName <> ''">
                     <span ex:content="value" ex:formats="item { title: expression(.FullName) }"></span>
                     <span ex:content="value"></span>
                  </span>
               </div>
            </span>
            <span ex:content=".Wife">
               <div>Wife:
                  <span ex:if=".FullName <> ''">
                     <span ex:content="value" ex:formats="item { title: expression(.FullName) }"></span>
                     <span ex:content="value"></span>
                  </span>
               </div>
            </span>
            <div>Marriage:
               <span ex:content=".MarriageDate"></span><span ex:if-exists=".MarriagePlaceText">,</span>
               <span ex:if=".MarriagePlace <> ''">
                  <span><a ex:href-subcontent="http://$wrHostName/wiki/{{.MarriagePlace}}"><span ex:content=".MarriagePlaceText" class="exhibit-place"></span></a></span>
                  <span ex:content=".MarriagePlaceText" class="exhibit-place"></span>
               </span>
            </div>
            <span ex:content=".OtherEvents">
               <div>
                  <span ex:content=".EventType"></span>:
                  <span ex:content=".Date"></span><span ex:if-exists=".PlaceText">,</span>
                  <span ex:if=".Place <> ''">
                     <span><a ex:href-subcontent="http://$wrHostName/wiki/{{.Place}}"><span ex:content=".PlaceText" class="exhibit-place"></span></a></span>
                     <span ex:content=".PlaceText" class="exhibit-place"></span>
                  </span>
               </div>
            </span>
            <span ex:if-exists=".Child">
               <div>Children:
                  <ol class="exhibit-children">
                     <span ex:content=".Child">
                        <li>
                           <span ex:if=".FullName <> ''">
                              <span ex:content="value" ex:formats="item { title: expression(.FullName) }"></span>
                              <span ex:content="value" ex:formats="item { title: expression(.label) }"></span>
                           </span>
                        </li>
                     </span>
                  </ol>
               </div>
            </span>
            <div class="exhibit-more">
               ( <a ex:href-subcontent="http://$wrHostName/wiki/{{.label}}">more...</a> )
            </div>
         </td>
       </tr>
   </table>
   <table ex:role="lens" ex:itemtypes="External" class="exhibit-lens">
       <tr>
           <td>
               <div>
                  <span ex:if=".FullName <> ''">
                     <span ex:content=".FullName" class="exhibit-name"></span>
                     <span ex:content=".label"></span>
                  </span>
               </div>
               <div class="exhibit-more">
                  ( <a ex:href-subcontent="http://$wrHostName/wiki/{{.label}}">more...</a> )
               </div>
           </td>
       </tr>
   </table>
   <div ex:role="view"
      ex:viewClass="Template"
      ex:label="Pedigree"
      ex:title="View pedigree"
      ex:template="exhibitPersonPedigreeTemplate"
      ex:lenses="exhibitLenses"
      ex:slotIDPrefix="exhibit-pedigree-"
      ex:slotKey="Position"
      ex:slotClass="exhibit-pedigree-box"
     ex:showSummary="false"
      ex:showToolbox="false">
 </div>
 <div ex:role="view"
     ex:viewClass="Map"
     ex:label="Birth Places"
    ex:title="View birth places on a map"
     ex:latlng=".addressLatLng"
     ex:maxAutoZoom='9'
     ex:mapHeight='435'
     ex:proxy=".BirthPlace"
     ex:icon=".IconURL"
     ex:colorKey=".Surname"
     ex:colorMarkerGenerator="wrExhibitColorMarkerGenerator"
     ex:makeIcon="wrExhibitMakeIcon"
     ex:showSummary="false"
      ex:showToolbox="false">
 </div>
 <div ex:role="view"
     ex:viewClass="Map"
     ex:label="Death Places"
    ex:title="View death places on a map"
     ex:latlng=".addressLatLng"
     ex:maxAutoZoom='9'
     ex:mapHeight='435'
     ex:proxy=".DeathPlace"
     ex:icon=".IconURL"
     ex:colorKey=".Surname"
     ex:colorMarkerGenerator="wrExhibitColorMarkerGenerator"
     ex:makeIcon="wrExhibitMakeIcon"
     ex:showSummary="false"
      ex:showToolbox="false">
 </div>
 <div ex:role="view"
     ex:viewClass="Map"
     ex:label="All Places"
     ex:title="View all event places on a map"
     ex:latlng=".addressLatLng"
     ex:maxAutoZoom='9'
     ex:mapHeight='435'
     ex:proxy=".AllPlaces"
     ex:icon=".IconURL"
     ex:colorKey=".Surname"
     ex:addOverlaysCallback="wrExhibitMapAddOverlays"
     ex:colorMarkerGenerator="wrExhibitColorMarkerGenerator"
     ex:makeIcon="wrExhibitMakeIcon"
     ex:showSummary="false"
     ex:showToolbox="false">
 </div>
 <div ex:role="view"
       ex:viewClass="Timeline"
       ex:label="Timeline"
       ex:title="View people as timeline"
       ex:timelineHeight='435'
       ex:topBandHeight='86'
       ex:bottomBandHeight='14'
       ex:start=".BirthYear"
       ex:end=".DeathYear"
       ex:eventLabel=".FullName"
       ex:topBandUnit="decade"
       ex:topBandPixelsPerUnit="55"
       ex:bottomBandUnit="century"
       ex:bottomBandPixelsPerUnit="135"
       ex:colorKey=".Surname"
     ex:showSummary="false"
      ex:showToolbox="false">
 </div>
 <div ex:role="view"
       ex:viewClass="Thumbnail"
       ex:label="Thumbnails"
       ex:title="View people as thumbnails"
     ex:orders=".Surname"
     ex:possibleOrders=".Surname, .Givenname, .Generation, .BirthYear, .DeathYear"
     ex:grouped="true"
        ex:showSummary="false"
      ex:showToolbox="false">
      <table ex:role="lens" class="exhibit-thumbnail" style="display:none;"><tr><td>
         <div class="center"><span ex:if-exists=".ImageURL"><img ex:src-content=".ImageURL"/></span></div>
         <div><span ex:content="value" ex:formats="item { title: expression(.FullName) }"></span></div>
         <div>b. <span ex:content=".BirthDate"></span></div>
         <div>d. <span ex:content=".DeathDate"></span></div>
      </td></tr></table>
   </div>
    <div ex:role="view"
         ex:viewClass="Tile"
         ex:label="Details"
         ex:title="View details"
        ex:orders=".Surname, .Givenname"
        ex:possibleOrders=".Surname, .Givenname, .Generation, .BirthYear, .DeathYear"
        ex:grouped="false"
        ex:showSummary="false"
         ex:showToolbox="false">
    </div>
 </div>
<div ex:role="collection" ex:itemTypes="Person" style="display:none"></div>
END
			);
			return;
		}
	}
	$wgOut->setPageTitle('Show Pedigree');
	if ($error) {
		$wgOut->addHTML("<p><font color=red>$error</font></p>");
	}

	$queryBoxStyle = 'width:100%;text-align:center;';
    $personfamilytitle = wfMsg('personfamilytitle');
	$form = <<< END
<form name="search" action="/wiki/Special:ShowPedigree" method="get">
<div id="searchFormDiv" style="$queryBoxStyle">
$personfamilytitle<input type="text" name="pagetitle" size="24" maxlength="100" value="$titleText" onfocus="select()" />
<input type="submit" value="Go" />
</div>
</form>
END;

	$wgOut->addHTML($form);
}
?>