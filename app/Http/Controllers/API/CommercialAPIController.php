<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateCommercialAPIRequest;
use App\Http\Requests\API\UpdateCommercialAPIRequest;
use App\Models\Commercial;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\CommercialRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Class CommercialAPIController
 */
class CommercialAPIController extends AppBaseController
{
    private CommercialRepository $commercialRepository;

    public function __construct(CommercialRepository $commercialRepo)
    {
        $this->commercialRepository = $commercialRepo;
    }

    /**
     * Display a listing of the Commercials.
     * GET|HEAD /commercials/list
     */
    public function index(Request $request): JsonResponse
    {
        $commercials = $this->commercialRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($commercials->toArray(), 'Commercials retrieved successfully');
    }

    /**
     * Store a newly created Commercial in storage.
     * POST /commercials/create
     */
    public function store(CreateCommercialAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if (!array_key_exists('code', $input) || empty($input['code'])) {
            return $this->sendError('code is required', 400);
        }

        $existing = Commercial::where('code', $input['code'])->first();
        if ($existing != null) {
            return $this->sendError('A commercial with this code already exists', 400);
        }

        $commercial = $this->commercialRepository->create($input);

        return $this->sendResponse($commercial->toArray(), 'Commercial saved successfully');
    }

    /**
     * Display the specified Commercial.
     * GET|HEAD /commercials/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Commercial $commercial */
        $commercial = $this->commercialRepository->find($id);

        if (empty($commercial)) {
            return $this->sendError('Commercial not found');
        }

        return $this->sendResponse($commercial->toArray(), 'Commercial retrieved successfully');
    }

    /**
     * Find a Commercial by code.
     * GET /commercials/code/{code}
     */
    public function findByCode($code): JsonResponse
    {
        $commercial = Commercial::where('code', $code)->first();

        if (empty($commercial)) {
            return $this->sendError('Commercial not found');
        }

        return $this->sendResponse($commercial->toArray(), 'Commercial retrieved successfully');
    }

    /**
     * Update the specified Commercial in storage.
     * PUT/PATCH /commercials/{id}/update
     */
    public function update($id, UpdateCommercialAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Commercial $commercial */
        $commercial = $this->commercialRepository->find($id);

        if (empty($commercial)) {
            return $this->sendError('Commercial not found');
        }

        $commercial = $this->commercialRepository->update($input, $id);

        return $this->sendResponse($commercial->toArray(), 'Commercial updated successfully');
    }

    /**
     * Remove the specified Commercial from storage.
     * DELETE /commercials/{id}/delete
     */
    public function destroy($id): JsonResponse
    {
        /** @var Commercial $commercial */
        $commercial = $this->commercialRepository->find($id);

        if (empty($commercial)) {
            return $this->sendError('Commercial not found');
        }

        $commercial->delete();

        return $this->sendSuccess('Commercial deleted successfully');
    }
}
