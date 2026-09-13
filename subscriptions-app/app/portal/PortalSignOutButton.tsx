"use client";

import { useRouter } from "next/navigation";
import { createSupabaseBrowserClient } from "@/lib/supabase/browser";

export default function PortalSignOutButton() {
  const router = useRouter();
  return (
    <button
      type="button"
      className="btn btn-secondary"
      style={{ padding: "6px 12px", fontSize: 13 }}
      onClick={async () => {
        const supabase = createSupabaseBrowserClient();
        await supabase.auth.signOut();
        router.push("/portal/login");
        router.refresh();
      }}
    >
      Log out
    </button>
  );
}
