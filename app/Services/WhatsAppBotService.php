<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WorkOrder;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppBotService
{
    protected ?Customer $customer = null;
    protected ?string $phoneNumber = null;
    protected string $state = 'main_menu';

    // Cache TTL for session state (30 minutes)
    protected int $sessionTtl = 1800;

    /**
     * Process incoming message and return bot response.
     */
    public function processMessage(string $message, WhatsAppConversation $conversation): ?string
    {
        $this->phoneNumber = $conversation->contact->phone_number ?? null;
        $this->customer = $this->findCustomerByPhone($this->phoneNumber);
        $this->state = $this->getSessionState();

        $message = trim($message);

        Log::info('WhatsApp Bot processing', [
            'phone' => $this->phoneNumber,
            'customer' => $this->customer?->name,
            'state' => $this->state,
            'message' => $message,
        ]);

        // Check for menu reset commands
        if (in_array(strtolower($message), ['menu', 'start', 'hi', 'hello', 'main', '0'])) {
            $this->setState('main_menu');
            return $this->getMainMenu();
        }

        // Process based on current state
        return match ($this->state) {
            'main_menu' => $this->handleMainMenu($message),
            'check_status' => $this->handleCheckStatus($message),
            'talk_agent' => $this->handleTalkAgent($message, $conversation),
            'other_message' => $this->handleOtherMessage($message, $conversation),
            default => $this->getMainMenu(),
        };
    }

    /**
     * Get the main menu message.
     */
    protected function getMainMenu(): string
    {
        $greeting = $this->customer
            ? "Assalam o Alaikum *{$this->customer->name}*! 👋"
            : "Assalam o Alaikum! 👋";

        return "{$greeting}

Welcome to *Tasker Company* 🏠
Your trusted home services partner.

Please choose an option:

1️⃣ *Check Order Status*
    Enter work order number to track

2️⃣ *My Active Bookings*
    View all your current orders

3️⃣ *Book a Service* 📱
    Use our app or website

4️⃣ *Talk to Representative*
    Connect with our team

5️⃣ *Other / General Inquiry*
    Send us a message

━━━━━━━━━━━━━━━━━━━━
_Type a number (1-5) to continue_
_Type *0* anytime to return to menu_";
    }

    /**
     * Handle main menu selection.
     */
    protected function handleMainMenu(string $input): string
    {
        return match ($input) {
            '1' => $this->promptCheckStatus(),
            '2' => $this->showActiveBookings(),
            '3' => $this->showBookingInfo(),
            '4' => $this->promptTalkAgent(),
            '5' => $this->promptOtherMessage(),
            default => $this->getInvalidOptionMessage(),
        };
    }

    /**
     * Prompt for work order number.
     */
    protected function promptCheckStatus(): string
    {
        $this->setState('check_status');

        return "🔍 *Check Order Status*

Please enter your work order number:
_(Example: WO-2025-00123)_

━━━━━━━━━━━━━━━━━━━━
_Type *0* to go back to menu_";
    }

    /**
     * Handle work order status check.
     */
    protected function handleCheckStatus(string $input): string
    {
        if ($input === '0') {
            $this->setState('main_menu');
            return $this->getMainMenu();
        }

        // Clean the input
        $woNumber = strtoupper(trim($input));

        // Query work order
        $query = WorkOrder::with(['customer', 'status', 'subStatus', 'assignedTo', 'service'])
            ->where(function ($q) use ($woNumber) {
                $q->where('work_order_number', $woNumber)
                    ->orWhere('work_order_number', 'like', "%{$woNumber}%")
                    ->orWhere('id', $woNumber);
            });

        // Scope to customer if known
        if ($this->customer) {
            $query->where('customer_id', $this->customer->id);
        }

        $workOrder = $query->first();

        if (!$workOrder) {
            return "❌ *Order Not Found*

We couldn't find work order: *{$woNumber}*" .
                ($this->customer ? " in your account." : ".") . "

Please check the number and try again.

━━━━━━━━━━━━━━━━━━━━
_Enter another number or type *0* for menu_";
        }

        // Reset state
        $this->setState('main_menu');

        $statusEmoji = $this->getStatusEmoji($workOrder->status?->slug);

        return "📋 *Work Order Details*

🔢 *Number:* {$workOrder->work_order_number}
{$statusEmoji} *Status:* {$workOrder->status?->name}" .
            ($workOrder->subStatus ? "\n📍 *Sub-Status:* {$workOrder->subStatus->name}" : "") . "
🔧 *Service:* {$workOrder->service?->name}
👤 *Customer:* {$workOrder->customer?->name}
👷 *Assigned To:* " . ($workOrder->assignedTo?->first_name ?? 'Not assigned') . "
📅 *Scheduled:* " . ($workOrder->scheduled_date?->format('d M Y') ?? 'TBD') . "

━━━━━━━━━━━━━━━━━━━━
_Type *0* to return to main menu_";
    }

    /**
     * Show all active bookings for the customer.
     */
    protected function showActiveBookings(): string
    {
        if (!$this->customer) {
            return "🔐 *Verification Required*

We couldn't find your account with this phone number.

Please provide your registered phone number or work order number.

━━━━━━━━━━━━━━━━━━━━
_Type *1* to check by order number_
_Type *0* for main menu_";
        }

        $workOrders = WorkOrder::with(['status', 'service'])
            ->where('customer_id', $this->customer->id)
            ->whereHas('status', function ($q) {
                $q->whereNotIn('slug', ['closed', 'cancelled', 'completed']);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($workOrders->isEmpty()) {
            return "📭 *No Active Bookings*

You don't have any active bookings at the moment.

━━━━━━━━━━━━━━━━━━━━
_Type *3* to book a new service_
_Type *0* for main menu_";
        }

        $list = $workOrders->map(function ($wo, $index) {
            $emoji = $this->getStatusEmoji($wo->status?->slug);
            return ($index + 1) . ". *{$wo->work_order_number}*\n   {$emoji} {$wo->status?->name} | {$wo->service?->name}";
        })->join("\n\n");

        return "📋 *Your Active Bookings*

{$list}

━━━━━━━━━━━━━━━━━━━━
_Type *1* and enter order number for details_
_Type *0* for main menu_";
    }

    /**
     * Show booking information.
     */
    protected function showBookingInfo(): string
    {
        return "📱 *Book a Service*

To book a new service, please use:

🌐 *Website:* taskercompany.com
📲 *App:* Download from Play Store

Or call us at: *0304-1112717*

_WhatsApp booking coming soon!_ 🚀

━━━━━━━━━━━━━━━━━━━━
_Type *0* for main menu_";
    }

    /**
     * Prompt to talk to agent.
     */
    protected function promptTalkAgent(): string
    {
        $this->setState('talk_agent');

        return "👤 *Talk to Representative*

Please briefly describe your query:
_(Our team will respond shortly)_

━━━━━━━━━━━━━━━━━━━━
_Type *0* to go back to menu_";
    }

    /**
     * Handle talk to agent flow.
     */
    protected function handleTalkAgent(string $message, WhatsAppConversation $conversation): string
    {
        if ($message === '0') {
            $this->setState('main_menu');
            return $this->getMainMenu();
        }

        // Mark conversation as needing human attention
        $conversation->update([
            'status' => 'open',
            'notes' => "Customer requested to talk to agent. Message: {$message}",
        ]);

        $this->setState('main_menu');

        return "✅ *Request Received*

Thank you! Your message has been sent to our team.

📩 _\"{$message}\"_

A representative will respond shortly during business hours (9 AM - 6 PM).

━━━━━━━━━━━━━━━━━━━━
_Type *0* for main menu_";
    }

    /**
     * Prompt for other message.
     */
    protected function promptOtherMessage(): string
    {
        $this->setState('other_message');

        return "💬 *General Inquiry*

Please type your message or question:

━━━━━━━━━━━━━━━━━━━━
_Type *0* to go back to menu_";
    }

    /**
     * Handle other message.
     */
    protected function handleOtherMessage(string $message, WhatsAppConversation $conversation): string
    {
        if ($message === '0') {
            $this->setState('main_menu');
            return $this->getMainMenu();
        }

        // Save the inquiry
        $conversation->update([
            'status' => 'open',
            'notes' => "General inquiry: {$message}",
        ]);

        $this->setState('main_menu');

        return "✅ *Message Received*

Thank you for reaching out! We've received your message:

📩 _\"{$message}\"_

Our team will review and respond if needed.

━━━━━━━━━━━━━━━━━━━━
_Type *0* for main menu_";
    }

    /**
     * Get invalid option message.
     */
    protected function getInvalidOptionMessage(): string
    {
        return "❓ *Invalid Option*

Please enter a number from 1-5 to select an option.

" . $this->getMainMenu();
    }

    /**
     * Get status emoji.
     */
    protected function getStatusEmoji(?string $status): string
    {
        return match ($status) {
            'pending' => '🟡',
            'in-progress', 'assigned' => '🔵',
            'completed' => '✅',
            'cancelled' => '❌',
            'on-hold' => '⏸️',
            'feedback-pending' => '💬',
            default => '⚪',
        };
    }

    /**
     * Find customer by phone number.
     */
    protected function findCustomerByPhone(?string $phone): ?Customer
    {
        if (!$phone) {
            return null;
        }

        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

        return Customer::where('phone', 'like', '%' . $normalizedPhone)
            ->orWhere('whatsapp', 'like', '%' . $normalizedPhone)
            ->orWhere('phone', 'like', '%' . substr($normalizedPhone, -10))
            ->first();
    }

    /**
     * Get session state from cache.
     */
    protected function getSessionState(): string
    {
        if (!$this->phoneNumber) {
            return 'main_menu';
        }

        return Cache::get("whatsapp_bot_state:{$this->phoneNumber}", 'main_menu');
    }

    /**
     * Set session state in cache.
     */
    protected function setState(string $state): void
    {
        if ($this->phoneNumber) {
            Cache::put("whatsapp_bot_state:{$this->phoneNumber}", $state, $this->sessionTtl);
        }
        $this->state = $state;
    }
}
