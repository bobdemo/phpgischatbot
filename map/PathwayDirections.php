<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../settings.php';
use GisAgentTB\TelegramBot\DB;
use GisAgentTB\TelegramBot\Logger;
if(isset($_GET["json"]))
{
    header('Content-Type: application/json');
    
        
    $data = json_decode($_GET["json"]);

    $lat1 = $data[0];
    $lng1 = $data[1];
    $lat2 = $data[2];
    $lng2 = $data[3];
    $result = [ "source" => [$lat1,$lng1], "target" => [$lat2,$lng2] ];
    if(isset($_GET["id"]))
        $result["id"] = $_GET["id"];
    try
    {
        $pdo = DB::initialize($DB_NAME,$DB_USER,$DB_PASSWORD);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);

        /// se ci sono errori ritorna null
        /// se non ci sono errori restituisce un set di record  uno per arco
        /// 
        /// select * from _gcb_directions (  st_geomfromewkt( 'srid=4326; POINT(8.89486014957786 39.098766189688)') , 
        ///                                  st_geomfromewkt( 'srid=4326; POINT(8.84690765546061 39.0520890133367)' ) ) 
        ///         as ( id  integer,sentieri text,isfrom bool,isto bool,path text,msg text,cost numeric)
        ///
        $arcslist = DB::directionsForZone($lat1, $lng1, $lat2, $lng2 );
        if (isset($arcslist) )
        {
            
            /// ( 
            ///  oder: ordinamento archi
            ///  nodes: text 'i nodi del path separati da _ '
            ///  arcs: text 'gli archi del path separati da _ '
            ///  id: arcid,  'arc id'
            ///  sentieri: text, 'id dei sentieri di cui fa parte l'arco'
            ///  path: text, 'geom web encoded dell'arco'
            ///  msg_direct : messaggio per l'arco in entrata da from 
            ///  msg_reverse : messaggio per l'arco in entrata da to
            /// )
            $result ["arcs"] = $arcslist;
        }
    }
    catch (Exception $ex)
    {
    }
}
Logger::logInfo(json_encode($result));
echo json_encode($result);
?>
