<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCarrierRequest;
use App\Http\Requests\UpdateCarrierRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\CarrierRepository;
use Illuminate\Http\Request;
use Flash;

class CarrierController extends AppBaseController
{
    /** @var CarrierRepository $carrierRepository*/
    private $carrierRepository;

    public function __construct(CarrierRepository $carrierRepo)
    {
        $this->carrierRepository = $carrierRepo;
    }

    /**
     * Display a listing of the Carrier.
     */
    public function index(Request $request)
    {
        $carriers = $this->carrierRepository->paginate(10);

        return view('carriers.index')
            ->with('carriers', $carriers);
    }

    /**
     * Show the form for creating a new Carrier.
     */
    public function create()
    {
        return view('carriers.create');
    }

    /**
     * Store a newly created Carrier in storage.
     */
    public function store(CreateCarrierRequest $request)
    {
        $input = $request->all();

        $carrier = $this->carrierRepository->create($input);

        Flash::success('Carrier saved successfully.');

        return redirect(route('carriers.index'));
    }

    /**
     * Display the specified Carrier.
     */
    public function show($id)
    {
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            Flash::error('Carrier not found');

            return redirect(route('carriers.index'));
        }

        return view('carriers.show')->with('carrier', $carrier);
    }

    /**
     * Show the form for editing the specified Carrier.
     */
    public function edit($id)
    {
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            Flash::error('Carrier not found');

            return redirect(route('carriers.index'));
        }

        return view('carriers.edit')->with('carrier', $carrier);
    }

    /**
     * Update the specified Carrier in storage.
     */
    public function update($id, UpdateCarrierRequest $request)
    {
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            Flash::error('Carrier not found');

            return redirect(route('carriers.index'));
        }

        $carrier = $this->carrierRepository->update($request->all(), $id);

        Flash::success('Carrier updated successfully.');

        return redirect(route('carriers.index'));
    }

    /**
     * Remove the specified Carrier from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            Flash::error('Carrier not found');

            return redirect(route('carriers.index'));
        }

        $this->carrierRepository->delete($id);

        Flash::success('Carrier deleted successfully.');

        return redirect(route('carriers.index'));
    }
}
