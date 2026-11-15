<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Informations de contact du support et liens légaux, lues depuis la table settings
 * pour pouvoir être modifiées sans redéploiement.
 */
class SupportAPIController extends AppBaseController
{
    /** Champ de la réponse => clé dans la table settings */
    public const SETTING_KEYS = [
        'email' => 'SUPPORT_EMAIL',
        'phone' => 'SUPPORT_PHONE',
        'whatsapp' => 'SUPPORT_WHATSAPP',
        'privacy_policy_url' => 'PRIVACY_POLICY_URL',
        'terms_of_use_url' => 'TERMS_OF_USE_URL',
    ];

    /**
     * GET /v1/support
     */
    public function index(): JsonResponse
    {
        $data = array_map(
            fn (string $key) => Setting::get($key) ?: null,
            self::SETTING_KEYS
        );

        return $this->sendResponse($data, 'Informations du support récupérées avec succès');
    }
}
