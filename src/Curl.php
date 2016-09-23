<?php
/*
		$ch = curl_init("https://api.telegram.org/bot".$botToken."/".$action);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HEADER => false,
			CURLOPT_HTTPHEADER => array(
				'Host: api.telegram.org',
				'Content-Type: multipart/form-data'
			),
			CURLOPT_POSTFIELDS => array(
				'key' => $value,
				....
			),
			CURLOPT_TIMEOUT => 0,
			CURLOPT_CONNECTTIMEOUT => 6000,
			CURLOPT_SSL_VERIFYPEER => false
		));
		curl_exec($ch);
		curl_close($ch);

*/
namespace GisAgentTB\TelegramBot;

use GisAgentTB\TelegramBot\Exception\BotException;
use Exception;

class Curl
{
    /** @var resource cURL handle */
    private $ch;

    /** @var mixed The response */
    private $response = false;

    /**
     * @param string $url
     * @param array  $options
     */
    public function __construct($url, $params = array())
    {
        $this->ch = curl_init($url);

        $options = array(CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true);
        //$options = array_merge($options, array(CURLOPT_RETURNTRANSFER => true));

        foreach ($options as $key => $val) {
            curl_setopt($this->ch, $key, $val);
        }
    }

    /**
     * Close the cURL handle
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->ch)) {
            curl_close($this->ch);
        }
    }

    /**
     * Get the response
     * @return string
     * @throws \RuntimeException On cURL error
     */
    public function getResponse()
    {
        $response = curl_exec($this->ch);
        $error    = curl_error($this->ch);
        $errno    = curl_errno($this->ch);
        if ( $errno !==  0 ) {
            throw new BotException("Curl class:" . $error . " - " . $errno, 0);
        }
        return $this->response = $response;
    }

    /**
     * Let echo out the response
     * @return string
     */
    public function __toString()
    {
        return $this->getResponse();
    }
}