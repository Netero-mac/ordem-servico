<?php

namespace App\Actions\ServiceOrders;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Gera o próximo número legível de OS: OS-2026-0001, OS-2026-0002...
 * A sequência reinicia a cada ano (no fuso da loja).
 *
 * O UPDATE "last_number = last_number + 1" é atômico e trava a linha do ano
 * até o fim da transação, então duas OS abertas ao mesmo tempo nunca
 * recebem o mesmo número (no PostgreSQL e no SQLite).
 */
class GenerateServiceOrderNumber
{
    public function handle(): string
    {
        $year = (int) Date::now(config('app.display_timezone'))->format('Y');

        $next = DB::transaction(function () use ($year): int {
            DB::table('service_order_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

            DB::table('service_order_sequences')->where('year', $year)->increment('last_number');

            return (int) DB::table('service_order_sequences')->where('year', $year)->value('last_number');
        });

        return sprintf('OS-%d-%04d', $year, $next);
    }
}
