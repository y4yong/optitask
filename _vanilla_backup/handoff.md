# OptiTask — Project Handoff Document

> **Project**: OptiTask — AI-Powered Task Management System  
> **Developed for**: FTSM, Universiti Kebangsaan Malaysia (UKM)  
> **Last Updated**: July 2026  

---

## 1. Project Overview

OptiTask is a **role-based task management web application** built with PHP and MySQL, designed to streamline task assignment, tracking, and performance evaluation within an organization. It features three distinct user roles — **Admin**, **Manager**, and **Employee** — each with their own dashboard and set of capabilities.

Key highlights:
- Role-based access control (Admin / Manager / Employee)
- AI-powered coaching via Google Gemini API (stuck task detection, productivity insights)
- Email notifications via PHPMailer (SMTP/Gmail)
- Audit logging for all critical actions
- Skill proficiency tracking and AI-based candidate suggestions
- Premium soft-pink glassmorphism UI using TailwindCSS CDN, Quicksand & Outfit fonts

---

## 2. Technology Stack

| Layer         | Technology                                               |
|---------------|----------------------------------------------------------|
| Backend       | PHP (vanilla, no framework)                              |
| Database      | MySQL (via `mysqli` extension)                           |
| Frontend      | HTML, TailwindCSS (CDN), Vanilla JavaScript              |
| Fonts         | Google Fonts — Quicksand (body), Outfit (headings)       |
| Icons         | Lucide Icons, Ionicons                                   |
| Alerts        | SweetAlert2                                              |
| Email         | PHPMailer (SMTP via Gmail)                               |
| AI            | Google Gemini API                                        |
| Server        | Laragon (Apache + MySQL, local development)              |

---

## 3. Directory Structure

```
optitask/
├── admin/                        # Admin-role pages
│   ├── audit.php                 # Audit log viewer
│   ├── dashboard_admin.php       # Admin dashboard
│   ├── get_user_skills.php       # AJAX endpoint for user skills
│   └── manage_users.php          # User management (CRUD, suspend/activate)
│
├── employee/                     # Employee-role pages
│   ├── ai_coach_helper.php       # Gemini AI coach logic (hint generation)
│   ├── ajax_ai_assistant.php     # AJAX endpoint for AI assistant widget
│   ├── dashboard_employee.php    # Employee dashboard
│   ├── notification.php          # Employee notification center
│   ├── performance.php           # Performance analytics page
│   ├── skills.php                # Skill profile & self-assessment
│   ├── tasks.php                 # Task list & detail view
│   └── update_tasks.php          # Task submission & evidence upload
│
├── manager/                      # Manager-role pages
│   ├── assign_tasks.php          # Task assignment form
│   ├── dashboard_manager.php     # Manager dashboard (KPIs, charts)
│   ├── notification.php          # Manager notification center
│   ├── suggest_candidate.php     # AI-based candidate suggestion endpoint
│   └── verify_tasks.php          # Task verification & approval
│
├── assets/
│   └── includes/                 # (Reserved for shared partials)
│
├── PHPMailer/                    # PHPMailer library (local, not Composer)
│   └── src/
│       ├── Exception.php
│       ├── PHPMailer.php
│       └── SMTP.php
│
├── uploads/                      # File uploads (task attachments, evidence)
│
├── config_ai.php                 # Gemini API key (EXCLUDED from git)
├── config_ai.example.php         # Template — copy and add your API key
├── db_config.php                 # MySQL connection + log_audit() helper
├── db_setup_helper.php           # One-click DB installer & seed script
├── email_helper.php              # send_email_notification() function
├── login.php                     # Authentication (login form + handler)
├── signup.php                    # Registration (with security key gate)
├── logout.php                    # Session destroy + redirect
├── cron_stuck_detector.php       # Scheduled script — stuck task AI tips
├── setup_skills.php              # Utility to seed skills table
├── patch_logout.php              # Logout session patch utility
├── create_audit_table.php        # Migration: create audit_logs table
├── alter.php                     # Migration: schema alteration script
├── check_audit_logs.php          # Diagnostic: verify audit_logs table
├── check_cols.php                # Diagnostic: inspect column structure
├── check_dept.php                # Diagnostic: verify departments table
├── diagnostic_db.php             # Quick DB connection diagnostic
├── test_mail.php                 # Email sending test page
├── .gitignore
└── handoff.md                    # ← This file
```

---

## 4. Database Schema

**Database name**: `optitask`  
**Character set**: `utf8mb4` / `utf8mb4_unicode_ci`

### Tables

| Table              | Purpose                                      |
|--------------------|----------------------------------------------|
| `departments`      | Department list (IT, HR, Marketing, Finance)  |
| `users`            | All user accounts (Admin, Manager, Employee)  |
| `tasks`            | Task records with status, priority, files     |
| `notifications`    | In-app notification messages per user         |
| `audit_logs`       | Immutable log of all system actions           |
| `skills`           | Catalogue of available skills                 |
| `employee_skills`  | Junction table: user ↔ skill (with proficiency 1–5) |

### Key Columns — `users`

| Column              | Type           | Notes                                |
|---------------------|----------------|--------------------------------------|
| `user_id`           | VARCHAR(50) PK | Prefixed: `AD___`, `MG___`, `EM___`  |
| `username`          | VARCHAR(100)   | Full name                            |
| `email`             | VARCHAR(100)   | Unique                               |
| `password`          | VARCHAR(255)   | `password_hash()` / bcrypt           |
| `role`              | VARCHAR(50)    | `Admin`, `Manager`, or `Employee`    |
| `account_status`    | VARCHAR(20)    | `Active` or `Suspended`              |
| `suspension_reason` | VARCHAR(255)   | Nullable                             |
| `dept_id`           | INT FK         | References `departments.dept_id`     |

### Key Columns — `tasks`

| Column            | Type           | Notes                                 |
|-------------------|----------------|---------------------------------------|
| `task_id`         | VARCHAR(50) PK | Unique task identifier                |
| `task_title`      | VARCHAR(255)   |                                       |
| `description`     | TEXT           |                                       |
| `start_date`      | DATE           |                                       |
| `due_date`        | DATE           |                                       |
| `task_status`     | VARCHAR(50)    | `To-Do`, `In Progress`, `Done`, `Verified` |
| `priority`        | VARCHAR(20)    | `Low`, `Medium`, `High`               |
| `employee_id`     | VARCHAR(50) FK | Assigned employee                     |
| `task_type`       | VARCHAR(50)    | `Personal` or `Assigned`              |
| `task_file`       | VARCHAR(255)   | Manager-uploaded attachment            |
| `submission_file` | VARCHAR(255)   | Employee-uploaded evidence             |
| `evidence_link`   | VARCHAR(255)   | External evidence URL                  |
| `manager_notes`   | TEXT           | Notes from the manager                 |

---

## 5. Setup Instructions

### Prerequisites
- **Laragon** (or any Apache + MySQL + PHP stack)
- PHP 7.4+ with `mysqli` extension enabled
- MySQL 5.7+

### Step-by-step

1. **Clone / copy** the project into your web server root:
   ```
   C:\laragon\www\optitask\
   ```

2. **Configure the database connection** — edit `db_config.php`:
   ```php
   $servername = "localhost";
   $username   = "root";
   $password   = "";
   $dbname     = "optitask";
   ```

3. **Initialize the database** — open in your browser:
   ```
   http://localhost/optitask/db_setup_helper.php
   ```
   Click the **"Initialize & Seed Database"** button. This will:
   - Create the `optitask` database
   - Create all 7 required tables
   - Seed departments, test users, and skills

4. **Configure AI features** — copy the example config and add your Gemini API key:
   ```
   cp config_ai.example.php config_ai.php
   ```
   Then edit `config_ai.php`:
   ```php
   define('GEMINI_API_KEY', 'your-gemini-api-key-here');
   ```

5. **Configure email** — edit `email_helper.php` with your SMTP credentials:
   ```php
   $mail->Username = 'your-email@gmail.com';
   $mail->Password = 'your-app-password';
   ```

6. **Access the application**:
   ```
   http://localhost/optitask/login.php
   ```

---

## 6. Default Test Accounts

| Role     | Worker ID | Password      | Security Key (for sign-up) |
|----------|-----------|---------------|----------------------------|
| Admin    | `AD001`   | `admin123`    | `ADMIN_BOSS`               |
| Manager  | `MG001`   | `manager123`  | `MGR_OPTI`                 |
| Employee | `EM001`   | `employee123` | `STAFF_UKM`                |

> **Note**: The Worker ID prefix determines the role: `AD` → Admin, `MG` → Manager, all others → Employee.

---

## 7. Feature Breakdown by Role

### Admin (`/admin/`)
- **Dashboard**: System-wide overview and statistics
- **User Management**: View, edit, suspend/activate user accounts
- **Audit Logs**: Browse all system audit entries (login, task changes, etc.)
- **Skills API**: AJAX endpoint to fetch user skill profiles

### Manager (`/manager/`)
- **Dashboard**: Department KPIs, task status charts, team overview
- **Assign Tasks**: Create and assign tasks to employees with file attachments
- **Verify Tasks**: Review employee submissions and mark as `Verified`
- **AI Candidate Suggestions**: Gemini-powered recommendation for best-fit employees
- **Notifications**: View and manage manager-specific notifications

### Employee (`/employee/`)
- **Dashboard**: Personal task list, efficiency rating (circular progress), AI insights
- **My Tasks**: View all assigned tasks with status, priority, and due dates
- **Submissions**: Upload evidence files/links, update task status
- **Profile & Skills**: Self-assess skill proficiency (1–5 scale)
- **Performance**: Detailed performance analytics
- **Notifications**: View personal notifications and AI coach tips
- **AI Coach**: Real-time productivity coaching via Gemini API

---

## 8. AI Integration (Gemini)

### Components
1. **`employee/ai_coach_helper.php`** — Core AI logic:
   - `get_stuck_task_hint($task_title, $days_stuck)` — Generates coaching advice for overdue tasks
   - Used by both the cron detector and the live dashboard

2. **`employee/ajax_ai_assistant.php`** — AJAX endpoint for the employee dashboard AI widget

3. **`manager/suggest_candidate.php`** — Suggests the best employee for a task based on skill matching

4. **`cron_stuck_detector.php`** — Automated background script:
   - Scans all tasks with status `In Progress`
   - Triggers AI coaching tips if a task has been stuck for ≥ 3 days
   - Inserts insight notifications for affected employees
   - De-duplicates (won't re-notify within 24 hours)
   - Runnable via CLI or browser

### Cron Setup (Optional)
```bash
# Run daily at 2:00 AM
0 2 * * * php /path/to/optitask/cron_stuck_detector.php
```

---

## 9. Email Notifications

- **Library**: PHPMailer (included locally in `/PHPMailer/`)
- **SMTP Provider**: Gmail (port 587, STARTTLS)
- **Function**: `send_email_notification($to_email, $to_name, $subject, $message_content)`
- **Template**: Branded HTML email matching the soft-pink OptiTask theme
- **Usage**: Called when tasks are assigned, updated, or verified
- **Test page**: `test_mail.php`

---

## 10. Audit Logging

Every critical action is logged to the `audit_logs` table via the `log_audit()` function defined in `db_config.php`:

```php
log_audit($conn, $user_id, $action, $details);
```

### Tracked Actions
| Action                    | Trigger                          |
|---------------------------|----------------------------------|
| `LOGIN`                   | Successful authentication        |
| `REGISTER`                | New account created              |
| `START_TASK`              | Employee starts a task           |
| `SUBMIT_TASK`             | Employee submits evidence        |
| `VERIFY_TASK`             | Manager verifies a task          |
| `ASSIGN_TASK`             | Manager assigns a task           |
| `STUCK_DETECTOR_TRIGGERED`| Cron injects a coaching tip      |

---

## 11. Security Considerations

| Area                | Implementation                                          |
|---------------------|---------------------------------------------------------|
| Password storage    | `password_hash()` with `PASSWORD_DEFAULT` (bcrypt)      |
| Session management  | PHP native sessions; role checked on every page load    |
| Input sanitization  | `htmlspecialchars()`, `filter_var()`, prepared statements|
| SQL injection        | Parameterized queries (`mysqli` prepared statements)    |
| Registration gate   | Security keys required per role during sign-up          |
| Audit trail         | All actions logged to `audit_logs` table                |

### ⚠️ Items to Address Before Production
1. **`config_ai.php`** and **`email_helper.php`** contain sensitive credentials — ensure they are excluded from version control (add to `.gitignore`).
2. **SMTP App Password** is currently hardcoded in `email_helper.php` — migrate to environment variables or a secure config.
3. **Security keys** for registration (`ADMIN_BOSS`, `MGR_OPTI`, `STAFF_UKM`) are hardcoded in `signup.php` — consider moving to a database or environment config.
4. **HTTPS** should be enforced in production.
5. **File uploads** (`/uploads/`) should be validated for type and size; directory should not be directly browsable.
6. **CSRF protection** is not implemented — consider adding tokens to all forms.
7. **Rate limiting** on login attempts is not present.

---

## 12. UI/UX Design System

| Element             | Value                                            |
|---------------------|--------------------------------------------------|
| Primary color       | `#FB6F92` (soft pink)                            |
| Secondary color     | `#FF8FAB` (lighter pink)                         |
| Accent              | `#FFB3C6` (pastel pink)                          |
| Background          | `#FFF5F7` (ultra-light pink)                     |
| Border / Subtle     | `#FFE5EC`                                        |
| Text primary        | `#1e293b` (dark slate)                           |
| Text secondary      | `#64748b` (gray)                                 |
| Body font           | `Quicksand` (Google Fonts)                       |
| Heading font        | `Outfit` (Google Fonts)                          |
| Card style          | Glassmorphism (`backdrop-filter: blur(20px)`)    |
| Border radius       | `2rem` – `2.5rem` (rounded-3xl/4xl)             |
| Transitions         | `cubic-bezier(0.4, 0, 0.2, 1)` — smooth easing  |

---

## 13. Known Utilities & Migration Scripts

| File                     | Purpose                                      |
|--------------------------|----------------------------------------------|
| `db_setup_helper.php`    | Full DB installer with visual UI             |
| `create_audit_table.php` | Creates `audit_logs` table if missing        |
| `alter.php`              | Schema alteration script                     |
| `setup_skills.php`       | Seeds skills into the database               |
| `patch_logout.php`       | Patches logout session handling              |
| `check_audit_logs.php`   | Diagnostic: verifies audit table exists      |
| `check_cols.php`         | Diagnostic: inspects table columns           |
| `check_dept.php`         | Diagnostic: verifies departments table       |
| `diagnostic_db.php`      | Quick database connection test               |
| `test_mail.php`          | End-to-end email sending test                |

---

## 14. Maintenance Notes

- **No package manager** is used (no Composer, no npm). All dependencies (PHPMailer, TailwindCSS CDN) are included directly.
- **No build step** is required — the project runs as-is on any PHP-enabled web server.
- **Database migrations** are handled manually via the utility scripts above.
- **Session-based auth** — there are no JWT tokens or API authentication layers.
- The **TailwindCSS CDN** is used for development convenience. For production, consider generating a purged CSS build.

---

## 15. Contact & Handoff Checklist

- [ ] Database initialized via `db_setup_helper.php`
- [ ] `config_ai.php` created with valid Gemini API key
- [ ] `email_helper.php` updated with valid SMTP credentials
- [ ] Test login with all three roles (`AD001`, `MG001`, `EM001`)
- [ ] Verify email notifications are working (`test_mail.php`)
- [ ] Cron job configured for `cron_stuck_detector.php` (if applicable)
- [ ] Sensitive files added to `.gitignore`
- [ ] Uploads directory has proper write permissions

---

*End of Handoff Document*
