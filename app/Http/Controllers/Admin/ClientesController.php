<?php

namespace App\Http\Controllers\Admin;

use App\Models\Cliente;
use App\Models\Plan;

class ClientesController extends CrudController
{
    protected string $model = Cliente::class;
    protected string $routeBase = 'admin.clientes';
    protected array $titles = ['index'=>'Clientes','create'=>'Nuevo cliente','edit'=>'Editar cliente'];
    protected array $fields = [
        ['name'=>'razon_social','label'=>'Razón social','type'=>'text'],
        ['name'=>'nombre_comercial','label'=>'Nombre comercial','type'=>'text'],
        ['name'=>'rfc','label'=>'RFC','type'=>'text'],
        ['name'=>'plan_id','label'=>'Plan','type'=>'select','options'=>'@planes'],
        ['name'=>'plan','label'=>'Plan (fallback)','type'=>'text'],
        ['name'=>'activo','label'=>'Activo','type'=>'switch'],
    ];
    protected array $rules = [
        'razon_social'     => 'nullable|string|max:190',
        'nombre_comercial' => 'nullable|string|max:190',
        // RFC mexicano: 3/4 letras (&/Ñ válidos) + 6 dígitos + 3 alfanum — único
        'rfc'              => ['nullable','string','max:13','regex:/^([A-ZÑ&]{3,4})\d{6}([A-Z0-9]{3})$/i','unique:clientes,rfc'],
        'plan_id'          => 'nullable|integer|exists:planes,id',
        'plan'             => 'nullable|string|max:50',
        'activo'           => 'sometimes|boolean',
    ];

    public function create(): \Illuminate\Contracts\View\View { $this->inflate(); return parent::create(); }
    public function edit($id): \Illuminate\Contracts\View\View { $this->inflate(); return parent::edit($id); }
    private function inflate(): void
    {
        $planes = Plan::query()->orderBy('nombre')->get(['id','nombre','clave'])
            ->map(fn($p)=>['value'=>$p->id,'label'=>$p->nombre.' ('.$p->clave.')'])->all();
        foreach ($this->fields as &$f) if (($f['options'] ?? '') === '@planes') $f['options']=$planes;
    }
}
