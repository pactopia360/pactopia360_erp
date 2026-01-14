@extends('layouts.admin')
@section('title', $mode==='create' ? 'Nuevo admin' : 'Editar admin')
@section('pageClass','p360-admin-usuarios-admin')

@push('styles')
@php
  $CSS_ABS = public_path('assets/admin/css/usuarios-admin.css');
  $CSS_URL = asset('assets/admin/css/usuarios-admin.css') . (is_file($CSS_ABS) ? ('?v='.filemtime($CSS_ABS)) : '');
@endphp
<link rel="stylesheet" href="{{ $CSS_URL }}">
@endpush

@section('page-header')
  <div class="p360-ph">
    <div class="p360-ph-left">
      <div class="p360-ph-kicker">PACTOPIA 360</div>
      <h1 class="p360-ph-title">
        {{ $mode==='create' ? 'Crear usuario administrativo' : 'Editar usuario administrativo' }}
      </h1>
      <div class="p360-ph-sub">Credenciales, rol, permisos y flags.</div>
    </div>
    <div class="p360-ph-right">
      <a class="btnx ghost" href="{{ route('admin.usuarios.administrativos.index') }}">← Volver</a>
    </div>
  </div>
@endsection

@section('content')
  <div class="p360-card">
    <form method="POST"
          action="{{ $mode==='create' ? route('admin.usuarios.administrativos.store') : route('admin.usuarios.administrativos.update', $row->id) }}">
      @csrf
      @if($mode==='edit') @method('PUT') @endif

      <div class="grid">
        <div class="field">
          <label>Nombre</label>
          <input name="nombre" value="{{ old('nombre', $row->nombre) }}" required>
          @error('nombre') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field">
          <label>Email</label>
          <input name="email" type="email" value="{{ old('email', $row->email) }}" required>
          @error('email') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field">
          <label>{{ $mode==='create' ? 'Password' : 'Password (opcional)' }}</label>
          <input name="password" type="password" {{ $mode==='create' ? 'required' : '' }}
                 placeholder="{{ $mode==='create' ? '' : 'Dejar vacío para no cambiar' }}">
          @error('password') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field sm">
          <label>Rol</label>
          <input name="rol" value="{{ old('rol', $row->rol) }}" placeholder="ej. admin, soporte, auditor…">
          @error('rol') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field sm">
          <label>Estado</label>
          <select name="activo">
            <option value="1" @selected((int)old('activo', (int)$row->activo)===1)>Activo</option>
            <option value="0" @selected((int)old('activo', (int)$row->activo)===0)>Inactivo</option>
          </select>
          @error('activo') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field sm">
          <label>SuperAdmin</label>
          <select name="es_superadmin">
            <option value="0" @selected((int)old('es_superadmin', (int)$row->es_superadmin)===0)>No</option>
            <option value="1" @selected((int)old('es_superadmin', (int)$row->es_superadmin)===1)>Sí</option>
          </select>
          @error('es_superadmin') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field sm">
          <label>Forzar cambio password</label>
          <select name="force_password_change">
            <option value="0" @selected((int)old('force_password_change', (int)$row->force_password_change)===0)>No</option>
            <option value="1" @selected((int)old('force_password_change', (int)$row->force_password_change)===1)>Sí</option>
          </select>
          @error('force_password_change') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field" style="grid-column:1 / -1">
          <label>Permisos (uno por línea o separados por coma)</label>
          @php
            $permsText = old('permisos_text', is_array($row->permisos ?? null) ? implode("\n", $row->permisos) : (string)($row->permisos ?? ''));
          @endphp
          <textarea name="permisos_text" rows="6" placeholder="clientes.ver&#10;clientes.editar&#10;billing.ver">{{ $permsText }}</textarea>
          @error('permisos_text') <div class="err">{{ $message }}</div> @enderror
          <div class="muted">Se guardan como JSON en la columna <span class="mono">permisos</span>.</div>
        </div>

        @if($mode==='edit')
          <div class="field sm">
            <label>Último login</label>
            <input value="{{ $row->last_login_at ?: '—' }}" disabled>
          </div>
          <div class="field sm">
            <label>IP</label>
            <input value="{{ $row->last_login_ip ?: '—' }}" disabled>
          </div>
        @endif
      </div>

      <div class="actions">
        <button class="btnx primary" type="submit">{{ $mode==='create' ? 'Crear' : 'Guardar cambios' }}</button>
        <a class="btnx ghost" href="{{ route('admin.usuarios.administrativos.index') }}">Cancelar</a>
      </div>
    </form>

    @if($mode==='edit')
      <hr class="sep">

      <div class="p360-mini">
        <div>
          <div class="title">Reset password</div>
          <div class="muted">Si dejas vacío, se genera uno aleatorio y además se marca <span class="mono">force_password_change=1</span>.</div>
        </div>

        <form method="POST" action="{{ route('admin.usuarios.administrativos.reset_password', $row->id) }}" class="inline">
          @csrf
          <input type="text" name="new_password" placeholder="Nueva contraseña (opcional)">
          <button class="btnx" type="submit">Reset</button>
        </form>
      </div>

      @error('new_password') <div class="err">{{ $message }}</div> @enderror
    @endif
  </div>
@endsection
