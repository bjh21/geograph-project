/**
 * $Project: GeoGraph $
 * $Id: mapping.js 3657 2007-08-09 18:12:09Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2005  Barry Hunter (geo@barryhunter.co.uk)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */
 
 var currentelement = null;
 var dragmarkers = null;

 var markers = null;
 var current_popup = null;
 
 var marker1 = null;
 var eastings1 = 0;
 var northings1 = 0;
 var marker2 = null;
 var eastings2 = 0;
 var northings2 = 0;
 var lat1 = 0;
 var lon1 = 0;
 var lat2 = 0;
 var lon2 = 0;
 var epsg4326;
 var epsg900913;

 var pickupbox = null;
 var pickuplayer = null;
 var squarebox = null;
 var sboxeast = null;
 var sboxnorth = null;
 var sboxwidth = null;

/* Error tiles */
//OpenLayers.Util.onImageLoadErrorColor = "transparent";//FIXME?
OpenLayers.Util.Geograph = {};
OpenLayers.Util.Geograph.MISSING_TILE_URL = "/maps/transparent_256_256.png";
OpenLayers.Util.Geograph.MISSING_TILE_URL_BLUE = "/maps/blue_256_256.png";
OpenLayers.Util.Geograph.MISSING_TILE_URL_BLUE200 = "/maps/blue_200_200.png";

function initOL() {
	epsg4326 = new OpenLayers.Projection("EPSG:4326");
	epsg900913 = new OpenLayers.Projection("EPSG:900913"); // FIXME these should be initialized in a function => openlayers could be loaded later
	OpenLayers.Util.Geograph.originalOnImageLoadError = OpenLayers.Util.onImageLoadError;
	OpenLayers.Util.onImageLoadError = function() {
		// FIXME would be nice to have this.layer -> test for this.layer.errorTile
		if (this.src.contains("hills")) { // FIXME
			/* Profile (Nop) */
			// do nothing - this layer is transparent
		} else if (this.src.match(/tile\.php\?.*&o=1/)) {
			/* Overlays */
			// do nothing - this layer is transparent
		} else if (this.src.match(/tile\.php\?/)) {
			/* Base layer */
			// FIXME blue tile?
			//this.src = OpenLayers.Util.Geograph.MISSING_TILE_URL;
			if (this.map.baseLayer.errorTile)
				this.src = this.map.baseLayer.errorTile;
		} else {
			OpenLayers.Util.Geograph.originalOnImageLoadError;
		}
	};
}

/**
 * Subclass OpenLayers.Layer.XYZ for layers with a restristricted range of zoom levels.
 */
OpenLayers.Layer.XYrZ = OpenLayers.Class(OpenLayers.Layer.XYZ, {
    /**
     * Constructor: OpenLayers.Layer.XYrZ
     *
     * Parameters:
     * name - {String}
     * url - {String}
     * minzoom - {Integer}
     * maxzoom - {Integer}
     * errortileurl - {String}
     * options - {Object} Hashtable of extra options to tag onto the layer
     * highzoom - {Integer} First zoom level using highzoomurl
     * highzoomurl - {String} URL to use for zoom levels >= highzoomurl
     */
    initialize: function(name, url, minzoom, maxzoom, errortileurl, options, highzoom, highzoomurl) {
        this.highZoom = highzoom;
        this.highZoomUrl = highzoomurl;
        this.minZoomLevel = minzoom;
        this.maxZoomLevel = maxzoom;
        //this.numZoomLevels = null; //FIXME?
        this.errorTile = errortileurl;
        url = url || this.url;
        name = name || this.name;
        var newArguments = [name, url, options];
        OpenLayers.Layer.XYZ.prototype.initialize.apply(this, newArguments);
    },
    /**
     * APIMethod: clone
     * Create a clone of this layer
     *
     * Parameters:
     * obj - {Object} Is this ever used?
     * 
     * Returns:
     * {<OpenLayers.Layer.XYrZ>} An exact clone of this OpenLayers.Layer.XYrZ
     */
    clone: function (obj) {
        
        if (obj == null) {
            obj = new OpenLayers.Layer.XYrZ(this.name,
                                            this.url,
                                            this.minZoomLevel,
                                            this.maxZoomLevel,
                                            this.errorTile,
                                            this.getOptions(),
                                            this.highZoom,
                                            this.highZoomUrl);
        }

        //get all additions from superclasses
        obj = OpenLayers.Layer.XYZ.prototype.clone.apply(this, [obj]);

        return obj;
    },    
    /**
     * Method: getURL
     *
     * Parameters:
     * bounds - {<OpenLayers.Bounds>}
     *
     * Returns:
     * {String} A string with the layer's url and parameters and also the
     *          passed-in bounds and appropriate tile size specified as
     *          parameters
     */
    getURL: function (bounds) {
        var xyz = this.getXYZ(bounds);
        if (xyz.z < this.minZoomLevel || xyz.z > this.maxZoomLevel) {
            return this.errorTile; // FIXME check also x/y range?
        }
        var url = ( this.highZoom != null && xyz.z >= this.highZoom ) ? this.highZoomUrl : this.url;
        if (OpenLayers.Util.isArray(url)) {
            var s = '' + xyz.x + xyz.y + xyz.z;
            url = this.selectUrl(s, url);
        }
        
        if ('userParam' in this && this.userParam != null) {
            xyz.u = this.userParam;
        }
        return OpenLayers.String.format(url, xyz);
    },
    
    /**
     * Method: getXYZ
     * Calculates x, y and z for the given bounds.
     *
     * Parameters:
     * bounds - {<OpenLayers.Bounds>}
     *
     * Returns:
     * {Object} - an object with x, y and z properties.
     */
    getXYZ: function(bounds) {
        var res = this.map.getResolution();
        var x = Math.round((bounds.left - this.maxExtent.left) /
            (res * this.tileSize.w));
        var y = Math.round((this.maxExtent.top - bounds.top) /
            (res * this.tileSize.h));
        var z = this.serverResolutions != null ?
            OpenLayers.Util.indexOf(this.serverResolutions, res) :
            this.map.getZoom() + this.zoomOffset;

        var limit = Math.pow(2, z);
        if (this.wrapDateLine)
        {
           x = ((x % limit) + limit) % limit;
        }

        return {'x': x, 'y': y, 'z': z};
    },
    CLASS_NAME: "OpenLayers.Layer.XYrZ"
});

 function initMarkersLayer() {
	var SHADOW_Z_INDEX = 10;
	var MARKER_Z_INDEX = 11;
	var styleMap = new OpenLayers.StyleMap({
		/* img/icons/cam-s.png == img/icons/view-s.png   */
		/* img/icons/camicon.png  img/icons/viewicon.png */
		externalGraphic:   "/img/icons/viewicon.png", //FIXME cam?
		backgroundGraphic: "/img/icons/view-s.png",
		backgroundXOffset: -10,
		backgroundYOffset: -34,
		backgroundWidth: 37,
		backgroundHeight: 34,
		graphicZIndex: MARKER_Z_INDEX,
		backgroundGraphicZIndex: SHADOW_Z_INDEX,
		graphicWidth: 20,
		graphicHeight: 34,
		graphicXOffset: -10, // FIXME Offsets: +/- 1??
		graphicYOffset: -34
	});
	var mtypelookup = {
		0: {externalGraphic:   "/img/icons/viewicon.png"},
		1: {externalGraphic:   "/img/icons/camicon.png"} //FIXME shadow, ...
	};
	styleMap.addUniqueValueRules("default", "mtype", mtypelookup);
	dragmarkers = new OpenLayers.Layer.Vector(
		"Markers",
		{
			styleMap: styleMap,
			isBaseLayer: false,
			rendererOptions: {yOrdering: true},
			renderers: OpenLayers.Layer.Vector.prototype.renderers, //FIXME?
			displayInLayerSwitcher: false
		}
	);
 }

function initIconLayer() {
	markers = new OpenLayers.Layer.Markers(
		"Icons markers",
		{
			isBaseLayer: false,
			displayInLayerSwitcher: false
		}
	);
}

GeoPopup = OpenLayers.Class(OpenLayers.Popup.FramedCloud, {
	'autoSize': true,
	'maxSize': new OpenLayers.Size(450,350),
});

function createPopupMarker(point,html,url,w,h,scaleddim) {
	if (typeof url !== "undefined" && url != null) {
		var maxdim = Math.max(w, h);
		var scale = scaleddim ? (maxdim <= scaleddim ? 1 : scaleddim/maxdim) : 1;
		var sw = Math.round(scale * w);
		var sh = Math.round(scale * h);
		var ax = Math.round(0.5 * sw);
		var ay = Math.round(0.5 * sh);
	} else {
		var url = '/img/icons/viewicon.png';
		var sw = 20;
		var sh = 34;
		var ax = 10;
		var ay = 34; // FIXME Offsets: +/- 1??
	}
	var size = new OpenLayers.Size(sw,sh);
	var offset = new OpenLayers.Pixel(-ax,-ay);
	var icon = new OpenLayers.Icon(url,size,offset,null);
	addPopupMarker(point, GeoPopup, html, true, true, icon);
}

/* http://openlayers.org/dev/examples/popupMatrix.html */
/**
 * Function: addMarker
 * Add a new marker to the markers layer given the following lonlat, 
 *     popupClass, and popup contents HTML. Also allow specifying 
 *     whether or not to give the popup a close box.
 * 
 * Parameters:
 * ll - {<OpenLayers.LonLat>} Where to place the marker
 * popupClass - {<OpenLayers.Class>} Which class of popup to bring up 
 *     when the marker is clicked.
 * popupContentHTML - {String} What to put in the popup
 * closeBox - {Boolean} Should popup have a close box?
 * overflow - {Boolean} Let the popup overflow scrollbars?
 */
function addPopupMarker(ll, popupClass, popupContentHTML, closeBox, overflow, icon) {
	var feature = new OpenLayers.Feature(markers, ll.clone().transform(epsg4326, map.getProjectionObject()));
	feature.closeBox = closeBox;
	feature.popupClass = popupClass;
	feature.data.popupContentHTML = popupContentHTML;
	feature.data.overflow = (overflow) ? "auto" : "hidden";
	feature.data.icon = icon;

	var marker = feature.createMarker();

	var markerClick = function (evt) {
		if (current_popup != null && current_popup != this.popup) {
			current_popup.hide();
		}
		if (this.popup == null) {
			this.popup = this.createPopup(this.closeBox);
			map.addPopup(this.popup);
			this.popup.show();
		} else {
			this.popup.toggle();
		}
		current_popup = this.popup;
		OpenLayers.Event.stop(evt);
	};
	marker.events.register("mousedown", feature, markerClick);

	markers.addMarker(marker);
}

 /*function markerCompleteZoom() {
	if (squarebox !== null && map.zoom < 17) {
		pickuplayer.removeFeatures( [ squarebox ] );
		squarebox.destroy();
		squarebox = null;
	}
 }*/

 function markerCompleteDrag(vector, pixel) {
	if (vector.attributes.isdraggable) {
		if (squarebox !== null) {
			pickuplayer.removeFeatures( [ squarebox ] );
			squarebox.destroy();
			squarebox = null;
		}
	} else {
		vector.move(vector.attributes.initialpos);
	}
 }

 function markerDrag(vector, pixel) {
	if (vector.attributes.isdraggable) {
		//var pp = map.getLonLatFromPixel(pixel).transform(map.getProjectionObject(), epsg4326);
		var pp = new OpenLayers.LonLat(vector.geometry.x, vector.geometry.y);
		pp.transform(map.getProjectionObject(), epsg4326);
		
		//create a wgs84 coordinate
		var wgs84=new GT_WGS84();
		wgs84.setDegrees(pp.lat, pp.lon);
		if (ri == -1||issubmit) {
		if (wgs84.isIreland()) {
			//convert to Irish
			var grid=wgs84.getIrish(true);
		
		} else if (wgs84.isGreatBritain()) {
			//convert to OSGB
			var grid=wgs84.getOSGB();
		} else if (wgs84.isAustria32()) {
			//convert to Austrian
			var grid=wgs84.getAustrian32();
		} else if (wgs84.isAustria33()) {
			//convert to Austrian
			var grid=wgs84.getAustrian33();
		}
		}
		else if (ri == 1)
			var grid=wgs84.getOSGB();
		else if (ri == 2)
			var grid=wgs84.getIrish();
		else if (ri == 6)
			var grid=wgs84.getAustrian32(true, false);
		else if (ri == 7)
			var grid=wgs84.getAustrian33(true, false);

		if (map.zoom >= 18) {
			var newdigits = 5;
			var newprec = 1;
		} else if (map.zoom >= 15) {
			var newdigits = 4;
			var newprec = 10;
		} else if (map.zoom >= 12) {
			var newdigits = 3;
			var newprec = 100;
		} else if (map.zoom >= 9) {
			var newdigits = 2;
			var newprec = 1000;
		} else {
			var newdigits = 2;
			var newprec = 0;
		}

		//get a grid reference with the given precision
		var gridref = grid.getGridRef(newdigits);

		if (vector.attributes.mtype) {
			lon2 = pp.lon*Math.PI/180.;
			lat2 = pp.lat*Math.PI/180.;
			eastings2 = grid.eastings;
			northings2 = grid.northings;
			document.theForm.photographer_gridref.value = gridref;
		} else {
			lon1 = pp.lon*Math.PI/180.;
			lat1 = pp.lat*Math.PI/180.;
			eastings1 = grid.eastings;
			northings1 = grid.northings;
			document.theForm.grid_reference.value = gridref;
		}

		if (newprec) {
			var neweast = Math.floor(grid.eastings/newprec);
			var newnorth = Math.floor(grid.northings/newprec);
			if (squarebox !== null && (neweast != sboxeast || newnorth != sboxnorth || newprec != sboxwidth)) {
				pickuplayer.removeFeatures( [ squarebox ] );
				squarebox.destroy();
				squarebox = null;
			}
			if (squarebox === null && pickuplayer !== null) {
				sboxeast = neweast;
				sboxnorth = newnorth;
				sboxwidth = newprec;
				grid.setGridCoordinates( sboxeast   *newprec,  sboxnorth   *newprec);
				var ll1 = grid.getWGS84(true);
				grid.setGridCoordinates((sboxeast+1)*newprec,  sboxnorth   *newprec);
				var ll2 = grid.getWGS84(true);
				grid.setGridCoordinates((sboxeast+1)*newprec, (sboxnorth+1)*newprec);
				var ll3 = grid.getWGS84(true);
				grid.setGridCoordinates( sboxeast   *newprec, (sboxnorth+1)*newprec);
				var ll4 = grid.getWGS84(true);
				var p1 = new OpenLayers.Geometry.Point(ll1.longitude, ll1.latitude);
				var p2 = new OpenLayers.Geometry.Point(ll2.longitude, ll2.latitude);
				var p3 = new OpenLayers.Geometry.Point(ll3.longitude, ll3.latitude);
				var p4 = new OpenLayers.Geometry.Point(ll4.longitude, ll4.latitude);
				p1.transform(epsg4326, map.getProjectionObject());
				p2.transform(epsg4326, map.getProjectionObject());
				p3.transform(epsg4326, map.getProjectionObject());
				p4.transform(epsg4326, map.getProjectionObject());
				var points = [ p1, p2, p3, p4 ];
				var ring = new OpenLayers.Geometry.LinearRing(points);
				var style = {
					strokeColor: '#ffffff',
					strokeWidth: 1,
					strokeOpacity: 0.5,
					fillColor: '#808080',
					fillOpacity: 0.5
				};
				squarebox = new OpenLayers.Feature.Vector(ring, null, style);
				pickuplayer.addFeatures([squarebox]);
			}
		} else if (squarebox !== null) {
			pickuplayer.removeFeatures( [ squarebox ] );
			squarebox.destroy();
			squarebox = null;
		}

		//if (document.theForm.use6fig)
		//	document.theForm.use6fig.checked = true;
		
		if (eastings1 > 0 && eastings2 > 0 && pickupbox != null) {
			pickuplayer.removeFeatures( [ pickupbox ] );
			pickupbox.destroy();
			pickupbox = null;
		}
		
		if (!iscmap) {
			updateViewDirection();
		}
		
		if (typeof parentUpdateVariables != 'undefined') {
			parentUpdateVariables();
		}
		map.events.triggerEvent("dragend");
	}
 }
 
 function createMarker(point,type) { // types: 0==normal 1==photographer
	var ll = point.clone().transform(epsg4326, map.getProjectionObject());
	var marker = new OpenLayers.Feature.Vector(
		new OpenLayers.Geometry.Point(ll.lon, ll.lat),
		{
			mtype:type,
			isdraggable: issubmit && !(type && iscmap),
			initialpos:ll
		}
	);
 	if (type) {
		marker2 = marker;
	} else {
		marker1 = marker;
	}
	dragmarkers.addFeatures([marker]);
	//map.events.triggerEvent("dragend");
	return marker;
}

function createPMarker(ppoint) {
	return createMarker(ppoint,1)
}

function checkFormSubmission(that_form,mapenabled) {
	if (checkGridReferences(that_form)) {
		message = '';
		if (that_form.grid_reference.value == '' || that_form.grid_reference.value.length < 7) 
			message = message + "* Subject Grid Reference\n";
		if (that_form.photographer_gridref.value == '') 
			message = message + "* Photographer Grid Reference\n";
		if (that_form.view_direction.selectedIndex == 0) 
			message = message + "* View Direction\n";
		if (message.length > 0) {
			message = "We notice that the following fields have been left blank:\n\n" + message;
			message = message + "\nWhile you can continue without providing this information we would appreciate including as much detail as possible as it will make plotting the photo on a map much easier.\n\n";
			if (mapenabled) {
				message = message + "Adding the missing information should be very quick by dragging the icons on the map.\n\n";
			}
			message = message + "Click OK to add the information, or Cancel to continue anyway.";
			return !confirm(message);
		}
		return true;
	} else {
		return false;
	}
}

function checkGridReferences(that_form) {
	if (!checkGridReference(that_form.grid_reference,true)) 
		return false;
	if (!checkGridReference(that_form.photographer_gridref,true)) 
		return false;
	return true;

} 

function checkGridReference(that,showmessage) {
	GridRef = /\b([a-zA-Z]{1,3}) ?(\d{2,5})[ \.]?(\d{2,5})\b/;
	ok = true;
	if (that.value.length > 0) {
		myArray = GridRef.exec(that.value); 
		if (myArray && myArray.length > 0) {
			numbers = myArray[2]+myArray[3];
			if (numbers.length == 0 || numbers.length % 2 != 0) {
				ok = false;
			}
		} else {
			ok = false;
		}
	}
	if (ok == false && showmessage) {
		if (that.name == 'grid_reference') {
			alert("please enter a valid subject grid reference");
		} else {
			alert("please enter a valid photographer grid reference");
		}
		that.focus();
	}
	return ok;
}

String.prototype.trim = function () {
	return this.replace(/^\s+|\s+$/g,"");
}

/*function getMapCenter() {
	latlon = map.getCenter();
}*/
function mapMarkerToCenter(that) {
	latlon = map.getCenter();
	if (that.name == 'photographer_gridref') {
		currentelement = marker2;
	} else {
		currentelement = marker1;
	}
	currentelement.move(latlon);
	markerDrag(currentelement, null);
}


function updateMapMarker(that,showmessage,dontcalcdirection) {
	if (!checkGridReference(that,showmessage)) {
		return false;
	}
	if (!document.getElementById('map')) {
		//we have no map! so we only wanted to check the GR
		return;
	}
	
	if (that.name == 'photographer_gridref') {
		currentelement = marker2;
	} else {
		currentelement = marker1;
	}
	
	gridref = that.value.trim().toUpperCase();
	var grid;
	var ok = false;
	
	if (ri == -1 || issubmit) {
		grid=new GT_Austrian32();
		if (grid.parseGridRef(gridref)) {
			ok = true;
		} else {
			grid=new GT_Austrian33();
			ok = grid.parseGridRef(gridref)
		}
	} else {
		if (ri == 1)
			grid=new GT_OSGB();
		else if (ri == 2)
			grid=new GT_Irish();
		else if (ri == 6)
			grid=new GT_Austrian32();
		else if (ri == 7)
			grid=new GT_Austrian33();
		else
			return;
		ok = grid.parseGridRef(gridref);
	}
	
	if (ok) {
		//convert to a wgs84 coordinate
		var wgs84 = grid.getWGS84(true);

		//now work with wgs84.latitude and wgs84.longitude
		var point = new OpenLayers.LonLat(wgs84.longitude, wgs84.latitude);

		if (currentelement == null && map) {
			currentelement = createMarker(point,that.name == 'photographer_gridref'? 1 : 0);
			//markerDrag(currentelement, null); FIXME?
		} else {
			point.transform(epsg4326, map.getProjectionObject());
			currentelement.move(point);
		}

		if (that.name == 'photographer_gridref') {
			lon2 = wgs84.longitude*Math.PI/180.;
			lat2 = wgs84.latitude*Math.PI/180.;
			eastings2 = grid.eastings;
			northings2 = grid.northings;
		} else {
			lon1 = wgs84.longitude*Math.PI/180.;
			lat1 = wgs84.latitude*Math.PI/180.;
			eastings1 = grid.eastings;
			northings1 = grid.northings;
		}  

		if (!dontcalcdirection)
			updateViewDirection();
		
		if (eastings1 > 0 && eastings2 > 0 && pickupbox != null) {
			setTimeout(" if (pickupbox != null) { pickuplayer.removeFeatures( [ pickupbox ] ); pickupbox.destroy(); pickupbox = null; }",1000); //FIXME?
		}
		
		if (typeof parentUpdateVariables != 'undefined') {
			parentUpdateVariables();
		}

		map.events.triggerEvent("dragend");
	}
}

function updateViewDirection() {
	if (eastings1 > 0 && eastings2 > 0) {
		//distance = Math.sqrt( Math.pow(eastings1 - eastings2,2) + Math.pow(northings1 - northings2,2) );
		R = 6378137.0;
		dlat = lat1-lat2;
		dlon = lon1-lon2;
		slat = Math.sin(0.5*dlat);
		slon = Math.sin(0.5*dlon);
		sinsq = slat*slat + Math.cos(lat1)*Math.cos(lat2)*slon*slon;
		arc = 2 * Math.atan2(Math.sqrt(sinsq), Math.sqrt(1-sinsq));
		distance = R * arc;
		mindist = map.zoom >= 19 ? 3 : 14;
	
		if (distance > mindist) {
			//realangle = Math.atan2( eastings1 - eastings2, northings1 - northings2 ) / (Math.PI/180);
			y = Math.sin(dlon)*Math.cos(lat1);
			x = Math.cos(lat2)*Math.sin(lat1) - Math.sin(lat2)*Math.cos(lat1)*Math.cos(dlon);
			realangle = Math.atan2(y, x);
			realangle *= 180./Math.PI;

			if (realangle < 0)
				realangle = realangle + 360.0;

			jump = 360.0/16.0;

			newangle = Math.floor(Math.round(realangle/jump)*jump);
			if (newangle == 360)
				newangle = 0;

			var ele = document.theForm.view_direction;
			for(q=0;q<ele.options.length;q++)
				if (ele.options[q].value == newangle)
					ele.selectedIndex = q;

		}
	}
}

function updateCamIcon() {

}

function moveToLatLon(lat, lon) {
	var point = new OpenLayers.LonLat(lon,lat);
	point.transform(epsg4326, map.getProjectionObject());
	map.setCenter(point);
}