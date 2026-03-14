<?php

namespace App\Utilities;

use Exception;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;

class FirebaseMessagingUtils{

    public static function sendNotification($title, $body, $type, $customerNotification, $firebaseId) {

        try {

            $serviceAccount = [
                'type'                        => env('FIREBASE_TYPE', 'service_account'),
                'project_id'                  => env('FIREBASE_PROJECT_ID'),
                'private_key_id'              => env('FIREBASE_PRIVATE_KEY_ID'),
                'private_key'                 => str_replace('\\n', "\n", env('FIREBASE_PRIVATE_KEY')),
                'client_email'                => env('FIREBASE_CLIENT_EMAIL'),
                'client_id'                   => env('FIREBASE_CLIENT_ID'),
                'auth_uri'                    => env('FIREBASE_AUTH_URI'),
                'token_uri'                   => env('FIREBASE_TOKEN_URI'),
                'auth_provider_x509_cert_url' => env('FIREBASE_AUTH_PROVIDER_CERT_URL'),
                'client_x509_cert_url'        => env('FIREBASE_CLIENT_CERT_URL'),
                'universe_domain'             => env('FIREBASE_UNIVERSE_DOMAIN', 'googleapis.com'),
            ];

            $factory = (new Factory)
             ->withServiceAccount($serviceAccount);

             $messaging = $factory->createMessaging();
            /** @var  $messaging */
            //$messaging = Firebase::project('app')->messaging();

            $message = CloudMessage::withTarget('token', $firebaseId)
                ->withNotification(Notification::fromArray([
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'notification_sound',
                    'badge' => '1',
                    'type' => "".$customerNotification["type"],
                    'id' => "".$customerNotification["id"],
                ]))
                ->withHighestPossiblePriority()
                ->withData(array(
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'id' => $customerNotification["id"],
                    'status' => 'done',
                    'notification_type' => "".$customerNotification["type"],
                    'notification_id' => "".$customerNotification["id"],
                    'meta_data_id' => "".$customerNotification["data_id"],
                    //'notification' => json_encode($customerNotification),
                    "title" => $title,
                    "body" => $body,
                ));

             $messaging->send($message);

             return true;

        }catch (Exception $e){
            // En cas d'erreur, logger l'exception et retourner une réponse d'erreur
            return  false;
        }
    }

    public static function sendData($metadata, $id) {

        try {
            /** @var  $messaging */
            $messaging = Firebase::project('app')->messaging();

            $message = CloudMessage::withTarget('token', $id)
                ->withHighestPossiblePriority()
                ->withData(array(
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'id' => '1',
                    'status' => 'done',
                    'metadata' => json_encode($metadata)
                ));

            $messaging->send($message);

            return true;

        }catch (Exception $e){
            // En cas d'erreur, logger l'exception et retourner une réponse d'erreur
            return  false;
        }

    }


}
/*class FirebaseMessagingUtils{

    const FCM_TOKEN = "AAAABdbzFcw:APA91bEfNvdQV4TZSS2D9Xw8dpBg0W2qPMNMWHA34bT-DUe6M_swGdLlJPiZp10SKxAWn6x7n03v3WREc3QlGqZ1cCo0NGDb_OpbrY9rSedd74dU3OkqdJDgN6zVj639W8AgSuvQG9Eq";

    public static function sendNotification($title, $body, $type, $customerNotification, $firebaseId) {

        $url = 'https://fcm.googleapis.com/fcm/send';

        $fields = array (
            'to' => $firebaseId,
            'notification' => array (
                "title" => $title,
                "body" => $body,
                "type" => $type,
                "sound"=> "default",
                "time_to_live" => 2419200

            ),
            'priority' => 'high',
            'data' => array(
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'id' => $customerNotification["id"],
                'status' => 'done',
                'notification_type' => "".$customerNotification["type"],
                'notification_id' => "".$customerNotification["id"],
                'meta_data_id' => "".$customerNotification["data_id"],
                'notification' => json_encode($customerNotification),
                "title" => $title,
                "body" => $body,
            )
        );

        $fields = json_encode ($fields);

        $headers = array (
            'Authorization: key=' .self::FCM_TOKEN,
            'Content-Type: application/json'
        );

        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POST, true );
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );

        $result = curl_exec ( $ch );
        curl_close ( $ch );

        return $result;
    }

    public static function sendData($metadata, $id) {

        $url = 'https://fcm.googleapis.com/fcm/send';

        $fields = array (
            'to' => $id,
            'priority' => 'high',
            'data' => array(
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'id' => '1',
                'status' => 'done',
                'metadata' => json_encode($metadata)
            )
        );
        $fields = json_encode ($fields);

        $headers = array (
            'Authorization: key=' .self::FCM_TOKEN,
            'Content-Type: application/json'
        );

        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POST, true );
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );

        $result = curl_exec ( $ch );
        curl_close ( $ch );

        return $result;
    }
}*/
