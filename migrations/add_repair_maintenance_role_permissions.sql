insert into public.role_permissions (role_id, module, action, scope)
select r.id, 'repair_maintenance', permission.action, 'all'
from public.roles r
cross join (values ('view'), ('create'), ('edit'), ('delete')) as permission(action)
where lower(trim(r.name)) in ('admin', 'super admin')
on conflict (role_id, module, action)
do update set scope = excluded.scope;
