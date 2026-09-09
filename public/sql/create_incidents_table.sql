-- Create incident report records
CREATE TABLE IF NOT EXISTS incidents (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    incident_date DATE NOT NULL,
    incident_time TIME NOT NULL,
    reporter_name TEXT NOT NULL,
    department TEXT NOT NULL,
    shared_with TEXT,
    approved_by_users JSONB DEFAULT '[]'::jsonb,
    status TEXT DEFAULT 'pending',
    branch_id TEXT,
    station_name TEXT NOT NULL,
    shift TEXT NOT NULL,
    description TEXT NOT NULL,
    witness1_name TEXT,
    witness1_position TEXT,
    witness2_name TEXT,
    witness2_position TEXT,
    manager_comments TEXT,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_incidents_date ON incidents(incident_date);
CREATE INDEX IF NOT EXISTS idx_incidents_branch_id ON incidents(branch_id);

ALTER TABLE public.incidents
    ADD COLUMN IF NOT EXISTS shared_with TEXT,
    ADD COLUMN IF NOT EXISTS approved_by_users JSONB DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';

DROP POLICY IF EXISTS "Admins can view incidents" ON public.incidents;
DROP POLICY IF EXISTS "Admins can insert incidents" ON public.incidents;
DROP POLICY IF EXISTS "Admins can delete incidents" ON public.incidents;
ALTER TABLE public.incidents DISABLE ROW LEVEL SECURITY;
GRANT ALL ON TABLE public.incidents TO anon, authenticated, service_role;