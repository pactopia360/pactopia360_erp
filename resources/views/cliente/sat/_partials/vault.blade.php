{{-- Plantilla compacta para embebido en la página principal o standalone --}}
<div class="section">
  <h3>Bóveda Fiscal</h3>

  <div class="v-filters">
    <select class="select" id="vTipo"><option value="ambos">Ambos</option><option value="emitidos">Emitidos</option><option value="recibidos">Recibidos</option></select>
    <input type="date" class="input" id="vFrom">
    <input type="date" class="input" id="vTo">
    <input type="text" class="input" id="vQ" placeholder="RFC / Razón / UUID">
    <select class="select" id="vRfc">
      <option value="">Todos los RFC</option>
      @foreach(($credList ?? []) as $c)
        <option value="{{ strtoupper($c['rfc'] ?? $c->rfc) }}">{{ strtoupper($c['rfc'] ?? $c->rfc) }}</option>
      @endforeach
    </select>
    <button class="btn" id="vApply">Filtrar</button>
  </div>

  <div class="v-totals">
    <div class="v-pill">CFDI: <b id="tCnt">0</b></div>
    <div class="v-pill">Subtotal: <b id="tSub">$0.00</b></div>
    <div class="v-pill">IVA: <b id="tIva">$0.00</b></div>
    <div class="v-pill">Total: <b id="tTot">$0.00</b></div>
  </div>

  <div class="table-wrap" style="margin-top:10px;overflow:auto">
    <table class="table">
      <thead>
        <tr>
          <th>Fecha</th><th>Tipo</th><th>RFC</th><th>Razón</th><th>UUID</th>
          <th class="right">Subtotal</th><th class="right">IVA</th><th class="right">Total</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="vaultRows">
        <tr><td colspan="9" style="text-align:center;color:var(--mut);font-weight:700">Sin datos (integra endpoint)</td></tr>
      </tbody>
    </table>
  </div>
</div>
