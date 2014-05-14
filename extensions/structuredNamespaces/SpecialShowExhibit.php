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
			$error = wfMsg('entertitlepersonfamily');
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
            $family = wfMsg('family');
            $person = wfMsg('person');
            $birth = wfMsg('birth');
            $death = wfMsg('death');
            $marriage = wfMsg('marriage');
            $others = wfMsg('others');
            $missing = wfMsg('missing');
            $mixed = wfMsg('mixed');
            $viewallobjects = wfMsg('viewallobjects');
            $givenname = wfMsg('givenname');
            $surname = wfMsg('surname');
            $siblingcount = wfMsg('siblingcount');
            $date = wfMsg('date');
            $location = wfMsg('location');
            $latitudelongitude = wfMsg('latitudelongitude');
            $viewsortablecolumns = wfMsg('viewsortablecolumns');
            $viewtimeline = wfMsg('viewtimeline');
            $marriagedate = wfMsg('marriagedate');
            $birthdate = wfMsg('birthdate');
            $deathdate = wfMsg('deathdate');
            $ageatdeath = wfMsg('ageatdeath');
            $vieweventmap = wfMsg('vieweventmap');
            $event = wfMsg('event');
			$divs = <<< END
	<div ex:role="collection" ex:itemTypes="Person" id="the-people"></div>
	<div ex:role="coder" ex:coderClass="Color" id="event-colors">
		  <span ex:color="#b0e0e6">$family</span>
		  <span ex:color="#66cdaa">$person</span>
		  <span ex:color="#ffa07a">$birth</span>
		  <span ex:color="#ffd700">$death</span>
		  <span ex:color="#d3d3d3">$marriage</span>
		  <span ex:case="others" ex:color="red">$others</span>
		  <span ex:case="missing" ex:color="white">$missing</span>
		  <span ex:case="mixed" ex:color="green">$mixed</span>
	</div>
	<table width="100%">
		<tr valign="top">
			<td ex:role="viewPanel">
				<div ex:role="view"
					ex:label="$viewallobjects"
 					ex:formats="date { mode: medium; show: date; }"
 				  	ex:orders=".Surname, .Given, .label"
 				  	>
					<div ex:role="lens" ex:formats="date { mode: medium; show: date; }" ex:colorCoder="event-colors"
 				  	ex.colorKey=".type">
						<div>Object Type: <span ex:content=".type"></span></div>
						<div>Label: <span ex:content=".label"></span></div>
						<div ex:if-exists=".Surname">$surname: <span ex:content=".Surname"></span></div>
						<div ex:if-exists=".Given">$givenname: <span ex:content=".Given"></span></div>
						<div ex:if-exists=".Family">$family: <a ex:content=".Family.label" ex:href-content=".Family.URL"></a></div>
						<div ex:if-exists=".Person">$person: <a ex:content=".Person.label" ex:href-content=".Person.URL"></a></div>
						<div ex:if-exists=".Birth">$birth: <span ex:content=".Birth"></span></div>
						<div ex:if-exists=".Death">$death: <span ex:content=".Death"></span></div>
						<div ex:if-exists=".Marriage">$marriage: <span ex:content=".Marriage"></span></div>
						<div ex:if-exists=".Family.Marriage">$marriage: <span ex:content=".Family.Marriage"></span></div>
						<div ex:if-exists=".SiblingCount">$siblingcount: <span ex:content=".SiblingCount"></span></div>
						<div ex:if-exists=".Date">$date: <span ex:content=".Date"></span></div>
						<div ex:if-exists=".Location">$location: <span ex:content=".Location"></span></div>
						<div ex:if-exists=".LatLon">$latitudelongitude: <span ex:content=".LatLon"></span></div>
					</div>
				</div>
				<div ex:role="exhibit-view"
					ex:viewClass="Tabular"
					ex:collectionID="the-people"
					ex:label="$viewsortablecolumns"
					ex:columns=".Given, .Surname, .Family.Marriage.Date, .Birth.Date, .Death.Date, date-range(.Birth.Date, .Death.Date, 'year'), .SiblingCount, .Family, .label"
					ex:columnLabels="$givenname, $surname, $marriagedate, $birthdate, $deathdate, $ageatdeath, $siblingcount, $family, $person"
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
					ex:label="$viewtimeline"
					ex:start=".Birth.Date"
					ex:end=".Death.Date"
					ex:marker=".Family.Marriage.Date"
					ex:densityFactor="2"
					ex:timelineHeight="600"
					ex:colorKey=".Surname">
					<div ex:role="lens" ex:formats="date { mode: medium; show: date; }" class="popup-content" style="display: none;">
						<ul>
						<li><div>$person: <a ex:content=".label" ex:href-content=".URL"></a></div></li>
						<li><div>$family: <a ex:content=".Family.label" ex:href-content=".Family.URL"></a></div></li>
						<li><div>$birthdate: <span ex:content=".Birth.Date"></span></div></li>
						<li><div>$deathdate: <span ex:content=".Death.Date"></span></div></li>
						<li><div ex:if-exists=".Family.Marriage.Date">$marriagedate: <span ex:content=".Family.Marriage.Date"></span></div></li>
						</ul>
					</div>
				</div>
	  		  	<div ex:role="view"
			  		ex:viewClass="Map"
			  		ex:label="$vieweventmap"
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
						<li><div>$event: <span ex:content=".type"></span></div></li>
						<li><div ex:if-exists=".Date">$date: <span ex:content=".Date"></span></div></li>
						<li><div ex:if-exists=".Location">$location: <span ex:content=".Location"></span></div></li>
						<li><div ex:if-exists=".LatLon">$latitudelongitude: <span ex:content=".LatLon"></span></div></li>
						<li><div ex:if-exists=".Family">$family: <a ex:content=".Family.label" ex:href-content=".Family.URL"></a></div></li>
						<li><div ex:if-exists=".Person">$person: <a ex:content=".Person.label" ex:href-content=".Person.URL"></a></div></li>
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
    $personfamilytitle = wfMsg('personorfamilytitle');
    $go = wfMsg('go');
	$form = <<< END
<form name="search" action="/wiki/Special:ShowExhibit" method="get">
	<div id="searchFormDiv" style="$queryBoxStyle">
		$personfamilytitle: <input type="text" name="pagetitle" size="24" maxlength="100" value="$titleText" onfocus="select()" />
		<input type="submit" value="$go" />
	</div>
</form>
END;

	$wgOut->addHTML($form);
}

?>
