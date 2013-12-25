function ShowMap() {
   var pointString = getSourcePoints();
   var xmlDoc = parseXml(pointString);
   var points = xmlDoc.documentElement.getElementsByTagName("point");

   var pointsBounds = new google.maps.LatLngBounds(new google.maps.LatLng(points[0].getAttribute("lat"), points[0].getAttribute("lng")),
                                        new google.maps.LatLng(points[0].getAttribute("lat"), points[0].getAttribute("lng")));
   for (var i = 1; i < points.length; i++) {
   	var ll = new google.maps.LatLng(points[i].getAttribute("lat"), points[i].getAttribute("lng"));
   	pointsBounds.extend(ll);
   }
   var margin = 0.3;
   var ll = new google.maps.LatLng(pointsBounds.getSouthWest().lat(), pointsBounds.getSouthWest().lng() - margin);
   pointsBounds.extend(ll);
   var ll = new google.maps.LatLng(pointsBounds.getNorthEast().lat() + margin, pointsBounds.getNorthEast().lng() + margin);
   pointsBounds.extend(ll);

   var defaultZoom = 9;
    var map = new google.maps.Map(document.getElementById("sourcemap"), {
        center: pointsBounds.getCenter(),
        zoom: defaultZoom,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        mapTypeControl: true,
        zoomControl: true,
        zoomControlOptions: {
            style: google.maps.ZoomControlStyle.LARGE
        }
    });
    google.maps.event.addListener(map, 'bounds_changed', function() {
        for (var i = defaultZoom-1; i >= 0; i--) {
            var bounds = map.getBounds();
            if (bounds.contains(pointsBounds.getNorthEast()) && bounds.contains(pointsBounds.getSouthWest())) {
            	break;
            }
            map.setZoom(i);
        }
        google.maps.event.clearListeners(map, 'bounds_changed');
    });

   var infoWindow = new google.maps.InfoWindow();

   for (var i = 0; i < points.length; i++) {
   	var point = new google.maps.Point(points[i].getAttribute("lng"), points[i].getAttribute("lat"));
   	var html = points[i].getAttribute("html");
   	createMarker(map, html);
//   	map.addOverlay(marker);
   }

   function createMarker(map, html) {
   	var marker = new google.maps.Marker({
        icon: "/w/skins/common/images/maps/marker.png",
        position: point,
        map: map
    });
   	html = '<div style="width:400px;height:150px;overflow:scroll">' + html + '</div>';
   	google.maps.event.addListener(marker, 'click', function() {
        infoWindow.setContent(html);
        infoWindow.open(map, marker);
   	});
   	return marker;
   }
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