<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class P360GeneratePwaIcons extends Command
{
    protected $signature = 'p360:icons {--force : Sobrescribe aunque ya existan}';
    protected $description = 'Genera los íconos PWA en public/assets/admin/img (192/512, normales y maskable).';

    public function handle(): int
    {
        $base = public_path('assets/admin/img');
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }

        $targets = [
            ['file' => 'icon-192.png',           'w' => 192, 'h' => 192],
            ['file' => 'icon-192-maskable.png',  'w' => 192, 'h' => 192],
            ['file' => 'icon-512.png',           'w' => 512, 'h' => 512],
            ['file' => 'icon-512-maskable.png',  'w' => 512, 'h' => 512],
        ];

        $force = (bool) $this->option('force');
        $made  = [];

        foreach ($targets as $t) {
            $path = $base . DIRECTORY_SEPARATOR . $t['file'];
            $shouldMake = $force || !$this->isPngWithSize($path, $t['w'], $t['h']);

            $ok = $shouldMake ? $this->makePng($t['w'], $t['h'], $path) : true;

            $made[] = [
                'file' => str_replace(public_path(), '', $path),
                'ok'   => $ok,
                'bytes'=> $ok ? @filesize($path) : 0,
                'mime' => $ok ? (@mime_content_type($path) ?: '') : '',
                'dim'  => $this->dims($path),
                'note' => $shouldMake ? ($ok ? 'recreado' : 'falló') : 'ok (existente)',
            ];

            $this->line(($ok ? '✔︎' : '✗') . " {$t['file']} " . ($shouldMake ? 'recreado' : 'ok existente'));
        }

        $this->newLine();
        $this->table(['file', 'ok', 'bytes', 'mime', 'dim', 'nota'], $made);
        $this->info('Listo. Deben medir exactamente 192×192 y 512×512.');

        return self::SUCCESS;
    }

    private function isPngWithSize(string $path, int $w, int $h): bool
    {
        if (!is_file($path)) return false;
        if ((@mime_content_type($path) ?: '') !== 'image/png') return false;
        [$iw, $ih] = @getimagesize($path) ?: [0, 0];
        return ($iw === $w && $ih === $h);
    }

    private function dims(string $path): string
    {
        if (!is_file($path)) return '-';
        [$iw, $ih] = @getimagesize($path) ?: [0, 0];
        return $iw && $ih ? "{$iw}x{$ih}" : '-';
    }

    /**
     * Crea un PNG exacto w×h; usa GD si existe, si no, genera uno 1×1 y falla dimensión (no recomendado).
     */
    private function makePng(int $w, int $h, string $path): bool
    {
        if (function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor($w, $h);
            imagesavealpha($im, true);

            // Fondo azul marino
            $bg = imagecolorallocate($im, 15, 23, 42);   // #0f172a
            imagefilledrectangle($im, 0, 0, $w, $h, $bg);

            // Aro rojo
            $fg = imagecolorallocate($im, 225, 29, 46);  // #e11d2e
            imagesetthickness($im, max(4, (int)round($w * 0.04)));
            imagearc($im, (int)($w*0.5), (int)($h*0.5), (int)($w*0.64), (int)($h*0.64), 0, 360, $fg);

            // Texto P360
            $font = 5;
            $text = 'P360';
            $tw = imagefontwidth($font) * strlen($text);
            $th = imagefontheight($font);
            $tx = (int)(($w - $tw) / 2);
            $ty = (int)(($h - $th) / 2);
            imagestring($im, $font, $tx, $ty, $text, $fg);

            $ok = imagepng($im, $path);
            imagedestroy($im);
            return (bool) $ok;
        }

        // Fallback SIN GD: 1x1 transparente (no cumple tamaño)
        $b64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
        return (bool) file_put_contents($path, base64_decode($b64));
    }
}
