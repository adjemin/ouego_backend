<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateSlideAPIRequest;
use App\Http\Requests\API\UpdateSlideAPIRequest;
use App\Models\Slide;
use App\Repositories\SlideRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class SlideAPIController
 */
class SlideAPIController extends AppBaseController
{
    private SlideRepository $slideRepository;

    public function __construct(SlideRepository $slideRepo)
    {
        $this->slideRepository = $slideRepo;
    }

    /**
     * Display a listing of the Slides.
     * GET|HEAD /slides
     */
    public function index(Request $request): JsonResponse
    {
        $slides = $this->slideRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($slides->toArray(), 'Slides retrieved successfully');
    }

    /**
     * Store a newly created Slide in storage.
     * POST /slides
     */
    public function store(CreateSlideAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $slide = $this->slideRepository->create($input);

        return $this->sendResponse($slide->toArray(), 'Slide saved successfully');
    }

    /**
     * Display the specified Slide.
     * GET|HEAD /slides/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Slide $slide */
        $slide = $this->slideRepository->find($id);

        if (empty($slide)) {
            return $this->sendError('Slide not found');
        }

        return $this->sendResponse($slide->toArray(), 'Slide retrieved successfully');
    }

    /**
     * Update the specified Slide in storage.
     * PUT/PATCH /slides/{id}
     */
    public function update($id, UpdateSlideAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Slide $slide */
        $slide = $this->slideRepository->find($id);

        if (empty($slide)) {
            return $this->sendError('Slide not found');
        }

        $slide = $this->slideRepository->update($input, $id);

        return $this->sendResponse($slide->toArray(), 'Slide updated successfully');
    }

    /**
     * Remove the specified Slide from storage.
     * DELETE /slides/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Slide $slide */
        $slide = $this->slideRepository->find($id);

        if (empty($slide)) {
            return $this->sendError('Slide not found');
        }

        $slide->delete();

        return $this->sendSuccess('Slide deleted successfully');
    }
}
