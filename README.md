# Texol Work Card System

A comprehensive PHP-based work card and ticket management system for Texol Energies, built with Supabase as the backend.

## Overview

The Texol Work Card System is an internal business application designed to streamline work order management, ticket tracking, incident reporting, and various digitized forms. It provides role-based access control, email notifications, and a responsive user interface.

## Features

### Core Modules

- **Dashboard**: Overview of work cards, tickets, and recent activities with real-time metrics
- **Tasks/Job Cards**: Collaborative task management with assignment capabilities
- **Tickets**: Comprehensive ticket management system with multiple assignees, priority levels, and status tracking
- **My Tickets**: Personal ticket view for individual users
- **Assigned Tickets**: View tickets assigned to technicians

### Digitized Forms

- **Requisitions**: Purchase and material requisition forms with approval workflow
- **Customer Feedback**: Customer service feedback collection and tracking
- **Attendance**: Meeting attendance tracking with digital signatures
- **Incident Form**: Incident reporting and management
- **Shortage Acknowledgement**: Material shortage reporting
- **Repair and Maintenance**: Equipment repair and maintenance requests

### Administration

- **Users**: User management with role assignment
- **Departments**: Organizational department management
- **Categories**: Ticket and work card categorization
- **Roles**: Role-based access control configuration
- **Permissions**: Granular permission management for modules
- **Branches**: Branch/location management
- **Items**: Inventory item management
- **Suppliers**: Supplier information management

### Additional Features

- **Profile Management**: User profile customization with picture upload
- **Email Notifications**: Automated email notifications for ticket assignments and updates
- **File Uploads**: Support for attachments in tickets, job cards, and forms
- **Responsive Design**: Mobile-friendly interface with collapsible sidebar
- **Dark Mode**: Built-in dark mode support
- **Remember Me**: Persistent login functionality

## Technology Stack

### Backend
- **PHP 8.2+**: Server-side scripting
- **Supabase**: PostgreSQL database with REST API and authentication
- **PHPMailer**: Email functionality

### Frontend
- **Bootstrap 5.3**: Responsive UI framework
- **Bootstrap Icons**: Icon library
- **Quill.js**: Rich text editor
- **DataTables**: Enhanced table functionality
- **Vanilla JavaScript**: Client-side interactivity

### Database
- **PostgreSQL**: Primary database (via Supabase)
- **Row Level Security (RLS)**: Database-level access control

## Project Structure

```
texol/
├── config.php              # Configuration and .env loader
├── .env                    # Environment variables (Supabase credentials)
├── .htaccess               # Apache URL rewriting rules
├── public/                 # Public web root
│   ├── index.php          # Main dashboard
│   ├── login.php          # Authentication page
│   ├── mytickets.php      # User ticket management
│   ├── job_cards.php      # Task/job card management
│   ├── meetings.php       # Meeting and attendance management
│   ├── incident.php       # Incident reporting
│   ├── departments.php    # Department management
│   ├── categories.php     # Category management
│   ├── profile.php        # User profile
│   ├── partials/          # Reusable components
│   │   ├── sidebar.php   # Navigation sidebar
│   │   └── navbar_user.php # User navbar component
│   ├── uploads/          # File upload directories
│   ├── PHPMailer/        # Email library
│   └── sql/              # Database migration scripts
├── sql/                   # Additional SQL scripts
├── supabase/             # Supabase-specific SQL scripts
└── migrations/           # Database migration files
```

## Installation

### Prerequisites

- PHP 8.2 or higher
- Apache web server with mod_rewrite enabled
- Supabase account and project
- cURL PHP extension
- GD PHP extension (for image processing)

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd texol
   ```

2. **Configure environment variables**
   ```bash
   cp .env.example .env
   ```
   Edit `.env` and add your Supabase credentials:
   ```
   SUPABASE_URL=your-supabase-project-url
   SUPABASE_ANON_KEY=your-supabase-anon-key
   ```

3. **Set up database tables**
   Run the SQL scripts in the following order:
   - `supabase/ensure_users_table.sql`
   - `supabase/departments.sql`
   - `supabase/roles.sql`
   - `supabase/job_cards.sql`
   - `sql/tickets_schema.sql`
   - `public/sql/create_meetings_tables.sql`
   - `public/sql/create_incidents_table.sql`
   - `public/sql/create_repair_maintenance_forms_table.sql`
   - `public/sql/create_shortage_acknowledgements_table.sql`

4. **Configure web server**
   - Point your web server to the `public/` directory
   - Ensure mod_rewrite is enabled for clean URLs
   - The `.htaccess` file handles URL rewriting

5. **Set file permissions**
   ```bash
   chmod 755 public/uploads
   chmod 755 public/uploads/*
   ```

## Database Schema

### Core Tables

- **users**: User accounts with authentication and profile information
- **tickets**: Work tickets with comprehensive tracking fields
- **ticket_assignees**: Many-to-many relationship for ticket assignments
- **ticket_notes**: Ticket history and comments
- **job_cards**: Collaborative task cards
- **tasks**: Individual tasks within job cards
- **meetings**: Meeting scheduling and management
- **meeting_attendance**: Meeting attendance tracking
- **incidents**: Incident reporting
- **departments**: Organizational departments
- **categories**: Ticket and task categories
- **branches**: Business locations/branches

### Security Features

- Row Level Security (RLS) on all major tables
- Role-based access control
- Permission system for granular access
- Session-based authentication
- CSRF protection on forms

## User Roles

- **Admin**: Full system access and configuration
- **HOD (Head of Department)**: Department-level management
- **Technician**: Task assignment and completion
- **Call Center Agent**: Customer feedback management
- **User**: Basic ticket creation and personal dashboard access

## Configuration

### Environment Variables

The `.env` file contains:
- `SUPABASE_URL`: Your Supabase project URL
- `SUPABASE_ANON_KEY`: Supabase anonymous/public key for API access

### Permission System

Permissions are managed through the `user_permissions` table and checked via the `check_permission()` function in `config.php`. Each module can have specific actions (view, create, edit, delete, all).

## Development

### Adding New Modules

1. Create the PHP file in `public/`
2. Add corresponding database tables/migrations
3. Update sidebar navigation in `public/partials/sidebar.php`
4. Add permissions to the database
5. Include session check and permission validation

### Email Configuration

Email functionality uses PHPMailer. Configure SMTP settings in the respective notification files:
- `notify_ticket.php`
- `notify_task.php`
- `notify_requisition.php`

## Security Considerations

- Never commit `.env` file with real credentials
- Use strong passwords for user accounts
- Regularly update Supabase API keys
- Implement rate limiting for API calls
- Keep PHP and dependencies updated
- Use HTTPS in production

## Troubleshooting

### Common Issues

1. **Login not working**: Verify Supabase credentials in `.env`
2. **File uploads failing**: Check directory permissions on `public/uploads/`
3. **Database connection errors**: Ensure Supabase project is active and RLS policies are correct
4. **URL rewriting issues**: Verify mod_rewrite is enabled and `.htaccess` is being read

## Support

For issues and questions related to this system, please contact the development team or submit an issue through the internal project management system.

## License

Internal use only - Texol Energies proprietary software.