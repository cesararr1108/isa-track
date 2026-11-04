const { chromium } = require('playwright');
(async () => {
  const dir = process.argv[2], fps = 30, dur = 31;
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
  await p.goto('file://' + dir + '/promo.html'); await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(500);
  const n = fps * dur;
  for (let i = 0; i < n; i++) {
    await p.evaluate(t => setTime(t), i / fps);
    await p.screenshot({ path: `${dir}/frames/f${String(i).padStart(4, '0')}.jpg`, type: 'jpeg', quality: 92 });
  }
  await b.close(); console.log('cuadros', n);
})();
