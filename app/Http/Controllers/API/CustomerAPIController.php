<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateCustomerAPIRequest;
use App\Http\Requests\API\UpdateCustomerAPIRequest;
use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use App\Models\CustomerOTP;
use Carbon\Carbon;
use MtnSmsCloud\MTNSMSApi;

/**
 * Class CustomerAPIController
 */
class CustomerAPIController extends AppBaseController
{
    private CustomerRepository $customerRepository;

    public function __construct(CustomerRepository $customerRepo)
    {
        $this->customerRepository = $customerRepo;
    }

    /**
     * Display a listing of the Customers.
     * GET|HEAD /customers
     */
    public function index(Request $request): JsonResponse
    {
        $customers = $this->customerRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($customers->toArray(), 'Customers retrieved successfully');
    }

    /**
     * Store a newly created Customer in storage.
     * POST /customers
     */
    public function store(CreateCustomerAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $customer = $this->customerRepository->create($input);

        return $this->sendResponse($customer->toArray(), 'Customer saved successfully');
    }

    /**
     * Display the specified Customer.
     * GET|HEAD /customers/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $this->customerRepository->find($id);

        if (empty($customer)) {
            return $this->sendError('Customer not found');
        }

        return $this->sendResponse($customer->toArray(), 'Customer retrieved successfully');
    }

    /**
     * Update the specified Customer in storage.
     * PUT/PATCH /customers/edit_profil
     */
    public function update(UpdateCustomerAPIRequest $request): JsonResponse
    {

        $customer = auth('api-customers')->user();

        $input = $request->all();

        if(array_key_exists('last_name', $input) && array_key_exists('first_name', $input)){
            $input['last_name'] = ucfirst(strtolower($input['last_name']));
            $input['first_name'] = ucfirst(strtolower($input['first_name']));
            $input['name'] = $input['first_name']." ".$input['last_name'];
        }



        if (empty($customer)) {
            return $this->sendError('Customer not found');
        }

        $customer = $this->customerRepository->update($input, $customer->id);

        $token = JWTAuth::fromUser($customer);

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $customer
        ], 'Customer updated successfully');
    }

    /**
     * Remove the specified Customer from storage.
     * DELETE /customers/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $this->customerRepository->find($id);

        if (empty($customer)) {
            return $this->sendError('Customer not found');
        }

        $customer->delete();

        return $this->sendSuccess('Customer deleted successfully');
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

        $customer = Customer::where('phone', '=', $input['phone'])->first();
        if ($customer != null) {
            return $this->sendError('Numéro de téléphone déjà utilisé', 400);
        }

        $customerDeleted = Customer::withTrashed()->where(['phone' => $input['phone']])->first();
        if ($customerDeleted == null) {
            $input['last_name'] = ucfirst(strtolower($input['last_name']));
            $input['first_name'] = ucfirst(strtolower($input['first_name']));
            $input['name'] = $input['first_name']." ".$input['last_name'];

            $customer = Customer::create($input);

        } else {
            $customerDeleted->restore();
            $customer = $customerDeleted;
        }

        $token = JWTAuth::fromUser($customer);


        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $customer
        ], 'Customer saved successfully');

    }

    public function login(Request $request){
        if ($request->isJson()) {
            $input = $request->json()->all();
        } else {
            $input = $request->all();
        }

        if (!array_key_exists('dialing_code', $input)) {
            return $this->sendError('dialing_code is required',400);
        }

        if(!array_key_exists('phone_number', $input)) {
            return $this->sendError('phone_number is required',400);
        }

        $input['phone'] = $input['dialing_code'].''.$input['phone_number'];

        $customer = Customer::where('phone', '=', $input['phone'])->first();
        if ($customer == null) {
            return $this->sendError('Compte introuvable', 401);
        }


        $token = JWTAuth::fromUser($customer);


        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $customer
        ], 'Customer saved successfully');
    }

    public function logout(Request $request){

        auth('api-customers')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);

    }


    public function refresh()
    {

        return $this->sendResponse([
            'token' => auth('api-customers')->refresh(),
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => auth('api-customers')->user()
        ], 'Token refreshed successfully');
    }

    public function getProfil(Request $request){

        $customer = auth('api-customers')->user();

        $token = JWTAuth::fromUser($customer);

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => $customer
        ], 'Customer got successfully');


    }

    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError(json_encode($validator->errors()),422);
        }

        // Vérifier si le numéro de téléphone est déjà enregistré
        $customer = Customer::where('phone', $request->phone)->first();
        if ($customer && $customer->is_blocked) {
            return $this->sendError('Votre compte bloqué a été bloqué, veuillez contacter le support', 403);
        }

        // Générer un OTP à 6 chiffres
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Créer ou mettre à jour l'entrée CustomerOTP
        $customerOTP = CustomerOTP::updateOrCreate(
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

        $customerOTP = CustomerOTP::where('phone', $request->phone)->first();

        if (!$customerOTP || $customerOTP->otp !== $request->otp || Carbon::now()->gt($customerOTP->otp_expires_at)) {
            return $this->sendError('OTP invalide ou expiré',400);
        }

        // Réinitialiser l'OTP
        $customerOTP->forceDelete();

        // Récupérer l'utilisateur et générer le token JWT
        $customer = Customer::where('phone', $request->phone)->first();
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
