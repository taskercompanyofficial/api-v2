<?php

namespace App\Http\Controllers\Auth\App;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OTP;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
    public function otp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|regex:/^\+?[0-9]{10,15}$/',
            'source' => 'required|in:tasker-staff',
        ]);

        $phone = $validated['phone'];
        $user = Staff::with('status')->where('phone', $phone)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account not found.',
            ]);
        }

        // Check if staff status is active
        if (!$user->status || $user->status->slug !== 'active') {
            $statusMessage = $user->status ? $user->status->name : 'Unknown';
            return response()->json([
                'status' => 'error',
                'message' => "Your account is currently {$statusMessage}. Please contact your administrator.",
            ], 403);
        }

        // Count OTP requests for this phone in the last 24 hours
        $attempts = OTP::where('phone_number', $phone)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($attempts >= 5) {
            return response()->json([
                'message' => 'Too many attempts. Please try again after 24 hours.',
            ], 429);
        }

        $this->setOtp($phone, $validated['source']);

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent successfully.',
        ]);
    }

    public function veriyotp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|regex:/^\+?[0-9]{10,15}$/',
            'otp' => 'required|integer|digits:6',
        ]);

        $phone = $validated['phone'];
        $otp = $validated['otp'];

        $otpRecord = OTP::where('phone_number', $phone)
            ->where('otp', $otp)
            ->where('status', '=', 'active')
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 400);
        }
        $otpRecord->update(['status' => 'expired']);
        $user = Staff::with('status')->where('phone', $phone)->first();

        // Check if staff status is active
        if (!$user->status || $user->status->slug !== 'active') {
            $statusMessage = $user->status ? $user->status->name : 'Unknown';
            return response()->json([
                'status' => 'error',
                'message' => "Your account is currently {$statusMessage}. Please contact your administrator.",
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        // Send new-login WhatsApp message
        return response()->json([
            'status' => 'success',
            'message' => 'Login successfully.',
            'token' => $token,
        ]);
    }


    private function setOtp($phone, $source)
    {
        $otp = random_int(100000, 999999);

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
            Log::info('OTP send to ' . $phone . ' with OTP ' . $otp);
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $curlURL);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curlData));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('WHATSAPP_ACCESS_TOKEN'),
            ]);
            $response = curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            Log::error('OTP send failed: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'OTP send failed. Please try again.',
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user = Staff::with(['staffRole:id,name', 'status'])->find($user->id);

        // Check if staff status is active
        if (!$user->status || $user->status->slug !== 'active') {
            $user->tokens()->delete();
            $statusMessage = $user->status ? $user->status->name : 'Unknown';
            return response()->json([
                'status' => 'error',
                'message' => "Your account is currently {$statusMessage}. Please contact your administrator.",
            ], 403);
        }

        // Check if profile is complete
        $requiredFields = [
            'cnic',
            'dob',
            'gender',
            'permanent_address',
            'city',
            'state',
            'postal_code',
            'profile_picture',
            'cnic_front_image',
            'cnic_back_image',
        ];

        $isProfileComplete = true;
        foreach ($requiredFields as $field) {
            if (empty($user->$field)) {
                $isProfileComplete = false;
                break;
            }
        }

        return response()->json([
            'user' => $user,
            'is_profile_complete' => $isProfileComplete,
        ], 200);
    }
    public function signOut(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ], 200);
    }
}
