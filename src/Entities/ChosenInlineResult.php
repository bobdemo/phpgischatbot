<?php

namespace GisAgentTB\TelegramBot\Entities;

use GisAgentTB\TelegramBot\Exception\BotException;
use GisAgentTB\TelegramBot\DB;

class ChosenInlineResult 
{
    private $resultId;
    private $chosenInlineResult;
    private $fromQueryAction;
    private $date;
    private $owner;
    private $result;
    
    public function __construct($update)
    {
        $micro_date = microtime();
        $dateArray = explode(" ",$micro_date);
        $this->date = date("Y-m-d H:i:s",$dateArray[1]);
        //$this->date = date("Y-m-d h:i:s", time());
        $this->chosenInlineResult = $update["chosen_inline_result"];
        $this->fromQueryAction = explode(" ", $this->chosenInlineResult["query"])[0];
        $this->resultId = $this->chosenInlineResult["result_id"];
        $ownerList = DB::findOwner($update["chosen_inline_result"]["from"]["id"]);
        if ( $ownerList != null ) 
        {
            $this->owner = $ownerList[0]; //L'ultimo che ha eseguito un'azione
        }

        $this->result["origin"] = "ChosenInlineResult class";
        $this->result["state"] = INTERNAL_ERROR;
        $this->result["params"] = [];
    }
    
    public function getOwner() //L'owner di chosen inline result è l'ultimo che ha acceduto con quell'userid
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

    public function manage()
    {
        if(isset($this->owner))
        {
            try
            {
                switch($this->fromQueryAction)
                {
                    case "search":
                    {
                        $this->result["state"] = SEARCH_RESULT_CHOICE;
                        break;
                    }
                    case "trips":
                    {
                        $track = DB::findTrackById($this->resultId);
                        if(isset($track))
                        {
                            if($track["chatid"] == $this->owner["chatid"])
                            {
                                $key = DB::getKey($track["id"], "management");
                                if (isset($key))
                                {
                                    $this->result["state"] = TRIPS_RESULT_CHOICE_MANAGE_TRACK_REQUEST;
                                    $this->result["params"]["url"] = array('https://gisepi.crs4.it/tgbot/map/emap.php?i=' . $track["id"] . "&mk=" . $key
                                        . "&lang=" . $this->owner["language"]);
                                }
                            }
                            else
                            {
                                $this->result["state"] = TRIPS_RESULT_CHOICE;
                            }
                        }
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
            $this->result["state"] = OWNER_NOT_SET_INTERNAL_ERROR;
        }
        return $this->result;
    }
}
