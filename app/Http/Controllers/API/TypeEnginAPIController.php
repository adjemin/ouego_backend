<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateTypeEnginAPIRequest;
use App\Http\Requests\API\UpdateTypeEnginAPIRequest;
use Illuminate\Support\Str;
use App\Models\TypeEngin;
use App\Repositories\TypeEnginRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class TypeEnginAPIController
 */
class TypeEnginAPIController extends AppBaseController
{
    private TypeEnginRepository $typeEnginRepository;

    public function __construct(TypeEnginRepository $typeEnginRepo)
    {
        $this->typeEnginRepository = $typeEnginRepo;
    }

    /**
     * Display a listing of the TypeEngins.
     * GET|HEAD /type-engins
     */
    public function index(Request $request): JsonResponse
    {
        $typeEngins = $this->typeEnginRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($typeEngins->toArray(), 'Type Engins retrieved successfully');
    }

    /**
     * Store a newly created TypeEngin in storage.
     * POST /type-engins
     */
    public function store(CreateTypeEnginAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $input['slug'] = Str::slug($input['name']);

        $typeEngin = TypeEngin::where('slug', $input['slug'])->first();
        if($typeEngin != null){
            return $this->sendError('This typeEngin already exist', 400);
        }

        $typeEngin = $this->typeEnginRepository->create($input);

        return $this->sendResponse($typeEngin->toArray(), 'Type Engin saved successfully');
    }

    /**
     * Display the specified TypeEngin.
     * GET|HEAD /type-engins/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var TypeEngin $typeEngin */
        $typeEngin = $this->typeEnginRepository->find($id);

        if (empty($typeEngin)) {
            return $this->sendError('Type Engin not found');
        }

        return $this->sendResponse($typeEngin->toArray(), 'Type Engin retrieved successfully');
    }

    /**
     * Update the specified TypeEngin in storage.
     * PUT/PATCH /type-engins/{id}
     */
    public function update($id, UpdateTypeEnginAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var TypeEngin $typeEngin */
        $typeEngin = $this->typeEnginRepository->find($id);

        if (empty($typeEngin)) {
            return $this->sendError('Type Engin not found');
        }

        $typeEngin = $this->typeEnginRepository->update($input, $id);

        return $this->sendResponse($typeEngin->toArray(), 'TypeEngin updated successfully');
    }

    /**
     * Remove the specified TypeEngin from storage.
     * DELETE /type-engins/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var TypeEngin $typeEngin */
        $typeEngin = $this->typeEnginRepository->find($id);

        if (empty($typeEngin)) {
            return $this->sendError('Type Engin not found');
        }

        $typeEngin->delete();

        return $this->sendSuccess('Type Engin deleted successfully');
    }
}
