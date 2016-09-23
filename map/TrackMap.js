L.Control.Owners = L.Control.extend({
	options: {
		collapsed: true,
		position: 'topright',
		autoZIndex: true
	},

	initialize: function ( layers, options) {
		L.setOptions(this, options);

		this._layers = {};
		this._lastZIndex = 0;
		this._handlingClick = false;
                for (i in layers) {
			this._addLayer(layers[i], i);
		}
	},

	onAdd: function (map) {
		this._initLayout();
		this._update();

		map
		    .on('layeradd', this._onLayerChange, this)
		    .on('layerremove', this._onLayerChange, this);

		return this._container;
	},

	onRemove: function (map) {
		map
		    .off('layeradd', this._onLayerChange)
		    .off('layerremove', this._onLayerChange);
	},

	removeLayer: function (layer) {
		var id = L.stamp(layer);
		delete this._layers[id];
		this._update();
		return this;
	},

	_initLayout: function () {
		var className = 'leaflet-control-layers',
		    container = this._container = L.DomUtil.create('div', className);

		//Makes this work on IE10 Touch devices by stopping it from firing a mouseout event when the touch is released
		container.setAttribute('aria-haspopup', true);

		if (!L.Browser.touch) {
			L.DomEvent
				.disableClickPropagation(container)
				.disableScrollPropagation(container);
		} else {
			L.DomEvent.on(container, 'click', L.DomEvent.stopPropagation);
		}

		var form = this._form = L.DomUtil.create('form', className + '-list');

		if (this.options.collapsed) {
			if (!L.Browser.android) {
				L.DomEvent
				    .on(container, 'mouseover', this._expand, this)
				    .on(container, 'mouseout', this._collapse, this);
			}
			var link = this._layersLink = L.DomUtil.create('a', className + '-owner-toggle', container);
			link.href = '#';
			link.title = 'Owners';

			if (L.Browser.touch) {
				L.DomEvent
				    .on(link, 'click', L.DomEvent.stop)
				    .on(link, 'click', this._expand, this);
			}
			else {
				L.DomEvent.on(link, 'focus', this._expand, this);
			}
			//Work around for Firefox android issue https://github.com/Leaflet/Leaflet/issues/2033
			L.DomEvent.on(form, 'click', function () {
				setTimeout(L.bind(this._onInputClick, this), 0);
			}, this);

			this._map.on('click', this._collapse, this);
			// TODO keyboard accessibility
		} else {
			this._expand();
		}

		this._layersList = L.DomUtil.create('div', className + '-overlays', form);

		container.appendChild(form);
	},

	_addLayer: function (layer, name) {
		var id = L.stamp(layer);
                this._layers[id] = {
			layer: layer,
			name: name
		};

		if (this.options.autoZIndex && layer.setZIndex) {
			this._lastZIndex++;
			layer.setZIndex(this._lastZIndex);
		}
        },

	_update: function () {
		if (!this._container) {
			return;
		}
                this._layersList.innerHTML = '';

		var i, obj;

		for (i in this._layers) {
			obj = this._layers[i];
			this._addItem(obj);
		}

	},

	_onLayerChange: function (e) {
		var obj = this._layers[L.stamp(e.layer)];

		if (!obj) { return; }

		if (!this._handlingClick) {
			this._update();
		}
                var type = ( e.type === 'layeradd' ? 'ownerActivate' : 'ownerDeactivate' );
		if (type) {
			this._map.fire(type, obj);
		}
	},

	// IE7 bugs out if you create a radio dynamically, so you have to do it this hacky way (see http://bit.ly/PqYLBe)
	_createRadioElement: function (name, checked) {
                var radioHtml = '<input type="radio" class="leaflet-control-layers-selector" name="' + name + '"';
		if (checked) {
			radioHtml += ' checked="checked"';
		}
		radioHtml += '/>';
                var radioFragment = document.createElement('div');
		radioFragment.innerHTML = radioHtml;

		return radioFragment.firstChild;
	},

	_addItem: function (obj) {
		var label = document.createElement('label'),
		    input,
		    checked = this._map.hasLayer(obj.layer);

                input = this._createRadioElement('leaflet-base-layers', checked);
                
		L.DomEvent.on(input, 'click', this._onInputClick, this);

		var name = document.createElement('span');
                var img = this._lastZIndex  % 3 + 1;
                
			
		name.innerHTML = '<img src="lib/images/avatar' + img + '.png" style="vertical-align: middle"/>' + obj.name;

		label.appendChild(input);
		label.appendChild(name);

		var container = this._layersList;
		container.appendChild(label);
                //// aggiungere img
		
		return label;
	},

	_onInputClick: function () {
		var i, input, obj,
		    inputs = this._form.getElementsByTagName('input'),
		    inputsLen = inputs.length;

		this._handlingClick = true;

		for (i = 0; i < inputsLen; i++) {
			input = inputs[i];
			obj = this._layers[input.layerId];
//// Activate and Deactivate
			if (input.checked && !this._map.hasLayer(obj.layer)) {
				this._map.addLayer(obj.layer);

			} else if (!input.checked && this._map.hasLayer(obj.layer)) {
				this._map.removeLayer(obj.layer);
			}
		}

		this._handlingClick = false;

		this._refocusOnMap();
	},

	_expand: function () {
		L.DomUtil.addClass(this._container, 'leaflet-control-layers-expanded');
	},

	_collapse: function () {
		this._container.className = this._container.className.replace(' leaflet-control-layers-expanded', '');
	}
});

L.control.owners = function ( overlays, options) {
	return new L.Control.Owners(overlays, options);
};

/// gestisce la visualizzazione e la mappa
function TrackMap (role, waypoints, track, medialist, lang) 
{ 
    this.pageLoaded = false;
    this.role = role;
    this.waypoints = waypoints;
    this.medialist = medialist;
    this.track = track;
    this.map = null;   /// la mappa
    this.editingMode = false; 
    this.currentOwnerId = null;  /// id dell'owner selezionato 
    this.currentOwnerData = null; /// riferimento all'oggetto che contiene i dati dell'owner selezionato 
    this.currentElementIndex = 0; /// indice della lista selezionato
    this.currentPopup = null;  /// riferimento al popup aperto
    //// GESTIONE LINGUA
    this.lang = lang;
    this.bounds = null;
    this.mode = "view";
    this.labels =  
    {
        "en" : [ "Trip not found", 
            "This address is invalid or too old, you can regenerate it esecuting a new query:<br>"
            + "try tapping <code> @gischatbot trips keyword </code> on Telegram", "Element","of", "Date:", "Type:", "Trip:", "Owner:",
            "Trip empty", "Name"],
        "it" :  [ "Percorso non trovato",
            "Questo indirizzo non è più valido, puoi generarne uno nuovo eseguendo una nuova query:<br>"
            + "prova a scrivere <code> @gischatbot trips parola_chiave </code> su Telegram",
            "Elemento", "di", "Data:", "Tipo:", "Percorso:", "Proprietario:", "Percorso vuoto", "Nome"]
    };
    this.getLabel = function(i) { return this.labels[this.lang][i];};
    
    /// ICONE PER I MARKER
    this.createIcon = function (url)
    {
            return  L.icon({
                    iconUrl: url,
                    iconSize:     [64, 64],
                    iconAnchor:   [32, 64],
                    popupAnchor:  [32, -32]
            });
    };
    this.createMediaIcon = function (url)
    {
            return  L.icon({
                    iconUrl: url,
                    iconSize:     [64, 64],
                    iconAnchor:   [96, 64],
                    popupAnchor:  [32, -32]
            });
    };
    //// 4 icone per driving mode 
    this.pedestrianIcon = L.icon
    ({
        iconUrl: 'images/path_foot.png',
        iconSize:     [32, 32],
        iconAnchor:   [16, 16],
        popupAnchor:  [16, 16]
    });
    this.bicycleIcon = L.icon
    ({
        iconUrl: 'images/path_bicycle.png',
        iconSize:     [32, 32],
        iconAnchor:   [16, 16],
        popupAnchor:  [16, 16]
    });
    this.carIcon = L.icon
    ({
        iconUrl: 'images/path_car.png',
        iconSize:     [32, 32],
        iconAnchor:   [16, 16],
        popupAnchor:  [16, 16]
    });
    this.noneIcon = L.icon
    ({
        iconUrl: 'images/path_none.png',
        iconSize:     [32, 32],
        iconAnchor:   [16, 16],
        popupAnchor:  [16, 16]
    });
    /// 3 icone per i media - spostate a destra su punto trackpoint
    this.audioIcon = this.createMediaIcon ('images/music.png');
    this.textIcon = this.createMediaIcon ('images/library.png');
    this.videoIcon = this.createMediaIcon ('images/cinema.png');
    this.photoIcon = this.createMediaIcon ('images/camera.png');
    /// 3 icone per i waypoint
    this.poiIcon = this.createIcon ('images/gps_09.png');
    this.dangerIcon = this.createIcon('images/danger.png');
    this.genericIcon = this.createIcon('images/generic.png');
    // - icona waypoint selezionato da cambiare
    this.selectIcon = this.createIcon('images/poi.png');


    //// GESTIONE LISTA ELEMENTI:
    //// - i dati del trip sono suddivisi per owner.
    //// - i dati di un owner sono una lista di elementi ordinata dalla  data. 
    //// - gli elementi della lista sono i trackpoint e i roadbook element
    //// - la lista ÃƒÂ¨ visualizzata nel pannello in bass e si scorre con i soliti tasti
    //// - il pannello page contiene il form di modifica campi o cancellazione 
    //// - i popup dei marker dei trackpoint su mappa permettono di modificare i path e aggiungere nuovi trackpoint
    //// - ogni elemento selezionato nella lista ÃƒÂ¨ evidenziato in qualche modo (i media aggiungono un marker da rimuovere)

    //// seleziona l'elemento precedente nella lista
    this.selectPrevious = function ()
    {
        if ( this.currentOwnerData === null || this.currentOwnerData.waypoints === null )
            return;
        var elements = this.currentOwnerData.waypoints;
        var index = this.currentElementIndex;
        if ( elements && elements[index]){
            if( index === 0)
                index = index = elements.length - 1;
            else
                index = index - 1;
            this.unselectElement();
            this.selectElement(index);
        }    
    };
    
    //// seleziona l'elemento successivo nella lista
    this.selectNext = function (index)
    {
        if ( this.currentOwnerData === null || this.currentOwnerData.waypoints === null )
            return;
        var elements = this.currentOwnerData.waypoints;
        var index = this.currentElementIndex;
        if ( elements && elements[index]){
            if( index === elements.length-1)
                index = 0;
            else
                index = index + 1;
            this.unselectElement();
            this.selectElement(index);
        }    
    };

    //// evidenzia e mostra l'elemento selezionato nella lista e nella mappa
    this.selectElement = function (index)
    {
        if ( this.currentOwnerData === null || this.currentOwnerData.waypoints === null )
            return;
        this.closePopup();
        // change info panel
        var element = this.currentOwnerData.waypoints[index];
        if ( element ){
            // change something on map
            this.currentElementIndex = index;
            this.currentOwnerData.selectMarker(element);
            this.map.panTo([element.latitude,element.longitude], {animate: true, duration: 1}); 
            // change info panel
            this.showInfo();
            if(this.pageLoaded)
                mediaViewManager.openMediaList();
        }
    };

    //// deseleziona l'elemento corrente nella mappa
    this.unselectElement = function ()
    {
        var element = this.currentOwnerData.waypoints[this.currentElementIndex];
        if ( element ){
            this.closePopup();
            // change something on map
            this.currentOwnerData.resetMarker(element);
        }
    };
    
    //// deseleziona l'elemento corrente nella mappa
    this.getCurrentWaypoint = function ()
    {
        return this.currentOwnerData.waypoints[this.currentElementIndex];
        
    };

    //// scrive le info dell'elemento corrente nella lista
    this.showInfo = function()
    {
        this.closePopup();
        var index = this.currentElementIndex;
        var count = this.currentOwnerData.waypoints.length;
        var element = this.currentOwnerData.waypoints[index];
        var html = " [" + element.date + '] Id: ' + element.id + ' (' + this.getLabel(2) + " " + (index + 1) + " " 
                 + this.getLabel(3) + " " + count  + ") " + this.getLabel(5) + element.type;
         var screenwidth = document.documentElement.clientWidth;
        
        if ( element.type === 'waypoint')	
            html += " " + element.tag;
        if(screenwidth > 480)
            html = this.getLabel(6) + " '" + this.dataManager.getTrackName() + "' -  " + this.getLabel(7) + " " + this.currentOwnerData.ownerName + "<br/>" + html;
        document.getElementById("infoText").innerHTML = html;
    };
    
    this.togglePage = function()
    {
        var info = document.getElementById("info");
        var infoHeight = info.clientHeight;
        var page = document.getElementById("page");
        var size = page.clientHeight;
        var height = document.getElementsByTagName("body")[0].clientHeight - 8 - infoHeight;
        this.closePopup();
        if(size < height)
        {
            if ( trackEditingManager && mapper.editingMode )
                trackEditingManager.toggleEditingMode();
            info.style.top="8px";
            info.style.height = infoHeight + "px";
            info.style.border="none";
            page.setAttribute("style", "display: inline; top: " + (infoHeight + 8) + "px; left: initial; "
            + "height: " + height + "px;"); 
            document.getElementById("infoButton").setAttribute("src", "images/closeInfo.png");
            this.loadPage('mediaManagement');
        }
        else
        { 
            document.getElementById("infoButton").setAttribute("src", "images/openInfo.png");
            page.setAttribute("style", null);
            info.setAttribute("style", null);
        }
    };
    
    this.toggleInfoButton = function()
    {
        var infoButton = document.getElementById("infoButton");
        if(!infoButton.style || infoButton.style.display !== 'none')
            infoButton.style.display = 'none';
        else
            infoButton.style.display = 'initial';
    };
    
    this.toggleNavButtons = function()
    {
        var navButtons = document.getElementsByName("navButton");
        if(navButtons[1].style.display==='none' && this.waypoints.lenght > 1)
        {
            for (var i = 0; i < navButtons.length; i++) 
                navButtons[i].style.display="initial";
        }
        else if(navButtons[0].style.display==='initial')
        {
            for (var i = 0; i < navButtons.length; i++) 
                navButtons[i].style.display="none";
        }
    };

    this.openHelp = function (text)
    {
        var help = document.getElementById("help");
        this.toggleInfoButton();
        help.style.display = 'inline';
        document.getElementById("help").innerHTML= text;    
    };
    
    this.closeHelp = function()
    {
        var help = document.getElementById("help");
        document.getElementById("infoButton").style.display = 'initial';
        help.innerHTML = '';
        help.style.display = 'none';
    };

    this.directions = function ( type, elementId )
    {
	this.dataManager.directions(this.currentOwnerId,type,elementId);
    };

    this.getElementByDate = function (date) 
    {
	return this.currentOwnerData.getElementByDate(date);
    };
    
    // carica la lista degli elementi del trip dell'owner selezionato 
    this.changeUser = function (ownerId,select)
    {
        if ( ownerId === null) {
            ownerId = this.dataManager.getFirstOwnerId();
        }
        var ownerData = this.dataManager.getOwnerData(ownerId);	
        if ( ownerData === null || this.currentOwnerId === ownerId ) 
            return;
        if ( this.currentOwnerId )
            this.dataManager.deactivate(this.currentOwnerId);
        this.currentOwnerId = ownerId;
        this.currentOwnerData = ownerData;
        this.dataManager.activate(this.currentOwnerId);
        if ( select )
            this.selectElement(0);
        
    };

    /// chiude il popup se aperto
    this.closePopup = function()
    {
        if(mapper.currentPopup)
        {
            mapper.currentPopup.closePopup();
            mapper.currentPopup = null;
            return true;
        }
        return false;
    };

    //// Inizializza la mappa
    this.initMap = function() 
    {
        if(this.track !== null && this.role !== null)
        {
            this.map = new L.Map('map', { center: new L.LatLng(39.2,  9.1), zoom: 11 });

            /// base layer
            this.google = L.tileLayer('https://mts1.google.com/vt/lyrs=s&hl=x-local&src=app&x={x}&y={y}&z={z}',
                    { attribution: 'google' }).addTo(this.map);
            this.osm = L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', 
                    { maxZoom: 18, attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors' });
            /// controlli
            L.control.layers({ 'osm': this.osm, "google": this.google }).addTo(this.map);
            L.control.scale({ position: 'topleft' }).addTo(this.map);
            //// crea l'oggetto che gestisce i dati ( compresa la loro scrittura su db )
            if(this.waypoints)
            {
                this.dataManager =  new TrackDataManager ( this, this.waypoints, this.medialist, this.track, this.key );
                /// verifica la presenza di dati nel data manager
                if ( this.dataManager.trackOwnersId.length === 0) 
                { 
                    document.getElementById("infoText").innerHTML = this.getLabel(8) ;
                }
                else
                { 
                    L.control.owners( this.dataManager.getLayerGroups() ).addTo(this.map);
                    this.map.on("popupopen", function()
                    {
                            mapper.currentPopup = this;
                    });
                    mapper.changeUser(null);
                } 
            }
        }
        else if(!this.role)
            this.pageError(1);
        else
            this.pageError(0);
    };
    
    this.loadPage = function(type)
    {
        var url = "https://gisepi.crs4.it/tgbot/map/trackMapSubViewDispatcher.php?type=" + type; 
        reqwest
        (
            {
                url: url,
                type: 'json',
                crossOrigin: true,
                method: 'get',
                success: function(data)
                {
                    document.getElementById("page").innerHTML = data;
                    mapper.pageLoaded = true;
                    mediaViewManager.openMediaList('photo', 0);
                }
            }
        );
    };
    
    this.pageError = function(errId)
    {
        switch(errId)
        {
            case 0:
            {
                document.getElementsByTagName("body")[0].innerHTML = this.getLabel(0);
                break;
            }
            case 1:
            {
                document.getElementsByTagName("body")[0].innerHTML = this.getLabel(1);
                break;
            }
        }
    };
   
    this.onSelectMarker = function (event)
    {
        //// Editing mode???
        var marker = event.target;
        if ( !marker && marker === null )
            return;
        var waypoint = marker.waypoint;
        if ( waypoint === null )
            return;
        var index = mapper.currentOwnerData.waypoints.indexOf(waypoint);
        if ( index !== -1)
        {
            mapper.unselectElement(); 
            mapper.selectElement(index);
        }   
    };
    
    this.openWindowPopup = function (text, opt1, opt2, onclick1, onclick2)
    {
        var barrier = document.createElement("div");
        var popup = document.createElement("div");
        
        popup.innerHTML = "<p>" + text + "</p><input type='button' value='" + opt1
        + "' style='padding-left: 10px; padding-right: 10px'"
        + " " + onclick1 + "> <input type='button' style='padding-left: 10px; padding-right: 10px' value='"
        + opt2 + "' " + onclick2 + ">";

        document.getElementsByTagName("body")[0].appendChild(barrier);
        barrier.appendChild(popup);
        
        barrier.id = 'barrier';
        barrier.style.zIndex = 1010;
        barrier.style.width = 'calc(100% - 16px)';
        barrier.style.height = 'calc(100% - 16px)';
        barrier.style.position = 'absolute';
        barrier.style.top = '8px';
        barrier.style.left = '8px';
        
        popup.style.zIndex = 1011;
        popup.style.backgroundColor = 'white';
        popup.style.border = '1px solid black';
        popup.style.padding = '8px';
        popup.style.position = 'relative';
        popup.style.width = '300px';
        popup.style.height = '75px';
        popup.style.marginTop = 'calc(50% - 46.5px)';
        popup.style.marginLeft = 'calc(50% - 159px)';
        popup.style.top = 'calc(50% - ' + (barrier.clientHeight/2) + 'px)';
        popup.style.left = 'calc(50% - ' + (barrier.clientWidth/2) + 'px)';
    };
    
    this.closeWindowPopup = function()
    {
        document.getElementsByTagName("body")[0].removeChild(document.getElementById("barrier"));
    };
};

var mapper = new TrackMap(role, waypoints, track, medialist, lang);
var mediaViewManager = new MediaViewManager(lang, track.id);
mapper.initMap();

if(mapper.map)
{
    mapper.map.whenReady(function (e) 
    {
        window.setTimeout(function () 
        {
            var bounds = mapper.currentOwnerData.layerGroup.getBounds();
            if ( bounds !== null )
            {
                mapper.map.fitBounds(bounds);
                mapper.selectElement(0);
            } 
        }, 200);
    });
}



