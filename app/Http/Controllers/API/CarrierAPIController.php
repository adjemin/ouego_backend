<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateCarrierAPIRequest;
use App\Http\Requests\API\UpdateCarrierAPIRequest;
use App\Models\Carrier;
use App\Repositories\CarrierRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class CarrierAPIController
 */
class CarrierAPIController extends AppBaseController
{
    private CarrierRepository $carrierRepository;

    public function __construct(CarrierRepository $carrierRepo)
    {
        $this->carrierRepository = $carrierRepo;
    }

    /**
     * Display a listing of the Carriers.
     * GET|HEAD /carriers
     */
    public function index(Request $request): JsonResponse
    {
        $carriers = $this->carrierRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($carriers->toArray(), 'Carriers retrieved successfully');
    }

    /**
     * Store a newly created Carrier in storage.
     * POST /carriers
     */
    public function store(CreateCarrierAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $carrier = $this->carrierRepository->create($input);

        return $this->sendResponse($carrier->toArray(), 'Carrier saved successfully');
    }

    /**
     * Display the specified Carrier.
     * GET|HEAD /carriers/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Carrier $carrier */
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            return $this->sendError('Carrier not found');
        }

        return $this->sendResponse($carrier->toArray(), 'Carrier retrieved successfully');
    }

    /**
     * Update the specified Carrier in storage.
     * PUT/PATCH /carriers/{id}
     */
    public function update($id, UpdateCarrierAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Carrier $carrier */
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            return $this->sendError('Carrier not found');
        }

        $carrier = $this->carrierRepository->update($input, $id);

        return $this->sendResponse($carrier->toArray(), 'Carrier updated successfully');
    }

    /**
     * Remove the specified Carrier from storage.
     * DELETE /carriers/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Carrier $carrier */
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            return $this->sendError('Carrier not found');
        }

        $carrier->delete();

        return $this->sendSuccess('Carrier deleted successfully');
    }
}
