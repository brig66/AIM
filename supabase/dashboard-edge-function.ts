import "jsr:@supabase/functions-js/edge-runtime.d.ts";
import { createClient } from "jsr:@supabase/supabase-js@2";

/**
 * dashboard - JSON API for the AIM Analytics dashboard.
 * Serves no HTML: Supabase rewrites text/html to text/plain on GET for
 * *.supabase.co, so the page is hosted separately and calls this API.
 * Auth: POST /login returns a signed token, required in x-aim-session.
 * Password lives in the database: select public.dash_set_password('...').
 *
 * The token carries a role. Reads accept any valid token; writes require
 * 'admin'. Today the single password is AIM's own and always yields 'admin',
 * so nothing changes for existing sessions - but the role travels inside the
 * signature, so a client token issued later cannot claim to be an admin by
 * editing anything the browser can reach.
 *
 * CORS is an allowlist read from internal_config('dashboard_allowed_origins'),
 * a comma-separated list. The browser is told the specific origin that asked,
 * never "*": with credentials in a header rather than a cookie, "*" would let
 * any page on the internet call this API on behalf of a signed-in user.
 * Unknown origins get no CORS headers at all, so the browser blocks the read.
 */

const db = createClient(Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, { auth: { persistSession: false } });

const SESSION_DAYS = 14;

let originCache: { list: string[]; at: number } | null = null;
async function allowedOrigins(): Promise<string[]> {
  if (originCache && Date.now() - originCache.at < 300_000) return originCache.list;
  const { data } = await db.from("internal_config").select("value")
    .eq("key", "dashboard_allowed_origins").maybeSingle();
  const list = (data?.value ?? "")
    .split(",").map((s: string) => s.trim().replace(/\/$/, "")).filter(Boolean);
  originCache = { list, at: Date.now() };
  return list;
}

async function corsFor(req: Request): Promise<Record<string, string>> {
  const origin = (req.headers.get("origin") ?? "").replace(/\/$/, "");
  const base = {
    "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
    "Access-Control-Allow-Headers": "content-type, x-aim-session",
    "Access-Control-Max-Age": "86400",
    "Vary": "Origin",
  };
  if (!origin) return base;  // curl and server-to-server: no browser to protect
  const list = await allowedOrigins();
  if (list.length === 0) return { ...base, "Access-Control-Allow-Origin": "*" };
  if (list.includes(origin)) return { ...base, "Access-Control-Allow-Origin": origin };
  return base;
}

let keyCache: { key: CryptoKey; at: number } | null = null;
async function signingKey(): Promise<CryptoKey> {
  if (keyCache && Date.now() - keyCache.at < 300_000) return keyCache.key;
  const { data } = await db.from("internal_config").select("value")
    .eq("key", "dashboard_signing_key").single();
  if (!data?.value) throw new Error("dashboard_signing_key missing");
  const key = await crypto.subtle.importKey("raw", new TextEncoder().encode(data.value),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  keyCache = { key, at: Date.now() };
  return key;
}

async function sign(v: string) {
  const sig = await crypto.subtle.sign("HMAC", await signingKey(), new TextEncoder().encode(v));
  return [...new Uint8Array(sig)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

const ROLES = new Set(["admin", "client"]);

async function makeToken(role: string) {
  const exp = Date.now() + SESSION_DAYS * 86400000;
  const body = `${exp}.${role}`;
  return { token: `${body}.${await sign(body)}`, expires: exp, role };
}

function timingSafeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let d = 0;
  for (let i = 0; i < a.length; i++) d |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return d === 0;
}

/**
 * Returns the role a token proves, or null. Two formats are accepted:
 *   exp.role.sig  - current, signature covers "exp.role"
 *   exp.sig       - issued before roles existed, signature covers "exp"
 * The legacy form resolves to 'admin' because the only credential that could
 * ever have minted one is AIM's own. Accepting it means deploying this does not
 * sign anyone out mid-session; the form disappears on its own as tokens expire.
 */
async function tokenRole(tok: string | null): Promise<string | null> {
  if (!tok) return null;
  const parts = tok.split(".");
  if (parts.length !== 2 && parts.length !== 3) return null;
  const exp = Number(parts[0]);
  if (!Number.isFinite(exp) || exp < Date.now()) return null;

  if (parts.length === 3) {
    const [e, role, sig] = parts;
    if (!ROLES.has(role)) return null;
    return timingSafeEqual(sig, await sign(`${e}.${role}`)) ? role : null;
  }
  const [e, sig] = parts;
  return timingSafeEqual(sig, await sign(e)) ? "admin" : null;
}

function route(p: string) {
  const x = p.replace(/^\/functions\/v1/, "").replace(/^\/dashboard/, "");
  return x === "" ? "/" : x;
}

// Each API path maps to one dash_* function and the query params it takes.
// Adding a tab means adding a line here, not a new branch. The map is an
// allowlist on purpose: dash_set_password is a dash_ function too, so a
// name-prefix rule would be no boundary at all.
type Arg = "client" | "engine" | "from" | "to" | "id" | "limit" | "dim"
         | "run" | "type";
const ROUTES: Record<string, { fn: string; args: Arg[] }> = {
  "/api/overview": { fn: "dash_overview", args: ["client", "engine", "from", "to"] },
  "/api/keywords": { fn: "dash_keywords", args: ["client", "engine", "from", "to"] },
  "/api/keyword":  { fn: "dash_keyword_history", args: ["id", "from", "to"] },
  "/api/health":   { fn: "dash_health", args: ["client"] },
  "/api/forms":    { fn: "dash_forms", args: ["client", "from", "to", "limit"] },
  "/api/ai":       { fn: "dash_ai", args: ["client", "from", "to"] },
  "/api/report":   { fn: "dash_report", args: ["client", "from", "to"] },
  "/api/summary":  { fn: "dash_summary", args: ["client", "from", "to"] },

  // Front Page Placement over time. Separate from dash_report because it
  // rebuilds a snapshot of every tracked phrase at the end of every bucket and
  // is the slowest read the Overview makes; the page fetches it alongside the
  // report rather than inside it, so a slow history cannot hold up the tiles.
  "/api/rank_movement": { fn: "dash_rank_movement", args: ["client", "from", "to"] },

  // Traffic (GA4)
  "/api/traffic":        { fn: "dash_traffic", args: ["client", "from", "to"] },
  "/api/traffic_totals": { fn: "dash_traffic_totals", args: ["client", "from", "to"] },
  "/api/traffic_auto":   { fn: "dash_traffic_automation", args: ["client", "from", "to"] },
  "/api/traffic_tech":   { fn: "dash_traffic_tech_automation",
                           args: ["client", "from", "to"] },

  // Search Console
  "/api/gsc":     { fn: "dash_gsc_daily", args: ["client", "from", "to"] },
  "/api/gsc_top": { fn: "dash_gsc_top", args: ["client", "from", "to", "dim", "limit"] },

  // Backlinks + disavow
  "/api/backlinks":       { fn: "dash_backlinks_summary", args: ["client", "from", "to"] },
  "/api/backlinks_toxic": { fn: "dash_backlinks_toxic", args: ["client", "limit"] },
  "/api/disavow":         { fn: "dash_disavow_batches", args: ["client"] },

  // AI Readiness (point-in-time rubric audits)
  "/api/audits":        { fn: "dash_audits", args: ["client"] },
  "/api/audit_scores":  { fn: "dash_audit_scores", args: ["run"] },
  "/api/audit_history": { fn: "dash_audit_history", args: ["client", "type"] },

  // AI tracking prompt set (read; the writes below are admin-only)
  "/api/prompts": { fn: "dash_prompts", args: ["client"] },

  // Client + keyword administration (reads; writes are admin-only below)
  "/api/clients_admin":   { fn: "dash_clients_admin", args: [] },
  "/api/keywords_recent": { fn: "dash_keywords_recent", args: ["client", "limit"] },
};
const PARAM: Record<Arg, { key: string; cast: (v: string | null) => unknown }> = {
  client: { key: "p_client", cast: (v) => Number(v) },
  engine: { key: "p_engine", cast: (v) => v ?? "google" },
  from:   { key: "p_from", cast: (v) => v },
  to:     { key: "p_to", cast: (v) => v },
  id:     { key: "p_keyword", cast: (v) => Number(v) },
  limit:  { key: "p_limit", cast: (v) => Number(v ?? 300) },
  run:    { key: "p_run", cast: (v) => Number(v) },
  // Only 'query' or 'page' are meaningful; anything else falls back to query
  // rather than reaching the function as an unexpected value.
  dim:    { key: "p_dim", cast: (v) => (v === "page" ? "page" : "query") },
  type:   { key: "p_type", cast: (v) => v ?? "ai_visibility" },
};

// Write routes are a separate allowlist with their own guard. Everything here
// requires the admin role; a read token cannot reach any of it.
const WRITES: Record<string, { fn: string; build: (b: any) => Record<string, unknown> }> = {
  "/api/prompt_add": {
    fn: "dash_prompt_add",
    build: (b) => ({
      p_client: Number(b.client),
      p_prompt: String(b.prompt ?? ""),
      p_tags: Array.isArray(b.tags)
        ? b.tags.map((t: unknown) => String(t)).filter(Boolean).slice(0, 12)
        : null,
    }),
  },
  "/api/prompt_active": {
    fn: "dash_prompt_set_active",
    build: (b) => ({ p_id: Number(b.id), p_active: !!b.active }),
  },
  "/api/prompt_remove": {
    fn: "dash_prompt_remove",
    build: (b) => ({ p_id: Number(b.id) }),
  },
  "/api/client_add": {
    fn: "dash_client_add",
    build: (b) => ({
      p_name: String(b.name ?? ""),
      p_domain: String(b.domain ?? ""),
      p_market_scope: String(b.market_scope ?? "local"),
    }),
  },
  "/api/client_update": {
    // A patch object: keys present are written, keys absent are left alone, an
    // explicit null clears. Only these fields are settable - slug is the join
    // key other systems use and is deliberately not editable after creation.
    fn: "dash_client_update",
    build: (b) => {
      const allow = ["name", "domain", "ga4_property_id", "gsc_site_url",
                     "gbp_location_id", "market_scope", "active"];
      const patch: Record<string, unknown> = {};
      for (const k of allow) {
        if (b && Object.prototype.hasOwnProperty.call(b.patch ?? {}, k)) {
          patch[k] = (b.patch as Record<string, unknown>)[k];
        }
      }
      return { p_id: Number(b.id), p_patch: patch };
    },
  },
  "/api/keywords_add": {
    fn: "dash_keywords_add",
    build: (b) => ({
      p_client: Number(b.client),
      p_phrases: Array.isArray(b.phrases)
        ? b.phrases.map((p: unknown) => String(p)).slice(0, 2000)
        : [],
      p_location: String(b.location ?? ""),
    }),
  },
  "/api/keyword_remove": {
    fn: "dash_keyword_remove",
    build: (b) => ({ p_id: Number(b.id) }),
  },
};

Deno.serve(async (req: Request) => {
  const CORS = await corsFor(req);
  const json = (b: unknown, s = 200) =>
    new Response(JSON.stringify(b), { status: s,
      headers: { ...CORS, "Content-Type": "application/json", "Cache-Control": "no-store" } });

  if (req.method === "OPTIONS") return new Response(null, { status: 204, headers: CORS });
  const url = new URL(req.url);
  const path = route(url.pathname);

  if (path === "/health") {
    return json({ ok: true, service: "aim-dashboard-api", time: new Date().toISOString(),
                  allowed_origins: await allowedOrigins() });
  }

  if (path === "/login" && req.method === "POST") {
    let pw = "";
    try {
      const ct = req.headers.get("content-type") ?? "";
      if (ct.includes("application/json")) pw = String((await req.json()).password ?? "");
      else pw = String((await req.formData()).get("password") ?? "");
    } catch { /* empty */ }
    pw = pw.trim();
    if (!pw) return json({ error: "bad_password" }, 401);

    // dash_check_login returns a role. If it is ever missing, fall back to the
    // original boolean check so a half-applied deploy cannot lock anyone out.
    let role: string | null = null;
    const { data: r, error } = await db.rpc("dash_check_login", { p_pw: pw });
    if (error) {
      const { data: ok, error: e2 } = await db.rpc("dash_check_password", { p_pw: pw });
      if (e2) return json({ error: "login_backend_error", detail: e2.message }, 500);
      role = ok ? "admin" : null;
    } else {
      role = r ? String(r) : null;
    }
    if (!role || !ROLES.has(role)) {
      await new Promise((res) => setTimeout(res, 400));
      return json({ error: "bad_password" }, 401);
    }
    return json(await makeToken(role));
  }

  if (!path.startsWith("/api/")) return json({ error: "not_found", path }, 404);

  const role = await tokenRole(req.headers.get("x-aim-session"));
  if (!role) return json({ error: "unauthorized" }, 401);

  const q = url.searchParams;

  try {
    // Reclassifying form submissions runs in its own function under the
    // collector credential. The browser must never hold that token, so it is
    // read here and the call is made server to server.
    if (path === "/api/forms_reclassify") {
      if (req.method !== "POST") return json({ error: "method_not_allowed" }, 405);
      if (role !== "admin") return json({ error: "forbidden", need: "admin" }, 403);
      let body: any = {};
      try { body = await req.json(); } catch { /* defaults */ }
      const { data: tok } = await db.from("internal_config").select("value")
        .eq("key", "collector_token").single();
      if (!tok?.value) return json({ error: "collector_token_missing" }, 500);
      const r = await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/forms-classify`, {
        method: "POST",
        headers: { "content-type": "application/json", "x-collector-token": tok.value },
        body: JSON.stringify({
          limit: Math.min(Math.max(Number(body.limit ?? 40), 1), 200),
          dry_run: body.dry_run === true,
          client: body.client ? Number(body.client) : undefined,
        }),
      });
      const out = await r.json().catch(() => ({ error: "classifier_returned_no_json" }));
      return json(out, r.ok ? 200 : 502);
    }

    // ---- writes: admin only -------------------------------------------------
    const w = WRITES[path];
    if (w) {
      if (req.method !== "POST") return json({ error: "method_not_allowed" }, 405);
      if (role !== "admin") return json({ error: "forbidden", need: "admin" }, 403);
      let body: any = {};
      try { body = await req.json(); } catch { return json({ error: "bad_json" }, 400); }
      const { data, error } = await db.rpc(w.fn, w.build(body));
      if (error) {
        // The RPCs raise named errors with a human hint; pass both through so the
        // page can say what went wrong instead of "request failed".
        return json({ error: error.message, detail: error.hint ?? null }, 400);
      }
      return json({ ok: true, result: data });
    }

    if (path === "/api/clients") {
      const { data, error } = await db.from("clients")
        .select("id,slug,name,domain,market_scope,gsc_site_url,gbp_location_id,onboarded_at")
        .eq("active", true).order("name");
      if (error) throw error;
      return json(data);
    }
    const r = ROUTES[path];
    if (!r) return json({ error: "not_found", path }, 404);
    const args: Record<string, unknown> = {};
    for (const a of r.args) args[PARAM[a].key] = PARAM[a].cast(q.get(a));
    const { data, error } = await db.rpc(r.fn, args);
    if (error) throw error;
    return json(data);
  } catch (e) {
    return json({ error: String((e as Error).message ?? e) }, 500);
  }
});
