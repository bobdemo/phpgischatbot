<?php
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/../settings.php';
    use GisAgentTB\TelegramBot\Logger;
    use GisAgentTB\TelegramBot\DB;
    
    echo "<script src='./TrackDataManager.js' type='text/javascript'></script>";
    echo "<script src='./TrackOwnerData.js'   type='text/javascript'></script>";
    echo "<script src='./MediaViewManager.js'   type='text/javascript'></script>";
    $script = "<script type='text/javascript'>";
    $script .= 'var id = "' . $trackId . '";';
    $script .= 'var lang = "' . $lang . '";';
    $script .= 'var role = null;';
    $script .= 'var track = null;';
    $script .= 'var medialist = null;';
    $script .= 'var waypoints = null;';
    $script .= 'var key = null;';
    
    try
    {
        $pdo = DB::initialize($DB_NAME,$DB_USER,$DB_PASSWORD);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        $role = null;
        
        if(isset($vkey))
        {
            if(DB::isKeyValid($trackId, "visibility", $vkey . ""))
            {
                $role = 'guest';
                $script .= 'key = ' . $vkey . ";";
            }
        }
        else
        {
            if(DB::isKeyValid($trackId, "management", $mkey . ""))
            {
                $role = 'manager';
                $script .= 'key = ' . $mkey . ";";
            }
        }
        
        $track = DB::findTrackById($trackId);
        if ( isset($track) && $track != null )
        {
            $track["management_key"] = null;
            $track["visibility_key"] = null;
            $track = json_encode($track);
            if(isset($role))
            {
                $script .= "role = '" . $role . "';";
            }
            $script .= 'track  = ' . $track . ";";
        }
        
        $trackPoints = DB::readTrackPointsForMap($trackId);
        if ( isset($trackPoints) )
        {
            $script .= 'waypoints = ' . $trackPoints . ";";
            $media = ["text" => DB::fetchElements($trackId, 'text'), 
                "audio" => DB::fetchElements($trackId, 'audio'),
                "video" => DB::fetchElements($trackId, 'video'), 
                "photo" => DB::fetchElements($trackId, 'photo')];
            $script .= 'medialist = ' . json_encode($media) . ";";
            $script .= "</script>";
            $script .= "<script type='text/javascript' src='TrackMap.js'></script>";
            if($role != null && $role === 'manager')
            {
                $script .= "<script type='text/javascript' src='TrackEditingManager.js'></script>";
                $script .= "<script type='text/javascript' src='lib/leaflet.geometryutil.js'></script>";
                $script .= "<script type='text/javascript' src='MediaEditingManager.js'></script>";
            }
        }
        else
        {
            $script .= "</script>";
        }
        
        echo $script;
    }
    catch (\PDOException $e)
    {
        $lenght = $script.lenght();
        if($lenght > 10 && substr($script, ($lenght - 10)) == "</script>")
        {
            $script .= "</script>";
        }
        echo $script;
        Logger::logError($e->getMessage());
    }
?>