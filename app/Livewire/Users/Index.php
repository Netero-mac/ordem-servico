<?php

namespace App\Livewire\Users;

use App\Actions\Users\DeleteUser;
use App\Enums\UserRole;
use App\Exceptions\CannotDeleteRecord;
use App\Livewire\Forms\UserForm;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, User> $users
 */
#[Title('Usuários')]
class Index extends Component
{
    public UserForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', User::class);

        $this->form->reset();
        $this->form->resetValidation();
        Flux::modal('user-form')->show();
    }

    public function edit(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        $this->form->resetValidation();
        $this->form->setUser($user);
        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $user = $this->form->user;
        $this->authorize($user ? 'update' : 'create', $user ?? User::class);

        $this->form->save(canChangeRole: $user === null || (bool) auth()->user()?->can('changeRole', $user));

        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: $user ? 'Usuário atualizado.' : 'Usuário criado.');
        $this->form->reset();
        unset($this->users);
    }

    public function delete(int $userId, DeleteUser $deleteUser): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('delete', $user);

        try {
            $deleteUser->handle($user);
            Flux::toast(variant: 'success', text: 'Usuário excluído.');
        } catch (CannotDeleteRecord $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        unset($this->users);
    }

    public function render(): View
    {
        return view('livewire.users.index', ['roles' => UserRole::cases()]);
    }
}
