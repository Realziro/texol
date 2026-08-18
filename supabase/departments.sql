create table if not exists public.departments (
    id uuid primary key default gen_random_uuid(),
    name text not null unique,
    description text not null,
    department_head text not null,
    prime_user text not null,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create index if not exists idx_departments_name on public.departments(name);
create index if not exists idx_departments_created_at on public.departments(created_at desc);

create or replace function public.set_departments_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

drop trigger if exists trg_departments_set_updated_at on public.departments;
create trigger trg_departments_set_updated_at
before update on public.departments
for each row
execute function public.set_departments_updated_at();
