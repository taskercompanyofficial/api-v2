<?php

namespace App\Http\Controllers\TaskerApp;

use App\Http\Controllers\Controller;
use App\Models\CommercialQuote;
use Illuminate\Http\Request;
use Notification;

class CommercialQuoteController extends Controller
{
    /**
     * Store a new commercial quote request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organizationName' => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'contactPerson' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'facilitySize' => 'nullable|string|max:50',
            'services' => 'required|array|min:1',
            'services.*' => 'string',
            'description' => 'nullable|string',
        ]);

        $quote = CommercialQuote::create([
            'customer_id' => $request->user()->id,
            'organization_name' => $validated['organizationName'],
            'business_type' => $validated['business_type'],
            'contact_person' => $validated['contactPerson'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'facility_size' => $validated['facilitySize'] ?? null,
            'services' => $validated['services'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);
        return response()->json([
            'status' => 'success',
            'message' => 'Quote request submitted successfully. Our team will contact you within 24 hours.',
        ], 201);
    }

    /**
     * Get all quote requests for the authenticated customer
     */
    public function index(Request $request)
    {
        $quotes = CommercialQuote::where('customer_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $quotes,
        ]);
    }

    /**
     * Get a specific quote request
     */
    public function show(Request $request, $id)
    {
        $quote = CommercialQuote::where('customer_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $quote,
        ]);
    }
}
