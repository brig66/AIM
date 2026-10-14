import "jsr:@supabase/functions-js/edge-runtime.d.ts";
import { createClient } from "jsr:@supabase/supabase-js@2";

// tracker-guard - answers one question for the standalone AI Visibility Tracker
// skills (aim-ai-visibility-tracker, aim-visibility-tracker-run): is this
// domain already tracked on the AIM Analytics Dashboard? If it is, ai-collect
// already queries the engines for it on the dashboard's schedule, and a chat
// run would buy the same answers again into a local file the dashboard never
// reads.
//
// POST {"domains": ["example.com", ...]}  ->  {"dashboard_client": true|false}
//
// Deliberately returns a bare boolean - no client name, id or list - because
// verify_jwt is off so a skill can call it without a credential.

const db = createClient(Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, { auth: { persistSession: false } });

const json = (b: unknown, s = 200) =>
  new Response(JSON.stringify(b), { status: s, headers: { "Content-Type": "application/json" } });

const bare = (d: unknown) => String(d ?? "").trim().toLowerCase()
  .replace(/^[a-z]+:\/\//, "").replace(/^www\./, "").replace(/[/?#:].*$/, "");

let cache: { at: number; domains: Set<string> } | null = null;

async function dashboardDomains(): Promise<Set<string>> {
  if (cache && Date.now() - cache.at < 300_000) return cache.domains;
  const out = new Set<string>();

  const { data: clients, error } = await db.from("clients").select("id,domain").eq("active", true);
  if (error) throw new Error(error.message);
  const active = new Set<number>();
  for (const c of clients ?? []) {
    active.add(c.id);
    if (bare(c.domain)) out.add(bare(c.domain));
  }

  // A client's sibling sites and microsites live on its prompt set, not on
  // the client row, and a chat run for one of those is the same spend.
  const { data: sets, error: sErr } = await db.from("ai_prompt_sets")
    .select("client_id,primary_domain,brand").eq("active", true);
  if (sErr) throw new Error(sErr.message);
  for (const s of sets ?? []) {
    if (!active.has(s.client_id)) continue;
    for (const d of [s.primary_domain, ...(s.brand?.domains ?? [])]) if (bare(d)) out.add(bare(d));
  }

  cache = { at: Date.now(), domains: out };
  return out;
}

Deno.serve(async (req: Request) => {
  if (req.method !== "POST") return json({ error: "method_not_allowed" }, 405);
  let body: any = {};
  try { body = await req.json(); } catch { /* empty */ }
  const asked = (Array.isArray(body.domains) ? body.domains : [body.domain])
    .map(bare).filter(Boolean).slice(0, 50);
  if (!asked.length) return json({ error: "domains required" }, 400);

  try {
    const known = await dashboardDomains();
    return json({ dashboard_client: asked.some((d: string) => known.has(d)) });
  } catch (e) {
    return json({ error: String((e as Error).message ?? e) }, 500);
  }
});
