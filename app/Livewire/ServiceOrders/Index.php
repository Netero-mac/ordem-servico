<?php

namespace App\Livewire\ServiceOrders;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, ServiceOrder> $orders
 * @property-read Collection<int, User> $technicians
 * @property-read int $overdueCount
 */
#[Title('Ordens de serviço')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    /** '' = todas | 'open' = em aberto | valor do enum */
    #[Url(except: '')]
    public string $status = '';

    /** '' = todos | 'none' = sem técnico | id */
    #[Url(as: 'tecnico', except: '')]
    public string $technician = '';

    /** Período de entrada (Y-m-d, no fuso da loja). */
    #[Url(as: 'de', except: '')]
    public string $from = '';

    #[Url(as: 'ate', except: '')]
    public string $to = '';

    #[Url(as: 'atrasadas', except: false)]
    public bool $onlyOverdue = false;

    public function mount(): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'technician', 'from', 'to', 'onlyOverdue');
        $this->resetPage();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function technicians(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function overdueCount(): int
    {
        return ServiceOrder::overdue()->count();
    }

    /**
     * @return LengthAwarePaginator<int, ServiceOrder>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        $timezone = config('app.display_timezone');

        return ServiceOrder::query()
            ->with(['customer', 'device', 'technician'])
            ->when(trim($this->search) !== '', function (Builder $query) {
                $term = trim($this->search);
                $query->where(fn (Builder $query) => $query
                    ->whereLike('number', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $query) => $query->withTrashed()->whereLike('name', "%{$term}%")));
            })
            ->when($this->status === 'open', fn (Builder $query) => $query->whereIn('status', ServiceOrderStatus::openStatuses()))
            ->when(ServiceOrderStatus::tryFrom($this->status), fn (Builder $query, ServiceOrderStatus $status) => $query->where('status', $status))
            ->when($this->technician === 'none', fn (Builder $query) => $query->whereNull('technician_id'))
            ->when(ctype_digit($this->technician), fn (Builder $query) => $query->where('technician_id', (int) $this->technician))
            ->when($this->parseDate($this->from), fn (Builder $query, Carbon $from) => $query->where('created_at', '>=', $from->setTimezone($timezone)->startOfDay()->utc()))
            ->when($this->parseDate($this->to), fn (Builder $query, Carbon $to) => $query->where('created_at', '<=', $to->setTimezone($timezone)->endOfDay()->utc()))
            ->when($this->onlyOverdue, fn (Builder $query) => $query->overdue())
            ->latest()
            ->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.service-orders.index', [
            'statuses' => ServiceOrderStatus::cases(),
        ]);
    }

    private function parseDate(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value, config('app.display_timezone')) ?: null;
    }
}
