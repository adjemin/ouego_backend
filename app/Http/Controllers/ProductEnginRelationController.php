<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductEnginRelationRequest;
use App\Http\Requests\UpdateProductEnginRelationRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\ProductEnginRelationRepository;
use Illuminate\Http\Request;
use Flash;

class ProductEnginRelationController extends AppBaseController
{
    /** @var ProductEnginRelationRepository $productEnginRelationRepository*/
    private $productEnginRelationRepository;

    public function __construct(ProductEnginRelationRepository $productEnginRelationRepo)
    {
        $this->productEnginRelationRepository = $productEnginRelationRepo;
    }

    /**
     * Display a listing of the ProductEnginRelation.
     */
    public function index(Request $request)
    {
        $productEnginRelations = $this->productEnginRelationRepository->paginate(10);

        return view('product_engin_relations.index')
            ->with('productEnginRelations', $productEnginRelations);
    }

    /**
     * Show the form for creating a new ProductEnginRelation.
     */
    public function create()
    {
        return view('product_engin_relations.create');
    }

    /**
     * Store a newly created ProductEnginRelation in storage.
     */
    public function store(CreateProductEnginRelationRequest $request)
    {
        $input = $request->all();

        $productEnginRelation = $this->productEnginRelationRepository->create($input);

        Flash::success('Product Engin Relation saved successfully.');

        return redirect(route('productEnginRelations.index'));
    }

    /**
     * Display the specified ProductEnginRelation.
     */
    public function show($id)
    {
        $productEnginRelation = $this->productEnginRelationRepository->find($id);

        if (empty($productEnginRelation)) {
            Flash::error('Product Engin Relation not found');

            return redirect(route('productEnginRelations.index'));
        }

        return view('product_engin_relations.show')->with('productEnginRelation', $productEnginRelation);
    }

    /**
     * Show the form for editing the specified ProductEnginRelation.
     */
    public function edit($id)
    {
        $productEnginRelation = $this->productEnginRelationRepository->find($id);

        if (empty($productEnginRelation)) {
            Flash::error('Product Engin Relation not found');

            return redirect(route('productEnginRelations.index'));
        }

        return view('product_engin_relations.edit')->with('productEnginRelation', $productEnginRelation);
    }

    /**
     * Update the specified ProductEnginRelation in storage.
     */
    public function update($id, UpdateProductEnginRelationRequest $request)
    {
        $productEnginRelation = $this->productEnginRelationRepository->find($id);

        if (empty($productEnginRelation)) {
            Flash::error('Product Engin Relation not found');

            return redirect(route('productEnginRelations.index'));
        }

        $productEnginRelation = $this->productEnginRelationRepository->update($request->all(), $id);

        Flash::success('Product Engin Relation updated successfully.');

        return redirect(route('productEnginRelations.index'));
    }

    /**
     * Remove the specified ProductEnginRelation from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $productEnginRelation = $this->productEnginRelationRepository->find($id);

        if (empty($productEnginRelation)) {
            Flash::error('Product Engin Relation not found');

            return redirect(route('productEnginRelations.index'));
        }

        $this->productEnginRelationRepository->delete($id);

        Flash::success('Product Engin Relation deleted successfully.');

        return redirect(route('productEnginRelations.index'));
    }
}
