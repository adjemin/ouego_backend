<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateOrderInvitationAPIRequest;
use App\Http\Requests\API\UpdateOrderInvitationAPIRequest;
use App\Models\OrderInvitation;
use App\Repositories\OrderInvitationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class OrderInvitationAPIController
 */
class OrderInvitationAPIController extends AppBaseController
{
    private OrderInvitationRepository $orderInvitationRepository;

    public function __construct(OrderInvitationRepository $orderInvitationRepo)
    {
        $this->orderInvitationRepository = $orderInvitationRepo;
    }

    /**
     * Display a listing of the OrderInvitations.
     * GET|HEAD /order-invitations
     */
    public function index(Request $request): JsonResponse
    {
        $orderInvitations = $this->orderInvitationRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($orderInvitations->toArray(), 'Order Invitations retrieved successfully');
    }

    /**
     * Store a newly created OrderInvitation in storage.
     * POST /order-invitations
     */
    public function store(CreateOrderInvitationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $orderInvitation = $this->orderInvitationRepository->create($input);

        return $this->sendResponse($orderInvitation->toArray(), 'Order Invitation saved successfully');
    }

    /**
     * Display the specified OrderInvitation.
     * GET|HEAD /order-invitations/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Order Invitation not found');
        }

        return $this->sendResponse($orderInvitation->toArray(), 'Order Invitation retrieved successfully');
    }

    /**
     * Update the specified OrderInvitation in storage.
     * PUT/PATCH /order-invitations/{id}
     */
    public function update($id, UpdateOrderInvitationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Order Invitation not found');
        }

        $orderInvitation = $this->orderInvitationRepository->update($input, $id);

        return $this->sendResponse($orderInvitation->toArray(), 'OrderInvitation updated successfully');
    }

    /**
     * Remove the specified OrderInvitation from storage.
     * DELETE /order-invitations/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Order Invitation not found');
        }

        $orderInvitation->delete();

        return $this->sendSuccess('Order Invitation deleted successfully');
    }
}
