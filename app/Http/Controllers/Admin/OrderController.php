<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'atencao' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:60'],
        ]);

        $orders = Order::query()
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['atencao'] ?? false, fn ($q) => $q->needsAttention())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('customer->name', 'like', $like));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'filters'));
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load(['items.variant.product', 'history.user', 'user'])]);
    }

    public function updateStatus(Request $request, Order $order, CancelOrder $cancel): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $to = OrderStatus::from($data['status']);

        if (! $order->canTransitionTo($to)) {
            return back()->with('error', "Não é possível mudar de \"{$order->status->label()}\" para \"{$to->label()}\".");
        }

        if ($to === OrderStatus::Cancelled) {
            $wasPaid = $order->status->isPaid();
            $cancel->handle($order, $data['note'] ?: 'Cancelado pela loja.', $request->user());

            return back()->with('success', $wasPaid
                ? 'Pedido cancelado e estoque devolvido. Faça o estorno do pagamento no painel do Asaas.'
                : 'Pedido cancelado.');
        }

        $order->transitionTo($to, $data['note'] ?? null, $request->user());

        return back()->with('success', "Pedido marcado como \"{$to->label()}\".");
    }

    public function resolveAttention(Request $request, Order $order): RedirectResponse
    {
        $order->update(['needs_attention' => false]);
        $order->history()->create([
            'from_status' => $order->status->value,
            'to_status' => $order->status->value,
            'note' => 'Alerta resolvido: '.$order->attention_reason,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Alerta marcado como resolvido.');
    }
}
