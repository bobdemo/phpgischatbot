<?php

namespace GisAgentTB\TelegramBot;

class Logger 
{
    public static function writeLog ($message)
    {
        $myFile = "/tmp/log.txt";
        $fh = fopen($myFile, 'a') or die("can't open file");
        
        $logMessage = date("d") . "/" . date("m") . "/" . date("Y") . " " . date("H") . ":" . date("i") . ":" . date("s");
        
        fwrite($fh, $logMessage . " " . $message . "\n\n" );
        fclose($fh);
    }
    
    public static function checkJSON($content)
    {
            self::writeLog("Content: " . $content);
    }
    
    public static function logError($error)
    {
        self::writeLog("Error " . $error);
    }
    
    public static function logInfo($info)
    {
        self::writeLog("Info: " . $info);
    }
     
    public static function logWarning($warning)
    {
        self::writeLog("Warning: " . $warning);
    }
}
