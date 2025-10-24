<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Cfdi extends BaseAdminModel
{
    protected $table = 'cfdis'; // ajusta si tu tabla tiene otro nombre
    protected $guarded = [];
    public $timestamps = true;  // ajusta si no usas timestamps
}
