<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WorkOrder;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WhatsAppBotService
{
    protected WhatsAppService $whatsappService;
    protected ?Customer $customer = null;
    protected ?string $phoneNumber = null;
    protected ?string $contactName = null;
    protected string $state = 'new';

    // Cache TTL for session state (30 minutes)
    protected int $sessionTtl = 1800;

    // Company contact info
    protected string $companyName = 'Tasker Company';
    protected string $helplineUAN = '0304-111-2717';
    protected string $companyEmail = 'info@taskercompany.com';
    protected string $companyWebsite = 'taskercompany.com';

    // Business hours (Pakistan time)
    protected int $businessStartHour = 8;  // 8 AM
    protected int $businessEndHour = 20;   // 8 PM

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Process incoming message and return response.
     */
    public function processMessage(string $message, WhatsAppConversation $conversation): ?array
    {
        $this->phoneNumber = $conversation->contact->phone_number ?? null;
        $this->contactName = $conversation->contact->whatsapp_name ?? 'Customer';
        $this->customer = $this->findCustomerByPhone($this->phoneNumber);
        $this->state = $this->getSessionState();

        $message = trim($message);

        Log::info('WhatsApp Bot processing', [
            'phone' => $this->phoneNumber,
            'contact_name' => $this->contactName,
            'customer' => $this->customer?->name,
            'state' => $this->state,
            'message' => $message,
        ]);

        // Check if bot is disabled for this conversation (staff takeover)
        if ($this->isBotDisabledForConversation($conversation)) {
            Log::info('Bot disabled for conversation - staff takeover active');
            return null;
        }

        // First-time greeting for new conversations
        if ($this->state === 'new') {
            $this->setState('main_menu');
            return $this->getWelcomeGreeting();
        }

        // Check for menu reset commands
        $messageLower = strtolower($message);
        if (in_array($messageLower, ['menu', 'start', 'hi', 'hello', 'main', '0', 'salam', 'assalam', 'aoa', 'helo', 'hey'])) {
            $this->setState('main_menu');
            return $this->getMainMenuResponse();
        }

        // Handle interactive button/list selections (button IDs)
        if (str_starts_with($message, 'menu_')) {
            return $this->handleMenuSelection($message);
        }

        // Process based on current state
        return match ($this->state) {
            'main_menu' => $this->handleMainMenuInput($message),
            'check_status' => $this->handleCheckStatus($message),
            'talk_agent' => $this->handleTalkAgent($message, $conversation),
            'other_message' => $this->handleOtherMessage($message, $conversation),
            default => $this->getMainMenuResponse(),
        };
    }

    /**
     * Check if bot should be disabled for this conversation (staff takeover).
     */
    protected function isBotDisabledForConversation(WhatsAppConversation $conversation): bool
    {
        // Check if conversation has staff assigned and bot is disabled
        return (bool) ($conversation->bot_disabled ?? false);
    }

    /**
     * Check if currently within business hours.
     */
    protected function isBusinessHours(): bool
    {
        $now = Carbon::now('Asia/Karachi');
        $hour = $now->hour;
        $dayOfWeek = $now->dayOfWeek; // 0 = Sunday

        // Sunday is off
        if ($dayOfWeek === 0) {
            return false;
        }

        return $hour >= $this->businessStartHour && $hour < $this->businessEndHour;
    }

    /**
     * Get next business hours opening time.
     */
    protected function getNextOpeningTime(): string
    {
        $now = Carbon::now('Asia/Karachi');

        if ($now->hour >= $this->businessEndHour) {
            // After closing - opens tomorrow
            return 'tomorrow at 08:00 AM';
        } elseif ($now->dayOfWeek === 0) {
            // Sunday - opens Monday
            return 'Monday at 08:00 AM';
        } else {
            return 'at 08:00 AM';
        }
    }

    /**
     * Get the welcome greeting for first-time contact.
     */
    protected function getWelcomeGreeting(): array
    {
        $customerName = $this->customer?->name ?? $this->contactName;

        $greeting = "Assalam-o-Alaikum! 🙏\n\n" .
            "Thank You *{$customerName}* for contacting *{$this->companyName}*! 🏠\n\n";

        // Add business hours message if outside hours
        if (!$this->isBusinessHours()) {
            $nextOpen = $this->getNextOpeningTime();
            $greeting .= "⏰ We are currently unavailable, but our team will respond {$nextOpen}.\n\n";
        }

        $greeting .= "How can we help you today?\n\n" .
            "📞 Helpline: {$this->helplineUAN}\n" .
            "📧 Email: {$this->companyEmail}";

        return [
            'type' => 'interactive_list',
            'header' => '🏠 Welcome to Tasker Company',
            'body' => $greeting,
            'footer' => 'Select an option to continue',
            'button' => 'Choose Service',
            'sections' => $this->getMainMenuSections(),
        ];
    }

    /**
     * Get main menu sections for list.
     */
    protected function getMainMenuSections(): array
    {
        return [
            [
                'title' => 'Our Services',
                'rows' => [
                    [
                        'id' => 'menu_track',
                        'title' => '🔍 Track Order',
                        'description' => 'Check your work order status',
                    ],
                    [
                        'id' => 'menu_bookings',
                        'title' => '📋 My Bookings',
                        'description' => 'View all your active orders',
                    ],
                    [
                        'id' => 'menu_book',
                        'title' => '📱 Book Service',
                        'description' => 'Book a new home service',
                    ],
                ],
            ],
            [
                'title' => 'Support',
                'rows' => [
                    [
                        'id' => 'menu_agent',
                        'title' => '👤 Talk to Agent',
                        'description' => 'Chat with our team',
                    ],
                    [
                        'id' => 'menu_contact',
                        'title' => '📞 Contact Info',
                        'description' => 'Get our contact details',
                    ],
                    [
                        'id' => 'menu_other',
                        'title' => '💬 Other Inquiry',
                        'description' => 'General questions',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the main menu response.
     */
    protected function getMainMenuResponse(): array
    {
        $customerName = $this->customer?->name ?? $this->contactName;

        $body = "Hi *{$customerName}*! 👋\n\n" .
            "What would you like to do?\n\n" .
            "📞 Helpline: {$this->helplineUAN}";

        return [
            'type' => 'interactive_list',
            'header' => '🏠 Tasker Company',
            'body' => $body,
            'footer' => 'Select an option below',
            'button' => 'Options',
            'sections' => $this->getMainMenuSections(),
        ];
    }

    /**
     * Handle selection from interactive menu.
     */
    protected function handleMenuSelection(string $selection): array
    {
        return match ($selection) {
            'menu_track' => $this->promptCheckStatus(),
            'menu_bookings' => $this->showActiveBookings(),
            'menu_book' => $this->showBookingInfo(),
            'menu_agent' => $this->promptTalkAgent(),
            'menu_contact' => $this->showContactInfo(),
            'menu_other' => $this->promptOtherMessage(),
            'menu_main' => $this->getMainMenuResponse(),
            default => $this->getMainMenuResponse(),
        };
    }

    /**
     * Handle menu input as text (fallback for numbered input).
     */
    protected function handleMainMenuInput(string $input): array
    {
        return match ($input) {
            '1' => $this->promptCheckStatus(),
            '2' => $this->showActiveBookings(),
            '3' => $this->showBookingInfo(),
            '4' => $this->promptTalkAgent(),
            '5' => $this->showContactInfo(),
            '6' => $this->promptOtherMessage(),
            default => $this->getInvalidOptionResponse(),
        };
    }

    /**
     * Prompt for work order number.
     */
    protected function promptCheckStatus(): array
    {
        $this->setState('check_status');

        return [
            'type' => 'interactive_buttons',
            'header' => '🔍 Track Order Status',
            'body' => "Please type your work order number.\n\n*Example:* WO-2025-00123\n\nOr type the last few digits like *123*",
            'footer' => "📞 Need help? {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Back to Menu'],
            ],
        ];
    }

    /**
     * Handle work order status check.
     */
    protected function handleCheckStatus(string $input): array
    {
        if ($input === '0' || $input === 'menu_main') {
            $this->setState('main_menu');
            return $this->getMainMenuResponse();
        }

        $woNumber = strtoupper(trim($input));

        $query = WorkOrder::with(['customer', 'status', 'subStatus', 'assignedTo', 'service'])
            ->where(function ($q) use ($woNumber) {
                $q->where('work_order_number', $woNumber)
                    ->orWhere('work_order_number', 'like', "%{$woNumber}%")
                    ->orWhere('id', is_numeric($woNumber) ? $woNumber : 0);
            });

        if ($this->customer) {
            $query->where('customer_id', $this->customer->id);
        }

        $workOrder = $query->first();

        if (!$workOrder) {
            return [
                'type' => 'interactive_buttons',
                'header' => '❌ Order Not Found',
                'body' => "We couldn't find order *{$woNumber}*.\n\nPlease check the number and try again.",
                'footer' => "📞 Help: {$this->helplineUAN}",
                'buttons' => [
                    ['id' => 'menu_track', 'title' => '🔄 Try Again'],
                    ['id' => 'menu_main', 'title' => '↩️ Menu'],
                ],
            ];
        }

        $this->setState('main_menu');
        $statusEmoji = $this->getStatusEmoji($workOrder->status?->slug);

        return [
            'type' => 'interactive_buttons',
            'header' => "📋 {$workOrder->work_order_number}",
            'body' => "{$statusEmoji} *Status:* {$workOrder->status?->name}\n" .
                "🔧 *Service:* {$workOrder->service?->name}\n" .
                "👷 *Technician:* " . ($workOrder->assignedTo?->first_name ?? 'Pending') . "\n" .
                "📅 *Date:* " . ($workOrder->scheduled_date?->format('d M Y') ?? 'TBD'),
            'footer' => "📞 {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_track', 'title' => '🔍 Another Order'],
                ['id' => 'menu_main', 'title' => '↩️ Menu'],
            ],
        ];
    }

    /**
     * Show all active bookings.
     */
    protected function showActiveBookings(): array
    {
        $this->setState('main_menu');

        if (!$this->customer) {
            return [
                'type' => 'interactive_buttons',
                'header' => '🔐 Verification Needed',
                'body' => "We couldn't link this number to an account.\n\nTry tracking by order number instead.",
                'footer' => "📞 {$this->helplineUAN}",
                'buttons' => [
                    ['id' => 'menu_track', 'title' => '🔍 Track Order'],
                    ['id' => 'menu_agent', 'title' => '👤 Talk to Agent'],
                ],
            ];
        }

        $workOrders = WorkOrder::with(['status', 'service'])
            ->where('customer_id', $this->customer->id)
            ->whereHas('status', fn($q) => $q->whereNotIn('slug', ['closed', 'cancelled', 'completed']))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($workOrders->isEmpty()) {
            return [
                'type' => 'interactive_buttons',
                'header' => '📭 No Active Orders',
                'body' => "You have no active service requests.",
                'footer' => "📞 {$this->helplineUAN}",
                'buttons' => [
                    ['id' => 'menu_book', 'title' => '📱 Book Service'],
                    ['id' => 'menu_main', 'title' => '↩️ Menu'],
                ],
            ];
        }

        $list = $workOrders->map(
            fn($wo) =>
            $this->getStatusEmoji($wo->status?->slug) . " *{$wo->work_order_number}*\n    {$wo->status?->name}"
        )->join("\n\n");

        return [
            'type' => 'interactive_buttons',
            'header' => '📋 Your Active Orders',
            'body' => $list,
            'footer' => 'Reply with order # for details',
            'buttons' => [
                ['id' => 'menu_track', 'title' => '🔍 Get Details'],
                ['id' => 'menu_main', 'title' => '↩️ Menu'],
            ],
        ];
    }

    /**
     * Show booking information.
     */
    protected function showBookingInfo(): array
    {
        $this->setState('main_menu');

        return [
            'type' => 'interactive_buttons',
            'header' => '📱 Book a Service',
            'body' => "To book a service:\n\n" .
                "🌐 Web: {$this->companyWebsite}\n" .
                "📞 Call: {$this->helplineUAN}\n\n" .
                "_WhatsApp booking coming soon!_",
            'footer' => 'AC, Plumbing, Electric & more!',
            'buttons' => [
                ['id' => 'menu_contact', 'title' => '📞 Contact Us'],
                ['id' => 'menu_main', 'title' => '↩️ Menu'],
            ],
        ];
    }

    /**
     * Show contact information.
     */
    protected function showContactInfo(): array
    {
        $this->setState('main_menu');

        return [
            'type' => 'interactive_buttons',
            'header' => '📞 Contact Us',
            'body' => "*{$this->companyName}*\n\n" .
                "📞 UAN: {$this->helplineUAN}\n" .
                "📧 Email: {$this->companyEmail}\n" .
                "🌐 Web: {$this->companyWebsite}\n\n" .
                "🕐 Mon-Sat: 8AM - 8PM",
            'footer' => "We're here to help!",
            'buttons' => [
                ['id' => 'menu_agent', 'title' => '👤 Chat with Agent'],
                ['id' => 'menu_main', 'title' => '↩️ Menu'],
            ],
        ];
    }

    /**
     * Prompt to talk to agent.
     */
    protected function promptTalkAgent(): array
    {
        $this->setState('talk_agent');

        $msg = "Type your message and we'll connect you.\n\n";
        if (!$this->isBusinessHours()) {
            $msg .= "⏰ _We're offline now. Response {$this->getNextOpeningTime()}_";
        } else {
            $msg .= "_Response time: ~5-10 mins_";
        }

        return [
            'type' => 'interactive_buttons',
            'header' => '👤 Talk to Agent',
            'body' => $msg,
            'footer' => "📞 Urgent? {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Menu'],
            ],
        ];
    }

    /**
     * Handle talk to agent.
     */
    protected function handleTalkAgent(string $message, WhatsAppConversation $conversation): array
    {
        if ($message === '0' || $message === 'menu_main') {
            $this->setState('main_menu');
            return $this->getMainMenuResponse();
        }

        $conversation->update(['status' => 'open', 'notes' => "Agent request: {$message}"]);
        $this->setState('main_menu');

        $response = "✅ *Message Sent!*\n\n" .
            "📩 _\"{$message}\"_\n\n";

        if (!$this->isBusinessHours()) {
            $response .= "⏰ We'll respond {$this->getNextOpeningTime()}.";
        } else {
            $response .= "Our team will reply shortly.";
        }

        return [
            'type' => 'interactive_buttons',
            'header' => '✅ Received!',
            'body' => $response,
            'footer' => "📞 Urgent? {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Menu'],
            ],
        ];
    }

    /**
     * Prompt for other message.
     */
    protected function promptOtherMessage(): array
    {
        $this->setState('other_message');

        return [
            'type' => 'interactive_buttons',
            'header' => '💬 General Inquiry',
            'body' => "Please type your question or feedback.",
            'footer' => "📞 {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Menu'],
            ],
        ];
    }

    /**
     * Handle other message.
     */
    protected function handleOtherMessage(string $message, WhatsAppConversation $conversation): array
    {
        if ($message === '0' || $message === 'menu_main') {
            $this->setState('main_menu');
            return $this->getMainMenuResponse();
        }

        $conversation->update(['notes' => "Inquiry: {$message}"]);
        $this->setState('main_menu');

        return [
            'type' => 'interactive_buttons',
            'header' => '✅ Received',
            'body' => "Thank you! We've got your message.\n\n📩 _\"{$message}\"_",
            'footer' => "Thanks for reaching out!",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Menu'],
            ],
        ];
    }

    /**
     * Get invalid option response.
     */
    protected function getInvalidOptionResponse(): array
    {
        return [
            'type' => 'interactive_buttons',
            'header' => '❓ Invalid Option',
            'body' => "Please select from the menu or type *menu* for options.",
            'footer' => "📞 {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '📋 Show Menu'],
            ],
        ];
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
            'scheduled' => '📅',
            default => '⚪',
        };
    }

    /**
     * Find customer by phone.
     */
    protected function findCustomerByPhone(?string $phone): ?Customer
    {
        if (!$phone) return null;

        $normalized = preg_replace('/[^0-9]/', '', $phone);

        return Customer::where('phone', 'like', '%' . $normalized)
            ->orWhere('whatsapp', 'like', '%' . $normalized)
            ->orWhere('phone', 'like', '%' . substr($normalized, -10))
            ->first();
    }

    /**
     * Get session state.
     */
    protected function getSessionState(): string
    {
        if (!$this->phoneNumber) return 'new';
        return Cache::get("whatsapp_bot:{$this->phoneNumber}", 'new');
    }

    /**
     * Set session state.
     */
    protected function setState(string $state): void
    {
        if ($this->phoneNumber) {
            Cache::put("whatsapp_bot:{$this->phoneNumber}", $state, $this->sessionTtl);
        }
        $this->state = $state;
    }
}
