<?php

namespace GisAgentTB\TelegramBot;

class SessionManager 
{   
    public static function updateSession($owner, $session)
    {
        try 
        {
            return DB::updateSession($session, $owner["userid"], $owner["chatid"]);
        } 
        catch (BotException $ex) 
        {
            return false;
        }
    }
       
    public static function manageSession($owner)
    {
        $tracksActions = explode("/", $owner["session"]);
        $tracks = [];

        foreach($tracksActions as $trackActions)
        {
            $tracks[] = explode(" ", $trackActions);
        }

        Logger::logInfo(json_encode($tracks));
        $summary = self::getSummary($tracks);
        
        if(self::emptySession($owner["userid"], $owner["chatid"]))
        {
            return $summary;
        }
        else
        {
            return null;
        }
    }
    
    
    private static function getSummary($tracks)
    {
        $summary = [];
        
        $summary["trackNumber"] = count($tracks);
        $summary["emptyClosedTracks"] = 0;
        $summary["closedTracks"] = 0;
        $openTrack = false;
        $offlineOpenTrack = false;
        
        foreach($tracks as $track)
        {
            $points=0;
            $openTrack = true;
            
            foreach($track as $action)
            {
                if($action == "start")      //Ci si riferisce ad un percorso aperto mentre si era offline
                {
                    $offlineOpenTrack = true;
                }
                else if($action == "stop")
                {
                    $openTrack = false;
                    $summary["closedTracks"]++;
                    if($points==0)
                    {
                        $summary["emptyClosedTracks"]++;
                    }
                }
                else if($action == "point")
                {
                    $points++;
                }
            }
        }

        if($openTrack)
        {
            $summary["current"]["points"] = 0;
            $summary["current"]["contents"] = 0;
            if($offlineOpenTrack)               //Le considerazioni sulla mancanza di nome o privacy
            {                                   //sono valide solo se il percorso è stato aperto offline
                $summary["current"]["name"] = false;
                $summary["current"]["privacy"] = false;
            }
            
            foreach($tracks[$summary["trackNumber"] - 1] as $action)
            {
                if($offlineOpenTrack)
                {
                    if($action == "name")
                    {
                        $summary["current"]["name"] = true;
                    }
                    if($action == "privacy")
                    {
                        $summary["current"]["privacy"] = true;
                    }
                }
                if($action == "point")
                {
                    $summary["current"]["points"]++;
                }
                if($action == "content")
                {
                    $summary["current"]["contents"]++;
                }
            }
        }
        return $summary;
    }
    
    private function emptySession($userId, $chatId)
    {
        try
        {
            return DB::emptySession($userId, $chatId);
        } 
        catch (Exception $ex) 
        {
            return false;
        }
    }
    
}
