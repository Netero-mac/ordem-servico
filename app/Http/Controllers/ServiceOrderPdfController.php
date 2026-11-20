<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderPdfController extends Controller
{
    /**
     * Comprovante de entrada: o que o cliente deixou, o defeito e os termos.
     */
    public function entry(ServiceOrder $serviceOrder): Response
    {
        Gate::authorize('view', $serviceOrder);

        return $this->render('pdf.entry-receipt', $serviceOrder, 'entrada');
    }

    /**
     * Comprovante de entrega: serviço feito, valores e garantia.
     */
    public function delivery(ServiceOrder $serviceOrder): Response
    {
        Gate::authorize('printDelivery', $serviceOrder);

        return $this->render('pdf.delivery-receipt', $serviceOrder, 'entrega');
    }

    private function render(string $view, ServiceOrder $order, string $suffix): Response
    {
        $order->load(['customer', 'device', 'items', 'openedBy', 'technician']);

        $sum = fn (ItemType $type): int => (int) $order->items
            ->where('type', $type)
            ->sum(fn (ServiceOrderItem $item) => $item->totalCents());

        return Pdf::loadView($view, [
            'order' => $order,
            'shop' => config('shop'),
            'totals' => [
                'parts' => $sum(ItemType::Part),
                'labor' => $sum(ItemType::Labor),
                'total' => $order->totalCents(),
            ],
        ])
            ->setPaper('a4')
            ->stream("{$order->number}-{$suffix}.pdf");
    }
}
