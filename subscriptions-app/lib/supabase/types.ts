// Generated from the live Supabase project (rrmilbbyzulopuesqwic) via
// `mcp__Supabase__generate_typescript_types`. Regenerate the same way if the
// schema changes — do not hand-edit column shapes here.
export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[];

export type Database = {
  __InternalSupabase: {
    PostgrestVersion: "14.5";
  };
  public: {
    Tables: {
      admin_alerts: {
        Row: {
          client_id: string | null;
          created_at: string;
          id: string;
          message: string;
          resolved: boolean;
          severity: string;
        };
        Insert: {
          client_id?: string | null;
          created_at?: string;
          id?: string;
          message: string;
          resolved?: boolean;
          severity?: string;
        };
        Update: {
          client_id?: string | null;
          created_at?: string;
          id?: string;
          message?: string;
          resolved?: boolean;
          severity?: string;
        };
        Relationships: [
          {
            foreignKeyName: "admin_alerts_client_id_fkey";
            columns: ["client_id"];
            isOneToOne: false;
            referencedRelation: "clients";
            referencedColumns: ["id"];
          },
        ];
      };
      billing_events: {
        Row: {
          client_id: string | null;
          created_at: string;
          event_type: string;
          id: string;
          payload: Json;
          processed_at: string | null;
          stripe_event_id: string;
        };
        Insert: {
          client_id?: string | null;
          created_at?: string;
          event_type: string;
          id?: string;
          payload: Json;
          processed_at?: string | null;
          stripe_event_id: string;
        };
        Update: {
          client_id?: string | null;
          created_at?: string;
          event_type?: string;
          id?: string;
          payload?: Json;
          processed_at?: string | null;
          stripe_event_id?: string;
        };
        Relationships: [
          {
            foreignKeyName: "billing_events_client_id_fkey";
            columns: ["client_id"];
            isOneToOne: false;
            referencedRelation: "clients";
            referencedColumns: ["id"];
          },
        ];
      };
      clients: {
        Row: {
          auth_user_id: string | null;
          company_name: string;
          contact_email: string;
          contact_name: string;
          created_at: string;
          current_period_end: string | null;
          id: string;
          plan: string;
          status: string;
          stripe_customer_id: string | null;
          stripe_subscription_id: string | null;
          updated_at: string;
          website_domain: string;
        };
        Insert: {
          auth_user_id?: string | null;
          company_name: string;
          contact_email: string;
          contact_name: string;
          created_at?: string;
          current_period_end?: string | null;
          id?: string;
          plan?: string;
          status?: string;
          stripe_customer_id?: string | null;
          stripe_subscription_id?: string | null;
          updated_at?: string;
          website_domain: string;
        };
        Update: {
          auth_user_id?: string | null;
          company_name?: string;
          contact_email?: string;
          contact_name?: string;
          created_at?: string;
          current_period_end?: string | null;
          id?: string;
          plan?: string;
          status?: string;
          stripe_customer_id?: string | null;
          stripe_subscription_id?: string | null;
          updated_at?: string;
          website_domain?: string;
        };
        Relationships: [];
      };
      runs: {
        Row: {
          client_id: string;
          created_at: string;
          engines_queried: string[] | null;
          error_message: string | null;
          finished_at: string | null;
          id: string;
          period_end: string;
          period_start: string;
          started_at: string | null;
          status: string;
        };
        Insert: {
          client_id: string;
          created_at?: string;
          engines_queried?: string[] | null;
          error_message?: string | null;
          finished_at?: string | null;
          id?: string;
          period_end: string;
          period_start: string;
          started_at?: string | null;
          status?: string;
        };
        Update: {
          client_id?: string;
          created_at?: string;
          engines_queried?: string[] | null;
          error_message?: string | null;
          finished_at?: string | null;
          id?: string;
          period_end?: string;
          period_start?: string;
          started_at?: string | null;
          status?: string;
        };
        Relationships: [
          {
            foreignKeyName: "runs_client_id_fkey";
            columns: ["client_id"];
            isOneToOne: false;
            referencedRelation: "clients";
            referencedColumns: ["id"];
          },
        ];
      };
      scorecards: {
        Row: {
          branded_score: number | null;
          client_id: string;
          created_at: string;
          emailed_at: string | null;
          id: string;
          nonbranded_score: number | null;
          overall_score: number | null;
          pdf_storage_path: string;
          run_id: string;
        };
        Insert: {
          branded_score?: number | null;
          client_id: string;
          created_at?: string;
          emailed_at?: string | null;
          id?: string;
          nonbranded_score?: number | null;
          overall_score?: number | null;
          pdf_storage_path: string;
          run_id: string;
        };
        Update: {
          branded_score?: number | null;
          client_id?: string;
          created_at?: string;
          emailed_at?: string | null;
          id?: string;
          nonbranded_score?: number | null;
          overall_score?: number | null;
          pdf_storage_path?: string;
          run_id?: string;
        };
        Relationships: [
          {
            foreignKeyName: "scorecards_client_id_fkey";
            columns: ["client_id"];
            isOneToOne: false;
            referencedRelation: "clients";
            referencedColumns: ["id"];
          },
          {
            foreignKeyName: "scorecards_run_id_fkey";
            columns: ["run_id"];
            isOneToOne: false;
            referencedRelation: "runs";
            referencedColumns: ["id"];
          },
        ];
      };
      tracking_prompts: {
        Row: {
          active: boolean;
          client_id: string;
          created_at: string;
          id: string;
          is_branded: boolean;
          prompt_text: string;
        };
        Insert: {
          active?: boolean;
          client_id: string;
          created_at?: string;
          id?: string;
          is_branded?: boolean;
          prompt_text: string;
        };
        Update: {
          active?: boolean;
          client_id?: string;
          created_at?: string;
          id?: string;
          is_branded?: boolean;
          prompt_text?: string;
        };
        Relationships: [
          {
            foreignKeyName: "tracking_prompts_client_id_fkey";
            columns: ["client_id"];
            isOneToOne: false;
            referencedRelation: "clients";
            referencedColumns: ["id"];
          },
        ];
      };
    };
    Views: {
      [_ in never]: never;
    };
    Functions: {
      [_ in never]: never;
    };
    Enums: {
      [_ in never]: never;
    };
    CompositeTypes: {
      [_ in never]: never;
    };
  };
};

type DefaultSchema = Database["public"];

export type Tables<T extends keyof DefaultSchema["Tables"]> =
  DefaultSchema["Tables"][T]["Row"];
export type TablesInsert<T extends keyof DefaultSchema["Tables"]> =
  DefaultSchema["Tables"][T]["Insert"];
export type TablesUpdate<T extends keyof DefaultSchema["Tables"]> =
  DefaultSchema["Tables"][T]["Update"];

export type Client = Tables<"clients">;
export type TrackingPrompt = Tables<"tracking_prompts">;
export type Run = Tables<"runs">;
export type Scorecard = Tables<"scorecards">;
export type BillingEvent = Tables<"billing_events">;
export type AdminAlert = Tables<"admin_alerts">;
