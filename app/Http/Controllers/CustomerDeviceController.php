<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCustomerDeviceRequest;
use App\Http\Requests\UpdateCustomerDeviceRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\CustomerDeviceRepository;
use Illuminate\Http\Request;
use Flash;

class CustomerDeviceController extends AppBaseController
{
    /** @var CustomerDeviceRepository $customerDeviceRepository*/
    private $customerDeviceRepository;

    public function __construct(CustomerDeviceRepository $customerDeviceRepo)
    {
        $this->customerDeviceRepository = $customerDeviceRepo;
    }

    /**
     * Display a listing of the CustomerDevice.
     */
    public function index(Request $request)
    {
        $customerDevices = $this->customerDeviceRepository->paginate(10);

        return view('customer_devices.index')
            ->with('customerDevices', $customerDevices);
    }

    /**
     * Show the form for creating a new CustomerDevice.
     */
    public function create()
    {
        return view('customer_devices.create');
    }

    /**
     * Store a newly created CustomerDevice in storage.
     */
    public function store(CreateCustomerDeviceRequest $request)
    {
        $input = $request->all();

        $customerDevice = $this->customerDeviceRepository->create($input);

        Flash::success('Customer Device saved successfully.');

        return redirect(route('customerDevices.index'));
    }

    /**
     * Display the specified CustomerDevice.
     */
    public function show($id)
    {
        $customerDevice = $this->customerDeviceRepository->find($id);

        if (empty($customerDevice)) {
            Flash::error('Customer Device not found');

            return redirect(route('customerDevices.index'));
        }

        return view('customer_devices.show')->with('customerDevice', $customerDevice);
    }

    /**
     * Show the form for editing the specified CustomerDevice.
     */
    public function edit($id)
    {
        $customerDevice = $this->customerDeviceRepository->find($id);

        if (empty($customerDevice)) {
            Flash::error('Customer Device not found');

            return redirect(route('customerDevices.index'));
        }

        return view('customer_devices.edit')->with('customerDevice', $customerDevice);
    }

    /**
     * Update the specified CustomerDevice in storage.
     */
    public function update($id, UpdateCustomerDeviceRequest $request)
    {
        $customerDevice = $this->customerDeviceRepository->find($id);

        if (empty($customerDevice)) {
            Flash::error('Customer Device not found');

            return redirect(route('customerDevices.index'));
        }

        $customerDevice = $this->customerDeviceRepository->update($request->all(), $id);

        Flash::success('Customer Device updated successfully.');

        return redirect(route('customerDevices.index'));
    }

    /**
     * Remove the specified CustomerDevice from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $customerDevice = $this->customerDeviceRepository->find($id);

        if (empty($customerDevice)) {
            Flash::error('Customer Device not found');

            return redirect(route('customerDevices.index'));
        }

        $this->customerDeviceRepository->delete($id);

        Flash::success('Customer Device deleted successfully.');

        return redirect(route('customerDevices.index'));
    }
}
