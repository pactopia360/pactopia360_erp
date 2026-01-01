<p>Hola,</p>
<p>Este es el resumen de tu licencia y configuración en Pactopia360.</p>

<ul>
  <li><strong>RFC:</strong> {{ $account->rfc ?? '—' }}</li>
  <li><strong>Razón Social:</strong> {{ $account->razon_social ?? $account->name ?? '—' }}</li>
  <li><strong>Precio:</strong>
    @if($price)
      {{ $price->plan }} · {{ $price->billing_cycle }} · ${{ number_format((float)($price->display_amount ?? 0),2) }} {{ $price->currency }}
    @else
      No asignado
    @endif
  </li>
  <li><strong>Concepto:</strong> {{ $concept ?? '—' }}</li>
</ul>

<p><strong>Módulos:</strong></p>
<pre style="background:#f3f4f6;padding:10px;border-radius:8px">{{ json_encode(($meta['modules'] ?? []), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>

<p>Atentamente,<br>PACTOPIA360</p>
