/**
 * CPMMIS Shared JavaScript Utilities
 * Eliminates duplication across 20+ page files
 */

const CPMMIS = {
    getRelativeTime(timestamp) {
        if (!timestamp) return "";
        const time = typeof timestamp === 'number' ? timestamp : Math.floor(new Date(timestamp).getTime() / 1000);
        const diff = Math.floor(Date.now() / 1000) - time;
        if (diff < 1) return 'now';
        const intervals = [
            [31536000, ' year'], [2592000, ' month'], [604800, ' week'],
            [86400, ' day'], [3600, ' hour'], [60, ' minute']
        ];
        for (const [secs, str] of intervals) {
            const d = Math.floor(diff / secs);
            if (d >= 1) return d + str + (d > 1 ? 's' : '');
        }
        return diff + ' second' + (diff > 1 ? 's' : '');
    },

    updateLiveTimes() {
        document.querySelectorAll('.live-time').forEach(el => {
            const ts = parseInt(el.dataset.timestamp);
            if (!ts) return;
            const diff = Math.floor((Date.now() / 1000) - ts);
            let text;
            if (diff < 1) text = 'now';
            else if (diff < 60) text = diff + ' second' + (diff > 1 ? 's' : '');
            else if (diff < 3600) text = Math.floor(diff / 60) + ' minute' + (Math.floor(diff / 60) > 1 ? 's' : '');
            else if (diff < 86400) text = Math.floor(diff / 3600) + ' hour' + (Math.floor(diff / 3600) > 1 ? 's' : '');
            else if (diff < 2592000) text = Math.floor(diff / 86400) + ' day' + (Math.floor(diff / 86400) > 1 ? 's' : '');
            else if (diff < 31536000) text = Math.floor(diff / 2592000) + ' month' + (Math.floor(diff / 2592000) > 1 ? 's' : '');
            else text = Math.floor(diff / 31536000) + ' year' + (Math.floor(diff / 31536000) > 1 ? 's' : '');
            el.textContent = text + (el.closest('[data-suffix]') ? '' : ' ago');
        });
    },

    showToast(msg) {
        const toast = document.getElementById('toast-container');
        const msgEl = document.getElementById('toast-message');
        if (!toast || !msgEl) return;
        msgEl.innerText = msg;
        toast.classList.remove('-translate-y-20', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
        setTimeout(() => {
            toast.classList.add('-translate-y-20', 'opacity-0');
            toast.classList.remove('translate-y-0', 'opacity-100');
        }, 3000);
    },

    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (sidebar) sidebar.classList.toggle('open');
        if (backdrop) backdrop.classList.toggle('open');
    },

    toggleSettingsDropdown() {
        const notif = document.getElementById('notif-popover');
        const settings = document.getElementById('settings-popover');
        if (notif) notif.classList.add('hidden');
        if (settings) settings.classList.toggle('hidden');
    },

    openSettingsDrawer() {
        const settings = document.getElementById('settings-popover');
        const overlay = document.getElementById('drawer-overlay');
        const drawer = document.getElementById('settings-drawer');
        if (settings) settings.classList.add('hidden');
        if (overlay) overlay.classList.remove('hidden');
        if (drawer) {
            drawer.classList.add('open');
            drawer.classList.remove('translate-x-full');
        }
    },

    closeSettingsDrawer() {
        const overlay = document.getElementById('drawer-overlay');
        const drawer = document.getElementById('settings-drawer');
        if (overlay) overlay.classList.add('hidden');
        if (drawer) {
            drawer.classList.add('translate-x-full');
            drawer.classList.remove('open');
        }
    },

    toggleSecurityMenu() {
        const sub = document.getElementById('security-submenu');
        const chev = document.getElementById('security-chevron');
        if (!sub || !chev) return;
        if (sub.style.maxHeight === "0px" || sub.style.maxHeight === "") {
            sub.style.maxHeight = "400px";
            chev.classList.add('rotate-90');
        } else {
            sub.style.maxHeight = "0px";
            chev.classList.remove('rotate-90');
        }
    },

    animateIn(modalId, contentId) {
        const m = document.getElementById(modalId);
        const c = document.getElementById(contentId);
        if (!m || !c) return;
        m.classList.remove('hidden');
        m.classList.add('flex');
        setTimeout(() => {
            c.classList.remove('scale-95', 'opacity-0');
            c.classList.add('scale-100', 'opacity-100');
        }, 10);
    },

    togglePass(id, btn) {
        const input = document.getElementById(id);
        const icon = btn.querySelector('i');
        if (!input || !icon) return;
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = "password";
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    },

    highlightRow() {
        const urlParams = new URLSearchParams(window.location.search);
        const highlightId = urlParams.get('highlight');
        if (highlightId) {
            const el = document.getElementById('row-' + highlightId);
            if (el) {
                setTimeout(() => {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('global-highlight-row');
                }, 100);
            }
        }
    },

    closeAllPopovers(e) {
        if (!e.target.closest('.nav-icon-container') && !e.target.closest('#settings-drawer') && !e.target.closest('#notif-popover')) {
            const settings = document.getElementById('settings-popover');
            const notif = document.getElementById('notif-popover');
            if (settings) settings.classList.add('hidden');
            if (notif) notif.classList.add('hidden');
        }
    }
};

// Initialize shared behaviors on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    CPMMIS.updateLiveTimes();
    setInterval(CPMMIS.updateLiveTimes, 30000);
    CPMMIS.highlightRow();
    window.onclick = CPMMIS.closeAllPopovers;
});
