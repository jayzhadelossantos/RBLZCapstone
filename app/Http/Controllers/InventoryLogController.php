<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InventoryLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $action = $request->string('action')->toString();

        $logs = InventoryLog::query()
            ->with(['property', 'propertyRequest.requester', 'user'])
            ->when($search !== '', fn ($query) => $query->whereHas('property', fn ($property) => $property
                ->where('property_name', 'like', "%{$search}%")
                ->orWhere('property_code', 'like', "%{$search}%")))
            ->when(in_array($action, InventoryLog::actions(), true), fn ($query) => $query->where('action', $action))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('inventory-logs.index', [
            'logs' => $logs,
            'actions' => InventoryLog::actions(),
        ]);
    }
}
