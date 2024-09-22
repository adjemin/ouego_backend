<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateDriverNotificationAPIRequest;
use App\Http\Requests\API\UpdateDriverNotificationAPIRequest;
use App\Models\DriverNotification;
use App\Repositories\DriverNotificationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Utilities\DriverNotificationsUtils;

/**
 * Class DriverNotificationAPIController
 */
class DriverNotificationAPIController extends AppBaseController
{
    private DriverNotificationRepository $driverNotificationRepository;

    public function __construct(DriverNotificationRepository $driverNotificationRepo)
    {
        $this->driverNotificationRepository = $driverNotificationRepo;
    }

    /**
     * Display a listing of the DriverNotification.
     * GET|HEAD /driver-notifications
     */
    public function index(Request $request): JsonResponse
    {
        $driverNotifications = $this->driverNotificationRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($driverNotifications->toArray(), 'Driver Notifications retrieved successfully');
    }

    /**
     * Store a newly created DriverNotification in storage.
     * POST /driver-notifications
     */
    public function store(CreateDriverNotificationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $driverNotifications = $this->driverNotificationRepository->create($input);

        return $this->sendResponse($driverNotifications->toArray(), 'Driver Notifications saved successfully');
    }

    /**
     * Display the specified DriverNotification.
     * GET|HEAD /driver-notifications/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var DriverNotification $driverNotifications */
        $driverNotifications = $this->driverNotificationRepository->find($id);

        if (empty($driverNotifications)) {
            return $this->sendError('Driver Notifications not found');
        }

        return $this->sendResponse($driverNotifications->toArray(), 'Driver Notifications retrieved successfully');
    }

    /**
     * Update the specified DriverNotification in storage.
     * PUT/PATCH /driver-notifications/{id}
     */
    public function update($id, UpdateDriverNotificationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var DriverNotification $driverNotifications */
        $driverNotifications = $this->driverNotificationRepository->find($id);

        if (empty($driverNotifications)) {
            return $this->sendError('Driver Notifications not found');
        }

        $driverNotifications = $this->driverNotificationRepository->update($input, $id);

        return $this->sendResponse($driverNotifications->toArray(), 'DriverNotification updated successfully');
    }

    /**
     * Remove the specified DriverNotification from storage.
     * DELETE /driver-notifications/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var DriverNotification $driverNotifications */
        $driverNotifications = $this->driverNotificationRepository->find($id);

        if (empty($driverNotifications)) {
            return $this->sendError('Driver Notifications not found');
        }

        $driverNotifications->delete();

        return $this->sendSuccess('Driver Notifications deleted successfully');
    }

        /**
     * Soumet une notification de test au driver.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function submitTestNotification($id, Request $request): JsonResponse
    {
        try {
            // Récupérer la notification avec l'ID 100
            $notification = DriverNotification::findOrFail($id);

            // Envoyer la notification au driver
            $data = DriverNotificationsUtils::notify($notification);

            // Retourner une réponse de succès
            return response()->json([
                'success' => true,
                'message' => 'Notification de test envoyée avec succès.',
                'notification_id' => $notification->id,
                "data" => $data
            ], 200);

        } catch (\Exception $e) {
            // En cas d'erreur, retourner une réponse d'erreur
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de la notification de test.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
