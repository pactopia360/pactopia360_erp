<?php

declare(strict_types=1);

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmpleadoNomina extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql_clientes';

    protected $table = 'empleados_nomina';

    protected $fillable = [
        'cuenta_id',
        'numero_empleado',
        'rfc',
        'curp',
        'nss',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'nombre_completo',
        'email',
        'telefono',
        'codigo_postal',
        'regimen_fiscal',
        'uso_cfdi',
        'fecha_inicio_relacion_laboral',
        'tipo_contrato',
        'tipo_jornada',
        'tipo_regimen',
        'periodicidad_pago',
        'departamento',
        'puesto',
        'riesgo_puesto',
        'salario_base_cot_apor',
        'salario_diario_integrado',
        'banco',
        'cuenta_bancaria',
        'sindicalizado',
        'activo',
        'metodo_asistencia',
        'codigo_biometrico',
        'pin_asistencia',
        'telefono_whatsapp',
        'dispositivo_biometrico',
        'sincronizar_asistencia',
        'meta_asistencia',
        'meta',
    ];

    protected $casts = [
        'fecha_inicio_relacion_laboral' => 'date',
        'salario_base_cot_apor' => 'decimal:2',
        'salario_diario_integrado' => 'decimal:2',
        'sindicalizado' => 'boolean',
        'activo' => 'boolean',
        'sincronizar_asistencia' => 'boolean',
        'meta_asistencia' => 'array',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmpleadoNomina $empleado) {
            $empleado->rfc = strtoupper(preg_replace('/[^A-ZÑ&0-9]/i', '', (string) $empleado->rfc));
            $empleado->curp = $empleado->curp ? strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $empleado->curp)) : null;
            $empleado->uso_cfdi = $empleado->uso_cfdi ?: 'CN01';
            $empleado->regimen_fiscal = $empleado->regimen_fiscal ?: '605';

            $empleado->telefono_whatsapp = $empleado->telefono_whatsapp
                ? preg_replace('/\D+/', '', (string) $empleado->telefono_whatsapp)
                : null;

            $empleado->codigo_biometrico = $empleado->codigo_biometrico
                ? strtoupper(trim((string) $empleado->codigo_biometrico))
                : null;

            $empleado->metodo_asistencia = $empleado->metodo_asistencia ?: 'manual';

            $nombreCompleto = trim(implode(' ', array_filter([
                $empleado->nombre,
                $empleado->apellido_paterno,
                $empleado->apellido_materno,
            ])));

            $empleado->nombre_completo = mb_strtoupper($nombreCompleto, 'UTF-8');
        });
    }

    public function getLabelAttribute(): string
    {
        return trim(($this->nombre_completo ?: 'Empleado') . ' · ' . $this->rfc);
    }

    public function getMetodoAsistenciaLabelAttribute(): string
    {
        return match ((string) $this->metodo_asistencia) {
            'whatsapp' => 'WhatsApp',
            'biometrico' => 'Biométrico',
            'mixto' => 'Mixto',
            default => 'Manual',
        };
    }
}