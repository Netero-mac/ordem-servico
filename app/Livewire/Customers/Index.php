<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clientes')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        $term = trim($this->search);
        $digits = Phone::normalize($term);

        return Customer::query()
            ->withCount(['devices', 'serviceOrders'])
            ->when($term !== '', function (Builder $query) use ($term, $digits) {
                $query->where(function (Builder $query) use ($term, $digits) {
                    $query->whereLike('name', "%{$term}%");

                    // Só busca por telefone se a pessoa digitou números suficientes.
                    if (strlen($digits) >= 3) {
                        $query->orWhereLike('phone', "%{$digits}%");
                    }
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.customers.index');
    }
}
