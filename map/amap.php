<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Area Map</title> 
    <link rel="stylesheet" href="lib/leaflet.css"/>
    <link rel="stylesheet" href="amap.css" />
    <script src="lib/reqwest.js"></script>
    <script src="lib/leaflet-src.js"></script>
    <script src="lib/Polyline.encoded.js"> </script>
    <script src="lib/L.Control.Locate.js"></script>
    
</head>
    <body>
        <div id="map" class="map"></div>
        <div id="info">
            <img id="closeDirections" src="images/closeDirections.png" title="closeDirections" 
                 onclick="mapper.unsetDirectionsMode()" heigth="27px" width="27px"/>
            <img id="driveMode" src="images/start.png" title="driveMode"
                 onclick="mapper.startDriving()" height="27px" width="27px"/>
            <img id="infoButton" name="navButton" src="images/openInfo.png" title="Information"
                 onclick="mapper.resizeInfo()"/>
            <img id="prev" name="navButton" src="images/previous.png" onClick="mapper.selectPrevious()"
                title="Previous" heigth="40px" width="40px"/>
            <h1 id="infoText">
                <?php
                    if(isset($_GET["lang"]))
                    {
                        $lang = $_GET["lang"];
                    }
                    else
                    {
                        $lang = "en";
                    }
                    if($lang == "en")
                    {
                        echo "Downloading data";
                    }
                    else
                    {
                        echo "Caricamento dati";
                    }
                ?>
            </h1>

            <img id="next" name="navButton" src="images/next.png" onClick="mapper.selectNext()"
                 title="Next" heigth="40px" width="40px"/>
            <div id="page">
            </div>
        </div>
        <?php
            echo "<script type='text/javascript'>";

            if ($_GET["i"]!=null)
            {
                echo "var id = " . $_GET["i"] . ";";
            }
            else
            {
                echo "var id = 1;";
            }
            if ($lang != null)
            {
                echo "var lang = '" . $lang . "';";
            }
            else
            {
                echo "var lang = 'en';";
            }
            echo "</script>";
            echo "<script src='ZoneMapper.js' type='text/javascript'></script>";
        ?>
    </body>
</html>
           