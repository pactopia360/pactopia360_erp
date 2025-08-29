<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $fillable = ['user_id','action','entity_type','entity_id','meta','ip'];
    protected $casts = ['meta'=>'array'];
}
