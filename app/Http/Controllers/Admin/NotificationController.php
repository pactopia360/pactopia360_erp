<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(): View
    {
        [$items] = $this->fetchList();
        return view('admin.notificaciones.index', ['items'=>$items]);
    }

    public function count(): JsonResponse
    {
        $count = 0;
        if (Schema::hasTable('notifications')) {
            $count = DB::table('notifications')->whereNull('read_at')->count();
        } elseif (Schema::hasTable('notificaciones')) {
            $readCol = Schema::hasColumn('notificaciones','leida') ? 'leida' : (Schema::hasColumn('notificaciones','read_at') ? 'read_at' : null);
            if ($readCol) {
                $count = DB::table('notificaciones')->where(function($w) use ($readCol){
                    if ($readCol === 'leida') $w->where('leida',0);
                    else $w->whereNull('read_at');
                })->count();
            }
        } else {
            $count = (int) session('admin_unread_notifications', 0);
        }
        return response()->json(['count'=>$count]);
    }

    public function list(): JsonResponse
    {
        [$items, $moreUrl] = $this->fetchList();
        return response()->json(['items'=>$items, 'more_url'=>$moreUrl]);
    }

    public function readAll(): JsonResponse
    {
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->whereNull('read_at')->update(['read_at'=>now()]);
        } elseif (Schema::hasTable('notificaciones')) {
            if (Schema::hasColumn('notificaciones','leida')) {
                DB::table('notificaciones')->where('leida',0)->update(['leida'=>1]);
            } elseif (Schema::hasColumn('notificaciones','read_at')) {
                DB::table('notificaciones')->whereNull('read_at')->update(['read_at'=>now()]);
            }
        } else {
            session(['admin_unread_notifications'=>0]);
        }
        return response()->json(['ok'=>true]);
    }

    private function fetchList(): array
    {
        $items = [];
        if (Schema::hasTable('notifications')) {
            $rows = DB::table('notifications')->orderByDesc('created_at')->limit(20)->get();
            foreach ($rows as $r) {
                $items[] = [
                    'title' => $r->type ?? 'Notificación',
                    'text'  => is_string($r->data ?? null) ? $r->data : '',
                    'date'  => (string)($r->created_at ?? ''),
                ];
            }
        } elseif (Schema::hasTable('notificaciones')) {
            $titleCol = Schema::hasColumn('notificaciones','titulo') ? 'titulo' : (Schema::hasColumn('notificaciones','title') ? 'title' : null);
            $bodyCol  = Schema::hasColumn('notificaciones','contenido') ? 'contenido' : (Schema::hasColumn('notificaciones','body') ? 'body' : null);
            $dateCol  = Schema::hasColumn('notificaciones','fecha') ? 'fecha' : (Schema::hasColumn('notificaciones','created_at') ? 'created_at' : null);
            $rows = DB::table('notificaciones')->orderByDesc($dateCol ?? 'id')->limit(20)->get();
            foreach ($rows as $r) {
                $items[] = [
                    'title' => $titleCol ? (string)$r->{$titleCol} : 'Notificación',
                    'text'  => $bodyCol ? (string)$r->{$bodyCol}  : '',
                    'date'  => $dateCol ? (string)$r->{$dateCol}  : '',
                ];
            }
        }
        $more = route('admin.notificaciones');
        return [$items, $more];
    }
}
