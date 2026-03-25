@extends('layouts.cliente')

@section('title', 'SAT Bóveda v2 · Pactopia360')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-column gap-3">

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-2">SAT Bóveda v2</h1>
                <p class="text-muted mb-0">
                    Base nueva por usuario. Aquí vamos a construir metadata → XML → conciliación.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('cliente.sat.v2.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">RFC de trabajo</label>
                        <select name="rfc" class="form-select">
                            <option value="">Selecciona un RFC</option>
                            @foreach($rfcs as $rfc)
                                <option value="{{ $rfc->rfc }}" {{ $selectedRfc === $rfc->rfc ? 'selected' : '' }}>
                                    {{ $rfc->rfc }} — {{ $rfc->razon_social ?: 'Sin razón social' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Cargar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Metadata procesada</div>
                        <div class="fs-3 fw-bold">{{ number_format($metadataCount) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">CFDI XML procesados</div>
                        <div class="fs-3 fw-bold">{{ number_format($cfdiCount) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Lotes metadata</div>
                        <div class="fs-3 fw-bold">{{ number_format($metadataUploads->count()) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Lotes XML</div>
                        <div class="fs-3 fw-bold">{{ number_format($xmlUploads->count()) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Estado del paso actual</h2>
                <ul class="mb-0">
                    <li>Base de datos nueva creada por usuario.</li>
                    <li>Acceso nuevo por módulo <strong>sat_boveda_v2</strong>.</li>
                    <li>Selector RFC listo.</li>
                    <li>Siguiente paso: carga de metadata TXT/CSV/ZIP.</li>
                </ul>
            </div>
        </div>

    </div>
</div>
@endsection