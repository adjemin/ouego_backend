<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderItemRequest;
use App\Http\Requests\UpdateOrderItemRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\OrderItemRepository;
use Illuminate\Http\Request;
use Flash;

class OrderItemController extends AppBaseController
{
    /** @var OrderItemRepository $orderItemRepository*/
    private $orderItemRepository;

    public function __construct(OrderItemRepository $orderItemRepo)
    {
        $this->orderItemRepository = $orderItemRepo;
    }

    /**
     * Display a listing of the OrderItem.
     */
    public function index(Request $request)
    {
        $orderItems = $this->orderItemRepository->paginate(10);

        return view('order_items.index')
            ->with('orderItems', $orderItems);
    }

    /**
     * Show the form for creating a new OrderItem.
     */
    public function create()
    {
        return view('order_items.create');
    }

    /**
     * Store a newly created OrderItem in storage.
     */
    public function store(CreateOrderItemRequest $request)
    {
        $input = $request->all();

        $orderItem = $this->orderItemRepository->create($input);

        Flash::success('Order Item saved successfully.');

        return redirect(route('orderItems.index'));
    }

    /**
     * Display the specified OrderItem.
     */
    public function show($id)
    {
        $orderItem = $this->orderItemRepository->find($id);

        if (empty($orderItem)) {
            Flash::error('Order Item not found');

            return redirect(route('orderItems.index'));
        }

        return view('order_items.show')->with('orderItem', $orderItem);
    }

    /**
     * Show the form for editing the specified OrderItem.
     */
    public function edit($id)
    {
        $orderItem = $this->orderItemRepository->find($id);

        if (empty($orderItem)) {
            Flash::error('Order Item not found');

            return redirect(route('orderItems.index'));
        }

        return view('order_items.edit')->with('orderItem', $orderItem);
    }

    /**
     * Update the specified OrderItem in storage.
     */
    public function update($id, UpdateOrderItemRequest $request)
    {
        $orderItem = $this->orderItemRepository->find($id);

        if (empty($orderItem)) {
            Flash::error('Order Item not found');

            return redirect(route('orderItems.index'));
        }

        $orderItem = $this->orderItemRepository->update($request->all(), $id);

        Flash::success('Order Item updated successfully.');

        return redirect(route('orderItems.index'));
    }

    /**
     * Remove the specified OrderItem from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $orderItem = $this->orderItemRepository->find($id);

        if (empty($orderItem)) {
            Flash::error('Order Item not found');

            return redirect(route('orderItems.index'));
        }

        $this->orderItemRepository->delete($id);

        Flash::success('Order Item deleted successfully.');

        return redirect(route('orderItems.index'));
    }
}
