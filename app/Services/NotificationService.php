<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send Push Notification using Expo
     */
    public function sendPushNotification($to, $title, $body, $data = [])
    {
        if (!$to) {
            return null;
        }

        $url = 'https://exp.host/--/api/v2/push/send';
        $postData = [
            'to' => $to,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
        ];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            Log::info("Push notification sent to {$to}", [
                'title' => $title,
                'response' => $response
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error("Failed to send push notification to {$to}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send WhatsApp Notification
     */
    public function sendWhatsAppNotification($phone, $message)
    {
        if (!$phone) {
            return null;
        }

        try {
            $whatsappService = new WhatsAppService();
            $result = $whatsappService->sendTextMessage($phone, $message);
            
            Log::info("WhatsApp notification sent to {$phone}");

            return $result;
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp notification to {$phone}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
