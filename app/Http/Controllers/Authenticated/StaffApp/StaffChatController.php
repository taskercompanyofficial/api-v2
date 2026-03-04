<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffChatController extends Controller
{
    /**
     * Get list of all active staff with their linked WhatsApp conversations.
     * Matches WhatsApp conversations by phone number.
     */
    public function getStaffList(Request $request): JsonResponse
    {
        $query = Staff::where('status_id', 1)
            ->select([
                'id', 'first_name', 'last_name', 'email', 'phone',
                'profile_image', 'role_id', 'branch_id',
            ])
            ->with(['role:id,name', 'branch:id,name']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by branch
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $staff = $query->orderBy('first_name')->get();

        // Get all phone numbers from staff
        $phoneNumbers = $staff->pluck('phone')->filter()->map(function ($phone) {
            return preg_replace('/[^0-9]/', '', $phone);
        })->filter()->toArray();

        // Find WhatsApp conversations linked to these phone numbers
        $whatsappConversations = \App\Models\WhatsAppConversation::with(['contact', 'latestMessage'])
            ->whereHas('contact', function ($q) use ($phoneNumbers) {
                $q->where(function ($q2) use ($phoneNumbers) {
                    foreach ($phoneNumbers as $phone) {
                        $q2->orWhere('phone_number', 'like', "%{$phone}%");
                    }
                });
            })
            ->get()
            ->keyBy(function ($conv) {
                return preg_replace('/[^0-9]/', '', $conv->contact->phone_number ?? '');
            });

        // Enrich staff data with WhatsApp conversation info
        $enriched = $staff->map(function ($member) use ($whatsappConversations) {
            $memberData = $member->toArray();
            $cleanPhone = preg_replace('/[^0-9]/', '', $member->phone ?? '');

            $waConv = $whatsappConversations->get($cleanPhone);
            $memberData['whatsapp_conversation'] = $waConv ? [
                'id' => $waConv->id,
                'status' => $waConv->status,
                'last_message_at' => $waConv->last_message_at?->toISOString(),
                'unread_count' => $waConv->unread_count,
                'latest_message' => $waConv->latestMessage ? [
                    'content' => $waConv->latestMessage->content,
                    'type' => $waConv->latestMessage->type,
                    'direction' => $waConv->latestMessage->direction,
                    'status' => $waConv->latestMessage->status,
                    'created_at' => $waConv->latestMessage->created_at->toISOString(),
                ] : null,
            ] : null;

            // Use last_message_at for sorting
            $memberData['latest_activity_at'] = $waConv?->last_message_at?->timestamp ?? 0;

            return $memberData;
        });

        // Sort by latest activity (most recent first)
        $sorted = $enriched->sortByDesc('latest_activity_at')->values();

        return response()->json([
            'success' => true,
            'data' => $sorted,
        ]);
    }
}
