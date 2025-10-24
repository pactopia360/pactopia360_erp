<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Pago extends BaseAdminModel
{
    protected $table = 'pagos';
    protected $guarded = [];
    public $timestamps = true;
}
