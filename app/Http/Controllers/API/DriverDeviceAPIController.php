<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateDriverDeviceAPIRequest;
use App\Http\Requests\API\UpdateDriverDeviceAPIRequest;
use App\Models\DriverDevice;
use App\Repositories\DriverDeviceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class DriverDeviceAPIController
 */
class DriverDeviceAPIController extends AppBaseController
{
    private DriverDeviceRepository $driverDeviceRepository;

    public function __construct(DriverDeviceRepository $driverDeviceRepo)
    {
        $this->driverDeviceRepository = $driverDeviceRepo;
    }

    /**
     * Display a listing of the DriverDevices.
     * GET|HEAD /driver-devices
     */
    public function index(Request $request): JsonResponse
    {
        $driverDevices = $this->driverDeviceRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($driverDevices->toArray(), 'Driver Devices retrieved successfully');
    }

    /**
     * Store a newly created DriverDevice in storage.
     * POST /driver-devices
     */
    public function store(CreateDriverDeviceAPIRequest $request): JsonResponse
    {

        if($request->isJson()){
            $input = $request->json()->all();
        }else{
            $input = $request->all();
        }

        if(!array_key_exists('driver_id', $input)){
            return $this->sendError('Le champ driver_id est obligatoire');
        }

        if(!array_key_exists('firebase_id', $input)){
            return $this->sendError('Le champ firebase_id est obligatoire');
        }

        $devices = DriverDevice::where(['firebase_id' => $input['firebase_id']])->get();

        if(count($devices) == 0){
            $device = $this->driverDeviceRepository->create($input);
        }else{
            $device = $devices[0];
            $device = $this->driverDeviceRepository->update($input, $device->id);
        }

        return $this->sendResponse($device->toArray(), 'Driver Device saved successfully');
    }

    /**
     * Display the specified DriverDevice.
     * GET|HEAD /driver-devices/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var DriverDevice $driverDevice */
        $driverDevice = $this->driverDeviceRepository->find($id);

        if (empty($driverDevice)) {
            return $this->sendError('Appareil du chauffeur introuvable');
        }

        return $this->sendResponse($driverDevice->toArray(), 'Driver Device retrieved successfully');
    }

    /**
     * Update the specified DriverDevice in storage.
     * PUT/PATCH /driver-devices/{id}
     */
    public function update($id, UpdateDriverDeviceAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var DriverDevice $driverDevice */
        $driverDevice = $this->driverDeviceRepository->find($id);

        if (empty($driverDevice)) {
            return $this->sendError('Appareil du chauffeur introuvable');
        }

        $driverDevice = $this->driverDeviceRepository->update($input, $id);

        return $this->sendResponse($driverDevice->toArray(), 'DriverDevice updated successfully');
    }

    /**
     * Remove the specified DriverDevice from storage.
     * DELETE /driver-devices/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var DriverDevice $driverDevice */
        $driverDevice = $this->driverDeviceRepository->find($id);

        if (empty($driverDevice)) {
            return $this->sendError('Appareil du chauffeur introuvable');
        }

        $driverDevice->delete();

        return $this->sendSuccess('Driver Device deleted successfully');
    }
}
