{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\mail\payment_receipt.blade.php --}}
<p>Hola,</p>
<p>Se registró un pago en <strong>Pactopia360</strong>.</p>

<ul>
  <li><strong>Pago #:</strong> {{ $payment->id }}</li>
  <li><strong>Cuenta:</strong> {{ $payment->account_id }}</li>
  <li><strong>Monto:</strong> ${{ number_format((float)$amount_pesos,2) }} {{ $payment->currency }}</li>
  <li><strong>Status:</strong> {{ $payment->status }}</li>
  <li><strong>Fecha:</strong> {{ $payment->paid_at ?? $payment->created_at ?? '—' }}</li>
</ul>

<p style="color:#64748b;font-size:12px">
  Si tienes dudas sobre este pago, responde a este correo o contacta a soporte.
</p>

<p>Atentamente,<br>PACTOPIA360</p>