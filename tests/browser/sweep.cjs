const { chromium } = require('playwright');
const urls = ['/', '/rezervasyon', '/rezervasyon?f=gelecek', '/rezervasyon/yeni', '/rezervasyon/1', '/folyo/rezervasyon/1', '/oda-plani', '/musaitlik', '/bloklar',
  '/profiller', '/profiller/yeni/individual', '/kat-hizmetleri', '/house-status', '/arizali-odalar', '/oda-gecmisi', '/bekleyen-odalar', '/kayip-esya',
  '/istekler', '/oda-degisimleri', '/kasa', '/ogun-sayilari', '/gece-kapanisi', '/kisayollar', '/ayarlar/fiyat-kodlari', '/ayarlar/doviz-kurlari',
  '/ayarlar/departman-kodlari', '/ayarlar/outletler', '/ayarlar/kullanicilar', '/ayarlar/roller', '/ayarlar/rezervasyon-secenekleri',
  '/rezervasyon?f=ileri&grup=room_type', '/kat-hizmetleri/mobil', '/raporlar', '/raporlar?r=forecast&goster=1', '/raporlar?r=in_house&goster=1', '/raporlar?r=journal&goster=1'];
(async () => {
  const b = await chromium.launch();
  for (const [w, h] of [[1280, 720], [1366, 768], [1920, 1080]]) {
    const p = await b.newPage({ viewport: { width: w, height: h } });
    const errors = [];
    p.on('pageerror', (e) => errors.push(e.message));
    await p.goto((process.env.PMS_URL || 'http://127.0.0.1:8000') + '/giris'); await p.fill('[name=login]',process.env.PMS_USER); await p.fill('[name=password]',process.env.PMS_PASS); await p.press('[name=password]','Enter'); await p.waitForLoadState('networkidle');
    for (const u of urls) {
      const r = await p.goto((process.env.PMS_URL || 'http://127.0.0.1:8000') + '' + u);
      const m = await p.evaluate(() => {
        const over = document.documentElement.scrollWidth - window.innerWidth;
        const clipped = [...document.querySelectorAll('.btn-side, .btn')].filter(e => e.offsetParent && e.scrollWidth > e.clientWidth + 2).map(e => e.innerText.split('\n')[0]).slice(0, 4);
        return { over, clipped };
      });
      if (r.status() !== 200 || m.over > 0 || m.clipped.length || errors.length) console.log(w, u, r.status(), 'overflow', m.over, 'clipped', m.clipped.join('|'), errors.splice(0).join(';'));
    }
    await p.close();
  }
  console.log('sweep done'); await b.close();
})();
