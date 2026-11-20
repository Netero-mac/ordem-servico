<?php

namespace App\Services;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Support\Str;

/**
 * Monta o link wa.me com a mensagem pronta e o link de acompanhamento da OS.
 */
class WhatsAppLinkBuilder
{
    public function forServiceOrder(ServiceOrder $order): string
    {
        return 'https://wa.me/'.Phone::whatsApp($order->customer->phone).'?text='.rawurlencode($this->message($order));
    }

    public function message(ServiceOrder $order): string
    {
        $firstName = Str::before(trim($order->customer->name), ' ');
        $shop = config('shop.name');
        $device = $order->device->description();
        $link = route('tracking.show', $order->public_token);

        $status = match ($order->status) {
            ServiceOrderStatus::AwaitingApproval => 'O orçamento do seu '.$device.' está pronto: total de '.Money::format($order->totalCents()).'. Você pode aprovar ou recusar pelo link abaixo.',
            ServiceOrderStatus::Ready => "Seu {$device} está pronto para retirada!",
            ServiceOrderStatus::Delivered => "Obrigado pela confiança! Seu {$device} tem {$order->warranty_days} dias de garantia.",
            default => "Status atual do seu {$device}: {$order->status->label()}.",
        };

        return implode("\n\n", [
            "Olá, {$firstName}! Aqui é da {$shop}.",
            "Ordem de serviço {$order->number}. {$status}",
            "Acompanhe pelo link: {$link}",
        ]);
    }
}
