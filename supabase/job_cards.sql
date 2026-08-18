-- Job Cards table
create table if not exists public.job_cards (
    id uuid primary key default gen_random_uuid(),
    title text not null,
    description text,
    owner_id uuid not null references public.users(id) on delete cascade,
    status text not null check (status in ('Pending', 'In Progress', 'Completed')) default 'Pending',
    created_at timestamp with time zone default now(),
    due_date timestamp with time zone
);

-- Job Card Sharing table
create table if not exists public.job_card_users (
    id uuid primary key default gen_random_uuid(),
    job_card_id uuid not null references public.job_cards(id) on delete cascade,
    user_id uuid not null references public.users(id) on delete cascade,
    role text not null check (role in ('owner', 'editor', 'viewer')) default 'viewer',
    unique(job_card_id, user_id)
);

-- Tasks table
create table if not exists public.tasks (
    id uuid primary key default gen_random_uuid(),
    job_card_id uuid not null references public.job_cards(id) on delete cascade,
    title text not null,
    description text,
    status text not null check (status in ('To Do', 'Doing', 'Done')) default 'To Do',
    assigned_to uuid references public.users(id) on delete set null,
    priority text not null check (priority in ('low', 'medium', 'high')) default 'medium',
    due_date timestamp with time zone,
    created_at timestamp with time zone default now(),
    updated_at timestamp with time zone default now()
);

-- Job Card Notes table
create table if not exists public.job_card_notes (
    id uuid primary key default gen_random_uuid(),
    job_card_id uuid not null references public.job_cards(id) on delete cascade,
    user_id uuid not null references public.users(id) on delete cascade,
    note text not null,
    created_at timestamp with time zone default now()
);

-- Task Notes table
create table if not exists public.task_notes (
    id uuid primary key default gen_random_uuid(),
    task_id uuid not null references public.tasks(id) on delete cascade,
    user_id uuid not null references public.users(id) on delete cascade,
    note text not null,
    created_at timestamp with time zone default now()
);

-- Create indexes for better performance
create index if not exists idx_job_cards_owner_id on public.job_cards(owner_id);
create index if not exists idx_job_cards_status on public.job_cards(status);
create index if not exists idx_job_cards_created_at on public.job_cards(created_at);
create index if not exists idx_job_card_users_job_card_id on public.job_card_users(job_card_id);
create index if not exists idx_job_card_users_user_id on public.job_card_users(user_id);
create index if not exists idx_tasks_job_card_id on public.tasks(job_card_id);
create index if not exists idx_tasks_assigned_to on public.tasks(assigned_to);
create index if not exists idx_tasks_status on public.tasks(status);
create index if not exists idx_job_card_notes_job_card_id on public.job_card_notes(job_card_id);
create index if not exists idx_job_card_notes_user_id on public.job_card_notes(user_id);
create index if not exists idx_task_notes_task_id on public.task_notes(task_id);
create index if not exists idx_task_notes_user_id on public.task_notes(user_id);

-- Row Level Security (RLS) policies
alter table public.job_cards enable row level security;
alter table public.job_card_users enable row level security;
alter table public.tasks enable row level security;
alter table public.job_card_notes enable row level security;
alter table public.task_notes enable row level security;

-- Job Cards RLS policies
create policy "Users can view job cards they own or are shared"
    on public.job_cards for select
    using (
        owner_id = auth.uid() or 
        id in (
            select job_card_id from public.job_card_users 
            where user_id = auth.uid()
        )
    );

create policy "Users can insert job cards they own"
    on public.job_cards for insert
    with check (owner_id = auth.uid());

create policy "Users can update job cards they own"
    on public.job_cards for update
    using (owner_id = auth.uid())
    with check (owner_id = auth.uid());

create policy "Users can delete job cards they own"
    on public.job_cards for delete
    using (owner_id = auth.uid());

-- Job Card Users RLS policies
create policy "Users can view job card shares they are part of"
    on public.job_card_users for select
    using (user_id = auth.uid() or job_card_id in (
        select id from public.job_cards where owner_id = auth.uid()
    ));

create policy "Job card owners can manage shares"
    on public.job_card_users for all
    using (job_card_id in (
        select id from public.job_cards where owner_id = auth.uid()
    ));

-- Tasks RLS policies
create policy "Users can view tasks for job cards they have access to"
    on public.tasks for select
    using (
        job_card_id in (
            select id from public.job_cards 
            where owner_id = auth.uid() or 
            id in (
                select job_card_id from public.job_card_users 
                where user_id = auth.uid()
            )
        )
    );

create policy "Users can insert tasks for job cards they have access to"
    on public.tasks for insert
    with check (
        job_card_id in (
            select id from public.job_cards 
            where owner_id = auth.uid() or 
            id in (
                select job_card_id from public.job_card_users 
                where user_id = auth.uid() and role in ('owner', 'editor')
            )
        )
    );

create policy "Users can update tasks for job cards they have access to"
    on public.tasks for update
    using (
        job_card_id in (
            select id from public.job_cards 
            where owner_id = auth.uid() or 
            id in (
                select job_card_id from public.job_card_users 
                where user_id = auth.uid() and role in ('owner', 'editor')
            )
        )
    );

create policy "Users can delete tasks for job cards they own"
    on public.tasks for delete
    using (
        job_card_id in (
            select id from public.job_cards where owner_id = auth.uid()
        )
    );

-- Job Card Notes RLS policies
create policy "Users can view notes for job cards they have access to"
    on public.job_card_notes for select
    using (
        job_card_id in (
            select id from public.job_cards 
            where owner_id = auth.uid() or 
            id in (
                select job_card_id from public.job_card_users 
                where user_id = auth.uid()
            )
        )
    );

create policy "Users can insert notes for job cards they have access to"
    on public.job_card_notes for insert
    with check (
        job_card_id in (
            select id from public.job_cards 
            where owner_id = auth.uid() or 
            id in (
                select job_card_id from public.job_card_users 
                where user_id = auth.uid()
            )
        )
    );

-- Task Notes RLS policies
create policy "Users can view task notes for tasks they have access to"
    on public.task_notes for select
    using (
        task_id in (
            select id from public.tasks 
            where job_card_id in (
                select id from public.job_cards 
                where owner_id = auth.uid() or 
                id in (
                    select job_card_id from public.job_card_users 
                    where user_id = auth.uid()
                )
            )
        )
    );

create policy "Users can insert task notes for tasks they have access to"
    on public.task_notes for insert
    with check (
        task_id in (
            select id from public.tasks 
            where job_card_id in (
                select id from public.job_cards 
                where owner_id = auth.uid() or 
                id in (
                    select job_card_id from public.job_card_users 
                    where user_id = auth.uid()
                )
            )
        )
    );
