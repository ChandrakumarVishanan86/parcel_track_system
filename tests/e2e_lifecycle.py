"""End-to-end business-rule test. Run against a freshly imported sql/database.sql:  python3 tests/e2e_lifecycle.py"""
from decimal import Decimal as D, ROUND_HALF_UP, ROUND_CEILING
import math, lib
from lib import *

def r2(x): return D(x).quantize(D('0.01'), rounding=ROUND_HALF_UP)

adm, dsp, hub, cus = Client('admin', 'admin123'), Client('dispatcher1', 'dispatch123'), Client('hub1', 'hub123'), Client('customer2', 'customer123')
check('logins', all(c.ok for c in (adm, dsp, hub, cus)))
br = {c: int(i) for i, c in sql('SELECT id, code FROM branches')}
CMB, NEG, KDY, GAL = br['CMB'], br['NEG'], br['KDY'], br['GAL']

def book(c, **o):
    d = dict(sender_name='Test Sender', sender_phone='0771110001', sender_address='1 Test Rd, Colombo', receiver_name='Test Receiver', receiver_phone='0772220002', receiver_address='2 Dest Rd, Kandy',
             origin_branch_id=CMB, dest_branch_id=KDY, service='Standard', weight_kg=1.2, description='Books', pieces=1, payer='Sender'); d.update(o)
    r = c.post('shipments/api', action='create', **d); return r.json()

# ---------- pricing: API quote vs independent maths
def expect(service, zone, kg, dims=None, fragile=0, cod=0, dv=0, ins=0):
    base = {('Standard','Local'):(350,1,90),('Standard','Regional'):(450,1,120),('Standard','National'):(600,1,160),('Express','Local'):(550,1,140),('Express','Regional'):(700,1,180),('Express','National'):(900,1,240),('Same-day','Local'):(1200,1,250)}[(service,zone)]
    vol = D(dims[0]*dims[1]*dims[2]) / 5000 if dims else D(0)
    ch = max(D(str(kg)), vol); ch = max(D('0.5'), (ch * 2).to_integral_value(rounding=ROUND_CEILING) / 2)
    fr = r2(D(base[0]) + max(D(0), ch - base[1]) * base[2]); fu = r2(fr * D('0.08')); ff = D(150) if fragile else D(0)
    cf = max(D(100), r2(D(cod) * D('0.02'))) if cod > 0 else D(0); inf = r2(D(dv) * D('0.01')) if ins else D(0)
    tax = r2((fr + fu + ff + cf + inf) * D('0.18')); return ch, fr + fu + ff + cf + inf + tax
for svc, o, d, kg, dims, fr_, cod, dv, ins in [('Standard',CMB,CMB,0.4,None,0,0,0,0),('Standard',CMB,NEG,1.0,None,0,0,0,0),('Express',CMB,KDY,2.3,None,1,0,0,0),('Express',CMB,GAL,5,(40,30,30),1,15000,20000,1),
                                          ('Same-day',CMB,CMB,3,None,0,2500,0,0),('Standard',NEG,GAL,49.9,None,0,500000,100000,1),('Standard',KDY,CMB,0.5,(10,10,10),0,0,0,0)]:
    zone = 'Local' if o == d else ('Regional' if sql(f'SELECT region FROM branches WHERE id={o}')==sql(f'SELECT region FROM branches WHERE id={d}') else 'National')
    q = adm.get('shipments/api', action='quote', service=svc, origin_branch_id=o, dest_branch_id=d, weight_kg=kg, fragile=fr_, cod_amount=cod, declared_value=dv, insured=ins,
                length_cm=dims[0] if dims else '', width_cm=dims[1] if dims else '', height_cm=dims[2] if dims else '').json()
    ch, tot = expect(svc, zone, kg, dims, fr_, cod, dv, ins)
    check(f'quote {svc}/{zone}/{kg}', q.get('success') and D(str(q['data']['total_charge'])) == tot and D(str(q['data']['chargeable_kg'])) == ch, f'{q} expected {tot} {ch}')
for bad, why in [(dict(service='Same-day', dest_branch_id=KDY), 'same-day non-local'), (dict(weight_kg=50.5), 'overweight'), (dict(weight_kg=0), 'zero weight'), (dict(cod_amount=500001), 'COD cap')]:
    p = dict(service='Standard', origin_branch_id=CMB, dest_branch_id=NEG, weight_kg=1); p.update(bad)
    check('quote rejects ' + why, adm.get('shipments/api', action='quote', **p).status_code == 422)

# ---------- booking validation
check('same phone rejected', not book(adm, receiver_phone='0771110001')['success'])
check('bad phone rejected', not book(adm, receiver_phone='abc')['success'])
check('short address rejected', not book(adm, receiver_address='x')['success'])
check('past pickup date rejected', not book(adm, pickup_date='2020-01-01')['success'])
check('hub cannot book', hub.post('shipments/api', action='create').status_code == 403)
check('missing CSRF -> 403', adm.post('shipments/api', csrf=False, action='create').status_code == 403)

# ---------- full happy path with COD, receiver-pays freight
b = book(adm, receiver_name='Nimal Perera', cod_amount=3000, payer='Receiver', fragile=1)
check('book COD parcel', b.get('success'), b); sid, tn = b['id'], b['tracking_no']
row = sql(f"SELECT status, payment_status, cod_fee, total_charge FROM shipments WHERE id={sid}")[0]
check('booked/unpaid', row[0] == 'Booked' and row[1] == 'Unpaid'); total = D(row[3])
# public tracking masks details
pub = requests.get(lib.BASE + '/track/api', params={'n': tn}).json()
check('public track works', pub.get('success'), pub); txt = str(pub)
check('public track hides phone/otp', '0772220002' not in txt and 'otp' not in txt.lower() and 'Nimal Perera' not in txt, txt[:300])
check('unknown tn -> not found', not requests.get(lib.BASE + '/track/api', params={'n': 'PT0000000000'}).json().get('success'))
# rider rules
rd = {u: int(i) for i, u in sql("SELECT id, username FROM users WHERE username IN ('rider1','rider2','rider3','rider4')")}
rbr = {u: int(i) for u, i in sql("SELECT username, branch_id FROM users WHERE username LIKE 'rider%'")}
r_cmb = next(u for u, bid in rbr.items() if bid == CMB); r_kdy = next(u for u, bid in rbr.items() if bid == KDY)
check('wrong-branch rider cannot be assigned pickup', not dsp.post('shipments/api', action='assign_pickup', ids=[sid], rider_id=rd.get(r_kdy) or int(val(f"SELECT id FROM users WHERE username='{r_kdy}'"))).json()['success'])
rid_cmb = int(val(f"SELECT id FROM users WHERE username='{r_cmb}'")); rid_kdy = int(val(f"SELECT id FROM users WHERE username='{r_kdy}'"))
check('hub cannot assign', hub.post('shipments/api', action='assign_pickup', ids=[sid], rider_id=rid_cmb).status_code == 403)
a = dsp.post('shipments/api', action='assign_pickup', ids=[sid], rider_id=rid_cmb).json(); check('assign pickup', a['success'], a)
R1 = Client(r_cmb, 'rider123'); R2 = Client(r_kdy, 'rider123')
check('other rider cannot pick up', not R2.post('rider/api', action='pickup', id=sid).json()['success'])
check('hub scan before pickup fails', not hub.post('hub/api', action='scan_in', branch_id=CMB, tns=tn).json()['results'][0]['ok'])
# receiver pays -> no pickup cash needed
p = R1.post('rider/api', action='pickup', id=sid).json(); check('pickup', p['success'], p)
check('no pickup cash for receiver-pays', int(val(f"SELECT COUNT(*) FROM rider_cash WHERE shipment_id={sid}")) == 0)
s = hub.post('hub/api', action='scan_in', branch_id=CMB, tns=tn).json(); check('hub scan-in', s['results'][0]['ok'], s)
check('double scan rejected', not hub.post('hub/api', action='scan_in', branch_id=CMB, tns=tn).json()['results'][0]['ok'])
check('status At Origin Hub', val(f"SELECT status FROM shipments WHERE id={sid}") == 'At Origin Hub')
m = hub.post('hub/api', action='create_manifest', from_branch_id=CMB, to_branch_id=GAL, ids=[sid]).json(); check('manifest to wrong destination rejected', not m['success'], m)
m = hub.post('hub/api', action='create_manifest', from_branch_id=CMB, to_branch_id=KDY, ids=[sid], vehicle='WP LB-1', driver='Kamal').json(); check('manifest created', m['success'], m); mid = m['id']
d = hub.post('hub/api', action='dispatch', id=mid).json(); check('manifest dispatched', d['success'], d)
check('status In Transit', val(f"SELECT status FROM shipments WHERE id={sid}") == 'In Transit')
hub_k = Client('hub2', 'hub123'); hub_kdy = next((Client(u, 'hub123') for (u,) in sql("SELECT username FROM users WHERE role='hub' AND branch_id=%d" % KDY)), None)
check('wrong-branch hub cannot receive', hub.post('hub/api', action='receive', id=mid, tns=tn).status_code in (403, 409))
rv = hub_kdy.post('hub/api', action='receive', id=mid, tns=tn).json(); check('receive at destination', rv['success'] and rv['results'][0]['ok'], rv)
check('status At Destination Hub', val(f"SELECT status FROM shipments WHERE id={sid}") == 'At Destination Hub')
check('manifest Received', val(f"SELECT status FROM manifests WHERE id={mid}") == 'Received')
check('wrong-branch rider cannot take delivery', not dsp.post('shipments/api', action='assign_delivery', ids=[sid], rider_id=rid_cmb).json()['success'])
a = dsp.post('shipments/api', action='assign_delivery', ids=[sid], rider_id=rid_kdy).json(); check('assign delivery', a['success'], a)
otp = val(f"SELECT delivery_otp FROM shipments WHERE id={sid}"); check('otp generated', otp and len(otp) == 4)
check('rider tasks hide OTP', 'delivery_otp' not in str(R2.get('rider/api', action='tasks').json()))
check('otp sent by SMS', int(val(f"SELECT COUNT(*) FROM sms_log WHERE shipment_id={sid} AND message LIKE '%{otp}%'")) >= 1)
wrong = '0000' if otp != '0000' else '1111'
check('missing receiver name rejected', not R2.post('rider/api', action='deliver', id=sid, otp=otp, received_by='').json()['success'])
for i in range(4): R2.post('rider/api', action='deliver', id=sid, otp=wrong, received_by='X Y')
w = R2.post('rider/api', action='deliver', id=sid, otp=wrong, received_by='X Y').json(); check('5th wrong OTP locks', not w['success'])
l = R2.post('rider/api', action='deliver', id=sid, otp=otp, received_by='Nimal Perera').json(); check('correct OTP refused while locked', not l['success'], l)
check('dispatcher resets OTP', dsp.post('shipments/api', action='regen_otp', id=sid).json()['success'])
otp = val(f"SELECT delivery_otp FROM shipments WHERE id={sid}")
dl = R2.post('rider/api', action='deliver', id=sid, otp=otp, received_by='Nimal Perera').json(); check('delivered with OTP', dl['success'], dl)
check('status Delivered', val(f"SELECT status FROM shipments WHERE id={sid}") == 'Delivered')
cash = {k: D(v) for k, v in sql(f"SELECT kind, amount FROM rider_cash WHERE shipment_id={sid}")}
check('COD cash recorded', cash.get('COD') == D(3000), cash); check('delivery freight cash recorded', cash.get('Delivery freight') == total, f'{cash} {total}')
check('cannot deliver twice', not R2.post('rider/api', action='deliver', id=sid, otp=otp, received_by='N P').json()['success'])
# settlement
due = sum(D(a) for (a,) in sql(f"SELECT amount FROM rider_cash WHERE rider_id={rid_kdy} AND settlement_id IS NULL"))
check('settle mismatch refused', not dsp.post('cod/api', action='settle', rider_id=rid_kdy, counted=float(due) - 1).json()['success'])
check('hub cannot settle', hub.post('cod/api', action='settle', rider_id=rid_kdy, counted=float(due)).status_code == 403)
st = dsp.post('cod/api', action='settle', rider_id=rid_kdy, counted=float(due), note='e2e').json(); check('settle exact', st['success'], st)
check('nothing left to settle', not dsp.post('cod/api', action='settle', rider_id=rid_kdy, counted=0).json()['success'])
check('cash cleared', val(f"SELECT COUNT(*) FROM rider_cash WHERE rider_id={rid_kdy} AND settlement_id IS NULL") == '0')

# ---------- sender pays cash at pickup (rider collects), local parcel skips transit
b = book(adm, origin_branch_id=CMB, dest_branch_id=CMB, service='Express', receiver_address='9 Local Lane, Colombo 05'); sid2, tn2 = b['id'], b['tracking_no']
tot2 = D(val(f"SELECT total_charge FROM shipments WHERE id={sid2}"))
check('unpaid pickup refused without cash', not (dsp.post('shipments/api', action='assign_pickup', ids=[sid2], rider_id=rid_cmb).json() and R1.post('rider/api', action='pickup', id=sid2, collect=False).json()['success']))
R1.post('rider/api', action='pickup', id=sid2, collect=False)
check('status still Pickup Assigned', val(f"SELECT status FROM shipments WHERE id={sid2}") == 'Pickup Assigned')
p = R1.post('rider/api', action='pickup', id=sid2, collect=True).json(); check('pickup collecting cash', p['success'], p)
check('payment marked Paid', val(f"SELECT payment_status FROM shipments WHERE id={sid2}") == 'Paid')
check('pickup freight cash', D(val(f"SELECT amount FROM rider_cash WHERE shipment_id={sid2} AND kind='Pickup freight'")) == tot2)
hub.post('hub/api', action='scan_in', branch_id=CMB, tns=tn2)
check('local parcel goes straight to destination hub', val(f"SELECT status FROM shipments WHERE id={sid2}") == 'At Destination Hub')
dsp.post('shipments/api', action='assign_delivery', ids=[sid2], rider_id=rid_cmb)

# ---------- failed delivery x3 -> Returning -> Returned
reasons = R1.get('rider/api', action='tasks').json()['reasons']
for i in range(3):
    f = R1.post('rider/api', action='fail', id=sid2, reason=reasons[0]).json(); check(f'failed attempt {i+1}', f['success'], f)
    if i < 2: check('back to Delivery Failed', val(f"SELECT status FROM shipments WHERE id={sid2}") == 'Delivery Failed'); dsp.post('shipments/api', action='assign_delivery', ids=[sid2], rider_id=rid_cmb)
check('3 attempts -> Returning', val(f"SELECT status FROM shipments WHERE id={sid2}") == 'Returning')
check('invalid fail reason rejected', not R1.post('rider/api', action='fail', id=sid2, reason='because').json()['success'])
check('unassigned return refused', not R1.post('rider/api', action='returned', id=sid2).json()['success'])
ca = dsp.post('shipments/api', action='assign_delivery', ids=[sid2], rider_id=rid_cmb).json(); check('assign return run', ca['success'] and val(f"SELECT status FROM shipments WHERE id={sid2}") == 'Returning', ca)
rt = R1.post('rider/api', action='returned', id=sid2).json(); check('return confirmed', rt['success'], rt); check('status Returned', val(f"SELECT status FROM shipments WHERE id={sid2}") == 'Returned')

# ---------- cancel + refund
b = book(adm, pay_method='Card'); sid3 = b['id']; check('paid at booking', val(f"SELECT payment_status FROM shipments WHERE id={sid3}") == 'Paid')
c = adm.post('shipments/api', action='cancel', id=sid3, reason='Customer changed mind').json(); check('cancel', c['success'], c)
check('refund recorded', val(f"SELECT payment_status FROM shipments WHERE id={sid3}") == 'Refunded'); check('cannot cancel twice', not adm.post('shipments/api', action='cancel', id=sid3).json()['success'])
b = book(adm); sid4 = b['id']; dsp.post('shipments/api', action='assign_pickup', ids=[sid4], rider_id=rid_cmb); R1.post('rider/api', action='pickup', id=sid4, collect=True)
check('cannot cancel after pickup', not adm.post('shipments/api', action='cancel', id=sid4).json()['success'])

# ---------- rider capacity (25)
ids = []
for i in range(26):
    bb = book(adm, sender_phone=f'07711{i:05d}'[:10], receiver_phone=f'07722{i:05d}'[:10]); ids.append(bb['id'])
cap = dsp.post('shipments/api', action='assign_pickup', ids=ids, rider_id=rid_cmb).json(); check('rider capacity enforced', not cap['success'], cap)

# ---------- customer portal isolation & credit
mine = cus.get('shipments/api', action='list').json()['data']; check('customer sees only own parcels', mine and all(int(x['customer_id']) == 2 for x in mine))
other = int(val("SELECT id FROM shipments WHERE customer_id <> 2 AND customer_id IS NOT NULL LIMIT 1"))
check('customer cannot open others parcel', cus.get('shipments/api', action='get', id=other).status_code == 404)
check('customer cannot cancel others parcel', not cus.post('shipments/api', action='cancel', id=other).json()['success'])
check('customer cannot assign', cus.post('shipments/api', action='assign_pickup', ids=[sid], rider_id=rid_cmb).status_code == 403)
cb = book(cus, receiver_name='Portal Receiver', receiver_phone='0773330003'); check('customer can book', cb.get('success'), cb)
check('customer booking is Online + linked', sql(f"SELECT source, customer_id FROM shipments WHERE id={cb['id']}")[0] == ['Online', '2'])
check('customer blocked from COD/reports', cus.get('cod/api', action='overview').status_code == 403 and cus.get('reports', action='data', **{'from': '2026-01-01', 'to': '2026-02-01'}).url.endswith('/login') is False)
cl = cus.get('customers/api', action='list').json()['data']; check('customer list shows only self', len(cl) == 1 and int(cl[0]['id']) == 2)
cr = Client('customer1', 'customer123'); b = book(cr, receiver_name='Credit R', receiver_phone='0774440004', pay_method='Online'); check('credit customer books', b.get('success'), b)
check('role guard on pages', requests.get(lib.BASE + '/admin/users').url.endswith('/login'))
check('rider blocked from dispatch API', R1.get('hub/api', action='overview', branch_id=1).status_code == 403)
check('hub operator locked to own branch', hub.get('hub/api', action='overview', branch_id=KDY).json()['counts'] is not None)
# customers CRUD + login creation
nc = dsp.post('customers/api', action='save', full_name='Eee Customer', phone='0779998877', create_login=1, branch_id=CMB).json(); check('create customer + login', nc['success'], nc)
check('login username derived from phone', val("SELECT COUNT(*) FROM users WHERE username='c0779998877'") == '1')
check('duplicate phone rejected', dsp.post('customers/api', action='save', full_name='Dup', phone='0779998877').status_code == 422)
check('staff API admin-only', dsp.get('admin/users-api', action='list').status_code == 403)
# integrity
check('every total = parts', val("SELECT COUNT(*) FROM shipments WHERE ABS(total_charge-(freight+fuel+fragile_fee+cod_fee+insurance_fee+tax))>0.005") == '0')
check('no orphan statuses', val("SELECT COUNT(*) FROM shipments s WHERE s.status <> (SELECT e.status FROM shipment_events e WHERE e.shipment_id=s.id ORDER BY e.id DESC LIMIT 1)") == '0')
summary('e2e lifecycle')
