<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderDeliveryRequest;
use App\Http\Requests\UpdateOrderDeliveryRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\OrderDeliveryRepository;
use Illuminate\Http\Request;
use Flash;

class OrderDeliveryController extends AppBaseController
{
    /** @var OrderDeliveryRepository $orderDeliveryRepository*/
    private $orderDeliveryRepository;

    public function __construct(OrderDeliveryRepository $orderDeliveryRepo)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepo;
    }

    /**
     * Display a listing of the OrderDelivery.
     */
    public function index(Request $request)
    {
        $orderDeliveries = $this->orderDeliveryRepository->paginate(10);

        return view('order_deliveries.index')
            ->with('orderDeliveries', $orderDeliveries);
    }

    /**
     * Show the form for creating a new OrderDelivery.
     */
    public function create()
    {
        return view('order_deliveries.create');
    }

    /**
     * Store a newly created OrderDelivery in storage.
     */
    public function store(CreateOrderDeliveryRequest $request)
    {
        $input = $request->all();

        $orderDelivery = $this->orderDeliveryRepository->create($input);

        Flash::success('Order Delivery saved successfully.');

        return redirect(route('orderDeliveries.index'));
    }

    /**
     * Display the specified OrderDelivery.
     */
    public function show($id)
    {
        $orderDelivery = $this->orderDeliveryRepository->find($id);

        if (empty($orderDelivery)) {
            Flash::error('Order Delivery not found');

            return redirect(route('orderDeliveries.index'));
        }

        return view('order_deliveries.show')->with('orderDelivery', $orderDelivery);
    }

    /**
     * Show the form for editing the specified OrderDelivery.
     */
    public function edit($id)
    {
        $orderDelivery = $this->orderDeliveryRepository->find($id);

        if (empty($orderDelivery)) {
            Flash::error('Order Delivery not found');

            return redirect(route('orderDeliveries.index'));
        }

        return view('order_deliveries.edit')->with('orderDelivery', $orderDelivery);
    }

    /**
     * Update the specified OrderDelivery in storage.
     */
    public function update($id, UpdateOrderDeliveryRequest $request)
    {
        $orderDelivery = $this->orderDeliveryRepository->find($id);

        if (empty($orderDelivery)) {
            Flash::error('Order Delivery not found');

            return redirect(route('orderDeliveries.index'));
        }

        $orderDelivery = $this->orderDeliveryRepository->update($request->all(), $id);

        Flash::success('Order Delivery updated successfully.');

        return redirect(route('orderDeliveries.index'));
    }

    /**
     * Remove the specified OrderDelivery from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $orderDelivery = $this->orderDeliveryRepository->find($id);

        if (empty($orderDelivery)) {
            Flash::error('Order Delivery not found');

            return redirect(route('orderDeliveries.index'));
        }

        $this->orderDeliveryRepository->delete($id);

        Flash::success('Order Delivery deleted successfully.');

        return redirect(route('orderDeliveries.index'));
    }
}
