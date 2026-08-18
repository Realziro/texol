-- Add phone column to users table
-- This script adds a phone number column to the existing users table

DO $$
BEGIN
    -- Add phone column if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' 
        AND column_name = 'phone'
    ) THEN
        ALTER TABLE users 
        ADD COLUMN phone VARCHAR(20);
        
        -- Add comment for documentation
        COMMENT ON COLUMN users.phone IS 'Phone number of the user (optional)';
        
        RAISE NOTICE 'Phone column added successfully to users table';
    ELSE
        RAISE NOTICE 'Phone column already exists in users table';
    END IF;
END $$;

-- Create index for phone number if it doesn't exist (useful for lookups)
CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);

-- Verify the column was added
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'users' 
AND column_name = 'phone';
