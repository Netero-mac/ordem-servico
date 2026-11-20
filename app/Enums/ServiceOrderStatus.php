<?php

namespace App\Enums;

enum ServiceOrderStatus: string
{
    case Received = 'received';
    case InAnalysis = 'in_analysis';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case AwaitingPart = 'awaiting_part';
    case InRepair = 'in_repair';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recebido',
            self::InAnalysis => 'Em análise',
            self::AwaitingApproval => 'Aguardando aprovação',
            self::Approved => 'Aprovado',
            self::Rejected => 'Recusado',
            self::AwaitingPart => 'Aguardando peça',
            self::InRepair => 'Em conserto',
            self::Ready => 'Pronto para retirada',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Cor do badge (paleta do Flux/Tailwind).
     */
    public function color(): string
    {
        return match ($this) {
            self::Received => 'zinc',
            self::InAnalysis => 'sky',
            self::AwaitingApproval => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'rose',
            self::AwaitingPart => 'orange',
            self::InRepair => 'indigo',
            self::Ready => 'lime',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }

    /**
     * Status para os quais esta OS pode ir a partir do status atual.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::InAnalysis, self::Cancelled],
            self::InAnalysis => [self::AwaitingApproval, self::Cancelled],
            self::AwaitingApproval => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::AwaitingPart, self::InRepair, self::Cancelled],
            self::AwaitingPart => [self::InRepair, self::Cancelled],
            self::InRepair => [self::AwaitingPart, self::Ready],
            // Orçamento recusado: o aparelho volta para o cliente sem conserto.
            self::Rejected => [self::Ready],
            self::Ready => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * OS "em aberto" = ainda não saiu da loja nem foi cancelada.
     */
    public function isOpen(): bool
    {
        return ! $this->isFinal();
    }

    /**
     * @return list<self>
     */
    public static function openStatuses(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isOpen()));
    }

    /**
     * @return array<string, string> valor => rótulo, para selects
     */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases()),
            'label',
            'value',
        );
    }
}
