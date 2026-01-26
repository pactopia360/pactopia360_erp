<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SatCredentialsController extends Controller
{
    /**
     * GET /admin/sat/credentials/{id}/cer
     */
    public function downloadCer(Request $request, string $id): StreamedResponse
    {
        $cred = SatCredential::query()->findOrFail($id);

        $path = ltrim((string) ($cred->cer_path ?? ''), '/');
        if ($path === '' || !Storage::disk('public')->exists($path)) {
            abort(404, 'Certificado .cer no encontrado.');
        }

        $rfc = strtoupper((string) ($cred->rfc ?? 'CSD'));
        $filename = "{$rfc}.cer";

        return Storage::disk('public')->download($path, $filename, [
            'Content-Type' => 'application/x-x509-ca-cert',
        ]);
    }

    /**
     * GET /admin/sat/credentials/{id}/key
     */
    public function downloadKey(Request $request, string $id): StreamedResponse
    {
        $cred = SatCredential::query()->findOrFail($id);

        $path = ltrim((string) ($cred->key_path ?? ''), '/');
        if ($path === '' || !Storage::disk('public')->exists($path)) {
            abort(404, 'Llave .key no encontrada.');
        }

        $rfc = strtoupper((string) ($cred->rfc ?? 'CSD'));
        $filename = "{$rfc}.key";

        return Storage::disk('public')->download($path, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
