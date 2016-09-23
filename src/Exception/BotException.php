<?php

namespace GisAgentTB\TelegramBot\Exception;

use Exception;
use GisAgentTB\TelegramBot\Logger;

class BotException extends Exception
{
    public function __construct($message, $code)
    {
        parent::__construct($message, $code, null);
        
        ////scrittura Log
        Logger::logError($message);
    }
}
