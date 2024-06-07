<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateRoutePointHistoryAPIRequest;
use App\Http\Requests\API\UpdateRoutePointHistoryAPIRequest;
use App\Models\RoutePointHistory;
use App\Repositories\RoutePointHistoryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class RoutePointHistoryAPIController
 */
class RoutePointHistoryAPIController extends AppBaseController
{
    private RoutePointHistoryRepository $routePointHistoryRepository;

    public function __construct(RoutePointHistoryRepository $routePointHistoryRepo)
    {
        $this->routePointHistoryRepository = $routePointHistoryRepo;
    }

    /**
     * Display a listing of the RoutePointHistories.
     * GET|HEAD /route-point-histories
     */
    public function index(Request $request): JsonResponse
    {
        $routePointHistories = $this->routePointHistoryRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($routePointHistories->toArray(), 'Route Point Histories retrieved successfully');
    }

    /**
     * Store a newly created RoutePointHistory in storage.
     * POST /route-point-histories
     */
    public function store(CreateRoutePointHistoryAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $routePointHistory = $this->routePointHistoryRepository->create($input);

        return $this->sendResponse($routePointHistory->toArray(), 'Route Point History saved successfully');
    }

    /**
     * Display the specified RoutePointHistory.
     * GET|HEAD /route-point-histories/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var RoutePointHistory $routePointHistory */
        $routePointHistory = $this->routePointHistoryRepository->find($id);

        if (empty($routePointHistory)) {
            return $this->sendError('Route Point History not found');
        }

        return $this->sendResponse($routePointHistory->toArray(), 'Route Point History retrieved successfully');
    }

    /**
     * Update the specified RoutePointHistory in storage.
     * PUT/PATCH /route-point-histories/{id}
     */
    public function update($id, UpdateRoutePointHistoryAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var RoutePointHistory $routePointHistory */
        $routePointHistory = $this->routePointHistoryRepository->find($id);

        if (empty($routePointHistory)) {
            return $this->sendError('Route Point History not found');
        }

        $routePointHistory = $this->routePointHistoryRepository->update($input, $id);

        return $this->sendResponse($routePointHistory->toArray(), 'RoutePointHistory updated successfully');
    }

    /**
     * Remove the specified RoutePointHistory from storage.
     * DELETE /route-point-histories/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var RoutePointHistory $routePointHistory */
        $routePointHistory = $this->routePointHistoryRepository->find($id);

        if (empty($routePointHistory)) {
            return $this->sendError('Route Point History not found');
        }

        $routePointHistory->delete();

        return $this->sendSuccess('Route Point History deleted successfully');
    }
}
