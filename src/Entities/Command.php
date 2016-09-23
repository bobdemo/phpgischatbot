<?php

namespace GisAgentTB\TelegramBot\Entities;

use GisAgentTB\TelegramBot\Exception\BotException;
use GisAgentTB\TelegramBot\DB;


class Command
{
    private $message;
    private $command;
    private $date;
    private $owner;
    private $params;
    private $result;

    public function __construct($update)
    {
        $this->message = $update["message"];
        $micro_date = microtime();
        $dateArray = explode(" ",$micro_date);
        $this->date = date("Y-m-d H:i:s",$dateArray[1]);
        //$this->date = date("Y-m-d h:i:s", $this->message["date"]);
        $userId = $this->message["from"]["id"];
        $chatId = $this->message["chat"]["id"];
        $commandWords = explode(" ",$this->message["text"]);
        $this->command = $this->getCommandName($commandWords[0]);
        $this->params = array_splice($commandWords, 1, count($commandWords)-1);  //Elimina il primo elemento dell'array
        $this->owner = DB::findOwner($userId, $chatId);
        if ( !isset($this->owner) )
        {
            $username = null;
            $firstName = null;
            $lastName = null;
            if ( isset ( $this->message["from"]["username"] ) ) 
            {
                $username = $this->message["from"]["username"];
            }
            if ( isset ( $this->message["from"]["first_name"] ) ) 
            {
                $firstName = $this->message["from"]["first_name"];
            }
            if ( isset ( $this->message["from"]["last_name"] ) ) 
            {
                $lastName = $this->message["from"]["last_name"];
            }
            $language = DB::getChatLanguage($chatId);
            if(!isset($language))
            {
                $language = 'en';
            }
            $this->owner = DB::insertOwner($userId, $username, $firstName, $lastName,  $chatId, $this->date, $language);
        }
        else
        {
            $this->owner["diff_access"] = DB::setOwnerLastAccess($userId, $chatId,$this->date);
            //Prende l'ultimo accesso e lo aggiorna col corrente
        }
        
        $this->result["origin"] = "Command class";
        $this->result["state"] = INTERNAL_ERROR;
        $this->result["params"] = [];
    }
    
    public function getOwner()
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

    //return the name of the command
    public function getCommandName($text)
    {
        $words = explode("@", $text);
        return $words[0];
    }

    public function manage()
    {
        if(isset($this->owner))
        {
            try
            {
                switch ($this->command)
                {
                    case "/start":
                    {
                        $this->start();
                        break;
                    }
                    case "/language":
                    {
                        $this->start();
                        break;
                    }
                    case "/begin":
                    {
                        $this->begin();
                        break;
                    }
                    case "/setprivate":
                    {
                        $this->setPrivacy(true);
                        break;
                    }
                    case "/setpublic":
                    {
                        $this->setPrivacy(false);
                        break;
                    }
                    case "/setname":
                    {
                        $this->setName();
                        break;
                    }
                    case "/settag":
                    {
                        $this->setTag();
                        break;
                    }
                    case "/show":
                    {
                        $this->show();
                        break;
                    }
                    case "/end":
                    {
                        $this->end();
                        break;
                    }
                    case "/confirm":
                    {
                        $this->confirm();
                        break;
                    }
                    case "/help":
                    {
                        $this->help();
                        break;
                    }
                    case "/about":
                    {
                        $this->about();
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

    //Il comando language causa la definizione di una costante language da utilizzare per 
    //identificare il testo da mostrare nel linguaggio corretto

    public function start()
    {
        $this->result["state"] = SET_LANGUAGE_REQUEST;
        $this->result["params"]["callback_data"] = array("language en registration", "language it registration"); 
    }
    
    public function begin()
    {
        $openedTrack = DB::findTrackOpenAtDate ($this->owner["chatid"], $this->date, true);
        
        if (!isset ($openedTrack))
        {
            $newTrack = DB::initTrack($this->owner["userid"], $this->owner["chatid"]);
            if($newTrack != null )
            {
                DB::validatePreviousTrack($newTrack["id"], $this->owner["chatid"]);
                $this->result["state"] = CREATE_TRACK_CHOICE_SET_TRACK_PRIVACY_REQUEST;
                $this->result["params"]["callback_data"] = array("public track " . $newTrack["start"], 
                    "private track " . $newTrack["start"]);
            }
            else 
            {
                $this->result["state"] = CREATE_TRACK_CHOICE_INTERNAL_ERROR;
            }
        }
        else  
        {
            $this->result["state"] = CREATE_CONCURRENT_TRACK_CHOICE;
        }
    }
    
    public function setPrivacy($isPrivate)
    {
        $track = DB::findTrackOpenAtDate ($this->owner["chatid"], $this->date, false);
        if (isset ($track) )
	{
	    $trackId = $track["id"];
            
            if(DB::setTrackPrivate($trackId, $isPrivate))
            {
                if($isPrivate)
                {
                    $this->result["state"] = SET_PRIVATE_TRACK_CHOICE;
                }
                else
                {
                    $this->result["state"] = SET_PUBLIC_TRACK_CHOICE;
                }
            }
            else
            {
                $this->result["state"] = SET_TRACK_PRIVACY_CHOICE_INTERNAL_ERROR;
            }
        }
        else
        {
            $this->result["state"] = PRIVACY_ON_NOT_OPEN_TRACK_CHOICE;
        }
    }
    
    public function setName()
    {
        if(isset($this->params[0])) //Se si è inserito anche il nome nel comando
        {
            $track = DB::findTrackOpenAtDate ($this->owner["chatid"], $this->date, false);
            if (isset ($track) )
            {
                $trackId = $track["id"];
                $trackName = implode(" ", $this->params);
                
                if(DB::setTrackName($trackId, $trackName))
                {
                    $this->result["state"] = SET_TRACK_NAME_CHOICE;
                }
            }
            else
            {
                $this->result["state"] = NAME_ON_NOT_OPEN_TRACK_CHOICE;
            }
        }
        else
        {
            $this->result["state"] = SET_TRACK_NAME_REQUEST;
        }
    }
    
    public function setTag()
    {
        $track = DB::findTrackOpenAtDate ($this->owner["chatid"], $this->date, false);
        if (isset ($track))
        {
            if(isset($track["id"]) && DB::trackHasPoints($track["id"]))
            {
                if(isset($this->params[0]) && (strcasecmp($this->params[0], "generic") == 0
                    || strcasecmp($this->params[0], "danger") == 0
                    || strcasecmp($this->params[0], "poi") == 0))  //Se si è inserito anche il nome corretto del tag nel comando
                {
                    if(DB::setTrackPointTag($this->date, $this->params[0], $this->owner["chatid"]))
                    {
                        switch ($this->params[0])
                        {
                            case 'generic':
                            {
                                $this->result["state"] = SET_TAG_GENERIC_CHOICE;
                                break;
                            }
                            case 'danger':
                            {
                                $this->result["state"] = SET_TAG_DANGER_CHOICE;
                                break;
                            }
                            case 'poi':
                            {
                                $this->result["state"] = SET_TAG_POI_CHOICE;
                                break;
                            }
                        }
                    }
                }
                else
                {
                    $this->result["state"] = SET_TAG_REQUEST;
                    $this->result["params"]["callback_data"] = array("tag danger " . $this->date . " ",
                        "tag poi " . $this->date . " ",
                        "tag generic " . $this->date . " ");
                }
            }
            else
            {
                $this->result["state"] = TAG_ON_NOT_EXISTENT_TRACKPOINT;
            }
        }
        else
        {
            $this->result["state"] = TAG_ON_NOT_OPEN_TRACK_CHOICE;
        }
    }

    public function show()
    {
        $track = DB::findTrackOpenAtDate ($this->owner["chatid"], $this->date, false);
        /// solo trackpoints e linee per editing
        /// 'EditTrack map';
        /// solo visualizzazione track + info 
        /// 'RoadBook map';
	if (isset ($track) )
	{
	    $trackId = $track["id"];
            //// verifica su proprietario track
            //// richiede la chiave valida di editing, se non presente
            //// viene creata su db key semplice = EXTRACT(EPOCH FROM now() )
            //// vengono verificate solo le ultime 10 cifre (anche millisecondi) in formato stringa
            $key = DB::getKey($trackId, "management");
            if (isset($key))
            {  ///// problema per le dimensioni:  43 senza valori parametri
               ///// t di max 10 char e k di max 10 char 
                $this->result["params"]["url"] = array("https://gisepi.crs4.it/tgbot/map/emap.php?i="
                    . $trackId . "&mk=" . $key . "&lang=" . $this->owner["language"]);
                if($track["name"] == null)
                {
                    $track["name"] = "No name";
                }
                $this->result["params"]["name"] = $track["name"];
                $this->result["state"] = SHOW_MAP_CHOICE;
            }
            else ////dovrebbe gestire errore
            {
                $this->result["state"] = EDIT_KEY_NOT_SET_INTERNAL_ERROR;
            } 
	}
        else
        {
            $this->result["state"] = SHOW_NOT_OPEN_TRACK_MAP_CHOICE;
        } 
    }

    public function end()
    {
        $openedTrack = DB::findTrackOpenAtDate ($this->owner["chatid"], $this->date, false);
        
        if(isset($openedTrack))
        {
            if(DB::trackHasPoints($openedTrack["id"]))
            {
                $track = DB::closeTrack($openedTrack["id"]);
                if(isset($track))
                {
                    $this->result["state"] = END_TRACK_CHOICE_OVERHAUL_REQUEST;
                    $this->result["params"]["callback_data"] = array("confirm track " . $track["stop"]);
                }
                else
                {
                    $this->result["state"] = END_TRACK_CHOICE_INTERNAL_ERROR;
                }
            }
            else
            {
                DB::deleteTrack($openedTrack["id"]);
                $this->result["state"] = END_EMPTY_TRACK_CHOICE;      
            }
        }
        else
        {
            $this->result["state"] = ACTION_ON_NOT_EXISTENT_TRACK_CHOICE;
        }
    }
    
    public function confirm()
    {
        $trackObject = DB::findTrackClosedAtDate($this->owner["chatid"], $this->date);
        if(isset($trackObject))
        {
            if($trackObject["private"] == null)
            {
                $trackObject["private"] = true;
            }
            if($trackObject["name"] == null)
            {
                $trackObject["name"] = 'No name';
            }
            
            if(DB::validateTrack($trackObject["id"], $trackObject["private"], $trackObject["name"]))
            {
                $this->result["state"] = NO_OVERHAUL_TRACK_CHOICE;
            }
            else
            {
                $this->result["state"] = CONFIRM_TRACK_INTERNAL_ERROR;
            }
        }
        else
        {
            $this->result["state"] = CONFIRM_NOT_EXISTENT_TRACK_CHOICE;
        }
    }

    public function help()
    {
	$this->result["state"] = HELP_CHOICE;
    }

    public function about()
    {
        $this->result["state"] = ABOUT_CHOICE;
    }
}
