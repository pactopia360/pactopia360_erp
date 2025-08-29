<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class SearchController extends Controller
{
    public function index(Request $req): View
    {
        $q = trim((string) $req->get('q', ''));
        $results = [];

        if ($q !== '') {
            // CLIENTES
            if (Schema::hasTable('clientes')) {
                $rows = DB::table('clientes')
                    ->select(['id','razon_social','nombre_comercial','rfc'])
                    ->where(function($w) use ($q){
                        $w->where('razon_social','like',"%$q%")
                          ->orWhere('nombre_comercial','like',"%$q%")
                          ->orWhere('rfc','like',"%$q%");
                    })
                    ->orderByDesc('id')->limit(10)->get();
                foreach ($rows as $r) {
                    $name = $r->nombre_comercial ?: ($r->razon_social ?: ('#'.$r->id));
                    $results[] = [
                        'type'  => 'Cliente',
                        'title' => $name,
                        'sub'   => $r->rfc ?: '',
                        'url'   => Route::has('admin.clientes.edit') ? route('admin.clientes.edit',$r->id) : '#',
                    ];
                }
            }

            // PLANES
            if (Schema::hasTable('planes')) {
                $rows = DB::table('planes')
                    ->select(['id','clave','nombre'])
                    ->where(function($w) use ($q){
                        $w->where('clave','like',"%$q%")->orWhere('nombre','like',"%$q%");
                    })
                    ->orderBy('nombre')->limit(10)->get();
                foreach ($rows as $r) {
                    $results[] = [
                        'type'  => 'Plan',
                        'title' => $r->nombre ?: $r->clave,
                        'sub'   => $r->clave,
                        'url'   => Route::has('admin.planes.edit') ? route('admin.planes.edit',$r->id) : '#',
                    ];
                }
            }

            // PAGOS
            if (Schema::hasTable('pagos')) {
                $rows = DB::table('pagos')
                    ->select(['id','monto','fecha','referencia'])
                    ->where(function($w) use ($q){
                        $w->where('referencia','like',"%$q%")->orWhere('id','like',"%$q%");
                    })
                    ->orderByDesc('fecha')->limit(10)->get();
                foreach ($rows as $r) {
                    $results[] = [
                        'type'  => 'Pago',
                        'title' => '$'.number_format((float)$r->monto,2),
                        'sub'   => ($r->fecha ? (string)$r->fecha : '').($r->referencia ? ' · '.$r->referencia : ''),
                        'url'   => Route::has('admin.pagos.edit') ? route('admin.pagos.edit',$r->id) : '#',
                    ];
                }
            }

            // CFDI / FACTURAS
            foreach (['cfdis','facturas','comprobantes','facturacion'] as $t) {
                if (!Schema::hasTable($t)) continue;
                $rows = DB::table($t)
                    ->select(['id','uuid','folio','total','fecha'])
                    ->where(function($w) use ($q){
                        $w->where('uuid','like',"%$q%")->orWhere('folio','like',"%$q%");
                    })
                    ->orderByDesc('fecha')->limit(10)->get();
                foreach ($rows as $r) {
                    $results[] = [
                        'type'  => 'CFDI',
                        'title' => $r->uuid ?: ($r->folio ? "Folio {$r->folio}" : ('#'.$r->id)),
                        'sub'   => ($r->fecha ? (string)$r->fecha : '').' · $'.number_format((float)($r->total ?? 0),2),
                        'url'   => Route::has('admin.facturacion.edit') ? route('admin.facturacion.edit',$r->id) : '#',
                    ];
                }
                break;
            }
        }

        return view('admin.search.index', [
            'q'       => $q,
            'results' => $results,
        ]);
    }
}
