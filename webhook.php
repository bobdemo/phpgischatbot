<?php


require __DIR__ . '/vendor/autoload.php';
require 'settings.php';

use GisAgentTB\TelegramBot\Exception\BotException;
use GisAgentTB\TelegramBot\Logger;
use GisAgentTB\TelegramBot\Telegram;

/// il codice seguente andrebbe su di una classe Main  per la gestione qui si lascia il set delle costanti
/// ci deve poi essere il controllo sull'accesso senza parametri validi e su chi (solo i server di telegram) 
/// andrebbe anche aggiunto la gestione Exception

// read incoming info and grab the chatID
 

$content = file_get_contents("php://input");
 
if(isset($content))
{
    try
    {
        Logger::logError('webhook.php: START ');
        // Create Telegram API object
        $telegram = new Telegram($BOT_TOKEN, $BOT_NAME);

        $telegram->initDB($DB_NAME,$DB_USER,$DB_PASSWORD);

        $telegram->handle($content);

        Logger::logError('webhook.php: END ');
    }
    catch(BotException $exc)
    {
        Logger::logError($exc->getMessage());
        //Se il codice è 1 si deve inviare un messaggio di errore su chat
    }
    catch(\Exception $exc)
    {
        Logger::logError($exc->getMessage());
    }
}
