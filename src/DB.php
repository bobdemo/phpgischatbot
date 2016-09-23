<?php
/**
 * 
 *   
 */
namespace GisAgentTB\TelegramBot;

use GisAgentTB\TelegramBot\Logger;
use GisAgentTB\TelegramBot\Exception\BotException;

/**
 * Class DB.
 */
class DB
{
    /**
     * PDO object 
     *
     * @var \PDO
     */
    static protected $pdo;

    /**  
     * Initialize
     *
     * @param array $credentials  Database connection details
     * @param TelegramAgent $telegram     TelegramAgent object to connect with this object
     *
     * @return \PDO PDO database object
     * @throws TelegramException
     */
    public static function initialize($dbName, $dbUser, $dbPass)
    {
        if (!isset($dbName) || !isset($dbUser) || !isset($dbPass) ) 
        {
            throw new BotException('PostgeSQL credentials not provided!', 0);
        }
        $dsn = 'pgsql:host=localhost' . ';dbname=' . $dbName;
        try 
        {
            $pdo = new \PDO($dsn, $dbUser, $dbPass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
            Logger::logInfo("DB: initialize");
        } 
        catch (\PDOException $e) 
        {
            Logger::logError($e->getMessage());
            //Credenziali db errate
        }
        self::$pdo = $pdo;
        return self::$pdo;
    }
 
    /**
     * Check if database connection has been created
     *
     * @return bool
     */
    public static function isDbConnected()
    {
        if (empty(self::$pdo)) 
        {
            return false;
        } 
        else 
        {
            return true;
        }
    }
    
    /**
     * Convert from unix timestamp to timestamp
     *
     * @param int $time Unix timestamp
     *
     * @return null|string Timestamp if a time has been passed, else null
     */
    protected static function getTimestamp($time = null)
    {
        if (is_null($time)) 
        {
            return date('Y-m-d H:i:s', time());
        }
        return date('Y-m-d H:i:s', $time);
    }

    /**
     *
     * @param text $query  the query to prepare
     *
     * @return Statement the prepared pdo statement
     */
    public static function prepareStatement ($query)
    {
        if(self::isDbConnected())
        {
           return self::$pdo->prepare($query);
        }
        return null;
    }

    /**
     * Return the current user data
     *
     * @param text $userId the user identifier
     * @param text $chatId the chat identifier
     *
     * @return null|array the owner data or null
     */
    public static function findOwner($userId, $chatId = null) //Se la chat non viene passata restituisce una lista
    {                                                         //di chat attive
        try
        {
            $query = "select * from owner where userid=:userId";
            if($chatId != null)
            {
                $query .= " and chatid=:chatId";
            }
            else
            {   
                $query .= " and expired is false order by last_access desc";
            }
            $statement = self::prepareStatement($query);
            $statement->bindParam(':userId',$userId,\PDO::PARAM_INT);
            if($chatId != null)
            {
                $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            }
            $result = null;
            if ($statement->execute() != null) 
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;
            if (isset($result) && count($result) > 0)
            {
                if($chatId != null)
                {
                    return $result[0];
                }
                else
                {
                    return $result;
                }
            }
            else
            {
                return null;
            }
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    /**
     * Insert a new user
     *
     * @param text $userId the user identifier
     * @param text $chatId the chat identifier
     * @param text $username the user username
     * @param text $firstName the chat firstname
     * @param text $lastName the user lastname
     * @param text $language the language to use in the chat
     *
     * @return null|array the owner data or null
     */
    public static function insertOwner($userId, $username, $firstName, $lastName, $chatId, $date, $language)
    {
        try
        {
            $query = "insert into owner ( userid, username, firstname, lastname, chatid, language, "
                    . "last_access, diff_access, expired) values (:userId, :username, "
                    . ":firstName, :lastName, :chatId, :language, :date1::date, '00:00:00'::interval, "
                    . "false) returning *";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':userId',$userId,\PDO::PARAM_INT);
            $statement->bindParam(':username',$username,\PDO::PARAM_STR);
            $statement->bindParam(':firstName',$firstName,\PDO::PARAM_STR);
            $statement->bindParam(':lastName',$lastName,\PDO::PARAM_STR);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $statement->bindParam(':language',$language,\PDO::PARAM_STR);
            $statement->bindParam(':date1',$date,\PDO::PARAM_STR);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;
            if (isset($result) && count($result) > 0 )
            {
                return $result[0];
            }
            
            return null;
            
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }

    /**
     * set the user last access performing a chek of the new last access date  
     * 
     * @param text $userId the user identifier
     * @param date $date the date of the last access
     *
     * @return null|integer the intervall betwen the last and its previous value or null   
     */
    public static function setOwnerLastAccess($userId,$chatId, $date)
    {
        try
        {
            $query = "update owner set last_access = :date1, diff_access = :date2 - last_access "
                    . "where userid=:userId";
            if($chatId !== null)
            {
                $query .= " and chatid=:chatId";
            }
            $query .= " and last_access < :date3 returning diff_access";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':userId',$userId,\PDO::PARAM_INT);
            if($chatId !== null)
            {
                $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            }
            $statement->bindParam(':date1',$date,\PDO::PARAM_STR);
            $statement->bindParam(':date2',$date,\PDO::PARAM_STR);
            $statement->bindParam(':date3',$date,\PDO::PARAM_STR);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;  
            if (isset($result) && count($result) > 0 )
            {
                return $result[0]["diff_access"];
            }
            else
            {
                return null;
            }
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function updateSession($session, $userId, $chatId)
    {
        try
        {
            $query = "update owner set session = :session where userid = :userId "
                    . "and chatid = :chatId returning session";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':session',$session,\PDO::PARAM_STR);
            $statement->bindParam(':userId',$userId,\PDO::PARAM_INT);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;  
            if (isset($result) && isset($result[0]))
            {
                return true;
            }
            
            return false;
            
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function emptySession($userId, $chatId)
    {
        try
        {
            $query = "update owner set session = null where userid = :userId "
                    . "and chatid = :chatId returning *";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':userId',$userId,\PDO::PARAM_INT);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;  
            if (isset($result) && isset($result[0]))
            {
                return true;
            }
            
            return false;
            
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    /**
     * set the langage to use in the chat by the user
     * 
     * @param text $userId the user identifier
     * @param text $chatId the user identifier
     * @param date $language the langage to use in the chat by the user
     *
     * @return null|text the langage to use in the chat by the user or null   
     */
    public static function getChatLanguage($chatId)
    {
        try
        {
            $query = "select language from owner where chatid=:chatId limit 1";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;
            if (isset($result) && isset($result[0]))
            {
                return $result[0]['language'];
            }
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function updateLanguage($chatId, $language )
    {
        try
        {
            $query = "update owner set language = :language where chatid = :chatId returning language";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $statement->bindParam(':language',$language,\PDO::PARAM_STR);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;
            if (isset($result) && count($result) > 0 )
            {
                return $result[0]['language'];
            }
            return null;
            
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
   
    /**
     * get the key to use in the mapper and media viewer, if the current key 
     * is expired (1 day) a new key is created.
     * The key should be returned only to the owner of the track or users 
     * partecipating to track creation.
     * 
     * @param text $trackId the track identifier
     * @param text $mode  management or simple viewing mode
     * 
     * @return null|text the Track key or null   
     */
    public static function getKey ($trackId, $mode)
    {
        try
        {
            $query = '';
            if($mode == 'management')
            {
                $query = "update track set management_key = (case management_key is null"
                    . " when true then extract(epoch from now())::text else management_key end ) where id=:trackId " 
                    . " returning management_key as key";
            }
            else
            {
                $query = "update track set visibility_key = (case visibility_key is null"
                    . " when true then extract(epoch from now())::text else visibility_key end)  where id=:trackId"
                    . " returning visibility_key as key";
            }
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $result = null;
            if ($statement->execute() != null)
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;
            if (isset($result) && count($result) > 0 )
            {
                return $result[0]["key"];
            }
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function isKeyValid($trackId, $mode, $key)
    {
        try
        {
            $query = '';
            if($mode == 'management')
            {
                $query = "select id from track where management_key = :key and id = :trackId";
            }
            else
            {
                $query = "select id from track where visibility_key = :key and id = :trackId";
            }
            
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $statement->bindParam(':key',$key,\PDO::PARAM_STR);
            $result = null;
            if ($statement->execute() != null)
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;
            if (isset($result) && count($result) > 0 )
            {
                return true;
            }
            return false;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    /**
     * funzione che restituisce i dati di un track dato il suo identificativo
     *  
     * @param trackId
     * 
     * @return null|array the track data  
     * 
     */
    public static function findTrackById ($trackId)
    {
        try
        {
            $query = "select * from track where id = :trackId";
	    $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $result = null;
            if ($statement->execute() != null)
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if (isset($result) && count($result) > 0 )
                {
                    return $result[0];
                }
            }    
            return null;
            
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    /**
     * search the last track created with the verify of opened or closed  
     *  
     * @param text $userId the track identifier
     * @param text $chatId the track identifier
     * @param text $openOnly the track identifier
     * @param text $date the date
     * 
     * @return null|array the Track key or null   
     */
    public static function findTrackOpenAtDate ($chatId, $date, $openOnly)
    {
        try
        {
            if($openOnly)
            {
                $query = "select * from track where chatid=:chatId"
                    . " and (stop is null or (start::date - '00:00:01.5'::interval, stop::date + "
                    . "'00:01:00'::interval) OVERLAPS ('" . $date . "', '" . $date . "'))"
                    . " order by start desc limit 1";
            } //Tragitti tutt'ora aperti o che erano aperti in quella data (tolleranza di un'ora dalla chiusura)
            else
            {
                $query = "select * from track where chatid=:chatId"
                    . " and ((stop is null or (start::date - '00:00:01.5'::interval, stop::date + "
                    . "'00:00:01.5'::interval) OVERLAPS ( '" . $date . "', '" . $date . "')) "
                    . "or validated=false ) order by start desc limit 1";
            } //Tragitti aperti in quella data e tragitti non validati (al più uno)
            $statement = self::prepareStatement($query);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $result = null;
            if ($statement->execute() != null)
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if (isset($result) && count($result) > 0 )
                {
                    return $result[0];
                }
            }
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function findTrackClosedAtDate($chatId, $date)
    {
        try
        {
            $query = "select * from track where chatid=:chatId and stop <= :date order by stop desc limit 1";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $statement->bindParam(':date',$date,\PDO::PARAM_STR);
            $result = null;
            if ($statement->execute() != null)
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if ( count($result) > 0 )
                {
                    return $result[0];
                }
            }
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    ////// rimuove un track point da mappa ( editing utente )
    //// verifica su path e previous!!!!!
    public static function removeTrackPoint($trackPointId, $key)
    {
        try
        {
            //// find next se esiste, per sua modifica previous e path --> delete current

        }
        catch (Exception $ex)
        {
            throw new \BotException($exc->getMessage(),0);
        }
        return false;
    }
    
    private static function createLine($trackId, $latitude, $longitude)
    {
        try
        {
            $index = 0;
            //// find previous
            $query = "select id, st_x(point) as longitude, st_y(point) as latitude "
                    . "from trackpoint a, (select max(id) from trackpoint where "
                    . "trackid = :trackId) b where b.max = a.id ";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $previous = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                if (isset($result) && count($result) > 0 )
                {
                    $path = Request::getPath([$result[0]["latitude"]+0,$result[0]["longitude"]+0,$latitude,$longitude]);
                }
            }
            return $path;      
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function findPreviousTrackPoint($trackId, $trackPointId)
    {
        //Attenione alle modifiche, viene utilizzata anche per capire a che trackpoint riferire il 
        //tag confrontando data messaggio con bottoni e data del trackpoint
        try
        {
            $query = "select a.*, st_x(point) as longitude, st_y(point) as latitude  from trackpoint a, 
            (select userid, min(:trackPointId - id) as min from trackpoint where 
            trackid=:trackId AND (:trackPointId - id ) > 0 group by(userid)) 
            b where (:trackPointId - a.id) = b.min and a.trackid=:trackId and a.userid=b.userid";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $statement->bindParam(':trackPointId',$trackPointId,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if (isset($result) && count($result) > 0 )
                {
                    return $result[0];
                }
	    }
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    //// insert trackpoint from bot   
    public static function insertTrackPoint($trackId, $userId, $chatId, $latitude, $longitude, $date)
    {
        try
        {
            $zone = null;
            $result = self::searchAreaByLocation($latitude, $longitude,0);
            if (isset($result) && count($result) > 0 )
            {
                $zone = $result[0]["id"];
            }
            $wktPoint = "POINT(" . $longitude . " " . $latitude . ")";
            $path = self::createLine($trackId, $latitude, $longitude);
            $query = "insert into trackpoint ( trackid, date, point, userid, chatid, zone, path, pathtype, webencoded ) values ( :trackId, :date, "
                    . "st_pointfromtext(:wkt1,4326), :userId, :chatId, :zone, ";
            if ( isset($path["path"]) )
            {
                 $query .=  " st_linefromtext(:wkt2,4326), :pathtype, googleencodeline( st_linefromtext(:wkt2,4326) )  ) returning * ";
            }
            else 
            {
                $query .=  " NULL, 'none', NULL  )  returning * ";
            }
            
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $statement->bindParam(':date',$date,\PDO::PARAM_STR);
            $statement->bindParam(':wkt1',$wktPoint,\PDO::PARAM_STR);
            $statement->bindParam(':userId',$userId,\PDO::PARAM_INT);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $statement->bindParam(':zone',$zone,\PDO::PARAM_INT);
            if ( isset($path) )
            {
                $statement->bindParam(':wkt2',$path["path"],\PDO::PARAM_STR);
                $statement->bindParam(':pathtype',$path["type"],\PDO::PARAM_STR);
            }
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if (isset($result) && count($result) > 0 )
                {   
                    return $result[0];
                }
            }
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    //// insert  track point from edit map retrive  $chatId  $date
    public static function insertTrackPointFromMap($trackId, $userId, $latitude, $longitude, $tag, $date, $path,  $key)
    {
        return false;
    }
    
    //// insert  track point from edit map retrive  $chatId  $date
    public static function updateTrackPointFromMap($id, $trackId, $userId, $latitude, $longitude, $date, $path, $tag,  $key)
    {
        return false;
    }
    
    //// insert  track point from edit map retrive  $chatId  $date
    public static function removeTrackPointFromMap($id, $trackId, $userId, $key)
    {
        return false;
    }
    
    public static function insertRoadbookElement($trackId, $userId, $text, $name, $type, $date)
    {
        try
        {
            $query = "insert into roadbook ( trackid, userid, text, name, type, date) values"
                    . " (:trackId, :userId, :text, :name, :type, :date) returning trackid";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $statement->bindParam(':userId',$userId,\PDO::PARAM_INT);
            $statement->bindParam(':text',$text,\PDO::PARAM_STR);
            $statement->bindParam(':name',$name,\PDO::PARAM_STR);
            $statement->bindParam(':type',$type,\PDO::PARAM_STR);
            $statement->bindParam(':date',$date,\PDO::PARAM_STR);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if (isset($result) && count($result) > 0 )
                {
                    return true;
                }
            }
            return false;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    //// insert  track point from edit map retrive  $chatId  $date, solo per testo
    public static function insertRoadbookElementFromMap($trackId, $userId, $text, $name, $type, $date, $key)
    {
        return false;
    }
    
    //// insert  track point from edit map retrive  $chatId  $date
    public static function updateRoadbookElement($id, $field, $value)
    {
        try
        {
            $query = '';
            if($field == 'text')
            {
                $query = "update roadbook set text=:value where id=:id returning *";
            }
            else
            {
                $query = "update roadbook set date=:value where id=:id returning *";
            }
            $statement = self::prepareStatement($query);
            $statement->bindParam(':value',$value,\PDO::PARAM_STR);
            $statement->bindParam(':id',$id,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
	    {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $rowCount = $statement->rowCount();
                $statement = null;
                if(isset($result) && count($result) > 0 && $rowCount>0)
                {
                    return $result[0];
                }
            }    
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    //// insert  track point from edit map retrive  $chatId  $date
    public static function removeRoadbookElementFromMap($id, $trackId, $userId, $key)
    {
        return false;
    }
    
    public static function initTrack ($userId, $chatId)
    {
        try 
        {
            /// i track in una chat possono essere + di 1
            $query = "insert into track ( userid, chatid) values (:userId, :chatId) returning *";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $statement->bindParam(':userId',$userId,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
            $statement = null;  
            if (isset($result) && count($result) > 0 )
            {
               return $result[0];
            }
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function setTrackName($trackId, $name)
    {
        try
        {
            $query = "update track set name = :name where id = :trackId returning id";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $statement->bindParam(':name',$name,\PDO::PARAM_STR);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if (isset($result) && count($result) > 0 )
                {
                    return true;
                }
            }    
            return false; 
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function setTrackPrivate($trackId, $private)
    {
        try
        {
            $query = "update track set private = :private where id = :trackId returning id";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $statement->bindParam(':private',$private,\PDO::PARAM_BOOL);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if (isset($result) && count($result) > 0 )
                {
                    return true;
                }
            }    
            return false;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function setTrackPointTag($date, $tag, $chatId)  //Il collegamento trackpoint tag avviene
    {                                                     //tramite accostamento temporale in modo che se la
        try                                               //richiesta di tag arriva in ritardo il trackpoint
        {                                                 //taggato è sempre quello del messaggio più vicino
            $query = "update trackpoint set tag = :tag where id ="
                    . "(select id from trackpoint where date<=:date and chatid =:chatId "
                    . "order by id desc limit 1) returning id";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':tag',$tag,\PDO::PARAM_STR);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_STR);
            $statement->bindParam(':date',$date,\PDO::PARAM_STR);
            //La data è quella di invio del trackpoint associato possono esserci date uguali in caso di invio
            //di posizioni offline, si binda al trackpoint più recente
            $result = null;
            if($statement->execute())
            {                                                    
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if (isset($result) && count($result) > 0 )
                {
                    return true;
                }
            }    
            return false;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
   
    public static function closeTrack($trackId)
    {
       	$date = date ('Y-m-d H:i:s');
       	$query = "update track set stop=:stop where id=:trackId returning *"; //and stop is NULL ";
       	$statement = self::prepareStatement($query);
       	$statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
        $statement->bindParam(':stop',$date,\PDO::PARAM_STR);
        $result = null;
        if($statement->execute())
        {
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $statement = null;
            if (isset($result) && count($result) > 0 )
            {
                return $result[0];
            }
        }    
        return false;
    }
    
    public static function trackHasPoints($trackId)
    {
        try
        {
            //La funzione e'  chiamata quando si conferma il tracciato, se il tragitto ha dei punti interni restituisce true
            $query = "select * from trackpoint where trackid = :trackId";
            $statement = self::prepareStatement($query);
       	    $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if (isset($result) && count($result) > 0 )
                {
                    return true;
                }
            }    
            return false;
        }
        catch(PDOException $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }

    public static function deleteTrack($trackId)
    {    
        try
        {
            $query = "delete from track where id = :trackId ";
            $statement = self::prepareStatement($query);
       	    $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if (isset($result) && count($result) > 0 )
                {
                    return true;
                }
            }
            return false;
        }
        catch(PDOException $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function validateTrack($trackId, $private, $name)
    { //Sistema il nome e la privacy se non sono state impostate
        try
        {
            $query = "update track set validated = true, "
                    . "private = :private, "
                    . "name = :name "
                    . "where id = :trackId returning id";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':private',$private,\PDO::PARAM_BOOL);
            $statement->bindParam(':name',$name,\PDO::PARAM_STR);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;  
                if (isset($result) && count($result) > 0 )
                {
                    return true;
                }
            }
            return false;
        }
        catch(PDOException $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function validatePreviousTrack($trackId, $chatId)
    {
        try
        {
            $query = "update track set validated = true, "
                    . "private = (case private is null when true then true else private end), "
                    . "name = (case name is null when true then 'No name' else name end) "
                    . "where chatid = :chatId and id < :trackId and validated=false returning id";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':chatId',$chatId,\PDO::PARAM_INT);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            if ($statement->execute() != null)
            {
               return true;
            }
            return false;
        }
        catch(PDOException $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    //// restituisce i punti e la linea codificata per il web 
    public static function readTrackPointsForMap($trackId)
    {
        try
        {
            /// controllo permessi su track e lettura su trackpoint
            $query = " select a.id, a.userid, a.trackid, case when c.username is not null then c.username when c.firstname is not null then c.firstname "
                    . " when c.lastname is not null then c.lastname end as user, b.name as track, st_y(a.point) as latitude, "
                    . " st_x(a.point) as longitude, a.tag, a.date, a.webencoded as path "
                    . " from trackpoint a, track b, owner c where a.trackid = b.id and b.id = :trackId "
                    . " and c.userid = a.userid and a.chatid = c.chatid" ;
            
            $query .= " order by a.id ";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                return json_encode($result);
            }
        }
        catch (Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }

        return null;
    }
    
    public static function fetchElements($trackId, $type)
    {
        try
        {
            //Da aggiungere filtro sulla posizione (con range massimo) e controllo sulla validazione dei track
            //Cioè se il track non è chiuso e validato i contenuti non sono disponibili
            $query = "select distinct a.id, a.text, a.name, a.type, a.trackid, a.date, a.userid,"
                    . "case when c.username is not null then c.username when c.firstname is not null "
                    . "then c.firstname when c.lastname is not null then c.lastname else null end as user "
                    . " from roadbook a, owner c where c.userid = a.userid and trackid=:trackId";
            if ( isset($type) && $type != null )
            {
                $query .= " and type=:type";
            }
            $statement = self::prepareStatement($query);
            $statement->bindParam(':trackId',$trackId,\PDO::PARAM_INT);
            if ( isset($type) && $type != null )
            {    
                $statement->bindParam(':type',$type,\PDO::PARAM_STR);
            }
            $result = null;
            if($statement->execute())
            {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if ( isset($result) && count($result) > 0 )
                {
                    return $result;
                }
            }
            return null;
        }
        catch(PDOException $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function deleteFile($id)
    {
        try
        {
            $query = "delete from roadbook where id=:id returning *";
            $statement = self::prepareStatement($query);
            $statement->bindParam(':id',$id,\PDO::PARAM_INT);
            $result = null;
            if($statement->execute())
	    {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if(isset($result) && count($result) == 0)
                {
                    return true;
                }
            }    
            return false;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function searchAreaByName($name)
    {
        //Attenione alle modifiche, viene utilizzata anche per capire a che trackpoint riferire il 
        //tag confrontando data messaggio con bottoni e data del trackpoint
        try
        {
            $query = "select id, name from pathway.zone where name ilike '%" . $name . "%'";
            $statement = self::prepareStatement($query);
            $result = null;
            if($statement->execute())
	    {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if(isset($result))
                {
                    return $result;
                }
            }    
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    //// radius in Km
    public static function searchAreaByLocation($lat, $lon, $radius = 50)
    {
        try
        {
            $query = "select id, name from pathway.zone where st_distance "
                    . "( zone.geom , ST_GeographyFromText('SRID=4326;POINT(".$lon." ".$lat.")') )"
                    . " <= ".$radius." * 1000";
            $statement = self::prepareStatement($query);
            $result = null;
            if($statement->execute())
	    {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if(isset($result))
                {
                    return $result;
                }
            }    
            return null;
            
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function searchTripsByLocation($lat, $lon, $chatIdList, $radius = 50)
    {
        try
        {
            $chatSet = implode(", ", $chatIdList);
            $query = "select distinct a.* from track a, trackpoint b where a.validated = true "
                    . " and ( a.private = false or a.chatid in (" . $chatSet . ") ) "
                    . " and a.id = b.trackid and st_distance ( b.point , ST_GeographyFromText('SRID=4326;POINT(".$lon." ".$lat.")') )"
                    . " < ".$radius." * 1000 ";
            $statement = self::prepareStatement($query);
            $result = null;
            if($statement->execute())
	    {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if(isset($result))
                {
                    return $result;
                }
            }
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    public static function searchTripsByWord($word, $chatIdList)
    {
        try
        {
            $chatSet = implode(", ", $chatIdList);
            $query = "select public.owner.chatid, id, name from public.track join public.owner on track.chatid=owner.chatid "
                    . "where validated=true and (private=false or owner.chatid in (" . $chatSet . ")) and (name ilike '%" . $word . "%' or "
                    . "username ilike '%" . $word . "%' or firstname ilike '%" . $word . "%' or "
                    . "lastname ilike '%" . $word . "%')";  //Da aggiungere la order by attività utente
            $statement = self::prepareStatement($query);
            $result = null;
            if($statement->execute())
	    {
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement = null;
                if(isset($result))
                {
                    return $result;
                }
            }    
            return null;
        }
        catch(\Exception $exc)
        {
            throw new \BotException($exc->getMessage(),0);
        }
    }
    
    
    public static function directionsForZone($lat1, $lng1, $lat2, $lng2)
    {
        Logger::logInfo($lat1);
        if ( isset($lng1,$lat1,$lat2,$lng2) )
        {
            try
            {
                //select distinct * from  _gcb_directions (  st_geomfromewkt( 'srid=4326; POINT(8.89486014957786 39.098766189688)') , st_geomfromewkt( 'srid=4326; POINT(8.84690765546061 39.0520890133367)' ) ) 
                // as ( ord integer, arcid  integer, arcfrom integer, arcto integer, sentieri text,arcs text,nodes text,path text,msg text,cost numeric ) order by ord
/// ritorna um record con campi   ( ord integer, arcid  integer, arcfrom integer, arcto integer, sentieri text,arcs text,nodes text,path text,msg text,cost numeric)
                $query = "select distinct * from _gcb_directions2(st_geomfromewkt('SRID=4326;POINT(" . $lng1 . " " . $lat1 . ")'),"
                        . " st_geomfromewkt('SRID=4326;POINT(" . $lng2 . " " . $lat2 . ")') )"
                        . " as ( ord integer, arcid  integer, arcfrom integer, arcto integer, sentieri text, arcs text, nodes text, path text, msg text, cost numeric ) order by ord";
                $result = null;
                $statement = self::prepareStatement($query);
                if($statement->execute())
                {
                    Logger::logInfo($query);
                    $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                    $statement = null;
                    if(isset($result) && count($result) > 0)
                    {
                        return $result;
                    }
                }
                return null;
            }
            catch(\Exception $exc)
            {
                throw new \BotException($exc->getMessage(),0);
            }
        }
    }
}
