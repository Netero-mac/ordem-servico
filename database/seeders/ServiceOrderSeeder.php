<?php

namespace Database\Seeders;

use App\Actions\ServiceOrders\ChangeServiceOrderStatus;
use App\Enums\ServiceOrderStatus as S;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Cria 20 OS em status variados, percorrendo as transições reais
 * (via ChangeServiceOrderStatus) para que o histórico fique coerente.
 */
class ServiceOrderSeeder extends Seeder
{
    /**
     * Caminhos a partir de "received" até o status final desejado.
     * Cada caminho aparece N vezes.
     *
     * @var list<array{0: int, 1: list<S>}>
     */
    private const SCENARIOS = [
        [2, []],
        [2, [S::InAnalysis]],
        [3, [S::InAnalysis, S::AwaitingApproval]],
        [1, [S::InAnalysis, S::AwaitingApproval, S::Approved]],
        [1, [S::InAnalysis, S::AwaitingApproval, S::Rejected]],
        [2, [S::InAnalysis, S::AwaitingApproval, S::Approved, S::AwaitingPart]],
        [3, [S::InAnalysis, S::AwaitingApproval, S::Approved, S::InRepair]],
        [2, [S::InAnalysis, S::AwaitingApproval, S::Approved, S::InRepair, S::Ready]],
        [2, [S::InAnalysis, S::AwaitingApproval, S::Approved, S::AwaitingPart, S::InRepair, S::Ready, S::Delivered]],
        [1, [S::InAnalysis, S::AwaitingApproval, S::Rejected, S::Ready, S::Delivered]],
        [1, [S::InAnalysis, S::Cancelled]],
    ];

    public function run(ChangeServiceOrderStatus $changeStatus): void
    {
        $admin = User::query()->where('role', UserRole::Admin)->firstOrFail();
        $technician = User::query()->where('role', UserRole::Technician)->firstOrFail();
        $devices = Device::all();

        $scenarios = collect(self::SCENARIOS)
            ->flatMap(fn (array $scenario) => array_fill(0, $scenario[0], $scenario[1]))
            ->shuffle();

        // Referência fixa: cada OS volta no tempo a partir de HOJE, não da OS anterior.
        $today = now(config('app.display_timezone'));

        try {
            foreach ($scenarios as $path) {
                // Abre a OS no passado e avança algumas horas a cada etapa.
                Carbon::setTestNow(
                    $today
                        ->subDays(fake()->numberBetween(10, 25))
                        ->setTime(fake()->numberBetween(8, 17), fake()->numberBetween(0, 59))
                        ->utc()
                );

                $device = $devices->random();

                $order = ServiceOrder::factory()->create([
                    'device_id' => $device->id,
                    'customer_id' => $device->customer_id,
                    'user_id' => $admin->id,
                    'technician_id' => fake()->boolean(80) ? $technician->id : null,
                    // Previsão em torno de hoje: algumas atrasadas, a maioria no prazo.
                    'estimated_delivery_at' => $today->addDays(fake()->numberBetween(-2, 9))->setTime(18, 0)->utc(),
                ]);

                foreach ($path as $status) {
                    Carbon::setTestNow(now()->addHours(fake()->numberBetween(2, 30)));

                    if ($status === S::AwaitingApproval) {
                        $this->writeQuote($order);
                    }

                    $order = $changeStatus->handle(
                        $order,
                        $status,
                        $status === S::Approved && fake()->boolean() ? null : $technician,
                        $this->noteFor($status),
                    );
                }
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    private function writeQuote(ServiceOrder $order): void
    {
        $order->update(['diagnosis' => fake()->randomElement([
            'Display danificado, necessária troca da tela.',
            'Bateria com desgaste acima de 30%. Recomendada a troca.',
            'Conector de carga oxidado.',
            'HD com setores defeituosos; recomendada troca por SSD.',
            'Placa com oxidação leve; limpeza química resolve.',
        ])]);

        ServiceOrderItem::factory()->part()->for($order)->create();
        ServiceOrderItem::factory()->labor()->for($order)->create();
    }

    private function noteFor(S $status): ?string
    {
        return match ($status) {
            S::AwaitingApproval => 'Orçamento enviado ao cliente pelo WhatsApp.',
            S::Approved => 'Cliente aprovou o orçamento.',
            S::Rejected => 'Cliente achou o valor alto.',
            S::AwaitingPart => 'Peça encomendada ao fornecedor.',
            S::Cancelled => 'Cliente desistiu do conserto.',
            default => null,
        };
    }
}
