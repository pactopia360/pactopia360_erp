@extends('layouts.admin')

@section('title', 'Facturación · Nuevo emisor')
@section('layout', 'full')
@section('contentLayout', 'full')
@section('pageClass', 'billing-emisores-create-page')

@section('content')
    @include('admin.billing.invoicing.emisores.partials.form', [
        'mode' => 'create',
        'titleText' => 'Nuevo emisor',
        'submitText' => 'Guardar emisor',
        'actionUrl' => route('admin.billing.invoicing.emisores.store'),
        'row' => null,
        'cuentas' => $cuentas ?? [],
    ])
@endsection