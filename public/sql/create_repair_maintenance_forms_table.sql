CREATE TABLE IF NOT EXISTS public.repair_maintenance_forms (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    form_date DATE NOT NULL,
    branch_id TEXT,
    station_name TEXT NOT NULL,
    repair_type TEXT NOT NULL,
    remarks TEXT,
    technician_name TEXT NOT NULL,
    department TEXT,
    requirements TEXT,
    shared_with TEXT,
    approved_by_users JSONB DEFAULT '[]'::jsonb,
    status TEXT DEFAULT 'pending',
    created_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_repair_maintenance_date
    ON public.repair_maintenance_forms(form_date);

ALTER TABLE public.repair_maintenance_forms
    ADD COLUMN IF NOT EXISTS shared_with TEXT,
    ADD COLUMN IF NOT EXISTS approved_by_users JSONB DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';

DROP POLICY IF EXISTS "Admins can view repair maintenance forms" ON public.repair_maintenance_forms;
DROP POLICY IF EXISTS "Admins can insert repair maintenance forms" ON public.repair_maintenance_forms;
DROP POLICY IF EXISTS "Admins can update repair maintenance forms" ON public.repair_maintenance_forms;
DROP POLICY IF EXISTS "Admins can delete repair maintenance forms" ON public.repair_maintenance_forms;

ALTER TABLE public.repair_maintenance_forms DISABLE ROW LEVEL SECURITY;
GRANT ALL ON TABLE public.repair_maintenance_forms TO anon, authenticated, service_role;