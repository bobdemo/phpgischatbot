var mapper = new ZoneMapper(id,lang);
mapper.initMap();

function ZoneMapper (id, lang)          //Costruttore oggetto ZoneMapper
{
    this.pathsLoaded=0;
    this.paths = [];
    this.pathsIds = [];
    this.currentPosition = null;
    this.selectedPoint = null;
    this.directionsMode = false;
    this.driveMode = false;
    this.directionsType = '';
    this.route = [];
    this.currentRouteIndex = 0;
    this.zoneLayer;
    this.zoneName = "";
    this.zoneId = id;
    this.map = null;
    this.currentPathIndex = 0;
    this.tapTolerance = 30;
    if ( lang && lang === 'it' ) 
        this.lang = 'it';
    else
        this.lang = 'en';
        
    this.labels =  
    {
        "en" : [ "Map of ", "Pathway","of","Area not selected", "This is the start point of this path: ", 
                "This is the final point of this path: ", "choose an option to get directions.",
                "Click here to get more info", "Geolocation is disabled: you should enable"
                + " it to get directions", "You are there", "Computing route, please wait", "Loading description","Length"],
        "it" :  [ "Mappa di ", "Sentiero", "di","Nessuna area selezionata", 
                "Questo è il punto iniziale di questo sentiero: ","Questo è il punto finale di questo sentiero: ",
                "scegli un'opzione per avere delle indicazioni", "Clicca qui per avere più informazioni",
                "La geolocalizzazione non è attiva, dovresti abilitarla per potere avere delle indicazioni",
                "Tu sei qui", "Calcolo percorso, attendi","Caricamento descrizione","Lunghezza"]
    };
    
    this.pedestrianIcon = L.icon
    (
        {
            iconUrl: 'images/pedestrianPosition.png',
            iconSize:     [51, 51],
            iconAnchor:   [20.5, 51],
            popupAnchor:  [0, -51]
        }
    );
    
    this.bicycleIcon = L.icon
    (
        {
            iconUrl: 'images/cyclistPosition.png',
            iconSize:     [51, 51],
            iconAnchor:   [20.5, 51],
            popupAnchor:  [0, -51]
        }
    );
    
    this.carIcon = L.icon
    (
        {
            iconUrl: 'images/driverPosition.png',
            iconSize:     [51, 51],
            iconAnchor:   [20.5, 51],
            popupAnchor:  [0, -51]
        }
    );
    
    this.greenIcon = L.icon     //Icona inizio tratto
    (
        {
            iconUrl: 'images/pathStart.png',
            iconSize:     [40, 56],
            iconAnchor:   [20, 56],
            popupAnchor:  [0, -56]
        }
    );
    
    this.redIcon = L.icon       //Icona fine tratto
    (
        {
            iconUrl: 'images/pathEnd.png',
            iconSize:     [40, 56],
            iconAnchor:   [20, 56],
            popupAnchor:  [0, -56]
        }
    );
    
    this.getLabel = function(i) //Restituisce la label nella lingua giusta
    {
        return this.labels[this.lang][i]; 
    };
    
    this.getMap = function()    //Restituisce la mappa
    { 
        return this.map;
    };
    
    this.initMap = function()  //Inizializza la mappa
    {
        this.map = new L.Map
        (
            'map', 
            {
                center: new L.LatLng(39.2,  9.1), zoom: 11,      //Crea la mappa
                tapTolerance: this.tapTolerance
            }
        );

        this.map.on("popupopen", function()
        {
            mapper.currentPopup = this._popup;
            mapper.map.panTo(this._popup.getLatLng());
        });   

        this.map.on("locationfound", function(event)
        {
            mapper.currentPosition = event.latlng;
            mapper.locationManagement();
        });

        this.google = L.tileLayer   //Crea tile google
        (
            'https://mts1.google.com/vt/lyrs=s&hl=x-local&src=app&x={x}&y={y}&z={z}',
            { 
                attribution: 'google' 
            }
        ).addTo(this.map);         //E la aggiunge (come default)

        this.osm = L.tileLayer     //Crea tile osm
        ( 
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', 
            {
                maxZoom: 18, 
                attribution: '&copy; <a href="https://openstreetmap.org/copyright">'
                + 'OpenStreetMap</a> contributors'
            }
        );
        L.control.layers({ "osm": this.osm, "google": this.google }).addTo(this.map);
        L.control.scale({ position: 'topleft' }).addTo(this.map);
        if ( this.zoneId &&  this.zoneId !== null )
        {
            var geoJsonUrl = "https://gisepi.crs4.it/geoserver/testgisepi/ows?service=WFS&version=1.0.0"
                    + "&request=GetFeature&typeName=testgisepi:zone&maxFeatures=5000&CQL_FILTER=id="
                    + id + "&outputFormat=application%2Fjson&"; //Richiesta dati area al server gisepi
            
	    reqwest
            (
                {
                    url: geoJsonUrl,
                    type: 'json'
                }
            ).then
            (
                function (data) //data è il risultato della richiesta
                {
                    //il geoJson descrive l'oggetto visualizzabile su mappa
                    mapper.zoneLayer = L.geoJson(data,{ style: {"color": "#000060",     
                        "opacity": 0.1, "weight": 3 }}).addTo(mapper.map);              
                    mapper.zoneName = data.features[0].properties["name"];      //Recupera il nome dell'area
                    document.title = mapper.getLabel(0) + mapper.zoneName;
                    if ( data.features[0].properties["ids"] )  
                    {
                        mapper.pathsIds = data.features[0].properties["ids"].split("_"); 
                        //Split trasforma la stringa in un array di stringhe
                        
                        if ( mapper.pathsIds )
                        {
                            var length=mapper.pathsIds.length;
                            for (var i=0; i<length; i++)
                            {
                                mapper.loadPathway(mapper.pathsIds[i]);
                            }
                        }
                    }
                    mapper.zoneLayer.on("click", function()
                    {
                        mapper.closePopup();
                    });
                }
            );
            this.map.on('zoomend', function()       //Cambia l'opacità  dell'area a seconda dello zoom
            {
                if ( mapper.zoneLayer && mapper.map.getZoom() > 11 )
                        mapper.zoneLayer.setStyle({fillOpacity: 0 });
                if ( mapper.zoneLayer && mapper.map.getZoom() <= 11 )
                        mapper.zoneLayer.setStyle({fillOpacity: 0.1 });;
            });
        }
        else
            alert ( mapper.getLabel(3) );
    };
    
    this.loadPathway = function (id)          //Recupera un sentiero e lo aggiunge alla mappa
    {
	var url = "https://gisepi.crs4.it/geoserver/testgisepi/wfs?service=WFS&version=2.0" 
                + "&request=GetFeature&CQL_FILTER=zone=" + this.zoneId + " AND id=" + id
                + "&outputFormat=application/json&typeNames=testgisepi:pathway_2"
                + "&propertyName=id,name,length3d,webencoded";
	reqwest
        (
            {
                url: url,
                type: 'json'
            }
        ).then
        (
            function (data)         //data sono i dati restituiti dalla richiesta
            {
                if( data.features.length !== 0 )
                {
                    var encoded = data.features[0].properties["webencoded"];
                    var id = data.features[0].properties["id"];
                    var name = data.features[0].properties["name"];
                    var length3d = data.features[0].properties["length3d"];
                    var i=0, index=0;
                    
                    while(i < mapper.pathsIds.length && mapper.pathsIds[i] + "" !== id + "")
                    {
                        i++;
                    }
                    index = i;
                    
                    var line = L.Polyline.fromEncoded(encoded).addTo(mapper.map);   //Aggiunge gli elementi le linee
                    line.setStyle ({opacity:1, color: 'red'});                                 //(codificate)
                                                                                    //di un sentiero alla mappa
                    line.lineIndex = index;
                    line.lineName = name;
                    line.length3d = length3d;
                    line.on("click", function()
                    {
                        if(!mapper.closePopup() && !mapper.directionsMode)
                        {
                            mapper.unselectLine();
                            mapper.selectLine(this);
                        }
                    });
                    
                    var bounds = line.getBounds();         //Array di coppie di coordinate dei punti della linea
                    mapper.updateBounds(bounds);
                    mapper.paths[index] = line;
                    mapper.pathsLoaded++;
                    
                    if(mapper.pathsLoaded === mapper.pathsIds.length)
                    {
                        //// sleziona il primo sentiero
                        mapper.setMarker(mapper.paths[0], null);
                        mapper.selectLine(mapper.paths[0]);
                    }
                }
            }
        );		
    };
    
    this.updateBounds = function (bounds)
    { 
        if(!this.map.northBound || this.map.northBound < bounds.getNorth())
            this.map.northBound = bounds.getNorth();
        if(!this.map.southBound || this.map.southBound > bounds.getSouth())
            this.map.southBound = bounds.getSouth();
        if(!this.map.westBound || this.map.westBound > bounds.getWest())
            this.map.westBound = bounds.getWest();
        if(!this.map.eastBound || this.map.eastBound < bounds.getEast())
            this.map.eastBound = bounds.getEast();
    };
    
    this.setMarker = function(line, type)
    {
        var llpoints = line.getLatLngs();
        ///// rimuove i marker se presenti
        mapper.closePopup();
        if(this.directionsMode)
        {
            if(type === "pedestrian")
                this.map.posMarker = L.marker(llpoints[0],{icon: this.pedestrianIcon});
            else if(type === "bicycle")
                this.map.posMarker = L.marker(llpoints[0],{icon: this.bicycleIcon});
            else
                this.map.posMarker = L.marker(llpoints[0],{icon: this.carIcon});
            this.map.posMarker.addTo(this.map).bindPopup(this.getLabel(9));
        }
        else 
        {
            // se presenti toglie i marker 
            if ( this.map.startMarker != null && this.map.hasLayer(this.map.startMarker) ) 
                 this.map.removeLayer(this.map.startMarker);
            if ( this.map.endMarker != null && this.map.hasLayer(this.map.endMarker) ) 
                 this.map.removeLayer(this.map.endMarker);
            // ridefinisce i popup
            var last = llpoints.length - 1;
            var startPopupHtml = "<br/><img src='images/onFoot.png' width='53px' height:'34px'"
                + " onclick='mapper.setDirectionsMode(\"pedestrian\"," + JSON.stringify(llpoints[0]) + ")'/>"
                + "<img src='images/byBicycle.png' width='53px' height:'34px'"
                + " onclick='mapper.setDirectionsMode(\"bicycle\"," + JSON.stringify(llpoints[0]) + ")'/>"
                + "<img src='images/byCar.png' width='53px' height:'34px'"
                + " onclick='mapper.setDirectionsMode(\"auto_shorter\"," + JSON.stringify(llpoints[0]) + ")'/>";
            var endPopupHtml = "<br/><img src='images/onFoot.png' width='53px' height:'34px'"
                + " onclick='mapper.setDirectionsMode(\"pedestrian\"," + JSON.stringify(llpoints[last]) + ")'/>"
                + "<img src='images/byBicycle.png' width='53px' height:'34px'"
                + " onclick='mapper.setDirectionsMode(\"bicycle\"," + JSON.stringify(llpoints[last]) + ")'/>"
                + "<img src='images/byCar.png' width='53px' height:'34px'"
                + " onclick='mapper.setDirectionsMode(\"auto_shorter\"," + JSON.stringify(llpoints[last]) + ")'/>";

            this.map.startMarker = L.marker(llpoints[0],{icon: this.greenIcon});//crea il marker di inizio sentiero
            this.map.startMarker.addTo(this.map).bindPopup(this.getLabel(4) + this.getLabel(6) + startPopupHtml,
                {maxWidth: 159});

            this.map.endMarker = L.marker(llpoints[last],{icon: this.redIcon} );//crea il marker finale
            this.map.endMarker.addTo(this.map).bindPopup(this.getLabel(5) + this.getLabel(6) + endPopupHtml, 
                {maxWidth: 159});
        }
        
    }
    
    this.show = function (line)
    {           
        this.selectLine(line);
    };    
    
    this.closePopup = function()
    {
        if(mapper.currentPopup)
        {
            mapper.map.closePopup();
            mapper.currentPopup = null;
            return true;
        }
        return false;
    };

    this.selectLine = function (line)
    {
        /// se la linea è generata da directions 
        if(this.directionsMode  )
        {
            var index = line.lineIndex;
            this.currentRouteIndex = index;
            line.setStyle({color: 'purple'});
            line.bringToFront();
            this.showDirections(index);
        }
        else
        {
            var index = line.lineIndex;
            var name = line.lineName;
            var length3d =  (line.length3d / 1000) + "";
            length3d = length3d.substring( 0, 6)
            var north = this.map.northBound, south = this.map.southBound, west = this.map.westBound;
            var east = this.map.eastBound;
            var bounds = L.latLngBounds([south, west], [north, east]);

            this.currentPathIndex = index;
            this.setMarker(line,null)

            line.setStyle({opacity:1, color: 'blue'});               //Colora di blu la linea corrente
            line.bringToFront();
            
            this.map.fitBounds(bounds.pad(0.05));
            this.showInfo(index, name, length3d);
        }
    };
    
    this.unselectLine = function()
    {   
        if(this.directionsMode  )
        {
            this.route[this.currentRouteIndex].setStyle({opacity:1, color: 'yellow'});
        }
        else {
            this.paths[this.currentPathIndex].setStyle({opacity:1, color: 'red'}); //Colora di rosso la linea precedente
        }
    };
    
    this.selectPrevious = function ()
    {
        if(this.directionsMode)
        {
            index = this.currentRouteIndex;
            index--;
            this.unselectLine();
            this.selectLine(this.route[index]);
        }
        else
        {
            index = this.currentPathIndex;
            if(index === 0)
                index = index = this.pathsIds.length - 1;
            else
                index = index - 1;
            this.unselectLine();
            this.selectLine(this.paths[index]);
        }
    };
    
    this.selectNext = function ()
    {
        if(this.directionsMode)
        {
            index = this.currentRouteIndex;
            index++;
            this.unselectLine();
            this.selectLine(this.route[index]);
        }
        else
        {
            index = this.currentPathIndex;
            if(index === this.pathsIds.length - 1)
                index = 0;
            else
                index = index + 1;
            this.unselectLine();
            this.selectLine(this.paths[index]);
            if ( ! (document.getElementById("info").style.height < "80%" ) )
                this.getMoreInfo();
        }
    };
    
    this.showInfo = function(index, name, length3d)
    {
        this.closePopup();
        var navButtons = document.getElementsByName("navButton");
        var html = this.getLabel(1) + " (" + (index + 1) + " " + mapper.getLabel(2) + " "
            + this.pathsIds.length + ") : " + name + " " + mapper.getLabel(12) + " " + length3d + "Km";
        var screenwidth = document.documentElement.clientWidth;
        if(screenwidth > 480)
            html = mapper.zoneName + "<br/>" + html;

        for (var i = 0; i < navButtons.length; i++) 
        {
            navButtons[i].style.display="inline";
        }

        document.getElementById("infoText").innerHTML = html;
        this.getMoreInfo();
    };
    
    this.resizeInfo = function ()
    {	
        var size = document.getElementById("info").clientHeight;
        var screenheight = document.documentElement.clientHeight;
        var screenwidth = document.documentElement.clientWidth;
        this.closePopup();
        if(size < screenheight)
        {
            document.getElementById("info").style.height=screenheight + "px";
            document.getElementById("page").style.display="inline";
            document.getElementById("infoButton").setAttribute("src", "images/closeInfo.png");
        }
        else
        { 
            document.getElementById("infoButton").setAttribute("src", "images/openInfo.png");
            document.getElementById("page").style.display="none";
            if(screenwidth > 480)
                document.getElementById("info").style.height="13%";
            else
                document.getElementById("info").style.height="15%";
        }
    };

    this.getMoreInfo = function ()
    {
        var pathId = this.pathsIds[this.currentPathIndex];
        document.getElementById("page").innerHTML="<h4>"+mapper.getLabel(12) +"</h4>";
        var url = "PathInfoLoader.php?areaId=" + mapper.zoneId + "&id=" + pathId;
	reqwest
        (
            {
                url: url,
                type: 'json'
            }
        ).then
        (
            function (data)         //data sono i dati restituiti dalla richiesta
            {
                document.getElementById("page").innerHTML=data.description + "<br/><br/><br/>" 
                    + "<a href='" + data.link + "' target='_blank'>" + mapper.getLabel(7) + "</a>";
            }
        );
    };
    
    /*
    this.showElevation = function(path) {
    /* da aggiungere: http://mrmufflon.github.io/Leaflet.Elevation/example/example.html
     * stile per mobile controllo in basso a destra ridurre altezza  150 x 300 ?
                     * 
                     var el = L.control.elevation();
                     el.addTo(map);
                        var gjl = L.geoJson(geojson,{
                            onEachFeature: el.addData.bind(el)
                        }).addTo(map);

                     * 
    };*/

    
    
    this.setDirectionsMode = function(type, to)
    {
        var navButtons = document.getElementsByName("navButton");
        if(this.directionsMode)
            this.unsetDirectionsMode();
        this.directionsMode = true;
        this.directionsType = type;
        this.selectedPoint = to;
        this.map.locate({watch: true});
        
        document.getElementById("info").style.border = "1px solid black";
        document.getElementById("infoText").innerHTML = this.getLabel(10);
        
        document.getElementById("infoButton").style.display = "none";
        document.getElementById("closeDirections").style.display = "block";
        navButtons[1].style.display = "none";
        navButtons[2].style.display = "none";
    };
    
    this.unsetDirectionsMode = function()
    {
        this.directionsMode = false;
        this.driveMode = false;
        this.map.stopLocate();
        this.map.removeLayer(this.map.posMarker);
        this.map.posMarker = null;
        for(var i=0; i<this.route.length; i++)
            this.map.removeLayer(this.route[i]);
        this.route = [];
        document.getElementById("info").style.border = "1px solid rgba(255,255,255,0.8)";
        document.getElementById("infoButton").style.display = "inline";
        document.getElementById("closeDirections").style.display = "none";
        document.getElementById("driveMode").style.display = "none";
        this.selectLine(this.paths[this.currentPathIndex]);
    };
    
    this.showDirections = function(index)
    {
        var navButtons = document.getElementsByName("navButton");
        var bounds = this.route[index].getBounds();

        this.closePopup();

        if(index === 0)
            navButtons[1].style.display = "none";
        else
            navButtons[1].style.display = "inline";
        if(index === this.route.length - 1)
            navButtons[2].style.display = "none";
        else
            navButtons[2].style.display = "inline";

        document.getElementById("infoText").innerHTML = this.route[index].descr;
        
        this.route[index].setStyle({opacity:1, color: 'purple'});
        this.map.fitBounds(bounds);
    };
    
    this.startDriving = function()
    {
        var driveMode = document.getElementById("driveMode");
        this.driveMode = true;
        driveMode.setAttribute("src", "images/stop.png");
        driveMode.setAttribute("onclick", "mapper.stopDriving()");
    };
    
    this.stopDriving = function()
    {
        var driveMode = document.getElementById("driveMode");
        this.driveMode = false;
        driveMode.setAttribute("src", "images/start.png");
        driveMode.setAttribute("onclick", "mapper.startDriving()");
    };
    
    this.locationManagement = function()
    {
        if(this.directionsMode && !this.map.posMarker )  //Prima volta che viene registrata la posizione
        {
            this.routeRequest();
        }
        else if(this.directionMode)
        {
            this.map.posMarker.setLatLng(pos.latlng);
            if(this.driveMode)
            {
                var i, minDistance, distance;
                for(i=0; i<this.route.lenght; i++)
                {
                    distance = this.route[i].distanceTo(pos);
                    if((!minDistance &&  distance < 20) || (distance < minDistance))
                    {
                        minDistance = distance;
                        this.currentRouteIndex = i;
                    }
                }
                if(!minDistance) //Non ci si trova in prossimita   di nessuna delle linee
                    this.routeRequest();
                else
                {
                    console.log("move to another line");
                    this.selectLine(this.route[i]);
                }
            }
        }
    };
    
    this.routeRequest = function ()
    {
        //this.currentPosition = L.latLng(39.0520890133367, 8.84690765546061);
        console.log("directions request");
        if(this.zoneLayer.getBounds().contains(this.currentPosition) && this.directionsType !== 'auto_shorter')
        {
            console.log("Internal request");
            this.internalRouteRequest();
        }
        else
        {
            this.externalRouteRequest();
        }
        this.closePopup();
    };
    
    this.internalRouteRequest = function ()
    {
        var data = JSON.stringify([mapper.currentPosition.lat, mapper.currentPosition.lng, this.selectedPoint.lat, this.selectedPoint.lng]);
        //var url = "https://gisepi.crs4.it/tgbot/map/PathwayDirections.php?json=" + data; 
        /// cross origin non e' necessario
        var url = "./PathwayDirections.php?json=" + data;

        console.log(url);
        reqwest
        (
            {
                url: url,
                method: "get",
                type: 'json',
                success: function(data)
                {
                    if ( data && data !== null && data["arcs"]  )
                    {
                        var line, i, encoded, encodedLines = data["arcs"];
                        /// Cancellare la route!!!!!!!
                        for ( i=0; i < encodedLines.length; i++ )
                        {
                            encoded = encodedLines[i]["path"];
                            if (encoded)
                            {
				
                                line = L.Polyline.fromEncoded(encoded).addTo(mapper.map);
                                line.setStyle({opacity:1, color: 'yellow'});
                                line.lineIndex = i;
                                line.descr = encodedLines[i]["msg"];
                                mapper.route[i] = line;
                            }
                        }
                        if(mapper.route[0])
                        {
                            mapper.setMarker(mapper.route[0], mapper.directionsType);
                            mapper.currentRouteIndex = 0;
                            mapper.selectLine(mapper.route[0]);
                        }
                    }
                    console.log(mapper.route);
                    if(mapper.route.length === 0)
                        mapper.externalRouteRequest();
                },
                error: function(request)
                {
                    console.log(this.url);
                    console.log(request);
                    mapper.externalRouteRequest();
                }
            }
        );
    };
    
    this.externalRouteRequest = function ()
    {
        var latlons = [{lat: this.currentPosition.lat, lon: this.currentPosition.lng},
            {lat: this.selectedPoint.lat, lon: this.selectedPoint.lng}];
        var data = JSON.stringify({locations: latlons, costing: this.directionsType, 
            directions_options: {units: 'meters', language: mapper.lang},
            id: 'gischatbot'});
        var url = "https://valhalla.mapzen.com/route?json=" + data + "&api_key=valhalla-i5Du3Hk"; 
        console.log("External request: " + url);
        
        reqwest
        (
            {
                url: url,
                method: "get",
                crossOrigin: true,
                type: 'json',
                success: function(data)
                {
                    if ( data && data !== null && data["trip"] !== null && data["trip"].legs !== null ) 
                    {
                        var legsLength = data["trip"].legs.length;

                        if(legsLength > 0)
                        {
                            var leg = data["trip"].legs[0];
                            var encoded = leg.shape;
                            
                            if(encoded)
                            {
                                var length = leg.maneuvers.length;
                                var latLons = mapper.decodeMapzen(encoded);
                                /// Cancellare la route!!!!!!!
                                for(var j=0; j<length; j++ )
                                {
                                    var begin = leg.maneuvers[j].begin_shape_index;
                                    var end = leg.maneuvers[j].end_shape_index + 1;
                                    var partialLatLons = latLons.slice(begin, end);
                                    var descr = leg.maneuvers[j].instruction;
                                    var line = null;

                                    if(partialLatLons)
                                    {
                                        if(partialLatLons)			    
                                        {    
                                            line = L.polyline(partialLatLons,{opacity:1, color: 'yellow'}).addTo(mapper.map);
                                            line.lineIndex = j;
                                            line.descr = descr;
                                            mapper.route[j] = line;
                                        }
                                    }
                                }
                                if(mapper.route[0])
                                {
                                    mapper.setMarker(mapper.route[0], mapper.directionsType);
                                    mapper.currentRouteIndex = 0;
                                    mapper.selectLine(mapper.route[0]);
                                }
                            }
                        }
                    }

                }   
            }
        );
    };
    
    this.decodeMapzen = function(str,precision) 
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
