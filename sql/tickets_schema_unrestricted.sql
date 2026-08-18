-- Enhanced Tickets Table Schema - Unrestricted Version
-- This SQL script drops existing tickets table and creates new one without RLS restrictions

-- Drop existing tables and dependencies (order matters due to foreign keys)
DROP TABLE IF EXISTS ticket_notes CASCADE;
DROP TABLE IF EXISTS ticket_assignees CASCADE;
DROP TABLE IF EXISTS tickets CASCADE;
DROP TABLE IF EXISTS departments CASCADE;

-- Drop trigger function if it exists
DROP FUNCTION IF EXISTS update_updated_at_column() CASCADE;

-- Create departments table first (needed for foreign key)
CREATE TABLE departments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    manager_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT true
);

-- Insert default departments
INSERT INTO departments (name, description) VALUES 
('Maintenance', 'Maintenance and repair services'),
('Production', 'Production and manufacturing'),
('Quality', 'Quality control and assurance'),
('Engineering', 'Engineering and technical services'),
('HSE', 'Health, Safety, and Environment'),
('IT', 'Information Technology'),
('HR', 'Human Resources'),
('Finance', 'Finance and accounting');

-- Create enhanced tickets table
CREATE TABLE tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    requester VARCHAR(255) NOT NULL,
    source VARCHAR(50) NOT NULL CHECK (source IN ('phone', 'email', 'portal', 'chat', 'feedback')),
    description TEXT NOT NULL,
    department VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    status VARCHAR(20) NOT NULL DEFAULT 'Open' CHECK (status IN ('Open', 'Pending', 'In Progress', 'Resolved', 'Closed')),
    urgency VARCHAR(10) NOT NULL DEFAULT 'medium' CHECK (urgency IN ('low', 'medium', 'high', 'urgent')),
    impact VARCHAR(10) NOT NULL DEFAULT 'medium' CHECK (impact IN ('low', 'medium', 'high')),
    priority VARCHAR(10) NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    planned_start_date TIMESTAMP,
    planned_end_date TIMESTAMP,
    due_date TIMESTAMP,
    requested_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP,
    
    -- Additional metadata
    assigned_to VARCHAR(255),
    resolution_notes TEXT,
    attachments JSONB, -- Store file paths and metadata
    tags TEXT[], -- Array of tags for categorization
    estimated_hours DECIMAL(10,2),
    actual_hours DECIMAL(10,2),
    
    -- Constraints
    CONSTRAINT check_dates CHECK (
        (planned_start_date IS NULL OR planned_end_date IS NULL OR planned_start_date <= planned_end_date)
    ),
    CONSTRAINT check_priority_urgency CHECK (
        (urgency = 'urgent' AND priority IN ('high', 'urgent')) OR
        (urgency != 'urgent')
    )
);

-- Create indexes for better performance
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_priority ON tickets(priority);
CREATE INDEX idx_tickets_department ON tickets(department);
CREATE INDEX idx_tickets_requested_by ON tickets(requested_by);
CREATE INDEX idx_tickets_created_at ON tickets(created_at);
CREATE INDEX idx_tickets_due_date ON tickets(due_date);

-- Create trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_tickets_updated_at 
    BEFORE UPDATE ON tickets 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- Create ticket_assignees table for multiple assignees
CREATE TABLE ticket_assignees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    technician_email VARCHAR(255) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by VARCHAR(255),
    is_primary BOOLEAN DEFAULT false,
    
    UNIQUE(ticket_id, technician_email)
);

-- Create indexes for ticket_assignees
CREATE INDEX idx_ticket_assignees_ticket_id ON ticket_assignees(ticket_id);
CREATE INDEX idx_ticket_assignees_technician_email ON ticket_assignees(technician_email);

-- Create ticket_notes table for tracking ticket history
CREATE TABLE ticket_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    note TEXT NOT NULL,
    created_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note_type VARCHAR(20) DEFAULT 'comment' CHECK (note_type IN ('comment', 'status_change', 'assignment', 'resolution'))
);

-- Create indexes for ticket_notes
CREATE INDEX idx_ticket_notes_ticket_id ON ticket_notes(ticket_id);
CREATE INDEX idx_ticket_notes_created_at ON ticket_notes(created_at);

-- Comments for documentation
COMMENT ON TABLE tickets IS 'Enhanced tickets table with comprehensive tracking fields - UNRESTRICTED ACCESS';
COMMENT ON TABLE ticket_assignees IS 'Many-to-many relationship between tickets and technicians - UNRESTRICTED ACCESS';
COMMENT ON TABLE ticket_notes IS 'Audit trail and notes for tickets - UNRESTRICTED ACCESS';
COMMENT ON TABLE departments IS 'Department information for ticket categorization - UNRESTRICTED ACCESS';

-- Column comments
COMMENT ON COLUMN tickets.source IS 'How ticket was submitted: phone, email, portal, chat, feedback';
COMMENT ON COLUMN tickets.urgency IS 'How quickly ticket needs attention: low, medium, high, urgent';
COMMENT ON COLUMN tickets.impact IS 'Business impact of the issue: low, medium, high';
COMMENT ON COLUMN tickets.priority IS 'Overall priority considering urgency and impact: low, medium, high, urgent';
COMMENT ON COLUMN tickets.planned_start_date IS 'Scheduled start date for resolution work';
COMMENT ON COLUMN tickets.planned_end_date IS 'Target completion date for ticket';
COMMENT ON COLUMN tickets.attachments IS 'JSON array of attached file information';
COMMENT ON COLUMN tickets.tags IS 'Array of tags for better ticket categorization and search';

-- Grant public access to all tables (unrestricted)
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO PUBLIC;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO PUBLIC;

-- Disable Row Level Security (keep tables unrestricted)
-- ALTER TABLE tickets DISABLE ROW LEVEL SECURITY;
-- ALTER TABLE ticket_notes DISABLE ROW LEVEL SECURITY;
-- ALTER TABLE ticket_assignees DISABLE ROW LEVEL SECURITY;
-- ALTER TABLE departments DISABLE ROW LEVEL SECURITY;

-- NO RLS policies created - tables remain unrestricted
