-- Create shortage acknowledgement records
CREATE TABLE IF NOT EXISTS public.shortage_acknowledgements (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    form_date DATE NOT NULL,
    reference_pcv_no TEXT,
    attendant_name TEXT NOT NULL,
    department TEXT NOT NULL,
    branch_id TEXT,
    station_name TEXT,
    pump TEXT NOT NULL,
    shift TEXT NOT NULL,
    shortage_amount NUMERIC(14, 2) NOT NULL,
    amount_words TEXT,
    comments TEXT,
    payment_plan TEXT,
    shared_with TEXT,
    approved_by_users JSONB DEFAULT '[]'::jsonb,
    status TEXT DEFAULT 'pending',
    created_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_shortage_ack_date
    ON public.shortage_acknowledgements(form_date);

ALTER TABLE public.shortage_acknowledgements
    ADD COLUMN IF NOT EXISTS shared_with TEXT,
    ADD COLUMN IF NOT EXISTS approved_by_users JSONB DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';

DROP POLICY IF EXISTS "Admins can view shortage acknowledgements" ON public.shortage_acknowledgements;
DROP POLICY IF EXISTS "Admins can insert shortage acknowledgements" ON public.shortage_acknowledgements;
DROP POLICY IF EXISTS "Admins can update shortage acknowledgements" ON public.shortage_acknowledgements;
DROP POLICY IF EXISTS "Admins can delete shortage acknowledgements" ON public.shortage_acknowledgements;

ALTER TABLE public.shortage_acknowledgements DISABLE ROW LEVEL SECURITY;
GRANT ALL ON TABLE public.shortage_acknowledgements TO anon, authenticated, service_role;