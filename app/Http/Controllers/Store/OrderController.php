<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('store.orders.index', [
            'orders' => $request->user()->orders()->withCount('items')->paginate(10),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOwner($request, $order);

        return view('store.orders.show', ['order' => $order->load(['items', 'history'])]);
    }

    /** Consultado pela página do PIX a cada poucos segundos até o pagamento cair. */
    public function status(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        return response()->json([
            'status' => $order->status->value,
            'paid' => $order->status->isPaid(),
        ]);
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        // 404 (e não 403) para não confirmar que o código existe.
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 404);
    }
}
