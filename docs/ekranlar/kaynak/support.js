/*
 * Taslak okuyucu — docs/ekranlar/kaynak/*.dc.html dosyalarını tarayıcıda yerel olarak gösterir.
 * Yalnız bu taslaklarda kullanılan alt kümeyi destekler: {{yol}} boşlukları, <sc-for>, <sc-if>,
 * onClick bağları ve DCLogic durum (state) sınıfı.
 * Kullanım: bu klasörde `php -S 127.0.0.1:8000` çalıştırıp tarayıcıda dosyayı açın.
 */
(function () {
  class DCLogic {
    constructor(props) { this.props = props || {}; this.state = {}; }
    setState(patch) { Object.assign(this.state, typeof patch === 'function' ? patch(this.state) : patch); this.__render(); }
    forceUpdate() { this.__render(); }
  }
  window.DCLogic = DCLogic;

  const HOLE = /\{\{\s*([^}]+?)\s*\}\}/g;

  function lookup(path, scope) {
    path = path.trim();
    if (path === 'true') return true;
    if (path === 'false') return false;
    if (/^-?\d+(\.\d+)?$/.test(path)) return Number(path);
    return path.split('.').reduce((v, k) => (v == null ? undefined : v[k]), scope);
  }

  function interpolate(text, scope) {
    return text.replace(HOLE, (_, p) => { const v = lookup(p, scope); return v == null ? '' : String(v); });
  }

  function renderChildren(parent, target, scope) {
    for (const child of Array.from(parent.childNodes)) renderNode(child, target, scope);
  }

  function renderNode(node, target, scope) {
    if (node.nodeType === Node.TEXT_NODE) { target.appendChild(document.createTextNode(interpolate(node.textContent, scope))); return; }
    if (node.nodeType !== Node.ELEMENT_NODE) return;
    if (node.tagName === 'TEMPLATE' && node.hasAttribute('data-sc-for')) {
      const list = lookup(node.getAttribute('list').replace(HOLE, '$1'), scope) || [];
      const as = node.getAttribute('as') || 'item';
      list.forEach((item, i) => renderChildren(node.content, target, Object.assign({}, scope, { [as]: item, $index: i })));
      return;
    }
    if (node.tagName === 'TEMPLATE' && node.hasAttribute('data-sc-if')) {
      if (lookup(node.getAttribute('value').replace(HOLE, '$1'), scope)) renderChildren(node.content, target, scope);
      return;
    }
    const ns = node.namespaceURI;
    const el = ns && ns !== 'http://www.w3.org/1999/xhtml' ? document.createElementNS(ns, node.localName) : document.createElement(node.localName);
    for (const attr of Array.from(node.attributes)) {
      const whole = attr.value.match(/^\s*\{\{\s*([^}]+?)\s*\}\}\s*$/);
      if (/^on[A-Z]/.test(attr.name) || /^on[a-z]+$/.test(attr.name) && whole) {
        const fn = whole ? lookup(whole[1], scope) : null;
        if (typeof fn === 'function') el.addEventListener(attr.name.slice(2).toLowerCase(), fn);
        continue;
      }
      el.setAttribute(attr.name, interpolate(attr.value, scope));
    }
    renderChildren(node, el, scope);
    target.appendChild(el);
  }

  async function boot() {
    const raw = await (await fetch(location.href, { cache: 'no-store' })).text();
    const prepared = raw
      .replace(/<sc-for\b/g, '<template data-sc-for').replace(/<\/sc-for>/g, '</template>')
      .replace(/<sc-if\b/g, '<template data-sc-if').replace(/<\/sc-if>/g, '</template>');
    const doc = new DOMParser().parseFromString(prepared, 'text/html');
    const xdc = doc.querySelector('x-dc');
    const helmet = xdc.querySelector('helmet');
    if (helmet) { for (const n of Array.from(helmet.children)) document.head.appendChild(document.importNode(n, true)); helmet.remove(); }
    const script = doc.querySelector('script[data-dc-script]');
    const Component = new Function('DCLogic', script.textContent + '\nreturn Component;')(DCLogic);
    const inst = new Component({});
    const mount = document.createElement('div');
    document.body.innerHTML = '';
    document.body.appendChild(mount);
    inst.__render = () => {
      const frag = document.createDocumentFragment();
      renderChildren(xdc, frag, inst.renderVals());
      mount.replaceChildren(frag);
    };
    inst.__render();
    document.documentElement.setAttribute('data-ready', '1');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
