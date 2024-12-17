<?php

namespace App\Utilities;

use Twilio\Rest\Client;
use MtnSmsCloud\MTNSMSApi;

class TwilioUtils
{
    public static  function sendSMS(string $client_phone, string $message){
        //$sid = "AC15b74e366c933e541d689af3add2ccb9"; //Account SID from www.twilio.com/console
        //$token = "1c384345d7859979b610bd4b63504b9f"; //Auth Token from www.twilio.com/console
        //$sender = 'MG396e469644343efa4233d4f5d5a1d26f'; //Messaging Service SID

        //if(strpos($phone, '+') !== false){
        //    $phone = str_replace("+", "", $phone);
        //}

        //$client = new Client($sid, $token);
        //$message = $client->messages->create(
        //    $phone, // Text this number
        //    [
        //        'from' => $sender, // From a valid Twilio number
        //        'body' => $messageText
        //    ]
        //);

        if(strpos($client_phone, '+') !== false){
            $client_phone = str_replace("+", "", $client_phone);
        }

        if (substr( $client_phone, 0, 3 ) === "225") {

            $sender_id = 'ADJEMIN';
            $token = "YlSf8vDE8LcYGs1oLqxqRkGDRSyuzpiJGGR";
            $msa = new MTNSMSApi($sender_id, $token);

            /**
             * Send a new Campaign
             *
             * @var array $recipients {Ex: ["225xxxxxxxx", "225xxxxxxxx"]}
             * @var string $message
             */
            $recipients = [$client_phone];

            $result = $msa->newCampaign($recipients, $message);

            $result = (array)json_decode($result,true);

            $smsCount = array_key_exists('smsCount', $result)?$result['smsCount'] : 0;


            if($smsCount>=1){

                return true;
            }else{
                return false;
            }


        }else{

            $BulkSmsSender = new BulkSmsSender();
            $result = $BulkSmsSender->sendMessage([$client_phone], $message) ;

            return $result;
        }
    }

}
