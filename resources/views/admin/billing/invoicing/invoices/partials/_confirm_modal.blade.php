<div class="invx-modal" id="confirmActionModal" aria-hidden="true">
    <div class="invx-modal__backdrop" data-invx-modal-close></div>

    <div class="invx-modal__dialog invx-modal__dialog--sm">
        <div class="invx-modal__head">
            <div>
                <h3 class="invx-modal__title" id="invxConfirmTitle">Confirmar acción</h3>
                <p class="invx-modal__sub">Valida la operación antes de continuar.</p>
            </div>
            <button type="button" class="invx-iconbtn" data-invx-modal-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="invx-modal__body">
            <p class="invx-confirm-text" id="invxConfirmMessage">¿Deseas continuar?</p>

            <form method="POST" action="#" id="invxConfirmForm">
                @csrf
                <div class="invx-form-actions">
                    <button type="button" class="invx-btn invx-btn--soft" data-invx-modal-close>Cerrar</button>
                    <button type="submit" class="invx-btn invx-btn--danger" id="invxConfirmSubmit">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>