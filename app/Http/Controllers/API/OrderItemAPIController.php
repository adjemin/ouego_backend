<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateOrderItemAPIRequest;
use App\Http\Requests\API\UpdateOrderItemAPIRequest;
use App\Models\OrderItem;
use App\Repositories\OrderItemRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class OrderItemAPIController
 */
class OrderItemAPIController extends AppBaseController
{
    private OrderItemRepository $orderItemRepository;

    public function __construct(OrderItemRepository $orderItemRepo)
    {
        $this->orderItemRepository = $orderItemRepo;
    }

    /**
     * Display a listing of the OrderItems.
     * GET|HEAD /order-items
     */
    public function index(Request $request): JsonResponse
    {
        $orderItems = $this->orderItemRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($orderItems->toArray(), 'Order Items retrieved successfully');
    }

    /**
     * Store a newly created OrderItem in storage.
     * POST /order-items
     */
    public function store(CreateOrderItemAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $orderItem = $this->orderItemRepository->create($input);

        return $this->sendResponse($orderItem->toArray(), 'Order Item saved successfully');
    }

    /**
     * Display the specified OrderItem.
     * GET|HEAD /order-items/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var OrderItem $orderItem */
        $orderItem = $this->orderItemRepository->find($id);

        if (empty($orderItem)) {
            return $this->sendError('Order Item not found');
        }

        return $this->sendResponse($orderItem->toArray(), 'Order Item retrieved successfully');
    }

    /**
     * Update the specified OrderItem in storage.
     * PUT/PATCH /order-items/{id}
     */
    public function update($id, UpdateOrderItemAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var OrderItem $orderItem */
        $orderItem = $this->orderItemRepository->find($id);

        if (empty($orderItem)) {
            return $this->sendError('Order Item not found');
        }

        $orderItem = $this->orderItemRepository->update($input, $id);

        return $this->sendResponse($orderItem->toArray(), 'OrderItem updated successfully');
    }

    /**
     * Remove the specified OrderItem from storage.
     * DELETE /order-items/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var OrderItem $orderItem */
        $orderItem = $this->orderItemRepository->find($id);

        if (empty($orderItem)) {
            return $this->sendError('Order Item not found');
        }

        $orderItem->delete();

        return $this->sendSuccess('Order Item deleted successfully');
    }
}
