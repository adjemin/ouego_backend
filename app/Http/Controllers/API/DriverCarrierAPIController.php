<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateDriverCarrierAPIRequest;
use App\Http\Requests\API\UpdateDriverCarrierAPIRequest;
use App\Models\DriverCarrier;
use App\Repositories\DriverCarrierRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class DriverCarrierAPIController
 */
class DriverCarrierAPIController extends AppBaseController
{
    private DriverCarrierRepository $driverCarrierRepository;

    public function __construct(DriverCarrierRepository $driverCarrierRepo)
    {
        $this->driverCarrierRepository = $driverCarrierRepo;
    }

    /**
     * Display a listing of the DriverCarriers.
     * GET|HEAD /driver-carriers
     */
    public function index(Request $request): JsonResponse
    {
        $driverCarriers = $this->driverCarrierRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($driverCarriers->toArray(), 'Driver Carriers retrieved successfully');
    }

    /**
     * Store a newly created DriverCarrier in storage.
     * POST /driver-carriers
     */
    public function store(CreateDriverCarrierAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $driverCarrier = $this->driverCarrierRepository->create($input);

        return $this->sendResponse($driverCarrier->toArray(), 'Driver Carrier saved successfully');
    }

    /**
     * Display the specified DriverCarrier.
     * GET|HEAD /driver-carriers/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var DriverCarrier $driverCarrier */
        $driverCarrier = $this->driverCarrierRepository->find($id);

        if (empty($driverCarrier)) {
            return $this->sendError('Driver Carrier not found');
        }

        return $this->sendResponse($driverCarrier->toArray(), 'Driver Carrier retrieved successfully');
    }

    /**
     * Update the specified DriverCarrier in storage.
     * PUT/PATCH /driver-carriers/{id}
     */
    public function update($id, UpdateDriverCarrierAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var DriverCarrier $driverCarrier */
        $driverCarrier = $this->driverCarrierRepository->find($id);

        if (empty($driverCarrier)) {
            return $this->sendError('Driver Carrier not found');
        }

        $driverCarrier = $this->driverCarrierRepository->update($input, $id);

        return $this->sendResponse($driverCarrier->toArray(), 'DriverCarrier updated successfully');
    }

    /**
     * Remove the specified DriverCarrier from storage.
     * DELETE /driver-carriers/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var DriverCarrier $driverCarrier */
        $driverCarrier = $this->driverCarrierRepository->find($id);

        if (empty($driverCarrier)) {
            return $this->sendError('Driver Carrier not found');
        }

        $driverCarrier->delete();

        return $this->sendSuccess('Driver Carrier deleted successfully');
    }
}
