<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega a foto de um disco privado, só para quem pode ver a OS.
 */
class ServiceOrderPhotoController extends Controller
{
    public function __invoke(ServiceOrder $serviceOrder, ServiceOrderPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $serviceOrder);

        abort_unless($photo->service_order_id === $serviceOrder->id, 404);

        return Storage::disk(config('filesystems.photos_disk'))->response($photo->path, headers: [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
