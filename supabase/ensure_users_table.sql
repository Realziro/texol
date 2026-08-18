-- Check users table structure and create if needed
-- This script ensures we have a users table with UUID and email fields

CREATE TABLE IF NOT EXISTS public.users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email text UNIQUE NOT NULL,
    full_name text,
    role text DEFAULT 'user',
    status text DEFAULT 'active',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create index for email lookups
CREATE INDEX IF NOT EXISTS idx_users_email ON public.users(email);

-- Insert current session user if not exists (for testing)
-- This is just for development - remove in production
INSERT INTO public.users (email, full_name, role, status)
VALUES (
    'test@example.com', 
    'Test User', 
    'user', 
    'active'
) ON CONFLICT (email) DO NOTHING;
