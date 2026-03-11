<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class InvoicingLogsController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(Request $request): View
    {
        $q       = trim((string) $request->query('q', ''));
        $status  = trim((string) $request->query('status', ''));
        $source  = trim((string) $request->query('source', ''));
        $period  = trim((string) $request->query('period', ''));

        $rows = collect();
        $table = null;
        $error = null;

        try {
            if (Schema::connection($this->adm)->hasTable('billing_invoices')) {
                $table = 'billing_invoices';

                $qb = DB::connection($this->adm)
                    ->table($table)
                    ->orderByDesc('id');

                $cols = Schema::connection($this->adm)->getColumnListing($table);
                $lc   = array_map('strtolower', $cols);
                $has  = fn (string $c): bool => in_array(strtolower($c), $lc, true);

                if ($status !== '' && $has('status')) {
                    $qb->where('status', $status);
                }

                if ($source !== '' && $has('source')) {
                    $qb->where('source', 'like', '%' . $source . '%');
                }

                if ($period !== '' && $has('period')) {
                    $qb->where('period', $period);
                }

                if ($q !== '') {
                    $qb->where(function ($w) use ($q, $has) {
                        if ($has('id'))          $w->orWhere('id', 'like', '%' . $q . '%');
                        if ($has('request_id'))  $w->orWhere('request_id', 'like', '%' . $q . '%');
                        if ($has('account_id'))  $w->orWhere('account_id', 'like', '%' . $q . '%');
                        if ($has('period'))      $w->orWhere('period', 'like', '%' . $q . '%');
                        if ($has('cfdi_uuid'))   $w->orWhere('cfdi_uuid', 'like', '%' . $q . '%');
                        if ($has('rfc'))         $w->orWhere('rfc', 'like', '%' . $q . '%');
                        if ($has('razon_social'))$w->orWhere('razon_social', 'like', '%' . $q . '%');
                        if ($has('source'))      $w->orWhere('source', 'like', '%' . $q . '%');
                        if ($has('notes'))       $w->orWhere('notes', 'like', '%' . $q . '%');
                    });
                }

                $rows = $qb->paginate(30)->withQueryString();

                if (Schema::connection($this->adm)->hasTable('accounts')) {
                    $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();

                    if (!empty($ids)) {
                        $accounts = DB::connection($this->adm)
                            ->table('accounts')
                            ->select(['id', 'email', 'rfc', 'razon_social', 'name'])
                            ->whereIn('id', $ids)
                            ->get()
                            ->keyBy('id');

                        $rows->getCollection()->transform(function ($r) use ($accounts) {
                            $a = $accounts[$r->account_id] ?? null;
                            $r->account_email = $a->email ?? null;
                            $r->account_name  = $a->razon_social ?? ($a->name ?? null);
                            $r->account_rfc   = $a->rfc ?? null;
                            return $r;
                        });
                    }
                }
            } else {
                $error = 'No existe la tabla billing_invoices en la conexión admin.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.billing.invoicing.logs.index', [
            'rows'  => $rows,
            'table' => $table,
            'error' => $error,
            'q'     => $q,
            'status'=> $status,
            'source'=> $source,
            'period'=> $period,
        ]);
    }
}