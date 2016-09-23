<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Track Viewer</title> 
    <link rel="stylesheet" href="lib/leaflet.css" />
    <link rel="stylesheet" href="emap.css" />
    <script src="lib/reqwest.js"></script>
    <script src="lib/leaflet-src.js"></script>
    <script src="lib/Polyline.encoded.js"> </script>
</head>
    <body>
        <div id="map" class="map"></div>
        <div id="info">
            <div id="help"></div>
            <img id="infoButton" name="navButton" src="images/openInfo.png" title="Information"
                 onclick="mapper.togglePage()"/>
            <img id="previousPoint" name="navButton" src="images/previous.png" onClick="mapper.selectPrevious()"
                title="Previous" heigth="40px" width="40px"/>
                <h1 id="infoText">
                    <?php
                        $mkey = null;
                        $vkey = null;
                        $trackId = null;
                        $lang = 'en';
                        if(isset($_GET["i"]) && (isset($_GET["mk"]) || isset($_GET["vk"])))
                        {
                            $trackId = $_GET["i"];
                            if(isset($_GET["mk"]))
                            {
                                $mkey = $_GET["mk"];
                            }
                            else if(isset($_GET["vk"]))
                            {
                                $vkey = $_GET["vk"];
                            }
                            if(isset($_GET["lang"]))
                            {
                                $lang = $_GET["lang"];
                            }
                            
                            if($lang == "it")
                            {
                                echo "Caricamento dati";
                            }
                            else
                            {
                                echo "Downloading data";
                            }
                        }
                    ?>
                </h1>
                <img id="nextPoint" name="navButton" src="images/next.png" onClick="mapper.selectNext()"
                 title="Next" heigth="40px" width="40px"/>
        </div>
        <div id="page">
        </div>
        <?php
            require_once 'trackMapLoader.php';
        ?>    
    </body>
</html>

