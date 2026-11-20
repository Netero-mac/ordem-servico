<?php

namespace App\Livewire;

use App\Enums\ServiceOrderStatus;
use App\Services\DashboardMetrics;
use App\Support\Dates;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Painel')]
class Dashboard extends Component
{
    public function render(DashboardMetrics $metrics): View
    {
        $byStatus = $metrics->openByStatus();
        $revenue = $metrics->revenueInMonth();
        $revenueLastMonth = $metrics->revenueLastMonth();

        return view('livewire.dashboard', [
            'month' => Str::ucfirst(now(config('app.display_timezone'))->translatedFormat('F \d\e Y')),
            'openTotal' => array_sum($byStatus),
            'byStatus' => collect($byStatus)->map(fn (int $count, string $status) => [
                'status' => ServiceOrderStatus::from($status),
                'count' => $count,
            ])->values(),
            'maxCount' => max(1, ...array_values($byStatus)),
            'overdueCount' => $metrics->overdueCount(),
            'mostOverdue' => $metrics->mostOverdue(),
            'delivered' => $metrics->deliveredInMonth(),
            'revenue' => $revenue,
            'revenueLastMonth' => $revenueLastMonth,
            'revenueChange' => $revenueLastMonth > 0 ? ($revenue - $revenueLastMonth) / $revenueLastMonth * 100 : null,
            'today' => Dates::date(now()),
        ]);
    }
}
