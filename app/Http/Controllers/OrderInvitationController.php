<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderInvitationRequest;
use App\Http\Requests\UpdateOrderInvitationRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\OrderInvitationRepository;
use Illuminate\Http\Request;
use Flash;

class OrderInvitationController extends AppBaseController
{
    /** @var OrderInvitationRepository $orderInvitationRepository*/
    private $orderInvitationRepository;

    public function __construct(OrderInvitationRepository $orderInvitationRepo)
    {
        $this->orderInvitationRepository = $orderInvitationRepo;
    }

    /**
     * Display a listing of the OrderInvitation.
     */
    public function index(Request $request)
    {
        $orderInvitations = $this->orderInvitationRepository->paginate(10);

        return view('order_invitations.index')
            ->with('orderInvitations', $orderInvitations);
    }

    /**
     * Show the form for creating a new OrderInvitation.
     */
    public function create()
    {
        return view('order_invitations.create');
    }

    /**
     * Store a newly created OrderInvitation in storage.
     */
    public function store(CreateOrderInvitationRequest $request)
    {
        $input = $request->all();

        $orderInvitation = $this->orderInvitationRepository->create($input);

        Flash::success('Order Invitation saved successfully.');

        return redirect(route('orderInvitations.index'));
    }

    /**
     * Display the specified OrderInvitation.
     */
    public function show($id)
    {
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            Flash::error('Order Invitation not found');

            return redirect(route('orderInvitations.index'));
        }

        return view('order_invitations.show')->with('orderInvitation', $orderInvitation);
    }

    /**
     * Show the form for editing the specified OrderInvitation.
     */
    public function edit($id)
    {
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            Flash::error('Order Invitation not found');

            return redirect(route('orderInvitations.index'));
        }

        return view('order_invitations.edit')->with('orderInvitation', $orderInvitation);
    }

    /**
     * Update the specified OrderInvitation in storage.
     */
    public function update($id, UpdateOrderInvitationRequest $request)
    {
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            Flash::error('Order Invitation not found');

            return redirect(route('orderInvitations.index'));
        }

        $orderInvitation = $this->orderInvitationRepository->update($request->all(), $id);

        Flash::success('Order Invitation updated successfully.');

        return redirect(route('orderInvitations.index'));
    }

    /**
     * Remove the specified OrderInvitation from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            Flash::error('Order Invitation not found');

            return redirect(route('orderInvitations.index'));
        }

        $this->orderInvitationRepository->delete($id);

        Flash::success('Order Invitation deleted successfully.');

        return redirect(route('orderInvitations.index'));
    }
}
