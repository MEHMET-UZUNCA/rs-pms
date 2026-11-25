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
    if (e.shiftKey && e.key.length > 1) parts.push('shift');
    let key = e.key.toLowerCase();
    if (e.altKey && e.code?.startsWith('Key')) key = e.code.slice(3).toLowerCase(); // Alt+harf: klavye düzeninden bağımsız
    if (e.code?.startsWith('Digit') && (e.altKey || e.ctrlKey)) key = e.code.slice(5);
    parts.push(key === ' ' ? 'space' : key);
    return parts.join('+');
}

document.addEventListener('keydown', (e) => {
    const name = keyName(e);
    const target = [...document.querySelectorAll('[data-hotkey]')].find((el) =>
        el.dataset.hotkey.split(' ').includes(name) && !el.disabled && el.offsetParent !== null);
    if (!target) return;
    const plain = !e.altKey && !e.ctrlKey && !['escape', 'f1', 'f2', 'f3', 'f4', 'f5', 'f6', 'f7', 'f8', 'f9', 'f10', 'f11', 'f12'].includes(name);
    if (plain && isTyping(document.activeElement)) return; // Delete/Enter yazarken alana aittir
    if (name === 'escape' && document.querySelector('.menu-root.is-open')) return;
    e.preventDefault();
    target.focus?.();
    target.click();
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
