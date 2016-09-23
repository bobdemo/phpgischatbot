<?php
namespace GisAgentTB\TelegramBot;

use GisAgentTB\TelegramBot\Entities\Message;
use GisAgentTB\TelegramBot\Entities\Command;
use GisAgentTB\TelegramBot\Entities\InlineQuery;
use GisAgentTB\TelegramBot\Entities\ChosenInlineResult;
use GisAgentTB\TelegramBot\Entities\CallbackQuery;
use GisAgentTB\TelegramBot\Exception\BotException;
 
class Telegram 
{ 
     /**
     * Telegram Bot Token
     *
     * @var string
     */
    protected $botToken = '';

    /**
     * Telegram Bot name
     *
     * @var string
     */
    protected $botName = '';
    
    /**
     * PDO object
     *
     * @var PDO 
     */

    private $pdo;

    private $offline;
    
    public function __construct($botToken, $botName)
    {
        $this->botToken = $botToken;
        $this->botName = $botName;
        if(Request::initialize($this))      //Se la inizializzazione fallisce viene lanciata un'eccezione
        {
            Logger::logInfo('Telegram initialized');
        }
        else
        {
            throw new BotException("Inzializzazione telegram fallita",0);
        }
    }
   
    public function getFileUrl()
    {
        return 'https://api.telegram.org/file/bot'.$this->botToken.'/';
    }


 
    public function getApiUrl()
    {
        return 'https://api.telegram.org/bot'.$this->botToken.'/';
    }
    
    public function initDB($dbName,$dbUser,$dbPass)
    {
         $this->pdo = DB::initialize($dbName,$dbUser,$dbPass);
    } 
    
    public function handle($content)
    {
        /// verificare: inline_query, message, chosen_inline_result, callback_query, edited_message
        //  Controlla il formato del json e stampa l'output sul file di log
        
        Logger::checkJSON($content);
        $update = json_decode($content, true);
        $result = [];
        
        $entity = $this->constructEntity($update); //Messaggio, comando, inline query, chosen inline result, force reply
        
        if (isset($entity))
        {
            $result = $entity->manage();
            Logger::logInfo("Result:" . json_encode($result));

            $owner = $entity->getOwner();
            $queryId = null;
            
            if($entity instanceof InlineQuery)
            {
                $queryId = $entity->getQueryId();
            }

            $resultManager = new ResultManager($owner, $result['state'], $result['params']);
            $resultManager->buildLog($result['origin']);
            $response = $resultManager->getResponse();
            $summaryResponse = $resultManager->getSummaryResponse();
            
            if($response != null)
            {
                if($queryId != null)
                {
                    Request::sendQueryResponse($queryId, $response['results']);
                }
                else if($owner != null)
                {
                    Request::sendMessage($owner['chatid'], $response);
                }
            }
            if($summaryResponse != null)
            {
                Request::sendMessage($owner['chatid'], $summaryResponse);
            }
        }
    }
    
    public function constructEntity($update)
    {
        $entity = null;
        if(isset($update["message"]) && isset($update["message"]['from'] ))
        {
            if(!isset($update["message"]["entities"]))
            {
                //$testo = $update["message"]["text"];
                $entity = new Message($update);  
            }
            else if(isset($update["message"]["entities"]))
            {    
                foreach( $update["message"]["entities"] as $entity ) 
                {   
                    if ( isset($entity["type"]) &&
                         strcmp ($entity["type"], 'bot_command') == 0)
                    {
                        $entity = new Command($update);
                    }
                }
            }
        }
        else if (isset($update["inline_query"]))
        {
            $entity = new InlineQuery($update);
        }
        else if (isset($update["chosen_inline_result"]))
        {
            $entity = new ChosenInlineResult($update);
        }
        else if (isset($update["callback_query"]))
        {
            $entity = new CallbackQuery($update);
        }
        return $entity;
    }
}
