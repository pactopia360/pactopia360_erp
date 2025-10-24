@extends('admin.layout') {{-- ajusta al layout que uses --}}

@section('title', 'Reset de contraseña (Cliente por RFC)')

@section('content')
<div class="container" style="max-width:680px">
    <h1 class="mb-4">Reset de contraseña (Cliente por RFC)</h1>

    @if(session('ok'))
        <div class="alert alert-success">
            <strong>{{ session('msg') }}</strong>
            @php($reset = session('reset'))
            <div class="mt-2">
                <div><b>RFC:</b> {{ $reset['rfc'] ?? '' }}</div>
                <div><b>Cuenta ID:</b> {{ $reset['cuenta_id'] ?? '' }}</div>
                <div><b>Usuario ID:</b> {{ $reset['user_id'] ?? '' }}</div>
                <div><b>Email:</b> {{ $reset['email'] ?? '' }}</div>
                <div class="mt-2">
                    <b>Contraseña temporal:</b>
                    <code style="font-size:1.05rem">{{ $reset['password'] ?? '' }}</code>
                    <span class="text-muted d-block mt-1">Pásala al cliente. Al iniciar sesión por RFC o correo, se le pedirá cambiarla.</span>
                </div>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $e)
                <div>• {{ $e }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.soporte.reset_pass.do') }}" class="card p-3">
        @csrf
        <div class="mb-3">
            <label for="rfc" class="form-label">RFC del cliente</label>
            <input type="text" id="rfc" name="rfc" class="form-control" placeholder="GODE561231GR8"
                   value="{{ old('rfc') }}" required maxlength="32" autocomplete="off" />
            <div class="form-text">Se resetea la contraseña del OWNER (o el primer usuario existente) de la cuenta.</div>
        </div>
        <button type="submit" class="btn btn-primary">
            Resetear contraseña y generar temporal
        </button>
    </form>
</div>
@endsection
