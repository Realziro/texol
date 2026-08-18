-- Add email fields to job card tables to work with session-based authentication

-- Add email field to job_cards table
ALTER TABLE job_cards ADD COLUMN owner_email text;

-- Add email field to job_card_users table  
ALTER TABLE job_card_users ADD COLUMN user_email text;

-- Add email field to tasks table
ALTER TABLE tasks ADD COLUMN assigned_to_email text;

-- Update existing records to populate email fields from users table
UPDATE job_cards SET owner_email = users.email 
FROM users WHERE job_cards.owner_id = users.id;

UPDATE job_card_users SET user_email = users.email 
FROM users WHERE job_card_users.user_id = users.id;

UPDATE tasks SET assigned_to_email = users.email 
FROM users WHERE tasks.assigned_to = users.id;

-- Create indexes for email fields for better performance
CREATE INDEX IF NOT EXISTS idx_job_cards_owner_email ON job_cards(owner_email);
CREATE INDEX IF NOT EXISTS idx_job_card_users_user_email ON job_card_users(user_email);
CREATE INDEX IF NOT EXISTS idx_tasks_assigned_to_email ON tasks(assigned_to_email);
