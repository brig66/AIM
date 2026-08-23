-- dash_rank_movement(p_client, p_from, p_to)
--
-- Front Page Placement over time, for the Overview's movement chart. Mirrored
-- here so the read is reviewable alongside the route that serves it
-- (/api/rank_movement) and the page that draws it.
--
-- Applied to the aim-analytics-platform project as migration
-- `dash_rank_movement`, together with the index in idx_rank_kw_asof.sql.
--
-- Returns:
--   { "unit": "month" | "week",
--     "points": [ { "d": "2026-08-20", "label": "Aug 2026",
--                   "engines": [ { "engine": ..., "top3": ..., "p4_10": ...,
--                                  "page2": ..., "page1": ..., "tracked": ... } ],
--                   "unified": { "top3": ..., "p4_10": ..., "page2": ...,
--                                "page1": ..., "tracked": ... } } ] }
--
-- Why it is not part of dash_report: this rebuilds a snapshot of every tracked
-- phrase at the end of every bucket, which makes it the slowest read the
-- Overview does (about a second for a 583-phrase client over twelve months).
-- The page fetches it alongside the report rather than inside it, so a slow
-- history cannot hold up the tiles.

create or replace function public.dash_rank_movement(p_client bigint, p_from date, p_to date)
returns jsonb
language sql
stable
security definer
set search_path to 'public'
as $function$
-- Rank placement as it stood at the end of each bucket inside the selected
-- window, so the Overview can show movement rather than one snapshot.
--
-- Every bucket is read with the same rule dash_report uses for its headline
-- figures -- the latest interval whose valid_to is on or before the bucket's
-- as-of date, preferring DataForSEO over the AgencyAnalytics history during
-- the parallel-run period. The final bucket's as-of date is p_to itself, so
-- the last point on the chart is the same number as the tiles above it.
--
-- The 60-day lookback keeps the read cheap. Collection is weekly, so it never
-- drops a live keyword; a phrase unmeasured for two months is genuinely stale
-- and is left out of that bucket rather than carried forward for ever.
with win as (
  select case when p_to - p_from <= 70 then 'week' else 'month' end as unit
),
grid as (
  select least(
           (date_trunc((select unit from win), gs)
             + ('1 ' || (select unit from win))::interval
             - interval '1 day')::date,
           p_to) as d
  from generate_series(date_trunc((select unit from win), p_from::timestamp),
                       date_trunc((select unit from win), p_to::timestamp),
                       ('1 ' || (select unit from win))::interval) gs
),
-- Newest 24 buckets: a two-year monthly range stays readable, and anything
-- longer is trimmed from the left rather than crushed into the axis.
buckets as (
  select d from (select distinct d from grid order by d desc limit 24) x order by d
),
snap as (
  select b.d, s.keyword_id, s.engine, s.position
  from buckets b
  cross join lateral (
    select distinct on (ri.keyword_id, ri.engine)
           ri.keyword_id, ri.engine, ri.position
    from rank_intervals ri
    join keywords k on k.id = ri.keyword_id
    where k.client_id = p_client and k.active
      and ri.valid_to <= b.d and ri.valid_to > b.d - 60
    order by ri.keyword_id, ri.engine,
             (ri.source = 'dataforseo') desc, ri.valid_to desc
  ) s
),
per_engine as (
  select d, engine,
         count(*) filter (where position <= 3) as top3,
         count(*) filter (where position between 4 and 10) as p4_10,
         count(*) filter (where position between 11 and 20) as page2,
         count(*) filter (where position <= 10) as page1,
         count(*) as tracked
  from snap group by d, engine
),
-- One row per phrase at its best position across every engine, matching
-- rankings_unified so the two numbers can be read side by side.
per_unified as (
  select d,
         count(*) filter (where best <= 3) as top3,
         count(*) filter (where best between 4 and 10) as p4_10,
         count(*) filter (where best between 11 and 20) as page2,
         count(*) filter (where best <= 10) as page1,
         count(*) as tracked
  from (select d, keyword_id, min(position) best
          from snap group by d, keyword_id) u
  group by d
)
select jsonb_build_object(
  'unit', (select unit from win),
  'points', coalesce((
    select jsonb_agg(jsonb_build_object(
             'd', b.d,
             'label', case when (select unit from win) = 'month'
                           then to_char(b.d, 'Mon YYYY')
                           else to_char(b.d, 'Mon FMDD') end,
             'engines', coalesce((
               select jsonb_agg(jsonb_build_object(
                        'engine', e.engine, 'top3', e.top3, 'p4_10', e.p4_10,
                        'page2', e.page2, 'page1', e.page1, 'tracked', e.tracked)
                        order by e.engine)
               from per_engine e where e.d = b.d), '[]'::jsonb),
             'unified', coalesce((
               select to_jsonb(u) - 'd' from per_unified u where u.d = b.d),
               'null'::jsonb))
           order by b.d)
    from buckets b), '[]'::jsonb));
$function$;

-- Same grants as dash_report: the edge function calls it under the service
-- role, and nothing else should be able to reach it.
revoke all on function public.dash_rank_movement(bigint, date, date) from public;
grant execute on function public.dash_rank_movement(bigint, date, date) to service_role;
