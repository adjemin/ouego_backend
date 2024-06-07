<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateDriverAPIRequest;
use App\Http\Requests\API\UpdateDriverAPIRequest;
use App\Models\Driver;
use App\Repositories\DriverRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Class DriverAPIController
 */
class DriverAPIController extends AppBaseController
{
    private DriverRepository $driverRepository;

    public function __construct(DriverRepository $driverRepo)
    {
        $this->driverRepository = $driverRepo;
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
     * PUT/PATCH /drivers/{id}
     */
    public function update($id, UpdateDriverAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Driver $driver */
        $driver = $this->driverRepository->find($id);

        if (empty($driver)) {
            return $this->sendError('Driver not found');
        }

        $driver = $this->driverRepository->update($input, $id);

        return $this->sendResponse($driver->toArray(), 'Driver updated successfully');
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

        return $this->sendResponse([
            'token' => auth('api-drivers')->refresh(),
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL(),
            'server_time'=> now(),
            'user' => auth('api-drivers')->user()
        ], 'Token refreshed successfully');
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
}
