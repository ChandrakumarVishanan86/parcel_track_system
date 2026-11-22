"""Shared helpers for the ParcelTrack API tests.  Env: BASE (default http://localhost:8080/parcel_track), MYSQL (default 'mysql -uroot')."""
import os, re, subprocess, requests

BASE = os.environ.get('BASE', 'http://localhost:8080/parcel_track').rstrip('/')
MYSQL = os.environ.get('MYSQL', 'mysql -uroot').split()
DB = os.environ.get('DB_NAME', 'parcel_track')
PASS = FAIL = 0


def sql(q):
    out = subprocess.run(MYSQL + [DB, '-N', '-B', '-e', q], capture_output=True, text=True)
    if out.returncode: raise RuntimeError(out.stderr)
    return [r.split('\t') for r in out.stdout.strip().splitlines() if r]


def val(q):
    r = sql(q); return r[0][0] if r else None


class Client:
    def __init__(self, user, pw):
        self.s = requests.Session(); self.user = user
        r = self.s.get(BASE + '/login'); t = re.search(r'name="csrf_token" value="([^"]+)"', r.text)
        r = self.s.post(BASE + '/login', data={'username': user, 'password': pw, 'csrf_token': t.group(1) if t else ''}, allow_redirects=False)
        self.ok = r.status_code == 302
        self.csrf = re.search(r'csrf:\s*"([^"]+)"', self.s.get(BASE + '/' + ('rider' if user.startswith('rider') else 'dashboard')).text)
        self.csrf = self.csrf.group(1) if self.csrf else ''

    def get(self, path, **p):
        r = self.s.get(BASE + '/' + path, params=p); return r

    def post(self, path, csrf=True, **body):
        h = {'X-CSRF-Token': self.csrf} if csrf else {}
        return self.s.post(BASE + '/' + path, json=body, headers=h)


def check(name, cond, extra=''):
    global PASS, FAIL
    if cond: PASS += 1
    else: FAIL += 1; print('  FAIL:', name, extra)


def summary(title):
    print(f'{title}: {PASS} passed, {FAIL} failed'); raise SystemExit(1 if FAIL else 0)
