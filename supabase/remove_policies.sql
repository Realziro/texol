-- Remove all RLS policies from job card tables to make them unrestricted

-- Disable RLS on all tables first
ALTER TABLE job_cards DISABLE ROW LEVEL SECURITY;
ALTER TABLE job_card_users DISABLE ROW LEVEL SECURITY;
ALTER TABLE tasks DISABLE ROW LEVEL SECURITY;
ALTER TABLE job_card_notes DISABLE ROW LEVEL SECURITY;
ALTER TABLE task_notes DISABLE ROW LEVEL SECURITY;

-- Drop all existing policies
DROP POLICY IF EXISTS "Users can view their own job cards" ON job_cards;
DROP POLICY IF EXISTS "Users can insert their own job cards" ON job_cards;
DROP POLICY IF EXISTS "Users can update their own job cards" ON job_cards;
DROP POLICY IF EXISTS "Users can delete their own job cards" ON job_cards;

DROP POLICY IF EXISTS "Users can view job cards they have access to" ON job_card_users;
DROP POLICY IF EXISTS "Users can insert job card shares" ON job_card_users;
DROP POLICY IF EXISTS "Users can update their own job card shares" ON job_card_users;
DROP POLICY IF EXISTS "Users can delete their own job card shares" ON job_card_users;

DROP POLICY IF EXISTS "Users can view tasks for job cards they have access to" ON tasks;
DROP POLICY IF EXISTS "Users can insert tasks for job cards they can edit" ON tasks;
DROP POLICY IF EXISTS "Users can update tasks for job cards they can edit" ON tasks;
DROP POLICY IF EXISTS "Users can delete tasks for job cards they can edit" ON tasks;

DROP POLICY IF EXISTS "Users can view notes for job cards they have access to" ON job_card_notes;
DROP POLICY IF EXISTS "Users can insert notes for job cards they can edit" ON job_card_notes;
DROP POLICY IF EXISTS "Users can update their own job card notes" ON job_card_notes;
DROP POLICY IF EXISTS "Users can delete their own job card notes" ON job_card_notes;

DROP POLICY IF EXISTS "Users can view notes for tasks they have access to" ON task_notes;
DROP POLICY IF EXISTS "Users can insert notes for tasks they can edit" ON task_notes;
DROP POLICY IF EXISTS "Users can update their own task notes" ON task_notes;
DROP POLICY IF EXISTS "Users can delete their own task notes" ON task_notes;

-- Keep RLS disabled for unrestricted access
-- Tables are now completely unrestricted without any policies
