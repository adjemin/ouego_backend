<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateEnginAPIRequest;
use App\Http\Requests\API\UpdateEnginAPIRequest;
use App\Models\Engin;
use App\Repositories\EnginRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class EnginAPIController
 */
class EnginAPIController extends AppBaseController
{
    private EnginRepository $enginRepository;

    public function __construct(EnginRepository $enginRepo)
    {
        $this->enginRepository = $enginRepo;
    }

    /**
     * Display a listing of the Engins.
     * GET|HEAD /engins
     */
    public function index(Request $request): JsonResponse
    {
        $engins = $this->enginRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($engins->toArray(), 'Engins retrieved successfully');
    }

    /**
     * Store a newly created Engin in storage.
     * POST /engins
     */
    public function store(CreateEnginAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $engin = $this->enginRepository->create($input);

        return $this->sendResponse($engin->toArray(), 'Engin saved successfully');
    }

    /**
     * Display the specified Engin.
     * GET|HEAD /engins/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Engin $engin */
        $engin = $this->enginRepository->find($id);

        if (empty($engin)) {
            return $this->sendError('Engin not found');
        }

        return $this->sendResponse($engin->toArray(), 'Engin retrieved successfully');
    }

    /**
     * Update the specified Engin in storage.
     * PUT/PATCH /engins/{id}
     */
    public function update($id, UpdateEnginAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Engin $engin */
        $engin = $this->enginRepository->find($id);

        if (empty($engin)) {
            return $this->sendError('Engin not found');
        }

        $engin = $this->enginRepository->update($input, $id);

        return $this->sendResponse($engin->toArray(), 'Engin updated successfully');
    }

    /**
     * Remove the specified Engin from storage.
     * DELETE /engins/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Engin $engin */
        $engin = $this->enginRepository->find($id);

        if (empty($engin)) {
            return $this->sendError('Engin not found');
        }

        $engin->delete();

        return $this->sendSuccess('Engin deleted successfully');
    }
}
