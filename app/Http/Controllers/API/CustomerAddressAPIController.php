<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateCustomerAddressAPIRequest;
use App\Http\Requests\API\UpdateCustomerAddressAPIRequest;
use App\Models\CustomerAddress;
use App\Repositories\CustomerAddressRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class CustomerAddressAPIController
 */
class CustomerAddressAPIController extends AppBaseController
{
    private CustomerAddressRepository $customerAddressRepository;

    public function __construct(CustomerAddressRepository $customerAddressRepo)
    {
        $this->customerAddressRepository = $customerAddressRepo;
    }

    /**
     * Display a listing of the CustomerAddresses.
     * GET|HEAD /customer-addresses
     */
    public function index(Request $request): JsonResponse
    {
        $customer = auth('api-customers')->user();
        $query = $request->get('q');    

        $customerAddresses = CustomerAddress::where('customer_id', $customer->id)
        ->when(!empty($query), function ($searchQuery) use ($query) {
            return $searchQuery->where('address_name', 'ilike', "%$query%")
                ->orWhere('label', 'ilike', "%$query%")
                ->orWhere('details', 'ilike', "%$query%");
        })
        ->orderBy('address_name', 'asc')
        ->limit(10)
        ->get();
        
        return $this->sendResponse($customerAddresses->toArray(), 'Customer Addresses retrieved successfully');
    }

    /**
     * Store a newly created CustomerAddress in storage.
     * POST /customer-addresses
     */
    public function store(CreateCustomerAddressAPIRequest $request): JsonResponse
    {
        $customer = auth('api-customers')->user();
        $input = $request->all();
        $input['customer_id'] = $customer->id;

        $customerAddress = $this->customerAddressRepository->create($input);

        return $this->sendResponse($customerAddress->toArray(), 'Customer Address saved successfully');
    }

    /**
     * Display the specified CustomerAddress.
     * GET|HEAD /customer-addresses/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var CustomerAddress $customerAddress */
        $customerAddress = $this->customerAddressRepository->find($id);

        if (empty($customerAddress)) {
            return $this->sendError('Customer Address not found');
        }

        return $this->sendResponse($customerAddress->toArray(), 'Customer Address retrieved successfully');
    }

    /**
     * Update the specified CustomerAddress in storage.
     * PUT/PATCH /customer-addresses/{id}
     */
    public function update($id, UpdateCustomerAddressAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var CustomerAddress $customerAddress */
        $customerAddress = $this->customerAddressRepository->find($id);

        if (empty($customerAddress)) {
            return $this->sendError('Customer Address not found');
        }

        $customerAddress = $this->customerAddressRepository->update($input, $id);

        return $this->sendResponse($customerAddress->toArray(), 'CustomerAddress updated successfully');
    }

    /**
     * Remove the specified CustomerAddress from storage.
     * DELETE /customer-addresses/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var CustomerAddress $customerAddress */
        $customerAddress = $this->customerAddressRepository->find($id);

        if (empty($customerAddress)) {
            return $this->sendError('Customer Address not found');
        }

        $customerAddress->delete();

        return $this->sendSuccess('Customer Address deleted successfully');
    }
}
