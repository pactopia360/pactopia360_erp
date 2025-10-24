<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\EmisorStoreRequest;
use App\Models\Cliente\Emisor;
use App\Services\Facturotopia\EmisoresApi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class EmisoresController extends Controller
{
    protected function conn(): string { return (new Emisor)->getConnectionName() ?? 'mysql_clientes'; }
    protected function hasCol(string $t,string $c): bool { try { return Schema::connection($this->conn())->hasColumn($t,$c);}catch(\Throwable){return false;} }

    public function create(): View
    {
        return view('cliente.emisores.create'); // vista actualizada (abajo)
    }

    /** Convierte archivo subido a base64 si existe */
    protected function b64(?\Illuminate\Http\UploadedFile $f): ?string
    {
        if (!$f) return null;
        return base64_encode(file_get_contents($f->getRealPath()));
    }

    /** Construye arrays JSON direccion/series */
    protected function buildDireccion(EmisorStoreRequest $r): array
    {
        return [
            'cp'        => (string) $r->input('dir_cp'),
            'direccion' => (string) $r->input('dir_direccion', ''),
            'ciudad'    => (string) $r->input('dir_ciudad', ''),
            'estado'    => (string) $r->input('dir_estado', ''),
        ];
    }

    protected function buildSeries(EmisorStoreRequest $r): array
    {
        if ($json = trim((string) $r->input('series_json',''))) {
            $arr = json_decode($json, true);
            return is_array($arr) ? $arr : [];
        }
        $out = [];
        if ($r->filled('serie_ingreso')) $out[] = ['tipo'=>'ingreso','serie'=>$r->input('serie_ingreso'),'folio'=>(int)$r->input('folio_ingreso',1)];
        if ($r->filled('serie_egreso'))  $out[] = ['tipo'=>'egreso' ,'serie'=>$r->input('serie_egreso') ,'folio'=>(int)$r->input('folio_egreso',1)];
        return $out;
    }

    /** Guardar + (opcional) enviar al PAC */
    public function store(EmisorStoreRequest $r): RedirectResponse
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $payload = [
            'rfc'              => strtoupper($r->string('rfc')),
            'razon_social'     => $r->string('razon_social'),
            'nombre_comercial' => $r->input('nombre_comercial'),
            'email'            => $r->string('email'),
            'regimen_fiscal'   => $r->string('regimen_fiscal'), // local
            'grupo'            => $r->input('grupo'),
            'direccion'        => $this->buildDireccion($r),
            'series'           => $this->buildSeries($r),
            'certificados'     => [
                'csd_key'       => $this->b64($r->file('csd_key')),
                'csd_cer'       => $this->b64($r->file('csd_cer')),
                'csd_password'  => $r->input('csd_password'),
                'fiel_key'      => $this->b64($r->file('fiel_key')),
                'fiel_cer'      => $this->b64($r->file('fiel_cer')),
                'fiel_password' => $r->input('fiel_password'),
            ],
        ];

        // Guarda local
        $emisor = new Emisor($payload);
        if ($cuenta && $this->hasCol('emisores','cuenta_id')) $emisor->cuenta_id = $cuenta->id;
        $emisor->save();

        // ¿Sincronizar con PAC? (sólo PRO)
        $isPro   = strtoupper($cuenta->plan_actual ?? 'FREE') === 'PRO';
        $syncPac = $r->boolean('sync_pac') && $isPro;

        if ($syncPac) {
            // Map a formato Waretek
            $waretek = [
                'id'           => (string) $emisor->ext_id,                  // tu ID externo
                'razon_social' => (string) $emisor->razon_social,
                'grupo'        => (string) ($emisor->grupo ?? ''),
                'rfc'          => (string) $emisor->rfc,
                'regimen'      => (string) $emisor->regimen_fiscal,          // clave SAT (ej 601)
                'email'        => (string) $emisor->email,
                'direccion'    => [
                    'cp'        => (string) ($emisor->direccion['cp'] ?? ''),
                    'direccion' => (string) ($emisor->direccion['direccion'] ?? ''),
                    'ciudad'    => (string) ($emisor->direccion['ciudad'] ?? ''),
                    'estado'    => (string) ($emisor->direccion['estado'] ?? ''),
                ],
                'certificados' => [
                    'csd_key'       => $emisor->certificados['csd_key']       ?? null,
                    'csd_cer'       => $emisor->certificados['csd_cer']       ?? null,
                    'csd_password'  => $emisor->certificados['csd_password']  ?? null,
                    'fiel_key'      => $emisor->certificados['fiel_key']      ?? null,
                    'fiel_cer'      => $emisor->certificados['fiel_cer']      ?? null,
                    'fiel_password' => $emisor->certificados['fiel_password'] ?? null,
                ],
                'series'       => array_values($emisor->series ?? []),
            ];

            try {
                app(EmisoresApi::class)->create($waretek);
                return redirect()->route('cliente.emisores.index')->with('ok','Emisor guardado y sincronizado con el PAC.');
            } catch (\Throwable $e) {
                // No detiene el flujo: ya guardamos local
                return redirect()->route('cliente.emisores.index')
                    ->with('warn','Emisor guardado localmente, pero falló la sincronización con el PAC: '.$e->getMessage());
            }
        }

        return redirect()->route('cliente.emisores.index')->with('ok','Emisor guardado.');
    }
}
