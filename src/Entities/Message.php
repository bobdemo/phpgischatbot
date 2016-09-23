<?php

namespace GisAgentTB\TelegramBot\Entities;

use GisAgentTB\TelegramBot\Exception\BotException;
use GisAgentTB\TelegramBot\Request;
use GisAgentTB\TelegramBot\Logger;
use GisAgentTB\TelegramBot\DB;

class Message 
{
    private $message;
    private $date;
    private $owner;
    private $result;
    
    public function __construct($update) 
    {
        $this->message = $update["message"];
        $micro_date = microtime();
        $dateArray = explode(" ",$micro_date);
        $this->date = date("Y-m-d H:i:s",$dateArray[1]);
        //$this->date = date("Y-m-d h:i:s", $this->message["date"]);
        Logger::logInfo($this->date);
        $userId = $this->message["from"]["id"];
        $chatId = $this->message["chat"]["id"];

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
            $this->owner["diff_access"] = DB::setOwnerLastAccess($userId, $chatId, $this->date);
            //Prende l'ultimo accesso e lo aggiorna col corrente
        }
        
        $this->result["origin"] = "Message class";
        $this->result["state"] = INTERNAL_ERROR;
        $this->result["params"]["callback_data"] = [];
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

    public function manage()
    {  
        if(isset($this->owner))
        {
            try
            {
                if(isset($this->message["group_chat_created"]))     //Notifica di gruppo creato
                {
                    $this->result["state"] = SET_LANGUAGE_REQUEST;
                    $this->result["params"]["callback_data"] = array("language en registration", "language it registration");
                }
                else 
                {   
                    $validReply = false;
                    if(isset($this->message["reply_to_message"]))  //Messaggio in formato risposta
                    {
                        $this->replyTo = $this->message["reply_to_message"];
                        $validReply = $this->manageReply();
                        //Se la reply non è fra quelle generate forzatamente dal bot viene analizzata come
                        //normale messaggio
                    }
                    if(!$validReply)
                    {
                        if(isset($this->message["location"]))     
                        //Se si invia una posizione inserisce un track point in tragitto esistente o ne crea uno
                        {
                            $this->manageTrackPoint();
                        }
                        else if (isset($this->message["text"]) && 
                                (strpos($this->message["text"], "Show map of") !== false ||
                                strpos($this->message["text"], "Mostra la mappa di") !== false ||
                                strpos($this->message["text"], "No results found") !== false ||
                                strpos($this->message["text"], "Nessun risultato") !== false))
                        {
                            $this->result["state"] = INLINE_RESULT_CHOICE; 
                            //Testo dell'inline result, non viene parsato
                        }
                        else//Se si invia qualsiasi altra cosa inserisce un elemento del roadbook
                        {
                            $this->manageRoadBookElement();
                        }
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
    
    public function manageReply()
    {
        $date = date("Y-m-d h:i:sa", $this->replyTo["date"]);
        if($this->replyTo["text"] == "Only chat members will be able to see this track, how do you want to call it?"
            || $this->replyTo["text"] == "Il tuo percorso sarà visibile solo ai membri della chat, come vuoi chiamarlo?"
            || $this->replyTo["text"] == "Everybody will be able to see this track, how do you want to call it?"
            || $this->replyTo["text"] == "Il tuo percorso sarà visibile a tutti, come vuoi chiamarlo?"
            || $this->replyTo["text"] == "How do you want to call this track?"
            || $this->replyTo["text"] == "Come vuoi chiamare questo percorso?")
        {
            $currentTrack = DB::findTrackOpenAtDate($this->owner["chatid"], $date, false);
            
            if(isset($currentTrack) && isset($currentTrack["id"]))
            {
                $isFirstName = $currentTrack["name"] == null;
                $trackHasPoints = DB::trackHasPoints($currentTrack["id"]);
                $success = DB::setTrackName($currentTrack["id"], $this->message["text"]);
            
                if($success)
                {
                    if($trackHasPoints && $isFirstName)
                    {
                        //Se il track non è vuoto ed è la prima volta che gli viene dato un nome (ovvero lo si sta
                        //creando in quel momento con dei punti già aggiunti)
                        //allora va indicato che le posizioni inviate in precedenza sono state aggiunte al track
                        $this->result["state"] = SET_IMPLICIT_TRACK_NAME_CHOICE;
                    }
                    else if($isFirstName)
                    {
                        //Altrimenti si dà un semplice messaggio di possibilità di aggiunta posizioni
                        $this->result["state"] = SET_FIRST_TRACK_NAME_CHOICE;
                    }
                    else
                    {
                        $this->result["state"] = SET_TRACK_NAME_CHOICE;
                    }
                    return true;
                }
            }
        }
        $this->result["state"] = SET_TRACK_NAME_CHOICE_INTERNAL_ERROR;
        return false;
    }
    
    public function manageTrackPoint()
    {
        ////// Da aggiungere il controllo sull'accesso ad un area di trekking
        ////// verificare come informare l'utente a visualizzare le info 
        
        $latitude = $this->message["location"]["latitude"];
        $longitude = $this->message["location"]["longitude"];
	
        $this->addTrackPoint($latitude, $longitude);
    }
    
    public function addTrackPoint($latitude, $longitude)
    {
        $newTrack = false;
        $trackObject = DB::findTrackOpenAtDate($this->owner["chatid"], $this->date, false);
        if(!isset($trackObject))
	{
            $newTrack = true;
            $trackObject = DB::initTrack($this->owner["userid"], $this->owner["chatid"]);
        }
        
        if(!isset($trackObject))
        {
            $this->result["state"] = CREATE_TRACK_CHOICE_INTERNAL_ERROR;    
        }
        else 
        {
            $newTrackPoint = DB::insertTrackPoint($trackObject["id"], $this->owner["userid"], $this->owner["chatid"], $latitude, $longitude, $this->date);
            if(!isset($newTrackPoint))
            {     
                $this->result["state"] = CREATE_TRACKPOINT_CHOICE_INTERNAL_ERROR;
            }
            else
            {
                $previousTrackPoint = DB::findPreviousTrackPoint($newTrackPoint["trackid"], $newTrackPoint["id"]);
                if(!$newTrack)
                {
                    if(strtotime($this->owner["diff_access"]) >= strtotime("00:05:00"))
                    {
                        $this->result["state"] = CREATE_LATE_TRACKPOINT_CHOICE_SET_TAG_REQUEST;
                    }
                    else
                    {
                        $this->result["state"] = CREATE_TRACKPOINT_CHOICE_SET_TAG_REQUEST;
                    }
                }
                else
                {
                    $this->result["state"] = CREATE_FIRST_TRACKPOINT_CHOICE_SET_TAG_REQUEST;
                }
                $this->result["params"]["callback_data"] = array("tag danger " . $this->date . " ",
                    "tag poi " . $this->date . " ",
                    "tag generic " . $this->date . " ");
            }
        }
    }

    public function manageRoadBookElement()
    {
        $text = null;
        $type = null;
        $name = null;
        
        if (isset($this->message["text"]))      //Contenuto semplice testuale
        {
            $text = $this->message["text"];
            //Inserire controllo per il testo inviato con force reply (puo' essere disattivato per sbaglio dall'utente)
            $type = "text";
            $name = null;
        }
        else 					//File
        {
            if (isset( $this->message["caption"]))	//File con caption
            {
                $text = $this->message["caption"]; 
            }
            else  					//File senza caption
            {
                $text = "";
            }

            if(isset($this->message["voice"])) 		//Audio
            {
                $type = "audio";
                $name = $this->message["voice"]["file_id"];
            }
            else if(isset($this->message["photo"])) 	//Foto
            {
                $type = "photo";
                $name = $this->message["photo"][count($this->message["photo"]) - 1]["file_id"];
            }
            else if(isset($this->message["video"])) 	//Video
            {
                $type = "video";
                $name = $this->message["video"]["file_id"];
            }
            else					//Tipo file non supportato
            {
                Logger::logInfo(json_encode($this->message));
                $this->result["state"] = ILLEGAL_FILE_CHOICE;
            }
        }
        $this->addRoadbookElement($text, $name, $type); 
    }
    
    public function addRoadbookElement($text, $name, $type)
    {
        $userId = $this->message["from"]["id"];
        $chatId = $this->message["chat"]["id"];
        $track = DB::findTrackOpenAtDate ($chatId, $this->date, false);	//Controlla se c'è un tragitto aperto
        if(!isset($track))
        {
            $this->result["state"] = UNBOUND_FILE_CHOICE;
        }
        else
        {
            $success = DB::insertRoadBookElement($track["id"], $userId, $text, $name, $type, $this->date);
            if($success)
            {
                if(strtotime($this->owner["diff_access"]) >= strtotime("00:05:00"))
                {
                    $this->result["state"] = LATE_FILE_CHOICE;
                }
                else
                {
                    $this->result["state"] = FILE_CHOICE;
                }
            }
            else
            {
                $this->result["state"] = CREATE_ROADBOOK_ELEMENT_CHOICE_INTERNAL_ERROR;
            }
        }
    }
}
