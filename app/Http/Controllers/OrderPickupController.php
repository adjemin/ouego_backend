<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderPickupRequest;
use App\Http\Requests\UpdateOrderPickupRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\OrderPickupRepository;
use Illuminate\Http\Request;
use Flash;

class OrderPickupController extends AppBaseController
{
    /** @var OrderPickupRepository $orderPickupRepository*/
    private $orderPickupRepository;

    public function __construct(OrderPickupRepository $orderPickupRepo)
    {
        $this->orderPickupRepository = $orderPickupRepo;
    }

    /**
     * Display a listing of the OrderPickup.
     */
    public function index(Request $request)
    {
        $orderPickups = $this->orderPickupRepository->paginate(10);

        return view('order_pickups.index')
            ->with('orderPickups', $orderPickups);
    }

    /**
     * Show the form for creating a new OrderPickup.
     */
    public function create()
    {
        return view('order_pickups.create');
    }

    /**
     * Store a newly created OrderPickup in storage.
     */
    public function store(CreateOrderPickupRequest $request)
    {
        $input = $request->all();

        $orderPickup = $this->orderPickupRepository->create($input);

        Flash::success('Order Pickup saved successfully.');

        return redirect(route('orderPickups.index'));
    }

    /**
     * Display the specified OrderPickup.
     */
    public function show($id)
    {
        $orderPickup = $this->orderPickupRepository->find($id);

        if (empty($orderPickup)) {
            Flash::error('Order Pickup not found');

            return redirect(route('orderPickups.index'));
        }

        return view('order_pickups.show')->with('orderPickup', $orderPickup);
    }

    /**
     * Show the form for editing the specified OrderPickup.
     */
    public function edit($id)
    {
        $orderPickup = $this->orderPickupRepository->find($id);

        if (empty($orderPickup)) {
            Flash::error('Order Pickup not found');

            return redirect(route('orderPickups.index'));
        }

        return view('order_pickups.edit')->with('orderPickup', $orderPickup);
    }

    /**
     * Update the specified OrderPickup in storage.
     */
    public function update($id, UpdateOrderPickupRequest $request)
    {
        $orderPickup = $this->orderPickupRepository->find($id);

        if (empty($orderPickup)) {
            Flash::error('Order Pickup not found');

            return redirect(route('orderPickups.index'));
        }

        $orderPickup = $this->orderPickupRepository->update($request->all(), $id);

        Flash::success('Order Pickup updated successfully.');

        return redirect(route('orderPickups.index'));
    }

    /**
     * Remove the specified OrderPickup from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $orderPickup = $this->orderPickupRepository->find($id);

        if (empty($orderPickup)) {
            Flash::error('Order Pickup not found');

            return redirect(route('orderPickups.index'));
        }

        $this->orderPickupRepository->delete($id);

        Flash::success('Order Pickup deleted successfully.');

        return redirect(route('orderPickups.index'));
    }
}
