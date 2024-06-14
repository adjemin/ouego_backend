<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateCustomerNotificationAPIRequest;
use App\Http\Requests\API\UpdateCustomerNotificationAPIRequest;
use App\Models\CustomerNotification;
use App\Repositories\CustomerNotificationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class CustomerNotificationAPIController
 */
class CustomerNotificationAPIController extends AppBaseController
{
    private CustomerNotificationRepository $customerNotificationRepository;

    public function __construct(CustomerNotificationRepository $customerNotificationRepo)
    {
        $this->customerNotificationRepository = $customerNotificationRepo;
    }

    /**
     * Display a listing of the CustomerNotifications.
     * GET|HEAD /customer-notifications
     */
    public function index(Request $request): JsonResponse
    {
        $customerNotifications = $this->customerNotificationRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($customerNotifications->toArray(), 'Customer Notifications retrieved successfully');
    }

    /**
     * Store a newly created CustomerNotification in storage.
     * POST /customer-notifications
     */
    public function store(CreateCustomerNotificationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $customerNotification = $this->customerNotificationRepository->create($input);

        return $this->sendResponse($customerNotification->toArray(), 'Customer Notification saved successfully');
    }

    /**
     * Display the specified CustomerNotification.
     * GET|HEAD /customer-notifications/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var CustomerNotification $customerNotification */
        $customerNotification = $this->customerNotificationRepository->find($id);

        if (empty($customerNotification)) {
            return $this->sendError('Customer Notification not found');
        }

        return $this->sendResponse($customerNotification->toArray(), 'Customer Notification retrieved successfully');
    }

    /**
     * Update the specified CustomerNotification in storage.
     * PUT/PATCH /customer-notifications/{id}
     */
    public function update($id, UpdateCustomerNotificationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var CustomerNotification $customerNotification */
        $customerNotification = $this->customerNotificationRepository->find($id);

        if (empty($customerNotification)) {
            return $this->sendError('Customer Notification not found');
        }

        $customerNotification = $this->customerNotificationRepository->update($input, $id);

        return $this->sendResponse($customerNotification->toArray(), 'CustomerNotification updated successfully');
    }

    /**
     * Remove the specified CustomerNotification from storage.
     * DELETE /customer-notifications/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var CustomerNotification $customerNotification */
        $customerNotification = $this->customerNotificationRepository->find($id);

        if (empty($customerNotification)) {
            return $this->sendError('Customer Notification not found');
        }

        $customerNotification->delete();

        return $this->sendSuccess('Customer Notification deleted successfully');
    }
}
