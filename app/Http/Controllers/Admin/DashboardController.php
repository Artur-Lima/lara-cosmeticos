<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Estoque baixo: alerta para a Lara repor antes de esgotar. */
    public const LOW_STOCK = 2;

    public function __invoke(): View
    {
        $statusCounts = Product::toBase()
            ->selectRaw('status, count(*) as total')
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lowStock = ProductVariant::query()
            ->with('product:id,name')
            ->whereHas('product', fn ($q) => $q->where('status', ProductStatus::Active))
            ->where('is_active', true)
            ->where('stock', '<=', self::LOW_STOCK)
            ->orderBy('stock')
            ->limit(15)
            ->get();

        return view('admin.dashboard', [
            'published' => (int) ($statusCounts[ProductStatus::Active->value] ?? 0),
            'drafts' => (int) ($statusCounts[ProductStatus::Draft->value] ?? 0),
            'lowStock' => $lowStock,
            'hasShippingZones' => ShippingZone::where('is_active', true)->exists(),
            'toHandle' => Order::toHandle()->count(),
            'attention' => Order::needsAttention()->latest()->limit(10)->get(),
            'paidToday' => Order::whereDate('paid_at', today())->whereNot('status', 'cancelado')->sum('total'),
        ]);
    }
}
