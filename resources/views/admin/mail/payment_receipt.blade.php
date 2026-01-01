<p>Hola,</p>
<p>Recibo de pago registrado en Pactopia360.</p>

<ul>
  <li><strong>Pago #:</strong> {{ $payment->id }}</li>
  <li><strong>Cuenta:</strong> {{ $payment->account_id }}</li>
  <li><strong>Monto:</strong> ${{ number_format((float)$amount_pesos,2) }} {{ $payment->currency }}</li>
  <li><strong>Status:</strong> {{ $payment->status }}</li>
  <li><strong>Fecha:</strong> {{ $payment->paid_at ?? $payment->created_at ?? '—' }}</li>
</ul>

<p>Atentamente,<br>PACTOPIA360</p>
