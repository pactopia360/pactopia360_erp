{{-- Lanzador flotante --}}
<button id="novaToggle" class="nova-fab" aria-label="Abrir asistente">🤖</button>

{{-- Panel del asistente --}}
<aside id="novaPanel" class="nova-panel" aria-hidden="true">
  <header class="nova-header">
    <div class="nova-title">NovaBot · Ayuda rápida</div>
    <button id="novaClose" class="nova-close" aria-label="Cerrar">✕</button>
  </header>

  <div class="nova-body">
    <p class="nova-hint">Preguntas rápidas:</p>
    <div class="nova-quick">
      <button class="nova-btn" data-q="¿Cómo generar una nómina?">Nómina</button>
      <button class="nova-btn" data-q="¿Dónde están los empleados?">Empleados</button>
      <button class="nova-btn" data-q="¿Cómo subo archivos del checador?">Checador</button>
    </div>

    <div class="nova-input">
      <input id="novaInput" type="text" placeholder="Escribe tu pregunta…">
      <button id="novaSend" class="nova-send">Enviar</button>
    </div>

    <div id="novaLog" class="nova-log" aria-live="polite"></div>
  </div>
</aside>
