-- Update all roles to include repair_maintenance permissions
-- This migration ensures repair_maintenance is properly configured in the role permissions system

-- Add repair_maintenance permissions to Super Admin role (if not already present)
insert into public.role_permissions (role_id, module, action, scope)
select r.id, 'repair_maintenance', v.action, v.scope
from public.roles r
cross join (values 
    ('view', 'all'),
    ('create', 'all'),
    ('edit', 'all'),
    ('delete', 'all')
) as v(action, scope)
where r.name = 'Super Admin'
on conflict (role_id, module, action) do update set scope = excluded.scope;

-- Add repair_maintenance permissions to HOD role (if not already present)
insert into public.role_permissions (role_id, module, action, scope)
select r.id, 'repair_maintenance', v.action, v.scope
from public.roles r
cross join (values 
    ('view', 'department'),
    ('create', 'department'),
    ('edit', 'department')
) as v(action, scope)
where r.name = 'HOD'
on conflict (role_id, module, action) do update set scope = excluded.scope;

-- Add repair_maintenance notifications to Super Admin (if not already present)
insert into public.role_notifications (role_id, event_key, scope)
select r.id, e.event_key, 'all'
from public.roles r
cross join (values 
    ('repair_form_submitted'),
    ('repair_form_approved')
) as e(event_key)
where r.name = 'Super Admin'
on conflict (role_id, event_key) do update set scope = excluded.scope;

-- Add repair_maintenance notifications to HOD (if not already present)
insert into public.role_notifications (role_id, event_key, scope)
select r.id, e.event_key, 'department'
from public.roles r
cross join (values 
    ('repair_form_submitted'),
    ('repair_form_approved')
) as e(event_key)
where r.name = 'HOD'
on conflict (role_id, event_key) do update set scope = excluded.scope;
