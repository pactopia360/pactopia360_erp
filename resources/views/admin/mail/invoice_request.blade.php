<p>Hola,</p>
<p>Se ha registrado/actualizado una solicitud de factura.</p>

<ul>
  <li><strong>Cuenta:</strong> {{ $req->account_id }}</li>
  <li><strong>Periodo:</strong> {{ $req->period ?? '—' }}</li>
  <li><strong>Estatus:</strong> {{ $req->status ?? '—' }}</li>
</ul>

<p>Atentamente,<br>PACTOPIA360</p>
