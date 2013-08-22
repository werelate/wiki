function ShowMap() {
   var map = new GMap2(document.getElementById("sourcemap"));
   var pointString = getSourcePoints();
   var xmlDoc = new GXml.parse(pointString);
   var points = xmlDoc.documentElement.getElementsByTagName("point");

   var pointsBounds = new GLatLngBounds(new GLatLng(points[0].getAttribute("lat"), points[0].getAttribute("lng")),
                                        new GLatLng(points[0].getAttribute("lat"), points[0].getAttribute("lng")));
   for (var i = 1; i < points.length; i++) {
   	var ll = new GLatLng(points[i].getAttribute("lat"), points[i].getAttribute("lng"));
   	pointsBounds.extend(ll);
   }
   var margin = 0.3;
   var ll = new GLatLng(pointsBounds.getSouthWest().lat(), pointsBounds.getSouthWest().lng() - margin);
   pointsBounds.extend(ll);
   var ll = new GLatLng(pointsBounds.getNorthEast().lat() + margin, pointsBounds.getNorthEast().lng() + margin);
   pointsBounds.extend(ll);

   var defaultZoom = 9;
   map.setCenter(pointsBounds.getCenter(), defaultZoom, G_NORMAL_MAP);
   map.addControl(new GLargeMapControl());
   map.addControl(new GMapTypeControl());
   for (var i = defaultZoom-1; i >= 0; i--) {
       if (map.getBounds().containsBounds(pointsBounds)) {
       	break;
       }
       map.setZoom(i);
   }

   for (var i = 0; i < points.length; i++) {
   	var point = new GPoint(points[i].getAttribute("lng"), points[i].getAttribute("lat"));
   	var html = points[i].getAttribute("html");
   	var marker = createMarker(html);
   	map.addOverlay(marker);
   }

   function createMarker(html) {
   	var marker = new GMarker(point);
   	html = '<div style="width:400px;height:150px;overflow:scroll">' + html + '</div>';
   	GEvent.addListener(marker, 'click', function() {
   		marker.openInfoWindowHtml(html);
   	});
   	return marker;
   }
}
