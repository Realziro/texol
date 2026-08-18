-- Ticket Notes table for Work Card Management App
-- Stores notes against a ticket, visible to users who can access that ticket in the app UI.

-- Requires pgcrypto for gen_random_uuid() on some Supabase projects (usually enabled by default).
-- If gen_random_uuid() fails, use uuid_generate_v4() after enabling "uuid-ossp" extension instead.

create table if not exists public.ticket_notes (
  id uuid primary key default gen_random_uuid(),
  ticket_id uuid not null references public.tickets(id) on delete cascade,
  note text not null,
  created_by_email text,
  created_by_name text,
  created_at timestamptz not null default now()
);

create index if not exists ticket_notes_ticket_id_idx
  on public.ticket_notes(ticket_id);

create index if not exists ticket_notes_created_at_idx
  on public.ticket_notes(created_at);

-- Optional (recommended) if you want row level security:
-- NOTE: This app is NOT using Supabase Auth, so RLS policies based on auth.uid() won't work.
-- You can either:
-- - Keep RLS OFF for this table (simplest for your current approach), or
-- - Implement RLS using a server-side key + API, or JWT with custom claims.
--
-- alter table public.ticket_notes enable row level security;
-- create policy "read_all_notes" on public.ticket_notes
--   for select
--   using (true);
--
-- create policy "insert_notes" on public.ticket_notes
--   for insert
--   with check (true);

