<?php

namespace App\Services;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Números do painel. "Mês" é sempre o mês corrente no fuso da loja.
 */
class DashboardMetrics
{
    /**
     * Quantidade de OS em aberto por status (inclui status com zero, na ordem do fluxo).
     *
     * @return array<string, int> valor do status => quantidade
     */
    public function openByStatus(): array
    {
        $counts = ServiceOrder::query()
            ->whereIn('status', ServiceOrderStatus::openStatuses())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];
        foreach (ServiceOrderStatus::openStatuses() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    public function overdueCount(): int
    {
        return ServiceOrder::overdue()->count();
    }

    /**
     * As OS atrasadas há mais tempo.
     *
     * @return Collection<int, ServiceOrder>
     */
    public function mostOverdue(int $limit = 5): Collection
    {
        return ServiceOrder::overdue()
            ->with(['customer', 'device'])
            ->oldest('estimated_delivery_at')
            ->limit($limit)
            ->get();
    }

    public function deliveredInMonth(?CarbonImmutable $month = null): int
    {
        [$start, $end] = $this->monthRange($month);

        return ServiceOrder::query()
            ->where('status', ServiceOrderStatus::Delivered)
            ->whereBetween('delivered_at', [$start, $end])
            ->count();
    }

    /**
     * Faturamento = soma dos itens das OS ENTREGUES no mês cujo orçamento foi APROVADO.
     * OS recusadas também terminam como "entregue", mas não geram receita.
     */
    public function revenueInMonth(?CarbonImmutable $month = null): int
    {
        [$start, $end] = $this->monthRange($month);

        return (int) ServiceOrderItem::query()
            ->join('service_orders', 'service_orders.id', '=', 'service_order_items.service_order_id')
            ->whereNull('service_orders.deleted_at')
            ->where('service_orders.status', ServiceOrderStatus::Delivered)
            ->whereNotNull('service_orders.approved_at')
            ->whereBetween('service_orders.delivered_at', [$start, $end])
            ->sum(DB::raw('service_order_items.quantity * service_order_items.unit_price_cents'));
    }

    public function revenueLastMonth(): int
    {
        return $this->revenueInMonth($this->localNow()->subMonthNoOverflow());
    }

    /**
     * Início e fim do mês no fuso da loja, convertidos para UTC (como está no banco).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function monthRange(?CarbonImmutable $month): array
    {
        $month ??= $this->localNow();

        return [$month->startOfMonth()->utc(), $month->endOfMonth()->utc()];
    }

    private function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.display_timezone'));
    }
}
