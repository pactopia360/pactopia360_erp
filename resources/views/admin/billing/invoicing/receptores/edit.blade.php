@extends('layouts.admin')

@section('title', 'Facturación · Editar receptor')
@section('contentLayout', 'contained')
@section('pageClass', 'billing-receptores-edit-page')

@include('admin.billing.invoicing.receptores.partials.form', [
    'mode' => 'edit',
    'titleText' => 'Editar receptor',
    'submitText' => 'Actualizar receptor',
    'actionUrl' => route('admin.billing.invoicing.receptores.update', $row->id),
    'row' => $row,
    'cuentas' => $cuentas ?? [],
])