<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $req): View
    {
        $q = AuditLog::query()->latest();
        if ($s = trim((string)$req->get('q'))) {
            $q->where('action','like',"%$s%")->orWhere('entity_type','like',"%$s%");
        }
        $items = $q->paginate(30);
        return view('admin.crud.index', [
            'items'=>$items,
            'fields'=>[
                ['name'=>'id','label'=>'#','type'=>'text'],
                ['name'=>'action','label'=>'Acción','type'=>'text'],
                ['name'=>'entity_type','label'=>'Entidad','type'=>'text'],
                ['name'=>'entity_id','label'=>'ID','type'=>'text'],
                ['name'=>'ip','label'=>'IP','type'=>'text'],
                ['name'=>'created_at','label'=>'Fecha','type'=>'text'],
            ],
            'titles'=>['index'=>'Auditoría'],
            'routeBase'=>'admin.auditoria'
        ]);
    }

    public function destroy($id)
    {
        AuditLog::whereKey($id)->delete();
        return back()->with('status','Log eliminado');
    }
}
