<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateEnginRequest;
use App\Http\Requests\UpdateEnginRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\EnginRepository;
use Illuminate\Http\Request;
use Flash;

class EnginController extends AppBaseController
{
    /** @var EnginRepository $enginRepository*/
    private $enginRepository;

    public function __construct(EnginRepository $enginRepo)
    {
        $this->enginRepository = $enginRepo;
    }

    /**
     * Display a listing of the Engin.
     */
    public function index(Request $request)
    {
        $engins = $this->enginRepository->paginate(10);

        return view('engins.index')
            ->with('engins', $engins);
    }

    /**
     * Show the form for creating a new Engin.
     */
    public function create()
    {
        return view('engins.create');
    }

    /**
     * Store a newly created Engin in storage.
     */
    public function store(CreateEnginRequest $request)
    {
        $input = $request->all();

        $engin = $this->enginRepository->create($input);

        Flash::success('Engin saved successfully.');

        return redirect(route('engins.index'));
    }

    /**
     * Display the specified Engin.
     */
    public function show($id)
    {
        $engin = $this->enginRepository->find($id);

        if (empty($engin)) {
            Flash::error('Engin not found');

            return redirect(route('engins.index'));
        }

        return view('engins.show')->with('engin', $engin);
    }

    /**
     * Show the form for editing the specified Engin.
     */
    public function edit($id)
    {
        $engin = $this->enginRepository->find($id);

        if (empty($engin)) {
            Flash::error('Engin not found');

            return redirect(route('engins.index'));
        }

        return view('engins.edit')->with('engin', $engin);
    }

    /**
     * Update the specified Engin in storage.
     */
    public function update($id, UpdateEnginRequest $request)
    {
        $engin = $this->enginRepository->find($id);

        if (empty($engin)) {
            Flash::error('Engin not found');

            return redirect(route('engins.index'));
        }

        $engin = $this->enginRepository->update($request->all(), $id);

        Flash::success('Engin updated successfully.');

        return redirect(route('engins.index'));
    }

    /**
     * Remove the specified Engin from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $engin = $this->enginRepository->find($id);

        if (empty($engin)) {
            Flash::error('Engin not found');

            return redirect(route('engins.index'));
        }

        $this->enginRepository->delete($id);

        Flash::success('Engin deleted successfully.');

        return redirect(route('engins.index'));
    }
}
