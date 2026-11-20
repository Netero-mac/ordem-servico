<?php

namespace App\Models;

use App\Enums\ItemType;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_order_id
 * @property ItemType $type
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_cents
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['type', 'description', 'quantity', 'unit_price_cents'])]
class ServiceOrderItem extends Model
{
    /** @use HasFactory<ServiceOrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
        ];
    }

    public function totalCents(): int
    {
        return $this->quantity * $this->unit_price_cents;
    }

    /**
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
