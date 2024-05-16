<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateEnginPictureRequest;
use App\Http\Requests\UpdateEnginPictureRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\EnginPictureRepository;
use Illuminate\Http\Request;
use Flash;

class EnginPictureController extends AppBaseController
{
    /** @var EnginPictureRepository $enginPictureRepository*/
    private $enginPictureRepository;

    public function __construct(EnginPictureRepository $enginPictureRepo)
    {
        $this->enginPictureRepository = $enginPictureRepo;
    }

    /**
     * Display a listing of the EnginPicture.
     */
    public function index(Request $request)
    {
        $enginPictures = $this->enginPictureRepository->paginate(10);

        return view('engin_pictures.index')
            ->with('enginPictures', $enginPictures);
    }

    /**
     * Show the form for creating a new EnginPicture.
     */
    public function create()
    {
        return view('engin_pictures.create');
    }

    /**
     * Store a newly created EnginPicture in storage.
     */
    public function store(CreateEnginPictureRequest $request)
    {
        $input = $request->all();

        $enginPicture = $this->enginPictureRepository->create($input);

        Flash::success('Engin Picture saved successfully.');

        return redirect(route('enginPictures.index'));
    }

    /**
     * Display the specified EnginPicture.
     */
    public function show($id)
    {
        $enginPicture = $this->enginPictureRepository->find($id);

        if (empty($enginPicture)) {
            Flash::error('Engin Picture not found');

            return redirect(route('enginPictures.index'));
        }

        return view('engin_pictures.show')->with('enginPicture', $enginPicture);
    }

    /**
     * Show the form for editing the specified EnginPicture.
     */
    public function edit($id)
    {
        $enginPicture = $this->enginPictureRepository->find($id);

        if (empty($enginPicture)) {
            Flash::error('Engin Picture not found');

            return redirect(route('enginPictures.index'));
        }

        return view('engin_pictures.edit')->with('enginPicture', $enginPicture);
    }

    /**
     * Update the specified EnginPicture in storage.
     */
    public function update($id, UpdateEnginPictureRequest $request)
    {
        $enginPicture = $this->enginPictureRepository->find($id);

        if (empty($enginPicture)) {
            Flash::error('Engin Picture not found');

            return redirect(route('enginPictures.index'));
        }

        $enginPicture = $this->enginPictureRepository->update($request->all(), $id);

        Flash::success('Engin Picture updated successfully.');

        return redirect(route('enginPictures.index'));
    }

    /**
     * Remove the specified EnginPicture from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $enginPicture = $this->enginPictureRepository->find($id);

        if (empty($enginPicture)) {
            Flash::error('Engin Picture not found');

            return redirect(route('enginPictures.index'));
        }

        $this->enginPictureRepository->delete($id);

        Flash::success('Engin Picture deleted successfully.');

        return redirect(route('enginPictures.index'));
    }
}
