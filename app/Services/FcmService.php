<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public static function projectId(): string
    {
        return config('services.fcm.project_id') ?: env('FCM_PROJECT_ID') ?: Setting::get('fcm.project_id', '');
    }

    public static function serviceAccountPath(): string
    {
        return config('services.fcm.service_account_path') ?: env('FCM_SERVICE_ACCOUNT_PATH', '');
    }

    public static function serviceAccountJson(): string
    {
        return config('services.fcm.service_account_json') ?: env('FCM_SERVICE_ACCOUNT_JSON') ?: Setting::get('fcm.service_account_json', '');
    }

    public static function serviceAccountData(): ?array
    {
        $path = self::serviceAccountPath();
        if ($path && file_exists($path) && is_readable($path)) {
            $json = file_get_contents($path);
            $data = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
            Log::error('FcmService: Invalid JSON in FCM service account path: ' . $path);
        }

        $serviceAccountJson = self::serviceAccountJson();
        if (empty($serviceAccountJson)) {
            return null;
        }

        $data = json_decode($serviceAccountJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            Log::error('FcmService: Invalid service account JSON configuration.');
            return null;
        }

        return $data;
    }

    public static function getAccessToken(): ?string
    {
        $serviceAccountData = self::serviceAccountData();
        if (empty($serviceAccountData)) {
            return null;
        }

        // Cache the access token to avoid calling Google API on every notification (tokens are valid for 1 hour)
        return Cache::remember('fcm_access_token', 3000, function () use ($serviceAccountData) {
            try {
                if (!isset($serviceAccountData['private_key'], $serviceAccountData['client_email'])) {
                    Log::error('FcmService: Invalid service account JSON configuration.');
                    return null;
                }

                $privateKey = $serviceAccountData['private_key'];
                $clientEmail = $serviceAccountData['client_email'];

                $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
                $now = time();
                $payload = json_encode([
                    'iss' => $clientEmail,
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'exp' => $now + 3600,
                    'iat' => $now
                ]);

                $base64UrlHeader = self::base64UrlEncode($header);
                $base64UrlPayload = self::base64UrlEncode($payload);

                $signature = '';
                if (!openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $privateKey, 'SHA256')) {
                    Log::error('FcmService: OpenSSL signature generation failed.');
                    return null;
                }
                $base64UrlSignature = self::base64UrlEncode($signature);

                $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]);

                if ($response->failed()) {
                    Log::error('FcmService: OAuth2 token exchange failed: ' . $response->body());
                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::error('FcmService: Error obtaining access token - ' . $e->getMessage());
                return null;
            }
        });
    }

    public static function sendPushNotification(string $fcmToken, array $notificationData): bool
    {
        $projectId = self::projectId();
        $accessToken = self::getAccessToken();

        if (empty($projectId) || empty($accessToken)) {
            Log::warning('FcmService: Push notification skipped. FCM project_id or service_account_json is not configured.');
            return false;
        }

        try {
            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $notificationData['title'] ?? '',
                        'body' => $notificationData['body'] ?? '',
                    ],
                    'data' => collect($notificationData['data'] ?? [])
                        ->map(fn($val) => (string) $val) // FCM data values must be strings
                        ->toArray(),
                    'android' => [
                        'notification' => [
                            'sound' => 'default',
                            'click_action' => 'qa365://evaluations',
                        ]
                    ]
                ]
            ];

            // If a specific deep link is provided, inject it
            if (!empty($notificationData['data']['deep_link'])) {
                $payload['message']['android']['notification']['click_action'] = $notificationData['data']['deep_link'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->failed()) {
                Log::error('FcmService: FCM send error: ' . $response->body());
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('FcmService: Exception sending push notification: ' . $e->getMessage());
            return false;
        }
    }

    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
