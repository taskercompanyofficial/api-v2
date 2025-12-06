<?php


namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\SocialHandler;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SocialHandlersController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);

        $query = SocialHandler::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'name', 'slug', 'icon', 'url', 'is_active', 'order', 'created_at', 'updated_at'
        ]);

        $socialHandlers = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $socialHandlers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'is_active' => 'boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        // Generate unique slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug; $i = 1;
        while (SocialHandler::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $i; $i++;
        }

        $user = $request->user();

        $socialHandler = SocialHandler::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => $validated['icon'],
            'url' => $validated['url'],
            'is_active' => $validated['is_active'] ?? true,
            'order' => $validated['order'] ?? 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Social handler created successfully',
            'slug' => $socialHandler->slug,
        ]);
    }

    public function show(string $slug)
    {
        $socialHandler = SocialHandler::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->where('slug', $slug)->first();
        if (!$socialHandler) {
            return response()->json(['status' => 'error', 'message' => 'Social handler not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $socialHandler]);
    }

    public function update(Request $request, string $slug)
    {
        $socialHandler = SocialHandler::where('slug', $slug)->first();
        if (!$socialHandler) {
            return response()->json(['status' => 'error', 'message' => 'Social handler not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'icon' => 'sometimes|required|string|max:255',
            'url' => 'sometimes|required|url|max:500',
            'is_active' => 'boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        // Handle slug update if name changes
        if (isset($validated['name']) && $validated['name'] !== $socialHandler->name) {
            $newSlug = Str::slug($validated['name']);
            $original = $newSlug; $i = 1;
            while (SocialHandler::where('slug', $newSlug)->where('id', '!=', $socialHandler->id)->exists()) {
                $newSlug = $original . '-' . $i; $i++;
            }
            $validated['slug'] = $newSlug;
        }

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        $socialHandler->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Social handler updated successfully',
            'slug' => $socialHandler->slug
        ]);
    }

    public function destroy(string $slug)
    {
        $socialHandler = SocialHandler::where('slug', $slug)->first();
        if (!$socialHandler) {
            return response()->json(['status' => 'error', 'message' => 'Social handler not found'], 404);
        }
        $socialHandler->delete();
        return response()->json(['status' => 'success', 'message' => 'Social handler deleted successfully']);
    }
}
