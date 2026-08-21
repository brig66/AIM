#!/usr/bin/env python3
"""
dfs_client.py — thin DataForSEO v3 client shared by the AIM domain overview.

Credentials are resolved from the environment using the same name list the
platform's Supabase collectors (dfs-rank, dfs-backlinks) accept, so one
keys.env works for the edge functions and for this skill. Nothing here ever
prints a credential.

Every DataForSEO response carries a `cost` field. This client accumulates it,
so a run reports what it actually spent rather than an estimate. Use that
number, not the projections in references/endpoints.md, when reporting spend.

CLI:
    python3 dfs_client.py --check          # verify credentials + show balance
"""
import base64
import json
import os
import sys
import time
import urllib.error
import urllib.request

API_ROOT = "https://api.dataforseo.com"

LOGIN_NAMES = [
    "DATAFORSEO_LOGIN", "DATAFORSEO_USERNAME", "DATAFORSEO_EMAIL", "DATAFORSEO_USER",
    "DFS_LOGIN", "DFS_USERNAME", "DATA_FOR_SEO_LOGIN",
]
PASSWORD_NAMES = [
    "DATAFORSEO_PASSWORD", "DATAFORSEO_PASS", "DATAFORSEO_API_KEY", "DATAFORSEO_KEY",
    "DFS_PASSWORD", "DFS_PASS", "DATA_FOR_SEO_PASSWORD",
]


class DFSError(RuntimeError):
    """A DataForSEO call that must not be silently treated as 'no data'."""


def _from_env(names):
    for n in names:
        v = os.environ.get(n)
        if v and v.strip():
            return n, v.strip()
    return None, ""


def credentials():
    """(login, password, provenance) — provenance names the variables, never the values."""
    lname, login = _from_env(LOGIN_NAMES)
    pname, password = _from_env(PASSWORD_NAMES)
    if not login or not password:
        raise DFSError(
            "DataForSEO credentials not found. Set DATAFORSEO_LOGIN and "
            "DATAFORSEO_PASSWORD (or source the client's keys.env), then re-run.\n"
            "Looked for: " + ", ".join(LOGIN_NAMES + PASSWORD_NAMES)
        )
    return login, password, {"login_from": lname, "password_from": pname}


class Client:
    """POSTs one task at a time and keeps a running cost ledger."""

    def __init__(self, timeout=180, retries=3, verbose=True):
        self.login, self.password, self.cred_source = credentials()
        self.timeout = timeout
        self.retries = retries
        self.verbose = verbose
        self.cost = 0.0
        self.calls = []

    # -- transport ---------------------------------------------------------

    def _auth_header(self):
        raw = f"{self.login}:{self.password}".encode()
        return "Basic " + base64.b64encode(raw).decode()

    def _request(self, path, payload):
        body = json.dumps(payload).encode() if payload is not None else None
        req = urllib.request.Request(
            API_ROOT + path,
            data=body,
            method="POST" if body is not None else "GET",
            headers={
                "Authorization": self._auth_header(),
                "Content-Type": "application/json",
            },
        )
        with urllib.request.urlopen(req, timeout=self.timeout) as resp:
            return json.loads(resp.read().decode())

    def call(self, path, task=None, allow_empty=True):
        """
        POST a single task to `path` and return its first result object.

        Returns None only when the task succeeded and carried no result — the
        genuine "no data for this target" case. Every other failure raises, so
        an API outage can never be mistaken for a domain with zero keywords.
        """
        payload = [task] if task is not None else None
        last = None
        for attempt in range(1, self.retries + 1):
            try:
                data = self._request(path, payload)
                break
            except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, OSError) as e:
                last = e
                if attempt == self.retries:
                    raise DFSError(f"{path}: transport failed after {attempt} attempts: {e}")
                time.sleep(2 ** attempt)
        else:  # pragma: no cover - loop always breaks or raises
            raise DFSError(f"{path}: {last}")

        if data.get("status_code") != 20000:
            raise DFSError(f"{path}: {data.get('status_code')} {data.get('status_message')}")

        spent = float(data.get("cost") or 0.0)
        self.cost += spent

        tasks = data.get("tasks") or []
        if not tasks:
            raise DFSError(f"{path}: response carried no tasks")
        t = tasks[0]
        if t.get("status_code") != 20000:
            raise DFSError(f"{path}: task {t.get('status_code')} {t.get('status_message')}")

        results = t.get("result") or []
        result = results[0] if results else None
        self.calls.append({"path": path, "cost": round(spent, 6),
                           "result_count": t.get("result_count", len(results))})
        if self.verbose:
            print(f"  · {path}  ${spent:.4f}", file=sys.stderr)
        if result is None and not allow_empty:
            raise DFSError(f"{path}: task succeeded but returned no result")
        return result

    def ledger(self):
        return {"total_cost_usd": round(self.cost, 4), "calls": self.calls}


def user_data(client):
    """Account balance and rate limits — also the cheapest credential check."""
    return client.call("/v3/appendix/user_data", task=None)


def main():
    if "--check" not in sys.argv:
        print(__doc__)
        return 0
    try:
        c = Client(verbose=False)
    except DFSError as e:
        print(str(e), file=sys.stderr)
        return 2
    try:
        info = user_data(c) or {}
    except DFSError as e:
        print(f"Credentials found ({c.cred_source['login_from']}) but the call failed: {e}",
              file=sys.stderr)
        return 3
    money = (info.get("money") or {})
    print(json.dumps({
        "credentials_from": c.cred_source,
        "balance_usd": money.get("balance"),
        "limits": money.get("limits"),
        "rates": (info.get("rates") or {}).get("limits_minute"),
    }, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
