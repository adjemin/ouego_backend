<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateCustomerDeviceAPIRequest;
use App\Http\Requests\API\UpdateCustomerDeviceAPIRequest;
use App\Models\CustomerDevice;
use App\Repositories\CustomerDeviceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class CustomerDeviceAPIController
 */
class CustomerDeviceAPIController extends AppBaseController
{
    private CustomerDeviceRepository $customerDeviceRepository;

    public function __construct(CustomerDeviceRepository $customerDeviceRepo)
    {
        $this->customerDeviceRepository = $customerDeviceRepo;
    }

    /**
     * Display a listing of the CustomerDevices.
     * GET|HEAD /customer-devices
     */
    public function index(Request $request): JsonResponse
    {
        $customerDevices = $this->customerDeviceRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($customerDevices->toArray(), 'Customer Devices retrieved successfully');
    }

    /**
     * Store a newly created CustomerDevice in storage.
     * POST /customer-devices
     */
    public function store(CreateCustomerDeviceAPIRequest $request): JsonResponse
    {

        if($request->isJson()){
            $input = $request->json()->all();
        }else{
            $input = $request->all();
        }

        if(!array_key_exists('customer_id', $input)){
            return $this->sendError('Le champ customer_id est obligatoire');
        }

        if(!array_key_exists('firebase_id', $input)){
            return $this->sendError('Le champ firebase_id est obligatoire');
        }

        $customerDevices = CustomerDevice::where(['firebase_id' => $input['firebase_id']])->get();

        if(count($customerDevices) == 0){
            $customerDevice = $this->customerDeviceRepository->create($input);
        }else{
            $customerDevice = $customerDevices[0];
            $customerDevice = $this->customerDeviceRepository->update($input, $customerDevice->id);
        }

        return $this->sendResponse($customerDevice->toArray(), 'Customer Device saved successfully');
    }

    /**
     * Display the specified CustomerDevice.
     * GET|HEAD /customer-devices/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var CustomerDevice $customerDevice */
        $customerDevice = $this->customerDeviceRepository->find($id);

        if (empty($customerDevice)) {
            return $this->sendError('Appareil du client introuvable');
        }

        return $this->sendResponse($customerDevice->toArray(), 'Customer Device retrieved successfully');
    }

    /**
     * Update the specified CustomerDevice in storage.
     * PUT/PATCH /customer-devices/{id}
     */
    public function update($id, UpdateCustomerDeviceAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var CustomerDevice $customerDevice */
        $customerDevice = $this->customerDeviceRepository->find($id);

        if (empty($customerDevice)) {
            return $this->sendError('Appareil du client introuvable');
        }

        $customerDevice = $this->customerDeviceRepository->update($input, $id);

        return $this->sendResponse($customerDevice->toArray(), 'CustomerDevice updated successfully');
    }

    /**
     * Remove the specified CustomerDevice from storage.
     * DELETE /customer-devices/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var CustomerDevice $customerDevice */
        $customerDevice = $this->customerDeviceRepository->find($id);

        if (empty($customerDevice)) {
            return $this->sendError('Appareil du client introuvable');
        }

        $customerDevice->delete();

        return $this->sendSuccess('Customer Device deleted successfully');
    }
}
