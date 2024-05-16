<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateOrderDeliveryAPIRequest;
use App\Http\Requests\API\UpdateOrderDeliveryAPIRequest;
use App\Models\OrderDelivery;
use App\Repositories\OrderDeliveryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class OrderDeliveryAPIController
 */
class OrderDeliveryAPIController extends AppBaseController
{
    private OrderDeliveryRepository $orderDeliveryRepository;

    public function __construct(OrderDeliveryRepository $orderDeliveryRepo)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepo;
    }

    /**
     * Display a listing of the OrderDeliveries.
     * GET|HEAD /order-deliveries
     */
    public function index(Request $request): JsonResponse
    {
        $orderDeliveries = $this->orderDeliveryRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($orderDeliveries->toArray(), 'Order Deliveries retrieved successfully');
    }

    /**
     * Store a newly created OrderDelivery in storage.
     * POST /order-deliveries
     */
    public function store(CreateOrderDeliveryAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $orderDelivery = $this->orderDeliveryRepository->create($input);

        return $this->sendResponse($orderDelivery->toArray(), 'Order Delivery saved successfully');
    }

    /**
     * Display the specified OrderDelivery.
     * GET|HEAD /order-deliveries/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var OrderDelivery $orderDelivery */
        $orderDelivery = $this->orderDeliveryRepository->find($id);

        if (empty($orderDelivery)) {
            return $this->sendError('Order Delivery not found');
        }

        return $this->sendResponse($orderDelivery->toArray(), 'Order Delivery retrieved successfully');
    }

    /**
     * Update the specified OrderDelivery in storage.
     * PUT/PATCH /order-deliveries/{id}
     */
    public function update($id, UpdateOrderDeliveryAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var OrderDelivery $orderDelivery */
        $orderDelivery = $this->orderDeliveryRepository->find($id);

        if (empty($orderDelivery)) {
            return $this->sendError('Order Delivery not found');
        }

        $orderDelivery = $this->orderDeliveryRepository->update($input, $id);

        return $this->sendResponse($orderDelivery->toArray(), 'OrderDelivery updated successfully');
    }

    /**
     * Remove the specified OrderDelivery from storage.
     * DELETE /order-deliveries/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var OrderDelivery $orderDelivery */
        $orderDelivery = $this->orderDeliveryRepository->find($id);

        if (empty($orderDelivery)) {
            return $this->sendError('Order Delivery not found');
        }

        $orderDelivery->delete();

        return $this->sendSuccess('Order Delivery deleted successfully');
    }
}
