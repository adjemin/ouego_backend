<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateTypeEnginModelAPIRequest;
use App\Http\Requests\API\UpdateTypeEnginModelAPIRequest;
use App\Models\TypeEnginModel;
use App\Repositories\TypeEnginModelRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Str;

/**
 * Class TypeEnginModelAPIController
 */
class TypeEnginModelAPIController extends AppBaseController
{
    private TypeEnginModelRepository $typeEnginModelRepository;

    public function __construct(TypeEnginModelRepository $typeEnginModelRepo)
    {
        $this->typeEnginModelRepository = $typeEnginModelRepo;
    }

    /**
     * Display a listing of the TypeEnginModels.
     * GET|HEAD /type-engin-models
     */
    public function index(Request $request): JsonResponse
    {
        $typeEnginModels = $this->typeEnginModelRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($typeEnginModels->toArray(), 'Type Engin Models retrieved successfully');
    }

    /**
     * Store a newly created TypeEnginModel in storage.
     * POST /type-engin-models
     */
    public function store(CreateTypeEnginModelAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $input['slug'] = '';
        if(array_key_exists('title', $input) && !empty($input['title'])){
            $input['slug'] = Str::slug($input['title']);
        }

        if(array_key_exists('subtitle', $input) && strlen($input['subtitle']) > 0){
            $input['slug'] = $input['slug'].'-'.Str::slug($input['subtitle']);
        }

        if(strlen($input['subtitle']) < 1){
            if(array_key_exists('nombre_roues', $input) && strlen($input['nombre_roues']) > 0){
                $input['slug'] = $input['slug'].'-'.Str::slug($input['nombre_roues']);
            }

            if(array_key_exists('nombre_essieux', $input) && strlen($input['nombre_essieux']) > 0){
                $input['slug'] = $input['slug'].'-'.Str::slug($input['nombre_essieux']);
            }
        }



        $typeEnginModel = TypeEnginModel::where('slug', $input['slug'])->first();

        if($typeEnginModel != null){
            return $this->sendError('This TypeEnginModel already exist', 400);
        }

        $typeEnginModel = $this->typeEnginModelRepository->create($input);

        return $this->sendResponse($typeEnginModel->toArray(), 'Type Engin Model saved successfully');
    }

    /**
     * Display the specified TypeEnginModel.
     * GET|HEAD /type-engin-models/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var TypeEnginModel $typeEnginModel */
        $typeEnginModel = $this->typeEnginModelRepository->find($id);

        if (empty($typeEnginModel)) {
            return $this->sendError('Type Engin Model not found');
        }

        return $this->sendResponse($typeEnginModel->toArray(), 'Type Engin Model retrieved successfully');
    }

    /**
     * Update the specified TypeEnginModel in storage.
     * PUT/PATCH /type-engin-models/{id}
     */
    public function update($id, UpdateTypeEnginModelAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var TypeEnginModel $typeEnginModel */
        $typeEnginModel = $this->typeEnginModelRepository->find($id);

        if (empty($typeEnginModel)) {
            return $this->sendError('Type Engin Model not found');
        }

        $typeEnginModel = $this->typeEnginModelRepository->update($input, $id);

        return $this->sendResponse($typeEnginModel->toArray(), 'TypeEnginModel updated successfully');
    }

    /**
     * Remove the specified TypeEnginModel from storage.
     * DELETE /type-engin-models/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var TypeEnginModel $typeEnginModel */
        $typeEnginModel = $this->typeEnginModelRepository->find($id);

        if (empty($typeEnginModel)) {
            return $this->sendError('Type Engin Model not found');
        }

        $typeEnginModel->delete();

        return $this->sendSuccess('Type Engin Model deleted successfully');
    }
}
