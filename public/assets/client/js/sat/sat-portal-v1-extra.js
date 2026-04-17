/* C:\wamp64\www\pactopia360_erp\public\assets\client\js\sat\sat-portal-v1-extra.js */
(function () {
    'use strict';

    const APP = window.P360_SAT || {};

    const qs = (selector, scope = document) => scope.querySelector(selector);
    const qsa = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

    function openModal(modal) {
        if (!modal) return;

        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('sat-clean-modal-open');
        document.body.classList.add('sat-clean-modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;

        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');

        if (!qs('.sat-clean-modal.is-visible')) {
            document.documentElement.classList.remove('sat-clean-modal-open');
            document.body.classList.remove('sat-clean-modal-open');
        }
    }

    function closeAllModals() {
        qsa('.sat-clean-modal.is-visible').forEach(closeModal);
    }

    function bindGlobalModalClosing() {
        qsa('[data-rfc-close-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(button.closest('.sat-clean-modal'));
            });
        });

        qsa('[data-rfc-detail-close]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(qs('#satRfcDetailModal'));
            });
        });

        qsa('[data-quote-detail-close]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(qs('#satQuoteDetailModal'));
            });
        });

        qsa('[data-quote-edit-close]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(qs('#satQuoteEditModal'));
            });
        });

        qsa('[data-quote-payment-close]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(qs('#satQuotePaymentModal'));
            });
        });

        qsa('.sat-clean-modal__backdrop').forEach((backdrop) => {
            backdrop.addEventListener('click', () => {
                closeModal(backdrop.closest('.sat-clean-modal'));
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllModals();
            }
        });
    }

    function bindLinkedInviteModals() {
        const createModal = qs('#satRfcCreateModal');
        const inviteSingleModal = qs('#satInviteSingleModal');
        const inviteZipModal = qs('#satInviteZipModal');

        qsa('[data-open-linked-modal="invite-single"]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(createModal);
                openModal(inviteSingleModal);
            });
        });

        qsa('[data-open-linked-modal="invite-zip"]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(createModal);
                openModal(inviteZipModal);
            });
        });

        qsa('[data-back-to-create-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(button.closest('.sat-clean-modal'));
                openModal(createModal);
            });
        });
    }

    function bindVisualHotfixes() {
        qsa('.sat-clean-btn--icon-only').forEach((button) => {
            button.addEventListener('mouseenter', () => {
                button.classList.add('is-hover');
            });

            button.addEventListener('mouseleave', () => {
                button.classList.remove('is-hover');
            });
        });
    }

    function applyInitialAccordionState() {
        qsa('.sat-clean-accordion__item').forEach((item) => {
            item.removeAttribute('open');
        });
    }

    function syncDistributionButtons() {
        const desktopButtons = [
            qs('#satDesktopDownloadBtn'),
            qs('#satDesktopTableDownloadBtn'),
        ].filter(Boolean);

        const mobileButtons = [
            qs('#satMobileComingSoonBtn'),
            qs('#satMobileNotifyBtn'),
            qs('#satMobilePreviewBtn'),
            qs('#satMobileAlertsBtn'),
        ].filter(Boolean);

        const desktopUrl = String(
            APP.desktopDownloadUrl ||
            APP.desktop_download_url ||
            document.body?.dataset?.satDesktopDownloadUrl ||
            ''
        ).trim();

        const playStoreUrl = String(
            APP.mobilePlayStoreUrl ||
            APP.mobile_playstore_url ||
            APP.playStoreUrl ||
            APP.play_store_url ||
            document.body?.dataset?.satMobilePlayStoreUrl ||
            document.body?.dataset?.satPlayStoreUrl ||
            ''
        ).trim();

        desktopButtons.forEach((button) => {
            if (button.tagName === 'A' && desktopUrl !== '') {
                button.setAttribute('href', desktopUrl);
            }
        });

        if (playStoreUrl !== '') {
            const mobilePrimaryBtn = qs('#satMobileComingSoonBtn');
            const mobileAlertsBtn = qs('#satMobileAlertsBtn');

            if (mobilePrimaryBtn) {
                mobilePrimaryBtn.textContent = 'Ver en Play Store';
                if (mobilePrimaryBtn.tagName === 'A') {
                    mobilePrimaryBtn.setAttribute('href', playStoreUrl);
                }
            }

            if (mobileAlertsBtn) {
                mobileAlertsBtn.textContent = 'Abrir Play Store';
                if (mobileAlertsBtn.tagName === 'A') {
                    mobileAlertsBtn.setAttribute('href', playStoreUrl);
                }
            }

            mobileButtons.forEach((button) => {
                if (button.tagName === 'A') {
                    button.setAttribute('href', playStoreUrl);
                }
            });
        }
    }

    function boot() {
        applyInitialAccordionState();
        bindGlobalModalClosing();
        bindLinkedInviteModals();
        bindVisualHotfixes();
        syncDistributionButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();