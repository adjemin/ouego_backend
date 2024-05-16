<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateDeliveryTypeAPIRequest;
use App\Http\Requests\API\UpdateDeliveryTypeAPIRequest;
use App\Models\DeliveryType;
use App\Repositories\DeliveryTypeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class DeliveryTypeAPIController
 */
class DeliveryTypeAPIController extends AppBaseController
{
    private DeliveryTypeRepository $deliveryTypeRepository;

    public function __construct(DeliveryTypeRepository $deliveryTypeRepo)
    {
        $this->deliveryTypeRepository = $deliveryTypeRepo;
    }

    /**
     * Display a listing of the DeliveryTypes.
     * GET|HEAD /delivery-types
     */
    public function index(Request $request): JsonResponse
    {
        $deliveryTypes = $this->deliveryTypeRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($deliveryTypes->toArray(), 'Delivery Types retrieved successfully');
    }

    /**
     * Store a newly created DeliveryType in storage.
     * POST /delivery-types
     */
    public function store(CreateDeliveryTypeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $deliveryType = $this->deliveryTypeRepository->create($input);

        return $this->sendResponse($deliveryType->toArray(), 'Delivery Type saved successfully');
    }

    /**
     * Display the specified DeliveryType.
     * GET|HEAD /delivery-types/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var DeliveryType $deliveryType */
        $deliveryType = $this->deliveryTypeRepository->find($id);

        if (empty($deliveryType)) {
            return $this->sendError('Delivery Type not found');
        }

        return $this->sendResponse($deliveryType->toArray(), 'Delivery Type retrieved successfully');
    }

    /**
     * Update the specified DeliveryType in storage.
     * PUT/PATCH /delivery-types/{id}
     */
    public function update($id, UpdateDeliveryTypeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var DeliveryType $deliveryType */
        $deliveryType = $this->deliveryTypeRepository->find($id);

        if (empty($deliveryType)) {
            return $this->sendError('Delivery Type not found');
        }

        $deliveryType = $this->deliveryTypeRepository->update($input, $id);

        return $this->sendResponse($deliveryType->toArray(), 'DeliveryType updated successfully');
    }

    /**
     * Remove the specified DeliveryType from storage.
     * DELETE /delivery-types/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var DeliveryType $deliveryType */
        $deliveryType = $this->deliveryTypeRepository->find($id);

        if (empty($deliveryType)) {
            return $this->sendError('Delivery Type not found');
        }

        $deliveryType->delete();

        return $this->sendSuccess('Delivery Type deleted successfully');
    }
}
