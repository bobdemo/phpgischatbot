<?php
require __DIR__ . '/../vendor/autoload.php';

use GisAgentTB\TelegramBot\Logger;
use GisAgentTB\TelegramBot\Curl;
use GisAgentTB\TelegramBot\DB;

$result = null;
if(isset($_GET["act"]))
{
    $action = $_GET["act"];
    $result = [];
    switch ($action)
    {
        case 'fetch':
        {
            if(isset($_GET["trackid"]) && isset($_GET["id"]) && isset($_GET["name"]) && isset($_GET["type"]))
            {
                $trackid = $_GET["trackid"];
                $name = $_GET["name"];
                $id = $_GET["id"];
                $type = $_GET["type"];
                $result = getFile($trackid, $name, $id, $type);
            }
            break;
        }
        case 'delete':
        {
            if(isset($_GET["id"]))
            {
                $id = $_GET["id"];
                $result = deleteFile($id);
            }
            break;
        }
        case 'change':
        {
            if(isset($_GET["id"]) && isset($_GET["text"]))
            {
                $id = $_GET["id"];
                $text = json_decode($_GET["text"]);
                $result = changeFile($id, $text);
            }
            break;
        }
        case 'move':
        {
            if(isset($_GET["id"]) && isset($_GET["date"]))
            {
                $id = $_GET["id"];
                $date = json_decode($_GET["date"]);
                $result = moveFile($id, $date);
            }
        }
    }
    
    echo json_encode($result);
}

function getFile($trackid, $name, $id, $type)
{
    $path = saveFile($trackid, $name, $id);
    if(isset($path) && $path != null)
    {
        $ratio = '';
        if($type == 'photo')
        {
            $info = getimagesize($path);
            $ratio = $info[0] / $info[1];
        }
        else if($type == 'video')
        {
            include '../map/lib/mp4info/MP4Info.php';
            $info = MP4Info::getInfo($path);
            if($info->hasVideo) 
            {
                $ratio = $info->video->width / $info->video->height;
            }
        }
        return ["path" => "https://gisepi.crs4.it/tgbot/tmp/" . $trackid . "-" . $id, "ratio" => $ratio];
    }
    else
    {
        return ["path" => "not saved"];
    }
}


function getFileUrl($BOT_TOKEN)
{
    return 'https://api.telegram.org/file/bot'. $BOT_TOKEN .'/';
}

function getApiUrl($BOT_TOKEN)
{
    return 'https://api.telegram.org/bot'. $BOT_TOKEN .'/getfile?';
}
    
function saveFile($trackid, $fileName, $fileId)
{
    require '../settings.php';
    $localURL = "/usr/share/phptgbot/tmp/" . $trackid . "-" . $fileId;
    
    if(file_exists($localURL))
    {
        return $localURL;
    }
    else
    {
        try
        {
            $curl = new Curl(getApiUrl($BOT_TOKEN), array("file_id" => $fileName));
            $response = $curl->getResponse();
            $curl = null;
            if($response)
            {
                $file = json_decode($response);
                if ( isset($file) && isset($file->result) && isset($file->result->file_path) )
                {
                    $url = getFileUrl($BOT_TOKEN) . $file->result->file_path;

                    $file = fopen ($url, "rb");
                    if($file) 
                    {
                        $newUrl = $localURL;
                        $newf = fopen ($newUrl, "wb");
                        if ($newf)
                        {
                            while(!feof($file)) 
                            {
                                fwrite($newf, fread($file, 1024 * 8 ), 1024 * 8 );
                            }
                            fclose($newf);
                        }
                        fclose($file);
                    }
                    return $localURL;
                }
            }
        }
        catch(Exception $e)
        {
            Logger::logError("Curl error");
        }
    }
}

function deleteFile($id)
{
    require '../settings.php';
    try
    {
        $pdo = DB::initialize($DB_NAME,$DB_USER,$DB_PASSWORD);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        
        if(DB::deleteFile($id))
        {
            return json_encode("deleted");
        }
        else
        {
            return json_encode("not deleted");
        }
    }
    catch (Exception $ex)
    {
    }
}

function changeFile($id, $text)
{
    require '../settings.php';
    try
    {
        $pdo = DB::initialize($DB_NAME,$DB_USER,$DB_PASSWORD);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        
        $obj = DB::updateRoadbookElement($id, 'text', $text);
        Logger::logInfo(json_encode($obj));
        
        if($obj)
        {
            Logger::logInfo($obj['text']);
            return json_encode("changed");
        }
        else
        {
            return json_encode("not changed");
        }
    }
    catch (Exception $ex)
    {
    }
}

function moveFile($id, $date)
{
    require '../settings.php';
    try
    {
        $pdo = DB::initialize($DB_NAME,$DB_USER,$DB_PASSWORD);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        
        $obj = DB::updateRoadbookElement($id, 'date', $date);
        Logger::logInfo(json_encode($obj));
        
        if($obj)
        {
            return json_encode("moved");
        }
        else
        {
            return json_encode("not moved");
        }
    }
    catch (Exception $ex)
    {
    }
}