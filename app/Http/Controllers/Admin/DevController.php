<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class DevController
{
    public function truncate(Request $r)
    {
        $table = $r->string('table');
        DB::connection('mysql_admin')->statement("TRUNCATE TABLE `{$table}`");
        Log::channel('home')->warning('[dev.truncate]', ['table'=>$table,'user_id'=>auth()->id()]);
        return response()->json(['ok'=>true]);
    }

    public function optimize()
    {
        Artisan::call('optimize:clear');
        return response()->json(['ok'=>true,'msg'=>'Caches cleared']);
    }

    public function exportTable(Request $r)
    {
        $table = $r->string('table');
        $rows = DB::connection('mysql_admin')->table($table)->get();
        return response()->json(['table'=>$table,'rows'=>$rows]);
    }

    public function tail()
    {
        $path = storage_path('logs/home.log');
        $txt = file_exists($path) ? substr(file_get_contents($path), -20000) : '';
        return response($txt, 200, ['Content-Type'=>'text/plain; charset=UTF-8']);
    }
}
