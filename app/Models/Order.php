<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    // Sem 0/O/1/I: código ditado por telefone/WhatsApp sem confusão.
    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    protected $guarded = ['id'];

    protected $hidden = ['pix_qr_image'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'customer' => 'array',
            'address' => 'array',
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'needs_attention' => 'boolean',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'pix_expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public static function generateCode(): string
    {
        do {
            $code = 'LL-'.collect(range(1, 6))->map(fn () => self::CODE_ALPHABET[random_int(0, 31)])->implode('');
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function scopeNeedsAttention(Builder $query): void
    {
        $query->where('needs_attention', true);
    }

    /** Pedidos que a loja precisa mexer agora (pagos e ainda não concluídos). */
    public function scopeToHandle(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery]);
    }

    /** Muda o status e registra no histórico. Não valida a transição: quem chama decide. */
    public function transitionTo(OrderStatus $to, ?string $note = null, ?User $by = null): void
    {
        $from = $this->status;

        $this->status = $to;
        if ($to === OrderStatus::Paid && ! $this->paid_at) {
            $this->paid_at = now();
        }
        if ($to === OrderStatus::Cancelled) {
            $this->cancelled_at = now();
        }
        $this->save();

        $this->history()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note ? Str::limit($note, 495) : null,
            'user_id' => $by?->id,
        ]);
    }

    public function flagAttention(string $reason): void
    {
        $this->needs_attention = true;
        $this->attention_reason = Str::limit(trim(($this->attention_reason ? $this->attention_reason.' | ' : '').$reason), 495);
        $this->save();
    }

    public function canTransitionTo(OrderStatus $to): bool
    {
        return in_array($to, $this->status->nextFor($this->delivery_method), true);
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === OrderStatus::AwaitingPayment;
    }

    public function whatsappSummary(): string
    {
        $lines = $this->items->map(fn (OrderItem $i) => "{$i->quantity}x {$i->product_name} ({$i->variant_label})")->implode("\n");

        return "Pedido {$this->code}\n{$lines}\nTotal: R$ ".number_format((float) $this->total, 2, ',', '.');
    }
}
