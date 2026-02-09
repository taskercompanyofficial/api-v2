<?php

namespace App\Http\Controllers\Auth\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\OTP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    public function otp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|regex:/^\+?[0-9]{10,15}$/',
            'source' => 'required|in:vendor-app',
            'intent' => 'required|in:login,register',
            'password' => 'nullable|string|min:8',
        ]);

        $phone = $validated['phone'];
        $intent = $validated['intent'];

        $attempts = OTP::where('phone_number', $phone)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($attempts >= 5) {
            return response()->json([
                'message' => 'Too many attempts. Please try again after 24 hours.',
            ], 429);
        }

        if ($intent === 'login') {
            $vendor = Vendor::where('phone', $phone)->first();
            if (! $vendor || ! isset($validated['password']) || ! Hash::check($validated['password'], $vendor->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials',
                ], 401);
            }
            if ($vendor->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is not active',
                ], 403);
            }
        } else {
            // register intent
            if (Vendor::where('phone', $phone)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phone number already registered',
                ], 409);
            }
        }

        $this->setOtp($phone, $validated['source']);

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent successfully.',
        ]);
    }

    public function verifyotp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|regex:/^\+?[0-9]{10,15}$/',
            'otp' => 'required|integer|digits:6',
            'name' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        $phone = $validated['phone'];
        $otp = $validated['otp'];
        $otpRecord = OTP::where('phone_number', $phone)
            ->where('otp', $otp)
            ->where('status', '=', 'active')
            ->first();

        if (! $otpRecord) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 400);
        }

        $vendor = Vendor::where('phone', $phone)->first();
        $isNew = false;

        if (! $vendor) {
            // Registration path requires name and password
            if (!isset($validated['name']) || !isset($validated['password'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Name and password are required for registration',
                ], 422);
            }
            $vendor = Vendor::create([
                'name' => $validated['name'],
                'phone' => $phone,
                'password' => Hash::make($validated['password']),
                'status' => 'active',
            ]);
            $isNew = true;
            $this->sendWelcomeMessage($phone, $vendor->name, true);
        } else {
            $this->sendWelcomeMessage($phone, $vendor->name, false);
        }

        $token = $vendor->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => $isNew ? 'Account created successfully.' : 'Login successfully.',
            'token' => $token,
            'isNew' => $isNew,
        ]);
    }

    private function setOtp($phone, $source)
    {
        $otp = 123456;
        OTP::where('phone_number', $phone)
            ->where('status', '=', 'active')
            ->update(['status' => 'expired']);

        OTP::create([
            'phone_number' => $phone,
            'otp' => $otp,
            'source' => $source,
            'status' => 'active',
        ]);

        try {
            $curlURL = env('WHATSAPP_GRAPH_API_URL') . '/' . env('WHATSAPP_API_VERSION') . '/' . env('WHATSAPP_PHONE_NUMBER_ID') . '/messages';
            $contactUsPhoneNumber = '+923041112717';
            $curlData = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => 'one_time_passcode',
                    'language' => [
                        'code' => 'en_US',
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $otp],
                                ['type' => 'text', 'text' => $contactUsPhoneNumber],
                            ],
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => '0',
                            'parameters' => [
                                ['type' => 'text', 'text' => $contactUsPhoneNumber],
                            ],
                        ],
                    ],
                ],
            ];
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $curlURL);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curlData));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('WHATSAPP_ACCESS_TOKEN'),
            ]);
            curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            Log::error('Vendor OTP send failed: ' . $e->getMessage());
        }
    }

    private function sendWelcomeMessage($phone, $name, $isNew)
    {
        try {
            $messageText = $isNew
                ? "🎉 Welcome to Tasker Company Partner Network, {$name}!\n\nYou can now receive jobs and earn through our platform.\nWe’ll be in touch with next steps."
                : "👋 Welcome back, {$name}!\n\nYou’re signed in to the provider app.";

            $curlURL = env('WHATSAPP_GRAPH_API_URL') . '/' . env('WHATSAPP_PHONE_NUMBER_ID') . '/messages';
            $curlData = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $messageText],
            ];

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $curlURL);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curlData));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('WHATSAPP_ACCESS_TOKEN'),
            ]);

            curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            Log::error('Vendor welcome message failed: ' . $e->getMessage());
        }
    }
}
