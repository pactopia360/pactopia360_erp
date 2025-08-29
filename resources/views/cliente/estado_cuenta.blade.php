@extends('cliente.layouts.app')

@section('content')
<div class="p-6">
  <h1 class="text-2xl font-bold mb-4">Estado de cuenta</h1>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead><tr>
        <th class="text-left p-2">Periodo</th>
        <th class="text-right p-2">Cargo</th>
        <th class="text-right p-2">Abono</th>
        <th class="text-right p-2">Saldo</th>
        <th class="text-left p-2">Concepto</th>
      </tr></thead>
      <tbody>
        @forelse($movs as $m)
          <tr class="border-b">
            <td class="p-2">{{ \Illuminate\Support\Carbon::parse($m->periodo)->format('Y-m') }}</td>
            <td class="p-2 text-right">${{ number_format($m->cargo,2) }}</td>
            <td class="p-2 text-right">${{ number_format($m->abono,2) }}</td>
            <td class="p-2 text-right">${{ number_format($m->saldo,2) }}</td>
            <td class="p-2">{{ $m->concepto }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="p-4 text-center text-zinc-500">Sin movimientos</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-6">
    <a href="/pago" class="btn-primary">Ir a pagar</a>
  </div>
</div>
@endsection
