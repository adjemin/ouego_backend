<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateCarrierAPIRequest;
use App\Http\Requests\API\UpdateCarrierAPIRequest;
use App\Models\Carrier;
use App\Models\Driver;
use App\Models\DriverCarrier;
use App\Repositories\CarrierRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Facades\Auth;

/**
 * Class CarrierAPIController
 */
class CarrierAPIController extends AppBaseController
{
    private CarrierRepository $carrierRepository;

    public function __construct(CarrierRepository $carrierRepo)
    {
        $this->carrierRepository = $carrierRepo;
    }

     /**
     * Liste des carriers d'un driver
     * GET /api/driver/carriers
     *
     * @return JsonResponse
     */
    public function driverCarriers(): JsonResponse
    {
        // Récupérer le driver connecté
        $driver = Auth::guard('api-drivers')->user();

        // Récupérer les carriers associés au driver
        $carriers = $driver->carriers()->get();

        return $this->sendResponse($carriers->toArray(), 'Carrières du chauffeur récupérées avec succès');
    }

    /**
     * Un driver ajoute une carrière à ses carrières
     * POST /api/driver/carriers/{carrierId}
     *
     * @param int $carrierId
     * @return JsonResponse
     */
    public function addCarrierToDriver(int $carrierId): JsonResponse
    {
        // Récupérer le driver connecté
        $driver = Auth::guard('api-drivers')->user();

        // Vérifier si la carrière existe
        $carrier = Carrier::find($carrierId);
        if (!$carrier) {
            return $this->sendError('Carrière introuvable');
        }

        // Vérifier si la relation n'existe pas déjà
        if (!$driver->carriers()->where('carrier_id', $carrierId)->exists()) {
            // Ajouter la relation
            $driver->carriers()->attach($carrierId);
            return $this->sendSuccess('Carrière associée au chauffeur avec succès');
        }

        return $this->sendError('Carrière déjà associée à ce chauffeur');
    }

    /**
     * Un driver retire une carrière à ses carrières
     * DELETE /api/driver/carriers/{carrierId}
     *
     * @param int $carrierId
     * @return JsonResponse
     */
    public function removeCarrierFromDriver(int $carrierId): JsonResponse
    {
        // Récupérer le driver connecté
        $driver = Auth::guard('api-drivers')->user();

        // Vérifier si la relation existe
        if ($driver->carriers()->where('carrier_id', $carrierId)->exists()) {
            // Supprimer la relation
            $driver->carriers()->detach($carrierId);
            return $this->sendSuccess('Carrière retirée du chauffeur avec succès');
        }

        return $this->sendError('Carrière non associée à ce chauffeur');
    }

    /**
     * Display a listing of the Carriers.
     * GET|HEAD /carriers
     */
    public function index(Request $request): JsonResponse
    {
        $carriers = $this->carrierRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($carriers->toArray(), 'Carrières récupérées avec succès');
    }

    /**
     * Rechercher d'une carrière par nom
     * GET /api/carriers/search
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        // Valider la requête
        $request->validate([
            'name' => 'required|string|min:2'
        ]);

        // Rechercher les carriers par nom
        $carriers = Carrier::where('name', 'LIKE', '%' . $request->name . '%')->get();

        return $this->sendResponse($carriers->toArray(), 'Résultats de la recherche de carrières');
    }

    /**
     * Store a newly created Carrier in storage.
     * POST /carriers
     */
    public function store(CreateCarrierAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $carrier = $this->carrierRepository->create($input);

        $carrier->location  = [ $carrier->location_latitude,$carrier->location_longitude];
        $carrier->save();

        return $this->sendResponse($carrier, 'Carrière enregistrée avec succès');
    }

    /**
     * Display the specified Carrier.
     * GET|HEAD /carriers/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Carrier $carrier */
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            return $this->sendError('Carrière introuvable');
        }

        return $this->sendResponse($carrier->toArray(), 'Carrière récupérée avec succès');
    }

    /**
     * Update the specified Carrier in storage.
     * PUT/PATCH /carriers/{id}
     */
    public function update($id, UpdateCarrierAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Carrier $carrier */
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            return $this->sendError('Carrière introuvable');
        }

        $carrier = $this->carrierRepository->update($input, $id);

        return $this->sendResponse($carrier->toArray(), 'Carrière mise à jour avec succès');
    }

    /**
     * Remove the specified Carrier from storage.
     * DELETE /carriers/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Carrier $carrier */
        $carrier = $this->carrierRepository->find($id);

        if (empty($carrier)) {
            return $this->sendError('Carrière introuvable');
        }

        $carrier->delete();

        return $this->sendSuccess('Carrière supprimée avec succès');
    }
}
