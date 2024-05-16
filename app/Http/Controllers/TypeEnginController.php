<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTypeEnginRequest;
use App\Http\Requests\UpdateTypeEnginRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\TypeEnginRepository;
use Illuminate\Http\Request;
use Flash;

class TypeEnginController extends AppBaseController
{
    /** @var TypeEnginRepository $typeEnginRepository*/
    private $typeEnginRepository;

    public function __construct(TypeEnginRepository $typeEnginRepo)
    {
        $this->typeEnginRepository = $typeEnginRepo;
    }

    /**
     * Display a listing of the TypeEngin.
     */
    public function index(Request $request)
    {
        $typeEngins = $this->typeEnginRepository->paginate(10);

        return view('type_engins.index')
            ->with('typeEngins', $typeEngins);
    }

    /**
     * Show the form for creating a new TypeEngin.
     */
    public function create()
    {
        return view('type_engins.create');
    }

    /**
     * Store a newly created TypeEngin in storage.
     */
    public function store(CreateTypeEnginRequest $request)
    {
        $input = $request->all();

        $typeEngin = $this->typeEnginRepository->create($input);

        Flash::success('Type Engin saved successfully.');

        return redirect(route('typeEngins.index'));
    }

    /**
     * Display the specified TypeEngin.
     */
    public function show($id)
    {
        $typeEngin = $this->typeEnginRepository->find($id);

        if (empty($typeEngin)) {
            Flash::error('Type Engin not found');

            return redirect(route('typeEngins.index'));
        }

        return view('type_engins.show')->with('typeEngin', $typeEngin);
    }

    /**
     * Show the form for editing the specified TypeEngin.
     */
    public function edit($id)
    {
        $typeEngin = $this->typeEnginRepository->find($id);

        if (empty($typeEngin)) {
            Flash::error('Type Engin not found');

            return redirect(route('typeEngins.index'));
        }

        return view('type_engins.edit')->with('typeEngin', $typeEngin);
    }

    /**
     * Update the specified TypeEngin in storage.
     */
    public function update($id, UpdateTypeEnginRequest $request)
    {
        $typeEngin = $this->typeEnginRepository->find($id);

        if (empty($typeEngin)) {
            Flash::error('Type Engin not found');

            return redirect(route('typeEngins.index'));
        }

        $typeEngin = $this->typeEnginRepository->update($request->all(), $id);

        Flash::success('Type Engin updated successfully.');

        return redirect(route('typeEngins.index'));
    }

    /**
     * Remove the specified TypeEngin from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $typeEngin = $this->typeEnginRepository->find($id);

        if (empty($typeEngin)) {
            Flash::error('Type Engin not found');

            return redirect(route('typeEngins.index'));
        }

        $this->typeEnginRepository->delete($id);

        Flash::success('Type Engin deleted successfully.');

        return redirect(route('typeEngins.index'));
    }
}
