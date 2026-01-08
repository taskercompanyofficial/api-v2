<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Nnjeim\World\Models\City;

class CityController extends Controller
{
    /**
     * Priority cities to show by default
     */
    private array $priorityCities = ['Lahore', 'Islamabad', 'Rawalpindi'];

    /**
     * Get cities for SearchSelect component
     * Returns cities in {value, label} format
     * Shows priority cities by default, all cities when searching
     */
    public function citiesRaw(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $showAll = $request->boolean('show_all', false);

            // If searching or requested all, return all matching cities
            if (!empty($search) || $showAll) {
                $query = City::query()
                    ->where('country_code', 'PK')
                    ->when(!empty($search), function ($q) use ($search) {
                        $q->where('name', 'LIKE', '%' . $search . '%');
                    })
                    ->select('id', 'name')
                    ->orderByRaw("CASE WHEN name IN ('" . implode("','", $this->priorityCities) . "') THEN 0 ELSE 1 END")
                    ->orderBy('name')
                    ->limit(100);

                $cities = $query->get()->map(function ($city) {
                    return [
                        'value' => $city->id,
                        'label' => $city->name,
                    ];
                });
            } else {
                // Default: show only priority cities + "Other Cities..." option
                $priorityCities = City::query()
                    ->where('country_code', 'PK')
                    ->whereIn('name', $this->priorityCities)
                    ->select('id', 'name')
                    ->orderByRaw("FIELD(name, '" . implode("','", $this->priorityCities) . "')")
                    ->get()
                    ->map(function ($city) {
                        return [
                            'value' => $city->id,
                            'label' => $city->name,
                        ];
                    });

                // Add "Other Cities..." option that triggers show_all
                $cities = $priorityCities->push([
                    'value' => 'other',
                    'label' => '🔍 Search for other cities...',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $cities,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
