/* C:\wamp64\www\pactopia360_erp\public\assets\admin\js\sat-ops-quotes.js */
(function () {
    'use strict';

    const APP = window.SATQ_ADMIN_QUOTES || {};

    const qs = (selector, scope = document) => scope.querySelector(selector);
    const qsa = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

    const body = document.body;

    const openModal = (id) => {
        if (!id) {
            return;
        }

        const modal = document.getElementById(id);
        if (!modal) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        body.style.overflow = 'hidden';
    };

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (!qs('.satq-modal.is-open')) {
            body.style.overflow = '';
        }
    };

    const closeAllModals = () => {
        qsa('.satq-modal.is-open').forEach(closeModal);
    };

    const bindOpeners = () => {
        qsa('[data-modal-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-modal-target') || '';
                openModal(target);
            });
        });
    };

    const bindClosers = () => {
        qsa('.satq-modal').forEach((modal) => {
            qsa('[data-modal-close]', modal).forEach((closer) => {
                closer.addEventListener('click', () => {
                    closeModal(modal);
                });
            });

            modal.addEventListener('click', (event) => {
                if (event.target === modal || event.target.classList.contains('satq-modal-backdrop')) {
                    closeModal(modal);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllModals();
            }
        });
    };

    const bindAutoSubmitGuards = () => {
        qsa('.satq-quick-status form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const targetInput = qs('input[name="status_ui"]', form);
                const targetStatus = String(targetInput?.value || '').trim();

                if (!targetStatus) {
                    return;
                }

                const friendlyMap = {
                    en_proceso: 'En proceso',
                    pagada: 'Pagada',
                    en_descarga: 'En descarga',
                    borrador: 'Borrador',
                    cotizada: 'Cotizada',
                    completada: 'Completada',
                    cancelada: 'Cancelada',
                };

                const friendly = friendlyMap[targetStatus] || targetStatus;
                const ok = window.confirm(`¿Deseas cambiar esta cotización a "${friendly}"?`);

                if (!ok) {
                    event.preventDefault();
                }
            });
        });
    };

        const bindDangerForms = () => {
        qsa('form[action*="transfer/approve"]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const moveToDownload = qs('select[name="move_to_download"]', form)?.value === '1';
                const message = moveToDownload
                    ? '¿Deseas aprobar esta transferencia y mover la cotización a "En descarga"?'
                    : '¿Deseas aprobar esta transferencia y dejar la cotización como "Pagada"?';

                const ok = window.confirm(message);
                if (!ok) {
                    event.preventDefault();
                }
            });
        });

        qsa('form[action*="transfer/reject"]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const reason = String(qs('textarea[name="reject_reason"]', form)?.value || '').trim();

                if (!reason) {
                    window.alert('Debes capturar el motivo de rechazo de la transferencia.');
                    event.preventDefault();
                    return;
                }

                const ok = window.confirm('¿Deseas rechazar esta transferencia y devolver la cotización a estado "Cotizada"?');
                if (!ok) {
                    event.preventDefault();
                }
            });
        });

        qsa('form[action*="/reject"]').forEach((form) => {
            if (form.action.includes('transfer/reject')) {
                return;
            }

            form.addEventListener('submit', (event) => {
                const ok = window.confirm('¿Deseas rechazar esta cotización y notificar al cliente?');
                if (!ok) {
                    event.preventDefault();
                }
            });
        });

        qsa('form[action*="/confirm"]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const ok = window.confirm('¿Deseas confirmar esta cotización y dejarla lista para pago?');
                if (!ok) {
                    event.preventDefault();
                }
            });
        });

        qsa('form[action*="sat_request"]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const ok = window.confirm('¿Deseas marcar esta cotización como base preparada para solicitud SAT?');
                if (!ok) {
                    event.preventDefault();
                }
            });
        });
    };

    const bindNumericNormalizers = () => {
        qsa('input[type="number"]').forEach((input) => {
            input.addEventListener('blur', () => {
                const raw = String(input.value || '').trim();
                if (raw === '') {
                    return;
                }

                const parsed = Number(raw);
                if (!Number.isFinite(parsed)) {
                    return;
                }

                if (input.step === '0.01' || input.name === 'subtotal' || input.name === 'iva' || input.name === 'total') {
                    input.value = parsed.toFixed(2);
                    return;
                }

                if (input.name === 'xml_count') {
                    input.value = String(Math.max(0, Math.round(parsed)));
                }
            });
        });
    };

    const bindModalFocus = () => {
        qsa('[data-modal-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-modal-target') || '';
                const modal = document.getElementById(target);

                if (!modal) {
                    return;
                }

                window.setTimeout(() => {
                    const firstFocusable = qs('input, textarea, select, button', modal);
                    if (firstFocusable) {
                        firstFocusable.focus();
                    }
                }, 30);
            });
        });
    };

    const boot = () => {
        bindOpeners();
        bindClosers();
        bindAutoSubmitGuards();
        bindDangerForms();
        bindNumericNormalizers();
        bindModalFocus();

        if (APP && typeof APP === 'object') {
            window.SATQ_ADMIN_QUOTES = APP;
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();