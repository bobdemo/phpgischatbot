/* MycontrolEdit is a control to open conf page.
 *  serve a consentire l'editing e ad eseguire l'update su server
 */
var MycontrolEdit =  L.Control.extend
({
    options: {
            position: 'topright'
    },
    onAdd: function (map) {
        var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
        container.style.backgroundColor = 'white';
        container.style.backgroundImage = "url('lib/images/hospital.png')";
        container.style.backgroundSize = "32px 32px";
        container.style.backgroundPosition = " 50% 50%";
        container.style.backgroundRepeat = "no-repeat";
        container.style.width = '36px';
        container.style.height = '36px';
        container.style.cursor = 'pointer';
        container.onclick = function(){
            trackEditingManager.toggleEditingMode(); 
        };
        map.editControl = this;
        return container;
    }
});

function TrackEditingManager (key, track, lang) 
{ 
    this.path_element_id;
    this.mapper = mapper;
    this.key = key;
    this.track = track;
    this.lang = lang;
    this.mapzen_api_key = "api_key";
            
    this.labels =  
    {
        "en" : [ "Waypoint n.", "Remove", "Add previous", "Add next", "Move line point"],
        "it" :  [ "Punto Sosta n.", "Rimuovi", "Aggiungi Precedente", "Aggiungi Successivo", "Sposta il punto"  ]
    };
    this.getLabel = function(i) { return this.labels[this.lang][i];};
    
    ///// elements request async management
    //// id request --> elements to change
    this.requests = { };
    
    this.control = new MycontrolEdit();
    
    this.mapper.map.addControl(this.control);
    
    this.toggleEditingMode = function()
    {
        /// mapper.showAdvice(false); // apre un div per notifica
        if ( mapper.editingMode ) 
        {
            this._deactivate();
            this.control.getContainer().style.backgroundImage = "url('lib/images/hospital.png')";
        }
        else 
        {
            this._activate();
            mediaEditingManager.closeMediaEditingMode();
            this.control.getContainer().style.backgroundImage = "url('lib/images/eye.png')";
        }   
    };
    
    this._activate = function()
    {
        var ownerData = this.mapper.currentOwnerData;
        var i, j = ownerData.waypoints.length;
        for ( i=0; i<j; i++)
        {
            this.createLineMarkerAndPopup( ownerData.waypoints[i] );
        }
        this.mapper.editingMode = true;
        
    };
    
    this._deactivate = function()
    {
        var ownerData = this.mapper.currentOwnerData;
        var i, j = ownerData.waypoints.length;
        for ( i=0; i<j; i++)
        {
            this.removeMarkerAndPopup( ownerData.waypoints[i] );
        }
        this.mapper.editingMode = false;
        
    };
    
    //// crea il popup per la linea associata all'elemento che è il path che finisce 
    //// nel waypoint. Il popup permette di ricreare il path e aggiungere nuovi trackpoint
    this.createLineMarkerAndPopup = function ( element )
    {
         /// find line
         var html = "",ownerData = this.mapper.currentOwnerData;
         var index = ownerData.waypoints.indexOf(element);
         var marker = ownerData.getLayer("marker_"+element.id);
         if ( marker !== null && index !== -1)
         {
            html = "<strong>"+ this.getLabel(0)+index+" </strong><br/><a onclick='mapper.removeWayPoint(\""  
                  + element.id + "\")'>" + this.getLabel(1) + " </a> ";
            if ( index === 0 )            
                html += "<br/><a onclick='mapper.addWaypoint(\"" + element.id 
                     + "\", true)'>" + this.getLabel(2) + " </a> ";
            if ( index === ownerData.waypoints.length -1 )            
                html += "<br/><a onclick='mapper.addWaypoint(\"" + element.id  
                     + "\", false)'>" + this.getLabel(3) + " </a> ";
             marker.bindPopup(html);
         }
         var from = ownerData.findPreviousWaypoint(element).getLatLon();
         if ( from !== null )
             return ;
         var latlngs = [];
         var to = element.getLatLon();
         if ( ownerData.getLayer("path_"+element.id) === null ) //// assegna linea fittizia
         {
             latlngs.push(from);
             latlngs.push(to);
         }
         else latlngs = ownerData.getLayer("path_"+element.id).getLatLngs();
         
         //// find median: { latLng: L.LatLng ,  predecessor: integer  }
         var median = L.GeometryUtil.interpolateOnLine(this.mapper.map, latlngs, 0.5);
         var icon = this.mapper.noneIcon;
         if ( element.pathtype === "auto shorter" ) 
             icon =  this.mapper.carIcon;
         if ( element.tag === "bicycle" ) 
             icon = this.mapper.bicycleIcon;
         if ( element.tag === "pedestrian" ) 
             icon = this.mapper.footIcon;   
         var marker = L.marker(median.latLng,{icon: icon});
         marker.waypoint = element;
         marker.id = "editpath_"+element.id;
         html = "<a onclick='mapper.moveLinePoint(\"" + element.id + "\")'>" + this.getLabel(4) + " </a><br/><hr/> "
              + "<img src='images/path_none.png' width='80px' height:'40px' style='cursor: pointer;'"
              + " onclick='trackEditingManager.changePathRequest(\"none\", " + element.id + ")'/>"
              + "<img src='images/onFoot.png' width='80px' height:'40px' style='cursor: pointer;'"
              + " onclick='trackEditingManager.changePathRequest(\"pedestrian\", " + element.id + ")'/>"
              + "<img src='images/byBicycle.png' width='60px' height:'40px' style='cursor: pointer;'"
              + " onclick='trackEditingManager.changePathRequest(\"bicycle\", " + element.id + ")'/>"
              + "<img src='images/byCar.png' width='60px' height:'40px' style='cursor: pointer;'"
              + " onclick='trackEditingManager.changePathRequest(\"auto_shorter\", " + element.id + ")'/>";
         marker.bindPopup(html);
         ownerData.layerGroup.addLayer( marker );
    };
    
    this.removeMarkerAndPopup = function ( element )
    {
        var ownerData = this.mapper.currentOwnerData;
        ownerData.layerGroup.removeLayer( ownerData.getLayer("editpath_"+element.id) );
        var marker = ownerData.getLayer("marker_"+element.id);
        marker.unbindPopup();
    }
    
    this._changePath = function ( dataObj, isMapzen )
    {
        if ( !this.mapper.editingMode )
            return;
        var element = this.mapper.currentOwnerData.getWaypointElementById(this.path_element_id);
        if ( dataObj !== null && element !== null )
        {
            if ( isMapzen ){
                if ( _writePathToDb(element,this.key) ) 
                    element.path = dataObj;
                else ; //// advice!!!!!
                this.mapper.currentOwnerData.createLine(element,isMapzen);
            }
            else {
                 if ( dataObj && dataObj !== null && dataObj["arcs"]  )
                 {
                        var encoded = "", encodedLines = dataObj["arcs"];
                        for ( i=0; i < encodedLines.length; i++ )
                        {
                            encoded += encodedLines[i]["path"];
                        }
                        if ( encoded && encoded != null && encoded != "" )
                        {
			    element.path = encoded;	
                            this.mapper.currentOwnerData.createLine(element,false);
                        }
                }
            }
        } 
        else ; //// advice!!!!!
        this.path_element_id = null;
    };

    this.changePathRequest = function ( type, waypointId )
    {
        if ( !this.mapper.editingMode )
            return;
        var ownerData = this.mapper.currentOwnerData;
	var waypoint = ownerData.getWaypointElementById(waypointId);
	var wpFrom = ownerData.findPreviousWaypoint(waypoint).getLatLon();
	var wpTo = waypoint.getLatLon(); 
        if ( wpFrom && wpFrom != null && wpTo && wpTo != null )
        {
            var id = wpFrom.id + "_" + wpTo.id + "_" + type;
            this.requests[id] = { "from" : wpFrom, "to": wpTo, "type": type  };
            if  ( type === 'pedestrian' ) {
                  this._getPathInPathway( id, wpFrom, wpTo) 
            }
            else this._getPathFromMapzen( id, wpFrom, wpTo, type );
        }
    };
    
    //// create new path type:auto_shorter,bicycle,pedestrian tra due trackpoint
    this._getPathFromMapzen = function (id, from, to, type )
    {
        if ( !this.mapper.editingMode )
            return;
        if ( from && from != null && to && to != null )
        {
            var latlons = [{lat: from.lat, lon: from.lng},{lat: to.lat, lon: to.lng}];
            var data = JSON.stringify({locations: latlons, costing: type, 
                        directions_options: {units: 'meters', language: this.mapper.lang}, id: id});
            var url = "https://valhalla.mapzen.com/route?json=" + data + "&api_key=" + mapzen_api_key; 
	    reqwest
            (
                {
                    url: url,
                    type: 'json',
                    crossOrigin: true,
		    method: 'get',
		    error: function(error) 
		    {
                        // leggere la richiesta e from/to aggiornare requests
		        alert ( 'error mapzen');
                    },
		    success: function(data)
                    {
                    	var encoded="",id, line, length;
                    	if ( data && data !== null && data["trip"] !== null && data["trip"].legs !== null ) 
                    	{
                        	id = data["id"];
                                length = data["trip"].legs.length;
                        	for ( i=0; i<length; i++ )
                        	{
                            		line = data["trip"].legs[i];
                            		encoded += line["shape"];
                        	}
                    	}
			this.mapper.dataManager.changePath(id, encoded, true);
		    }
                }
            );
        }
    };

    this._getPathInPathway = function(id, from, to)
    {
	if ( !this.mapper.editingMode )
            return;
        var data = JSON.stringify([from.lat, from.lng, to.lat, to.lng]);
        //var url = "https://gisepi.crs4.it/tgbot/map/PathwayDirections.php?json=" + data; 
        /// cross origin non e'  necessario
        var url = "./PathwayDirections.php?id=" + id + "&json=" + data;
        reqwest
        (
            {
                url: url,
                method: "get",
                error : function (request){
                    console.log(this.url);
                    var index = this.url.indexOf("?id=");
                    if ( index === -1 ) 
                        return;
                    var id = this.url.substring( index + 4 ) ;
                    index = id.indexOf("&");
                    if ( index === -1 ) 
                        return;
                    id = this.url.substring( 0, index ) ;
                    var req = trackEditingManager.requests[id];
                    if ( req !== null ) 
                        /// function (id, from, to, type )
                        _getPathFromMapzen ( req.id, req.from, req.to, req.type );
                    // id from request ?????;  
                    // mapper.dataManager.mapzenPath(this.path_from, this.path_to, "pedestrian");
                },
                success: function(data)
                {
                    var line, id ="", i, encoded="", encodedLines
                    if ( data && data !== null && data["arcs"]  )
                    {
                        id = data["id"];
                        encodedLines = data["arcs"];
                        /// Cancellare la route!!!!!!!
                        for ( i=0; i < encodedLines.length; i++ )
                        {
                            line = encodedLines[i]["path"];
                            if (line)
                            {
				encoded += line;
                            }
                        }
                    }
                    if( encoded !== "" )
                    {
                        this.mapper.dataManager.changePath(id, encoded, false);
                        return;
                    }
                    else if ( trackEditingManager.requests[id] )
                    {
                        var req = trackEditingManager.requests[id];
                        if ( req ) 
                            _getPathFromMapzen ( req.id, req.from, req.to, req.type );
                    }
                }
            }
        );
    };  
    
    ////    
    this._decodeMapzen = function(str,precision) 
    {
        var index = 0,
        lat = 0,
        lng = 0,
        coordinates = [],
        shift = 0,
        result = 0,
        byte = null,
        latitude_change,
        longitude_change,
        factor = Math.pow(10, precision || 6);

        // Coordinates have variable length when encoded, so just keep
        // track of whether we've hit the end of the string. In each
        // loop iteration, a single coordinate is decoded.
        while (index < str.length) 
        {
            // Reset shift, result, and byte
            byte = null;
            shift = 0;
            result = 0;

            do
            {
                byte = str.charCodeAt(index++) - 63;
                result |= (byte & 0x1f) << shift;
                shift += 5;
            } while (byte >= 0x20);

            latitude_change = ((result & 1) ? ~(result >> 1) : (result >> 1));

            shift = result = 0;

            do
            {
                byte = str.charCodeAt(index++) - 63;
                result |= (byte & 0x1f) << shift;
                shift += 5;
            } while (byte >= 0x20);

            longitude_change = ((result & 1) ? ~(result >> 1) : (result >> 1));

            lat += latitude_change;
            lng += longitude_change;

            coordinates.push(L.latLng(lat / factor, lng / factor));
        }

        return coordinates;
    }; 
}

var trackEditingManager = new TrackEditingManager(key, track, lang);


