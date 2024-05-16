<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductTypeRequest;
use App\Http\Requests\UpdateProductTypeRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\ProductTypeRepository;
use Illuminate\Http\Request;
use Flash;

class ProductTypeController extends AppBaseController
{
    /** @var ProductTypeRepository $productTypeRepository*/
    private $productTypeRepository;

    public function __construct(ProductTypeRepository $productTypeRepo)
    {
        $this->productTypeRepository = $productTypeRepo;
    }

    /**
     * Display a listing of the ProductType.
     */
    public function index(Request $request)
    {
        $productTypes = $this->productTypeRepository->paginate(10);

        return view('product_types.index')
            ->with('productTypes', $productTypes);
    }

    /**
     * Show the form for creating a new ProductType.
     */
    public function create()
    {
        return view('product_types.create');
    }

    /**
     * Store a newly created ProductType in storage.
     */
    public function store(CreateProductTypeRequest $request)
    {
        $input = $request->all();

        $productType = $this->productTypeRepository->create($input);

        Flash::success('Product Type saved successfully.');

        return redirect(route('productTypes.index'));
    }

    /**
     * Display the specified ProductType.
     */
    public function show($id)
    {
        $productType = $this->productTypeRepository->find($id);

        if (empty($productType)) {
            Flash::error('Product Type not found');

            return redirect(route('productTypes.index'));
        }

        return view('product_types.show')->with('productType', $productType);
    }

    /**
     * Show the form for editing the specified ProductType.
     */
    public function edit($id)
    {
        $productType = $this->productTypeRepository->find($id);

        if (empty($productType)) {
            Flash::error('Product Type not found');

            return redirect(route('productTypes.index'));
        }

        return view('product_types.edit')->with('productType', $productType);
    }

    /**
     * Update the specified ProductType in storage.
     */
    public function update($id, UpdateProductTypeRequest $request)
    {
        $productType = $this->productTypeRepository->find($id);

        if (empty($productType)) {
            Flash::error('Product Type not found');

            return redirect(route('productTypes.index'));
        }

        $productType = $this->productTypeRepository->update($request->all(), $id);

        Flash::success('Product Type updated successfully.');

        return redirect(route('productTypes.index'));
    }

    /**
     * Remove the specified ProductType from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $productType = $this->productTypeRepository->find($id);

        if (empty($productType)) {
            Flash::error('Product Type not found');

            return redirect(route('productTypes.index'));
        }

        $this->productTypeRepository->delete($id);

        Flash::success('Product Type deleted successfully.');

        return redirect(route('productTypes.index'));
    }
}
