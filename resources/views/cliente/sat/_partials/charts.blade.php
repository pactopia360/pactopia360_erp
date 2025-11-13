{{-- Tabs + filtros + 2 charts (Chart.js) --}}
<div class="section">
  <h3>Gráficas</h3>

  <div class="tabs">
    <button class="tab is-active" data-scope="emitidos">Emitidos</button>
    <button class="tab" data-scope="recibidos">Recibidos</button>
    <button class="tab" data-scope="ambos">Ambos</button>
  </div>

  <div class="filters">
    <select id="chTipo" class="select">
      <option value="total">Importe total</option>
      <option value="cuentas"># CFDI</option>
    </select>
    <input id="chFrom" type="month" class="input">
    <input id="chTo"   type="month" class="input">
    <button class="btn" id="chApply">Aplicar</button>
  </div>

  <div class="chart-wrap">
    <div class="canvas-card"><canvas id="chartA" height="160"></canvas></div>
    <div class="canvas-card"><canvas id="chartB" height="160"></canvas></div>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const make = (id,lbl)=>new Chart(document.getElementById(id),{
    type:'line',
    data:{labels:[],datasets:[{label:lbl,data:[],borderWidth:2}]},
    options:{responsive:true,maintainAspectRatio:false}
  });
  const A=make('chartA','Serie A'), B=make('chartB','Serie B');
  let scope='emitidos';

  document.querySelectorAll('.tabs .tab').forEach(t=>{
    t.addEventListener('click',()=>{
      document.querySelectorAll('.tabs .tab').forEach(x=>x.classList.remove('is-active'));
      t.classList.add('is-active'); scope=t.dataset.scope;
    });
  });

  document.getElementById('chApply').addEventListener('click', ()=>{
    // mock: reemplaza con fetch a tu endpoint de analítica
    const labs = Array.from({length:6},(_,i)=>`M-${i+1}`);
    const rnd  = () => labs.map(()=> Math.round(Math.random()*1000));
    A.data.labels=labs; B.data.labels=labs;
    A.data.datasets[0].data=rnd(); B.data.datasets[0].data=rnd();
    A.update(); B.update();
  });
})();
</script>
@endpush
