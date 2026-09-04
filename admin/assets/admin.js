/* ============================================================================
   Furusato Restaurant Administration — Core Framework
   admin/assets/admin.js

   Shared client foundation for the dashboard: API client with CSRF handling,
   safe DOM helpers, toasts, accessible modals/confirm dialogs, the session
   inactivity watchdog and the section router. Feature logic lives in
   admin/dashboard.php and consumes this file through window.FurusatoAdmin.
   ========================================================================== */

(function () {
    'use strict';

    var App = {};
    window.FurusatoAdmin = App;

    /* --- Safe DOM helpers -------------------------------------------------- */

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function safeString(value, fallback) {
        if (value === null || value === undefined) return fallback || '';
        return String(value);
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function qs(selector, root) { return (root || document).querySelector(selector); }
    function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, wait || 250);
        };
    }

    App.escapeHtml = escapeHtml;
    App.safeString = safeString;
    App.el = el;
    App.qs = qs;
    App.qsa = qsa;
    App.debounce = debounce;
    /* --- Formatting --------------------------------------------------------- */

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function fmtMoney(value) {
        var n = Number(value);
        if (!isFinite(n)) return safeString(value);
        return 'Ksh ' + n.toLocaleString('en-KE');
    }

    function fmtDate(iso) {
        if (!iso) return '\u2014';
        var parts = safeString(iso).split('-');
        if (parts.length !== 3) return safeString(iso);
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(d.getTime())) return safeString(iso);
        return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
    }

    function fmtTime(hhmm) {
        var parts = safeString(hhmm).split(':');
        if (parts.length < 2) return safeString(hhmm) || '\u2014';
        var h = parseInt(parts[0], 10), m = parseInt(parts[1], 10);
        if (isNaN(h) || isNaN(m)) return safeString(hhmm);
        var suffix = h >= 12 ? 'PM' : 'AM';
        var hour12 = h % 12 === 0 ? 12 : h % 12;
        return hour12 + ':' + (m < 10 ? '0' + m : m) + ' ' + suffix;
    }

    function fmtDateTimeIso(iso) {
        var d = new Date(safeString(iso));
        if (isNaN(d.getTime())) return safeString(iso);
        var h = d.getHours(), m = d.getMinutes();
        var suffix = h >= 12 ? ' PM' : ' AM';
        return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() +
            ', ' + (h % 12 === 0 ? 12 : h % 12) + ':' + (m < 10 ? '0' + m : m) + suffix;
    }

    function timeAgo(iso) {
        var then = new Date(safeString(iso)).getTime();
        if (isNaN(then)) return '';
        var secs = Math.max(0, Math.floor((Date.now() - then) / 1000));
        if (secs < 60) return 'just now';
        if (secs < 3600) return Math.floor(secs / 60) + ' min ago';
        if (secs < 86400) return Math.floor(secs / 3600) + ' h ago';
        return Math.floor(secs / 86400) + ' d ago';
    }

    App.fmtMoney = fmtMoney;
    App.fmtDate = fmtDate;
    App.fmtTime = fmtTime;
    App.fmtDateTimeIso = fmtDateTimeIso;
    App.timeAgo = timeAgo;

    /* --- API client ------------------------------------------------------------ */

    function csrfToken() {
        var meta = qs('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    App.csrfToken = csrfToken;

    function parseResponse(response) {
        return response.text().then(function (text) {
            var data = null;
            if (text) {
                try { data = JSON.parse(text); } catch (e) { data = null; }
            }
            if (!response.ok) {
                var message = (data && (data.error || data.message)) || ('Request failed (HTTP ' + response.status + ')');
                var error = new Error(message);
                error.status = response.status;
                error.data = data;
                throw error;
            }
            if (!data || typeof data !== 'object') {
                throw new Error('Unexpected server response.');
            }
            return data;
        });
    }

    function apiJson(url, payload, method) {
        return fetch(url, {
            method: method || 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload || {})
        }).then(parseResponse);
    }

    function apiGet(url) {
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(parseResponse);
    }

    function apiForm(url, formData) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        }).then(parseResponse);
    }

    App.apiJson = apiJson;
    App.apiGet = apiGet;
    App.apiForm = apiForm;
    App.parseResponse = parseResponse;
    /* --- Toasts ------------------------------------------------------------------ */

    var toastStack = null;

    function toast(message, type) {
        if (!toastStack) {
            toastStack = el('div', 'toast-stack');
            toastStack.setAttribute('role', 'status');
            toastStack.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastStack);
        }

        var kind = type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success');
        var iconName = kind === 'error' ? 'fa-circle-exclamation' : (kind === 'info' ? 'fa-circle-info' : 'fa-circle-check');
        var icon = el('i', 'fa-solid fa-fw ' + iconName);
        icon.setAttribute('aria-hidden', 'true');

        var node = el('div', 'toast ' + kind);
        node.appendChild(icon);
        node.appendChild(el('span', null, message));
        toastStack.appendChild(node);

        setTimeout(function () {
            node.classList.add('leaving');
            setTimeout(function () { node.remove(); }, 300);
        }, 3600);
    }

    App.toast = toast;

    /* --- Buttons: double-submit protection ------------------------------------------ */

    function busyButton(btn, busy) {
        if (!btn) return;
        if (busy) {
            btn.setAttribute('aria-busy', 'true');
            btn.disabled = true;
        } else {
            btn.removeAttribute('aria-busy');
            btn.disabled = false;
        }
    }

    App.busyButton = busyButton;

    /* --- Accessible modals --------------------------------------------------------------- */

    var openModals = [];
    var lastFocused = null;

    function focusables(rootNode) {
        return qsa('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', rootNode)
            .filter(function (n) { return n.offsetParent !== null || n === document.activeElement; });
    }

    function onKeyDown(event) {
        if (event.key === 'Escape' && openModals.length) {
            closeModal(openModals[openModals.length - 1]);
            return;
        }
        if (event.key !== 'Tab' || !openModals.length) return;

        var topModal = openModals[openModals.length - 1];
        var nodes = focusables(topModal);
        if (!nodes.length) return;

        var first = nodes[0];
        var last = nodes[nodes.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function openModal(id) {
        var overlay = document.getElementById(id);
        if (!overlay) return;

        if (!openModals.length) {
            lastFocused = document.activeElement;
            document.addEventListener('keydown', onKeyDown, true);
        }

        overlay.classList.add('active');
        openModals.push(overlay);

        var nodes = focusables(overlay);
        if (nodes.length) nodes[0].focus();
    }

    function closeModal(id) {
        var overlay = typeof id === 'string' ? document.getElementById(id) : id;
        if (!overlay) return;

        overlay.classList.remove('active');
        openModals = openModals.filter(function (m) { return m !== overlay; });

        if (!openModals.length) {
            document.removeEventListener('keydown', onKeyDown, true);
            if (lastFocused && lastFocused.focus) lastFocused.focus();
        }
    }

    App.openModal = openModal;
    App.closeModal = closeModal;

    function initModals() {
        qsa('.modal-overlay').forEach(function (overlay) {
            overlay.addEventListener('mousedown', function (event) {
                if (event.target === overlay) closeModal(overlay);
            });
            qsa('[data-close-modal]', overlay).forEach(function (btn) {
                btn.addEventListener('click', function () { closeModal(overlay); });
            });
        });
    }

    /* --- Confirm dialog ---------------------------------------------------------------- */

    var confirmState = { resolve: null };

    function confirmDialog(options) {
        var opts = options || {};
        var overlay = document.getElementById('confirm-modal');
        if (!overlay) {
            return Promise.resolve(window.confirm(opts.message || 'Are you sure?'));
        }

        qs('#confirm-title').textContent = opts.title || 'Please confirm';
        qs('#confirm-message').textContent = opts.message || 'Are you sure?';

        var okBtn = qs('#confirm-ok');
        okBtn.textContent = opts.confirmLabel || 'Confirm';
        okBtn.className = 'btn ' + (opts.danger ? 'btn-danger' : 'btn-primary');

        return new Promise(function (resolve) {
            confirmState.resolve = resolve;
            openModal('confirm-modal');
        });
    }

    function initConfirmDialog() {
        var overlay = document.getElementById('confirm-modal');
        if (!overlay) return;

        function settle(result) {
            if (confirmState.resolve) {
                confirmState.resolve(result);
                confirmState.resolve = null;
            }
            closeModal('confirm-modal');
        }

        qs('#confirm-ok').addEventListener('click', function () { settle(true); });
        qs('#confirm-cancel').addEventListener('click', function () { settle(false); });
    }

    App.confirmDialog = confirmDialog;
    /* --- Session inactivity watchdog ----------------------------------------------- */

    var sessionTimer = null;
    var warningTimer = null;

    function hideSessionWarning() {
        var box = document.getElementById('session-warning');
        if (box) box.classList.remove('active');
    }

    function showSessionWarning() {
        var box = document.getElementById('session-warning');
        if (!box) return;
        box.classList.add('active');

        var timeLeft = 300;
        var label = document.getElementById('session-timer');
        if (label) label.textContent = '5:00';

        var countdown = setInterval(function () {
            timeLeft -= 1;
            if (timeLeft < 0 || !box.classList.contains('active')) {
                clearInterval(countdown);
                return;
            }
            if (label) {
                var m = Math.floor(timeLeft / 60);
                var s = timeLeft % 60;
                label.textContent = m + ':' + (s < 10 ? '0' + s : s);
            }
        }, 1000);
    }

    function resetSessionTimer() {
        clearTimeout(sessionTimer);
        clearTimeout(warningTimer);
        hideSessionWarning();

        var lifetime = 30 * 60 * 1000;
        var warningAt = lifetime - 5 * 60 * 1000;

        warningTimer = setTimeout(showSessionWarning, warningAt);
        sessionTimer = setTimeout(function () {
            window.location.href = '/admin/login.php?timeout=1';
        }, lifetime);
    }

    function extendSession() {
        apiGet('/api/auth.php?action=check_session')
            .then(function () {
                resetSessionTimer();
                toast('Session extended', 'success');
            })
            .catch(function () {
                window.location.href = '/admin/login.php';
            });
    }

    App.extendSession = extendSession;

    function initSessionWatchdog() {
        var extendBtn = document.getElementById('session-extend');
        if (extendBtn) extendBtn.addEventListener('click', extendSession);

        ['click', 'keydown', 'mousemove', 'touchstart', 'scroll'].forEach(function (evt) {
            document.addEventListener(evt, debounce(resetSessionTimer, 800), { passive: true });
        });

        resetSessionTimer();
    }
    /* --- Sidebar drawer (mobile) ------------------------------------------------------- */

    function initSidebar() {
        var sidebar = qs('.sidebar');
        var toggle = qs('.topbar-toggle');
        var backdrop = qs('.sidebar-backdrop');

        if (!sidebar || !toggle) return;

        function closeDrawer() {
            sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function () {
            var isOpen = sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('active', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        if (backdrop) backdrop.addEventListener('click', closeDrawer);

        qsa('.nav-link[data-section]', sidebar).forEach(function (link) {
            link.addEventListener('click', closeDrawer);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('open')) closeDrawer();
        });
    }

    /* --- Section router ------------------------------------------------------------------ */

    var sectionTitles = {
        overview: 'Overview',
        reservations: 'Reservations',
        menu: 'Menu',
        notifications: 'Notifications',
        settings: 'Settings',
        security: 'Security',
        account: 'Admin Account'
    };

    var sectionLoaders = {};

    function switchToSection(section) {
        if (!sectionTitles[section]) section = 'overview';

        qsa('.nav-link[data-section]').forEach(function (link) {
            var active = link.getAttribute('data-section') === section;
            link.classList.toggle('active', active);
            if (active) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        qsa('.section').forEach(function (node) {
            node.classList.toggle('active', node.id === 'section-' + section);
        });

        var title = qs('.topbar-title');
        var crumb = qs('.topbar-crumb');
        if (title) title.textContent = sectionTitles[section];
        if (crumb) crumb.textContent = 'Furusato / ' + sectionTitles[section];

        try { window.history.replaceState(null, '', '#section-' + section); } catch (e) { /* noop */ }

        if (sectionLoaders[section]) sectionLoaders[section]();
    }

    App.switchToSection = switchToSection;
    App.registerSectionLoader = function (section, fn) { sectionLoaders[section] = fn; };
    function initRouter() {
        qsa('.nav-link[data-section]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                switchToSection(link.getAttribute('data-section'));
            });
        });

        var initial = (window.location.hash || '').replace('#section-', '');
        switchToSection(sectionTitles[initial] ? initial : 'overview');
    }

    /* --- Boot ----------------------------------------------------------------------------- */

    function boot() {
        initModals();
        initConfirmDialog();
        initSessionWatchdog();
        initSidebar();
        initRouter();

        var signOut = document.getElementById('sign-out');
        if (signOut) {
            signOut.addEventListener('click', function (event) {
                event.preventDefault();
                confirmDialog({
                    title: 'Sign out',
                    message: 'End your admin session now?',
                    confirmLabel: 'Sign out'
                }).then(function (yes) {
                    if (yes) window.location.href = '/admin/logout.php';
                });
            });
        }

        if (typeof App.onBoot === 'function') App.onBoot();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();