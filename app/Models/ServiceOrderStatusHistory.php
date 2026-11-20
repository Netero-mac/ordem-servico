<?php

namespace App\Models;

use App\Enums\ServiceOrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceOrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_order_id
 * @property ServiceOrderStatus|null $from_status
 * @property ServiceOrderStatus $to_status
 * @property int|null $user_id
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['from_status', 'to_status', 'user_id', 'note'])]
class ServiceOrderStatusHistory extends Model
{
    /** @use HasFactory<ServiceOrderStatusHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'service_order_status_history';

    protected function casts(): array
    {
        return [
            'from_status' => ServiceOrderStatus::class,
            'to_status' => ServiceOrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * Quem fez a mudança. Nulo quando foi o cliente pelo link público.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
