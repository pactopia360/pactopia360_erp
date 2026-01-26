{{-- resources/views/cliente/sat/_partials/rfcs/_invite_modal.blade.php --}}

<div class="sat-modal-backdrop" id="modalExternalRfcInvite" style="display:none;">
  <div class="sat-modal sat-modal-lg">
    <div class="sat-modal-header">
      <div>
        <div class="sat-modal-kicker">Mis RFCs · Registro externo</div>
        <div class="sat-modal-title">Invitar a un emisor externo</div>
        <p class="sat-modal-sub">
          Se enviará una liga segura al correo indicado para que el emisor registre su RFC y cargue su e.firma (FIEL) sin requerir cuenta.
        </p>
      </div>
      <button type="button" class="sat-modal-close" data-close="modal-external-rfc" aria-label="Cerrar">✕</button>
    </div>

    <div class="sat-modal-body">
      <div class="sat-step-card">
        <div class="sat-step-kicker">
          <span>Datos del emisor</span>
          <small>Correo de acceso</small>
        </div>

        <div class="sat-step-grid sat-step-grid-2col">
          <div class="sat-field sat-field-full">
            <div class="sat-field-label">Correo</div>
            <input class="input sat-input-pill"
                   type="email"
                   id="externalInviteEmail"
                   placeholder="correo@empresa.com"
                   autocomplete="email">
            <div class="sat-modal-note" style="margin-top:6px;">
              Recomendación: usa un correo institucional del emisor.
            </div>
          </div>

          <div class="sat-field sat-field-full">
            <div class="sat-field-label">Nota (opcional)</div>
            <input class="input sat-input-pill"
                   type="text"
                   id="externalInviteNote"
                   maxlength="140"
                   placeholder="Ej. Registro para CFDI 2025 / Operación X">
          </div>
        </div>
      </div>

      <div class="sat-step-card" style="margin-top:14px;">
        <div class="sat-step-kicker">
          <span>Resultado</span>
          <small>Confirmación</small>
        </div>

        <div class="sat-modal-note">
          <div id="externalInviteResult" class="text-muted">—</div>
        </div>
      </div>
    </div>

    <div class="sat-modal-footer">
      <button type="button" class="btn" data-close="modal-external-rfc">Cancelar</button>
      <button type="button" class="btn primary" id="btnExternalInviteSend">Enviar liga</button>
    </div>
  </div>
</div>
