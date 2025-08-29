<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdminDemoSeeder extends Seeder
{
    private string $conn = 'mysql_admin';

    public function run(): void
    {
        if (!config('database.connections.' . $this->conn)) {
            $this->command?->warn("Conexión {$this->conn} no definida. Saltando AdminDemoSeeder.");
            return;
        }

        DB::connection($this->conn)->transaction(function () {
            $this->seedAccounts();
            $this->seedSubscriptionsAndPayments();
        });
    }

    /* ==================== ACCOUNTS ==================== */
    private function seedAccounts(): void
    {
        if (!Schema::connection($this->conn)->hasTable('accounts')) {
            $this->command?->warn('Tabla accounts no existe. Saltando cuentas demo.');
            return;
        }

        $cols = Schema::connection($this->conn)->getColumnListing('accounts');

        $cName    = $this->firstExisting($cols, ['name','empresa','razon_social','account_name','nombre']);
        $cRFC     = $this->firstExisting($cols, ['rfc','tax_id']);
        $cEmail   = $this->firstExisting($cols, ['correo_contacto','email','correo','email_contacto']);
        $cPhone   = $this->firstExisting($cols, ['telefono_contacto','telefono','phone','phone_number']);
        $cStatus  = $this->firstExisting($cols, ['estado','status','activo','is_active']);
        $cComName = $this->firstExisting($cols, ['nombre_comercial','commercial_name','alias']);

        $now = now();
        $base = [
            ['Empresa Demo 1','EMD0010101ABA','demo1@empresa.test','555-100-0001'],
            ['Empresa Demo 2','EMD0020202BBB','demo2@empresa.test','555-100-0002'],
            ['ACME S.A. de C.V.','ACM010101ABC','acme@empresa.test','555-100-0003'],
            ['Globex S.A.P.I.','GLO020202XYZ','globex@empresa.test','555-100-0004'],
            ['Initech S.A.','INI030303QWE','initech@empresa.test','555-100-0005'],
            ['Umbrella Corp.','UMB040404RTY','umbrella@empresa.test','555-100-0006'],
        ];

        foreach ($base as $i => [$nombre, $rfc, $mail, $tel]) {
            $row = [
                'created_at' => $now->copy()->subMonths(6)->addDays($i * 7),
                'updated_at' => $now->copy()->subMonths(6)->addDays($i * 7),
            ];
            if ($cName)    $row[$cName]    = $nombre;
            if ($cRFC)     $row[$cRFC]     = $rfc;
            if ($cEmail)   $row[$cEmail]   = $mail;
            if ($cPhone)   $row[$cPhone]   = $tel;
            if ($cComName) $row[$cComName] = $nombre;
            if ($cStatus)  $row[$cStatus]  = in_array($cStatus, ['activo','is_active'], true) ? 1 : 'activo';

            if ($cRFC) {
                DB::connection($this->conn)->table('accounts')->updateOrInsert([$cRFC => $rfc], $row);
            } elseif ($cEmail) {
                DB::connection($this->conn)->table('accounts')->updateOrInsert([$cEmail => $mail], $row);
            } elseif ($cName) {
                DB::connection($this->conn)->table('accounts')->updateOrInsert([$cName => $nombre], $row);
            } else {
                if (DB::connection($this->conn)->table('accounts')->count() === 0) {
                    DB::connection($this->conn)->table('accounts')->insert($row);
                }
            }
        }
    }

    /* ========== SUBSCRIPTIONS + PAYMENTS (si existen) ========== */
    private function seedSubscriptionsAndPayments(): void
    {
        $hasTable = fn($t) => Schema::connection($this->conn)->hasTable($t);
        $firstTable = function(array $cands) use($hasTable){ foreach($cands as $t){ if($hasTable($t)) return $t; } return null; };

        $T_CLIENTES = $firstTable(['clientes','accounts']);
        $T_SUBS     = $firstTable(['subscriptions','suscripciones']);
        $T_PAGOS    = $firstTable(['pagos','payments','cobros']);
        $T_PLANES   = $firstTable(['plans','planes']); // ← importante

        if (!$T_PAGOS && !$T_SUBS) {
            $this->command?->warn('Ni subscriptions ni pagos existen. Saltando cobros.');
            return;
        }

        // Resolver columnas de subscriptions
        $subsCols = $T_SUBS ? Schema::connection($this->conn)->getColumnListing($T_SUBS) : [];
        $subsIdAccount = $this->firstExisting($subsCols, ['account_id','cliente_id','account']);
        $subsIdPlan    = $this->firstExisting($subsCols, ['plan_id']);
        $subsStatus    = $this->firstExisting($subsCols, ['status','estado']);
        $subsStart     = $this->firstExisting($subsCols, ['started_at','start_at','created_at']);

        // Resolver columnas de pagos
        $payCols   = $T_PAGOS ? Schema::connection($this->conn)->getColumnListing($T_PAGOS) : [];
        $pCreated  = $this->firstExisting($payCols, ['fecha','created_at','date']);
        $pAmount   = $this->firstExisting($payCols, ['monto','amount','total']);
        $pStatus   = $this->firstExisting($payCols, ['estado','status']);
        $pMethod   = $this->firstExisting($payCols, ['metodo_pago','payment_method','method','metodo']);
        $pRef      = $this->firstExisting($payCols, ['referencia','reference']);
        $pSubId    = $this->firstExisting($payCols, ['subscription_id','suscripcion_id']);
        $pAccount  = $this->firstExisting($payCols, ['cliente_id','account_id','account']);
        $pCustName = $this->firstExisting($payCols, ['cliente','customer_name','empresa','customer']);
        $pRFC      = $this->firstExisting($payCols, ['rfc','tax_id']);
        $pCurrency = $this->firstExisting($payCols, ['moneda','currency']);
        $pConcept  = $this->firstExisting($payCols, ['concepto','concept','descripcion','description']);
        $pTax      = $this->firstExisting($payCols, ['iva','tax']);
        $pDiscount = $this->firstExisting($payCols, ['descuento','discount']);

        if (!$T_PAGOS || !$pCreated || !$pAmount) {
            $this->command?->warn('Tabla/columnas de pagos insuficientes. Revísalo.');
            return;
        }

        // === Resolver/crear IDs de planes (free/premium) ===
        $planIds = $this->ensurePlanIds($T_PLANES);

        // Clientes origen
        $cliCols = Schema::connection($this->conn)->getColumnListing($T_CLIENTES);
        $cId   = $this->firstExisting($cliCols, ['id']);
        $cName = $this->firstExisting($cliCols, ['razon_social','nombre','name','empresa','account_name']);
        $cRFC  = $this->firstExisting($cliCols, ['rfc','tax_id']);

        $clientes = DB::connection($this->conn)->table($T_CLIENTES)
            ->select([$cId.' as id'])
            ->when($cName, fn($q)=>$q->addSelect($cName.' as name'))
            ->when($cRFC,  fn($q)=>$q->addSelect($cRFC.' as rfc'))
            ->limit(6)->get();

        if ($clientes->isEmpty()) return;

        // (Opcional) Crear suscripciones con plan_id válido si la tabla existe
        $subsByAcc = [];
        if ($T_SUBS && $subsIdAccount) {
            foreach ($clientes as $i => $acc) {
                // alternar free/premium para datos de demo
                $planKey = ($i % 2 === 0) ? 'premium' : 'free';
                $planId  = $planIds[$planKey] ?? null;

                $row = [
                    $subsIdAccount => $acc->id,
                ];
                if ($subsIdPlan && $planId !== null) {
                    $row[$subsIdPlan] = $planId; // ← NUNCA null
                }
                if ($subsStatus) $row[$subsStatus] = 'active';
                if ($subsStart)  $row[$subsStart]  = now()->copy()->subMonths(10);

                $row['created_at'] = now();
                $row['updated_at'] = now();

                // Si plan_id es NOT NULL pero no lo tenemos, mejor saltamos esta fila
                if ($subsIdPlan && !$planId) {
                    $this->command?->warn("No hay plan_id para {$planKey}; saltando subscription de account {$acc->id}");
                    continue;
                }

                $id = DB::connection($this->conn)->table($T_SUBS)->insertGetId($row);
                $subsByAcc[$acc->id] = $id;
            }
        }

        // Pagos 12 meses (también llenamos cliente/account_id si existe)
        $start = Carbon::now()->startOfMonth()->subMonths(11);
        foreach ($clientes as $acc) {
            for ($m = 0; $m < 12; $m++) {
                $date   = $start->copy()->addMonths($m)->addDays(rand(0, 25))->setTime(rand(9, 18), rand(0, 59));
                $amount = [399, 499, 699, 999, 1299, 1599][rand(0, 5)];

                $pay = [
                    $pCreated    => $date,
                    'created_at' => $date,
                    'updated_at' => $date,
                    $pAmount     => $amount,
                ];
                if ($pAccount)  $pay[$pAccount]  = $acc->id;
                if ($pStatus)   $pay[$pStatus]   = 'paid';
                if ($pMethod)   $pay[$pMethod]   = 'transfer';
                if ($pRef)      $pay[$pRef]      = Str::upper(Str::random(10));
                if ($pCustName) $pay[$pCustName] = $acc->name ?? '';
                if ($pRFC)      $pay[$pRFC]      = $acc->rfc ?? '';
                if ($pCurrency) $pay[$pCurrency] = 'MXN';
                if ($pConcept)  $pay[$pConcept]  = 'Servicio Pactopia 360';
                if ($pTax)      $pay[$pTax]      = 0;
                if ($pDiscount) $pay[$pDiscount] = 0;
                if ($pSubId && isset($subsByAcc[$acc->id])) $pay[$pSubId] = $subsByAcc[$acc->id];

                DB::connection($this->conn)->table($T_PAGOS)->insert($pay);
            }
        }
    }

    /**
     * Devuelve ['free'=>id, 'premium'=>id] garantizando su existencia.
     * Soporta tablas 'plans' (cols name/clave) o 'planes' (cols nombre/clave).
     */
    private function ensurePlanIds(?string $table): array
    {
        $out = ['free'=>null,'premium'=>null];
        if (!$table || !Schema::connection($this->conn)->hasTable($table)) {
            return $out;
        }

        $cols = Schema::connection($this->conn)->getColumnListing($table);
        $cId   = $this->firstExisting($cols, ['id','id_plan']);
        $cName = $this->firstExisting($cols, ['clave','name','nombre','nombre_plan']);

        if (!$cId || !$cName) return $out;

        // leer existentes
        $rows = DB::connection($this->conn)->table($table)
            ->select([$cId.' as id', DB::raw('LOWER('.$cName.') as clave')])
            ->whereIn(DB::raw('LOWER('.$cName.')'), ['free','premium'])
            ->get();

        foreach ($rows as $r) {
            if ($r->clave === 'free')    $out['free']    = (int)$r->id;
            if ($r->clave === 'premium') $out['premium'] = (int)$r->id;
        }

        // crear faltantes mínimos
        $now = now();
        foreach (['free','premium'] as $k) {
            if ($out[$k] === null) {
                $data = [
                    $cName      => $k,
                    'created_at'=> $now,
                    'updated_at'=> $now,
                ];
                $id = DB::connection($this->conn)->table($table)->insertGetId($data);
                $out[$k] = $id;
            }
        }

        return $out;
    }

    /* ==================== HELPERS ==================== */
    private function firstExisting(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (in_array($c, $columns, true)) return $c;
        }
        return null;
    }
}
