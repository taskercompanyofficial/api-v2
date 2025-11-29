<?php

namespace App\Http\Controllers;

use App\Models\Appearance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppearanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $appearances = Appearance::all();
        return response()->json([
            'status' => 'success',
            'data' => $appearances,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'theme' => 'required|in:light,dark,system',
            'primary_color' => 'required|string',
            'sidebar_color' => 'required|string',
            'radius' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $appearance = Appearance::updateOrCreate(
            ['id' => 1], // Assuming single appearance record
            [
                'theme' => $request->theme,
                'primary_color' => $request->primary_color,
                'sidebar_color' => $request->sidebar_color,
                'radius' => $request->radius,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $appearance,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        $appearance = Appearance::first();
        
        if (!$appearance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appearance not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $appearance,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'theme' => 'sometimes|in:light,dark,system',
            'primary_color' => 'sometimes|string',
            'sidebar_color' => 'sometimes|string',
            'radius' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $appearance = Appearance::first();

        if (!$appearance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appearance not found',
            ], 404);
        }

        $appearance->update(array_merge(
            $request->only(['theme', 'primary_color', 'sidebar_color', 'radius']),
            ['updated_by' => auth()->id()]
        ));

        return response()->json([
            'status' => 'success',
            'data' => $appearance,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        $appearance = Appearance::first();

        if (!$appearance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appearance not found',
            ], 404);
        }

        $appearance->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Appearance deleted successfully',
        ]);
    }
}
