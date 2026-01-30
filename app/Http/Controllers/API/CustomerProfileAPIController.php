<?php

namespace App\Http\Controllers\API;

use App\Models\CustomerProfile;
use App\Repositories\CustomerProfileRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class CustomerProfileAPIController
 */
class CustomerProfileAPIController extends AppBaseController
{
    private CustomerProfileRepository $customerProfileRepository;

    public function __construct(CustomerProfileRepository $customerProfileRepo)
    {
        $this->customerProfileRepository = $customerProfileRepo;
    }

    /**
     * Display a listing of the CustomerProfiles.
     * GET|HEAD /customer-profiles
     */
    public function index(Request $request): JsonResponse
    {
        $customerProfiles = CustomerProfile::all();

        return $this->sendResponse($customerProfiles->toArray(), 'Customer Profiles retrieved successfully');
    }

    /**
     * Store a newly created CustomerProfile in storage.
     * POST /customer-profiles
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(CustomerProfile::$rules);

        $input = $request->all();

        $customerProfile = $this->customerProfileRepository->create($input);

        return $this->sendResponse($customerProfile->toArray(), 'Customer Profile saved successfully');
    }

    /**
     * Display the specified CustomerProfile.
     * GET|HEAD /customer-profiles/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var CustomerProfile $customerProfile */
        $customerProfile = $this->customerProfileRepository->find($id);

        if (empty($customerProfile)) {
            return $this->sendError('Customer Profile not found');
        }

        return $this->sendResponse($customerProfile->toArray(), 'Customer Profile retrieved successfully');
    }

    /**
     * Update the specified CustomerProfile in storage.
     * PUT/PATCH /customer-profiles/{id}
     */
    public function update($id, Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $input = $request->all();

        /** @var CustomerProfile $customerProfile */
        $customerProfile = $this->customerProfileRepository->find($id);

        if (empty($customerProfile)) {
            return $this->sendError('Customer Profile not found');
        }

        $customerProfile = $this->customerProfileRepository->update($input, $id);

        return $this->sendResponse($customerProfile->toArray(), 'Customer Profile updated successfully');
    }

    /**
     * Remove the specified CustomerProfile from storage.
     * DELETE /customer-profiles/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var CustomerProfile $customerProfile */
        $customerProfile = $this->customerProfileRepository->find($id);

        if (empty($customerProfile)) {
            return $this->sendError('Customer Profile not found');
        }

        $customerProfile->delete();

        return $this->sendSuccess('Customer Profile deleted successfully');
    }
}
