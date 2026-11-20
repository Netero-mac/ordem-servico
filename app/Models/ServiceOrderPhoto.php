<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServiceOrderPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_order_id
 * @property string $path
 * @property string|null $caption
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['path', 'caption'])]
class ServiceOrderPhoto extends Model
{
    /** @use HasFactory<ServiceOrderPhotoFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
