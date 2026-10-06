-- User-level permissions override matching role permissions.
create or replace function public.get_effective_permissions(p_email text)
returns table (module text, action text, scope text)
language sql
stable
as $$
    with role_perms as (
        select rp.module, rp.action,
               (array_agg(rp.scope order by case rp.scope when 'all' then 1 when 'department' then 2 else 3 end))[1] as scope
        from public.users u
        join public.role_permissions rp on rp.role_id = u.role_id
        where u.email = p_email
        group by rp.module, rp.action
    ),
    user_perms as (
        select up.module, up.action,
               (array_agg(up.scope order by case up.scope when 'all' then 1 when 'department' then 2 else 3 end))[1] as scope
        from public.user_permissions up
        where up.user_email = p_email
        group by up.module, up.action
    )
    select up.module, up.action, up.scope
    from user_perms up
    union all
    select rp.module, rp.action, rp.scope
    from role_perms rp
    where not exists (
        select 1
        from user_perms up
        where up.module = rp.module and up.action = rp.action
    );
$$;

grant execute on function public.get_effective_permissions(text) to anon, authenticated, service_role;