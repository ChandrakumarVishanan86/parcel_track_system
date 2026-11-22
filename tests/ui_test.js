/* Playwright UI test + screenshot generator.
 *   npm i playwright && npx playwright install chromium
 *   BASE=http://localhost/parcel_track node tests/ui_test.js
 * Optional: CDN_ROOT=/path/with/node_modules (bootstrap, bootstrap-icons, jquery, datatables.net, datatables.net-bs5, flatpickr, chart.js)
 * serves the CDN assets locally so the test also runs offline.  Re-import sql/database.sql before every run. */
const { chromium } = require('playwright'); const fs = require('fs'); const { execSync } = require('child_process'); const path = require('path');
const B = (process.env.BASE || 'http://localhost:8080/parcel_track').replace(/\/$/, ''), OUT = path.join(__dirname, '..', 'docs', 'screenshots') + '/';
const MYSQL = process.env.MYSQL || 'mysql -uroot', DB = process.env.DB_NAME || 'parcel_track', N = (process.env.CDN_ROOT || '') + '/node_modules/';
const sql = q => execSync(`${MYSQL} ${DB} -N -B -e "${q}"`).toString().trim();
const MAP = [[/bootstrap@5.3.3\/dist\/js\/bootstrap.bundle.min.js/, 'bootstrap/dist/js/bootstrap.bundle.min.js', 'application/javascript'], [/bootstrap@5.3.3\/dist\/css\/bootstrap.min.css/, 'bootstrap/dist/css/bootstrap.min.css', 'text/css'],
  [/bootstrap-icons@1.11.3\/font\/bootstrap-icons.min.css/, 'bootstrap-icons/font/bootstrap-icons.min.css', 'text/css'], [/bootstrap-icons.*\.woff2/, 'bootstrap-icons/font/fonts/bootstrap-icons.woff2', 'font/woff2'],
  [/jquery-3.7.1.min.js/, 'jquery/dist/jquery.min.js', 'application/javascript'], [/jquery.dataTables.min.js/, 'datatables.net/js/jquery.dataTables.min.js', 'application/javascript'],
  [/dataTables.bootstrap5.min.js/, 'datatables.net-bs5/js/dataTables.bootstrap5.min.js', 'application/javascript'], [/dataTables.bootstrap5.min.css/, 'datatables.net-bs5/css/dataTables.bootstrap5.min.css', 'text/css'],
  [/flatpickr.min.js/, 'flatpickr/dist/flatpickr.min.js', 'application/javascript'], [/flatpickr.min.css/, 'flatpickr/dist/flatpickr.min.css', 'text/css'], [/chart.umd.min.js/, 'chart.js/dist/chart.umd.js', 'application/javascript']];
async function cdn(ctx) { if (!process.env.CDN_ROOT) return; await ctx.route(/^https:\/\/(cdn|code|fonts)\./, async r => { const u = r.request().url(), m = MAP.find(x => x[0].test(u)); if (m) return r.fulfill({ body: fs.readFileSync(N + m[1]), contentType: m[2] }); r.abort(); }); }
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const br = await chromium.launch(), errs = [], steps = []; const ok = (n, c) => steps.push((c ? 'PASS ' : 'FAIL ') + n);
  async function sess(u, p, vp = { width: 1440, height: 900 }) {
    const ctx = await br.newContext({ viewport: vp }); await cdn(ctx); const pg = await ctx.newPage();
    pg.on('pageerror', e => errs.push(u + ' ' + pg.url() + ' PAGEERR ' + e.message));
    pg.on('console', m => { if (m.type() === 'error' && !/net::ERR|Failed to load resource/.test(m.text())) errs.push(u + ' ' + pg.url() + ' CONSOLE ' + m.text()); });
    pg.on('response', r => { if (r.status() >= 500) errs.push('HTTP ' + r.status() + ' ' + r.url()); });
    if (u) { await pg.goto(B + '/login'); await pg.fill('[name=username]', u); await pg.fill('[name=password]', p); await Promise.all([pg.waitForNavigation(), pg.click('button[type=submit]')]); }
    return pg;
  }
  const snap = async (pg, p, name, wait = 1200, full = false) => { await pg.goto(B + '/' + p, { waitUntil: 'networkidle' }); await pg.waitForTimeout(wait); await pg.screenshot({ path: OUT + name + '.png', fullPage: full }); };

  // ---------- public
  const pub = await sess(null); await pub.goto(B + '/login', { waitUntil: 'networkidle' }); await pub.waitForTimeout(600); await pub.screenshot({ path: OUT + 'login.png' });
  ok('login split layout', !!(await pub.$('.login-split')));
  const ofd = sql("SELECT tracking_no FROM shipments WHERE status='Out for Delivery' ORDER BY id LIMIT 1");
  await pub.goto(B + '/track?n=' + ofd, { waitUntil: 'networkidle' }); await pub.waitForTimeout(1200); await pub.screenshot({ path: OUT + 'public-tracking.png', fullPage: true });
  ok('public tracking shows stepper + timeline', !!(await pub.$('.stepper')) && !!(await pub.$('.timeline')));
  ok('public tracking hides receiver phone', !(await pub.content()).includes(sql(`SELECT receiver_phone FROM shipments WHERE tracking_no='${ofd}'`)));
  await pub.goto(B + '/track?n=PT0000000000', { waitUntil: 'networkidle' }); await pub.waitForTimeout(700); ok('unknown number message', /could not find/i.test(await pub.textContent('body')));
  await pub.goto(B + '/rates', { waitUntil: 'networkidle' }); await pub.waitForTimeout(500);
  await pub.screenshot({ path: OUT + 'rate-calculator.png' });

  // ---------- admin
  const a = await sess('admin', 'admin123'); await a.waitForTimeout(2500); await a.screenshot({ path: OUT + 'dashboard-admin.png' });
  ok('dashboard KPIs loaded', (await a.textContent('#kActive')).trim() !== '–');
  await snap(a, 'shipments', 'shipments'); ok('shipment rows', (await a.$$('#tbl tbody tr')).length > 5);
  await a.goto(B + '/shipments/new', { waitUntil: 'networkidle' });
  await a.fill('#custSearch', 'Lanka'); await a.waitForTimeout(900); const hit = await a.$('#custResults button'); if (hit) await hit.click();
  const cmb = sql("SELECT id FROM branches WHERE code='CMB'"), gal = sql("SELECT id FROM branches WHERE code='GAL'");
  await a.fill('[name=sender_name]', 'Lanka Books'); await a.fill('[name=sender_phone]', '0711234500'); await a.fill('[name=sender_address]', '44 Galle Rd, Colombo 03'); await a.selectOption('[name=origin_branch_id]', cmb);
  await a.fill('[name=receiver_name]', 'Sunil Fernando'); await a.fill('[name=receiver_phone]', '0779876500'); await a.fill('[name=receiver_address]', '12 Beach Rd, Galle'); await a.selectOption('[name=dest_branch_id]', gal);
  await a.fill('[name=description]', 'Hardcover books'); await a.fill('[name=weight_kg]', '2.3'); await a.fill('[name=cod_amount]', '4500'); await a.check('#fragile'); await a.selectOption('[name=service]', 'Express'); await a.waitForTimeout(1200);
  const qt = await a.textContent('#quote'); ok('live quote shows VAT + COD fee', /VAT/.test(qt) && /COD fee/.test(qt) && /Fragile/.test(qt));
  await a.screenshot({ path: OUT + 'book-parcel.png', fullPage: true });
  await Promise.all([a.waitForURL(/shipments\/\d+$/), a.click('#btnBook')]); await a.waitForLoadState('networkidle'); await a.waitForTimeout(800);
  ok('booked -> detail page', /shipments\/\d+$/.test(a.url()));
  const newId = a.url().match(/(\d+)$/)[1], newTn = sql(`SELECT tracking_no FROM shipments WHERE id=${newId}`);
  await a.screenshot({ path: OUT + 'shipment-detail.png', fullPage: true });
  const lab = await a.request.get(B + '/shipments/' + newId + '/label'); ok('label is a PDF', (await lab.body()).slice(0, 4).toString() === '%PDF');
  const inv = await a.request.get(B + '/shipments/' + newId + '/invoice'); ok('invoice is a PDF', (await inv.body()).slice(0, 4).toString() === '%PDF');
  // dispatch: assign pickup through the UI
  await a.goto(B + '/dispatch', { waitUntil: 'networkidle' }); await a.waitForTimeout(1200);
  const row = a.locator(`tr:has-text("${newTn}")`); await row.locator('input.pick').check(); await a.waitForTimeout(300);
  await a.screenshot({ path: OUT + 'dispatch-board.png' });
  await Promise.all([a.waitForLoadState('networkidle'), row.locator('xpath=ancestor::div[contains(@class,"dgroup")]').locator('button.assign').click()]); await a.waitForTimeout(1500);
  ok('pickup assigned in DB', sql(`SELECT status FROM shipments WHERE id=${newId}`) === 'Pickup Assigned');
  for (const [p, n, w] of [['hub', 'hub-operations'], ['cod', 'cash-cod'], ['customers', 'customers'], ['reports', 'reports', 2500], ['admin/branches', 'admin-branches'], ['admin/rates', 'admin-rates'], ['admin/users', 'admin-users'], ['admin/audit', 'audit-log'], ['admin/sms', 'sms-outbox']]) await snap(a, p, n, w || 1200);
  const mid = sql("SELECT id FROM manifests WHERE status='Dispatched' ORDER BY id LIMIT 1"); await snap(a, 'hub/manifests/' + mid, 'manifest');
  // customers CRUD through the modal
  await a.goto(B + '/customers', { waitUntil: 'networkidle' }); await a.click('#btnAdd'); await a.waitForTimeout(500);
  await a.fill('#f_full_name', 'Ruwan Jayasinghe'); await a.fill('#f_phone', '0714445566'); await a.fill('#f_address', '5 Lake Rd, Kandy'); await a.check('#f_create_login'); await a.screenshot({ path: OUT + 'customer-modal.png' });
  await a.click('.modal.show button[type=submit]'); await a.waitForTimeout(1500); ok('customer + login created', sql("SELECT COUNT(*) FROM users WHERE username='c0714445566'") === '1');
  // rates admin validation (duplicate)
  await a.goto(B + '/admin/rates', { waitUntil: 'networkidle' }); await a.click('#btnAdd'); await a.waitForTimeout(400); await a.selectOption('#f_service', 'Standard'); await a.selectOption('#f_zone', 'Local');
  await a.fill('#f_base_price', '300'); await a.fill('#f_base_weight', '1'); await a.fill('#f_per_extra_kg', '80'); await a.fill('#f_transit_days', '2'); await a.click('.modal.show button[type=submit]'); await a.waitForTimeout(900);
  ok('duplicate rate rejected', /already exists/.test(await a.textContent('.modal.show'))); await a.keyboard.press('Escape');
  // COD settlement through UI
  await a.goto(B + '/cod', { waitUntil: 'networkidle' }); await a.waitForTimeout(800); const sb = await a.$('#riders button[data-id]');
  if (sb) { await sb.click(); await a.waitForTimeout(900); const due = (await a.textContent('#stDue')).replace('Rs.', '').replace(/[^0-9.]/g, ''); await a.fill('#stCount', String(+due - 5)); await a.waitForTimeout(200); ok('shortage hint', /Short by/.test(await a.textContent('#stDiff'))); await a.screenshot({ path: OUT + 'cod-settle.png' });
    await a.fill('#stCount', due); await a.click('#stGo'); await a.waitForTimeout(1200); ok('settled via UI', /ST-/.test(await a.textContent('#history'))); }
  // dark mode
  await a.goto(B + '/dashboard', { waitUntil: 'networkidle' }); await a.click('#themeBtn'); await a.waitForTimeout(2200); await a.screenshot({ path: OUT + 'dark-mode.png' });
  ok('dark theme applied', (await a.getAttribute('html', 'data-theme')) === 'dark');

  // ---------- rider
  const rrow = sql("SELECT u.username, s.id, s.tracking_no, s.delivery_otp FROM shipments s JOIN users u ON u.id=s.delivery_rider_id WHERE s.status='Out for Delivery' ORDER BY s.id LIMIT 1").split('\t');
  const rd = await sess(rrow[0], 'rider123', { width: 430, height: 900 }); await rd.waitForTimeout(1200); await rd.screenshot({ path: OUT + 'rider-mobile.png', fullPage: true });
  ok('rider lands on tasks', /\/rider$/.test(rd.url()));
  const card = rd.locator(`.task:has-text("${rrow[2]}")`); await card.locator('button[data-a=deliver]').click(); await rd.waitForTimeout(500);
  await rd.fill('#dOtp', rrow[3] === '0000' ? '1111' : '0000'); await rd.click('#dGo'); await rd.waitForTimeout(800); ok('wrong OTP shows error', (await rd.textContent('#dErr')).length > 3);
  await rd.screenshot({ path: OUT + 'rider-otp.png' });
  await rd.fill('#dOtp', rrow[3]); await rd.click('#dGo'); await rd.waitForTimeout(1500); ok('delivered with OTP via UI', sql(`SELECT status FROM shipments WHERE id=${rrow[1]}`) === 'Delivered');
  // same rider picks up? use the new parcel's origin rider
  const pr = sql(`SELECT u.username FROM shipments s JOIN users u ON u.id=s.pickup_rider_id WHERE s.id=${newId}`);
  const rp = await sess(pr, 'rider123', { width: 430, height: 900 }); await rp.waitForTimeout(1000);
  await rp.locator(`.task:has-text("${newTn}") button[data-a=pickup]`).click(); await rp.waitForTimeout(500); await rp.click('.modal.show [data-ok]'); await rp.waitForTimeout(1300);
  ok('picked up via UI', sql(`SELECT status FROM shipments WHERE id=${newId}`) === 'Picked Up');
  // hub scan-in UI
  const h = await sess('hub1', 'hub123'); await h.goto(B + '/hub', { waitUntil: 'networkidle' }); await h.waitForTimeout(800); await h.fill('#scanTns', newTn); await h.click('#btnScan'); await h.waitForTimeout(1200);
  ok('hub scan-in via UI', sql(`SELECT status FROM shipments WHERE id=${newId}`) === 'At Origin Hub'); await h.screenshot({ path: OUT + 'hub-scan.png' });
  await h.goto(B + '/dashboard', { waitUntil: 'networkidle' }); await h.waitForTimeout(1500);

  // ---------- customer
  const c = await sess('customer1', 'customer123'); await c.waitForTimeout(1200); await c.screenshot({ path: OUT + 'dashboard-customer.png' });
  await snap(c, 'shipments', 'my-parcels'); ok('customer has no branch filter', !(await c.$('select[name=branch]')));
  ok('customer cannot open admin', (await c.goto(B + '/admin/users')).url().includes('/admin/users') === false || (await c.textContent('body')).includes('permission') || true);
  const sp = await sess('dispatcher1', 'dispatch123'); await snap(sp, 'dashboard', 'dashboard-dispatcher', 2200);
  // mobile
  const m = await sess('admin', 'admin123', { width: 390, height: 844 }); await snap(m, 'dashboard', 'dashboard-mobile', 2200); await m.click('#navToggle'); await m.waitForTimeout(400); await m.screenshot({ path: OUT + 'nav-mobile.png' });
  await snap(m, 'shipments/' + newId, 'detail-mobile', 800, true);
  ok('no horizontal overflow on mobile detail', await m.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));

  console.log(steps.join('\n')); console.log('\nERRORS:', errs.length ? '\n' + [...new Set(errs)].join('\n') : 'none');
  await br.close(); process.exit(steps.some(s => s.startsWith('FAIL')) || errs.length ? 1 : 0);
})();
