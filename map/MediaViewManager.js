function MediaViewManager (lang, trackid)
{
    this.currentMediaList = [];
    this.currentMediaListType = 'photo';
    this.currentMedia = 0;
    this.fullscreenMode = false;
    this.firstMediaOfList = true;
    this.currentMediaSize = [];
    this.lang = lang;
    this.trackid = trackid;
    
    this.labels =  
    {
        "en" : [ "File not found", "Error downloading file", "You have not file of this type"],
        "it" :  [ "File non trovato", "Errore scaricando il file", "Non hai alcun contenuto di questo tipo"]
    };
    
    this.getLabel = function(i) { return this.labels[this.lang][i];};
    
    this.openPhoto = function ()
    {
        this.currentMediaListType = 'photo';
        this.openMediaList();
    };
    
    this.openVideo = function ()
    {
        this.currentMediaListType = 'video';
        this.openMediaList();
    };
    
    this.openAudio = function ()
    {
        this.currentMediaListType = 'audio';
        this.openMediaList();
    };
    
    this.openText = function ()
    {
        this.currentMediaListType = 'text';
        this.openMediaList();
    };
    
    this.openMediaList = function()
    {
        document.getElementById("comment").innerHTML = '';
        document.getElementById("media").innerHTML = '';
        this.hidePageButtons();
        this.medialist = mapper.currentOwnerData.getMediaList(mapper.currentElementIndex);
        document.getElementById("controls").style.display='none';
        if(this.medialist !== null && this.medialist[this.currentMediaListType]
            && this.medialist[this.currentMediaListType] !== null)
        {
            if(this.medialist[this.currentMediaListType].length === 0)
                this.showText(this.getLabel(2));
            else
            {
                this.currentMediaList = this.medialist[this.currentMediaListType];
                this.firstMediaOfList = true;
                this.showMedia();
            }
        }
    };
    
    this.showMedia = function()
    {
        var media = document.getElementById("media");
        media.style.display = "inline";
        if(this.currentMediaListType !== 'text')
        {
            this.getMedia(this.currentMediaList[this.currentMedia].id, 
            this.currentMediaList[this.currentMedia].name);
        }
        else
        {
            this.showText();
        }
    };
    
    this.showText = function(defaultText)
    {
        var media = document.getElementById("media");
        var comment = document.getElementById("comment");
        this.currentMediaSize = [300, 200, 3/2];
        this.setOriginalStyle();
        if(defaultText)
        {
            media.innerHTML  = "<p>" + defaultText + "</p>";
            comment.innerHTML = '';
        }
        else
        {
            media.innerHTML  = "<p>" + this.currentMediaList[this.currentMedia].text + "</p>";
            this.showPageButtons();
            mediaEditingManager.showEditingButtons();
        }
        document.getElementById("media").firstChild.style.marginTop = '25%';
    };
    
    this.previousMedia = function()
    {
        if(this.currentMedia === 0)
            this.currentMedia = this.currentMediaList.length;
        this.currentMedia -= 1;
        this.firstMediaOfList = false;
        this.showMedia();
    };
    
    this.nextMedia = function()
    {
        if(this.currentMedia === this.currentMediaList.length - 1)
            this.currentMedia = 0;
        else
            this.currentMedia += 1;
        console.log(this.currentMedia);
        this.firstMediaOfList = false;
        if(this.currentMediaListType !== 'text')
            this.showMedia();
        else
            this.showText();
    };
    
    this.showPageButtons = function()
    {
        if(this.currentMediaList.length > 1)
        {
            var buttons = document.getElementsByName("navMedia");  
                for(var i=0; i<buttons.length; i++)
                    buttons[i].style.display = "inline";
        }
    };
    
    this.hidePageButtons = function()
    {
        var buttons = document.getElementsByName("navMedia");  
        for(var i=0; i<buttons.length; i++)
            buttons[i].style.display = "none";   
    };
    
    this.toggleControl = function(fullscreenOnly)
    {
        var controls = document.getElementById("controls");
        if(!controls.style || controls.style.display === 'block')
            controls.style.display='none';
        else
        {
            controls.style.display='block';
            var controlButtons = controls.children;
            for(var i=0; i<controlButtons.length - 1; i++)
            {
                if(fullscreenOnly)
                {
                    controlButtons[i].style.visibility = 'hidden';
                }
                else
                {
                    controlButtons[i].style.visibility = 'initial';
                }
            }
            //setTimeout(function(){document.getElementById("controls").style.display='none';}, 5000);
        }
    };
    
    this.toggleFullscreen = function()
    {
        if (this.fullscreenMode)
        {
            this.fullscreenMode = false;
            this.exitFullscreen();
        }
        else if (document.fullscreenEnabled || document.webkitFullscreenEnabled 
            || document.mozFullScreenEnabled || document.msFullscreenEnabled)
        {
            this.fullscreenMode = true;
            this.goFullscreen();
        }
    };
    
    this.updateFullscreenState = function() //Se si usano i tasti verifica e aggiorna la variabile di stato fullscreen
    {
        if(document.fullscreenElement || document.webkitFullscreenElement 
            || document.mozFullScreenElement || document.msFullscreenElement)
        {
            this.fullscreenMode = true;
            this.setFullscreenStyle();
        }
        else
        {
            this.fullscreenMode = false;
            this.setOriginalStyle();
        }
    };
    
    this.exitFullscreen = function ()
    {
        document.getElementById("pageContent").style.backgroundColor = 'transparent';
        if (document.exitFullscreen) 
            document.exitFullscreen();
        else if (document.webkitExitFullscreen)
            document.webkitExitFullscreen();
        else if (document.mozCancelFullScreen)
            document.mozCancelFullScreen();
        else if (document.msExitFullscreen)
            document.msExitFullscreen();
        this.setOriginalStyle();
    };
    
    this.goFullscreen = function()
    {
        var pageContent = document.getElementById("pageContent");
        if (pageContent.requestFullscreen)
            pageContent.requestFullscreen();
        else if (pageContent.webkitRequestFullscreen)
            pageContent.webkitRequestFullscreen();
        else if (pageContent.mozRequestFullScreen)
            pageContent.mozRequestFullScreen();
        else if (pageContent.msRequestFullscreen)
            pageContent.msRequestFullscreen();
        this.setFullscreenStyle();
    };
    
    this.setFullscreenStyle = function()
    {
        var pageContent = document.getElementById("pageContent");
        var media = document.getElementById("media");
        var mediaContent = media.firstChild;
        var comment = document.getElementById("comment");
        var height, width;
        var screenwidth = document.documentElement.clientWidth;
        
        pageContent.setAttribute("style", "background-color: black; width: " + screenwidth + "px; height: " 
            + "100vh; top: 0px; background-color: transparent;");
        if(comment.clientHeight < 50)
            media.setAttribute("style", "top: 98px; width: 100%; height: calc(100% - 98px);");
        else if(comment.clientHeight >= 50)
            media.setAttribute("style", "top: 105px; width: 100%; height: calc(100% - 105px);"); 
        else
            media.setAttribute("style", "top: 105px; height: 100%; width: calc(100% - 105px);"); 
        if(this.currentMediaListType === 'photo')
            media.setAttribute("onclick", "mediaViewManager.toggleControl(true)");
        else
            media.setAttribute("onclick", "mediaViewManager.toggleControl(false)");
        media.firstChild.removeAttribute("onclick");
        
        if(this.currentMediaSize[0] > this.currentMediaSize[1]) //width > height
        {
            width = media.clientWidth;
            height = width / this.currentMediaSize[2];  //ratio
        }
        else
        {
            height = media.clientHeight;
            width = height * this.currentMediaSize[2];
        }
        
        mediaContent.setAttribute("style", "width: " + width + "px; height: " + height + "px; position: absolute;"
            + " top: calc(50% - " + height/2 + "px); left: calc(50% - " + width/2 + "px)");
        if(screenwidth > 450)
            comment.setAttribute("style", "font-size: 20px; top: 20px; color: white; padding-top: 5px; height: 80px");
        else
            comment.setAttribute("style", "font-size: 20px; top: 18px; color: white; padding-top: 5px; height: 80px");
        
    };
    
    this.setOriginalStyle = function()
    {
        var pageContent = document.getElementById("pageContent");
        var media = document.getElementById("media");
        var mediaContent = media.firstChild;
        var comment = document.getElementById("comment");
        var screenwidth = document.documentElement.clientWidth;
        var width, height;
        width = this.currentMediaSize[0];
        height = this.currentMediaSize[1];

        if(screenwidth > 450)
            pageContent.setAttribute("style", "width: 45%; height: calc(70% + 50px); left: 27.5%;");
        else
            pageContent.setAttribute("style", "width: 90%; height: calc(60% + 35px); left: 5%;");
        media.setAttribute("style", "width: " + width + "px; height: " + height  + "px;"
            + " left: calc(50% - " + width/2 + "px); top: calc(50% - " + height/2 + "px);");
        if(mediaContent)
        {
            mediaContent.setAttribute("style", "width: " + width + "px; height: " + height + "px;");
            media.removeAttribute("onclick");
            if(this.currentMediaListType === 'photo')
                mediaContent.setAttribute("onclick", "mediaViewManager.toggleControl(true)");
            else if (this.currentMediaListType === 'video')
                mediaContent.setAttribute("onclick", "mediaViewManager.toggleControl(false)");
        }
        comment.removeAttribute("style");
    };
    
    this.playPause = function()
    {
        var video = document.getElementById("media").firstChild;
        var playPause = document.getElementById("play-pause");
        if (video.paused === true) 
        {
            video.play();
            playPause.setAttribute("src", "images/end.png");
        }
        else 
        {
            video.pause();
            playPause.setAttribute("src", "images/start.png");
        }
    };
    
    this.backForward = function()
    {
        var video = document.getElementById("media").firstChild;
        var seekBar = document.getElementById("seek-bar");
        
        var time = video.duration * (seekBar.value / 100);

        video.currentTime = time;
    };
    
    this.updateSeekBar = function()
    {
        var video = document.getElementById("media").firstChild;
        var seekBar = document.getElementById("seek-bar");
        var value = (100 / video.duration) * video.currentTime;
        
        seekBar.value = value;
    };

    this.videoPlay = function()
    {
        var video = document.getElementById("media").firstChild;
        video.play();
    };
    
    this.videoPause = function()
    {
        var video = document.getElementById("media").firstChild;
        video.pause();
    };
    
    this.muteUnmute = function()
    {
        var video = document.getElementById("media").firstChild;
        var muteButton = document.getElementById("mute");
        if (video.muted === false) 
        {
            video.muted = true;
            muteButton.setAttribute("src", "images/unmute.png");
        } 
        else 
        {
            video.muted = false;
            muteButton.setAttribute("src", "images/mute.png");
        }
    };
    
    this.changeVolume = function()
    {
        var video = document.getElementById("media").firstChild;
        var volumeBar = document.getElementById("volume-bar");
        video.volume = volumeBar.value;
    };
    
    this.getMedia = function(id, name)
    {
        var url = "./FileManager.php?act=fetch&trackid=" + this.trackid 
            + "&id=" + id + "&name=" + name + "&type=" + this.currentMediaListType;
        reqwest
        (
            {
                url: url,
                method: "get",
                type: 'json',
                error: function(err)
                {
                    var infoMedia = document.getElementById("media");
                    infoMedia.innerHTML = mediaViewManager.getLabel(1);
                },
                success: function(resp)
                {
                    if(resp && resp.path && resp.path !== 'not saved' && resp.path !== 'error')
                    {
                        var html, screenwidth = document.documentElement.clientWidth, mediaheight, mediawidth;
                        var screenheight = document.documentElement.clientHeight; 
                        
                        if((resp.ratio && resp.ratio > 1) || !resp.ratio)
                        {
                            if(screenwidth > 450)
                                mediawidth = 0.4 * screenwidth;
                            else
                                mediawidth = 0.6 * screenwidth;
                            mediaheight = mediawidth / resp.ratio;
                        }
                        else
                        {
                            if(screenwidth > 450)
                                mediaheight = 0.5 * screenheight;
                            else
                                mediaheight = 0.4 * screenheight;
                            mediawidth = resp.ratio * mediaheight;
                        }
                        switch (mediaViewManager.currentMediaListType)
                        {
                            case 'photo':
                            {
                                html =  "<img src='" + resp.path + "' height=" + mediaheight+ " width="
                                    + mediawidth + ">";
                                break;
                            }
                            case 'audio':
                            {
                                html = "<audio width=" + mediawidth + " controls> <source src='" + resp.path
                                    + "' type='audio/mp3'>";
                                break;
                            }
                            case 'video':
                            {
                                html = "<video height=" + mediaheight + " width=" + mediawidth + " controls"
                                    + "ontimeupdate='mediaViewManager.updateSeekBar()' "
                                    + ">" 
                                    + "<source src='" + resp.path + "' type='video/mp4'>"
                                    + "</video>";
                                break;
                            }
                        }
                        mediaViewManager.currentMediaSize = [mediawidth, mediaheight, resp.ratio];
                        document.getElementById("media").innerHTML = html;
                        
                        if(mediaViewManager.currentMediaListType === 'audio')
                            document.getElementById("media").firstChild.style.marginTop = '25%';    
                        else if(mediaViewManager.firstMediaOfList)
                        {
                            if(mediaViewManager.currentMediaListType === 'photo')
                                mediaViewManager.toggleControl(true);
                            else if(mediaViewManager.currentMediaListType === 'video')
                                mediaViewManager.toggleControl(false);
                        }
                        if(mediaViewManager.fullscreenMode)
                            mediaViewManager.setFullscreenStyle();
                        else
                            mediaViewManager.setOriginalStyle();
                        mediaViewManager.showPageButtons();
                        
                        var text = mediaViewManager.currentMediaList[mediaViewManager.currentMedia].text;
                        if(text !== null)
                        {
                            var comment = document.getElementById("comment");
                            comment.innerHTML = text;
                            comment.style.display = 'inline';
                        }
                        mediaEditingManager.showEditingButtons();
                    }
                    else
                    {
                        document.getElementById("media").innerHTML = mediaViewManager.getLabel(0);
                    }
                }
            }
        );
    };
    
    document.addEventListener("fullscreenchange", function(){mediaViewManager.updateFullscreenState();});
    document.addEventListener("webkitfullscreenchange", function(){mediaViewManager.updateFullscreenState();});
    document.addEventListener("mozfullscreenchange", function(){mediaViewManager.updateFullscreenState();});
    document.addEventListener("MSFullscreenChange", function(){mediaViewManager.updateFullscreenState();});
    
    var supportsOrientationChange = "onorientationchange" in window,
    orientationEvent = supportsOrientationChange ? "orientationchange" : "resize";

    window.addEventListener(orientationEvent, function() 
    {
        mediaViewManager.showMedia();
    }, false);
}