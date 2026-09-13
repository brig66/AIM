"use client";

import { useEffect, useState } from "react";
import { createSupabaseBrowserClient } from "@/lib/supabase/browser";
import type { TrackingPrompt } from "@/lib/supabase/types";

export default function PromptsPage() {
  const supabase = createSupabaseBrowserClient();
  const [clientId, setClientId] = useState<string | null>(null);
  const [prompts, setPrompts] = useState<TrackingPrompt[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [newText, setNewText] = useState("");
  const [newBranded, setNewBranded] = useState(false);

  async function load() {
    setLoading(true);
    setError(null);
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      setError("Not signed in.");
      setLoading(false);
      return;
    }
    const { data: client } = await supabase.from("clients").select("id").eq("auth_user_id", user.id).maybeSingle();
    if (!client) {
      setError("No account found for this login.");
      setLoading(false);
      return;
    }
    setClientId(client.id);
    const { data: rows, error: fetchError } = await supabase
      .from("tracking_prompts")
      .select("*")
      .eq("client_id", client.id)
      .order("created_at", { ascending: true });
    if (fetchError) setError(fetchError.message);
    setPrompts(rows ?? []);
    setLoading(false);
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function addPrompt() {
    if (!clientId || !newText.trim()) return;
    const { error: insertError } = await supabase
      .from("tracking_prompts")
      .insert({ client_id: clientId, prompt_text: newText.trim(), is_branded: newBranded, active: true });
    if (insertError) {
      setError(insertError.message);
      return;
    }
    setNewText("");
    setNewBranded(false);
    load();
  }

  async function toggleActive(prompt: TrackingPrompt) {
    await supabase.from("tracking_prompts").update({ active: !prompt.active }).eq("id", prompt.id);
    load();
  }

  async function removePrompt(id: string) {
    await supabase.from("tracking_prompts").delete().eq("id", id);
    load();
  }

  async function updateText(prompt: TrackingPrompt, text: string) {
    await supabase.from("tracking_prompts").update({ prompt_text: text }).eq("id", prompt.id);
  }

  if (loading) return <p className="muted">Loading&hellip;</p>;

  return (
    <>
      <h1 style={{ color: "var(--indigo)" }}>Tracking prompts</h1>
      <p className="muted">
        These are the questions we check across every AI engine each cycle. Mark each as branded (names your company)
        or non-branded (a category question). Turn a prompt off instead of deleting it to keep its history.
      </p>
      {error ? <p className="error-text">{error}</p> : null}

      <table className="card" style={{ marginTop: 16 }}>
        <thead>
          <tr>
            <th>Prompt</th>
            <th>Type</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {prompts.map((p) => (
            <tr key={p.id}>
              <td>
                <input
                  defaultValue={p.prompt_text}
                  onBlur={(e) => e.target.value !== p.prompt_text && updateText(p, e.target.value)}
                />
              </td>
              <td>{p.is_branded ? "Branded" : "Non-branded"}</td>
              <td>
                <button type="button" className="btn btn-secondary" style={{ padding: "4px 10px", fontSize: 12 }} onClick={() => toggleActive(p)}>
                  {p.active ? "Active" : "Paused"}
                </button>
              </td>
              <td>
                <button type="button" className="btn btn-danger" style={{ padding: "4px 10px", fontSize: 12 }} onClick={() => removePrompt(p.id)}>
                  Remove
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="card" style={{ marginTop: 16 }}>
        <h3 style={{ marginTop: 0 }}>Add a prompt</h3>
        <div style={{ display: "flex", gap: 8 }}>
          <input value={newText} onChange={(e) => setNewText(e.target.value)} placeholder="e.g. best plumber in Fort Worth" style={{ flex: 1 }} />
          <select value={newBranded ? "branded" : "nonbranded"} onChange={(e) => setNewBranded(e.target.value === "branded")} style={{ width: 150 }}>
            <option value="nonbranded">Non-branded</option>
            <option value="branded">Branded</option>
          </select>
          <button type="button" className="btn btn-primary" onClick={addPrompt}>
            Add
          </button>
        </div>
      </div>
    </>
  );
}
