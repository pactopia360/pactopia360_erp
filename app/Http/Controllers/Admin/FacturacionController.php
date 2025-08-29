<?php
namespace App\Http\Controllers\Admin;

use App\Models\Cfdi;
use App\Models\Cliente;

class FacturacionController extends CrudController
{
    protected string $model = Cfdi::class;
    protected string $routeBase = 'admin.facturacion';
    protected array $titles = ['index'=>'CFDIs','create'=>'Nuevo CFDI','edit'=>'Editar CFDI'];
    protected array $fields = [
        ['name'=>'cliente_id','label'=>'Cliente','type'=>'select','options'=>'@clientes'],
        ['name'=>'serie','label'=>'Serie','type'=>'text'],
        ['name'=>'folio','label'=>'Folio','type'=>'text'],
        ['name'=>'total','label'=>'Total','type'=>'number','step'=>'0.01','required'=>true],
        ['name'=>'fecha','label'=>'Fecha','type'=>'datetime','required'=>true],
        ['name'=>'estatus','label'=>'Estatus','type'=>'select','options'=>[
            ['value'=>'emitido','label'=>'Emitido'],
            ['value'=>'cancelado','label'=>'Cancelado'],
        ]],
        ['name'=>'uuid','label'=>'UUID','type'=>'text','required'=>true],
    ];
    protected array $rules = [
        'cliente_id' => 'nullable|integer|exists:clientes,id',
        'serie'      => 'nullable|string|max:10',
        'folio'      => 'nullable|string|max:20',
        'total'      => 'required|numeric|min:0',
        'fecha'      => 'required|date',
        'estatus'    => 'nullable|string|max:20|in:emitido,cancelado',
        'uuid'       => 'required|string|max:40|unique:cfdis,uuid',
    ];

    public function create(): \Illuminate\Contracts\View\View { $this->inflate(); return parent::create(); }
    public function edit($id): \Illuminate\Contracts\View\View { $this->inflate(); return parent::edit($id); }
    private function inflate(): void
    {
        $opts = Cliente::query()->orderBy('nombre_comercial')->limit(500)->get(['id','nombre_comercial','razon_social'])
            ->map(fn($c)=>['value'=>$c->id,'label'=>($c->nombre_comercial ?: $c->razon_social ?: ('#'.$c->id))])->all();
        foreach ($this->fields as &$f) if (($f['options'] ?? '') === '@clientes') $f['options']=$opts;
    }
}
