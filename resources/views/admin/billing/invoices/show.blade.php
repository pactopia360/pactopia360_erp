{{-- resources/views/admin/billing/invoices/show.blade.php --}}
@extends('layouts.admin')

@section('title','Factura · Detalle')
@section('pageClass','admin-invoice-show')

@php
$invoice = $invoice ?? null;

$id = $invoice->id ?? null;
$uuid = $invoice->cfdi_uuid ?? null;

$serie = $invoice->serie ?? '';
$folio = $invoice->folio ?? '';

$status = strtolower($invoice->status ?? 'draft');

$emisorNombre = $invoice->emisor_nombre ?? config('app.name');
$emisorRfc = $invoice->emisor_rfc ?? '';

$receptorNombre = $invoice->receptor_nombre ?? '';
$receptorRfc = $invoice->receptor_rfc ?? '';

$total = $invoice->total ?? $invoice->amount_mxn ?? 0;

$pdf = $invoice->pdf_path ?? null;
$xml = $invoice->xml_path ?? null;

$issuedAt = $invoice->issued_at ?? $invoice->created_at ?? null;

function money($v){
    return '$'.number_format((float)$v,2);
}

function datefmt($v){
    if(!$v) return '—';
    try{
        return \Carbon\Carbon::parse($v)->format('Y-m-d H:i');
    }catch(\Throwable $e){
        return $v;
    }
}
@endphp


@push('styles')
<style>

.invoice-page{
display:grid;
gap:20px;
}

.invoice-top{
display:flex;
justify-content:space-between;
align-items:center;
flex-wrap:wrap;
gap:10px;
}

.invoice-title{
font-size:28px;
font-weight:900;
}

.invoice-actions{
display:flex;
gap:10px;
flex-wrap:wrap;
}

.btn{
padding:10px 16px;
border-radius:10px;
font-weight:700;
border:1px solid #ddd;
background:#fff;
cursor:pointer;
}

.btn.primary{
background:#2563eb;
color:white;
border:none;
}

.btn.warn{
background:#f59e0b;
color:white;
border:none;
}

.btn.danger{
background:#dc2626;
color:white;
border:none;
}

.btn.success{
background:#059669;
color:white;
border:none;
}

.card{
background:white;
border:1px solid #e5e7eb;
border-radius:16px;
padding:20px;
display:grid;
gap:15px;
}

.card-title{
font-size:16px;
font-weight:900;
}

.grid{
display:grid;
gap:16px;
}

.grid-2{
grid-template-columns:1fr 1fr;
}

.grid-3{
grid-template-columns:repeat(3,1fr);
}

.kpi{
background:#f9fafb;
padding:14px;
border-radius:10px;
}

.kpi .k{
font-size:11px;
color:#6b7280;
text-transform:uppercase;
font-weight:800;
}

.kpi .v{
font-size:20px;
font-weight:900;
}

.status{
display:inline-flex;
align-items:center;
gap:6px;
font-size:12px;
padding:4px 10px;
border-radius:20px;
background:#f3f4f6;
font-weight:800;
}

.files{
display:flex;
gap:10px;
flex-wrap:wrap;
}

.filebox{
border:1px dashed #ddd;
padding:10px;
border-radius:10px;
}

pre{
background:#111827;
color:#e5e7eb;
padding:10px;
border-radius:10px;
font-size:12px;
overflow:auto;
}

</style>
@endpush


@section('content')

<div class="invoice-page">


<div class="invoice-top">

<div>

<div class="invoice-title">
Factura {{ $serie }}{{ $folio }}
</div>

<div style="color:#6b7280;font-size:14px">
UUID: {{ $uuid ?? '—' }}
</div>

</div>


<div class="invoice-actions">

<a href="{{ route('admin.billing.invoicing.invoices.index') }}" class="btn">
← Volver
</a>

@if(!$uuid)
<form method="POST" action="{{ route('admin.billing.invoicing.invoices.stamp',$id) }}">
@csrf
<button class="btn primary">
Timbrar CFDI
</button>
</form>
@endif

@if($uuid)
<form method="POST" action="{{ route('admin.billing.invoicing.invoices.cancel',$id) }}">
@csrf
<button class="btn danger">
Cancelar CFDI
</button>
</form>
@endif

</div>

</div>



<div class="card">

<div class="card-title">
Resumen de factura
</div>

<div class="grid grid-3">

<div class="kpi">
<div class="k">Factura</div>
<div class="v">#{{ $id }}</div>
</div>

<div class="kpi">
<div class="k">Estado</div>
<div class="v">{{ strtoupper($status) }}</div>
</div>

<div class="kpi">
<div class="k">Total</div>
<div class="v">{{ money($total) }}</div>
</div>

<div class="kpi">
<div class="k">Emitida</div>
<div class="v">{{ datefmt($issuedAt) }}</div>
</div>

<div class="kpi">
<div class="k">UUID</div>
<div class="v" style="font-size:14px">
{{ $uuid ?? '—' }}
</div>
</div>

<div class="kpi">
<div class="k">Tipo CFDI</div>
<div class="v">
{{ $invoice->tipo_cfdi ?? 'I' }}
</div>
</div>

</div>

</div>



<div class="grid grid-2">

<div class="card">

<div class="card-title">
Emisor
</div>

<div>
<strong>{{ $emisorNombre }}</strong>
</div>

<div>
RFC: {{ $emisorRfc }}
</div>

</div>



<div class="card">

<div class="card-title">
Receptor
</div>

<div>
<strong>{{ $receptorNombre ?: '—' }}</strong>
</div>

<div>
RFC: {{ $receptorRfc ?: '—' }}
</div>

</div>

</div>



<div class="card">

<div class="card-title">
Archivos CFDI
</div>

<div class="files">

@if($pdf)
<a href="{{ route('admin.billing.invoicing.invoices.download',[$id,'pdf']) }}" class="btn">
Descargar PDF
</a>
@endif

@if($xml)
<a href="{{ route('admin.billing.invoicing.invoices.download',[$id,'xml']) }}" class="btn">
Descargar XML
</a>
@endif

@if(!$pdf && !$xml)
<div class="filebox">
Aún no hay archivos CFDI generados
</div>
@endif

</div>

</div>



<div class="card">

<div class="card-title">
Enviar factura
</div>

<form method="POST" action="{{ route('admin.billing.invoicing.invoices.send',$id) }}">

@csrf

<div class="grid grid-2">

<input
type="text"
name="to"
placeholder="correo1@cliente.com,correo2@cliente.com"
style="padding:10px;border:1px solid #ddd;border-radius:8px"
>

<button class="btn success">
Enviar factura
</button>

</div>

</form>

</div>



@if($invoice)

<div class="card">

<div class="card-title">
JSON técnico
</div>

<pre>{{ json_encode($invoice,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>

</div>

@endif



</div>

@endsection