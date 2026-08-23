-- Applied to the aim-analytics-platform project as migration `idx_rank_kw_valid_to`.

-- Every "position as of date D" read -- dash_report's headline figures and the
-- new dash_rank_movement series -- seeks on (keyword_id, engine) and then walks
-- valid_to backwards. idx_rank_kw_span leads with valid_from, so those reads
-- had to scan a keyword's whole history and filter. This one seeks straight to
-- the date and carries position and source, so the walk stops at the first row.
create index if not exists idx_rank_kw_asof
  on public.rank_intervals (keyword_id, engine, valid_to desc)
  include ("position", source);
