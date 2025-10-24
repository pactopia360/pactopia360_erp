<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si tienes doctrine/dbal instalado puedes usar ->change().
        // Sin DBAL, usa SQL crudo que funciona en MySQL/MariaDB:
        // Cambia a VARCHAR(36) (o 191 si prefieres)
        DB::statement("ALTER TABLE `sessions` MODIFY `user_id` VARCHAR(36) NULL");

        // Opcional: asegúrate de que exista índice sobre user_id para consultas por usuario
        // (Si ya lo tenías, no hace falta. Descomenta si lo necesitas)
        // DB::statement("CREATE INDEX sessions_user_id_index ON `sessions` (`user_id`)");
    }

    public function down(): void
    {
        // Regrésalo a BIGINT UNSIGNED NULL si antes lo tenías así
        DB::statement("ALTER TABLE `sessions` MODIFY `user_id` BIGINT UNSIGNED NULL");
    }
};
