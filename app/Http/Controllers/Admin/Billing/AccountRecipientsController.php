<?php
// app/Http/Controllers/Admin/Billing/AccountRecipientsController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountRecipientsController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(Request $req, string $accountId): JsonResponse
    {
        abort_unless(Schema::connection($this->adm)->hasTable('account_recipients'), 404);

        $rows = DB::connection($this->adm)->table('account_recipients')
            ->where('account_id', $accountId)
            ->orderByDesc('is_primary')
            ->orderBy('type')
            ->orderBy('email')
            ->get();

        return response()->json([
            'ok' => true,
            'account_id' => $accountId,
            'recipients' => $rows,
        ]);
    }

    public function store(Request $req, string $accountId)
    {
        abort_unless(Schema::connection($this->adm)->hasTable('account_recipients'), 404);

        $data = $req->validate([
            'email'      => 'required|email:rfc,dns|max:190',
            'name'       => 'nullable|string|max:190',
            'type'       => 'nullable|string|in:to,cc,bcc',
            'is_primary' => 'nullable|boolean',
            'is_active'  => 'nullable|boolean',
        ]);

        $email = strtolower(trim((string) $data['email']));
        $type  = (string) ($data['type'] ?? 'to');

        DB::connection($this->adm)->transaction(function () use ($accountId, $email, $data, $type) {
            // Si se marca primary, apagamos otros primary del account
            $isPrimary = (bool) ($data['is_primary'] ?? false);
            if ($isPrimary) {
                DB::connection($this->adm)->table('account_recipients')
                    ->where('account_id', $accountId)
                    ->update(['is_primary' => 0, 'updated_at' => now()]);
            }

            $exists = DB::connection($this->adm)->table('account_recipients')
                ->where('account_id', $accountId)
                ->where('email', $email)
                ->first();

            $row = [
                'account_id'  => $accountId,
                'email'       => $email,
                'name'        => $data['name'] ?? null,
                'type'        => $type,
                'is_primary'  => $isPrimary ? 1 : 0,
                'is_active'   => array_key_exists('is_active', $data) ? ((bool)$data['is_active'] ? 1 : 0) : 1,
                'updated_at'  => now(),
            ];

            if ($exists) {
                DB::connection($this->adm)->table('account_recipients')
                    ->where('id', (int) $exists->id)
                    ->update($row);
            } else {
                $row['created_at'] = now();
                DB::connection($this->adm)->table('account_recipients')->insert($row);
            }
        });

        if ($req->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('ok', 'Destinatario guardado.');
    }

    public function destroy(Request $req, string $accountId, int $id)
    {
        abort_unless(Schema::connection($this->adm)->hasTable('account_recipients'), 404);

        DB::connection($this->adm)->table('account_recipients')
            ->where('account_id', $accountId)
            ->where('id', $id)
            ->delete();

        if ($req->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('ok', 'Destinatario eliminado.');
    }
}
