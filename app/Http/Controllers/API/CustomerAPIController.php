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
            return $this->sendError('dialing_code is required');
        }

        if(!array_key_exists('phone_number', $input)) {
            return $this->sendError('phone_number is required');
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

        auth('api-drivers')->logout();

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
}
