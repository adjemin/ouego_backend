<?php

namespace App\Utilities;

use App\Models\Driver;
use App\Models\DriverDevice;
use App\Models\DriverNotification;
use Illuminate\Support\Facades\Mail;

class DriverNotificationsUtils
{

    /**
     * @param $userNotification
     * @return bool|string|array
     */
    public static function notify(DriverNotification $userNotification){
        $user = Driver::where(["id" => $userNotification->driver_id])->first();

        if(!empty($user)){

            $title = $userNotification->title;
            $subtitle = $userNotification->subtitle;
            $typeNotification = $userNotification->type;


            $userDevices = DriverDevice::where([
                "driver_id" => $user->id,
                "deleted_at" => null
            ])->orderBy('updated_at', 'DESC')->get();

            $metadata = $userNotification->toArray();

            if($userDevices != null){

                $messages_sent = [];

                foreach($userDevices as $userDevice){
                    $deviceFirebaseId = "".$userDevice->firebase_id;
                    $result = FirebaseMessagingUtils::sendNotification($title, $subtitle,$typeNotification, $metadata, $deviceFirebaseId);
                    array_push($messages_sent, $result);
                }

                return $messages_sent;

            }else{

                return "No Devices";

            }


        }

        return "No Devices";


    }

    /**
     * @param $userId
     * @param $message @Message
     * @return array|string
     */
    public static function simpleNotify($userId, $metaData, $metaDataId, $type){

        if(is_array($metaData)){

            $metaDataArray = $metaData;

        }else{

            $metaDataArray = json_decode($metaData, true);

        }

        $user = User::where(["id" => $userId])->first();

        if(empty($user)){
            return "No Devices";
        }

        $title = array_key_exists('title', $metaDataArray)?$metaDataArray['title']:"Title";
        $subtitle = array_key_exists('subtitle', $metaDataArray)?$metaDataArray['subtitle']:"Subtitle";


        $userDevices = Device::where([
            "user_id" => $userId,
            "deleted_at" => null
        ])->orderBy('updated_at', 'DESC')->get();


        if($userDevices != null){

            $messages_sent = [];
            $metadata = $metaDataArray;
            $metadata['id']=  $metaDataId;
            foreach($userDevices as $userDevice){
                $device = "".$userDevice->firebase_id;
                $result = FirebaseMessagingUtils::sendNotification($title, $subtitle,$type, $metadata, $device);
                array_push($messages_sent, $result);
            }

            return $messages_sent;
        }else{
            return "No Devices";
        }

    }

}
