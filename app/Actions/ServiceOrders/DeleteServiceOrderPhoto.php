<?php

namespace App\Actions\ServiceOrders;

use App\Models\ServiceOrderPhoto;
use Illuminate\Support\Facades\Storage;

class DeleteServiceOrderPhoto
{
    public function handle(ServiceOrderPhoto $photo): void
    {
        Storage::disk(config('filesystems.photos_disk'))->delete($photo->path);

        $photo->delete();
    }
}
