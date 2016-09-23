///// data loader result javascript
var waypoints=[],medialist=[],track,key,lang='en';

<?php
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/../settings.php';
    use GisAgentTB\TelegramBot\Logger;
    use GisAgentTB\TelegramBot\DB;
    
    $trackId = $_GET["i"];
    $key = $_GET["key"];
    if ( isset($trackId) &&  isset($key) )
    {
        echo 'id = "' . $trackId . '";';
        echo 'key = "' . $key . '";';
        echo 'lang = "en";';
        Logger::logInfo("EditMap for track: " . $trackId . ", with key: " . $key);
        try
        {
            $pdo = DB::initialize($DB_NAME,$DB_USER,$DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
            ////  only for test 
            $key = DB::getKey($trackId, "management");
            $res = DB::findTrackById($trackId);
            if ( isset($res) && $res != null )
            {
                $res["management_key"] = null;
                $res["visibility_key"] = null;
                $track = json_encode($res);
            }
            //// END only for test
            $trackPoints = DB::readTrackPointsForMap($trackId, "management", $key);
            if ( isset($trackPoints) )
            {
                echo 'waypoints = ' . $trackPoints . ";";
                $res = DB::fetchElements($trackId, null);
                if ( isset($res) && $res != null )
                {
                    echo 'medialist = ' . json_encode($res) . ";";
                }
                if ( isset($track) && $track != null )
                {
                    echo 'track  = ' . $track . ";";
                }
            }
        }
        catch (\PDOException $e)
        {
            Logger::logError($e->getMessage());
        }
    }
?>


var mapper = new TrackMap(waypoints,medialist, track, key,lang);
mapper.initMap();
mapper.map.whenReady(function (e) {
        window.setTimeout(function () {
            var bounds = mapper.bounds;
            if ( bounds && bounds[0] !== -1 &&  bounds[1] !== -1
                && bounds[2] !== -1 && bounds[3] !== -1 )
                mapper.map.fitBounds([[bounds[0], bounds[1]],[bounds[2], bounds[3]]]);
        }, 200);
});   

