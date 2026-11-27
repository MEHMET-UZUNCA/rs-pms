/*
 * RS PMS ekran davranışları (bağımlılık yok):
 *  - Fidelio kısayolları: data-hotkey="alt+n" olan öğe tuşa basılınca tıklanır ("escape", "delete", "enter" da olur).
 *  - Menü çubuğu: tıkla aç, dışarı tıkla / Esc kapat; açıkken fareyle diğer menüye geçilir.
 *  - Liste (data-grid): tıkla seç, ↑/↓ gez, Enter / çift tık düzenle; seçime bağlı düğmeler (data-needs-selection) seçili satırın
 *    adreslerini (data-url-*) kullanır. Hızlı arama (data-grid-filter) satırları anında süzer.
 *  - Onay: data-confirm="…" olan form gönderilmeden önce sorar.
 */
// Arayüz dili <html lang> özniteliğinden; ekranlardaki metinler sunucuda lang/<dil>/pms.php'den gelir, burada yalnız betik mesajları var.
const LANG = document.documentElement.lang === 'tr' ? 'tr' : 'en';
const T = {
    en: { notLoaded: 'Information could not be loaded.', connection: 'Connection error.' },
    tr: { notLoaded: 'Bilgi alınamadı.', connection: 'Bağlantı hatası.' },
}[LANG];

const isTyping = (el) => el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));

function keyName(e) {
    const parts = [];
    if (e.ctrlKey) parts.push('ctrl');
    if (e.altKey) parts.push('alt');
    if (e.shiftKey && (e.key.length > 1 || e.ctrlKey || e.altKey)) parts.push('shift');
    let key = e.key.toLowerCase();
    if ((e.altKey || e.ctrlKey) && e.code?.startsWith('Key')) key = e.code.slice(3).toLowerCase(); // Alt/Ctrl+harf: klavye düzeninden bağımsız (Türkçe ı/i)
    if (e.code?.startsWith('Digit') && (e.altKey || e.ctrlKey)) key = e.code.slice(5);
    parts.push(key === ' ' ? 'space' : key);
    return parts.join('+');
}

document.addEventListener('keydown', (e) => {
    const name = keyName(e);
    const modal = document.querySelector('[data-modal]:not([hidden])');   // açık pencere varken yalnız onun kısayolları
    const matches = [...(modal ?? document).querySelectorAll('[data-hotkey]')].filter((el) => el.dataset.hotkey.split(' ').includes(name) && !el.disabled);
    // Önce ekrandaki görünür düğme, yoksa genel kısayol (Quick Keys)
    const target = matches.find((el) => el.offsetParent !== null && !el.hasAttribute('data-hotkey-global')) ?? matches.find((el) => el.hasAttribute('data-hotkey-global'));
    if (!target) return;
    if (name === 'ctrl+a' && isTyping(document.activeElement) && target.hasAttribute('data-hotkey-global')) return; // yazarken Ctrl+A: tümünü seç
    const plain = !e.altKey && !e.ctrlKey && !['escape', 'f1', 'f2', 'f3', 'f4', 'f5', 'f6', 'f7', 'f8', 'f9', 'f10', 'f11', 'f12'].includes(name);
    if (plain && isTyping(document.activeElement)) return; // Delete/Enter yazarken alana aittir
    if (name === 'escape' && document.querySelector('.menu-root.is-open')) return;
    e.preventDefault();
    target.focus?.();
    target.click();
});

// Pencere (data-modal): data-modal-open="id" açar, data-modal-close kapatır; açılınca [data-autofocus] odaklanır.
// select[data-submit-on-enter]: Enter ya da çift tık formu gönderir (ör. Select Profile Type).
document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-modal-open]');
    if (open) {
        const modal = document.getElementById(open.dataset.modalOpen);
        modal.hidden = false;
        modal.querySelector('[data-autofocus]')?.focus();
    }
    const close = e.target.closest('[data-modal-close]');
    if (close) close.closest('[data-modal]').hidden = true;
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.matches?.('select[data-submit-on-enter]')) { e.preventDefault(); e.target.form.requestSubmit(); }
});
document.addEventListener('dblclick', (e) => {
    const select = e.target.closest('select[data-submit-on-enter]');
    if (select) select.form.requestSubmit();
});

// Menü çubuğu
document.addEventListener('click', (e) => {
    const root = e.target.closest('.menu-root');
    const opened = document.querySelector('.menu-root.is-open');
    if (opened && opened !== root) opened.classList.remove('is-open');
    if (root && e.target.closest('.menu-item')) root.classList.toggle('is-open');
});
document.addEventListener('mouseover', (e) => {
    const root = e.target.closest('.menu-root');
    const opened = document.querySelector('.menu-root.is-open');
    if (root && opened && opened !== root) { opened.classList.remove('is-open'); root.classList.add('is-open'); }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelector('.menu-root.is-open')?.classList.remove('is-open');
});

// Liste
function initGrid(grid) {
    const rows = () => [...grid.querySelectorAll('tbody tr[data-id]')].filter((r) => !r.hidden);
    const buttons = [...document.querySelectorAll(`[data-needs-selection="${grid.id}"]`)];

    // Seçili satırın alt paneli (data-detail-target) sayfa yenilenmeden yüklenir (data-url-detail).
    const detail = grid.dataset.detailTarget ? document.querySelector(grid.dataset.detailTarget) : null;
    let detailTimer = null;
    let detailFor = null;
    function loadDetail(row) {
        if (!detail) return;
        const url = row?.dataset.urlDetail;
        if (!url) { detail.innerHTML = detail.dataset.empty ?? ''; detailFor = null; return; }
        if (detailFor === url) return;
        detailFor = url;
        clearTimeout(detailTimer);
        detailTimer = setTimeout(async () => {
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (detailFor === url) detail.innerHTML = res.ok ? await res.text() : `<p class="p-3 text-danger">${T.notLoaded}</p>`;
            } catch { if (detailFor === url) detail.innerHTML = `<p class="p-3 text-danger">${T.connection}</p>`; }
        }, 120);
    }

    function select(row, scroll = true) {
        grid.querySelectorAll('tr.is-selected').forEach((r) => r.classList.remove('is-selected'));
        if (row) {
            row.classList.add('is-selected');
            if (scroll) row.scrollIntoView({ block: 'nearest' });
        }
        loadDetail(row);
        buttons.forEach((b) => {
            const url = row?.dataset[`url${b.dataset.action[0].toUpperCase()}${b.dataset.action.slice(1)}`];
            if (b.tagName === 'A') { if (url) b.href = url; else b.removeAttribute('href'); }
            if (b.form && url) b.form.action = url;
            b.disabled = !url;
            b.classList.toggle('pointer-events-none', !url);
            b.classList.toggle('opacity-40', !url);
        });
    }

    grid.addEventListener('click', (e) => { const r = e.target.closest('tr[data-id]'); if (r) select(r, false); });
    grid.addEventListener('dblclick', (e) => { const r = e.target.closest('tr[data-id]'); if (r?.dataset.urlEdit) location.href = r.dataset.urlEdit; });
    document.addEventListener('keydown', (e) => {
        if (e.altKey || e.ctrlKey || document.querySelector('.menu-root.is-open')) return;
        const filterFocused = document.activeElement?.dataset.gridFilter === grid.id;
        if (isTyping(document.activeElement) && !filterFocused) return;
        const list = rows();
        const current = grid.querySelector('tr.is-selected');
        const i = list.indexOf(current);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const next = e.key === 'ArrowDown' ? list[Math.min(i + 1, list.length - 1)] : list[Math.max(i - 1, 0)];
            select(next ?? list[0]);
        } else if (e.key === 'Enter' && current?.dataset.urlEdit && !filterFocused) {
            e.preventDefault();
            location.href = current.dataset.urlEdit;
        }
    });

    const filter = document.querySelector(`[data-grid-filter="${grid.id}"]`);
    filter?.addEventListener('input', () => {
        const q = filter.value.trim().toLocaleLowerCase('tr');
        grid.querySelectorAll('tbody tr[data-id]').forEach((r) => { r.hidden = q !== '' && !r.textContent.toLocaleLowerCase('tr').includes(q); });
        const empty = grid.querySelector('[data-grid-empty]');
        if (empty) empty.hidden = rows().length > 0;
        const cur = grid.querySelector('tr.is-selected');
        if (!cur || cur.hidden) select(rows()[0] ?? null, false);
    });
    filter?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); filter.form?.requestSubmit(); } });

    const initial = grid.querySelector(`tr[data-id="${grid.dataset.selected}"]`) ?? rows()[0] ?? null;
    select(initial);
}
document.querySelectorAll('[data-grid]').forEach(initGrid);

document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm ?? e.submitter?.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
});

// Saat (durum çubuğu)
const clock = document.querySelector('[data-clock]');
if (clock) {
    const tick = () => { clock.textContent = new Date().toLocaleTimeString(LANG === 'tr' ? 'tr-TR' : 'en-GB', { hour: '2-digit', minute: '2-digit' }); };
    tick();
    setInterval(tick, 15000);
}

// Outlet formu: kod listesi süzme, "yalnız seçilenler", varsayılan kod seçeneklerini seçili kodlarla eşitleme
const codeList = document.querySelector('[data-code-list]');
if (codeList) {
    const filter = document.querySelector('[data-code-filter]');
    const onlySelected = document.querySelector('[data-code-only-selected]');
    const counter = document.querySelector('[data-code-count]');
    const defaultSelect = document.querySelector('[data-default-code]');
    const apply = () => {
        const q = (filter?.value ?? '').trim().toLocaleLowerCase('tr');
        codeList.querySelectorAll('tr[data-code]').forEach((r) => {
            const box = r.querySelector('input[type=checkbox]');
            r.hidden = (q !== '' && !r.textContent.toLocaleLowerCase('tr').includes(q)) || (onlySelected?.checked && !box.checked);
            r.classList.toggle('is-selected', box.checked);
        });
        const checked = [...codeList.querySelectorAll('input[type=checkbox]:checked')];
        if (counter) counter.textContent = checked.length;
        if (defaultSelect) {
            const current = defaultSelect.value;
            defaultSelect.querySelectorAll('option[value]:not([value=""])').forEach((o) => o.remove());
            checked.forEach((box) => {
                const o = new Option(`${box.dataset.no} ${box.dataset.name}`, box.value);
                defaultSelect.add(o);
            });
            defaultSelect.value = checked.some((b) => b.value === current) ? current : '';
        }
    };
    codeList.addEventListener('change', apply);
    filter?.addEventListener('input', apply);
    filter?.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
    onlySelected?.addEventListener('change', apply);
    apply();
}

// Satır ekle / sil (ör. departman kodunun tarihli vergi satırları): data-rows tablosu, data-row-template şablonu (__i__ yerine sıra no)
document.addEventListener('click', (e) => {
    const add = e.target.closest('[data-row-add]');
    if (add) {
        const table = document.querySelector(`[data-rows="${add.dataset.rowAdd}"]`);
        const index = Number(table.dataset.nextIndex || 0);
        table.dataset.nextIndex = index + 1;
        const html = table.querySelector('template[data-row-template]').innerHTML.replaceAll('__i__', index);
        table.tBodies[0].insertAdjacentHTML('beforeend', html);
        table.tBodies[0].lastElementChild.querySelector('select, input')?.focus();
    }
    const remove = e.target.closest('[data-row-remove]');
    if (remove) remove.closest('[data-row]').remove();
});

// Sekmeler (Rezervasyon Kartı: Ctrl+1 … Ctrl+0): [data-tabs] içinde data-tab-button / data-tab-panel. Hatalı alan varsa onun sekmesi açılır.
document.querySelectorAll('[data-tabs]').forEach((root) => {
    const buttons = [...root.querySelectorAll('[data-tab-button]')];
    const show = (id) => {
        buttons.forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tabButton === id)));
        root.querySelectorAll('[data-tab-panel]').forEach((p) => { p.hidden = p.dataset.tabPanel !== id; });
        try { sessionStorage.setItem('tab:' + location.pathname, id); } catch {}
    };
    buttons.forEach((b) => b.addEventListener('click', () => show(b.dataset.tabButton)));
    const invalid = root.querySelector('.input-error')?.closest('[data-tab-panel]')?.dataset.tabPanel;
    let saved = null;
    try { saved = sessionStorage.getItem('tab:' + location.pathname); } catch {}
    show(invalid ?? (buttons.some((b) => b.dataset.tabButton === saved) ? saved : buttons[0]?.dataset.tabButton));
});

// Oda listesi seçili oda tipine göre süzülür
const roomType = document.querySelector('[data-room-type]');
const roomSelect = document.querySelector('[data-room-select]');
if (roomType && roomSelect) {
    const filterRooms = () => {
        roomSelect.querySelectorAll('option[data-type]').forEach((o) => { o.hidden = o.dataset.type !== roomType.value; });
        if (roomSelect.selectedOptions[0]?.hidden) roomSelect.value = '';
    };
    roomType.addEventListener('change', filterRooms);
    filterRooms();
}

// Profil arama: soyad yazılır, var olan profil seçilirse yeni misafir alanları gizlenir
const profileSearch = document.querySelector('[data-profile-search]');
if (profileSearch) {
    const results = document.querySelector('[data-profile-results]');
    const idInput = document.querySelector('[data-profile-id]');
    const chosen = document.querySelector('[data-profile-chosen]');
    const newGuest = document.querySelectorAll('[data-new-guest], [data-new-guest-label]');
    let timer;
    // Kara listedeki misafir kırmızı ve nedeniyle gösterilir (Fidelio uyarısı); rezervasyon engellenmez.
    const label = (p) => `${p.display_name}${p.nationality_code ? ' · ' + p.nationality_code : ''}${p.status === 'blacklisted' ? ` — ${profileSearch.dataset.blacklisted}: ${p.blacklist_reason ?? ''}` : ''}`;
    const choose = (p) => {
        idInput.value = p ? p.id : '';
        chosen.textContent = p ? label(p) : '';
        chosen.classList.toggle('hidden', !p);
        chosen.classList.toggle('!text-danger', p?.status === 'blacklisted');
        newGuest.forEach((el) => el.classList.toggle('hidden', !!p));
        results.classList.add('hidden');
    };
    profileSearch.addEventListener('input', () => {
        clearTimeout(timer);
        if (idInput.value) choose(null);
        const q = profileSearch.value.trim();
        if (q.length < 2) { results.classList.add('hidden'); return; }
        timer = setTimeout(async () => {
            const res = await fetch(`${profileSearch.dataset.profileSearch}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
            const list = res.ok ? await res.json() : [];
            results.replaceChildren(...list.map((p) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'block w-full text-left px-2 py-1 hover:bg-brand-50' + (p.status === 'blacklisted' ? ' text-danger font-semibold' : '');
                b.textContent = label(p);
                b.addEventListener('click', () => { choose(p); profileSearch.value = p.display_name; });
                return b;
            }));
            results.classList.toggle('hidden', list.length === 0);
        }, 200);
    });
}

// Fixed Charges: kod seçilince varsayılan fiyatı tutara yaz (tutar boşsa)
document.querySelector('[data-fixed-code]')?.addEventListener('change', (e) => {
    const amount = document.querySelector('[data-fixed-amount]');
    const price = e.target.selectedOptions[0]?.dataset.price;
    if (amount && price && !amount.value) amount.value = String(Number(price)).replace('.', LANG === 'tr' ? ',' : '.');
});

// Oda planı (Room Rack): çubuğa tıkla → işlem menüsü (kart, folyo, giriş, çıkış); çubuğu sürükle → başka oda / tarih;
// sağ kenarı çek → çıkış tarihi; boş hücrelerde sürükle → o oda ve tarihlerle yeni rezervasyon. Değişiklikten önce onay sorulur.
const rack = document.querySelector('[data-rack]');
if (rack) {
    const menu = document.getElementById('rack-menu');
    const start = Date.parse(rack.dataset.start + 'T00:00:00Z');
    const DAY = 86400000;
    const iso = (i) => new Date(start + i * DAY).toISOString().slice(0, 10);
    const shift = (isoDate, days) => new Date(Date.parse(isoDate + 'T00:00:00Z') + days * DAY).toISOString().slice(0, 10);
    const dmy = (isoDate) => isoDate.split('-').reverse().join('.');
    const canMove = rack.hasAttribute('data-can-move');
    let menuBar = null;
    let suppressClick = false;

    const closeMenu = () => { menu.hidden = true; menuBar = null; };
    const runAction = (bar, action) => {
        const url = JSON.parse(bar.dataset.actions || '{}')[action];
        if (!url) return;
        if (action === 'checkin') {
            const f = document.getElementById('rack-checkin-form');
            f.action = url;
            f.submit();
        } else {
            location.href = url;
        }
    };
    const openMenu = (bar, x, y) => {
        const actions = JSON.parse(bar.dataset.actions || '{}');
        menu.querySelectorAll('[data-menu-action]').forEach((a) => { a.hidden = !actions[a.dataset.menuAction]; });
        menu.hidden = false;
        menu.style.left = Math.min(x, window.innerWidth - menu.offsetWidth - 8) + 'px';
        menu.style.top = Math.min(y, window.innerHeight - menu.offsetHeight - 8) + 'px';
        menuBar = bar;
        menu.querySelector('[data-menu-action]:not([hidden])')?.focus();
    };
    menu.addEventListener('click', (e) => {
        const a = e.target.closest('[data-menu-action]');
        if (!a || !menuBar) return;
        e.preventDefault();
        const bar = menuBar;
        closeMenu();
        runAction(bar, a.dataset.menuAction);
    });
    document.addEventListener('click', (e) => { if (!menu.hidden && !menu.contains(e.target) && !e.target.closest('[data-bar]')) closeMenu(); });
    // Menü açıkken Esc / harf kısayolları sayfanın kısayollarından önce gelir
    document.addEventListener('keydown', (e) => {
        const bar = menuBar ?? (document.activeElement?.matches?.('[data-bar]') ? document.activeElement : null);
        if (!bar || e.ctrlKey || e.altKey) return;
        const key = { Enter: 'card', b: 'folio', g: 'checkin', o: 'checkout' }[e.key.length === 1 ? e.key.toLowerCase() : e.key];
        if (e.key === 'Escape' && menuBar) { e.preventDefault(); e.stopImmediatePropagation(); closeMenu(); bar.focus(); return; }
        if (key === 'card' && !menuBar) { e.preventDefault(); e.stopImmediatePropagation(); const r = bar.getBoundingClientRect(); openMenu(bar, r.left, r.bottom); return; }
        if (key && JSON.parse(bar.dataset.actions || '{}')[key]) { e.preventDefault(); e.stopImmediatePropagation(); closeMenu(); runAction(bar, key); }
    }, true);
    rack.addEventListener('click', (e) => {
        const bar = e.target.closest('[data-bar]');
        if (!bar || suppressClick) { suppressClick = false; return; }
        if (e.target.closest('[data-resize]')) return;
        openMenu(bar, e.clientX, e.clientY);
    });

    // Sürükleme
    const cellAt = (x, y) => document.elementsFromPoint(x, y).find((el) => el.matches?.('[data-cell]'));
    let marked = [];
    const mark = (row, from, to, bad = false) => {
        marked.forEach((c) => c.classList.remove('!bg-brand-200', '!bg-danger/20'));
        marked = [];
        if (!row) return;
        row.querySelectorAll('[data-cell]').forEach((c) => {
            const i = Number(c.dataset.cell);
            if (i >= from && i <= to) { c.classList.add(bad ? '!bg-danger/20' : '!bg-brand-200'); marked.push(c); }
        });
    };
    let drag = null;
    rack.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || !canMove) return;
        const bar = e.target.closest('[data-bar]');
        const cell = cellAt(e.clientX, e.clientY);
        if (!cell) return;
        const row = cell.closest('[data-room-id]');
        if (bar) {
            drag = { kind: e.target.closest('[data-resize]') ? 'resize' : 'move', bar, row, grab: Number(cell.dataset.cell), x: e.clientX, y: e.clientY, active: false };
        } else if (!e.target.closest('[data-kind]')) {
            drag = { kind: 'new', row, grab: Number(cell.dataset.cell), x: e.clientX, y: e.clientY, active: true };
            mark(row, drag.grab, drag.grab);
        }
    });
    rack.addEventListener('pointermove', (e) => {
        if (!drag) return;
        if (!drag.active && Math.abs(e.clientX - drag.x) + Math.abs(e.clientY - drag.y) < 6) return;
        drag.active = true;
        if (drag.bar) drag.bar.style.pointerEvents = 'none';
        const cell = cellAt(e.clientX, e.clientY);
        if (!cell) return;
        const row = drag.kind === 'move' ? cell.closest('[data-room-id]') : drag.row;
        const i = Number(cell.dataset.cell);
        drag.target = { row, i };
        if (drag.kind === 'new') {
            mark(row, Math.min(drag.grab, i), Math.max(drag.grab, i));
        } else {
            const a = Math.round((Date.parse(drag.bar.dataset.arrival + 'T00:00:00Z') - start) / DAY);
            const d = Math.round((Date.parse(drag.bar.dataset.departure + 'T00:00:00Z') - start) / DAY);
            const delta = i - drag.grab;
            if (drag.kind === 'move') mark(row, a + delta, d + delta - 1, drag.bar.dataset.status === 'in_house' && delta !== 0);
            else mark(row, a, Math.max(a, i));
        }
    });
    const endDrag = (e) => {
        if (!drag) return;
        const d = drag;
        drag = null;
        if (d.bar) d.bar.style.pointerEvents = '';
        mark(null);
        if (d.kind === 'new') {
            const i = d.target ? d.target.i : d.grab;
            const from = Math.min(d.grab, i);
            const to = Math.max(d.grab, i);
            const url = new URL(rack.dataset.newUrl, location.href);
            url.searchParams.set('oda', d.row.dataset.roomId);
            url.searchParams.set('gelis', dmy(iso(from)));
            url.searchParams.set('cikis', dmy(iso(to + 1)));
            location.href = url;
            return;
        }
        if (!d.active || !d.target || e.type === 'pointercancel') return;
        suppressClick = true;
        const delta = d.target.i - d.grab;
        let arrival = d.bar.dataset.arrival;
        let departure = d.bar.dataset.departure;
        let room = d.row;
        if (d.kind === 'move') {
            arrival = shift(arrival, delta);
            departure = shift(departure, delta);
            room = d.target.row;
        } else {
            departure = iso(d.target.i + 1);
            if (departure <= arrival) departure = shift(arrival, 1);
        }
        if (room === d.row && arrival === d.bar.dataset.arrival && departure === d.bar.dataset.departure) return;
        const msg = (d.kind === 'move' ? rack.dataset.msgMove : rack.dataset.msgResize)
            .replace(':room', room.dataset.room).replace(':from', dmy(arrival)).replace(':to', dmy(departure));
        if (!confirm(msg)) return;
        const f = document.getElementById('rack-move-form');
        f.action = d.bar.dataset.moveUrl;
        f.room_id.value = room.dataset.roomId;
        f.arrival_date.value = arrival;
        f.departure_date.value = departure;
        f.submit();
    };
    rack.addEventListener('pointerup', endDrag);
    rack.addEventListener('pointercancel', endDrag);
}

// Satır işlem menüsü (kontrol paneli): data-row-actions satırına tıkla ya da Enter → menü; harf kısayolları (A oda, G giriş, B folyo, O çıkış, L mektup, N navigatör).
const rowMenu = document.getElementById('row-menu');
if (rowMenu) {
    const POST = ['checkin'];
    const KEYS = { Enter: 'card', a: 'room', g: 'checkin', b: 'folio', o: 'checkout', l: 'letter', n: 'navigator' };
    let current = null;
    const close = () => { rowMenu.hidden = true; current = null; };
    const run = (row, action) => {
        const url = JSON.parse(row.dataset.rowActions || '{}')[action];
        if (!url) return;
        if (POST.includes(action)) { const f = document.getElementById('row-menu-post'); f.action = url; f.submit(); } else location.href = url;
    };
    const open = (row, x, y) => {
        const actions = JSON.parse(row.dataset.rowActions || '{}');
        rowMenu.querySelectorAll('[data-menu-action]').forEach((a) => { a.hidden = !actions[a.dataset.menuAction]; });
        rowMenu.hidden = false;
        rowMenu.style.left = Math.max(8, Math.min(x, window.innerWidth - rowMenu.offsetWidth - 8)) + 'px';
        rowMenu.style.top = Math.max(8, Math.min(y, window.innerHeight - rowMenu.offsetHeight - 8)) + 'px';
        current = row;
        rowMenu.querySelector('[data-menu-action]:not([hidden])')?.focus();
    };
    document.addEventListener('click', (e) => {
        const item = e.target.closest('#row-menu [data-menu-action]');
        if (item && current) { e.preventDefault(); const row = current; close(); run(row, item.dataset.menuAction); return; }
        const row = e.target.closest('[data-row-actions]');
        if (row && !e.target.closest('a, button')) { open(row, e.clientX, e.clientY); return; }
        if (!rowMenu.contains(e.target)) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.altKey) return;
        const row = current ?? (document.activeElement?.matches?.('[data-row-actions]') ? document.activeElement : null);
        if (!row) return;
        if (e.key === 'Escape' && current) { e.preventDefault(); e.stopImmediatePropagation(); close(); row.focus(); return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (current) {
                const items = [...rowMenu.querySelectorAll('[data-menu-action]:not([hidden])')];
                const i = items.indexOf(document.activeElement);
                items[(i + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length]?.focus();
            } else {
                const rows = [...row.closest('tbody').querySelectorAll('[data-row-actions]')];
                rows[rows.indexOf(row) + (e.key === 'ArrowDown' ? 1 : -1)]?.focus();
            }
            e.preventDefault();
            return;
        }
        const action = KEYS[e.key.length === 1 ? e.key.toLowerCase() : e.key];
        if (!action) return;
        if (action === 'card' && current && document.activeElement?.matches('[data-menu-action]')) return;   // menüde Enter: seçili madde
        e.preventDefault();
        e.stopImmediatePropagation();
        if (action === 'card' && !current) { const r = row.getBoundingClientRect(); open(row, r.left + 40, r.bottom); return; }
        close();
        run(row, action);
    }, true);
}

// Sıralanabilir liste (navigatör kolonları): ↑ / ↓ düğmeleri satırı taşır
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-sortable] [data-move]');
    if (!btn) return;
    const li = btn.closest('li');
    if (btn.dataset.move === 'up' && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
    if (btn.dataset.move === 'down' && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
    btn.focus();
});

// Tümünü seç (Kat Hizmetleri oda listesi)
document.querySelectorAll('[data-check-all]').forEach((all) => all.addEventListener('change', () => {
    all.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach((box) => { box.checked = all.checked; });
}));
