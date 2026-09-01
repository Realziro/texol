-- Create meetings table
CREATE TABLE IF NOT EXISTS meetings (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    title TEXT NOT NULL,
    type TEXT NOT NULL CHECK (type IN ('training', 'meeting')),
    date DATE NOT NULL,
    time TIME NOT NULL,
    location TEXT,
    description TEXT,
    minutes TEXT,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create meeting_attendance table
CREATE TABLE IF NOT EXISTS meeting_attendance (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    meeting_id UUID NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    attendee_name TEXT NOT NULL,
    attendee_email TEXT,
    signature TEXT,
    signed_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_meetings_created_by ON meetings(created_by);
CREATE INDEX IF NOT EXISTS idx_meetings_date ON meetings(date);
CREATE INDEX IF NOT EXISTS idx_meeting_attendance_meeting_id ON meeting_attendance(meeting_id);
CREATE INDEX IF NOT EXISTS idx_meeting_attendance_email ON meeting_attendance(attendee_email);

-- Enable Row Level Security
ALTER TABLE meetings ENABLE ROW LEVEL SECURITY;
ALTER TABLE meeting_attendance ENABLE ROW LEVEL SECURITY;

-- RLS Policies for meetings
CREATE POLICY "Admins can view all meetings" ON meetings
    FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM users
            WHERE users.id = auth.uid()
            AND users.role = 'admin'
        )
    );

CREATE POLICY "Admins can insert meetings" ON meetings
    FOR INSERT
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM users
            WHERE users.id = auth.uid()
            AND users.role = 'admin'
        )
    );

CREATE POLICY "Admins can update meetings" ON meetings
    FOR UPDATE
    USING (
        EXISTS (
            SELECT 1 FROM users
            WHERE users.id = auth.uid()
            AND users.role = 'admin'
        )
    );

CREATE POLICY "Admins can delete meetings" ON meetings
    FOR DELETE
    USING (
        EXISTS (
            SELECT 1 FROM users
            WHERE users.id = auth.uid()
            AND users.role = 'admin'
        )
    );

-- RLS Policies for meeting_attendance
CREATE POLICY "Anyone can view attendance for their meeting" ON meeting_attendance
    FOR SELECT
    USING (true);

CREATE POLICY "Anyone can insert attendance" ON meeting_attendance
    FOR INSERT
    WITH CHECK (true);

CREATE POLICY "Admins can update attendance" ON meeting_attendance
    FOR UPDATE
    USING (
        EXISTS (
            SELECT 1 FROM users
            WHERE users.id = auth.uid()
            AND users.role = 'admin'
        )
    );

CREATE POLICY "Admins can delete attendance" ON meeting_attendance
    FOR DELETE
    USING (
        EXISTS (
            SELECT 1 FROM users
            WHERE users.id = auth.uid()
            AND users.role = 'admin'
        )
    );
