<?php

namespace App\Services\AI;

use App\Models\Customer;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

class GeminiAgentService
{
    protected GeminiService $gemini;
    protected ?Customer $customer = null;
    protected ?string $phoneNumber = null;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Process a user message and return AI response.
     *
     * @param string $message User's message
     * @param array $context Context with phone number
     * @return string AI response text
     */
    public function processMessage(string $message, array $context = []): string
    {
        try {
            // Store phone number and look up customer
            $this->phoneNumber = $context['phone'] ?? null;
            $this->customer = $this->findCustomerByPhone($this->phoneNumber);

            Log::info('AI Agent processing message', [
                'phone' => $this->phoneNumber,
                'customer_id' => $this->customer?->id,
                'customer_name' => $this->customer?->name,
            ]);

            $contents = $this->buildConversation($message, $context);
            $tools = $this->getToolDefinitions();
            $systemPrompt = $this->buildSystemPrompt();

            // First API call
            $result = $this->gemini->generateContent($contents, $tools, $systemPrompt);

            // Handle function calls if present
            $maxIterations = 5;
            $iteration = 0;

            while ($this->gemini->hasFunctionCalls($result) && $iteration < $maxIterations) {
                $functionCalls = $this->gemini->extractFunctionCalls($result);

                foreach ($functionCalls as $functionCall) {
                    $functionName = $functionCall['name'];
                    $args = $functionCall['args'] ?? [];

                    Log::info('AI Agent function call', [
                        'function' => $functionName,
                        'args' => $args,
                    ]);

                    // Execute the function
                    $functionResult = $this->executeFunction($functionName, $args);

                    // Add model's response with function call to contents
                    $contents[] = [
                        'role' => 'model',
                        'parts' => [
                            [
                                'functionCall' => [
                                    'name' => $functionName,
                                    'args' => $args,
                                ],
                            ],
                        ],
                    ];

                    // Add function response
                    $contents[] = $this->gemini->buildFunctionResponse($functionName, $functionResult);
                }

                // Get next response
                $result = $this->gemini->generateContent($contents, $tools, $systemPrompt);
                $iteration++;
            }

            // Extract final text response
            $response = $this->gemini->extractTextResponse($result);

            if (!$response) {
                return "I'm sorry, I couldn't process your request. Please try again.";
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('GeminiAgentService error', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
            return "I'm sorry, I encountered an error. Please try again later.";
        }
    }

    /**
     * Find customer by phone number.
     */
    protected function findCustomerByPhone(?string $phone): ?Customer
    {
        if (!$phone) {
            return null;
        }

        // Normalize phone - remove WhatsApp suffix and try variations
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

        return Customer::where('phone', 'like', '%' . $normalizedPhone)
            ->orWhere('whatsapp', 'like', '%' . $normalizedPhone)
            ->orWhere('phone', 'like', '%' . substr($normalizedPhone, -10)) // Last 10 digits
            ->first();
    }

    /**
     * Build system prompt with customer context.
     */
    protected function buildSystemPrompt(): string
    {
        $basePrompt = <<<PROMPT
You are a helpful AI assistant for Tasker Company, a service management company.
You help customers check their work order status and service requests.

Guidelines:
- Be friendly, concise, and professional
- Use Urdu/Roman Urdu if the customer writes in Urdu
- Always verify work orders belong to the customer before sharing details
- For updates or cancellations, ask customers to contact support or use the app
- Format responses for WhatsApp (use *bold* and _italic_ for emphasis)
PROMPT;

        // Add customer context if found
        if ($this->customer) {
            $basePrompt .= "\n\n---\nCUSTOMER CONTEXT:";
            $basePrompt .= "\n- Customer Name: {$this->customer->name}";
            $basePrompt .= "\n- Customer ID: {$this->customer->id}";
            $basePrompt .= "\n- Phone: {$this->customer->phone}";
            $basePrompt .= "\n\nIMPORTANT: Greet the customer by name. Only show work orders that belong to this customer (customer_id = {$this->customer->id}).";
        } else {
            $basePrompt .= "\n\n---\nNOTE: Customer not found in database. Ask them to provide their phone number or work order number to help them.";
        }

        return $basePrompt;
    }

    /**
     * Build conversation contents.
     */
    protected function buildConversation(string $message, array $context): array
    {
        $contents = [];

        // Add conversation history if provided
        if (isset($context['history']) && is_array($context['history'])) {
            foreach ($context['history'] as $msg) {
                if ($msg['role'] === 'user') {
                    $contents[] = $this->gemini->buildUserMessage($msg['content']);
                } else {
                    $contents[] = $this->gemini->buildModelMessage($msg['content']);
                }
            }
        }

        $contents[] = $this->gemini->buildUserMessage($message);

        return $contents;
    }

    /**
     * Get tool definitions for function calling.
     */
    protected function getToolDefinitions(): array
    {
        return [
            [
                'function_declarations' => [
                    [
                        'name' => 'get_my_work_orders',
                        'description' => 'Get all work orders for the current customer. Use when customer asks to see their complaints, orders, or requests.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => [
                                    'type' => 'string',
                                    'description' => 'Filter by status: pending, in-progress, completed, cancelled, or all',
                                ],
                                'limit' => [
                                    'type' => 'integer',
                                    'description' => 'Number of results (default: 5)',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_work_order_details',
                        'description' => 'Get details of a specific work order by number. Use when customer asks about a specific work order.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'work_order_number' => [
                                    'type' => 'string',
                                    'description' => 'The work order number (e.g., WO-2025-00001)',
                                ],
                            ],
                            'required' => ['work_order_number'],
                        ],
                    ],
                    [
                        'name' => 'get_work_order_history',
                        'description' => 'Get status history of a work order. Use when customer asks what happened with their order.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'work_order_number' => [
                                    'type' => 'string',
                                    'description' => 'The work order number',
                                ],
                            ],
                            'required' => ['work_order_number'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a function call.
     */
    protected function executeFunction(string $functionName, array $args): array
    {
        return match ($functionName) {
            'get_my_work_orders' => $this->getMyWorkOrders($args),
            'get_work_order_details' => $this->getWorkOrderDetails($args['work_order_number'] ?? ''),
            'get_work_order_history' => $this->getWorkOrderHistory($args['work_order_number'] ?? ''),
            default => ['error' => 'Unknown function: ' . $functionName],
        };
    }

    /**
     * Get work orders for the current customer.
     */
    protected function getMyWorkOrders(array $args): array
    {
        if (!$this->customer) {
            return [
                'found' => false,
                'message' => 'Customer not recognized. Please provide your phone number or work order number.',
            ];
        }

        $query = WorkOrder::with(['status', 'service', 'assignedTo'])
            ->where('customer_id', $this->customer->id);

        // Filter by status
        if (!empty($args['status']) && $args['status'] !== 'all') {
            $query->whereHas('status', function ($q) use ($args) {
                $q->where('slug', 'like', '%' . $args['status'] . '%')
                    ->orWhere('name', 'like', '%' . $args['status'] . '%');
            });
        }

        $limit = min($args['limit'] ?? 5, 10);
        $workOrders = $query->orderBy('created_at', 'desc')->limit($limit)->get();

        if ($workOrders->isEmpty()) {
            return [
                'found' => false,
                'count' => 0,
                'message' => 'No work orders found for your account.',
            ];
        }

        return [
            'found' => true,
            'count' => $workOrders->count(),
            'customer_name' => $this->customer->name,
            'work_orders' => $workOrders->map(fn($wo) => [
                'number' => $wo->work_order_number,
                'status' => $wo->status?->name ?? 'Unknown',
                'service' => $wo->service?->name,
                'scheduled_date' => $wo->scheduled_date?->format('d M Y'),
                'assigned_to' => $wo->assignedTo?->name,
            ])->toArray(),
        ];
    }

    /**
     * Get work order details (scoped to customer if known).
     */
    protected function getWorkOrderDetails(string $identifier): array
    {
        $query = WorkOrder::with(['customer', 'status', 'subStatus', 'assignedTo', 'service', 'category'])
            ->where(function ($q) use ($identifier) {
                $q->where('work_order_number', $identifier)
                    ->orWhere('id', $identifier);
            });

        // Scope to customer if known
        if ($this->customer) {
            $query->where('customer_id', $this->customer->id);
        }

        $workOrder = $query->first();

        if (!$workOrder) {
            return [
                'found' => false,
                'message' => $this->customer
                    ? "Work order '{$identifier}' not found in your account."
                    : "Work order '{$identifier}' not found. Please verify the number.",
            ];
        }

        return [
            'found' => true,
            'work_order' => [
                'number' => $workOrder->work_order_number,
                'status' => $workOrder->status?->name ?? 'Unknown',
                'sub_status' => $workOrder->subStatus?->name,
                'service' => $workOrder->service?->name,
                'category' => $workOrder->category?->name,
                'customer_name' => $workOrder->customer?->name,
                'assigned_to' => $workOrder->assignedTo?->name,
                'scheduled_date' => $workOrder->scheduled_date?->format('d M Y'),
                'scheduled_time' => $workOrder->scheduled_time,
                'description' => $workOrder->description,
                'created_at' => $workOrder->created_at->format('d M Y'),
            ],
        ];
    }

    /**
     * Get work order history (scoped to customer if known).
     */
    protected function getWorkOrderHistory(string $identifier): array
    {
        $query = WorkOrder::with(['statusHistory.fromStatus', 'statusHistory.toStatus'])
            ->where(function ($q) use ($identifier) {
                $q->where('work_order_number', $identifier)
                    ->orWhere('id', $identifier);
            });

        // Scope to customer if known
        if ($this->customer) {
            $query->where('customer_id', $this->customer->id);
        }

        $workOrder = $query->first();

        if (!$workOrder) {
            return [
                'found' => false,
                'message' => "Work order '{$identifier}' not found.",
            ];
        }

        $history = $workOrder->statusHistory->map(fn($h) => [
            'from' => $h->fromStatus?->name ?? 'New',
            'to' => $h->toStatus?->name,
            'date' => $h->created_at->format('d M Y, h:i A'),
            'notes' => $h->notes,
        ]);

        return [
            'found' => true,
            'work_order_number' => $workOrder->work_order_number,
            'current_status' => $workOrder->status?->name ?? 'Unknown',
            'history' => $history->toArray(),
        ];
    }
}
