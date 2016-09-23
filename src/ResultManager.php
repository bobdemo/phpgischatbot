<?php

namespace GisAgentTB\TelegramBot;

class ResultManager
{
    private $response;
    private $pieces;
    private $buttonValues;
    private $buttonValuesType;
    private $inlineResults;
    private $referredObjectName;
    private $summary;
    private $log;
    
    public function __construct ($owner, $state, $params) //params è un array di callback data o di coppie chiave
    {                                                       //valore per le inline query
        $data = Interpreter::getResponseData($state, $owner["language"]);
        Logger::logInfo(json_encode($data));
        if($owner["diff_access"] <= date("H:i:s", strtotime('00:00:02')))
        {
            SessionManager::updateSession($owner, $data["action"]);
        }
        else
        {
            $this->pieces = $data["pieces"];
            $this->log = $data["log"];
            if($owner["session"] != "")
            {
                $summary = SessionManager::manageSession($owner);
                Logger::logInfo(json_encode($summary));
                $this->summary["text"] = Interpreter::getSummaryData($summary, $owner["language"]);
            }
            if($params != []) //params può essere un array di callback data, di url oppure di risultati di inline query
            {
                if(isset($params["callback_data"]))
                {
                    $this->buttonValues = $params["callback_data"];
                    $this->buttonValuesType = "callback_data";
                }
                else if(isset($params["url"]))
                {
                    $this->buttonValues = $params["url"];
                    $this->buttonValuesType = "url";
                }
                else if(isset($params["results"]))          //I risultati hanno dei bottoni con campo url
                {
                    $this->buttonValuesType = "url";
                    $this->inlineResults = $params["results"];
                }

                if(isset($params["name"]))
                {
                    if(strcmp ($params["name"], 'No name') == 0 )
                    {
                        $this->referredObjectName = $this->pieces["default_name"];
                    }
                    else
                    {
                        $this->referredObjectName = $params["name"];
                    }
                }
            }
        }
    }
      
    public function getResponse()
    {
        if(isset($this->pieces))
        {
            if($this->pieces["inline"])
            {
                $this->addInlineResults();
            }
            else
            {
                if(isset($this->pieces["text"]))
                {
                    $this->addText();
                }
                if(isset($this->pieces["button_texts"]))
                {
                    $this->addKeyboard();
                }
                if($this->pieces["reply"])
                {
                    $this->addForceReply();
                }
            }
            return $this->response;
        }
        else
        {
            return null;
        }
    }
    
    public function getSummaryResponse()
    {
        if(isset($this->summary))
        {
            return $this->summary;
        }
        else
        {
            return null;
        }
    }
    
    private function addInlineResults()
    {
        if(isset($this->inlineResults) && count($this->inlineResults)!=0)
        {
            foreach($this->inlineResults as $result)
            {
                Logger::logInfo("oh " . json_encode($this->pieces["button_texts"]) . json_encode($result));
                $key_value = array_combine($this->pieces["button_texts"], array($result["url"])); 
                //Accoppia i due array formando un array associativo, la url di un result è sempre solo una
                $inlineKeyboard = $this->makeKeyboard($key_value);
                
                $this->response["results"][] = 
                [
                    "type" => $this->pieces["inline"]["inline_result_type"], "id" => $result["id"] . "",
                    "title" => $result["name"], 
                    "input_message_content" => ["message_text" => $this->pieces["text"] . $result["name"]],
                    "reply_markup" => ["inline_keyboard" => $inlineKeyboard]
                ];
            }
        }
        else
        {
            $this->response["results"] = 
            [ 
                [
                    "type" => $this->pieces["inline"]["inline_result_type"], "id" => '0', "title" => $this->pieces["text"], 
                    "input_message_content" => ["message_text" => $this->pieces["text"]]
                ]
            ];
        }
    }

    private function addText()
    {
        if(isset($this->referredObjectName))
        {
            $this->pieces["text"] .= $this->referredObjectName;
        }
        $this->response["text"] = $this->pieces["text"];
    }

    private function addKeyboard()
    {
        $key_value = array_combine($this->pieces["button_texts"], $this->buttonValues); 
        //Accoppia i due array formando un array associativo
        $keyboard = $this->makeKeyboard($key_value);
        if(isset($keyboard))
        {
            $this->response["reply_markup"] = 
            [
                'inline_keyboard' => $keyboard
            ];
        }
    }
    
    private function makeKeyboard($key_value)         
    {       
        if($key_value != false)     //La fusione non va a buon fine se gli array sono di dimensione diversa
        {
            $keyboard = [];
            $i = -1;
            $j = 0;
            
            foreach ($key_value as $buttonText => $data) 
            {
                if($j == 0)
                {
                    $i++;
                    $keyboard[$i] = array (array("text"=>$buttonText,$this->buttonValuesType=>$data));
                }
                else 
                {
                    $keyboard[$i][] = array("text"=>$buttonText,$this->buttonValuesType=>$data);
                }
                $j = ($j+1) % 2;
            }
            
            return $keyboard;
        }
    }

    private function addForceReply()
    {
        $this->response["reply_markup"] =
        [
            'force_reply' => true,
            'selective' => true
        ];
    }
    
    public function buildLog($origin)
    {
        if($this->log["type"] == 'Error')
        {
            Logger::logError($origin . " - " . $this->log["message"]);
        }
        else if($this->log["type"] == 'Warning')
        {
            Logger::logWarning($origin . " - " . $this->log["message"]);
        }
        else if($this->log["type"] == 'Info')
        {
            Logger::logInfo($origin . " - " . $this->log["message"]);
        }
    }
}
