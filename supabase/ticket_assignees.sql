-- Ticket Assignees table: supports assigning multiple technicians to a ticket

create table if not exists public.ticket_assignees (
  id uuid primary key default gen_random_uuid(),
  ticket_id uuid not null references public.tickets(id) on delete cascade,
  technician_email text not null,
  created_at timestamptz not null default now()
);

create index if not exists ticket_assignees_ticket_id_idx
  on public.ticket_assignees(ticket_id);

create index if not exists ticket_assignees_technician_email_idx
  on public.ticket_assignees(technician_email);

-- Optional RLS (NOT enabled by default here because app does not use Supabase Auth)
-- alter table public.ticket_assignees enable row level security;
-- create policy "read_all_assignees" on public.ticket_assignees
--   for select using (true);
-- create policy "full_access" on public.ticket_assignees
--   for insert, update, delete with check (true);

