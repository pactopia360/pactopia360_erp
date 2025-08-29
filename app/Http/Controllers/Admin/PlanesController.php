<?php

namespace App\Http\Controllers\Admin;

use App\Models\Plan;

class PlanesController extends CrudController
{
    protected string $model = Plan::class;
    protected string $routeBase = 'admin.planes';
    protected array $titles = ['index'=>'Planes','create'=>'Nuevo plan','edit'=>'Editar plan'];
    protected array $fields = [
        ['name'=>'clave','label'=>'Clave','type'=>'text','required'=>true],
        ['name'=>'nombre','label'=>'Nombre','type'=>'text','required'=>true],
        ['name'=>'precio_mensual','label'=>'Precio mensual','type'=>'number','step'=>'0.01'],
        ['name'=>'activo','label'=>'Activo','type'=>'switch'],
    ];
    protected array $rules = [
        'clave'          => 'required|string|max:60|alpha_dash|unique:planes,clave',
        'nombre'         => 'required|string|max:120',
        'precio_mensual' => 'nullable|numeric|min:0',
        'activo'         => 'sometimes|boolean',
    ];
}
