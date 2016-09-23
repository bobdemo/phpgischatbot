<?php

namespace GisAgentTB\TelegramBot;

use GisAgentTB\TelegramBot\Exception\BotException;
use GisAgentTB\TelegramBot\Logger;


class Request 
{
   private static $telegram;
   private $mapzen_api_key = "your_api_key";
   /**
     * Initialize
     *
     * @param Telegram $telegram
     */
   
    public static function initialize(Telegram $telegram)
    {
        if (is_object($telegram)) 
        {
            self::$telegram = $telegram;
            return true;
        } 
        else 
        {
            Logger::logError('Request Class: No Telegram Object');
            return false;
        }
    }

    public static function sendMessage ($chatId, $data)
    {
        $action = "sendmessage?";
        if (isset($chatId))
        {
            $params = null;
            if ( isset($data) && isset($data["text"]))
            {
                $params = array("chat_id" => $chatId, "text" => $data["text"], "parse_mode" => 'HTML');
            }
            if(isset($data['prevId']))
            {
               $params["reply_to_message_id"] = $data["prevId"];
            }
            if ( isset($data['reply_markup']) )
            {
               $params["reply_markup"] = json_encode($data["reply_markup"]);
            }
            
            $curl = new Curl(self::$telegram->getApiUrl() . $action, $params);
            
            $response = $curl->getResponse();
            
            $curl = null;
       
            Logger::logInfo("Message response: " . $response);
        } 
        else 
        {
            throw new BotException('Request class - Wrong chatId or reply in buildChatResponse', 0);
        }
    }
    
    public static function sendQueryResponse ($queryId, $data)
    {
        $action = "answerinlinequery?";
        if (isset($queryId))
        {
            //$action = $action . "inline_query_id=" . $queryId . "&results=";
             
            if ( isset($data) )
            {
                $curl = new Curl(self::$telegram->getApiUrl() . $action, array("inline_query_id" => $queryId, "results" => json_encode($data), "cache_time" => 2));
            }
            else
            {
                $curl = new Curl(self::$telegram->getApiUrl() . $action, array("inline_query_id" => $queryId, "results" => json_encode([]), "cache_time" => 2));
            }
       
            // $sendto = self::$telegram->getApiUrl() . $action;
        ////// gestione risposta API!!!!!!!!!!!!!!!
            
            $response = $curl->getResponse();
            
            $curl = null;
            // $result = file_get_contents($sendto);
            Logger::logInfo("Inline response: " . $response);
        } 
        else 
        {
            throw new BotException('Request class: query id not defined', 0);
        }
    }
   
    //////   https://api.telegram.org/file/bot<token>/<file_path>
 
    public static function getPath($latlon){
        $path = Request::getPathFromPathway($latlon);
        Logger::logInfo('-----Pathway path '. json_encode($path));
        if ( !isset($path) || !isset($path["path"]) )
        {
            $path = Request::getPathFromMapzen($latlon);
        } 
        return $path;
    }
    
    
    /**
     * getPathFromMazen  ////
     * via post con parametri da json da costruire tramite lettura points
     * 
     * @param array $latlon  
     *
     * @return 
     */
    public static function getPathFromPathway($latlon)
    {
        $url = "";
        $encoded = "";
        $path = [ ];
        if ( isset($latlon) ) {
            /// se la distanza è < 10 metri li connette semplicemente da database
            $url = "https://gisepi.crs4.it/tgbot/map/PathwayDirections.php?json=" . json_encode($latlon);
            $pathJson = file_get_contents( $url );
            Logger::logInfo($pathJson);
            if ( isset($pathJson ) && isset($pathJson["arcs"]) ) {
               
                $pathObj = json_decode($pathJson);
                foreach ($pathObj["arcs"] as $line) {
                    $encoded .= $line["arcs"];
                }
                $path = [  "type" => "pedestrian",  "path" => self::createPolyline(self::decodeGooglePath($encoded))];
                
            }
        }
         Logger::logInfo(json_encode($path));
        return $path;
    }
    
    
    /**
     * getPathFromMazen  ////
     * via post con parametri da json da costruire tramite lettura points
     * action elevation, path, search
     * @param action
     * @param array $latlon
     *
     * @return 
     */
    public static function getPathFromMapzen($latlon)
    {
        $url = "";
        $encoded = "";
        if ( isset($latlon) ) {
            /// se la distanza è < 10 metri li connette semplicemente da database
            
            $locations[ "locations" ] = [ [ "lat" => $latlon[0], "lon" => $latlon[1] ],[ "lat" => $latlon[2], "lon" => $latlon[3] ] ];
            $locations[ "costing" ] = "bicycle";
            $locations[ "directions_options" ] = array ( "units" => "meters" );
            $url = "https://valhalla.mapzen.com/route?id=gischatbot&api_key=" + $mapzen_api_key + "&json=" . json_encode($locations);
            $pathJson = file_get_contents( $url );
            $pathObj = json_decode($pathJson);
            foreach ($pathObj->trip->legs as $line) {
                $encoded .= $line->shape;
            }
            $path = [ "type" => "bicycle", "path" => self::createPolyline(self::decodeMapzenPath($encoded)) ];
        }
        return $path;     
    }

    
    public static function decodeMapzenPath($encoded)
    {
        $length = strlen($encoded);
        $index = 0;
        $points = array();
        $latitude = 0;
        $longitude = 0;
        while ($index < $length) {
            $byte = 0;
            $shift = 0;
            $result = 0;
            do {
                $byte = ord(substr($encoded, $index++)) - 63;
                $result |= ($byte & 0x1f) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);
            $latitudeChange = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $latitude += $latitudeChange;
            $shift = 0;
            $result = 0;
            do {
                $byte = ord(substr($encoded, $index++)) - 63;
                $result |= ($byte & 0x1f) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);
            $longitudeChange = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $longitude += $longitudeChange;
            $points[] = array($latitude * 1e-6, $longitude * 1e-6);
        }
        return $points;
    }
    
    /*
 * Copyright (c) 2008 Peter Chng, http://unitstep.net/
 * 
 * 
 * Decodes a polyline that was encoded using the Google Maps method.
 *
 * The encoding algorithm is detailed here:
 * http://code.google.com/apis/maps/documentation/polylinealgorithm.html
 *
 * This function is based off of Mark McClure's JavaScript polyline decoder
 * (http://facstaff.unca.edu/mcmcclur/GoogleMaps/EncodePolyline/decode.js)
 * which was in turn based off Google's own implementation.
 *
 * This function assumes a validly encoded polyline.  The behaviour of this
 * function is not specified when an invalid expression is supplied.
 *
 * @param String $encoded the encoded polyline.
 * @return Array an Nx2 array with the first element of each entry containing
 *  the latitude and the second containing the longitude of the
 *  corresponding point.
 */
    
    public static function decodeGooglePath($encoded)
    {
        $length = strlen($encoded);
        $index = 0;
        $points = array();
        $lat = 0;
        $lng = 0;

        while ($index < $length)
        {
          // Temporary variable to hold each ASCII byte.
          $b = 0;

          // The encoded polyline consists of a latitude value followed by a
          // longitude value.  They should always come in pairs.  Read the
          // latitude value first.
          $shift = 0;
          $result = 0;
          do
          {
            // The `ord(substr($encoded, $index++))` statement returns the ASCII
            //  code for the character at $index.  Subtract 63 to get the original
            // value. (63 was added to ensure proper ASCII characters are displayed
            // in the encoded polyline string, which is `human` readable)
            $b = ord(substr($encoded, $index++)) - 63;

            // AND the bits of the byte with 0x1f to get the original 5-bit `chunk.
            // Then left shift the bits by the required amount, which increases
            // by 5 bits each time.
            // OR the value into $results, which sums up the individual 5-bit chunks
            // into the original value.  Since the 5-bit chunks were reversed in
            // order during encoding, reading them in this way ensures proper
            // summation.
            $result |= ($b & 0x1f) << $shift;
            $shift += 5;
          }
          // Continue while the read byte is >= 0x20 since the last `chunk`
          // was not OR'd with 0x20 during the conversion process. (Signals the end)
          while ($b >= 0x20);

          // Check if negative, and convert. (All negative values have the last bit
          // set)
          $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));

          // Compute actual latitude since value is offset from previous value.
          $lat += $dlat;

          // The next values will correspond to the longitude for this point.
          $shift = 0;
          $result = 0;
          do
          {
              $b = ord(substr($encoded, $index++)) - 63;
              $result |= ($b & 0x1f) << $shift;
              $shift += 5;
          }
          while ($b >= 0x20);

          $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
          $lng += $dlng;

            // The actual latitude and longitude values were multiplied by
            // 1e5 before encoding so that they could be converted to a 32-bit
            // integer representation. (With a decimal accuracy of 5 places)
            // Convert back to original values.
          $points[] = array($lat * 1e-5, $lng * 1e-5);
        }
        return $points;
    }
    
    public static function createPolyline($points)
    {
        $result = "LINESTRING(";  
        $add = " ";
        foreach ($points as $point) {
            $result .= $add . $point[1] . " " . $point[0];
            $add = " ,";
        }
        $result .= ")";
        return $result;
    }
     
}
