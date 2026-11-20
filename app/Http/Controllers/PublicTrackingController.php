<?php

namespace App\Http\Controllers;

use App\Actions\ServiceOrders\RecordCustomerDecision;
use App\Enums\ItemType;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Http\Requests\CustomerDecisionRequest;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderStatusHistory;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Página que o cliente abre pelo link do WhatsApp, sem login.
 */
class PublicTrackingController extends Controller
{
    /**
     * Cabeçalhos para não indexar, não guardar em cache e não vazar o token no Referer.
     */
    private const HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Cache-Control' => 'no-store, private',
        'Referrer-Policy' => 'no-referrer',
    ];

    public function show(string $token): Response
    {
        $order = $this->findOrder($token);

        return response()
            ->view('public.tracking', ['order' => $this->publicData($order)])
            ->withHeaders(self::HEADERS);
    }

    public function decide(CustomerDecisionRequest $request, string $token, RecordCustomerDecision $recordDecision): RedirectResponse
    {
        $order = $this->findOrder($token);

        try {
            $order = $recordDecision->handle($order, $request->approved(), $request->validated('reason'));
        } catch (InvalidStatusTransition $e) {
            return redirect()->route('tracking.show', $token)->with('error', $e->getMessage());
        }

        $message = $order->status === ServiceOrderStatus::Approved
            ? 'Orçamento aprovado! Vamos começar o conserto e avisaremos quando estiver pronto.'
            : 'Orçamento recusado. Avisaremos quando o aparelho estiver disponível para retirada.';

        return redirect()->route('tracking.show', $token)->with('success', $message)->withHeaders(self::HEADERS);
    }

    private function findOrder(string $token): ServiceOrder
    {
        return ServiceOrder::query()->where('public_token', $token)->firstOrFail();
    }

    /**
     * Somente o que o cliente pode ver. Senha do aparelho, CPF, contatos,
     * observações internas e nomes da equipe ficam de fora.
     *
     * @return array<string, mixed>
     */
    private function publicData(ServiceOrder $order): array
    {
        $order->load(['customer', 'device', 'items', 'statusHistory']);

        return [
            'number' => $order->number,
            'customer_first_name' => Str::before(trim($order->customer->name), ' '),
            'device' => $order->device->description(),
            'device_type' => $order->device->type->label(),
            'status' => $order->status,
            'opened_at' => Dates::dateTime($order->created_at),
            'estimated_delivery' => $order->estimated_delivery_at ? Dates::date($order->estimated_delivery_at) : null,
            'delivered_at' => $order->delivered_at ? Dates::dateTime($order->delivered_at) : null,
            'warranty_days' => $order->warranty_days,
            'awaiting_approval' => $order->status === ServiceOrderStatus::AwaitingApproval,
            'timeline' => $order->statusHistory
                ->map(fn (ServiceOrderStatusHistory $entry) => [
                    'status' => $entry->to_status,
                    'at' => Dates::dateTime($entry->created_at),
                ])
                ->reverse()
                ->values()
                ->all(),
            'items' => $order->items
                ->map(fn (ServiceOrderItem $item) => [
                    'description' => $item->description,
                    'type' => $item->type->label(),
                    'quantity' => $item->quantity,
                    'unit_price' => Money::format($item->unit_price_cents),
                    'total' => Money::format($item->totalCents()),
                ])
                ->all(),
            'parts_total' => Money::format((int) $order->items->where('type', ItemType::Part)->sum(fn (ServiceOrderItem $i) => $i->totalCents())),
            'labor_total' => Money::format((int) $order->items->where('type', ItemType::Labor)->sum(fn (ServiceOrderItem $i) => $i->totalCents())),
            'total' => Money::format($order->totalCents()),
            'shop' => [
                'name' => config('shop.name'),
                'phone' => config('shop.phone'),
                'address' => config('shop.address'),
            ],
        ];
    }
}
