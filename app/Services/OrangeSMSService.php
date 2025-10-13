<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

class OrangeSMSService 
{
    private $clientId;
    private $clientSecret;
    private $senderPhoneNumber;
    private $senderName;
    private $baseUrl;
    private $accessToken;
    private $tokenExpiresAt;

    public function __construct()
    {
        $this->clientId = env('SMS_CLIENT_ID', 'dgZ7g3Kko3rkpafn5YDjvnoSF5KQf3h2');
        $this->clientSecret = env('SMS_SECRET', 'lcv6fudZElh7RTwt3uFmTKXsKxjmxEwmsdqJuSRpXgTk');
        $this->senderPhoneNumber = env('SENDER_PHONE_NUMBER', '+2250000');
        $this->senderName = env('SENDER_NAME', 'OUEGO');
        $this->baseUrl = 'https://api.orange.com';
        $this->accessToken = null;
        $this->tokenExpiresAt = null;
    }

    /**
     * Génère un token d'accès OAuth2
     * 
     * @return string Le token d'accès
     * @throws \Exception En cas d'erreur
     */
    public function generateAccessToken()
    {
        try {
            $authString = base64_encode($this->clientId . ':' . $this->clientSecret);
            
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->baseUrl . '/oauth/v3/token',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . $authString,
                    'Content-Type: application/x-www-form-urlencoded'
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            
            curl_close($curl);

            if ($error) {
                throw new \Exception("Erreur cURL lors de la génération du token: " . $error);
            }

            if ($httpCode !== 200 && $httpCode !== 201) {
                throw new \Exception("Erreur HTTP lors de la génération du token: " . $httpCode . " - " . $response);
            }

            $tokenData = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Erreur de décodage JSON: " . json_last_error_msg());
            }

            if (!isset($tokenData['access_token'])) {
                throw new \Exception("Token d'accès non trouvé dans la réponse");
            }

            $this->accessToken = $tokenData['access_token'];
            // Le token expire généralement après 1 heure (3600 secondes)
            $expiresIn = isset($tokenData['expires_in']) ? $tokenData['expires_in'] : 3600;
            $this->tokenExpiresAt = time() + $expiresIn;
            
            return $this->accessToken;

        } catch (\Exception $e) {
            error_log('Erreur lors de la génération du token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Vérifie si le token est valide et le renouvelle si nécessaire
     * 
     * @return string Le token d'accès valide
     */
    public function ensureValidToken()
    {
        if (!$this->accessToken || time() >= $this->tokenExpiresAt) {
            $this->generateAccessToken();
        }
        return $this->accessToken;
    }

    /**
     * Envoie un SMS via l'API Orange
     * 
     * @param string $recipientPhoneNumber Numéro du destinataire (format: +33123456789)
     * @param string $message Message à envoyer
     * @return array Réponse de l'API
     * @throws \Exception En cas d'erreur
     */
    public function sendSMS($recipientPhoneNumber, $message)
    {
        try {
            // S'assurer que le token est valide
            $this->ensureValidToken();

            // Préparer les données du SMS
            $smsData = [
                'outboundSMSMessageRequest' => [
                    'address' => 'tel:' . $recipientPhoneNumber,
                    'senderAddress' => 'tel:' . $this->senderPhoneNumber,
                    "senderName" => $this->senderName,
                    'outboundSMSTextMessage' => [
                        'message' => $message
                    ]
                ]
            ];

            // Encoder le numéro expéditeur pour l'URL
            $encodedSenderNumber = urlencode('tel:' . $this->senderPhoneNumber);
            $url = $this->baseUrl . '/smsmessaging/v1/outbound/' . $encodedSenderNumber . '/requests';

            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($smsData),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            
            curl_close($curl);

            if ($error) {
                throw new \Exception("Erreur cURL lors de l'envoi du SMS: " . $error);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new \Exception("Erreur HTTP lors de l'envoi du SMS: " . $httpCode . " - " . $response);
            }

            $result = json_decode($response, true);
            Log::info($result);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Erreur de décodage JSON: " . json_last_error_msg());
            }

            return $result;

        } catch (\Exception $e) {
            error_log('Erreur lors de l\'envoi du SMS: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Valide un numéro de téléphone au format international
     * 
     * @param string $phoneNumber Numéro à valider
     * @return bool True si valide, false sinon
     */
    public function validatePhoneNumber($phoneNumber)
    {
        // Validation basique du format international
        return preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber);
    }

    /**
     * Envoie un SMS avec validation des paramètres
     * 
     * @param string $recipientPhoneNumber Numéro du destinataire
     * @param string $senderPhoneNumber Numéro de l'expéditeur
     * @param string $message Message à envoyer
     * @return array Réponse de l'API
     * @throws \Exception En cas de paramètres invalides
     */
    public function sendValidatedSMS($recipientPhoneNumber, $senderPhoneNumber, $message)
    {
        // Validation des paramètres
        if (!$this->validatePhoneNumber($recipientPhoneNumber)) {
            throw new \Exception("Numéro destinataire invalide: " . $recipientPhoneNumber);
        }

        if (empty(trim($message))) {
            throw new \Exception('Le message ne peut pas être vide');
        }

        if (strlen($message) > 160) {
            error_log('Attention: Le message dépasse 160 caractères et pourrait être facturé comme plusieurs SMS');
        }

        return $this->sendSMS($recipientPhoneNumber, $senderPhoneNumber, $message);
    }

    /**
     * Obtient les informations du token actuel
     * 
     * @return array Informations sur le token
     */
    public function getTokenInfo()
    {
        return [
            'token' => $this->accessToken,
            'expires_at' => $this->tokenExpiresAt,
            'is_valid' => $this->accessToken && time() < $this->tokenExpiresAt,
            'expires_in' => $this->tokenExpiresAt ? max(0, $this->tokenExpiresAt - time()) : 0
        ];
    }
}
