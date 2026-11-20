<?php

namespace App\Models;

use App\Actions\ServiceOrders\GenerateServiceOrderNumber;
use App\Enums\ServiceOrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property int $device_id
 * @property int $user_id
 * @property int|null $technician_id
 * @property string $reported_issue
 * @property string|null $diagnosis
 * @property string|null $accessories
 * @property string|null $device_password
 * @property ServiceOrderStatus $status
 * @property string $public_token
 * @property CarbonImmutable|null $estimated_delivery_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $delivered_at
 * @property int $warranty_days
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'customer_id', 'device_id', 'user_id', 'technician_id',
    'reported_issue', 'diagnosis', 'accessories', 'device_password',
    'estimated_delivery_at', 'warranty_days',
])]
#[Hidden(['device_password', 'public_token'])]
class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Tamanho do token do link público (62^64 combinações: impossível de adivinhar).
     */
    public const PUBLIC_TOKEN_LENGTH = 64;

    protected $attributes = [
        'status' => 'received',
        'warranty_days' => 90,
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'device_password' => 'encrypted',
            'estimated_delivery_at' => 'datetime',
            'approved_at' => 'datetime',
            'delivered_at' => 'datetime',
            'warranty_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceOrder $order) {
            $order->number ??= app(GenerateServiceOrderNumber::class)->handle();
            $order->public_token ??= Str::random(self::PUBLIC_TOKEN_LENGTH);
        });

        // Toda OS nasce com a linha de abertura no histórico.
        static::created(function (ServiceOrder $order) {
            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => $order->status,
                'user_id' => $order->user_id,
                'note' => 'Ordem de serviço aberta.',
            ]);
        });
    }

    /**
     * Fim da garantia (conta a partir da entrega).
     */
    public function warrantyEndsAt(): ?CarbonImmutable
    {
        return $this->delivered_at?->addDays($this->warranty_days);
    }

    /**
     * Previsão de entrega vencida e o aparelho ainda está em serviço.
     * "Pronto para retirada" não conta: a loja já cumpriu a parte dela.
     */
    public function isOverdue(): bool
    {
        return $this->estimated_delivery_at !== null
            && $this->estimated_delivery_at->isPast()
            && in_array($this->status, self::overdueStatuses(), true);
    }

    /**
     * @param  Builder<ServiceOrder>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNotNull('estimated_delivery_at')
            ->where('estimated_delivery_at', '<', now())
            ->whereIn('status', self::overdueStatuses());
    }

    /**
     * @return list<ServiceOrderStatus>
     */
    public static function overdueStatuses(): array
    {
        return array_values(array_filter(
            ServiceOrderStatus::openStatuses(),
            fn (ServiceOrderStatus $status) => $status !== ServiceOrderStatus::Ready,
        ));
    }

    /**
     * Total do orçamento em centavos.
     */
    public function totalCents(): int
    {
        return (int) $this->items->sum(fn (ServiceOrderItem $item) => $item->totalCents());
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Quem abriu a OS.
     *
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Técnico responsável.
     *
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * @return HasMany<ServiceOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    /**
     * @return HasMany<ServiceOrderStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ServiceOrderStatusHistory::class)->oldest('created_at')->oldest('id');
    }

    /**
     * @return HasMany<ServiceOrderPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ServiceOrderPhoto::class);
    }
}
