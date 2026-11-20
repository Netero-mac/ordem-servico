<?php

namespace App\Actions\ServiceOrders;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StoreServiceOrderPhotos
{
    public const MAX_PHOTOS = 5;

    public const MAX_SIZE_KB = 5 * 1024;

    /**
     * Validação de cada arquivo (reaproveitada pelo componente Livewire).
     *
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_SIZE_KB];
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<ServiceOrderPhoto>
     *
     * @throws ValidationException quando ultrapassa o limite de fotos da OS
     */
    public function handle(ServiceOrder $order, array $files, string $errorKey = 'photos'): array
    {
        $available = self::MAX_PHOTOS - $order->photos()->count();

        if (count($files) > $available) {
            throw ValidationException::withMessages([
                $errorKey => $available > 0
                    ? "Esta OS só aceita mais {$available} foto(s). O limite é ".self::MAX_PHOTOS.'.'
                    : 'Esta OS já tem o limite de '.self::MAX_PHOTOS.' fotos.',
            ]);
        }

        $photos = [];

        foreach ($files as $file) {
            $path = $file->store("service-orders/{$order->id}", config('filesystems.photos_disk'));

            $photos[] = $order->photos()->create(['path' => $path]);
        }

        return $photos;
    }
}
