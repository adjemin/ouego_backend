<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateProductTypeAPIRequest;
use App\Http\Requests\API\UpdateProductTypeAPIRequest;
use App\Models\ProductType;
use App\Repositories\ProductTypeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Str;

/**
 * Class ProductTypeAPIController
 */
class ProductTypeAPIController extends AppBaseController
{
    private ProductTypeRepository $productTypeRepository;

    public function __construct(ProductTypeRepository $productTypeRepo)
    {
        $this->productTypeRepository = $productTypeRepo;
    }

    /**
     * Display a listing of the ProductTypes.
     * GET|HEAD /product-types
     */
    public function index(Request $request): JsonResponse
    {
        $productTypes = $this->productTypeRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($productTypes->toArray(), 'Product Types retrieved successfully');
    }

    /**
     * Store a newly created ProductType in storage.
     * POST /product-types
     */
    public function store(CreateProductTypeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if(!array_key_exists('name', $input)){
            return $this->sendError('name is required', 400);
        }

        $input['slug'] = Str::slug($input['name']);

        $productType = ProductType::where('slug', $input['slug'])->first();
        if($productType != null){
            return $this->sendError('This product_type already exist', 400);
        }

        $productType = $this->productTypeRepository->create($input);

        return $this->sendResponse($productType->toArray(), 'Product Type saved successfully');
    }

    /**
     * Display the specified ProductType.
     * GET|HEAD /product-types/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var ProductType $productType */
        $productType = $this->productTypeRepository->find($id);

        if (empty($productType)) {
            return $this->sendError('Product Type not found');
        }

        return $this->sendResponse($productType->toArray(), 'Product Type retrieved successfully');
    }

    /**
     * Update the specified ProductType in storage.
     * PUT/PATCH /product-types/{id}
     */
    public function update($id, UpdateProductTypeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var ProductType $productType */
        $productType = $this->productTypeRepository->find($id);

        if (empty($productType)) {
            return $this->sendError('Product Type not found');
        }

        if(array_key_exists('name', $input)){

            $input['slug'] = Str::slug($input['name']);

            $productType = ProductType::where('slug', $input['slug'])->first();
            if($productType != null){
                return $this->sendError('This product_type already exist', 400);
            }

        }

        $productType = $this->productTypeRepository->update($input, $id);

        return $this->sendResponse($productType->toArray(), 'ProductType updated successfully');
    }

    /**
     * Remove the specified ProductType from storage.
     * DELETE /product-types/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var ProductType $productType */
        $productType = $this->productTypeRepository->find($id);

        if (empty($productType)) {
            return $this->sendError('Product Type not found');
        }

        $productType->delete();

        return $this->sendSuccess('Product Type deleted successfully');
    }
}
