@extends('layouts.admin')

@section('title', 'Facturación · Editar emisor')
@section('contentLayout', 'contained')
@section('pageClass', 'billing-emisores-edit-page')

@section('content')
    @include('admin.billing.invoicing.emisores.partials.form', [
        'mode' => 'edit',
        'titleText' => 'Editar emisor',
        'submitText' => 'Actualizar emisor',
        'actionUrl' => route('admin.billing.invoicing.emisores.update', (int) $row->id),
        'row' => $row,
        'cuentas' => $cuentas ?? [],
    ])
@endsection