var map;
var parm;

function initGoogleLoader(key,callback,p) {
  parm = p;
  var script = document.createElement("script");
  script.src = "http://www.google.com/jsapi?key="+key+"&callback="+callback;
  script.type = "text/javascript";
  document.getElementsByTagName("head")[0].appendChild(script);
}

function loadMaps() {
  google.load("maps", "2", {"callback" : showPlaceMap});
}

function showPlaceMap() {
	var size = parm;
   map = new google.maps.Map2(document.getElementById("placemap"));

   // get map data
   var placeData = getPlaceData();
   var xmlDoc = new google.maps.Xml.parse(placeData);
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
   map.setCenter(pointsBounds.getCenter(), defaultZoom, google.maps.G_NORMAL_MAP);
   for (var i = defaultZoom-1; i >= 0; i--) {
       if (map.getBounds().containsBounds(pointsBounds)) {
       	break;
       }
       map.setZoom(i);
   }
   if (size == 1) {
	   map.addControl(new google.maps.LargeMapControl());
   	map.addControl(new google.maps.MapTypeControl());
   	map.addControl(new google.maps.OverviewMapControl());
		google.maps.Event.addListener(map, 'click', function(overlay, point) {
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
   else {
	   map.addControl(new google.maps.SmallZoomControl());
   }

   addMapOverlays(size, points);
}

function addMapOverlays(size, points) {
   map.clearOverlays()

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
   	var marker = createMarker(html, name, latlng, icon, size);
   	map.addOverlay(marker);
   }

   function createMarker(html, name, latlng, icon, size) {
   	var opts = {icon:icon, title:name, clickable:size==1}
   	var marker = new google.maps.Marker(latlng, opts);
   	if (size == 1) {
	  		html = '<div style="width:300px;">' + html + '</div>';
	   	google.maps.Event.addListener(marker, 'click', function() {
	   		marker.openInfoWindowHtml(html);
	   	});
   	}
   	return marker;
   }

	function createIcon(size) {
	   var icon = new google.maps.Icon();
	   if (size == 1) {
		   icon.iconSize = new google.maps.Size(20, 34);
		   icon.shadowSize = new google.maps.Size(37,34);
		   icon.iconAnchor = new google.maps.Point(9,34);
		   icon.infoWindowAnchor = new google.maps.Point(9, 2);
		   icon.infoShadowAnchor = new google.maps.Point(18,25);
		   icon.shadow = "http://www.werelate.org/w/skins/common/images/maps/shadow.png";
//		   icon.transparent = "http://www.werelate.org/w/skins/common/images/maps/lolly/transp.png";
	      icon.image = "http://www.werelate.org/w/skins/common/images/maps/marker.png";
	      icon.printImage = "http://www.werelate.org/w/skins/common/images/maps/marker.gif";
	      icon.mozPrintImage = "http://www.werelate.org/w/skins/common/images/maps/marker.gif";
	   }
	   else {
		   icon.iconSize = new google.maps.Size(12, 20);
		   icon.shadowSize = new google.maps.Size(22, 20);
		   icon.iconAnchor = new google.maps.Point(6, 20);
		   icon.infoWindowAnchor = new google.maps.Point(5, 1);
		   icon.infoShadowAnchor = new google.maps.Point(9, 12);
		   icon.shadow = "http://www.werelate.org/w/skins/common/images/maps/mm_20_shadow.png";
//		   icon.transparent = "http://www.werelate.org/w/skins/common/images/maps/lolly/transp.png";
	      icon.image = "http://www.werelate.org/w/skins/common/images/maps/mm_20_red.png";
	      icon.printImage = "http://www.werelate.org/w/skins/common/images/maps/mm_20_red.gif";
	      icon.mozPrintImage = "http://www.werelate.org/w/skins/common/images/maps/mm_20_red.gif";
	   }
      return icon;
	}
}
