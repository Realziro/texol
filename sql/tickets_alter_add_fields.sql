-- Add Missing Fields to Existing Tickets Table
-- This script uses ALTER TABLE to add only the fields that don't exist

-- First, let's check what columns exist and add the missing ones
-- Note: Some columns might already exist, so we'll add them conditionally

-- Add requester field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'requester'
    ) THEN
        ALTER TABLE tickets ADD COLUMN requester VARCHAR(255) NOT NULL DEFAULT '';
        RAISE NOTICE 'Added requester column';
    END IF;
END $$;

-- Add source field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'source'
    ) THEN
        ALTER TABLE tickets ADD COLUMN source VARCHAR(50) NOT NULL 
        CHECK (source IN ('phone', 'email', 'portal', 'chat', 'feedback')) DEFAULT 'portal';
        RAISE NOTICE 'Added source column';
    END IF;
END $$;

-- Add category field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'category'
    ) THEN
        ALTER TABLE tickets ADD COLUMN category VARCHAR(50);
        RAISE NOTICE 'Added category column';
    END IF;
END $$;

-- Add urgency field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'urgency'
    ) THEN
        ALTER TABLE tickets ADD COLUMN urgency VARCHAR(10) NOT NULL 
        CHECK (urgency IN ('low', 'medium', 'high', 'urgent')) DEFAULT 'medium';
        RAISE NOTICE 'Added urgency column';
    END IF;
END $$;

-- Add impact field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'impact'
    ) THEN
        ALTER TABLE tickets ADD COLUMN impact VARCHAR(10) NOT NULL 
        CHECK (impact IN ('low', 'medium', 'high')) DEFAULT 'medium';
        RAISE NOTICE 'Added impact column';
    END IF;
END $$;

-- Add planned_start_date field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'planned_start_date'
    ) THEN
        ALTER TABLE tickets ADD COLUMN planned_start_date TIMESTAMP;
        RAISE NOTICE 'Added planned_start_date column';
    END IF;
END $$;

-- Add planned_end_date field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'planned_end_date'
    ) THEN
        ALTER TABLE tickets ADD COLUMN planned_end_date TIMESTAMP;
        RAISE NOTICE 'Added planned_end_date column';
    END IF;
END $$;

-- Add resolved_at field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'resolved_at'
    ) THEN
        ALTER TABLE tickets ADD COLUMN resolved_at TIMESTAMP;
        RAISE NOTICE 'Added resolved_at column';
    END IF;
END $$;

-- Add closed_at field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'closed_at'
    ) THEN
        ALTER TABLE tickets ADD COLUMN closed_at TIMESTAMP;
        RAISE NOTICE 'Added closed_at column';
    END IF;
END $$;

-- Add assigned_to field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'assigned_to'
    ) THEN
        ALTER TABLE tickets ADD COLUMN assigned_to VARCHAR(255);
        RAISE NOTICE 'Added assigned_to column';
    END IF;
END $$;

-- Add resolution_notes field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'resolution_notes'
    ) THEN
        ALTER TABLE tickets ADD COLUMN resolution_notes TEXT;
        RAISE NOTICE 'Added resolution_notes column';
    END IF;
END $$;

-- Add attachments field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'attachments'
    ) THEN
        ALTER TABLE tickets ADD COLUMN attachments JSONB;
        RAISE NOTICE 'Added attachments column';
    END IF;
END $$;

-- Add tags field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'tags'
    ) THEN
        ALTER TABLE tickets ADD COLUMN tags TEXT[];
        RAISE NOTICE 'Added tags column';
    END IF;
END $$;

-- Add estimated_hours field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'estimated_hours'
    ) THEN
        ALTER TABLE tickets ADD COLUMN estimated_hours DECIMAL(10,2);
        RAISE NOTICE 'Added estimated_hours column';
    END IF;
END $$;

-- Add actual_hours field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'actual_hours'
    ) THEN
        ALTER TABLE tickets ADD COLUMN actual_hours DECIMAL(10,2);
        RAISE NOTICE 'Added actual_hours column';
    END IF;
END $$;

-- Add updated_at field if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tickets' AND column_name = 'updated_at'
    ) THEN
        ALTER TABLE tickets ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
        RAISE NOTICE 'Added updated_at column';
    END IF;
END $$;

-- Add date validation constraint if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.check_constraints 
        WHERE constraint_name = 'check_dates' AND table_name = 'tickets'
    ) THEN
        ALTER TABLE tickets ADD CONSTRAINT check_dates CHECK (
            (planned_start_date IS NULL OR planned_end_date IS NULL OR planned_start_date <= planned_end_date)
        );
        RAISE NOTICE 'Added check_dates constraint';
    END IF;
END $$;

-- Add priority_urgency validation constraint if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.check_constraints 
        WHERE constraint_name = 'check_priority_urgency' AND table_name = 'tickets'
    ) THEN
        ALTER TABLE tickets ADD CONSTRAINT check_priority_urgency CHECK (
            (urgency = 'urgent' AND priority IN ('high', 'urgent')) OR
            (urgency != 'urgent')
        );
        RAISE NOTICE 'Added check_priority_urgency constraint';
    END IF;
END $$;

-- Create/update trigger for updated_at if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.triggers 
        WHERE trigger_name = 'update_tickets_updated_at' AND table_name = 'tickets'
    ) THEN
        -- Create trigger function if it doesn't exist
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.routines 
            WHERE routine_name = 'update_updated_at_column'
        ) THEN
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language 'plpgsql';
            RAISE NOTICE 'Created update_updated_at_column function';
        END IF;
        
        -- Create trigger
        CREATE TRIGGER update_tickets_updated_at 
            BEFORE UPDATE ON tickets 
            FOR EACH ROW 
            EXECUTE FUNCTION update_updated_at_column();
        RAISE NOTICE 'Created update_tickets_updated_at trigger';
    END IF;
END $$;

-- Create indexes for new columns if they don't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_tickets_urgency' AND table_name = 'tickets'
    ) THEN
        CREATE INDEX idx_tickets_urgency ON tickets(urgency);
        RAISE NOTICE 'Created idx_tickets_urgency index';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_tickets_impact' AND table_name = 'tickets'
    ) THEN
        CREATE INDEX idx_tickets_impact ON tickets(impact);
        RAISE NOTICE 'Created idx_tickets_impact index';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_tickets_updated_at' AND table_name = 'tickets'
    ) THEN
        CREATE INDEX idx_tickets_updated_at ON tickets(updated_at);
        RAISE NOTICE 'Created idx_tickets_updated_at index';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_tickets_planned_start_date' AND table_name = 'tickets'
    ) THEN
        CREATE INDEX idx_tickets_planned_start_date ON tickets(planned_start_date);
        RAISE NOTICE 'Created idx_tickets_planned_start_date index';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_tickets_planned_end_date' AND table_name = 'tickets'
    ) THEN
        CREATE INDEX idx_tickets_planned_end_date ON tickets(planned_end_date);
        RAISE NOTICE 'Created idx_tickets_planned_end_date index';
    END IF;
END $$;

-- Create departments table if it doesn't exist
CREATE TABLE IF NOT EXISTS departments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    manager_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT true
);

-- Insert default departments if table is empty
INSERT INTO departments (name, description) VALUES 
('Maintenance', 'Maintenance and repair services'),
('Production', 'Production and manufacturing'),
('Quality', 'Quality control and assurance'),
('Engineering', 'Engineering and technical services'),
('HSE', 'Health, Safety, and Environment'),
('IT', 'Information Technology'),
('HR', 'Human Resources'),
('Finance', 'Finance and accounting')
ON CONFLICT (name) DO NOTHING;

-- Create ticket_assignees table if it doesn't exist
CREATE TABLE IF NOT EXISTS ticket_assignees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    technician_email VARCHAR(255) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by VARCHAR(255),
    is_primary BOOLEAN DEFAULT false,
    
    UNIQUE(ticket_id, technician_email)
);

-- Create index for ticket_assignees if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_ticket_assignees_ticket_id' AND table_name = 'ticket_assignees'
    ) THEN
        CREATE INDEX idx_ticket_assignees_ticket_id ON ticket_assignees(ticket_id);
        RAISE NOTICE 'Created idx_ticket_assignees_ticket_id index';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_ticket_assignees_technician_email' AND table_name = 'ticket_assignees'
    ) THEN
        CREATE INDEX idx_ticket_assignees_technician_email ON ticket_assignees(technician_email);
        RAISE NOTICE 'Created idx_ticket_assignees_technician_email index';
    END IF;
END $$;

-- Create ticket_notes table if it doesn't exist
CREATE TABLE IF NOT EXISTS ticket_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    note TEXT NOT NULL,
    created_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note_type VARCHAR(20) DEFAULT 'comment' CHECK (note_type IN ('comment', 'status_change', 'assignment', 'resolution'))
);

-- Create indexes for ticket_notes if they don't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_ticket_notes_ticket_id' AND table_name = 'ticket_notes'
    ) THEN
        CREATE INDEX idx_ticket_notes_ticket_id ON ticket_notes(ticket_id);
        RAISE NOTICE 'Created idx_ticket_notes_ticket_id index';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.indexes 
        WHERE index_name = 'idx_ticket_notes_created_at' AND table_name = 'ticket_notes'
    ) THEN
        CREATE INDEX idx_ticket_notes_created_at ON ticket_notes(created_at);
        RAISE NOTICE 'Created idx_ticket_notes_created_at index';
    END IF;
END $$;

-- Comments for new columns
COMMENT ON COLUMN tickets.requester IS 'Name or identifier of the person requesting the ticket';
COMMENT ON COLUMN tickets.source IS 'How ticket was submitted: phone, email, portal, chat, feedback';
COMMENT ON COLUMN tickets.category IS 'Category classification: hardware, software, network, infrastructure, other';
COMMENT ON COLUMN tickets.urgency IS 'How quickly ticket needs attention: low, medium, high, urgent';
COMMENT ON COLUMN tickets.impact IS 'Business impact of the issue: low, medium, high';
COMMENT ON COLUMN tickets.planned_start_date IS 'Scheduled start date for resolution work';
COMMENT ON COLUMN tickets.planned_end_date IS 'Target completion date for the ticket';
COMMENT ON COLUMN tickets.resolved_at IS 'Timestamp when ticket was marked as resolved';
COMMENT ON COLUMN tickets.closed_at IS 'Timestamp when ticket was marked as closed';
COMMENT ON COLUMN tickets.assigned_to IS 'Primary person/team assigned to the ticket';
COMMENT ON COLUMN tickets.resolution_notes IS 'Notes about how the ticket was resolved';
COMMENT ON COLUMN tickets.attachments IS 'JSON array of attached file information';
COMMENT ON COLUMN tickets.tags IS 'Array of tags for better ticket categorization and search';
COMMENT ON COLUMN tickets.estimated_hours IS 'Estimated time to complete the ticket';
COMMENT ON COLUMN tickets.actual_hours IS 'Actual time spent on the ticket';

-- Final notice
RAISE NOTICE 'Ticket table enhancement completed successfully!';
