<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SatDummyCredentialSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('sat_credentials')) {
            $this->command?->warn('[SatDummyCredentialSeeder] Tabla sat_credentials no existe, se omite.');
            return;
        }

        $conn    = DB::connection();
        $dbName  = $conn->getDatabaseName();
        $now     = now();

        // Detecta metadata real de la columna id
        $col = DB::selectOne("
            SELECT DATA_TYPE as data_type,
                   COLUMN_TYPE as column_type,
                   CHARACTER_MAXIMUM_LENGTH as char_len,
                   EXTRA as extra
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sat_credentials' AND COLUMN_NAME = 'id'
            LIMIT 1
        ", [$dbName]);

        $needsId   = true;
        $idValue   = null;
        $dataType  = $col->data_type ?? null;
        $extra     = strtolower((string)($col->extra ?? ''));
        $charLen   = (int)($col->char_len ?? 0);

        // Si el id ya es AUTO_INCREMENT, NO pasamos id manual
        if (str_contains($extra, 'auto_increment')) {
            $needsId = false;
        } else {
            // Genera id según el tipo real
            switch ($dataType) {
                case 'char':
                case 'varchar':
                    // Si el CHAR es 26 -> ULID; si ~36 -> UUID; si otro, recorta
                    if ($charLen >= 26 && $charLen < 36) {
                        $idValue = (string) Str::ulid();
                    } elseif ($charLen >= 36) {
                        $idValue = (string) Str::uuid();
                    } else {
                        // fallback: uuid sin guiones y recortado
                        $idValue = substr(str_replace('-', '', (string) Str::uuid()), 0, max(8, $charLen ?: 16));
                    }
                    break;

                case 'binary':
                case 'varbinary':
                    // Genera bytes aleatorios del tamaño de la columna (si lo sabemos), si no 16
                    $len     = $charLen > 0 ? $charLen : 16;
                    $idValue = random_bytes($len);
                    break;

                case 'bigint':
                case 'int':
                case 'mediumint':
                case 'smallint':
                case 'tinyint':
                    // Numérico sin AUTO_INCREMENT: calcula siguiente id
                    $maxId   = (int) (DB::table('sat_credentials')->max('id') ?? 0);
                    $idValue = $maxId + 1;
                    break;

                default:
                    // Desconocido: ULID como string
                    $idValue = (string) Str::ulid();
                    break;
            }
        }

        // Dummy target (ajústalo si quieres otro)
        $target = [
            'cuenta_id' => '9ed91364-5f83-4b9f-b1ad-8514b3c66598',
            'rfc'       => 'XAXX010101000',
        ];

        // Armado de payload respetando columnas existentes
        $payload = [
            'cer_path'          => null,
            'key_path'          => null,
            'key_password_enc'  => null,
            'meta'              => json_encode(['demo' => true, 'note' => 'Seeder dummy']),
            'validated_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        // Si la tabla NO tiene timestamps, elimínalos del payload
        foreach (['created_at','updated_at'] as $tsCol) {
            if (!Schema::hasColumn('sat_credentials', $tsCol)) {
                unset($payload[$tsCol]);
            }
        }

        // Inserta/actualiza de forma idempotente
        $exists = DB::table('sat_credentials')->where($target)->first();

        if ($exists) {
            DB::table('sat_credentials')->where($target)->update($payload);
            $this->command?->info('[SatDummyCredentialSeeder] Actualizado: '.$target['rfc']);
        } else {
            $insertData = array_merge($target, $payload);

            if ($needsId && $idValue !== null) {
                $insertData['id'] = $idValue;
            }

            DB::table('sat_credentials')->insert($insertData);
            $this->command?->info('[SatDummyCredentialSeeder] Insertado: '.$target['rfc'].' (id calculado)');
        }
    }
}
