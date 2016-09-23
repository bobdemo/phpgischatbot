function MediaEditingManager (key, track, lang) 
{ 
    this.lang = lang;
    this.buffer = null;
    this.isControlEnabled = false;
    
    this.control = L.Control.extend
    ({
        options: {
                position: 'bottomleft'
        },
        onAdd: function()
        {
            var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
            container.style.backgroundColor = 'white';
            container.style.backgroundImage = "url('lib/images/avatar.png')";
            container.style.backgroundSize = "32px 32px";
            container.style.width = '36px';
            container.style.height = '36px';
            container.style.cursor = 'pointer';
            mediaEditingManager.isControlEnabled = true;
            container.onclick = function()
            {
                mediaEditingManager.moveMediaElement();
                mapper.map.removeControl(mediaEditingManager.saveControl);
            };
            return container;
        },
        onRemove: function()
        {
            mediaEditingManager.isControlEnabled = false;
        }
    });
    
    this.saveControl = new this.control();
    
    this.labels =  
    {
        "en" : [ "Are you sure you want to delete this element?", "Yes", "No", "Insert a new caption",
        "Select a point where move the content"],
        "it" :  [ "Sei sicuro di volere eliminare questo elemento?", "Sì", "No",
        "Seleziona un punto in cui posizionare il contenuto"]
    };
    
    this.getLabel = function(i) { return this.labels[this.lang][i];};
    
    this.changeMediaElement = function()
    {
        this.selectedElement = mediaViewManager.currentMediaList[mediaViewManager.currentMedia];
        if(mediaViewManager.currentMediaListType !== 'text')
        {
            var comment = document.getElementById("comment");
            var text = this.getLabel(2);
            if(mediaViewManager.currentMediaList[mediaViewManager.currentMedia]['text'])
                text = mediaViewManager.currentMediaList[mediaViewManager.currentMedia]['text'];
            comment.innerHTML = "<input type='text' style='width:calc(100% - 39px);' value='" 
            + text + "'>"
            + "<input type='button' value='ok' style='margin-left: 10px; width: 25px; height: 25px;'"
            + " onclick=mediaEditingManager.changeText()>";
        }
        else
        {
            var media = document.getElementById("media");
            var text = mediaViewManager.currentMediaList[mediaViewManager.currentMedia]['text'];
            media.innerHTML = "<textarea rows='5' columns='70'"
            + " style='width: 100%; height: calc(100% - 50px); text-align: left;'>" + text + "</textarea>"
            + "<input type='button' value='ok' " + "style='position:absolute; right: 0px; width: 25px;"
            + " height: 25px;' onclick=mediaEditingManager.changeText()>";
        }
    };
        
    this.changeText = function()
    {
        if(mediaViewManager.currentMediaListType !== 'text')
            this.selectedElement.text = document.getElementById("comment").firstChild.value;
        else
            this.selectedElement.text = document.getElementById("media").firstChild.value;
        
        var mediaElement = mediaViewManager.currentMediaList[mediaViewManager.currentMedia];
        var url = "https://gisepi.crs4.it/tgbot/map/FileManager.php?act=change&id=" + this.selectedElement.id
        + "&text=" + JSON.stringify(this.selectedElement.text);
        
        reqwest
        (
            {
                url: url,
                method: "get",
                type: 'json',
                success: function(resp)
                {
                    if(resp.localeCompare('changed'))
                    {
                        var id = mediaEditingManager.selectedElement.id;
                        var type = mediaEditingManager.selectedElement.type;
                        mapper.currentOwnerData.updateMediaElement(id, type, "text", 
                        mediaEditingManager.selectedElement.text);
                        mediaViewManager.openMediaList();
                    }
                }
            }
        );
    };
        
    this.requestDeleteMediaElement = function()
    {
        this.selectedElement = mediaViewManager.currentMediaList[mediaViewManager.currentMedia];
        mapper.openWindowPopup(this.getLabel(0), this.getLabel(1), this.getLabel(2), 
        "onclick='mediaEditingManager.deleteMediaElement()'", "onclick='mapper.closeWindowPopup()'");
    };
        
    this.deleteMediaElement = function()
    {
        var url = "https://gisepi.crs4.it/tgbot/map/FileManager.php?act=delete&id=" + this.selectedElement["id"];
        
        reqwest
        (
            {
                url: url,
                method: "get",
                type: 'json',
                success: function(resp)
                {
                    if(resp.localeCompare('deleted'))
                    {
                        var id = mediaEditingManager.selectedElement.id;
                        var type = mediaEditingManager.selectedElement.type;
                        mapper.currentOwnerData.deleteMediaElement(id, type);
                        mapper.closeWindowPopup();
                        mediaViewManager.openMediaList();
                    }
                }
            }
        );
    };
    
    this.requestMoveMediaElement = function()
    {
        this.selectedElement = mediaViewManager.currentMediaList[mediaViewManager.currentMedia];
        mapper.togglePage();
        mapper.openHelp(this.getLabel(4));
        
        mapper.map.addControl(this.saveControl);
    };
    
    this.moveMediaElement = function()
    {
        this.selectedElement.date = mapper.getCurrentWaypoint()["date"];
        
        var url = "https://gisepi.crs4.it/tgbot/map/FileManager.php?act=move&id=" + this.selectedElement["id"] 
            + "&date=" + JSON.stringify(this.selectedElement.date);
    
        console.log(url);
        
        reqwest
        (
            {
                url: url,
                method: "get",
                type: 'json',
                success: function(resp)
                {
                    if(resp.localeCompare('moved'))
                    {
                        var id = mediaEditingManager.selectedElement.id;
                        var type = mediaEditingManager.selectedElement.type;
                        mapper.currentOwnerData.updateMediaElement(id, type, "date", 
                            mediaEditingManager.selectedElement.date);
                        mapper.closeHelp();
                        mediaViewManager.openMediaList();
                    }
                }
            }
        );
    };
    
    this.showEditingButtons = function()
    {
        document.getElementById("mediaManagement").style.display = 'block';
    };
    
    this.closeMediaEditingMode = function()
    {
        mapper.closeHelp();
        if(this.isControlEnabled)
            mapper.map.removeControl(this.saveControl);
    };
}

var mediaEditingManager = new MediaEditingManager(key, track, lang);
