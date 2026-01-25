<?php

namespace App\Http\Controllers\Auth\TaskerApp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OTP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AuthenticatedSessionController extends Controller
{
    public function otp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|regex:/^\+?[0-9]{10,15}$/',
            'source' => 'required|in:tasker-app',
        ]);

        $phone = $validated['phone'];

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

    public function verifyotp(Request $request)
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

        if (! $otpRecord) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 400);
        }

        // Check if a customer with this phone already exists
        $customer = Customer::where('phone', $phone)->first();

        $isNew = false;
        if (! $customer) {
            // Generate unique customer ID starting with "cus"
            $customerId = 'cus' . strtoupper(bin2hex(random_bytes(4)));
            $name = $customerId;

            $customer = Customer::create([
                'customer_id' => $customerId,
                'phone' => $phone,
                'name' => $name,
            ]);

            $isNew = true;
            $this->sendWelcomeMessage($phone, $customer->name, true);
        } else {
            $this->sendWelcomeMessage($phone, $customer->name, false);
        }
        $user = Customer::where('phone', $phone)->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => $isNew ? 'Account created successfully.' : 'Login successfully.',
            'token' => $token,
            'isNew' => $isNew,
        ]);
    }
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'status' => 'success',
            'user' => $user,
        ]);
    }
    public function update(Request $request)
    {
        $customer = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|string|email|max:255|unique:customers,email,' . $customer->id,
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png|max:2048', // 2MB max
        ]);

        if ($request->hasFile('avatar')) {
            if ($customer->avatar && Storage::disk('public')->exists($customer->avatar)) {
                Storage::disk('public')->delete($customer->avatar);
            }
            $file = $request->file('avatar');
            $folder = 'profile-images';
            $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($folder, $fileName, 'public');
            $validated['avatar'] = $filePath;
        } else {
            $validated['avatar'] = $customer->avatar;
        }

        $customer->update($validated);
        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            'data' => $customer->fresh(),
        ]);
    }

    private function setOtp($phone, $source)
    {
        // $otp = random_int(100000, 999999);
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

    /**
     * Send a welcome or new-login WhatsApp message to the customer.
     */
    private function sendWelcomeMessage($phone, $name, $isNew)
    {
        try {
            $messageText = $isNew
                ? "🎉 Welcome to Tasker Company, {$name}!\n\nWe’re here to make your life easier — from AC repair, servicing, and installation to all major home services.\n\nTell us what you need help with, and our team will guide you instantly.\nJust reply with your service requirement to get started."
                : "👋 Welcome back, {$name}!\n\nNeed help again? Whether it’s AC repair, tuning, installation, electrician work, plumbing, or any home service — we’re ready when you are.\n\nJust reply with your requirement and we’ll handle the rest.";

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
            Log::error('Welcome message send failed: ' . $e->getMessage());
        }
    }
}
