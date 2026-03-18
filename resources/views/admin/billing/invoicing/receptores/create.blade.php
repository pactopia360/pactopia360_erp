@extends('layouts.admin')

@section('title', 'Facturación · Nuevo receptor')
@section('contentLayout', 'contained')
@section('pageClass', 'billing-receptores-create-page')

@include('admin.billing.invoicing.receptores.partials.form', [
    'mode' => 'create',
    'titleText' => 'Nuevo receptor',
    'submitText' => 'Guardar receptor',
    'actionUrl' => route('admin.billing.invoicing.receptores.store'),
    'row' => null,
    'cuentas' => $cuentas ?? [],
])