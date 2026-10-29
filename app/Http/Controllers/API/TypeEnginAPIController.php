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

        return $this->sendResponse($typeEngins->toArray(), "Types d'engin récupérés avec succès");
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
            return $this->sendError("Ce type d'engin existe déjà", 400);
        }

        $typeEngin = $this->typeEnginRepository->create($input);

        return $this->sendResponse($typeEngin->toArray(), "Type d'engin enregistré avec succès");
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
            return $this->sendError("Type d'engin introuvable");
        }

        return $this->sendResponse($typeEngin->toArray(), "Type d'engin récupéré avec succès");
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
            return $this->sendError("Type d'engin introuvable");
        }

        $typeEngin = $this->typeEnginRepository->update($input, $id);

        return $this->sendResponse($typeEngin->toArray(), "Type d'engin mis à jour avec succès");
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
            return $this->sendError("Type d'engin introuvable");
        }

        $typeEngin->delete();

        return $this->sendSuccess("Type d'engin supprimé avec succès");
    }
}
