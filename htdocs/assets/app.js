/**
 * UNMOOR CLUB - SHARED CLIENT-SIDE SCRIPT
 * Handles mobile drawer, copy buttons, modal actions, and support menu.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Admin Mobile Drawer Toggle
    const mobileToggle = document.getElementById('adminMobileToggle');
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('adminBackdrop');

    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('open');
        });
    }

    if (backdrop && sidebar) {
        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('open');
            backdrop.classList.remove('open');
        });
    }

    // Copy to Clipboard buttons
    const copyButtons = document.querySelectorAll('[data-copy]');
    copyButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const textToCopy = btn.getAttribute('data-copy');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = btn.innerHTML;
                btn.innerHTML = '✅ Copied!';
                btn.style.opacity = '0.8';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.opacity = '1';
                }, 2000);
            }).catch(() => {
                // Fallback prompt
                prompt('Copy this text:', textToCopy);
            });
        });
    });

    // Auto-scroll chat to bottom if present
    const chatContainer = document.querySelector('.messages');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
});
