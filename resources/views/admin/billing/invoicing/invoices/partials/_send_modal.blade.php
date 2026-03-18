<div class="invx-modal" id="sendInvoiceModal" aria-hidden="true">
    <div class="invx-modal__backdrop" data-invx-modal-close></div>

    <div class="invx-modal__dialog invx-modal__dialog--sm">
        <div class="invx-modal__head">
            <div>
                <h3 class="invx-modal__title" id="invxSendModalTitle">Enviar factura</h3>
                <p class="invx-modal__sub">Uno o varios correos separados por coma.</p>
            </div>
            <button type="button" class="invx-iconbtn" data-invx-modal-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="invx-modal__body">
            <form method="POST" action="#" id="invxSendInvoiceForm" class="invx-form-grid invx-form-grid--compact">
                @csrf

                <div class="invx-field invx-field--span-12">
                    <div class="invx-floating">
                        <input id="invxSendTo" type="text" name="to" class="invx-input" value="" placeholder=" ">
                        <label for="invxSendTo">Correos destino</label>
                    </div>
                </div>

                <div class="invx-field invx-field--span-12">
                    <div class="invx-help">
                        Si lo dejas vacío, el backend intentará resolver destinatarios desde la cuenta o historial.
                    </div>
                </div>

                <div class="invx-field invx-field--span-12">
                    <div class="invx-form-actions">
                        <button type="button" class="invx-btn invx-btn--soft" data-invx-modal-close>Cerrar</button>
                        <button type="submit" class="invx-btn invx-btn--primary" id="invxSendInvoiceSubmit">Confirmar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>