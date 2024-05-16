<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateOrderPickupAPIRequest;
use App\Http\Requests\API\UpdateOrderPickupAPIRequest;
use App\Models\OrderPickup;
use App\Repositories\OrderPickupRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class OrderPickupAPIController
 */
class OrderPickupAPIController extends AppBaseController
{
    private OrderPickupRepository $orderPickupRepository;

    public function __construct(OrderPickupRepository $orderPickupRepo)
    {
        $this->orderPickupRepository = $orderPickupRepo;
    }

    /**
     * Display a listing of the OrderPickups.
     * GET|HEAD /order-pickups
     */
    public function index(Request $request): JsonResponse
    {
        $orderPickups = $this->orderPickupRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($orderPickups->toArray(), 'Order Pickups retrieved successfully');
    }

    /**
     * Store a newly created OrderPickup in storage.
     * POST /order-pickups
     */
    public function store(CreateOrderPickupAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $orderPickup = $this->orderPickupRepository->create($input);

        return $this->sendResponse($orderPickup->toArray(), 'Order Pickup saved successfully');
    }

    /**
     * Display the specified OrderPickup.
     * GET|HEAD /order-pickups/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var OrderPickup $orderPickup */
        $orderPickup = $this->orderPickupRepository->find($id);

        if (empty($orderPickup)) {
            return $this->sendError('Order Pickup not found');
        }

        return $this->sendResponse($orderPickup->toArray(), 'Order Pickup retrieved successfully');
    }

    /**
     * Update the specified OrderPickup in storage.
     * PUT/PATCH /order-pickups/{id}
     */
    public function update($id, UpdateOrderPickupAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var OrderPickup $orderPickup */
        $orderPickup = $this->orderPickupRepository->find($id);

        if (empty($orderPickup)) {
            return $this->sendError('Order Pickup not found');
        }

        $orderPickup = $this->orderPickupRepository->update($input, $id);

        return $this->sendResponse($orderPickup->toArray(), 'OrderPickup updated successfully');
    }

    /**
     * Remove the specified OrderPickup from storage.
     * DELETE /order-pickups/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var OrderPickup $orderPickup */
        $orderPickup = $this->orderPickupRepository->find($id);

        if (empty($orderPickup)) {
            return $this->sendError('Order Pickup not found');
        }

        $orderPickup->delete();

        return $this->sendSuccess('Order Pickup deleted successfully');
    }
}
