<?php

namespace App\Http\Controllers\Admin;

use App\Models\Pago;
use App\Models\Cliente;

class PagosController extends CrudController
{
    protected string $model = Pago::class;
    protected string $routeBase = 'admin.pagos';
    protected array $titles = ['index'=>'Pagos','create'=>'Nuevo pago','edit'=>'Editar pago'];
    protected array $fields = [
        ['name'=>'cliente_id','label'=>'Cliente','type'=>'select','options'=>'@clientes'],
        ['name'=>'monto','label'=>'Monto','type'=>'number','step'=>'0.01','required'=>true],
        ['name'=>'fecha','label'=>'Fecha','type'=>'datetime','required'=>true],
        ['name'=>'estado','label'=>'Estado','type'=>'select','options'=>[
            ['value'=>'pagado','label'=>'Pagado'],
            ['value'=>'pendiente','label'=>'Pendiente'],
        ]],
        ['name'=>'metodo_pago','label'=>'Método pago','type'=>'text'],
        ['name'=>'referencia','label'=>'Referencia','type'=>'text'],
    ];
    protected array $rules = [
        'cliente_id'  => 'nullable|integer|exists:clientes,id',
        'monto'       => 'required|numeric|min:0',
        'fecha'       => 'required|date|before_or_equal:now',
        'estado'      => 'nullable|string|max:20|in:pagado,pendiente',
        'metodo_pago' => 'nullable|string|max:50',
        'referencia'  => 'nullable|string|max:64',
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
