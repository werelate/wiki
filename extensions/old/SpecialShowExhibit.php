<?php
/**
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialShowExhibitSetup";

function wfSpecialShowExhibitSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "showexhibit" => "ShowExhibit" ) );
	$wgSpecialPages['ShowExhibit'] = array('SpecialPage','ShowExhibit');
}

/**
 * constructor
 */
function wfSpecialShowExhibit() {
	global $wgOut, $wgRequest, $wgScriptPath, $wgGoogleMapKey;

	$wgOut->setArticleRelated(false);
	$wgOut->setRobotpolicy('noindex,nofollow');
	$titleText = $wgRequest->getVal('pagetitle');
	$error = '';

	if ($titleText) {
		$title = Title::newFromText($titleText);
		if (is_null($title) || ($title->getNamespace() != NS_PERSON && $title->getNamespace() != NS_FAMILY) || !$title->exists()) {
			$error = 'Please enter the title of a person or family page (include the "Person:" or "Family:")';
		}
		else {
			$wgOut->setPageTitle('MIT Exhibit for ' . $title->getText());

			$t = Title::makeTitle( NS_SPECIAL, 'GetJSONData' );
			$generator = $t->getLocalUrl( 'pagetitle='.$title->getPrefixedURL());
			$wgOut->addLink(array('href' => $generator, 'type' => 'application/json', 'rel' => 'exhibit/data' ));
		  	$wgOut->addScript('<script src="/w/exhibit/src/webapp/api/exhibit-api.js" type="text/javascript"></script>');
		  	$wgOut->addScript('<script src="/w/exhibit/src/webapp/extensions/time/time-extension.js" type="text/javascript"></script>');
	 		$wgOut->addScript('<script src="/w/exhibit/src/webapp/extensions/map/map-extension.js?gmapkey=' . $wgGoogleMapKey . '" type="text/javascript"></script>');

	 		$style = <<< END
	<style>
		body {
			margin: 0.5in;
		}
		table.list {
			border:	  1px solid #ddd;
			padding:	 0.5em;
		}
		div.name {
			font-weight: bold;
			font-size:	120%;
		}
		table.exhibit-tabularView-body {
			border-style: none;
 		}
 		div.popup-content {
 			height: 200px;
 			width: 300px;
 			font-size: 11pt;
			overflow: auto; /* show the scrollbar if needed */
 		}
		div.exhibit-views-bubbleWithItems { /* used for the map bubbles with multiple items at the same location */
			height: 200px;
			width: 300px;
 			font-size: 11pt;
			overflow: auto;
		}

 		</style>
END;
		  	$wgOut->addScript($style);

			// to limit to a single type across the board: <div ex:role="collection" ex:itemTypes="Person"></div>
			// the lens is what shows up in the popup window
			// you can do things like:
			// <span ex:if-exists=".origin">from <span ex:content=".origin"></span></span>
			// <span ex:content=".company"></span>

			// the date format strings in the lens currently don't work
			// see http://www.nabble.com/Re:-Formatting-date-in-Exhibit-t4648146.html

			// with the colors: other = other attribute than what's listed, missing = no attribute, mixed means two different kinds ended up at the same place



			// MIT Exhibit divs:
			$divs = <<< END
	<div ex:role="collection" ex:itemTypes="Person" id="the-people"></div>
	<div ex:role="coder" ex:coderClass="Color" id="event-colors">
		  <span ex:color="#b0e0e6">Family</span>
		  <span ex:color="#66cdaa">Person</span>
		  <span ex:color="#ffa07a">Birth</span>
		  <span ex:color="#ffd700">Death</span>
		  <span ex:color="#d3d3d3">Marriage</span>
		  <span ex:case="others" ex:color="red">Others</span>
		  <span ex:case="missing" ex:color="white">Missing</span>
		  <span ex:case="mixed" ex:color="green">Mixed</span>
	</div>
	<table width="100%">
		<tr valign="top">
			<td ex:role="viewPanel">
				<div ex:role="view"
					ex:label="View All Objects"
 					ex:formats="date { mode: medium; show: date; }"
 				  	ex:orders=".Surname, .Given, .label"
 				  	>
					<div ex:role="lens" ex:formats="date { mode: medium; show: date; }" ex:colorCoder="event-colors"
 				  	ex.colorKey=".type">
						<div>Object Type: <span ex:content=".type"></span></div>
						<div>Label: <span ex:content=".label"></span></div>
						<div ex:if-exists=".Surname">Surname: <span ex:content=".Surname"></span></div>
						<div ex:if-exists=".Given">Given Name: <span ex:content=".Given"></span></div>
						<div ex:if-exists=".Family">Family: <a ex:content=".Family.label" ex:href-content=".Family.URL"></a></div>
						<div ex:if-exists=".Person">Person: <a ex:content=".Person.label" ex:href-content=".Person.URL"></a></div>
						<div ex:if-exists=".Birth">Birth: <span ex:content=".Birth"></span></div>
						<div ex:if-exists=".Death">Death: <span ex:content=".Death"></span></div>
						<div ex:if-exists=".Marriage">Marriage: <span ex:content=".Marriage"></span></div>
						<div ex:if-exists=".Family.Marriage">Marriage: <span ex:content=".Family.Marriage"></span></div>
						<div ex:if-exists=".SiblingCount">Sibling Count: <span ex:content=".SiblingCount"></span></div>
						<div ex:if-exists=".Date">Date: <span ex:content=".Date"></span></div>
						<div ex:if-exists=".Location">Location: <span ex:content=".Location"></span></div>
						<div ex:if-exists=".LatLon">Latitude, Longitude: <span ex:content=".LatLon"></span></div>
					</div>
				</div>
				<div ex:role="exhibit-view"
					ex:viewClass="Tabular"
					ex:collectionID="the-people"
					ex:label="View Sortable Columns"
					ex:columns=".Given, .Surname, .Family.Marriage.Date, .Birth.Date, .Death.Date, date-range(.Birth.Date, .Death.Date, 'year'), .SiblingCount, .Family, .label"
					ex:columnLabels="Given Name, Surname, Marriage Date, Birth Date, Death Date, Age at Death, Sibling Count, Family, Person"
					ex:columnFormats="text, text, date { mode: medium; show: date; }, date { mode: medium; show: date; }, date { mode: medium; show: date; }, number, number, item, item"
					ex:sortColumn="1"
					ex:border="1"
					ex:cellSpacing="3"
					ex:cellPadding="3"
					ex:sortAscending="true">
					<div ex:role="lens" class="popup-content" style="display: none;">
						<div>Page link: <a ex:content=".label" ex:href-content=".URL"></a></div>
					</div>
				</div>
				<div ex:role="view"
					ex:viewClass="Timeline"
					ex:label="View Timeline"
					ex:start=".Birth.Date"
					ex:end=".Death.Date"
					ex:marker=".Family.Marriage.Date"
					ex:densityFactor="2"
					ex:timelineHeight="600"
					ex:colorKey=".Surname">
					<div ex:role="lens" ex:formats="date { mode: medium; show: date; }" class="popup-content" style="display: none;">
						<ul>
						<li><div>Person: <a ex:content=".label" ex:href-content=".URL"></a></div></li>
						<li><div>Family: <a ex:content=".Family.label" ex:href-content=".Family.URL"></a></div></li>
						<li><div>Birth Date: <span ex:content=".Birth.Date"></span></div></li>
						<li><div>Death Date: <span ex:content=".Death.Date"></span></div></li>
						<li><div ex:if-exists=".Family.Marriage.Date">Marriage Date: <span ex:content=".Family.Marriage.Date"></span></div></li>
						</ul>
					</div>
				</div>
	  		  	<div ex:role="view"
			  		ex:viewClass="Map"
			  		ex:label="View Event Map"
					ex:latlng=".LatLon"
					ex:colorKey=".type"
					ex:center="32, -85"
					ex:zoom="3"
					ex:size="small"
					ex:scaleControl="true"
					ex:overviewControl="true"
					ex:type="normal"
					ex:bubbleTip="top"
					ex:mapHeight="600"
					ex:colorCoder="event-colors">
					<div ex:role="lens" ex:formats="date { mode: medium; show: date; }" class="popup-content" style="display: none;">
						<ul>
						<li><div>Event: <span ex:content=".type"></span></div></li>
						<li><div ex:if-exists=".Date">Date: <span ex:content=".Date"></span></div></li>
						<li><div ex:if-exists=".Location">Location: <span ex:content=".Location"></span></div></li>
						<li><div ex:if-exists=".LatLon">Latitude, Longitude: <span ex:content=".LatLon"></span></div></li>
						<li><div ex:if-exists=".Family">Family: <a ex:content=".Family.label" ex:href-content=".Family.URL"></a></div></li>
						<li><div ex:if-exists=".Person">Person: <a ex:content=".Person.label" ex:href-content=".Person.URL"></a></div></li>
						</ul>
					</div>
		 		</div>
			</td>
			<td width="20%">
				<b>Search</b>
				<div ex:role="facet" ex:facetClass="TextSearch"></div>
				<div ex:role="facet" ex:expression=".type" ex:facetLabel="Object Type"></div>
				<div ex:role="facet" ex:expression=".Surname" ex:facetLabel="Surname"></div>
				<div ex:role="facet" ex:expression=".Given" ex:facetLabel="Given Name"></div>
				<div ex:role="facet" ex:formats="date { mode: medium; show: date; }" ex:expression=".Date" ex:facetLabel="Date"></div>
				<div ex:role="facet" ex:expression=".Location" ex:facetLabel="Location"></div>
				<div ex:role="facet" ex:expression=".LatLon" ex:facetLabel="LatLon"></div>
 			</td>
		</tr>
	</table>
END;
			$wgOut->addHTML($divs);

// there may be a time when a numeric search comes in handy:
//  			<div ex:role="facet"
//				  ex:facetClass="NumericRange"
//				  ex:expression=".ChildCount"
//				  ex:facetLabel="Child Count"
//				  ex:interval="1"
//				</div>
//  			<div ex:role="facet"
//				  ex:facetClass="NumericRange"
//				  ex:expression=".SiblingCount"
//				  ex:facetLabel="Sibling Count"
//				  ex:interval="1"
//				</div>


			return;
		}
	}
	$wgOut->setPageTitle('Show Exhibit');
	if ($error) {
		$wgOut->addHTML("<p><font color=red>$error</font></p>");
	}

	$queryBoxStyle = 'width:100%;text-align:center;';
	$form = <<< END
<form name="search" action="/wiki/Special:ShowExhibit" method="get">
	<div id="searchFormDiv" style="$queryBoxStyle">
		Person or Family page title: <input type="text" name="pagetitle" size="24" maxlength="100" value="$titleText" onfocus="select()" />
		<input type="submit" value="Go" />
	</div>
</form>
END;

	$wgOut->addHTML($form);
}

?>
