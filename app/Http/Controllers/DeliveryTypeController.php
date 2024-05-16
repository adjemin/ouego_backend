<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDeliveryTypeRequest;
use App\Http\Requests\UpdateDeliveryTypeRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\DeliveryTypeRepository;
use Illuminate\Http\Request;
use Flash;

class DeliveryTypeController extends AppBaseController
{
    /** @var DeliveryTypeRepository $deliveryTypeRepository*/
    private $deliveryTypeRepository;

    public function __construct(DeliveryTypeRepository $deliveryTypeRepo)
    {
        $this->deliveryTypeRepository = $deliveryTypeRepo;
    }

    /**
     * Display a listing of the DeliveryType.
     */
    public function index(Request $request)
    {
        $deliveryTypes = $this->deliveryTypeRepository->paginate(10);

        return view('delivery_types.index')
            ->with('deliveryTypes', $deliveryTypes);
    }

    /**
     * Show the form for creating a new DeliveryType.
     */
    public function create()
    {
        return view('delivery_types.create');
    }

    /**
     * Store a newly created DeliveryType in storage.
     */
    public function store(CreateDeliveryTypeRequest $request)
    {
        $input = $request->all();

        $deliveryType = $this->deliveryTypeRepository->create($input);

        Flash::success('Delivery Type saved successfully.');

        return redirect(route('deliveryTypes.index'));
    }

    /**
     * Display the specified DeliveryType.
     */
    public function show($id)
    {
        $deliveryType = $this->deliveryTypeRepository->find($id);

        if (empty($deliveryType)) {
            Flash::error('Delivery Type not found');

            return redirect(route('deliveryTypes.index'));
        }

        return view('delivery_types.show')->with('deliveryType', $deliveryType);
    }

    /**
     * Show the form for editing the specified DeliveryType.
     */
    public function edit($id)
    {
        $deliveryType = $this->deliveryTypeRepository->find($id);

        if (empty($deliveryType)) {
            Flash::error('Delivery Type not found');

            return redirect(route('deliveryTypes.index'));
        }

        return view('delivery_types.edit')->with('deliveryType', $deliveryType);
    }

    /**
     * Update the specified DeliveryType in storage.
     */
    public function update($id, UpdateDeliveryTypeRequest $request)
    {
        $deliveryType = $this->deliveryTypeRepository->find($id);

        if (empty($deliveryType)) {
            Flash::error('Delivery Type not found');

            return redirect(route('deliveryTypes.index'));
        }

        $deliveryType = $this->deliveryTypeRepository->update($request->all(), $id);

        Flash::success('Delivery Type updated successfully.');

        return redirect(route('deliveryTypes.index'));
    }

    /**
     * Remove the specified DeliveryType from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $deliveryType = $this->deliveryTypeRepository->find($id);

        if (empty($deliveryType)) {
            Flash::error('Delivery Type not found');

            return redirect(route('deliveryTypes.index'));
        }

        $this->deliveryTypeRepository->delete($id);

        Flash::success('Delivery Type deleted successfully.');

        return redirect(route('deliveryTypes.index'));
    }
}
