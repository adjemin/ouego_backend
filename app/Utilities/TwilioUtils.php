<?php

namespace App\Utilities;

use Twilio\Rest\Client;

class TwilioUtils
{
    public static  function sendSMS(string $phone, string $messageText){
        $sid = "AC15b74e366c933e541d689af3add2ccb9"; //Account SID from www.twilio.com/console
        $token = "1c384345d7859979b610bd4b63504b9f"; //Auth Token from www.twilio.com/console
        $sender = 'MG396e469644343efa4233d4f5d5a1d26f'; //Messaging Service SID

        if(strpos($phone, '+') !== false){
            $phone = str_replace("+", "", $phone);
        }

        $client = new Client($sid, $token);
        $message = $client->messages->create(
            $phone, // Text this number
            [
                'from' => $sender, // From a valid Twilio number
                'body' => $messageText
            ]
        );
    }

}
