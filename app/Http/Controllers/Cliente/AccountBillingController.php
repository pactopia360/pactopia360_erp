<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountBillingController extends Controller
{
    public function statement(Request $req)
    {
        $accountId = $req->user()?->account_id ?? session('account_id');

        $acc = DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $accountId)
            ->first();

        $sub = DB::connection('mysql_admin')
            ->table('subscriptions')
            ->where('account_id', $accountId)
            ->first();

        $pending = DB::connection('mysql_admin')
            ->table('payments')
            ->where('account_id', $accountId)
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->get();

        $totalDue = $pending->sum('amount');

        return view('client.billing.statement', [
            'account'      => $acc,
            'subscription' => $sub,
            'pending'      => $pending,
            'totalDue'     => $totalDue,
        ]);
    }

    public function payPending(Request $req)
    {
        // Aquí invocas Stripe/Conekta y al webhook marcas payments=paid
        // y actualizas subscriptions.status='active', accounts.is_blocked=0
        return back()->with('ok', 'Redirigiendo a pasarela...');
    }
}
