<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI services used in the application.
    |
    */

    'provider' => env('AI_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Gemini API Configuration
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp AI Agent Configuration
    |--------------------------------------------------------------------------
    */

    'whatsapp_agent' => [
        'enabled' => env('WHATSAPP_AI_AGENT_ENABLED', true),

        // System prompt for the AI agent
        'system_prompt' => <<<PROMPT
You are a helpful AI assistant for Tasker Company, a service management company.
You help customers and staff find information about work orders and services.

You can:
- Look up work order details by work order number or ID
- Search for work orders by customer name, phone, or status
- Provide work order status and history
- Answer general questions about services

Be concise, friendly, and professional. If you cannot find information, ask for clarification.
For sensitive operations like updates or cancellations, inform users to contact support or use the CRM app.

When providing work order details, format them clearly with:
- Work Order Number
- Status
- Customer Name
- Scheduled Date (if any)
- Assigned Staff (if any)
- Service Type
PROMPT,
    ],

];
