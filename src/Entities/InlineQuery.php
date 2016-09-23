<?php

namespace GisAgentTB\TelegramBot\Entities;

use GisAgentTB\TelegramBot\Exception\BotException;
use GisAgentTB\TelegramBot\DB;

class InlineQuery 
{
    private $action;
    private $params;
    private $owner;
    private $ownerList;     //Owner con stesso userid ma chat distinte
    private $location;
    private $result;
    private $queryId;
    
    public function __construct($update)
    {
        if(isset($update["inline_query"]["query"]))
        {
            $query = explode(" ", $update["inline_query"]["query"]);
            $this->action = $query[0];
            $this->params = array_splice($query, 1, count($query)-1);
            $this->queryId = $update["inline_query"]["id"];
            
            if(isset($update["inline_query"]["location"]))
            {
                $this->location = $update["inline_query"]["location"];
            }
            else
            {
                $this->location = null;
            }
        }
        
        $this->ownerList = DB::findOwner($update["inline_query"]["from"]["id"]); 
        if(isset($this->ownerList))
        {
            $date = date("Y-m-d h:i:sa", (time() - 3));
            $i = 0;
            while($i<count($this->ownerList) && 
                $this->ownerList[$i]["userid"] != $this->ownerList[$i]["chatid"])
            {
                $this->ownerList[$i]["diff_access"] = DB::setOwnerLastAccess($userId, null, $date);
                $i++;
            }
            $this->owner = $this->ownerList[$i];
        }
        
        $this->result["origin"] = "InlineQuery class";
        $this->result["state"] = INLINE_QUERY_REQUEST_INTERNAL_ERROR;
        $this->result["params"] = [];
    }
    
    public function getOwner() //L'owner dell'inline query è quello della chat privata
    {
        if(isset($this->owner))
        {
            return $this->owner;
        }
        else
        {
            return null;
        }
    }
    
    public function getChatIdList()         //Tutte le chat registrate di quell'utente (personale e di gruppo)
    {
        if(isset($this->ownerList))
        {
            $chatIdList = [];
            foreach($this->ownerList as $owner)
            {
                $chatIdList[] = $owner["chatid"];
            }
            return $chatIdList;
        }
        return null;
    }
    
    public function getQueryId()
    {
        return $this->queryId;
    }
    
    public function manage()
    {
        if(isset($this->owner)) //Che dipende da ownerlist, quindi sono settati entrambi
        {
            try
            {
                $latitude = null;
                $longitude = null;

                if(isset($this->location))
                {
                    $latitude = $this->location["latitude"];
                    $longitude = $this->location["longitude"];
                }

                switch($this->action)
                {
                    case "search":
                    {
                        $this->search($latitude, $longitude);
                        break;
                    }
                    case "trips":
                    {
                        $this->trips($latitude, $longitude);  //Mostra tutti i trips pubblici (no trips proprietari privati)
                        break;
                    }
                }
            }
            catch(BotException $exc)
            {   
                $this->result["state"] = DB_INTERNAL_ERROR;
            }
        }
        else
        {
            $this->result["state"] = INLINE_OWNER_NOT_SET_INTERNAL_ERROR;
        }
        return $this->result;
    }
    
    public function search($latitude, $longitude)
    {
        $areaList = [];
        if(isset($this->params))
        {
            if(isset($this->params[0]) && preg_match('/\\d/', $this->params[0]) 
                && $latitude != null && $longitude != null)  //Se il parametro è un numero
            {
                $areaList = DB::searchAreaByLocation($latitude, $longitude, $this->params[0]);
            }
            else if(isset($this->params[0]))
            {
                $areaName = $this->params[0];
                $areaList = DB::searchAreaByName($areaName);          //Aree con nome simile
            }
            else if($latitude != null && $longitude != null)
            {
                $areaList = DB::searchAreaByLocation($latitude, $longitude);
            }
            if($areaList != null)
            {
                $this->result["state"] = SEARCH_REQUEST;
                foreach($areaList as $area)
                {
                    $this->result["params"]["results"][] = array("id" => $area["id"], "name" => $area["name"], "url" => 
                        'https://gisepi.crs4.it/tgbot/map/amap.php?i=' .  $area["id"] . '&lang=' . $this->owner["language"]);
                }
            }
            else 
            {
                $this->result["state"] = INLINE_QUERY_REQUEST_NO_RESULTS_FOUND;
            }
        }
        else 
        {
            $this->result["state"] = SEARCH_REQUEST_INTERNAL_ERROR;
        }
    }  
    
    public function trips($latitude, $longitude)
    {
        //Restituisce tutte le chat a cui l'utente appartiene in quel momento
        $chatIdList = $this->getChatIdList();
        //// errori in gettype params[0] anche gli interi sono visti come stringhe!!!!
        if(isset($this->params) && isset($this->params[0]) && ctype_digit($this->params[0])
            && $latitude != null && $longitude != null)
        {
            $trips = DB::searchTripsByLocation($latitude, $longitude, $chatIdList, $this->params[0]);
        }
        else if( isset($this->params) && isset($this->params[0]) )
        {
            $trips = DB::searchTripsByWord($this->params[0], $chatIdList);
        }
        else if($latitude != null && $longitude != null)
        {
            $trips = DB::searchTripsByLocation($latitude, $longitude, $chatIdList);
        }

        if($trips != null)
        {
            $this->result["state"] = TRIPS_REQUEST;
            foreach($trips as $trip)
            {
                $key = null;
                foreach($this->ownerList as $owner)
                {
                    if($owner["chatid"] == $trip["chatid"])   //Se il tragitto è proprietario (ovvero corrisponde
                    {                                         //la chat id)
                        $key = DB::getKey($trip["id"], "visibility");
                    }
                }
                
                if($trip["name"] == null)
                {
                    $trip["name"] = "No name";
                }
                
                if (isset($key))
                {
                    $this->result["params"]["results"][] = array("id" => $trip["id"], "name" => $trip["name"], 
                        "url" => 'https://gisepi.crs4.it/tgbot/map/emap.php?i=' . $trip["id"] . "&vk=" . $key .
                        '&lang=' . $this->owner["language"]);
                }
                else
                {
                    $this->result["params"]["results"][] = array("id" => $trip["id"], "name" => $trip["name"], 
                        "url" => 'https://gisepi.crs4.it/tgbot/map/emap.php?i=' . $trip["id"] .
                        '&lang=' . $this->owner["language"]);
                }
            } 
        }
        else
        {
            $this->result["state"] = INLINE_QUERY_REQUEST_NO_RESULTS_FOUND;
        }
    }
}