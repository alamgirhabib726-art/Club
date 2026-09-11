/**
 * UNMOOR CLUB - UNIVERSAL CUSTOM MODAL & DIALOG SYSTEM
 * Replaces native browser alert() and confirm() with modern dark-luxury UI modals.
 */

(function() {
    'use strict';

    // Inject CSS styles for the modal system if not already in document
    const styleId = 'unmoor-custom-modal-styles';
    if (!document.getElementById(styleId)) {
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            .unmoor-modal-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(3, 7, 18, 0.82);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 999999;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.22s;
                padding: 16px;
                box-sizing: border-box;
            }
            .unmoor-modal-backdrop.active {
                opacity: 1;
                visibility: visible;
            }
            .unmoor-modal-dialog {
                background: #0f172a;
                background: linear-gradient(180deg, #111827 0%, #0b0f19 100%);
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 20px;
                width: 100%;
                max-width: 380px;
                padding: 24px 20px 20px;
                box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.05);
                transform: scale(0.92) translateY(8px);
                transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
                box-sizing: border-box;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            .unmoor-modal-backdrop.active .unmoor-modal-dialog {
                transform: scale(1) translateY(0);
            }
            .unmoor-modal-icon-badge {
                width: 58px;
                height: 58px;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
                margin-bottom: 14px;
                box-sizing: border-box;
                position: relative;
            }
            .unmoor-modal-icon-badge.danger {
                background: rgba(239, 68, 68, 0.15);
                color: #ef4444;
                border: 1px solid rgba(239, 68, 68, 0.35);
                box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
            }
            .unmoor-modal-icon-badge.warning {
                background: rgba(245, 158, 11, 0.15);
                color: #f59e0b;
                border: 1px solid rgba(245, 158, 11, 0.35);
                box-shadow: 0 0 20px rgba(245, 158, 11, 0.2);
            }
            .unmoor-modal-icon-badge.primary {
                background: rgba(56, 189, 248, 0.15);
                color: #38bdf8;
                border: 1px solid rgba(56, 189, 248, 0.35);
                box-shadow: 0 0 20px rgba(56, 189, 248, 0.2);
            }
            .unmoor-modal-icon-badge.success {
                background: rgba(34, 197, 94, 0.15);
                color: #22c55e;
                border: 1px solid rgba(34, 197, 94, 0.35);
                box-shadow: 0 0 20px rgba(34, 197, 94, 0.2);
            }
            .unmoor-modal-title {
                font-size: 18px;
                font-weight: 800;
                color: #ffffff;
                margin: 0 0 8px 0;
                line-height: 1.3;
                letter-spacing: -0.2px;
            }
            .unmoor-modal-message {
                font-size: 14px;
                color: #94a3b8;
                line-height: 1.55;
                margin: 0 0 20px 0;
                word-break: break-word;
            }
            .unmoor-modal-actions {
                display: flex;
                gap: 10px;
                width: 100%;
            }
            .unmoor-modal-btn {
                flex: 1;
                padding: 12px 14px;
                border-radius: 12px;
                font-size: 14px;
                font-weight: 700;
                cursor: pointer;
                border: none;
                transition: all 0.15s ease;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                outline: none;
                box-sizing: border-box;
                min-height: 44px;
                white-space: nowrap;
            }
            .unmoor-modal-btn-cancel {
                background: rgba(255, 255, 255, 0.08);
                color: #cbd5e1;
                border: 1px solid rgba(255, 255, 255, 0.12);
            }
            .unmoor-modal-btn-cancel:hover {
                background: rgba(255, 255, 255, 0.14);
                color: #ffffff;
            }
            .unmoor-modal-btn-confirm {
                background: linear-gradient(135deg, #38bdf8, #2563eb);
                color: #ffffff;
                box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            }
            .unmoor-modal-btn-confirm:hover {
                opacity: 0.94;
                transform: translateY(-1px);
            }
            .unmoor-modal-btn-danger {
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: #ffffff;
                box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);
            }
            .unmoor-modal-btn-danger:hover {
                opacity: 0.94;
                transform: translateY(-1px);
            }
            .unmoor-modal-btn-success {
                background: linear-gradient(135deg, #22c55e, #16a34a);
                color: #ffffff;
                box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
            }
            .unmoor-modal-btn-success:hover {
                opacity: 0.94;
                transform: translateY(-1px);
            }

            /* Toast notifications */
            .unmoor-toast-container {
                position: fixed;
                bottom: 80px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 1000000;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                pointer-events: none;
                width: 90%;
                max-width: 360px;
            }
            .unmoor-toast {
                background: rgba(15, 23, 42, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.12);
                color: #ffffff;
                padding: 10px 16px;
                border-radius: 999px;
                font-size: 13.5px;
                font-weight: 700;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                opacity: 0;
                transform: translateY(12px) scale(0.95);
                transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
                display: flex;
                align-items: center;
                gap: 8px;
                pointer-events: auto;
            }
            .unmoor-toast.active {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        `;
        document.head.appendChild(style);
    }

    // Modal DOM Elements
    let backdropEl = null;
    let dialogEl = null;
    let iconBadgeEl = null;
    let titleEl = null;
    let msgEl = null;
    let actionsEl = null;
    let toastContainerEl = null;

    function ensureModalElements() {
        if (backdropEl) return;

        backdropEl = document.createElement('div');
        backdropEl.className = 'unmoor-modal-backdrop';
        backdropEl.id = 'unmoorGlobalModal';

        dialogEl = document.createElement('div');
        dialogEl.className = 'unmoor-modal-dialog';

        iconBadgeEl = document.createElement('div');
        iconBadgeEl.className = 'unmoor-modal-icon-badge primary';
        iconBadgeEl.innerHTML = '✨';

        titleEl = document.createElement('h3');
        titleEl.className = 'unmoor-modal-title';
        titleEl.textContent = 'Confirmation';

        msgEl = document.createElement('div');
        msgEl.className = 'unmoor-modal-message';
        msgEl.textContent = 'Are you sure you want to proceed?';

        actionsEl = document.createElement('div');
        actionsEl.className = 'unmoor-modal-actions';

        dialogEl.appendChild(iconBadgeEl);
        dialogEl.appendChild(titleEl);
        dialogEl.appendChild(msgEl);
        dialogEl.appendChild(actionsEl);
        backdropEl.appendChild(dialogEl);

        document.body.appendChild(backdropEl);

        // Close on backdrop click if user clicks outside
        backdropEl.addEventListener('click', (e) => {
            if (e.target === backdropEl && backdropEl.dataset.allowBackdropClose === 'true') {
                closeModal();
            }
        });
    }

    function closeModal() {
        if (backdropEl) {
            backdropEl.classList.remove('active');
        }
    }

    /**
     * Show custom confirmation dialog with Promise & callbacks
     */
    window.showAppConfirm = function(options) {
        return new Promise((resolve) => {
            ensureModalElements();

            const title = options.title || 'Confirm Action';
            const message = options.message || 'Are you sure you want to continue?';
            const icon = options.icon || (options.danger ? '⚠️' : '❓');
            const confirmText = options.confirmText || 'Confirm';
            const cancelText = options.cancelText || 'Cancel';
            const type = options.type || (options.danger ? 'danger' : 'primary');

            titleEl.textContent = title;
            msgEl.innerHTML = message.replace(/\n/g, '<br>');
            iconBadgeEl.innerHTML = icon;
            iconBadgeEl.className = 'unmoor-modal-icon-badge ' + type;

            backdropEl.dataset.allowBackdropClose = options.allowCancel !== false ? 'true' : 'false';

            actionsEl.innerHTML = '';

            if (options.allowCancel !== false) {
                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'unmoor-modal-btn unmoor-modal-btn-cancel';
                cancelBtn.textContent = cancelText;
                cancelBtn.onclick = function() {
                    closeModal();
                    if (typeof options.onCancel === 'function') options.onCancel();
                    resolve(false);
                };
                actionsEl.appendChild(cancelBtn);
            }

            const confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'unmoor-modal-btn ' + (type === 'danger' ? 'unmoor-modal-btn-danger' : (type === 'success' ? 'unmoor-modal-btn-success' : 'unmoor-modal-btn-confirm'));
            confirmBtn.textContent = confirmText;
            confirmBtn.onclick = function() {
                closeModal();
                if (typeof options.onConfirm === 'function') options.onConfirm();
                resolve(true);
            };
            actionsEl.appendChild(confirmBtn);

            backdropEl.classList.add('active');
            confirmBtn.focus();
        });
    };

    /**
     * Show custom alert dialog
     */
    window.showAppAlert = function(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }
        return new Promise((resolve) => {
            ensureModalElements();

            const title = options.title || 'Notice';
            const message = options.message || '';
            const icon = options.icon || (options.type === 'danger' ? '❌' : (options.type === 'success' ? '✅' : 'ℹ️'));
            const buttonText = options.buttonText || 'Understood';
            const type = options.type || 'primary';

            titleEl.textContent = title;
            msgEl.innerHTML = message.replace(/\n/g, '<br>');
            iconBadgeEl.innerHTML = icon;
            iconBadgeEl.className = 'unmoor-modal-icon-badge ' + type;

            backdropEl.dataset.allowBackdropClose = 'true';

            actionsEl.innerHTML = '';
            const okBtn = document.createElement('button');
            okBtn.type = 'button';
            okBtn.className = 'unmoor-modal-btn ' + (type === 'danger' ? 'unmoor-modal-btn-danger' : 'unmoor-modal-btn-confirm');
            okBtn.textContent = buttonText;
            okBtn.onclick = function() {
                closeModal();
                if (typeof options.onOk === 'function') options.onOk();
                resolve(true);
            };
            actionsEl.appendChild(okBtn);

            backdropEl.classList.add('active');
            okBtn.focus();
        });
    };

    /**
     * Show animated toast notification
     */
    window.showAppToast = function(message, type = 'info', duration = 2500) {
        if (!toastContainerEl) {
            toastContainerEl = document.createElement('div');
            toastContainerEl.className = 'unmoor-toast-container';
            document.body.appendChild(toastContainerEl);
        }

        const toast = document.createElement('div');
        toast.className = 'unmoor-toast';
        const icon = type === 'success' ? '✅' : (type === 'danger' ? '❌' : (type === 'copy' ? '📋' : 'ℹ️'));
        toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;
        toastContainerEl.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('active');
        });

        setTimeout(() => {
            toast.classList.remove('active');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);
    };

    // Auto-bind to elements with data-confirm or data-confirm-href or data-confirm-form
    document.addEventListener('DOMContentLoaded', () => {
        // Intercept links with data-confirm
        document.querySelectorAll('a[data-confirm]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const msg = this.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
                const title = this.getAttribute('data-confirm-title') || 'Confirm Action';
                const isDanger = this.getAttribute('data-confirm-danger') === 'true' || this.classList.contains('btn-danger') || this.classList.contains('admin-btn-danger');
                const href = this.getAttribute('href');

                window.showAppConfirm({
                    title: title,
                    message: msg,
                    danger: isDanger,
                    confirmText: this.getAttribute('data-confirm-btn') || 'Proceed',
                    onConfirm: () => {
                        if (href && href !== '#') {
                            window.location.href = href;
                        }
                    }
                });
            });
        });

        // Intercept forms with data-confirm
        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.dataset.confirmed === 'true') {
                    return true;
                }
                e.preventDefault();
                const msg = this.getAttribute('data-confirm') || 'Are you sure you want to submit?';
                const title = this.getAttribute('data-confirm-title') || 'Confirm Submission';
                const isDanger = this.getAttribute('data-confirm-danger') === 'true';

                window.showAppConfirm({
                    title: title,
                    message: msg,
                    danger: isDanger,
                    confirmText: this.getAttribute('data-confirm-btn') || 'Submit',
                    onConfirm: () => {
                        this.dataset.confirmed = 'true';
                        this.submit();
                    }
                });
            });
        });
    });

    // Override browser native alert to present beautiful modal
    window.alert = function(msg) {
        window.showAppAlert({
            title: 'Notice',
            message: String(msg),
            icon: 'ℹ️',
            buttonText: 'OK'
        });
    };

})();
