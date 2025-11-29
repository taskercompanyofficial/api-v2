<?php

namespace App\Http\Controllers;

use App\Models\Options;
use Illuminate\Http\Request;

class OptionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $options = Options::all();
        return response()->json($options);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|unique:options,key',
            'name' => 'nullable|string|unique:options,name',
            'value' => 'nullable|array',
        ]);
        try {
            $option = Options::create($request->all());
            return response()->json(['status' => 'success', 'message' => 'Option created successfully', 'data' => $option]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to create option. ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($key)
    {
        $option = Options::where('key', $key)->first();
        if (!$option) {
            return response()->json(['status' => 'error', 'message' => 'Option not found'], 404);
        }
        return response()->json($option);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Options $options)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Options $options)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Options $options)
    {
        //
    }
}
