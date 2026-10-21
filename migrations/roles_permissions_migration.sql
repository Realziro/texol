-- =====================================================================
-- Role-based permissions migration (Supabase / PostgreSQL)
-- Safe to re-run: uses IF NOT EXISTS everywhere.
-- Your page already lists a "roles" module, so a roles table may exist.
-- Check first:  select * from information_schema.columns where table_name = 'roles';
-- =====================================================================

-- 1. ROLES ------------------------------------------------------------
create table if not exists public.roles (
    id          uuid primary key default gen_random_uuid(),
    name        text not null unique,          -- e.g. 'HOD', 'Technician'
    description text,
    is_active   boolean not null default true,
    created_at  timestamptz not null default now()
);

-- If roles already existed, make sure these columns are there
alter table public.roles add column if not exists description text;
alter table public.roles add column if not exists is_active boolean not null default true;

-- 2. ROLE PERMISSIONS (module + action + scope) -----------------------
-- scope decides HOW MUCH data the role can see for that module:
--   'own'        -> only records the user created / is assigned to
--   'department' -> all records in the user's department (HOD)
--   'all'        -> every record in the system
create table if not exists public.role_permissions (
    id         uuid primary key default gen_random_uuid(),
    role_id    uuid not null references public.roles(id) on delete cascade,
    module     text not null,
    action     text not null,
    scope      text not null default 'own',
    created_at timestamptz not null default now(),
    unique (role_id, module, action)
);
create index if not exists idx_role_permissions_role on public.role_permissions(role_id);

-- 3. ROLE NOTIFICATIONS (which emails a role receives) ----------------
create table if not exists public.role_notifications (
    id         uuid primary key default gen_random_uuid(),
    role_id    uuid not null references public.roles(id) on delete cascade,
    event_key  text not null,   -- e.g. 'requisition_submitted', 'ticket_created'
    scope      text not null default 'own',
    created_at timestamptz not null default now(),
    unique (role_id, event_key)
);
create index if not exists idx_role_notifications_role on public.role_notifications(role_id);

-- 4. LINK USERS TO A ROLE AND DEPARTMENT ------------------------------
-- (needed so "department" scope knows which department a user belongs to)
alter table public.users add column if not exists role_id uuid references public.roles(id) on delete set null;
-- users.department (text, department name) already exists and is used for "department" scope.

create index if not exists idx_users_role on public.users(role_id);

-- 5. KEEP user_permissions AS PER-USER OVERRIDES ----------------------
-- Existing rows keep working and are treated as scope 'all', so nothing breaks.
alter table public.user_permissions
    add column if not exists scope text not null default 'all';

-- 6. EFFECTIVE PERMISSIONS (role + user overrides combined) -----------
-- User overrides replace the matching role permission; otherwise role
-- permissions apply. Within one source, the widest duplicate scope wins.
create or replace function public.get_effective_permissions(p_email text)
returns table (module text, action text, scope text)
language sql
stable
as $$
    with role_perms as (
        select rp.module, rp.action, rp.scope
        from public.users u
        join public.role_permissions rp on rp.role_id = u.role_id
        where u.email = p_email
    ),
    user_perms as (
        select up.module, up.action, up.scope
        from public.user_permissions up
        where up.user_email = p_email
    ),
    combined as (
        select module, action, scope from user_perms
        union all
        select rp.module, rp.action, rp.scope
        from role_perms rp
        where not exists (
            select 1
            from user_perms up
            where up.module = rp.module and up.action = rp.action
        )
    )
    select c.module, c.action,
           (array_agg(c.scope order by case c.scope when 'all' then 1 when 'department' then 2 else 3 end))[1]
    from combined c
    group by c.module, c.action;
$$;

-- 7. EFFECTIVE NOTIFICATION RECIPIENTS --------------------------------
-- Returns emails that should receive an event. Pass the department of the
-- record (e.g. the requisition's department) and the requester ID so users
-- with 'own' scope only get notifications for their own items.
-- Uses users.department (the department NAME, text), compared case-insensitively.
create or replace function public.get_notification_recipients(p_event text, p_department text, p_requester_id uuid default null)
returns table (email text, full_name text)
language sql
stable
as $$
    select u.email, u.full_name
    from public.users u
    join public.role_notifications rn on rn.role_id = u.role_id
    where rn.event_key = p_event
      and (
            rn.scope = 'all'
         or (rn.scope = 'department'
             and lower(trim(u.department)) = lower(trim(p_department)))
         or (rn.scope = 'own'
             and p_requester_id is not null
             and u.id = p_requester_id)
      );
$$;

-- 8. EXAMPLE: CREATE AN "HOD" ROLE ------------------------------------
-- Run once, adjust modules/events to your needs.
with new_role as (
    insert into public.roles (name, description)
    values ('HOD', 'Head of Department')
    on conflict (name) do update set description = excluded.description
    returning id
)
insert into public.role_permissions (role_id, module, action, scope)
select new_role.id, v.module, v.action, v.scope
from new_role,
     (values
        ('dashboard',             'view',   'department'),
        ('requisitions_view_all', 'view',   'department'),
        ('requisition_approval',  'view',   'department'),
        ('requisition_approval',  'edit',   'department'),
        ('job_cards',             'view',   'department'),
        ('tickets',               'view',   'department'),
        ('mytickets',             'view',   'own'),
        ('repair_maintenance',    'view',   'department'),
        ('repair_maintenance',    'create', 'department'),
        ('repair_maintenance',    'edit',   'department')
     ) as v(module, action, scope)
on conflict (role_id, module, action) do nothing;

insert into public.role_notifications (role_id, event_key, scope)
select r.id, e.event_key, 'department'
from public.roles r,
     (values
        ('requisition_submitted'),
        ('requisition_approved'),
        ('requisition_updated'),
        ('requisition_rejected'),
        ('ticket_created'),
        ('ticket_updated'),
        ('ticket_closed'),
        ('job_card_created'),
        ('job_card_updated'),
        ('incident_reported'),
        ('incident_settled'),
        ('repair_form_submitted'),
        ('repair_form_approved')
     ) as e(event_key)
where r.name = 'HOD'
on conflict (role_id, event_key) do update set scope = excluded.scope;

-- 9. CREATE A "SUPER ADMIN" ROLE -----------------------------------
-- This role can see everything in all departments and receives all notifications
with super_role as (
    insert into public.roles (name, description)
    values ('Super Admin', 'Super user with access to all departments and notifications')
    on conflict (name) do update set description = excluded.description
    returning id
)
insert into public.role_permissions (role_id, module, action, scope)
select super_role.id, v.module, v.action, v.scope
from super_role,
     (values
        ('dashboard',                 'view',   'all'),
        ('dashboard',                 'edit',   'all'),
        ('job_cards',                 'view',   'all'),
        ('job_cards',                 'create', 'all'),
        ('job_cards',                 'edit',   'all'),
        ('job_cards',                 'delete', 'all'),
        ('tickets',                   'view',   'all'),
        ('tickets',                   'create', 'all'),
        ('tickets',                   'edit',   'all'),
        ('tickets',                   'delete', 'all'),
        ('mytickets',                 'view',   'all'),
        ('users',                     'view',   'all'),
        ('users',                     'create', 'all'),
        ('users',                     'edit',   'all'),
        ('users',                     'delete', 'all'),
        ('departments',               'view',   'all'),
        ('departments',               'create', 'all'),
        ('departments',               'edit',   'all'),
        ('departments',               'delete', 'all'),
        ('categories',                'view',   'all'),
        ('categories',                'create', 'all'),
        ('categories',                'edit',   'all'),
        ('categories',                'delete', 'all'),
        ('roles',                     'view',   'all'),
        ('roles',                     'create', 'all'),
        ('roles',                     'edit',   'all'),
        ('roles',                     'delete', 'all'),
        ('permissions',              'view',   'all'),
        ('permissions',              'edit',   'all'),
        ('requisition_approval',     'view',   'all'),
        ('requisition_approval',     'edit',   'all'),
        ('requisitions_view_all',    'view',   'all'),
        ('requisitions_view_all',    'create', 'all'),
        ('requisitions_view_all',    'edit',   'all'),
        ('requisitions_view_all',    'delete', 'all'),
        ('customer_feedback',        'view',   'all'),
        ('customer_feedback',        'edit',   'all'),
        ('incident_settlement',      'view',   'all'),
        ('incident_settlement',      'edit',   'all'),
        ('cctv_checklist_texol',     'view',   'all'),
        ('cctv_checklist_texol',     'edit',   'all'),
        ('cctv_checklist_sta',       'view',   'all'),
        ('cctv_checklist_sta',       'edit',   'all'),
        ('repair_maintenance',       'view',   'all'),
        ('repair_maintenance',       'create', 'all'),
        ('repair_maintenance',       'edit',   'all'),
        ('repair_maintenance',       'delete', 'all')
     ) as v(module, action, scope)
on conflict (role_id, module, action) do nothing;

-- Super admin receives all notifications for all departments
insert into public.role_notifications (role_id, event_key, scope)
select r.id, e.event_key, 'all'
from public.roles r,
     (values
        ('requisition_submitted'),
        ('requisition_approved'),
        ('requisition_updated'),
        ('requisition_rejected'),
        ('ticket_created'),
        ('ticket_updated'),
        ('ticket_closed'),
        ('job_card_created'),
        ('job_card_updated'),
        ('incident_reported'),
        ('incident_settled'),
        ('repair_form_submitted'),
        ('repair_form_approved')
     ) as e(event_key)
where r.name = 'Super Admin'
on conflict (role_id, event_key) do update set scope = excluded.scope;

-- 9. REMOVE ALL RESTRICTIONS ------------------------------------------
-- If you already ran an earlier version, drop the check constraints it created
alter table public.role_permissions   drop constraint if exists role_permissions_action_check;
alter table public.role_permissions   drop constraint if exists role_permissions_scope_check;
alter table public.role_notifications drop constraint if exists role_notifications_scope_check;
alter table public.user_permissions   drop constraint if exists user_permissions_scope_check;

-- Row Level Security off, so the browser (anon key) can read and write freely
alter table public.roles              disable row level security;
alter table public.role_permissions   disable row level security;
alter table public.role_notifications disable row level security;
alter table public.users              disable row level security;
alter table public.user_permissions   disable row level security;

-- Full table access for the API roles
grant all on public.roles              to anon, authenticated, service_role;
grant all on public.role_permissions   to anon, authenticated, service_role;
grant all on public.role_notifications to anon, authenticated, service_role;
grant all on public.users              to anon, authenticated, service_role;
grant all on public.user_permissions   to anon, authenticated, service_role;
grant execute on function public.get_effective_permissions(text)            to anon, authenticated, service_role;
grant execute on function public.get_notification_recipients(text, text, uuid) to anon, authenticated, service_role;