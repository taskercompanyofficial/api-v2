<?php


namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\MajorClient;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MajorClientsController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);

        $query = MajorClient::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'name', 'slug', 'status', 'customer_type', 'created_at', 'updated_at'
        ]);

        $clients = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $clients]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'notes' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,draft,archived',
            'units_installed' => 'nullable|integer|min:0',
            'customer_type' => 'nullable|string|in:corporate,individual,government,other',
        ]);

        // Generate unique slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug; $i = 1;
        while (MajorClient::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $i; $i++;
        }

        $user = $request->user();

        $client = MajorClient::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'],
            'units_installed' => $validated['units_installed'] ?? null,
            'customer_type' => $validated['customer_type'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Major client created successfully',
            'slug' => $client->slug,
        ]);
    }

    public function show(string $slug)
    {
        $client = MajorClient::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->where('slug', $slug)->first();
        if (!$client) {
            return response()->json(['status' => 'error', 'message' => 'Major client not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $client]);
    }

    public function update(Request $request, string $slug)
    {
        $client = MajorClient::where('slug', $slug)->first();
        if (!$client) {
            return response()->json(['status' => 'error', 'message' => 'Major client not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|string|in:active,inactive,draft,archived',
            'units_installed' => 'nullable|integer|min:0',
            'customer_type' => 'nullable|string|in:corporate,individual,government,other',
        ]);

        // Handle slug update if name changes
        if (isset($validated['name']) && $validated['name'] !== $client->name) {
            $newSlug = Str::slug($validated['name']);
            $original = $newSlug; $i = 1;
            while (MajorClient::where('slug', $newSlug)->where('id', '!=', $client->id)->exists()) {
                $newSlug = $original . '-' . $i; $i++;
            }
            $validated['slug'] = $newSlug;
        }

        // Handle tags array
        if (isset($validated['tags']) && is_array($validated['tags'])) {
            $validated['tags'] = array_values($validated['tags']);
        } else {
            $validated['tags'] = $client->tags ?? [];
        }

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        $client->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Major client updated successfully',
            'slug' => $client->slug
        ]);
    }

    public function destroy(string $slug)
    {
        $client = MajorClient::where('slug', $slug)->first();
        if (!$client) {
            return response()->json(['status' => 'error', 'message' => 'Major client not found'], 404);
        }
        $client->delete();
        return response()->json(['status' => 'success', 'message' => 'Major client deleted successfully']);
    }
    public function getBrandsMeta()
    {
        $brands = MajorClient::select('id', 'name', 'slug', 'image')->where('status', 'active')->get();
        return response()->json(['status' => 'success', 'data' => $brands]);
    }
}
