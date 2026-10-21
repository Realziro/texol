ALTER TABLE public.requisitions
    ADD COLUMN IF NOT EXISTS branch TEXT;
