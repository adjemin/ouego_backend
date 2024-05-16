<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateServiceAPIRequest;
use App\Http\Requests\API\UpdateServiceAPIRequest;
use App\Models\Service;
use App\Repositories\ServiceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class ServiceAPIController
 */
class ServiceAPIController extends AppBaseController
{
    private ServiceRepository $serviceRepository;

    public function __construct(ServiceRepository $serviceRepo)
    {
        $this->serviceRepository = $serviceRepo;
    }

    /**
     * Display a listing of the Services.
     * GET|HEAD /services
     */
    public function index(Request $request): JsonResponse
    {
        $services = $this->serviceRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($services->toArray(), 'Services retrieved successfully');
    }

    /**
     * Store a newly created Service in storage.
     * POST /services
     */
    public function store(CreateServiceAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $service = $this->serviceRepository->create($input);

        return $this->sendResponse($service->toArray(), 'Service saved successfully');
    }

    /**
     * Display the specified Service.
     * GET|HEAD /services/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Service $service */
        $service = $this->serviceRepository->find($id);

        if (empty($service)) {
            return $this->sendError('Service not found');
        }

        return $this->sendResponse($service->toArray(), 'Service retrieved successfully');
    }

    /**
     * Update the specified Service in storage.
     * PUT/PATCH /services/{id}
     */
    public function update($id, UpdateServiceAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Service $service */
        $service = $this->serviceRepository->find($id);

        if (empty($service)) {
            return $this->sendError('Service not found');
        }

        $service = $this->serviceRepository->update($input, $id);

        return $this->sendResponse($service->toArray(), 'Service updated successfully');
    }

    /**
     * Remove the specified Service from storage.
     * DELETE /services/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Service $service */
        $service = $this->serviceRepository->find($id);

        if (empty($service)) {
            return $this->sendError('Service not found');
        }

        $service->delete();

        return $this->sendSuccess('Service deleted successfully');
    }
}
