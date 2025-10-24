<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

abstract class BaseAdminModel extends Model
{
    protected $connection = 'mysql_admin';
}
