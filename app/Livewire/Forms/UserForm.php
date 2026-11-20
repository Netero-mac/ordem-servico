<?php

namespace App\Livewire\Forms;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Form;

class UserForm extends Form
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'technician';

    /** Na edição, em branco = manter a senha atual. */
    public string $password = '';

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->password = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$this->user ? 'nullable' : 'required', 'string', Password::defaults(), 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'role' => 'papel', 'password' => 'senha'];
    }

    /**
     * @param  bool  $canChangeRole  falso quando o admin edita a si mesmo
     */
    public function save(bool $canChangeRole = true): User
    {
        $this->email = mb_strtolower(trim($this->email));
        $this->validate();

        $data = ['name' => trim($this->name), 'email' => $this->email];

        if ($canChangeRole) {
            $data['role'] = $this->role;
        }

        if (filled($this->password)) {
            $data['password'] = $this->password;
        }

        if ($this->user) {
            $this->user->update($data);

            return $this->user;
        }

        return $this->user = User::create([...$data, 'email_verified_at' => now()]);
    }
}
