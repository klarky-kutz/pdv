<?php

namespace Sms\Gateway;

class Twilio implements GatewayInterface
{
    protected $defaultConfig = array(
        'route'    => 4,
        'auth_key'    => '',
        'sender_id'   => '',
        'country_code'   => '',
        'url' => 'http://api.twilio.com/api/v2/',
    );

    protected $route;
    protected $authKey;
    protected $senderID;
    protected $country;
    protected $url;

	public function __construct ($config = array()) {
        extract(mergeArray($this->defaultConfig, $config));
        $this->route = $route;
        $this->authKey = $auth_key;
        $this->senderID = $sender_id;
        $this->country = $country_code;
        $this->url =  $url;
	}

    public function send($to, $message=null) 
    {
        if (is_array($to) && !empty($to)) {
            $sms = $to;
        } else {
            $sms = array(
                array(
                    'message' => $message,
                    'to' => array(
                        $to,
                    ),
                ),
            );
        }

        $postData = array(
            'country' => $this->country,
            'sender' => $this->senderID,
            'route' => $this->route,
            'mobiles' => $to,
            'authKey' => $this->authKey,
            'sms' => json_encode($sms),
        );

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->url."sendsms?campaign=&response=&afterminutes=&schtime=&unicode=&flash=&message=&encrypt=&authkey=&mobiles=&route=&sender=&country=91",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => '{ "sender": "SOCKET", "route": "'.$postData['route'].'", "country": "'.$postData['country'].'", "sms": '.$postData['sms'].'}',
          CURLOPT_SSL_VERIFYHOST => 0,
          CURLOPT_SSL_VERIFYPEER => 0,
          CURLOPT_HTTPHEADER => array(
            "authkey: $this->authKey",
            "content-type: application/json"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
          return "cURL Error #:" . $err;
        } else {
          return $response;
        }

	}

    public function deliveryStatus($response_id)
    {
        //...

    }

    public function getBalance()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://control.twilio.com/api/balance.php?type=".$this->route."&authkey=".$this->authKey,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_SSL_VERIFYHOST => 0,
          CURLOPT_SSL_VERIFYPEER => 0,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
          return "cURL Error #:" . $err;
        } else {
          return $response;
        }
    }
}