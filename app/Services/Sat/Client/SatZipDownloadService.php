<?php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatDownload;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SatZipDownloadService
{
    public function download(string $cuentaId, string $id): StreamedResponse
    {
        $download = SatDownload::on('mysql_clientes')
            ->where('cuenta_id',$cuentaId)
            ->where('id',$id)
            ->firstOrFail();

        $disk = config('filesystems.default','local');
        $path = ltrim((string)$download->zip_path,'/');

        if (!Storage::disk($disk)->exists($path)) {
            abort(404,'Archivo no disponible');
        }

        return Storage::disk($disk)->download(
            $path,
            $download->zip_name ?? "sat_{$id}.zip"
        );
    }
}
