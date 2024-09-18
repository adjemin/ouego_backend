<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateCustomerOTPAPIRequest;
use App\Http\Requests\API\UpdateCustomerOTPAPIRequest;
use App\Models\CustomerOTP;
use App\Repositories\CustomerOTPRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class CustomerOTPAPIController
 */
class CustomerOTPAPIController extends AppBaseController
{
    private CustomerOTPRepository $customerOTPRepository;

    public function __construct(CustomerOTPRepository $customerOTPRepo)
    {
        $this->customerOTPRepository = $customerOTPRepo;
    }

    /**
     * Display a listing of the CustomerOTPs.
     * GET|HEAD /customer-o-t-ps
     */
    public function index(Request $request): JsonResponse
    {
        $customerOTPs = $this->customerOTPRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($customerOTPs->toArray(), 'Customer O T Ps retrieved successfully');
    }

    /**
     * Store a newly created CustomerOTP in storage.
     * POST /customer-o-t-ps
     */
    public function store(CreateCustomerOTPAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $customerOTP = $this->customerOTPRepository->create($input);

        return $this->sendResponse($customerOTP->toArray(), 'Customer O T P saved successfully');
    }

    /**
     * Display the specified CustomerOTP.
     * GET|HEAD /customer-o-t-ps/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var CustomerOTP $customerOTP */
        $customerOTP = $this->customerOTPRepository->find($id);

        if (empty($customerOTP)) {
            return $this->sendError('Customer O T P not found');
        }

        return $this->sendResponse($customerOTP->toArray(), 'Customer O T P retrieved successfully');
    }

    /**
     * Update the specified CustomerOTP in storage.
     * PUT/PATCH /customer-o-t-ps/{id}
     */
    public function update($id, UpdateCustomerOTPAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var CustomerOTP $customerOTP */
        $customerOTP = $this->customerOTPRepository->find($id);

        if (empty($customerOTP)) {
            return $this->sendError('Customer O T P not found');
        }

        $customerOTP = $this->customerOTPRepository->update($input, $id);

        return $this->sendResponse($customerOTP->toArray(), 'CustomerOTP updated successfully');
    }

    /**
     * Remove the specified CustomerOTP from storage.
     * DELETE /customer-o-t-ps/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var CustomerOTP $customerOTP */
        $customerOTP = $this->customerOTPRepository->find($id);

        if (empty($customerOTP)) {
            return $this->sendError('Customer O T P not found');
        }

        $customerOTP->delete();

        return $this->sendSuccess('Customer O T P deleted successfully');
    }
}
