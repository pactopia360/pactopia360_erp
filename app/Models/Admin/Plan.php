<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Plan extends BaseAdminModel
{
    protected $table = 'planes';
    protected $guarded = [];
    public $timestamps = true;
}
