<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerDecisionRequest extends FormRequest
{
    /**
     * Rota pública: quem tem o token (o cliente) pode responder.
     * A regra de status é verificada na action.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'confirmed' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Confirme a sua escolha para continuar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['decision' => 'decisão', 'reason' => 'motivo'];
    }

    public function approved(): bool
    {
        return $this->validated('decision') === 'approve';
    }
}
