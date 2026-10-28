<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $category = trim($request->string('category')->toString());

        $inventory = Inventory::query()
            ->with('property')
            ->where('available_quantity', '>', 0)
            ->whereHas('property', fn ($query) => $query->whereNotIn('status', ['disposed', 'for_disposal']))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('property', fn ($properties) => $properties
                    ->where('property_name', 'like', "%{$search}%")
                    ->orWhere('property_code', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%"));
            })
            ->when($category !== '', fn ($query) => $query->whereHas('property', fn ($properties) => $properties->where('category', $category)))
            ->orderBy('property_id')
            ->paginate(12)
            ->withQueryString();

        return view('teacher.inventory.index', [
            'inventory' => $inventory,
            'categories' => Inventory::query()->with('property')->get()->pluck('property.category')->filter()->unique()->sort()->values(),
        ]);
    }
}
