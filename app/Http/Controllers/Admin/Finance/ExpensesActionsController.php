<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExpensesActionsController extends Controller
{
    /**
     * Upsert rápido (placeholder estable).
     * En el Paso 2 lo conectamos a tabla real de egresos.
     */
    public function upsert(Request $req): JsonResponse
    {
        $payload = [
            'id' => $req->input('id'),
            'account_id' => $req->input('account_id'),
            'period' => $req->input('period'),
            'amount' => $req->input('amount'),
            'notes' => $req->input('notes'),
        ];

        return response()->json([
            'ok' => false,
            'message' => 'ExpensesActionsController@upsert está listo pero aún no está conectado a persistencia. (Paso 2)',
            'payload' => $payload,
        ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'ExpensesActionsController@destroy está listo pero aún no está conectado a persistencia. (Paso 2)',
            'id' => $id,
        ], 200);
    }
}