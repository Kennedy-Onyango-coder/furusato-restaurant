/* ============================================================================
   Furusato Restaurant Administration — Feature logic
   admin/assets/dashboard.js

   Consumes window.FurusatoAdmin (see admin/assets/admin.js). Wires the real
   APIs (reservations, menu, settings, auth) into the dashboard sections.
   ========================================================================== */

(function () {
    'use strict';

    var App = window.FurusatoAdmin;
    if (!App) { return; }

    var qs = App.qs, qsa = App.qsa, el = App.el, esc = App.escapeHtml;

    var reservationsCache = [];
    var menuCache = null;

    var STATUS_LABELS = {
        pending: 'Pending',
        confirmed: 'Confirmed',
        declined: 'Declined',
        cancelled: 'Cancelled',
        completed: 'Completed',
        no_show: 'No-show'
    };

    function statusBadge(status) {
        var label = STATUS_LABELS[status] || status;
        return '<span class="badge badge-' + esc(status) + '"><span class="dot"></span>' + esc(label) + '</span>';
    }

    function emptyState(icon, text) {
        return '<div class="empty-state"><i class="' + icon + ' fa-fw" aria-hidden="true"></i><p>' + esc(text) + '</p></div>';
    }

    function phoneDigits(value) {
        return String(value || '').replace(/[^0-9+]/g, '');
    }

    function loadingBlock(label) {
        return '<div class="loading-block"><div class="spinner"></div><p>' + esc(label || 'Loading…') + '</p></div>';
    }
    /* --- Reservations: state, load, filter, render ------------------------- */

    var resState = { date: 'all', status: 'all', search: '' };

    function loadReservations() {
        var box = qs('#reservations-container');
        if (box) box.innerHTML = loadingBlock('Loading reservations…');

        return App.apiGet('/api/reservations.php')
            .then(function (data) {
                reservationsCache = Array.isArray(data.reservations) ? data.reservations : [];
                renderReservations();
            })
            .catch(function (err) {
                if (box) box.innerHTML = emptyState('fa-regular fa-circle-xmark', err.message || 'Failed to load reservations.');
            });
    }

    function filteredReservations() {
        var list = reservationsCache.slice();
        var today = new Date();
        var iso = function (d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        };

        if (resState.date === 'today') {
            list = list.filter(function (r) { return r.date === iso(today); });
        } else if (resState.date === 'tomorrow') {
            var t = new Date(today); t.setDate(t.getDate() + 1);
            list = list.filter(function (r) { return r.date === iso(t); });
        } else if (resState.date === 'week') {
            var end = new Date(today); end.setDate(end.getDate() + 7);
            list = list.filter(function (r) { return r.date >= iso(today) && r.date <= iso(end); });
        }

        if (resState.status !== 'all') {
            list = list.filter(function (r) { return (r.status || 'pending') === resState.status; });
        }

        if (resState.search) {
            var q = resState.search.toLowerCase();
            list = list.filter(function (r) {
                return [r.name, r.email, r.phone, r.id].join(' ').toLowerCase().indexOf(q) !== -1;
            });
        }

        list.sort(function (a, b) {
            var ka = (a.date || '') + ' ' + (a.time || '');
            var kb = (b.date || '') + ' ' + (b.time || '');
            return ka < kb ? -1 : (ka > kb ? 1 : 0);
        });

        return list;
    }

    function renderReservations() {
        var box = qs('#reservations-container');
        if (!box) return;

        var list = filteredReservations();
        if (!list.length) {
            box.innerHTML = emptyState('fa-regular fa-calendar', 'No reservations match the current filters.');
            return;
        }

        var html = '<div class="table-wrap"><table class="data-table">' +
            '<thead><tr><th>ID</th><th>Date</th><th>Time</th><th>Guest</th><th>Contact</th><th class="num">Guests</th><th>Status</th><th></th></tr></thead><tbody>';

        list.forEach(function (r) {
            html += '<tr data-res-id="' + esc(r.id) + '" style="cursor:pointer;">' +
                '<td class="cell-strong">' + esc(r.id) + '</td>' +
                '<td>' + esc(App.fmtDate(r.date)) + '</td>' +
                '<td class="num">' + esc(App.fmtTime(r.time)) + '</td>' +
                '<td class="cell-strong">' + esc(r.name || 'Unknown') + '</td>' +
                '<td class="cell-muted">' + esc(r.phone || '—') + (r.email ? '<br>' + esc(r.email) : '') + '</td>' +
                '<td class="num">' + esc(r.guests || 1) + '</td>' +
                '<td>' + statusBadge(r.status || 'pending') + '</td>' +
                '<td><span class="btn btn-ghost btn-sm" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span></td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
        box.innerHTML = html;
    }
    /* --- Reservations: detail modal ----------------------------------------- */

    function openReservationDetail(id) {
        var r = null;
        for (var i = 0; i < reservationsCache.length; i++) {
            if (reservationsCache[i].id === id) { r = reservationsCache[i]; break; }
        }
        if (!r) return;

        qs('#reservation-modal-title').textContent = 'Reservation ' + r.id;

        var requests = (r.special_requests || '').toString().trim();
        var notes = (r.admin_notes || '').toString().trim();

        var rows = [
            ['Reservation ID', esc(r.id || '—')],
            ['Guest', esc(r.name || '—')],
            ['Email', esc(r.email || '—')],
            ['Phone', esc(r.phone || '—')],
            ['Date', esc(App.fmtDate(r.date))],
            ['Time', esc(App.fmtTime(r.time))],
            ['Guests', esc(String(r.guests || 1))],
            ['Special requests', esc(requests !== '' ? requests : '—')],
            ['Created', esc(App.fmtDateTimeIso(r.created))],
            ['Status', statusBadge(r.status || 'pending')]
        ];

        var html = '<div class="detail-grid">';
        rows.forEach(function (row) {
            html += '<div class="dt">' + esc(row[0]) + '</div><div class="dd">' + row[1] + '</div>';
        });
        html += '</div>';

        html += '<div class="field" style="margin-top:6px;"><label for="res-notes">Internal notes</label>' +
            '<textarea id="res-notes" placeholder="Add a private note for staff…" style="min-height:64px;">' + esc(notes) + '</textarea></div>';

        qs('#reservation-modal-body').innerHTML = html;

        var statusSelect = '<select id="res-status-select" style="min-width:150px;border:1px solid var(--border-strong);border-radius:var(--radius-sm);padding:8px 11px;font-family:inherit;font-size:0.84rem;background:var(--surface);color:var(--ink);">';
        Object.keys(STATUS_LABELS).forEach(function (s) {
            statusSelect += '<option value="' + esc(s) + '"' + (s === (r.status || 'pending') ? ' selected' : '') + '>' + esc(STATUS_LABELS[s]) + '</option>';
        });
        statusSelect += '</select>';

        var contact = '';
        if (r.phone) {
            contact += '<a class="btn btn-outline btn-sm" href="tel:' + esc(phoneDigits(r.phone)) + '"><i class="fa-solid fa-phone btn-icon" aria-hidden="true"></i> Call</a> ';
            contact += '<a class="btn btn-outline btn-sm" href="https://wa.me/' + esc(phoneDigits(r.phone)) + '" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp btn-icon" aria-hidden="true"></i> WhatsApp</a> ';
        }
        if (r.email) {
            contact += '<a class="btn btn-outline btn-sm" href="mailto:' + esc(r.email) + '"><i class="fa-solid fa-envelope btn-icon" aria-hidden="true"></i> Email</a> ';
        }

        qs('#reservation-modal-foot').innerHTML =
            '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;width:100%;">' +
            '<div style="display:flex;gap:8px;flex-wrap:wrap;">' + contact + '</div>' +
            '<div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">' +
            statusSelect +
            '<button type="button" class="btn btn-primary btn-sm" id="res-update-status-btn"><span class="spinner-inline"></span><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Update status</button>' +
            '<button type="button" class="btn btn-outline btn-sm" id="res-save-notes-btn"><span class="spinner-inline"></span><i class="fa-solid fa-note-sticky btn-icon" aria-hidden="true"></i> Save notes</button>' +
            '<button type="button" class="btn btn-danger-outline btn-sm" id="res-delete-btn"><i class="fa-solid fa-trash btn-icon" aria-hidden="true"></i> Delete</button>' +
            '</div></div>';

        App.openModal('reservation-modal');

        qs('#res-update-status-btn').addEventListener('click', function () {
            updateReservationStatus(r, qs('#res-status-select').value);
        });
        qs('#res-save-notes-btn').addEventListener('click', function () {
            updateReservationNotes(r, qs('#res-notes').value);
        });
        qs('#res-delete-btn').addEventListener('click', function () {
            deleteReservation(r);
        });
    }
    function updateReservationStatus(r, status) {
        var destructive = (status === 'cancelled' || status === 'declined');
        var doIt = function () {
            var btn = qs('#res-update-status-btn');
            App.busyButton(btn, true);
            App.apiJson('/api/reservations.php', { action: 'update_status', id: r.id, status: status })
                .then(function () {
                    App.toast('Reservation marked ' + STATUS_LABELS[status], 'success');
                    App.closeModal('reservation-modal');
                    return loadReservations();
                })
                .catch(function (err) { App.toast(err.message || 'Update failed', 'error'); })
                .then(function () { App.busyButton(btn, false); });
        };

        if (destructive) {
            App.confirmDialog({
                title: 'Confirm status change',
                message: 'Mark this reservation as ' + STATUS_LABELS[status] + '?',
                confirmLabel: 'Confirm',
                danger: true
            }).then(function (yes) { if (yes) doIt(); });
        } else {
            doIt();
        }
    }

    function updateReservationNotes(r, notes) {
        var btn = qs('#res-save-notes-btn');
        App.busyButton(btn, true);
        App.apiJson('/api/reservations.php', { action: 'update_notes', id: r.id, notes: notes })
            .then(function () {
                App.toast('Notes saved', 'success');
                App.closeModal('reservation-modal');
                return loadReservations();
            })
            .catch(function (err) { App.toast(err.message || 'Failed to save notes', 'error'); })
            .then(function () { App.busyButton(btn, false); });
    }

    function deleteReservation(r) {
        App.confirmDialog({
            title: 'Delete reservation',
            message: 'Permanently delete reservation ' + r.id + '? This cannot be undone.',
            confirmLabel: 'Delete',
            danger: true
        }).then(function (yes) {
            if (!yes) return;
            var btn = qs('#res-delete-btn');
            App.busyButton(btn, true);
            App.apiJson('/api/reservations.php', { action: 'delete', id: r.id })
                .then(function () {
                    App.toast('Reservation deleted', 'success');
                    App.closeModal('reservation-modal');
                    return loadReservations();
                })
                .catch(function (err) { App.toast(err.message || 'Delete failed', 'error'); })
                .then(function () { App.busyButton(btn, false); });
        });
    }

    function initReservations() {
        var search = qs('#res-search');
        if (search) {
            search.addEventListener('input', App.debounce(function () {
                resState.search = search.value;
                renderReservations();
            }, 200));
        }

        qsa('#res-date-filter button').forEach(function (b) {
            b.addEventListener('click', function () {
                qsa('#res-date-filter button').forEach(function (x) { x.classList.remove('active'); });
                b.classList.add('active');
                resState.date = b.getAttribute('data-date');
                renderReservations();
            });
        });

        qsa('#res-status-filter button').forEach(function (b) {
            b.addEventListener('click', function () {
                qsa('#res-status-filter button').forEach(function (x) { x.classList.remove('active'); });
                b.classList.add('active');
                resState.status = b.getAttribute('data-status');
                renderReservations();
            });
        });

        var refresh = qs('#res-refresh');
        if (refresh) refresh.addEventListener('click', loadReservations);

        var container = qs('#reservations-container');
        if (container) {
            container.addEventListener('click', function (e) {
                var row = e.target.closest ? e.target.closest('tr[data-res-id]') : null;
                if (row) openReservationDetail(row.getAttribute('data-res-id'));
            });
        }
    }

    App.registerSectionLoader('reservations', function () { if (!reservationsCache.length) loadReservations(); });
    /* --- Menu: load and render --------------------------------------------- */

    function loadMenu() {
        var box = qs('#menu-container');
        if (box) box.innerHTML = loadingBlock('Loading menu…');

        return App.apiGet('/api/menu.php')
            .then(function (data) {
                menuCache = data && typeof data === 'object' ? data : { categories: [] };
                populateCategoryFilter();
                renderMenu();
            })
            .catch(function (err) {
                if (box) box.innerHTML = emptyState('fa-regular fa-circle-xmark', err.message || 'Failed to load menu.');
            });
    }

    function countItems(loc) {
        return (loc && Array.isArray(loc.items)) ? loc.items.length : 0;
    }

    function menuFilters() {
        var search = qs('#menu-search');
        var catSel = qs('#menu-category-filter');
        var availSel = qs('#menu-avail-filter');
        return {
            search: search ? search.value.toLowerCase() : '',
            category: catSel ? catSel.value : 'all',
            avail: availSel ? availSel.value : 'all'
        };
    }

    function itemVisible(item) {
        return (item.visible === undefined) ? true : (item.visible !== false);
    }

    function itemMatches(item, f) {
        if (f.category !== 'all' && item._cat !== f.category) return false;
        if (f.avail === 'visible' && !itemVisible(item)) return false;
        if (f.avail === 'hidden' && itemVisible(item)) return false;
        if (f.search) {
            var hay = [item.name, item.description, item.badge].join(' ').toLowerCase();
            if (hay.indexOf(f.search) === -1) return false;
        }
        return true;
    }

    function itemRow(item, cat, sub) {
        var f = menuFilters();
        if (!itemMatches(item, f)) return '';

        var thumb = item.image_url
            ? '<img class="item-thumb" src="' + esc(item.image_url) + '" alt="" loading="lazy">'
            : '<div class="item-thumb-empty"><i class="fa-solid fa-utensils" aria-hidden="true"></i></div>';

        var price = App.fmtMoney(item.price);
        if (item.original_price) {
            price = '<span class="price-was">' + App.fmtMoney(item.original_price) + '</span>' + price;
        }

        var badges = '';
        if (item.badge) badges += '<span class="badge badge-featured">' + esc(item.badge) + '</span> ';
        if (!itemVisible(item)) badges += '<span class="badge badge-hidden"><span class="dot"></span>Hidden</span>';

        return '<tr data-item-id="' + esc(item.id) + '" data-cat="' + esc(cat.id) + '" data-sub="' + esc(sub ? sub.id : '') + '">' +
            '<td class="drag-cell"><span class="drag-handle" draggable="true" aria-label="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></span></td>' +
            '<td>' + thumb + '</td>' +
            '<td class="item-name-cell">' + esc(item.name) +
                (item.description ? '<div class="item-desc-cell">' + esc(item.description) + '</div>' : '') + '</td>' +
            '<td class="price-cell">' + price + '</td>' +
            '<td>' + badges + '</td>' +
            '<td><div class="row-actions">' +
                '<button type="button" class="icon-btn" data-edit-item="' + esc(item.id) + '" aria-label="Edit item"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>' +
                '<button type="button" class="icon-btn danger" data-del-item="' + esc(item.id) + '" aria-label="Delete item"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>' +
            '</div></td></tr>';
    }

    function itemsTable(items, cat, sub) {
        var f = menuFilters();
        var shown = (items || []).filter(function (it) {
            it._cat = cat.id; it._sub = sub ? sub.id : '';
            return itemMatches(it, f);
        });

        if (!shown.length) return '';

        var html = '<div class="menu-items-wrap"><table class="menu-items">' +
            '<thead><tr><th class="drag-cell"></th><th></th><th>Item</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody>';
        shown.forEach(function (it) { html += itemRow(it, cat, sub); });
        html += '</tbody></table></div>';
        return html;
    }
    function categoryCard(cat) {
        var total = countItems(cat) + (cat.subcategories || []).reduce(function (n, s) { return n + countItems(s); }, 0);

        var subHtml = '';
        (cat.subcategories || []).forEach(function (sub) {
            subHtml +=
                '<div class="subcat-list" style="padding:10px 18px 0;">' +
                '<div class="subcat-row" data-subcat-id="' + esc(sub.id) + '" data-cat="' + esc(cat.id) + '">' +
                '<span class="drag-handle" draggable="true" data-drag-subcat="' + esc(sub.id) + '" aria-label="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></span>' +
                '<span class="sub-name">' + esc(sub.label || 'Subcategory') + '</span>' +
                (sub.labelJp ? '<span class="sub-jp">' + esc(sub.labelJp) + '</span>' : '') +
                '<span class="cat-count">' + countItems(sub) + ' items</span>' +
                '<span class="cat-actions">' +
                '<button type="button" class="icon-btn" data-add-item-sub="' + esc(sub.id) + '" aria-label="Add item"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>' +
                '<button type="button" class="icon-btn" data-edit-sub="' + esc(sub.id) + '" aria-label="Edit subcategory"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>' +
                '<button type="button" class="icon-btn danger" data-del-sub="' + esc(sub.id) + '" aria-label="Delete subcategory"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>' +
                '</span></div></div>' +
                '<div style="padding:0 18px;">' + itemsTable(sub.items || [], cat, sub) + '</div>';
        });

        return '<div class="menu-category" data-cat-id="' + esc(cat.id) + '">' +
            '<div class="menu-cat-head">' +
            '<span class="drag-handle" draggable="true" data-drag-cat="' + esc(cat.id) + '" aria-label="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></span>' +
            '<i class="fa-solid fa-folder cat-icon" aria-hidden="true"></i>' +
            '<span class="cat-name">' + esc(cat.label || cat.name || 'Category') + '</span>' +
            (cat.labelJp ? '<span class="cat-jp">' + esc(cat.labelJp) + '</span>' : '') +
            (cat.visible === false ? '<span class="badge badge-hidden"><span class="dot"></span>Hidden</span>' : '') +
            '<span class="cat-count">' + total + ' items</span>' +
            '<span class="cat-actions">' +
            '<button type="button" class="btn btn-outline btn-sm" data-add-item="' + esc(cat.id) + '"><i class="fa-solid fa-plus btn-icon" aria-hidden="true"></i> Item</button>' +
            '<button type="button" class="btn btn-outline btn-sm" data-add-sub="' + esc(cat.id) + '"><i class="fa-solid fa-list btn-icon" aria-hidden="true"></i> Subcategory</button>' +
            '<button type="button" class="icon-btn" data-edit-cat="' + esc(cat.id) + '" aria-label="Edit category"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>' +
            '<button type="button" class="icon-btn danger" data-del-cat="' + esc(cat.id) + '" aria-label="Delete category"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>' +
            '</span></div>' +
            subHtml +
            itemsTable(cat.items || [], cat, null) +
            '</div>';
    }

    function renderMenu() {
        var box = qs('#menu-container');
        if (!box) return;

        if (!menuCache || !Array.isArray(menuCache.categories) || !menuCache.categories.length) {
            box.innerHTML = emptyState('fa-regular fa-folder-open', 'No menu categories yet. Add a category to begin.');
            return;
        }

        var html = '';
        menuCache.categories.forEach(function (cat) { html += categoryCard(cat); });

        if (!html) {
            box.innerHTML = emptyState('fa-regular fa-folder-open', 'No items match the current filters.');
            return;
        }
        box.innerHTML = html;
    }

    function populateCategoryFilter() {
        var sel = qs('#menu-category-filter');
        if (!sel || !menuCache) return;
        var current = sel.value;
        var html = '<option value="all">All categories</option>';
        (menuCache.categories || []).forEach(function (cat) {
            html += '<option value="' + esc(cat.id) + '">' + esc(cat.label || cat.name) + '</option>';
        });
        sel.innerHTML = html;
        if (current) sel.value = current;
    }
    function initMenu() {
        var search = qs('#menu-search');
        if (search) search.addEventListener('input', App.debounce(renderMenu, 200));

        var catSel = qs('#menu-category-filter');
        if (catSel) catSel.addEventListener('change', renderMenu);

        var availSel = qs('#menu-avail-filter');
        if (availSel) availSel.addEventListener('change', renderMenu);

        var refresh = qs('#menu-refresh');
        if (refresh) refresh.addEventListener('click', loadMenu);

        var backupBtn = qs('#menu-backup-btn');
        if (backupBtn) backupBtn.addEventListener('click', createMenuBackup);

        var addItem = qs('#add-item-btn');
        if (addItem) addItem.addEventListener('click', function () { openItemEditor(null, null, null); });

        var addCat = qs('#add-category-btn');
        if (addCat) addCat.addEventListener('click', function () { openCategoryEditor(null); });

        var addSub = qs('#add-subcategory-btn');
        if (addSub) addSub.addEventListener('click', function () { openSubcategoryEditor(null, null); });

        var container = qs('#menu-container');
        if (container) {
            container.addEventListener('click', onMenuClick);
            container.addEventListener('dragstart', onMenuDragStart);
            container.addEventListener('dragover', onMenuDragOver);
            container.addEventListener('drop', onMenuDrop);
            container.addEventListener('dragend', onMenuDragEnd);
        }
    }

    App.registerSectionLoader('menu', function () { if (!menuCache) loadMenu(); });
    /* --- Menu: event delegation and lookups -------------------------------- */

    function findMenuItem(id) {
        var cats = (menuCache && menuCache.categories) || [];
        for (var i = 0; i < cats.length; i++) {
            var cat = cats[i];
            var items = cat.items || [];
            for (var j = 0; j < items.length; j++) {
                if (items[j].id === id) return { item: items[j], cat: cat, sub: null };
            }
            var subs = cat.subcategories || [];
            for (var k = 0; k < subs.length; k++) {
                var sitems = subs[k].items || [];
                for (var m = 0; m < sitems.length; m++) {
                    if (sitems[m].id === id) return { item: sitems[m], cat: cat, sub: subs[k] };
                }
            }
        }
        return null;
    }

    function findCategory(id) {
        var cats = (menuCache && menuCache.categories) || [];
        for (var i = 0; i < cats.length; i++) { if (cats[i].id === id) return cats[i]; }
        return null;
    }

    function findSubcategory(id) {
        var cats = (menuCache && menuCache.categories) || [];
        for (var i = 0; i < cats.length; i++) {
            var subs = cats[i].subcategories || [];
            for (var j = 0; j < subs.length; j++) { if (subs[j].id === id) return { cat: cats[i], sub: subs[j] }; }
        }
        return null;
    }

    function onMenuClick(e) {
        var t = e.target.closest ? e.target.closest('[data-edit-item],[data-del-item],[data-add-item],[data-add-item-sub],[data-edit-cat],[data-del-cat],[data-add-sub],[data-edit-sub],[data-del-sub]') : null;
        if (!t) return;

        if (t.hasAttribute('data-edit-item')) { openItemEditor(findMenuItem(t.getAttribute('data-edit-item'))); return; }
        if (t.hasAttribute('data-del-item')) { deleteMenuItem(t.getAttribute('data-del-item')); return; }
        if (t.hasAttribute('data-add-item')) { openItemEditor(null, t.getAttribute('data-add-item'), null); return; }
        if (t.hasAttribute('data-add-item-sub')) {
            var subId = t.getAttribute('data-add-item-sub');
            var loc = findSubcategory(subId);
            openItemEditor(null, loc ? loc.cat.id : null, subId);
            return;
        }
        if (t.hasAttribute('data-edit-cat')) { openCategoryEditor(findCategory(t.getAttribute('data-edit-cat'))); return; }
        if (t.hasAttribute('data-del-cat')) { deleteCategory(t.getAttribute('data-del-cat')); return; }
        if (t.hasAttribute('data-add-sub')) { openSubcategoryEditor(findCategory(t.getAttribute('data-add-sub')), null); return; }
        if (t.hasAttribute('data-edit-sub')) { openSubcategoryEditor(findSubcategory(t.getAttribute('data-edit-sub'))); return; }
        if (t.hasAttribute('data-del-sub')) { deleteSubcategory(t.getAttribute('data-del-sub')); return; }
    }

    function categoryOptions(selected) {
        var cats = (menuCache && menuCache.categories) || [];
        var html = '<option value="">Select category…</option>';
        cats.forEach(function (c) {
            html += '<option value="' + esc(c.id) + '"' + (c.id === selected ? ' selected' : '') + '>' + esc(c.label || c.name) + '</option>';
        });
        return html;
    }

    function subcategoryOptions(catId, selected) {
        if (!catId) return '<option value="">No category selected</option>';
        var cat = findCategory(catId);
        if (!cat) return '<option value="">Select category…</option>';
        var html = '<option value="">None (top level)</option>';
        (cat.subcategories || []).forEach(function (s) {
            html += '<option value="' + esc(s.id) + '"' + (s.id === selected ? ' selected' : '') + '>' + esc(s.label) + '</option>';
        });
        return html;
    }
    function openItemEditor(found, presetCat, presetSub) {
        var item = found ? found.item : null;
        var cat = found ? found.cat : (presetCat ? findCategory(presetCat) : null);
        var sub = found ? found.sub : (presetSub ? findSubcategory(presetSub) : null);

        qs('#item-modal-title').textContent = item ? 'Edit menu item' : 'Add menu item';

        var html =
            '<form id="item-form" class="form-grid">' +
            '<div class="field"><label for="it-name">Name <span class="req">*</span></label>' +
            '<input type="text" id="it-name" name="name" value="' + esc(item ? item.name : '') + '" required maxlength="100"></div>' +
            '<div class="field"><label for="it-desc">Description</label>' +
            '<textarea id="it-desc" name="description">' + esc(item ? (item.description || '') : '') + '</textarea></div>' +
            '<div class="form-grid-2">' +
            '<div class="field"><label for="it-price">Price (Ksh) <span class="req">*</span></label>' +
            '<input type="number" id="it-price" name="price" value="' + esc(item ? item.price : '') + '" min="0" step="0.01" required></div>' +
            '<div class="field"><label for="it-orig-price">Original price (optional)</label>' +
            '<input type="number" id="it-orig-price" name="original_price" value="' + esc(item ? (item.original_price || '') : '') + '" min="0" step="0.01">' +
            '<div class="hint">Shown struck-through when higher than the price.</div></div>' +
            '</div>' +
            '<div class="form-grid-2">' +
            '<div class="field"><label for="it-category">Category <span class="req">*</span></label>' +
            '<select id="it-category" name="category_id" required>' + categoryOptions(cat ? cat.id : null) + '</select></div>' +
            '<div class="field"><label for="it-subcategory">Subcategory</label>' +
            '<select id="it-subcategory" name="subcategory_id">' + subcategoryOptions(cat ? cat.id : null, sub ? sub.id : null) + '</select></div>' +
            '</div>' +
            '<div class="field"><label for="it-badge">Badge (optional)</label>' +
            '<input type="text" id="it-badge" name="badge" value="' + esc(item ? (item.badge || '') : '') + '" maxlength="50" placeholder="e.g. Popular, For 2 Persons">' +
            '<div class="hint">Short label shown on the public menu.</div></div>' +
            '<div class="field"><label for="it-image">Image</label>' +
            '<div class="upload-preview" style="margin-bottom:8px;">' +
            (item && item.image_url ? '<img src="' + esc(item.image_url) + '" alt="">' : '') +
            '<span class="hint" id="it-image-name">' + (item && item.image ? esc(item.image.split('/').pop()) : 'No new image selected') + '</span>' +
            '</div>' +
            '<input type="file" id="it-image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">' +
            '<div class="hint">JPEG, PNG, WEBP or GIF up to 5MB. The previous image is kept until a new one uploads successfully.</div></div>' +
            '<label class="field-check"><input type="checkbox" id="it-visible" name="visible" value="1"' + (item ? (itemVisible(item) ? ' checked' : '') : ' checked') + '> Visible on public menu</label>' +
            (item ? '<input type="hidden" name="id" value="' + esc(item.id) + '">' : '') +
            '</form>';

        qs('#item-modal-body').innerHTML = html;
        qs('#item-modal-foot').innerHTML =
            '<button type="button" class="btn btn-outline" data-close-modal>Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="item-save-btn"><span class="spinner-inline"></span><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save item</button>';

        App.openModal('item-modal');

        var catSel = qs('#it-category');
        var subSel = qs('#it-subcategory');
        if (catSel && subSel) {
            catSel.addEventListener('change', function () {
                subSel.innerHTML = subcategoryOptions(catSel.value, null);
            });
        }

        var img = qs('#it-image');
        if (img) img.addEventListener('change', function () {
            var label = qs('#it-image-name');
            if (label && img.files.length) label.textContent = img.files[0].name;
        });

        qs('#item-save-btn').addEventListener('click', saveMenuItem);
    }
    function saveMenuItem() {
        var form = qs('#item-form');
        if (!form) return;
        var idInput = form.querySelector('[name=id]');
        var editing = idInput ? idInput.value : '';

        var fd = new FormData(form);
        fd.set('action', editing ? 'update_item' : 'add_item');
        var visibleBox = form.querySelector('#it-visible');
        fd.set('visible', (visibleBox && visibleBox.checked) ? '1' : '0');
        if (editing) {
            var found = findMenuItem(editing);
            fd.set('existing_image', (found && found.item.image) ? found.item.image : '');
        }

        var btn = qs('#item-save-btn');
        App.busyButton(btn, true);
        App.apiForm('/api/menu.php', fd)
            .then(function (data) {
                App.toast(data.message || 'Item saved', 'success');
                App.closeModal('item-modal');
                return loadMenu();
            })
            .catch(function (err) { App.toast(err.message || 'Save failed', 'error'); })
            .then(function () { App.busyButton(btn, false); });
    }

    function deleteMenuItem(id) {
        var found = findMenuItem(id);
        App.confirmDialog({
            title: 'Delete menu item',
            message: 'Delete "' + (found ? found.item.name : id) + '" from the menu?',
            confirmLabel: 'Delete',
            danger: true
        }).then(function (yes) {
            if (!yes) return;
            var fd = new FormData();
            fd.append('action', 'delete_item');
            fd.append('id', id);
            App.apiForm('/api/menu.php', fd)
                .then(function (data) {
                    App.toast(data.message || 'Item deleted', 'success');
                    return loadMenu();
                })
                .catch(function (err) { App.toast(err.message || 'Delete failed', 'error'); });
        });
    }
    /* --- Menu: category editor ---------------------------------------------- */

    function openCategoryEditor(cat) {
        qs('#category-modal-title').textContent = cat ? 'Edit category' : 'Add category';

        var html =
            '<form id="category-form" class="form-grid">' +
            '<div class="field"><label for="cat-label">Label <span class="req">*</span></label>' +
            '<input type="text" id="cat-label" name="label" value="' + esc(cat ? (cat.label || '') : '') + '" required maxlength="50"></div>' +
            '<div class="field"><label for="cat-labeljp">Japanese label (optional)</label>' +
            '<input type="text" id="cat-labeljp" name="labelJp" value="' + esc(cat ? (cat.labelJp || '') : '') + '" maxlength="50"></div>' +
            '<label class="field-check"><input type="checkbox" id="cat-visible" name="visible" value="1"' + (cat ? (cat.visible === false ? '' : ' checked') : ' checked') + '> Visible on public menu</label>' +
            (cat ? '<input type="hidden" name="category_id" value="' + esc(cat.id) + '">' : '') +
            '</form>';

        qs('#category-modal-body').innerHTML = html;
        qs('#category-modal-foot').innerHTML =
            '<button type="button" class="btn btn-outline" data-close-modal>Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="category-save-btn"><span class="spinner-inline"></span><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save category</button>';

        App.openModal('category-modal');
        qs('#category-save-btn').addEventListener('click', saveCategory);
    }

    function saveCategory() {
        var form = qs('#category-form');
        if (!form) return;
        var idInput = form.querySelector('[name=category_id]');
        var editing = idInput ? idInput.value : '';

        var fd = new FormData(form);
        fd.set('action', editing ? 'edit_category' : 'create_category');
        var catVisibleBox = form.querySelector('#cat-visible');
        fd.set('visible', (catVisibleBox && catVisibleBox.checked) ? '1' : '0');

        var btn = qs('#category-save-btn');
        App.busyButton(btn, true);
        App.apiForm('/api/menu.php', fd)
            .then(function (data) {
                App.toast(data.message || 'Category saved', 'success');
                App.closeModal('category-modal');
                return loadMenu();
            })
            .catch(function (err) { App.toast(err.message || 'Save failed', 'error'); })
            .then(function () { App.busyButton(btn, false); });
    }

    function deleteCategory(id) {
        var cat = findCategory(id);
        App.confirmDialog({
            title: 'Delete category',
            message: 'Delete category "' + (cat ? cat.label : id) + '" and all its items?',
            confirmLabel: 'Delete',
            danger: true
        }).then(function (yes) {
            if (!yes) return;
            var fd = new FormData();
            fd.append('action', 'delete_category');
            fd.append('category_id', id);
            App.apiForm('/api/menu.php', fd)
                .then(function (data) {
                    App.toast(data.message || 'Category deleted', 'success');
                    return loadMenu();
                })
                .catch(function (err) { App.toast(err.message || 'Delete failed', 'error'); });
        });
    }
    /* --- Menu: subcategory editor ------------------------------------------- */

    function openSubcategoryEditor(loc, presetCat) {
        var cat = loc ? loc.cat : (presetCat ? presetCat : null);
        var sub = loc ? loc.sub : null;

        qs('#category-modal-title').textContent = sub ? 'Edit subcategory' : 'Add subcategory';

        var parentSelect = '<select id="sub-parent" name="parent_id" required>';
        var cats = (menuCache && menuCache.categories) || [];
        parentSelect += '<option value="">Select category…</option>';
        cats.forEach(function (c) {
            parentSelect += '<option value="' + esc(c.id) + '"' + (cat && c.id === cat.id ? ' selected' : '') + '>' + esc(c.label || c.name) + '</option>';
        });
        parentSelect += '</select>';

        var html =
            '<form id="subcategory-form" class="form-grid">' +
            '<div class="field"><label for="sub-parent">Parent category <span class="req">*</span></label>' + parentSelect + '</div>' +
            '<div class="field"><label for="sub-label">Label <span class="req">*</span></label>' +
            '<input type="text" id="sub-label" name="label" value="' + esc(sub ? (sub.label || '') : '') + '" required maxlength="50"></div>' +
            '<div class="field"><label for="sub-labeljp">Japanese label (optional)</label>' +
            '<input type="text" id="sub-labeljp" name="labelJp" value="' + esc(sub ? (sub.labelJp || '') : '') + '" maxlength="50"></div>' +
            (sub ? '<input type="hidden" name="subcategory_id" value="' + esc(sub.id) + '">' : '') +
            '</form>';

        qs('#category-modal-body').innerHTML = html;
        qs('#category-modal-foot').innerHTML =
            '<button type="button" class="btn btn-outline" data-close-modal>Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="subcategory-save-btn"><span class="spinner-inline"></span><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save subcategory</button>';

        App.openModal('category-modal');
        qs('#subcategory-save-btn').addEventListener('click', saveSubcategory);
    }

    function saveSubcategory() {
        var form = qs('#subcategory-form');
        if (!form) return;
        var idInput = form.querySelector('[name=subcategory_id]');
        var editing = idInput ? idInput.value : '';

        var fd = new FormData(form);
        fd.set('action', editing ? 'edit_subcategory' : 'create_subcategory');

        var btn = qs('#subcategory-save-btn');
        App.busyButton(btn, true);
        App.apiForm('/api/menu.php', fd)
            .then(function (data) {
                App.toast(data.message || 'Subcategory saved', 'success');
                App.closeModal('category-modal');
                return loadMenu();
            })
            .catch(function (err) { App.toast(err.message || 'Save failed', 'error'); })
            .then(function () { App.busyButton(btn, false); });
    }

    function deleteSubcategory(id) {
        var loc = findSubcategory(id);
        App.confirmDialog({
            title: 'Delete subcategory',
            message: 'Delete subcategory "' + (loc ? loc.sub.label : id) + '" and its items?',
            confirmLabel: 'Delete',
            danger: true
        }).then(function (yes) {
            if (!yes) return;
            var fd = new FormData();
            fd.append('action', 'delete_subcategory');
            fd.append('subcategory_id', id);
            App.apiForm('/api/menu.php', fd)
                .then(function (data) {
                    App.toast(data.message || 'Subcategory deleted', 'success');
                    return loadMenu();
                })
                .catch(function (err) { App.toast(err.message || 'Delete failed', 'error'); });
        });
    }
    /* --- Menu: drag-and-drop reordering ------------------------------------- */

    var dragState = null;

    function onMenuDragStart(e) {
        var handle = e.target.closest ? e.target.closest('.drag-handle[draggable]') : null;
        if (!handle) return;

        if (handle.hasAttribute('data-drag-cat')) {
            dragState = { type: 'category', id: handle.getAttribute('data-drag-cat') };
        } else if (handle.hasAttribute('data-drag-subcat')) {
            var subId = handle.getAttribute('data-drag-subcat');
            var loc = findSubcategory(subId);
            dragState = { type: 'subcategory', id: subId, catId: loc ? loc.cat.id : '' };
        } else {
            var row = handle.closest('tr[data-item-id]');
            if (!row) return;
            dragState = {
                type: 'item',
                id: row.getAttribute('data-item-id'),
                catId: row.getAttribute('data-cat'),
                subId: row.getAttribute('data-sub')
            };
        }

        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', dragState.id); } catch (err) { /* noop */ }
    }

    function findDropTarget(el) {
        if (!dragState || !el || !el.closest) return null;
        if (dragState.type === 'category') return el.closest('.menu-category');
        if (dragState.type === 'subcategory') return el.closest('.subcat-row');
        return el.closest('tr[data-item-id]');
    }

    function onMenuDragOver(e) {
        if (!dragState) return;
        var target = findDropTarget(e.target);
        qsa('.drop-target').forEach(function (n) { n.classList.remove('drop-target'); });
        if (target) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            target.classList.add('drop-target');
        }
    }

    function onMenuDragEnd() {
        qsa('.drop-target').forEach(function (n) { n.classList.remove('drop-target'); });
        dragState = null;
    }

    function onMenuDrop(e) {
        if (!dragState) return;
        var target = findDropTarget(e.target);
        qsa('.drop-target').forEach(function (n) { n.classList.remove('drop-target'); });
        if (!target) { dragState = null; return; }

        e.preventDefault();
        var st = dragState;
        dragState = null;

        if (st.type === 'category') {
            reorderCategories(st.id, target.getAttribute('data-cat-id'));
        } else if (st.type === 'subcategory') {
            reorderSubcategories(st.catId, st.id, target.getAttribute('data-subcat-id'));
        } else {
            var targetItemId = target.getAttribute('data-item-id');
            var targetCat = target.getAttribute('data-cat');
            var targetSub = target.getAttribute('data-sub') || '';
            if (targetCat === st.catId && targetSub === st.subId) {
                reorderItems(st.catId, st.subId, st.id, targetItemId);
            }
        }
    }
    function itemIdsInGroup(catId, subId) {
        var ids = [];
        var cats = (menuCache && menuCache.categories) || [];
        for (var i = 0; i < cats.length; i++) {
            if (cats[i].id !== catId) continue;
            if (subId) {
                var sub = null;
                (cats[i].subcategories || []).forEach(function (s) { if (s.id === subId) sub = s; });
                if (sub) (sub.items || []).forEach(function (it) { ids.push(it.id); });
            } else {
                (cats[i].items || []).forEach(function (it) { ids.push(it.id); });
            }
        }
        return ids;
    }

    function reorderItems(catId, subId, draggedId, targetId) {
        var ids = itemIdsInGroup(catId, subId);
        var from = ids.indexOf(draggedId);
        if (from === -1) return;
        ids.splice(from, 1);
        var insertAt = ids.indexOf(targetId);
        if (insertAt === -1) return;
        ids.splice(insertAt, 0, draggedId);

        App.apiJson('/api/menu.php', { action: 'reorder_items', category_id: catId, subcategory_id: subId, item_ids: ids })
            .then(function () { return loadMenu(); })
            .catch(function (err) { App.toast(err.message || 'Reorder failed', 'error'); loadMenu(); });
    }

    function reorderCategories(draggedId, targetId) {
        var ids = (menuCache.categories || []).map(function (c) { return c.id; });
        var from = ids.indexOf(draggedId);
        if (from === -1) return;
        ids.splice(from, 1);
        var insertAt = ids.indexOf(targetId);
        if (insertAt === -1) return;
        ids.splice(insertAt, 0, draggedId);

        App.apiJson('/api/menu.php', { action: 'reorder_categories', category_ids: ids })
            .then(function () { return loadMenu(); })
            .catch(function (err) { App.toast(err.message || 'Reorder failed', 'error'); loadMenu(); });
    }

    function reorderSubcategories(catId, draggedId, targetId) {
        var cat = findCategory(catId);
        if (!cat) return;
        var ids = (cat.subcategories || []).map(function (s) { return s.id; });
        var from = ids.indexOf(draggedId);
        if (from === -1) return;
        ids.splice(from, 1);
        var insertAt = ids.indexOf(targetId);
        if (insertAt === -1) return;
        ids.splice(insertAt, 0, draggedId);

        App.apiJson('/api/menu.php', { action: 'reorder_subcategories', category_id: catId, subcategory_ids: ids })
            .then(function () { return loadMenu(); })
            .catch(function (err) { App.toast(err.message || 'Reorder failed', 'error'); loadMenu(); });
    }

    function createMenuBackup() {
        var btn = qs('#menu-backup-btn');
        App.busyButton(btn, true);
        fetch('/api/menu-backup.php?action=create', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': App.csrfToken(), 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) { App.toast(data.message || 'Backup created', 'success'); }
                else { throw new Error((data && data.error) || 'Backup failed'); }
            })
            .catch(function (err) { App.toast(err.message || 'Backup failed', 'error'); })
            .then(function () { App.busyButton(btn, false); });
    }
    /* --- Settings ------------------------------------------------------------ */

    function formValues(form) {
        var out = {};
        var inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(function (inp) {
            if (!inp.name) return;
            out[inp.name] = inp.value;
        });
        return out;
    }

    function initSettings() {
        var restForm = qs('#restaurant-settings-form');
        if (restForm) {
            restForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = qs('#restaurant-settings-btn');
                App.busyButton(btn, true);
                App.apiJson('/api/settings.php', { action: 'update_restaurant', settings: formValues(restForm) })
                    .then(function (data) { App.toast(data.message || 'Settings saved', 'success'); })
                    .catch(function (err) { App.toast(err.message || 'Save failed', 'error'); })
                    .then(function () { App.busyButton(btn, false); });
            });
        }

        var waForm = qs('#whatsapp-settings-form');
        if (waForm) {
            waForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = qs('#whatsapp-settings-btn');
                App.busyButton(btn, true);
                App.apiJson('/api/settings.php', { action: 'update_whatsapp', settings: formValues(waForm) })
                    .then(function (data) {
                        App.toast(data.message || 'WhatsApp settings saved', 'success');
                        var keyInput = qs('#set-wa-key');
                        if (keyInput) keyInput.value = '';
                    })
                    .catch(function (err) { App.toast(err.message || 'Save failed', 'error'); })
                    .then(function () { App.busyButton(btn, false); });
            });
        }
    }


    /* --- Notifications ------------------------------------------------------- */

    function initNotifications() {
        var emailForm = qs('#test-email-form');
        if (emailForm) {
            emailForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = qs('#test-email-btn');
                App.busyButton(btn, true);
                App.apiJson('/api/settings.php', { action: 'test_email', email: qs('#test-email-input').value })
                    .then(function (data) { App.toast(data.message || 'Test email sent', 'success'); })
                    .catch(function (err) { App.toast(err.message || 'Send failed', 'error'); })
                    .then(function () { App.busyButton(btn, false); });
            });
        }

        var waForm = qs('#test-whatsapp-form');
        if (waForm) {
            waForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = qs('#test-whatsapp-btn');
                App.busyButton(btn, true);
                App.apiJson('/api/settings.php', { action: 'test_whatsapp', phone_number: qs('#test-whatsapp-phone').value })
                    .then(function (data) { App.toast(data.message || 'Test message sent', 'success'); })
                    .catch(function (err) { App.toast(err.message || 'Send failed', 'error'); })
                    .then(function () { App.busyButton(btn, false); });
            });
        }
    }

    /* --- Security (2FA) ------------------------------------------------------ */

    function initSecurity() {
        var enableBtn = qs('#enable-2fa-btn');
        if (enableBtn) enableBtn.addEventListener('click', startTwoFaSetup);

        var disableBtn = qs('#disable-2fa-btn');
        if (disableBtn) {
            disableBtn.addEventListener('click', function () {
                App.confirmDialog({
                    title: 'Disable two-factor authentication',
                    message: 'Two-factor authentication will be turned off. This reduces account security.',
                    confirmLabel: 'Disable',
                    danger: true
                }).then(function (yes) {
                    if (!yes) return;
                    App.apiJson('/api/auth.php?action=disable_2fa', {})
                        .then(function (data) {
                            App.toast(data.message || '2FA disabled', 'success');
                            setTimeout(function () { window.location.reload(); }, 700);
                        })
                        .catch(function (err) { App.toast(err.message || 'Failed to disable 2FA', 'error'); });
                });
            });
        }
    }

    function startTwoFaSetup() {
        var btn = qs('#enable-2fa-btn');
        App.busyButton(btn, true);
        App.apiJson('/api/auth.php?action=setup_2fa', {})
            .then(function (data) { renderTwoFaSetup(data); })
            .catch(function (err) { App.toast(err.message || 'Failed to start 2FA setup', 'error'); })
            .then(function () { App.busyButton(btn, false); });
    }

    function renderTwoFaSetup(data) {
        var secret = data.secret || '';
        var qr = data.qrCodeUrl || '';

        var html =
            '<p style="font-size:0.86rem;color:var(--muted);">Scan this code with an authenticator app (Google Authenticator, Authy, 1Password, etc.).</p>' +
            '<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">' +
            (qr ? '<img src="' + esc(qr) + '" alt="2FA QR code" style="width:180px;height:180px;border:1px solid var(--border);border-radius:var(--radius-sm);">' : '') +
            '<div style="min-width:220px;flex:1;">' +
            '<div class="field"><label for="twofa-secret">Manual entry key</label>' +
            '<input type="text" id="twofa-secret" readonly value="' + esc(secret) + '" style="font-family:monospace;letter-spacing:1px;"></div>' +
            '<div class="hint" style="color:var(--faint);font-size:0.74rem;">If you cannot scan the code, enter this key manually.</div>' +
            '</div></div>' +
            '<div class="field" style="margin-top:4px;"><label for="twofa-code">Verification code</label>' +
            '<input type="text" id="twofa-code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="6-digit code" style="max-width:220px;"></div>';

        qs('#twofa-modal-title').textContent = 'Enable two-factor authentication';
        qs('#twofa-modal-body').innerHTML = html;
        qs('#twofa-modal-foot').innerHTML =
            '<button type="button" class="btn btn-outline" data-close-modal>Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="twofa-verify-btn"><span class="spinner-inline"></span><i class="fa-solid fa-shield-halved btn-icon" aria-hidden="true"></i> Verify and enable</button>';

        App.openModal('twofa-modal');
        qs('#twofa-verify-btn').addEventListener('click', function () {
            verifyTwoFa(qs('#twofa-code').value);
        });
    }

    function verifyTwoFa(code) {
        if (!code) { App.toast('Enter the verification code', 'error'); return; }
        var btn = qs('#twofa-verify-btn');
        App.busyButton(btn, true);
        App.apiJson('/api/auth.php?action=enable_2fa', { code: code })
            .then(function (data) { renderTwoFaBackupCodes(data.backupCodes || []); })
            .catch(function (err) { App.toast(err.message || 'Verification failed', 'error'); })
            .then(function () { App.busyButton(btn, false); });
    }

    function renderTwoFaBackupCodes(codes) {
        var list = codes.map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('');
        qs('#twofa-modal-title').textContent = 'Two-factor authentication enabled';
        qs('#twofa-modal-body').innerHTML =
            '<p style="font-size:0.86rem;">2FA is now active. Save these backup codes somewhere safe. Each can be used once if you lose access to your authenticator app.</p>' +
            '<div class="panel" style="background:var(--surface-alt);"><div class="panel-body"><ul style="font-family:monospace;margin:0;padding-left:22px;">' + list + '</ul></div></div>';
        qs('#twofa-modal-foot').innerHTML =
            '<button type="button" class="btn btn-primary" id="twofa-done-btn">Done</button>';

        qs('#twofa-done-btn').addEventListener('click', function () { window.location.reload(); });
    }

    /* --- Account (change password) ------------------------------------------ */

    function initAccount() {
        var form = qs('#change-password-form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var current = qs('#cp-current').value;
            var next = qs('#cp-new').value;
            var confirm = qs('#cp-confirm').value;

            if (next.length < 8) { App.toast('New password must be at least 8 characters', 'error'); return; }
            if (next !== confirm) { App.toast('New passwords do not match', 'error'); return; }

            var btn = qs('#change-password-btn');
            App.busyButton(btn, true);
            App.apiJson('/api/auth.php?action=change_password', { current_password: current, new_password: next, confirm_password: confirm })
                .then(function (data) {
                    App.toast(data.message || 'Password changed', 'success');
                    form.reset();
                })
                .catch(function (err) { App.toast(err.message || 'Password change failed', 'error'); })
                .then(function () { App.busyButton(btn, false); });
        });
    }


    /* --- Boot ------------------------------------------------------------------ */

    function wireDataGoto() {
        qsa('[data-goto]').forEach(function (node) {
            node.addEventListener('click', function () {
                App.switchToSection(node.getAttribute('data-goto'));
            });
        });
    }

    function initQuickActions() {
        var addItem = qs('#quick-add-item');
        if (addItem) {
            addItem.addEventListener('click', function () {
                App.switchToSection('menu');
                openItemEditor(null, null, null);
            });
        }
    }

    App.onBoot = function () {
        // Delegated modal-close handling for dynamically injected buttons.
        document.addEventListener('click', function (e) {
            var closeBtn = e.target && e.target.closest ? e.target.closest('[data-close-modal]') : null;
            if (closeBtn) {
                var overlay = closeBtn.closest('.modal-overlay');
                if (overlay) App.closeModal(overlay);
            }
        });

        wireDataGoto();
        initQuickActions();
        initReservations();
        initMenu();
        initSettings();
        initNotifications();
        initSecurity();
        initAccount();
    };
})();
