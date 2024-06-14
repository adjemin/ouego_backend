<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateRoutePointAPIRequest;
use App\Http\Requests\API\UpdateRoutePointAPIRequest;
use App\Models\RoutePoint;
use App\Repositories\RoutePointRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class RoutePointAPIController
 */
class RoutePointAPIController extends AppBaseController
{
    private RoutePointRepository $routePointRepository;

    public function __construct(RoutePointRepository $routePointRepo)
    {
        $this->routePointRepository = $routePointRepo;
    }

    /**
     * Display a listing of the RoutePoints.
     * GET|HEAD /route-points
     */
    public function index(Request $request): JsonResponse
    {
        $routePoints = $this->routePointRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($routePoints->toArray(), 'Route Points retrieved successfully');
    }

    /**
     * Store a newly created RoutePoint in storage.
     * POST /route-points
     */
    public function store(CreateRoutePointAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $routePoint = $this->routePointRepository->create($input);

        return $this->sendResponse($routePoint->toArray(), 'Route Point saved successfully');
    }

    /**
     * Display the specified RoutePoint.
     * GET|HEAD /route-points/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var RoutePoint $routePoint */
        $routePoint = $this->routePointRepository->find($id);

        if (empty($routePoint)) {
            return $this->sendError('Route Point not found');
        }

        return $this->sendResponse($routePoint->toArray(), 'Route Point retrieved successfully');
    }

    /**
     * Update the specified RoutePoint in storage.
     * PUT/PATCH /route-points/{id}
     */
    public function update($id, UpdateRoutePointAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var RoutePoint $routePoint */
        $routePoint = $this->routePointRepository->find($id);

        if (empty($routePoint)) {
            return $this->sendError('Route Point not found');
        }

        $routePoint = $this->routePointRepository->update($input, $id);

        return $this->sendResponse($routePoint->toArray(), 'RoutePoint updated successfully');
    }

    /**
     * Remove the specified RoutePoint from storage.
     * DELETE /route-points/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var RoutePoint $routePoint */
        $routePoint = $this->routePointRepository->find($id);

        if (empty($routePoint)) {
            return $this->sendError('Route Point not found');
        }

        $routePoint->delete();

        return $this->sendSuccess('Route Point deleted successfully');
    }

    //TODO
    public function updateStatus(Request $request){

    }
}
