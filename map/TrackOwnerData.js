//// it manage data (Model)
function TrackOwnerData ( name, id, mapper)
{
    
    this.ownerName = name;
    this.ownerId = id;
    /// all elements in a time ordered array  
    this.waypoints = [];
    this.medialist = { "photo" : [],"text" : [],"video" : [],"audio" : []};
    
    this.getWaypointElement = function( index ) 
    {
        if ( index > 0 || index < this.waypoints.length )
            return this.waypoints[index];
        else return null;
    }
    
    this.getWaypointElementById = function( id ) 
    {
        var i, j = this.waypoints.length;
        for ( i=0; i<j; i++)
        {
            if ( this.waypoints[i].id === id )
                return this.waypoints[i];
        }
        return null;
    }
    
    ////  nearest in time  return a waypoint waypoint        
    this.getWaypointElementByDate = function(date) 
    {
        var prev = -1, length = this.waypoints.length;
        for ( i=0; i<length; i++)
        {
            if ( this.waypoints[i].date < date )
                prev = i;
        }
        /// ritorna il primo se la data e anteriore al track dell'owner
        if ( prev === -1 && length > 0 )
             return this.waypoints[0];
        else if ( prev === length  )
             return this.waypoints[length]; 
        else {
            if ( this.waypoints[prev].date - date < this.waypoints[prev+1].date - date )
                return this.waypoint[prev];
            else  this.waypoints[prev+1].date;
        }
        return null;
    };
    
    /// 
    this.addWaypointElement = function(dbObj) 
    {
	var userId = dbObj["userid"];
        var userName = dbObj["user"];
        //// controllo errore
        if ( this.ownerId !== userId || this.ownerName !== userName )
            return;
        var element = new TrackWaypointElement(dbObj);

        if ( element === null )
            return;
        this.waypoints.push(element);
	this.sortWaypointElementsByDate();
	this.addMarker(element);
	this.addPath(element);
    };
    
    this.addMediaElement = function(dbObj)
    {
        var element = new TrackMediaElement(dbObj);
        if ( element === null )
            return;
        if ( this.medialist[element.type] ){
            this.medialist[element.type].push(element);
            this.sortMediaElementsByDate(element.type);
        };
    };
    
    this.getMediaElement = function(index,type)
    {
        if ( this.medialist[type] && this.medialist[type].length > index )
        {
            return this.medialist[type][index];
        };
    };
    
    this.findMediaElement = function(id, type)
    {
        var i, list = this.medialist[type], length = list.length;
        console.log(list);
        for(var i=0; i<length; i++)
        {
            if(list[i].id === id)
                return i;
        }
        return -1;
    };
    
    this.deleteMediaElement = function(id, type)
    {
        var index = this.findMediaElement(id, type);
        if(index !== -1)
            this.medialist[type].splice(index, 1);
    };
    
    this.updateMediaElement = function(id, type, field, value)
    {
        var index = this.findMediaElement(id, type);
        if(index !== -1)
        {
            console.log(this.medialist[type][index]);
            this.medialist[type][index][field] = value;
            if(field === 'date')
            {
                this.medialist[type][index].time = (new Date(value)).getTime();
                this.sortMediaElementsByDate(type);
            }
            console.log(this.medialist[type][index]);
        }
    };
    
    ////           
    this.sortWaypointElementsByDate = function() 
    {
        this.waypoints.sort ( this.compareTime );
    };
    
    ////           
    this.sortMediaElementsByDate = function(type) 
    {
        this.medialist[type].sort ( this.compareTime );
    };
    
    ////           
    this.findPreviousWaypoint = function(el) 
    {
        var index = this.waypoints.indexOf(el);
	var i;
	if ( index <= 0 ) 
            return null;
        for ( i=index-1; i>-1; i--)
        {
            if ( this.waypoints[i].type === "waypoint" )
                return this.waypoints[i];
        }
        return null;
    } ;
    
    ////           
    this.findNextWaypoint = function(el) 
    {
        var index = this.waypoints.indexOf(el);
        if ( index === -1 || index === this.waypoints.length - 1 ) 
            return null;
        for ( i=index+1; i<this.waypoints.length; i++)
        {
            if ( this.waypoints[i].type === "waypoint" )
                return this.waypoints[i];
        }
    } ;
       
    ////           
    this.compareTime = function( a, b ) 
    {
        return  a.time - b.time ; 
    };
            
    //////////////// ELEMENTI MAPPA 
    
    this.mapper = mapper;
    this.layerGroup = L.featureGroup();
    
    this.addMarker = function( element ) 
    {
        var marker;
        if ( element.latitude && element.latitude !== null 
          && element.longitude && element.longitude !== null )
        {
            var icon = this.mapper.genericIcon;
            if ( element.tag === "danger" ) 
                icon =  this.mapper.dangerIcon;
            if ( element.tag === "poi" ) 
                icon = this.mapper.poiIcon;
            var marker = L.marker([element.latitude,element.longitude],{icon: icon});
            marker.waypoint = element;
            marker.id = "marker_"+element.id;
            marker.on("click", mapper.onSelectMarker )
         }
         if ( marker != null )
             this.layerGroup.addLayer( marker );
    }
    
    this.getMarker = function( element ) 
    {
        return this.getLayer("marker_"+element.id);
    }
    
    
    this.addPath = function (element,isMapzen)
    {
        var lineFrom  = null, lineTo  = null, line  = null, latlons = null;
        var previous = this.findPreviousWaypoint(element);
        if ( element.path && element.path !== null )
        {
            this.removePath(element);
            if ( isMapzen ) 
            {
                latlons = this.mapper.dataManager.decodeMapzen(element.path);
                line = L.polyline(latlons,{color: 'red'}).addTo(this.mapper.map);
            }
            else
            {
                line = L.Polyline.fromEncoded(element.path).addTo(this.mapper.map);
                latlons = line.getLatLngs();
                line.setStyle({color: 'red'});
            }
        }    
        if ( line !== null)
        {
            lineFrom = L.polyline([latlons[latlons.length-1],element.getLatLon()],{color: 'red',dashArray: [10,10] });
            lineFrom.id = "pathfrom_"+element.id;
            lineTo = L.polyline([previous.getLatLon(),latlons[0]],{color: 'red',dashArray: [10,10] });
            lineTo.id = "pathto_"+element.id;
            this.layerGroup.addLayer(lineFrom);
            this.layerGroup.addLayer(lineTo);
        }
        else
        {
            if ( previous !== null  )
            {
                line = L.polyline([previous.getLatLon(),element.getLatLon()],{ color: 'red', dashArray: [10, 10] }).addTo(this.mapper.map);
            }
        }
        if ( line !== null )
        {
              line.waypoint = element;
              line.id = "path_"+element.id;
              this.layerGroup.addLayer(line);
        }
    };
    
    this.getPath = function( element ) 
    {
        var path = {};
        path["line"] = this.getLayer("path_"+element.id);
        path["lineFrom"] = this.getLayer("pathfrom_"+element.id);
        path["lineTo"] = this.getLayer("pathto_"+element.id);
        return path;
    }
    
    this.getLayer = function( name ) 
    {
        var layers = this.layerGroup.getLayers();
        var i, length = layers.length;
        
	for ( i=0; i<length; i++)
        {
            if ( layers[i].id === id )
                return  layers[i];
        }
        return null;
    }
    
    this.resetMarker = function ( element )
    {
	var marker = this.getMarker(element);
	var icon = this.mapper.genericIcon;
        if ( element.tag === "danger" )
             icon =  this.mapper.dangerIcon;
        if ( element.tag === "poi" )
             icon = this.mapper.poiIcon;
	marker.setIcon(icon);    
	marker.setZIndexOffset(0);
    };

    this.selectMarker = function ( element )
    {
    	var marker = this.getMarker(element);
	var icon = this.mapper.selectIcon;
	marker.setIcon(icon); 
        marker.setZIndexOffset(1000);
    };
    
    this.removePath = function ( element )
    {
        var line, lineTo, lineFrom;
        if ( element !== null ){
            line = this.getLayer("path_"+element.id);
            lineTo = this.getLayer("pathto_"+element.id);
            lineFrom = this.getLayer("pathfrom_"+element.id);
            if ( line != null )
                this.layerGroup.removeLayer(line);
            if ( lineTo != null )
                this.layerGroup.removeLayer(lineTo);
            if ( lineFrom != null )
                this.layerGroup.removeLayer(lineFrom);
       }    
    };   
    
    this.getMediaList = function(waypointIndex)
    {
        var waypoint = this.getWaypointElement(waypointIndex);
        var result = { "photo" : [],"text" : [],"video" : [],"audio" : []};
        //next precedente waypoint
        var next = this.findNextWaypoint(waypoint);
        //prev precedente waypoint
        var prev = this.findPreviousWaypoint(waypoint);
        var el;
        
        if(!prev && !next){ // clone
            if(this.medialist["photo"])
                result["photo"] = this.medialist["photo"].slice(0);
            if(this.medialist["text"])
                result["text"] = this.medialist["text"].slice(0);
            if(this.medialist["text"])
                result["video"] = this.medialist["video"].slice(0);
            if(this.medialist["audio"])
                result["audio"] = this.medialist["audio"].slice(0);
            return result;
        }
        else
        {
            var delta;    
            var nameList, length, i, j, listNames = ["photo","video","audio","text"];
            for ( j=0; j < 4; j++ ) 
            {
                nameList = listNames[j];
                if ( this.medialist[nameList] && this.medialist[nameList].length )  
                {
                    length = this.medialist[nameList].length;
                    for ( i = 0; i < length; i++ )
                    {
                        el = this.medialist[nameList][i];
                        
                        if( ( !prev || el.time>=prev.time ) && ( !next || el.time<=next.time) )
                        {
                            delta = Math.abs(waypoint.time - el.time);
                            if(   ( !prev || delta < Math.abs(prev.time - el.time ) )
                               && ( !next || delta < Math.abs(next.time - el.time ) ) )
                            {
                                result[el.type].push(el);
                            }
                        }
                    }
                    
                }    
            }    
            
        }
        console.log(result);
        return result;
    };
};

/// waypointObj: {  
        //     "id":string, "userid":integer, "user":string,
        //     "trackId":string, , "date":string,
        //     "latitude":string, "longitude":string, "tag":string, "path":string
        //  }
        
        
function TrackWaypointElement ( db_obj ) 
{   
    this.id = db_obj["id"];
    this.date = db_obj["date"];
    this.ownerId = db_obj["userid"];
    this.ownerName = db_obj["user"];
    this.trackid = db_obj["trackid"];
    this.latitude = db_obj["latitude"];
    this.longitude = db_obj["longitude"];
    this.pathtype = db_obj["pathtype"];
    this.path = db_obj["path"];
    this.tag = db_obj["tag"];
    this.time = new Date ( this.date ).getTime();
    this.type =  "waypoint";
    this.cloneForNew = function( latitude, longitude, tag, path, pathtype ) {
         var json = this.todbjson();
         if ( json === null )
             return null;
         var element = new TrackWaypointElement ( json );
         element.id = null;
         element.latitude = latitude;
         element.longitude = longitude;
         element.tag = tag;
         element.path = path;
         element.pathtype = pathtype;
         return element;
    };     

    this.getLatLon = function() 
    {
        if ( this.latitude && this.longitude )
            return { "lat" : this.latitude , "lng": this.longitude };
        else return null;
    };

    //// for db write purpose 
    this.todbjson = function() 
    {
        var json = {};
        json.id = this.id;
        json.date = this.date;
        json.userid = this.ownerId;
        json.trackid = this.trackid;
        json.latitude = this.latitude;
        json.longitude = this.longitude;
        json.tag = this.tag;
        json.path = this.path;
        json.pathtype = this.pathtype;
        return json;
    };
} 

/// mediaObj: {  
        //     "id":string, "userid":integer, "user":string,
        //     "trackId":string, , "date":string,
        //     "text":string, "name":string, "type":string
        //  }

function TrackMediaElement ( db_obj ) 
{
    this.id = db_obj["id"];
    this.text = db_obj["text"];
    this.name = db_obj["name"];
    this.type = db_obj["type"];
    this.trackid = db_obj["trackid"];
    this.date = db_obj["date"];
    this.ownerId = db_obj["userid"];
    this.time = new Date ( this.date ).getTime();

    this.todbjson = function() 
    {
        var json = {};
        json.id = this.id;
        json.date = this.date;
        json.userid = this.ownerId;
        json.trackid = this.trackid;
        json.text = this.text;
        json.name = this.name;
        json.type = this.type;
        return json;
    };
    
} 
