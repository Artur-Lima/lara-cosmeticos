<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShippingZoneController extends Controller
{
    public function index(): View
    {
        return view('admin.shipping-zones.index', [
            'zones' => ShippingZone::orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.shipping-zones.form', ['zone' => new ShippingZone(['is_active' => true, 'position' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        ShippingZone::create($this->validated($request));

        return redirect()->route('admin.shipping-zones.index')->with('success', 'Região de entrega cadastrada.');
    }

    public function edit(ShippingZone $shippingZone): View
    {
        return view('admin.shipping-zones.form', ['zone' => $shippingZone]);
    }

    public function update(Request $request, ShippingZone $shippingZone): RedirectResponse
    {
        $shippingZone->update($this->validated($request, $shippingZone));

        return redirect()->route('admin.shipping-zones.index')->with('success', 'Região de entrega atualizada.');
    }

    public function destroy(ShippingZone $shippingZone): RedirectResponse
    {
        $shippingZone->delete();

        return redirect()->route('admin.shipping-zones.index')->with('success', 'Região de entrega excluída.');
    }

    private function validated(Request $request, ?ShippingZone $zone = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('shipping_zones')->ignore($zone?->id)],
            'fee' => ['required', 'numeric', 'min:0', 'max:999'],
            'free_above' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'estimate' => ['nullable', 'string', 'max:60'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return $data + ['position' => (int) ($data['position'] ?? 0), 'is_active' => $request->boolean('is_active')];
    }
}
