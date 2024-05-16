<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateProductEnginRelationAPIRequest;
use App\Http\Requests\API\UpdateProductEnginRelationAPIRequest;
use App\Models\ProductEnginRelation;
use App\Repositories\ProductEnginRelationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class ProductEnginRelationAPIController
 */
class ProductEnginRelationAPIController extends AppBaseController
{
    private ProductEnginRelationRepository $productEnginRelationRepository;

    public function __construct(ProductEnginRelationRepository $productEnginRelationRepo)
    {
        $this->productEnginRelationRepository = $productEnginRelationRepo;
    }

    /**
     * Display a listing of the ProductEnginRelations.
     * GET|HEAD /product-engin-relations
     */
    public function index(Request $request): JsonResponse
    {
        $productEnginRelations = $this->productEnginRelationRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($productEnginRelations->toArray(), 'Product Engin Relations retrieved successfully');
    }

    /**
     * Store a newly created ProductEnginRelation in storage.
     * POST /product-engin-relations
     */
    public function store(CreateProductEnginRelationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $productEnginRelation = $this->productEnginRelationRepository->create($input);

        return $this->sendResponse($productEnginRelation->toArray(), 'Product Engin Relation saved successfully');
    }

    /**
     * Display the specified ProductEnginRelation.
     * GET|HEAD /product-engin-relations/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var ProductEnginRelation $productEnginRelation */
        $productEnginRelation = $this->productEnginRelationRepository->find($id);

        if (empty($productEnginRelation)) {
            return $this->sendError('Product Engin Relation not found');
        }

        return $this->sendResponse($productEnginRelation->toArray(), 'Product Engin Relation retrieved successfully');
    }

    /**
     * Update the specified ProductEnginRelation in storage.
     * PUT/PATCH /product-engin-relations/{id}
     */
    public function update($id, UpdateProductEnginRelationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var ProductEnginRelation $productEnginRelation */
        $productEnginRelation = $this->productEnginRelationRepository->find($id);

        if (empty($productEnginRelation)) {
            return $this->sendError('Product Engin Relation not found');
        }

        $productEnginRelation = $this->productEnginRelationRepository->update($input, $id);

        return $this->sendResponse($productEnginRelation->toArray(), 'ProductEnginRelation updated successfully');
    }

    /**
     * Remove the specified ProductEnginRelation from storage.
     * DELETE /product-engin-relations/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var ProductEnginRelation $productEnginRelation */
        $productEnginRelation = $this->productEnginRelationRepository->find($id);

        if (empty($productEnginRelation)) {
            return $this->sendError('Product Engin Relation not found');
        }

        $productEnginRelation->delete();

        return $this->sendSuccess('Product Engin Relation deleted successfully');
    }
}
