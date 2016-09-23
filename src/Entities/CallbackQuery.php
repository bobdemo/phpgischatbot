<?php

namespace GisAgentTB\TelegramBot\Entities;

use GisAgentTB\TelegramBot\Exception\BotException;
use GisAgentTB\TelegramBot\DB;

class CallbackQuery 
{
    private $callback;
    private $message;
    private $date;
    private $owner;
    private $result;
    
    public function __construct($update) 
    {
        $this->callback = $update["callback_query"];
        if ( isset ($update["callback_query"]["message"]) )
        {
            $this->message = $update["callback_query"]["message"];
            $userId = $update["callback_query"]["from"]["id"];
            $chatId = $this->message["chat"]["id"];
            $this->owner = DB::findOwner($userId, $chatId);
            $micro_date = microtime();
            $dateArray = explode(" ",$micro_date);
            $this->date = date("Y-m-d H:i:s",$dateArray[1]);
            //$this->date = date("Y-m-d h:i:s", $this->message["date"]);
        
            if ( !isset($this->owner) )
            {
                $username = null;
                $firstName = null;
                $lastName = null;
                if ( isset ( $this->callback["from"]["username"] ) ) 
                {
                    $username = $this->callback["from"]["username"];
                }
                if ( isset ( $this->callback["from"]["first_name"] ) ) 
                {
                        $firstName = $this->callback["from"]["first_name"];
                }
                if ( isset ( $this->callback["from"]["last_name"] ) ) 
                {
                    $lastName = $this->callback["from"]["last_name"];
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
            }
        }
        
        $this->result["origin"] = "CallbackQuery class";
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

    public function manage()
    {  
        if(isset($this->owner))
        {
            try
            {
                if( isset($this->callback["data"]  ) )    
                {
                    if(strpos($this->callback["data"], 'language') !== false)
                    {
                        $this->setLanguage();
                    }
                    else if(strpos($this->callback["data"], 'public track') !== false) //public track date
                    {
                        $this->setTrackPublic();
                    }
                    else if(strpos($this->callback["data"], 'private track') !== false) //private track date
                    {
                        $this->setTrackPrivate();
                    }
                    else if(strpos($this->callback["data"], 'tag') !== false) //tag tagname date trackpointid [implicit track]
                    {
                        $this->setTag();
                    }
                    else if(strpos($this->callback["data"], 'confirm track') !== false) //confirm track trackid
                    {
                        $this->confirmTrack();
                    }
                    else
                    {
                        $this->result["state"] = INVALID_CALLBACK_DATA;
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

    public function setLanguage()
    {
        $data = explode(" ", $this->callback["data"]);
        $language = $data[1];

        if(DB::updateLanguage($this->owner["chatid"], $language ))
        {
            if(isset($data[2]) && $data[2] == "registration")
            {
                $this->owner["language"] = $language;
                $this->result["state"] = ABOUT_CHOICE;
            }
            else if($language == 'en')
            {
                $this->result["state"] = SET_LANGUAGE_CHOICE_EN;
            }
            else
            {
                $this->result["state"] = SET_LANGUAGE_CHOICE_IT;
            }
        }
        else
        {
            $this->result["state"] = SET_LANGUAGE_CHOICE_INTERNAL_ERROR;
        } 
    }
    public function setTrackPublic()
    {
        $data = explode(" ", $this->callback["data"]);
        $date = $data[2] . " " . $data[3];
        $referredTrack = DB::findTrackOpenAtDate($this->owner["chatid"], $date, false);
        if(isset($referredTrack) && isset($referredTrack["id"]))
        {
            DB::setTrackPrivate($referredTrack["id"], false);
            if($referredTrack["name"] == null)
            {
                $this->result["state"] = SET_PUBLIC_TRACK_CHOICE_SET_TRACK_NAME_REQUEST; 
            }
            else
            {
                $this->result["state"] = SET_PUBLIC_TRACK_CHOICE;
            }
        }
        else
        {
            $this->result["state"] = PRIVACY_ON_NOT_OPEN_TRACK_CHOICE;
        }
    }
    
    public function setTrackPrivate()
    {
        $data = explode(" ", $this->callback["data"]);
        $date = $data[2] . " " . $data[3];
        $referredTrack = DB::findTrackOpenAtDate($this->owner["chatid"], $date, false);
        if(isset($referredTrack) && isset($referredTrack["id"]))
        {
            DB::setTrackPrivate($referredTrack["id"], true);
            if($referredTrack["name"] == null)
            {
                $this->result["state"] = SET_PRIVATE_TRACK_CHOICE_SET_TRACK_NAME_REQUEST; 
            }
            else
            {
                $this->result["state"] = SET_PRIVATE_TRACK_CHOICE;
            }
        }
        else
        {
            $this->result["state"] = PRIVACY_ON_NOT_OPEN_TRACK_CHOICE;
        }
    }
    
    public function setTag()
    {
        $data = explode(" ", $this->callback["data"]);
        $date = $data[2] . " " . $data[3];
        $tag = $data[1];
        
        $success = DB::setTrackPointTag($date, $tag, $this->owner["chatid"]);
        if($success)
        {
            $trackObject = DB::findTrackOpenAtDate ($this->owner["chatid"], $date, false);
            if(isset($trackObject) && !isset($trackObject["private"]))
            {
                $this->setTagImplicitTrack($tag, $date);
                //Se non è settato il campo private del track qualsiasi pressione di un bottone di selezione tag
                //genera la richiesta di impostare la privacy del track
            }
            else 
            {
                switch ($tag)
                {
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
                    case 'generic':
                    {
                        $this->result["state"] = SET_TAG_GENERIC_CHOICE;
                        break;
                    }
                    default:
                    {
                        $this->result["state"] = SET_TAG_CHOICE_INTERNAL_ERROR;
                        break;
                    }
                }
            }
            
        }
        else
        {
            $this->result["state"] = SET_TAG_CHOICE_INTERNAL_ERROR;
        }
    }
    
    public function setTagImplicitTrack($tag, $date)
    {
        $this->result["params"]["callback_data"] = array("public track " . $date, "private track " . $date);
        switch ($tag)
        {
            case 'danger':
            {
                $this->result["state"] = SET_TAG_DANGER_CHOICE_SET_TRACK_PRIVACY_REQUEST;
                break;
            }
            case 'poi':
            {
                $this->result["state"] = SET_TAG_POI_CHOICE_SET_TRACK_PRIVACY_REQUEST;
                break;
            }
            case 'generic':
            {
                $this->result["state"] = SET_TAG_GENERIC_CHOICE_SET_TRACK_PRIVACY_REQUEST;
                break;
            }
            default :
            {
                $this->result["state"] = SET_TAG_CHOICE_INTERNAL_ERROR;
                break;
            }
        }
    }
    
    public function confirmTrack()
    {
        $data = explode(" ", $this->callback["data"]);
        $date = $data[2] . " " . $data[3];
        $trackObject = DB::findTrackClosedAtDate($this->owner["chatid"], $date);
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
}
