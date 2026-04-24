<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSepomexCodigosPostales extends Command
{
    protected $signature = 'sepomex:import {path=storage/app/imports/sepomex.csv} {--fresh}';

    protected $description = 'Importa catálogo SEPOMEX nacional a mysql_clientes';

    public function handle(): int
    {
        $path = base_path($this->argument('path'));

        if (!is_file($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::connection('mysql_clientes')->table('sepomex_codigos_postales')->truncate();
            $this->warn('Tabla sepomex_codigos_postales vaciada.');
        }

        $handle = fopen($path, 'rb');

        if (!$handle) {
            $this->error('No se pudo abrir el archivo.');
            return self::FAILURE;
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);
            $this->error('El archivo está vacío.');
            return self::FAILURE;
        }

        $delimiter = $this->detectDelimiter($firstLine);

        $headers = $this->parseLine($firstLine, $delimiter);
        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        $batch = [];
        $count = 0;
        $skipped = 0;

        $this->info('Importando SEPOMEX...');
        $this->line('Delimitador detectado: ' . ($delimiter === "\t" ? 'TAB' : $delimiter));

        while (($line = fgets($handle)) !== false) {
            $row = $this->parseLine($line, $delimiter);

            if (count($row) !== count($headers)) {
                $skipped++;
                continue;
            }

            $data = array_combine($headers, $row);

            if (!is_array($data)) {
                $skipped++;
                continue;
            }

            $codigoPostal = $this->cp($data['d_codigo'] ?? '');

            if ($codigoPostal === '') {
                $skipped++;
                continue;
            }

            $batch[] = [
                'codigo_postal'      => $codigoPostal,
                'estado'             => $this->txt($data['d_estado'] ?? ''),
                'municipio'          => $this->txt($data['D_mnpio'] ?? ''),
                'ciudad'             => $this->txt($data['d_ciudad'] ?? ''),
                'colonia'            => $this->txt($data['d_asenta'] ?? ''),
                'tipo_asentamiento'  => $this->txt($data['d_tipo_asenta'] ?? ''),
                'estado_clave'       => $this->key($data['c_estado'] ?? ''),
                'municipio_clave'    => $this->key($data['c_mnpio'] ?? ''),
                'zona'               => $this->txt($data['d_zona'] ?? ''),
                'activo'             => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            if (count($batch) >= 1000) {
                DB::connection('mysql_clientes')
                    ->table('sepomex_codigos_postales')
                    ->insertOrIgnore($batch);

                $count += count($batch);
                $this->line("Procesados: {$count}");
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::connection('mysql_clientes')
                ->table('sepomex_codigos_postales')
                ->insertOrIgnore($batch);

            $count += count($batch);
        }

        fclose($handle);

        $totalDb = DB::connection('mysql_clientes')
            ->table('sepomex_codigos_postales')
            ->count();

        $this->info("✅ Importación completa.");
        $this->info("Procesados: {$count}");
        $this->info("Saltados: {$skipped}");
        $this->info("Total en BD: {$totalDb}");

        return self::SUCCESS;
    }

    private function detectDelimiter(string $line): string
    {
        $tabs = substr_count($line, "\t");
        $commas = substr_count($line, ',');
        $semicolons = substr_count($line, ';');

        if ($tabs >= $commas && $tabs >= $semicolons) {
            return "\t";
        }

        if ($semicolons >= $commas) {
            return ';';
        }

        return ',';
    }

    private function parseLine(string $line, string $delimiter): array
    {
        $line = $this->txt($line);

        return array_map(
            fn ($value) => $this->txt($value),
            str_getcsv($line, $delimiter)
        );
    }

    private function txt(mixed $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
        }

        return trim($value);
    }

    private function cp(mixed $value): string
    {
        $value = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($value === '') {
            return '';
        }

        return str_pad(substr($value, 0, 5), 5, '0', STR_PAD_LEFT);
    }

    private function key(mixed $value): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $value !== '' ? $value : null;
    }
}