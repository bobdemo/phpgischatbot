function TrackDataManager ( mapper, waypoints, medialist, track, key  )
{
    //// dati origine
    this.waypoints = waypoints;  // trackpoints
    this.medialist = medialist;  // medialist
    this.track = track;          // track
    
    //// dati gestiti
    this.trackOwnersId = [];     /// lista ids (chiavi) degli owner del track
    this.trackOwnersData = {};   /// oggetto con i TrackOwnersData obj con chiave id owner
     
    this.mapper = mapper;        /// riferimento al mapper per gli elementi marker e line
    this.key = key;              /// chiave acceso r/w track data su server 
    
    
    /// restituisce il TrackOwnerData obj di un  owner
    this.getOwnerData = function(ownerId) 
    {
        return this.trackOwnersData[ownerId];
    };

    /// restituisce il primo TrackOwnerData obj 
    this.getFirstOwnerId = function()
    {
	if ( this.trackOwnersId.length > 0 )
        	return this.trackOwnersId[0];
	else return null;
    };

    /// restituisce il nome del track
    this.getTrackName = function() 
    {
	if ( this.track && this.trackName !== null )
            return this.track['name'];
	return null;
    };

    this.path_element_id = null;
    
    //// Carica i dati
    /////
    // 
    // 1. i trackpoints e roadbook elements sono suddivisi per utente creando oggetti TrackOwnerData
    // 2. ogni elemento ÃƒÂ¨memorizzato in  un oggetto TrackElement che ÃƒÂ¨ contenuto in un TrackOwnerData
    // 3. gli elementi sono ordinati in base all owner e quindi rispetto alla data 
    // 4. il boundingbox degli elementi definisce un oggetto bounds del data manager  
    this.loadData = function() 
    { 
        var ownerId, ownerName, latitude, longitude;
        var i, bounds = [-1,-1,-1,-1];
        var wplength = 0;
        if ( this.waypoints )
        {
        //// esempio obj waypoints
        ///{"id":string,"userid":integer,"user":string,"track":string,"latitude":"38.990257","longitude":"8.936084","tag":"danger","path":null}
            wplength = this.waypoints.length;
            for ( i = 0; i < wplength; i++ )
            {
                ownerId = this.waypoints[i]["userid"];
                ownerName = this.waypoints[i]["user"];
                if ( !this.trackOwnersData[ownerId] || this.trackOwnersData[ownerId] === null  )
                {
                    this.trackOwnersData[ownerId] = new TrackOwnerData( ownerName, ownerId, this.mapper);
                    this.trackOwnersId.push(ownerId);
                }	
                this.trackOwnersData[ownerId].addWaypointElement(this.waypoints[i]);
            }
            
            if ( this.medialist ) //// lista media
            {
                var nameList, length, i, j, listNames = ["photo","video","audio","text"];
                for ( j=0; j < 4; j++ ) 
                {
                    nameList = listNames[j];
                    if ( this.medialist[nameList] && this.medialist[nameList].length )  
                    {
                        length = this.medialist[nameList].length;
                        for ( i = 0; i < length; i++ )
                        {
                            ownerId = this.medialist[nameList][i]["userid"];
                            if (ownerId && this.trackOwnersData && this.trackOwnersData[ownerId] !== null )
                            {
                                this.trackOwnersData[ownerId].addMediaElement(this.medialist[nameList][i]);
                            }	
                        }
                    }    
                }    
            } 
            
        }	
    }; 
 
    /// restituisce l'elemento con indice index dell'owner con id ownerId
    this.getElement = function ( ownerId, index )
    {
    	try 
        {
            return this.trackOwnersData[ownerId].getWaypointElement(index);
	}
	catch ( exc )
	{
            return null;
	}
    };
    
    this.getLayerGroups = function() {
        var ownerData, layerGroups = {};
        var i, length = this.trackOwnersId.length;
        for ( i=0; i<length; i++ )
        {    
            ownerData = this.trackOwnersData[this.trackOwnersId[i]]; 
            if ( ownerData && ownerData.layerGroup )
            {
                layerGroups [ownerData.ownerName] = ownerData.layerGroup;
            }
        }
	return layerGroups;
    };

    /// restituisce il marker dell'elemento trackpoint con indice index dell'owner con id ownerId
    this.getMarker = function ( ownerId, index )
    {
        if ( this.trackOwnersData[ownerId] )
        {
            return  this.trackOwnersData[ownerId].getMarker(this.trackOwnersData[ownerId].getWaypointElement(index));
	}
	return null;
    };
    
    /// restituisce il path dell'elemento trackpoint  con indice index dell'owner con id ownerId
    this.getPath = function ( ownerId, index )
    {
        if ( this.trackOwnersData[ownerId] )
        {
            return  this.trackOwnersData[ownerId].getPath(this.trackOwnersData[ownerId].getWaypointElement(index));
        }
        return null;
    };   
    
    //// attiva i dati di un owner cioè visualizza i marker e colora di rosso le linee
    this.activate = function(ownerId) 
    {
        var i, j, element, path, marker;
	var ownerData = this.trackOwnersData[ownerId];
        if ( ownerData !== null )
	{
           ownerData.layerGroup.addTo(this.mapper.map); 
        /*
         * /// visualizza tutti i marker e colora le linee di rosso
            j = ownerData.waypoints.length;
            for ( i=0; i<j; i++)
            {
                element = ownerData.waypoints[i]; 
                if ( element )
                {
                    marker = ownerData.getMarker(element);
                    path = ownerData.getPath(element);
                    if ( marker && marker !== null && !ownerData.layerGroup.hasLayer(marker) ) 
                    {
                            ownerData.layerGroup.addLayer(marker);
                    }
                    if ( path )
                    {
                        if ( path["line"] !==  null  ) 
                             path["line"].setStyle ({color: 'red'});
                        if ( path["lineFrom"] ) 
                             path["lineFrom"].setStyle ({color: 'red'});
                        if ( path["lineTo"] )
                             path["lineTo"].setStyle ({color: 'red'}); 
                    }  
                }    
            }*/
	}
        
    };
    
    //// disattiva i dati di un owner cioè nasconde i marker e colora di bianco le linee
    this.deactivate = function(ownerId) 
    {
        //// nasconde tutti i marker e colora le linee di bianco
        var i, j, element,marker,path;
        var ownerData = this.trackOwnersData[ownerId];
        this.mapper.map.removeLayer(ownerData.layerGroup); 
        /*if ( ownerData != null  && ownerData.waypoints )
        {
                j = ownerData.waypoints.length;
                for ( i=0; i<j; i++)
                {
                    element = ownerData.elements[i];     
                    if ( element )
                    {
                        marker = ownerData.getMarker(element);
                        path = ownerData.getPath(element);
                        if ( marker && marker !== null && ownerData.layerGroup.hasLayer(marker) ) 
                        {
                                ownerData.layerGroup.removeLayer(marker);
                        }
                        if ( path["line"] ) 
                             path["line"].setStyle ({color: 'white'});
                        if ( path["lineFrom"] ) 
                             path["lineFrom"].setStyle ({color: 'white'});
                        if ( path["lineTo"] )
                             path["lineTo"].setStyle ({color: 'white'});
                    }
                }    
	}*/
    };
     
    //// inizializza i dati !!!!
    this.loadData();
};
