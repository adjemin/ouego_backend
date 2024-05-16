<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateEnginPictureAPIRequest;
use App\Http\Requests\API\UpdateEnginPictureAPIRequest;
use App\Models\EnginPicture;
use App\Repositories\EnginPictureRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class EnginPictureAPIController
 */
class EnginPictureAPIController extends AppBaseController
{
    private EnginPictureRepository $enginPictureRepository;

    public function __construct(EnginPictureRepository $enginPictureRepo)
    {
        $this->enginPictureRepository = $enginPictureRepo;
    }

    /**
     * Display a listing of the EnginPictures.
     * GET|HEAD /engin-pictures
     */
    public function index(Request $request): JsonResponse
    {
        $enginPictures = $this->enginPictureRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($enginPictures->toArray(), 'Engin Pictures retrieved successfully');
    }

    /**
     * Store a newly created EnginPicture in storage.
     * POST /engin-pictures
     */
    public function store(CreateEnginPictureAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $enginPicture = $this->enginPictureRepository->create($input);

        return $this->sendResponse($enginPicture->toArray(), 'Engin Picture saved successfully');
    }

    /**
     * Display the specified EnginPicture.
     * GET|HEAD /engin-pictures/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var EnginPicture $enginPicture */
        $enginPicture = $this->enginPictureRepository->find($id);

        if (empty($enginPicture)) {
            return $this->sendError('Engin Picture not found');
        }

        return $this->sendResponse($enginPicture->toArray(), 'Engin Picture retrieved successfully');
    }

    /**
     * Update the specified EnginPicture in storage.
     * PUT/PATCH /engin-pictures/{id}
     */
    public function update($id, UpdateEnginPictureAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var EnginPicture $enginPicture */
        $enginPicture = $this->enginPictureRepository->find($id);

        if (empty($enginPicture)) {
            return $this->sendError('Engin Picture not found');
        }

        $enginPicture = $this->enginPictureRepository->update($input, $id);

        return $this->sendResponse($enginPicture->toArray(), 'EnginPicture updated successfully');
    }

    /**
     * Remove the specified EnginPicture from storage.
     * DELETE /engin-pictures/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var EnginPicture $enginPicture */
        $enginPicture = $this->enginPictureRepository->find($id);

        if (empty($enginPicture)) {
            return $this->sendError('Engin Picture not found');
        }

        $enginPicture->delete();

        return $this->sendSuccess('Engin Picture deleted successfully');
    }
}
