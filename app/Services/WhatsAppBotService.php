<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WorkOrder;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppBotService
{
    protected WhatsAppService $whatsappService;
    protected ?Customer $customer = null;
    protected ?string $phoneNumber = null;
    protected string $state = 'main_menu';

    // Cache TTL for session state (30 minutes)
    protected int $sessionTtl = 1800;

    // Company contact info
    protected string $companyName = 'Tasker Company';
    protected string $helplineUAN = '0304-111-2717';
    protected string $companyEmail = 'info@taskercompany.com';
    protected string $companyWebsite = 'taskercompany.com';

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Process incoming message and return response type.
     * Returns array with 'type' and message data.
     */
    public function processMessage(string $message, WhatsAppConversation $conversation): array
    {
        $this->phoneNumber = $conversation->contact->phone_number ?? null;
        $this->customer = $this->findCustomerByPhone($this->phoneNumber);
        $this->state = $this->getSessionState();

        $message = trim($message);
        $messageLower = strtolower($message);

        Log::info('WhatsApp Bot processing', [
            'phone' => $this->phoneNumber,
            'customer' => $this->customer?->name,
            'state' => $this->state,
            'message' => $message,
        ]);

        // Check for menu reset commands
        if (in_array($messageLower, ['menu', 'start', 'hi', 'hello', 'main', '0', 'salam', 'assalam', 'aoa'])) {
            $this->setState('main_menu');
            return $this->getMainMenuResponse();
        }

        // Handle interactive button/list replies
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
     * Get the main menu as interactive list.
     */
    protected function getMainMenuResponse(): array
    {
        $greeting = $this->customer
            ? "Assalam o Alaikum {$this->customer->name}! 👋"
            : "Assalam o Alaikum! 👋";

        $body = "{$greeting}

Welcome to *{$this->companyName}* 🏠
Your trusted home services partner.

How can we help you today?

📞 Helpline: {$this->helplineUAN}
📧 Email: {$this->companyEmail}";

        return [
            'type' => 'interactive_list',
            'header' => '🏠 Tasker Company',
            'body' => $body,
            'footer' => 'Select an option below',
            'button' => 'Choose Option',
            'sections' => [
                [
                    'title' => 'Services',
                    'rows' => [
                        [
                            'id' => 'menu_track',
                            'title' => '🔍 Track Order',
                            'description' => 'Check status of your work order',
                        ],
                        [
                            'id' => 'menu_bookings',
                            'title' => '📋 My Bookings',
                            'description' => 'View all your active bookings',
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
                            'description' => 'Connect with our support team',
                        ],
                        [
                            'id' => 'menu_contact',
                            'title' => '📞 Contact Info',
                            'description' => 'Get our contact details',
                        ],
                        [
                            'id' => 'menu_other',
                            'title' => '💬 Other Inquiry',
                            'description' => 'Send us a message',
                        ],
                    ],
                ],
            ],
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
     * Prompt for work order number with buttons.
     */
    protected function promptCheckStatus(): array
    {
        $this->setState('check_status');

        return [
            'type' => 'interactive_buttons',
            'header' => '🔍 Track Order Status',
            'body' => "Please type your work order number.\n\nExample: *WO-2025-00123* or just the number like *123*",
            'footer' => "📞 Need help? Call {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
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

        // Clean the input
        $woNumber = strtoupper(trim($input));

        // Query work order
        $query = WorkOrder::with(['customer', 'status', 'subStatus', 'assignedTo', 'service'])
            ->where(function ($q) use ($woNumber) {
                $q->where('work_order_number', $woNumber)
                    ->orWhere('work_order_number', 'like', "%{$woNumber}%")
                    ->orWhere('id', is_numeric($woNumber) ? $woNumber : 0);
            });

        // Scope to customer if known
        if ($this->customer) {
            $query->where('customer_id', $this->customer->id);
        }

        $workOrder = $query->first();

        if (!$workOrder) {
            return [
                'type' => 'interactive_buttons',
                'header' => '❌ Order Not Found',
                'body' => "We couldn't find work order: *{$woNumber}*" .
                    ($this->customer ? " in your account." : ".") .
                    "\n\nPlease check the number and try again.",
                'footer' => "📞 Need help? Call {$this->helplineUAN}",
                'buttons' => [
                    ['id' => 'menu_track', 'title' => '🔄 Try Again'],
                    ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
                ],
            ];
        }

        // Reset state
        $this->setState('main_menu');

        $statusEmoji = $this->getStatusEmoji($workOrder->status?->slug);
        $scheduled = $workOrder->scheduled_date?->format('d M Y') ?? 'To be scheduled';
        $assignee = $workOrder->assignedTo?->first_name ?? 'Not yet assigned';

        return [
            'type' => 'interactive_buttons',
            'header' => "📋 {$workOrder->work_order_number}",
            'body' => "{$statusEmoji} *Status:* {$workOrder->status?->name}" .
                ($workOrder->subStatus ? "\n📍 {$workOrder->subStatus->name}" : "") .
                "\n\n🔧 *Service:* {$workOrder->service?->name}" .
                "\n👤 *Customer:* {$workOrder->customer?->name}" .
                "\n👷 *Technician:* {$assignee}" .
                "\n📅 *Scheduled:* {$scheduled}" .
                "\n\n_Order created: {$workOrder->created_at->format('d M Y')}_",
            'footer' => "📞 Questions? Call {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_track', 'title' => '🔍 Check Another'],
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
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
                'body' => "We couldn't find an account linked to this phone number.\n\nTo view your bookings, please provide your work order number instead.",
                'footer' => "📞 Need help? Call {$this->helplineUAN}",
                'buttons' => [
                    ['id' => 'menu_track', 'title' => '🔍 Track by Order #'],
                    ['id' => 'menu_agent', 'title' => '👤 Talk to Agent'],
                ],
            ];
        }

        $workOrders = WorkOrder::with(['status', 'service'])
            ->where('customer_id', $this->customer->id)
            ->whereHas('status', function ($q) {
                $q->whereNotIn('slug', ['closed', 'cancelled', 'completed']);
            })
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        if ($workOrders->isEmpty()) {
            return [
                'type' => 'interactive_buttons',
                'header' => '📭 No Active Bookings',
                'body' => "Hi {$this->customer->name}!\n\nYou don't have any active service requests at the moment.\n\nWould you like to book a new service?",
                'footer' => "📞 Call us: {$this->helplineUAN}",
                'buttons' => [
                    ['id' => 'menu_book', 'title' => '📱 Book Service'],
                    ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
                ],
            ];
        }

        $list = $workOrders->map(function ($wo) {
            $emoji = $this->getStatusEmoji($wo->status?->slug);
            return "{$emoji} *{$wo->work_order_number}*\n     {$wo->status?->name} • {$wo->service?->name}";
        })->join("\n\n");

        return [
            'type' => 'interactive_buttons',
            'header' => "📋 Your Active Bookings",
            'body' => "Hi {$this->customer->name}! Here are your active orders:\n\n{$list}",
            'footer' => "Reply with order # for details",
            'buttons' => [
                ['id' => 'menu_track', 'title' => '🔍 Get Details'],
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
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
            'body' => "To book a new home service, you can:\n\n" .
                "🌐 *Website:* {$this->companyWebsite}\n" .
                "📲 *App:* Download from Play Store\n" .
                "📞 *Call:* {$this->helplineUAN}\n\n" .
                "_WhatsApp booking coming soon!_ 🚀",
            'footer' => "We offer AC, Plumbing, Electric & more!",
            'buttons' => [
                ['id' => 'menu_contact', 'title' => '📞 Contact Us'],
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
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
            'body' => "*{$this->companyName}*\n" .
                "Your Trusted Home Services Partner\n\n" .
                "━━━━━━━━━━━━━━━━━━━━\n\n" .
                "📞 *UAN:* {$this->helplineUAN}\n" .
                "📧 *Email:* {$this->companyEmail}\n" .
                "🌐 *Web:* {$this->companyWebsite}\n\n" .
                "━━━━━━━━━━━━━━━━━━━━\n\n" .
                "🕐 *Business Hours:*\n" .
                "Mon-Sat: 9:00 AM - 6:00 PM\n" .
                "Sunday: Emergency Only",
            'footer' => "We're here to help!",
            'buttons' => [
                ['id' => 'menu_agent', 'title' => '👤 Chat with Agent'],
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
            ],
        ];
    }

    /**
     * Prompt to talk to agent.
     */
    protected function promptTalkAgent(): array
    {
        $this->setState('talk_agent');

        return [
            'type' => 'interactive_buttons',
            'header' => '👤 Talk to Representative',
            'body' => "Please describe your query or concern.\n\nType your message and our team will respond shortly.\n\n_Average response time: 5-10 minutes during business hours_",
            'footer' => "📞 Urgent? Call {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
            ],
        ];
    }

    /**
     * Handle talk to agent flow.
     */
    protected function handleTalkAgent(string $message, WhatsAppConversation $conversation): array
    {
        if ($message === '0' || $message === 'menu_main') {
            $this->setState('main_menu');
            return $this->getMainMenuResponse();
        }

        // Mark conversation as needing human attention
        $conversation->update([
            'status' => 'open',
            'notes' => "Customer requested support. Query: {$message}",
        ]);

        $this->setState('main_menu');

        $customerName = $this->customer?->name ?? 'Valued Customer';

        return [
            'type' => 'interactive_buttons',
            'header' => '✅ Message Received!',
            'body' => "Thank you {$customerName}!\n\n" .
                "Your message has been sent to our support team:\n\n" .
                "📩 _\"{$message}\"_\n\n" .
                "A representative will respond shortly.\n" .
                "_Business hours: 9 AM - 6 PM_",
            'footer' => "📞 Urgent? Call {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
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
            'body' => "Please type your question or feedback.\n\nWe value your input and will review your message.",
            'footer' => "📞 Quick help: {$this->helplineUAN}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
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

        // Save the inquiry
        $conversation->update([
            'notes' => "General inquiry: {$message}",
        ]);

        $this->setState('main_menu');

        return [
            'type' => 'interactive_buttons',
            'header' => '✅ Message Received',
            'body' => "Thank you for reaching out!\n\nWe've received your message:\n📩 _\"{$message}\"_\n\nOur team will review and respond if needed.",
            'footer' => "Thanks for contacting {$this->companyName}",
            'buttons' => [
                ['id' => 'menu_main', 'title' => '↩️ Main Menu'],
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
            'header' => '❓ Invalid Selection',
            'body' => "Sorry, I didn't understand that.\n\nPlease select an option from the menu or type *menu* to see options.",
            'footer' => "📞 Need help? Call {$this->helplineUAN}",
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
