<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateDriverAPIRequest;
use App\Http\Requests\API\UpdateDriverAPIRequest;
use App\Models\Driver;
use App\Models\Engin;
use App\Models\EnginPicture;
use App\Repositories\DriverRepository;
use App\Repositories\EnginRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use MtnSmsCloud\MTNSMSApi;
use App\Models\DriverOtp;

/**
 * Class DriverAPIController
 */
class DriverAPIController extends AppBaseController
{
    private DriverRepository $driverRepository;

    private EnginRepository $enginRepository;

    public function __construct(DriverRepository $driverRepo, EnginRepository $enginRepo)
    {
        $this->driverRepository = $driverRepo;
        $this->enginRepository = $enginRepo;
    }

    /**
     * Display a listing of the Drivers.
     * GET|HEAD /drivers
     */
    public function index(Request $request): JsonResponse
    {
        $drivers = $this->driverRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($drivers->toArray(), 'Drivers retrieved successfully');
    }

    /**
     * Store a newly created Driver in storage.
     * POST /drivers
     */
    public function store(CreateDriverAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $driver = $this->driverRepository->create($input);

        return $this->sendResponse($driver->toArray(), 'Driver saved successfully');
    }

    /**
     * Display the specified Driver.
     * GET|HEAD /drivers/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $this->driverRepository->find($id);

        if (empty($driver)) {
            return $this->sendError('Driver not found');
        }

        return $this->sendResponse($driver->toArray(), 'Driver retrieved successfully');
    }

    /**
     * Update the specified Driver in storage.
     * PUT/PATCH /drivers/edit_profil
     */
    public function update(UpdateDriverAPIRequest $request): JsonResponse
    {

        $driver = auth('api-drivers')->user();
        $id = $driver->id;

        $input = $request->all();

        /** @var Driver $driver */
        $driver = $this->driverRepository->find($id);

        if (empty($driver)) {
            return $this->sendError('Driver not found');
        }

        if(!empty($request->input("photo_url"))){
            $driver->photo_url = $request->input("photo_url");
            $driver->update();
            unset($input['photo_url']);
        }

        if(!empty($request->input("services"))){
            $driver->services = $request->input("services");
            $driver->update();
            unset($input['services']);
        }

        if(!empty($request->input("driver_license_docs"))){
            $driver->driver_license_docs = $request->input("driver_license_docs");
            $driver->update();
            unset($input['driver_license_docs']);
        }

        if(array_key_exists('last_name', $input) && array_key_exists('first_name', $input)){

            $input['last_name'] = ucfirst(strtolower($input['last_name']));
            $input['first_name'] = ucfirst(strtolower($input['first_name']));
            $input['name'] = $input['first_name']." ".$input['last_name'];
        }


        $driver = $this->driverRepository->update($input, $id);

        $token = JWTAuth::fromUser($driver);

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $driver
        ], 'Driver saved successfully');
    }

    /**
     * Remove the specified Driver from storage.
     * DELETE /drivers/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $this->driverRepository->find($id);

        if (empty($driver)) {
            return $this->sendError('Driver not found');
        }

        $driver->delete();

        return $this->sendSuccess('Driver deleted successfully');
    }

    public function register(Request $request){

        if ($request->isJson()) {
            $input = $request->json()->all();
        } else {
            $input = $request->all();
        }

        if (!array_key_exists('last_name', $input)) {
            return $this->sendError('last_name is required');
        }

        if (!array_key_exists('first_name', $input)) {
            return $this->sendError('first_name is required');
        }

        if (!array_key_exists('dialing_code', $input)) {
            return $this->sendError('dialing_code is required');
        }

        if(!array_key_exists('phone_number', $input)) {
            return $this->sendError('phone_number is required');
        }

        $input['phone'] = $input['dialing_code'].''.$input['phone_number'];

        $driver = Driver::where('phone', '=', $input['phone'])->first();
        if ($driver != null) {
            return $this->sendError('Numéro de téléphone déjà utilisé', 400);
        }

        $driverDeleted = Driver::withTrashed()->where(['phone' => $input['phone']])->first();
        if ($driverDeleted == null) {
            $input['last_name'] = ucfirst(strtolower($input['last_name']));
            $input['first_name'] = ucfirst(strtolower($input['first_name']));
            $input['name'] = $input['first_name']." ".$input['last_name'];

            $driver = Driver::create($input);

        } else {
            $driverDeleted->restore();
            $driver = $driverDeleted;
        }

        $token = JWTAuth::fromUser($driver);


        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $driver
        ], 'Driver saved successfully');

    }

    public function login(Request $request){
        if ($request->isJson()) {
            $input = $request->json()->all();
        } else {
            $input = $request->all();
        }

        if (!array_key_exists('dialing_code', $input)) {
            return $this->sendError('dialing_code is required');
        }

        if(!array_key_exists('phone_number', $input)) {
            return $this->sendError('phone_number is required');
        }

        $input['phone'] = $input['dialing_code'].''.$input['phone_number'];

        $driver = Driver::where('phone', '=', $input['phone'])->first();
        if ($driver == null) {
            return $this->sendError('Compte introuvable', 401);
        }


        $token = JWTAuth::fromUser($driver);


        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $driver
        ], 'Driver saved successfully');
    }

    public function logout(Request $request){

        auth('api-drivers')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);

    }


    public function refresh()
    {
        try {

            $token = auth('api-drivers')->refresh();

            return $this->sendResponse([
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL(),
                'server_time'=> now(),
                'user' => auth('api-drivers')->user()
            ], 'Token refreshed successfully');

        } catch (TokenExpiredException $e) {
            return response()->json([
                'code' => 401,
                'success' => false,
                'message' => 'Token has expired and can no longer be refreshed',
                'status' => 'UNAUTHORIZED',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'code' => 401,
                'success' => false,
                'message' => 'Could not refresh token',
                'status' => 'UNAUTHORIZED',
            ], 401);
        }
    }

    public function getProfil(Request $request){

        $driver = auth('api-drivers')->user();

        $token = JWTAuth::fromUser($driver);

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $driver
        ], 'Driver got successfully');


    }

    public function createCar(Request $request){

        $driver = auth('api-drivers')->user();

        if ($request->isJson()) {
            $input = $request->json()->all();
        } else {
            $input = $request->all();
        }

        $car = Engin::create([
            "car_docs" => $request->input("car_docs"),
            "driver_id" => $driver->id
        ]);

        $pictures = $request->input("pictures");
        if(!is_array($pictures)){
            $pictures = (array)$pictures;
        }

        if(!empty($pictures)){
            foreach($pictures as $picture){
                EnginPicture::create([
                    "engin_id" => $car->id,
                    "url" => $picture
                ]);
            }
        }

        $driver = auth('api-drivers')->user();

        $token = JWTAuth::fromUser($driver);

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $driver
        ], 'Driver got successfully');


    }

    public function updateCar($id, Request $request){

        if ($request->isJson()) {
            $input = $request->json()->all();
        } else {
            $input = $request->all();
        }

        /** @var Engin $engin */
        $engin = $this->enginRepository->find($id);

        if (empty($engin)) {
            return $this->sendError('Engin not found');
        }

        $engin = $this->enginRepository->update($input, $id);

        $driver = auth('api-drivers')->user();

        $token = JWTAuth::fromUser($driver);

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $driver
        ], 'Driver got successfully');
    }

    public function updateAvailability($id, Request $request){

        $input = $request->all();

        $driver = Driver::where('id', $id)->first();

        if (empty($driver)) {
            return $this->sendError('Driver not found');
        }

        if(!array_key_exists('latitude', $input)){
            return $this->sendError('latitude is required', 400);
        }

        if(!array_key_exists('longitude', $input)){
            return $this->sendError('longitude is required', 400);
        }

        $input_driver = [
            'driver_id' => $driver->id,
            'is_available' => array_key_exists('is_available', $input)?$input['is_available']:true,
            'last_location_latitude' => $input['latitude'],
            'last_location_longitude' => $input['longitude'],
        ];

        $driver = $this->driverRepository->update($input_driver, $driver->id);

        $driver->last_location  = [$driver->last_location_latitude, $driver->last_location_longitude];
        $driver->save();


        return $this->sendResponse([
            'user' => $driver
        ], 'Driver got successfully');
    }

    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError(json_encode($validator->errors()),422);
        }

        // Générer un OTP à 6 chiffres
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Créer ou mettre à jour l'entrée DriverOtp
        $customerOTP = DriverOtp::updateOrCreate(
            ['phone' => $request->phone],
            [
                'otp' => $otp,
                'otp_expires_at' => Carbon::now()->addMinutes(5),
                'is_test_mode' => false // Vous pouvez ajuster cela selon vos besoins
            ]
        );

        // Envoyer l'OTP par SMS
        $this->sendSMS($request->phone, "Votre code OTP est: {$otp}");

        return $this->sendResponse($customerOTP, 'OTP envoyé avec succès');
    }

    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|exists:customer_o_t_ps,phone',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError(json_encode($validator->errors()),422);
        }

        $customerOTP = DriverOtp::where('phone', $request->phone)->first();

        if (!$customerOTP || $customerOTP->otp !== $request->otp || Carbon::now()->gt($customerOTP->otp_expires_at)) {
            return $this->sendError('OTP invalide ou expiré',400);
        }

        // Réinitialiser l'OTP
        $customerOTP->forceDelete();

        // Récupérer l'utilisateur et générer le token JWT
        $customer = Driver::where('phone', $request->phone)->first();
        if($customer == null){

            return $this->sendResponse(true, 'OTP verified successfully');

        }else{
            $token = JWTAuth::fromUser($customer);

            return $this->sendResponse([
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL(),
                'server_time'=> now(),
                'user' => $customer
            ], 'Customer got successfully');
        }

    }

    public function sendSMS($client_phone, $message){


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
