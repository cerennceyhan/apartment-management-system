#!/usr/bin/env python3
"""HTTP smoke test: log in as the demo admin and request every page and endpoint.

Usage:
    php -S 127.0.0.1:8000 -t public          # in one terminal (database must be imported)
    python3 tests/smoke_test.py http://127.0.0.1:8000

Use ``--legacy`` for the old flat layout (before the public/ folder existed).
Only the standard library is needed. Exits non-zero when anything looks broken.
"""

import argparse
import http.cookiejar
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

PHP_ERROR = re.compile(r"(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught)")

PAGES = ["aidatlar", "aidatlarim", "bloklar", "carihareketler", "daireler",
         "destek", "giderler", "sakinler", "siteler", "yetkiler"]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("base", nargs="?", default="http://127.0.0.1:8000")
    parser.add_argument("--legacy", action="store_true", help="old layout: ajax endpoints lived in includes/")
    parser.add_argument("--username", default="admin")
    parser.add_argument("--password", default="admin123")
    args = parser.parse_args()
    base = args.base.rstrip("/")

    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    plain = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)
    failures = []

    def request(path, data=None, json_body=None, follow=True, expect=200):
        url = base + "/" + path
        headers = {}
        body = None
        if json_body is not None:
            body = json.dumps(json_body).encode()
            headers["Content-Type"] = "application/json"
        elif data is not None:
            body = urllib.parse.urlencode(data).encode()
        req = urllib.request.Request(url, data=body, headers=headers)
        try:
            res = (opener if follow else plain).open(req, timeout=30)
            status, text, location = res.status, res.read().decode("utf-8", "replace"), res.headers.get("Location")
        except urllib.error.HTTPError as err:
            status, text, location = err.code, err.read().decode("utf-8", "replace"), err.headers.get("Location")
        problems = []
        if status != expect:
            problems.append(f"status {status} (expected {expect})")
        m = PHP_ERROR.search(text)
        if m:
            line = text[max(0, m.start() - 10): m.start() + 160].replace("\n", " ")
            problems.append(f"php message: {line}")
        print(("FAIL " if problems else "ok   ") + f"{path:<45} {status} " + "; ".join(problems))
        if problems:
            failures.append(path)
        return status, text, location

    ajax = "includes" if args.legacy else "ajax"
    page_url = lambda p: f"pages/{p}.php"
    chart_endpoint = "ajax/get_dues_pie_data.php" if args.legacy else "ajax/dues_chart_data.php"

    request("login.php")
    request("index.php", follow=False, expect=302)  # not logged in -> redirect
    status, _, loc = request("login.php", data={"login_type": "uye", "username": args.username, "password": args.password},
                             follow=False, expect=302)
    if loc is None or "index.php" not in loc:
        print("login did not redirect to index.php; is the database imported?")
        sys.exit(1)

    request("index.php")
    for page in PAGES:
        request(page_url(page))
    request(f"{chart_endpoint}?month=2025-07")
    request(f"{ajax}/assign_owner.php?daire_id=1")
    request(f"{ajax}/assign_tenant.php?daire_id=1")
    request(f"{ajax}/set_selection.php", json_body={"site_id": 1, "blok_id": 1})
    for endpoint in ("test", "current-user", "dashboard", "sakinler", "aidatlar", "giderler"):
        request(f"api.php?endpoint={endpoint}")
    request("logout.php", follow=False, expect=302)
    request("index.php", follow=False, expect=302)

    print(f"\n{len(failures)} problem(s)" if failures else "\nall good")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
