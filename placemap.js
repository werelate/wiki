var map;
var markersArray = [];

function showPlaceMap() {
   var size = getSize();
   // get map data
   var placeData = getPlaceData();
   var xmlDoc = parseXml(placeData);
   var points = xmlDoc.documentElement.getElementsByTagName("p");

   // set map center and zoom level
   var pointsBounds = new google.maps.LatLngBounds(new google.maps.LatLng(points[0].getAttribute("a"), points[0].getAttribute("o")),
                                        new google.maps.LatLng(points[0].getAttribute("a"), points[0].getAttribute("o")));

   for (var i = 1; i < points.length; i++) {
   	var ll = new google.maps.LatLng(points[i].getAttribute("a"), points[i].getAttribute("o"));
   	pointsBounds.extend(ll);
   }
   var margin = 0.3;
   var ll = new google.maps.LatLng(pointsBounds.getSouthWest().lat(), pointsBounds.getSouthWest().lng() - margin);
   pointsBounds.extend(ll);
   var ll = new google.maps.LatLng(pointsBounds.getNorthEast().lat() + margin, pointsBounds.getNorthEast().lng() + margin);
   pointsBounds.extend(ll);
   var defaultZoom = 9;
   var opts = {
        zoomControl: true,
        center: pointsBounds.getCenter(),
        zoom: defaultZoom,
        mapTypeId: google.maps.MapTypeId.ROADMAP
    };
    if (size === 1) {
        opts.mapTypeControl = true;
        opts.overviewMapControl = true;
        opts.zoomControlOptions = { style: google.maps.ZoomControlStyle.LARGE };
    }
    else {
        opts.zoomControlOptions = { style: google.maps.ZoomControlStyle.SMALL };
    }
    map = new google.maps.Map(document.getElementById("placemap"), opts);

   google.maps.event.addListener(map, 'bounds_changed', function() {
       console.log('bounds_changed');
       for (var i = defaultZoom-1; i >= 0; i--) {
           var bounds = map.getBounds();
           if (bounds.contains(pointsBounds.getNorthEast()) && bounds.contains(pointsBounds.getSouthWest())) {
           	break;
           }
           console.log('setZoom', i);
           map.setZoom(i);
       }
       google.maps.event.clearListeners(map, 'bounds_changed');
   });
   if (size == 1) {
       google.maps.event.addListener(map, 'click', function(overlay, point) {
			if (point || (overlay && (overlay instanceof google.maps.Marker))) {
				var lat;
				var lng;
				if (point) {
	            lat = point.y;
	            lng = point.x;
				}
				else {
	            lat = overlay.getLatLng().lat();
	            lng = overlay.getLatLng().lng();
				}				
	         document.getElementById("latbox").innerHTML=Math.round(lat*1000000)/1000000;
	         document.getElementById("lngbox").innerHTML=Math.round(lng*1000000)/1000000;
				document.getElementById("latlnginst").style.display='none';
				document.getElementById("latlngbox").style.display='block';
			}
		});
   }

   addMapOverlays(size, points);
}

function parseXml(str) {
  if (window.ActiveXObject) {
    var doc = new ActiveXObject('Microsoft.XMLDOM');
    doc.loadXML(str);
    return doc;
  } else if (window.DOMParser) {
    return (new DOMParser).parseFromString(str, 'text/xml');
  }
}

function clearOverlays() {
    for (var i = 0; i < markersArray.length; i++ ) {
      markersArray[i].setMap(null);
    }
    markersArray.length = 0;
}

function addMapOverlays(size, points) {
   clearOverlays();

   var infoWindow = new google.maps.InfoWindow();

   for (var i = 0; i < points.length; i++) {
      var point = points[i];
      var lat = parseFloat(point.getAttribute("a"));
      var coords;
      if (lat >= 0) {
      	coords = lat + '&deg;N ';
      }
      else {
         coords = (lat * -1.0) + '&deg;S ';
      }
      var lng = parseFloat(point.getAttribute("o"));
      if (lng >= 0) {
      	coords = coords + lng + '&deg;E';
      }
      else {
         coords = coords + (lng * -1.0) + '&deg;W';
      }
   	var latlng = new google.maps.LatLng(lat, lng);
      var type = point.getAttribute("t");
      var name = point.getAttribute("n");
      var url = point.getAttribute("u");
      var html = '<center><b><a href="' + url + '">' + name + '</a></b></center><br>' + 
   					'<b>Type:</b> ' + type + '<br><b>Coordinates:</b> ' + coords;
   	var icon = createIcon(size);
   	var marker = createMarker(map, html, name, latlng, icon, size);
    markersArray.push(marker);
   	//map.addOverlay(marker);
   }

   function createMarker(map, html, name, latlng, icon, size) {
    var marker = new google.maps.Marker({
        icon: icon,
        position: latlng,
        clickable: size === 1,
        title: name,
        map: map
    });
   	if (size == 1) {
	  		html = '<div style="width:300px;">' + html + '</div>';
	   	google.maps.event.addListener(marker, 'click', function() {
            infoWindow.setContent(html);
            infoWindow.open(map, marker);
	   	});
   	}
   	return marker;
   }

	function createIcon(size) {
	   var icon;
	   if (size == 1) {
//		   icon.iconSize = new google.maps.Size(20, 34);
//		   icon.shadowSize = new google.maps.Size(37,34);
//		   icon.iconAnchor = new google.maps.Point(9,34);
//		   icon.infoWindowAnchor = new google.maps.Point(9, 2);
//		   icon.infoShadowAnchor = new google.maps.Point(18,25);
//		   icon.shadow = "/w/skins/common/images/maps/shadow.png";
////		   icon.transparent = "/w/skins/common/images/maps/lolly/transp.png";
//	      icon.image = "/w/skins/common/images/maps/marker.png";
//	      icon.printImage = "/w/skins/common/images/maps/marker.gif";
//	      icon.mozPrintImage = "/w/skins/common/images/maps/marker.gif";
           icon = "/w/skins/common/images/maps/marker.png";
	   }
	   else {
//		   icon.iconSize = new google.maps.Size(12, 20);
//		   icon.shadowSize = new google.maps.Size(22, 20);
//		   icon.iconAnchor = new google.maps.Point(6, 20);
//		   icon.infoWindowAnchor = new google.maps.Point(5, 1);
//		   icon.infoShadowAnchor = new google.maps.Point(9, 12);
//		   icon.shadow = "/w/skins/common/images/maps/mm_20_shadow.png";
////		   icon.transparent = "/w/skins/common/images/maps/lolly/transp.png";
//	      icon.image = "/w/skins/common/images/maps/mm_20_red.png";
//	      icon.printImage = "/w/skins/common/images/maps/mm_20_red.gif";
//	      icon.mozPrintImage = "/w/skins/common/images/maps/mm_20_red.gif";
	      icon = "/w/skins/common/images/maps/mm_20_red.png";

	   }
      return icon;
	}
}
