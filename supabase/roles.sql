create table if not exists public.roles (
    id uuid primary key default gen_random_uuid(),
    name text not null unique,
    description text not null,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create index if not exists idx_roles_name on public.roles(name);
create index if not exists idx_roles_created_at on public.roles(created_at desc);

create or replace function public.set_roles_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

drop trigger if exists trg_roles_set_updated_at on public.roles;
create trigger trg_roles_set_updated_at
before update on public.roles
for each row
execute function public.set_roles_updated_at();

alter table public.roles disable row level security;

drop policy if exists "roles_select_all" on public.roles;
drop policy if exists "roles_insert_all" on public.roles;
drop policy if exists "roles_update_all" on public.roles;
drop policy if exists "roles_delete_all" on public.roles;
