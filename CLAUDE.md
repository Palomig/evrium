# CLAUDE.md - AI Assistant Guide for Evrium Project

**Last Updated**: 2025-11-17
**Project**: Evrium - Educational Platform + CRM + Salary Management System
**Repository**: Palomig/evrium

---

## 📋 Table of Contents

1. [Project Overview](#project-overview)
2. [Repository Structure](#repository-structure)
3. [Technology Stack](#technology-stack)
4. [Development Workflows](#development-workflows)
5. [Code Conventions](#code-conventions)
6. [Database Schemas](#database-schemas)
7. [API Guidelines](#api-guidelines)
8. [Security Practices](#security-practices)
9. [Testing Guidelines](#testing-guidelines)
10. [Deployment Process](#deployment-process)
11. [Common Tasks](#common-tasks)
12. [Troubleshooting](#troubleshooting)

---

## 🎯 Project Overview

### Triple-Purpose Application

Evrium consists of **three integrated systems**:

#### 1. Interactive Geometry Learning Platform (Root)
- **Purpose**: Educational website for geometry (grades 7-9) based on Atanasyan textbook
- **Features**: Interactive SVG visualizations, chapter navigation, exercise editor, documentation
- **Tech**: PHP + Vanilla JavaScript + Bootstrap 5
- **Entry Point**: `index.php`
- **URL**: `https://эвриум.рф/`

#### 2. Evrium CRM (Tutoring Management System)
- **Purpose**: Full-featured CRM for tutors managing students, lessons, payments, skills tracking
- **Features**: Student CRUD, lesson tracking, financial management, REST API, PDF reports
- **Tech**: PHP 8.x + MySQL + Bootstrap 5
- **Location**: `/crm/` directory
- **Entry Point**: `crm/login.php`
- **URL**: `https://эвриум.рф/crm/`

#### 3. Zarplata (Teacher Salary Management System) ⭐ NEW
- **Purpose**: Automated salary calculation and scheduling system for teachers
- **Features**: Template-based scheduling, payment formulas, attendance tracking, Telegram bot integration
- **Tech**: PHP 8.x + MySQL + Material Design Dark Theme + Vanilla JavaScript
- **Location**: `/zarplata/` directory
- **Entry Point**: `zarplata/login.php`
- **URL**: `https://эвриум.рф/zarplata/`
- **Font**: Montserrat (Google Fonts)

### Key Metrics

**Geometry Platform**:
- **Curriculum**: 12 chapters across 3 grades (7-9)
- **Topics**: 100+ geometry topics
- **Exercises**: 300+ interactive problems

**CRM System**:
- **Database Tables**: 8 (+ 2 views + 3 triggers)
- **API Endpoints**: 10+ REST endpoints
- **Skills Tracked**: 5 core math competencies (extendable)
- **Roles**: Teacher, SuperAdmin

**Zarplata System**:
- **Database Tables**: 10 (users, teachers, students, payment_formulas, lessons_template, lessons_instance, attendance_log, payments, audit_log, settings)
- **Views**: 2 (teacher_stats, lessons_stats)
- **Triggers**: 2 (calculate_payment_after_lesson_complete, audit_attendance_log)
- **Payment Formula Types**: 3 (min_plus_per, fixed, expression)
- **Roles**: Admin, Owner

---

## 📂 Repository Structure

```
evrium/
├── 🌐 GEOMETRY PLATFORM (Root Level)
│   ├── index.php                    # Homepage with class selection
│   ├── chapter.php                  # Chapter display with topics list
│   ├── topic.php                    # Individual topic with theory/examples
│   ├── examples.php                 # Interactive exercise gallery
│   ├── editor.php                   # JSON-based exercise editor
│   ├── docs.php                     # Complete documentation
│   ├── config.php                   # ⭐ MASSIVE curriculum data (96KB)
│   │                                #    All 12 chapters, topics, theory, examples
│   │
├── 📚 CRM SYSTEM
│   └── crm/
│       ├── login.php                # Apple-style login page
│       ├── logout.php               # Session cleanup
│       ├── dashboard.php            # Teacher dashboard
│       ├── database.sql             # ⭐ Complete DB schema (with triggers)
│       ├── .htaccess                # Apache URL rewriting
│       ├── README.md                # CRM documentation (Russian)
│       ├── INSTALL.md               # Installation guide
│       │
│       ├── config/                  # ⭐ CORE CONFIGURATION
│       │   ├── db.php               # Database abstraction layer (PDO)
│       │   ├── auth.php             # Authentication & authorization
│       │   └── helpers.php          # Utility functions
│       │
│       └── api/                     # REST API Endpoints
│           └── students.php         # Student CRUD + filters/search
│
├── 💰 ZARPLATA SYSTEM (Teacher Salary Management) ⭐ NEW
│   └── zarplata/
│       ├── 📁 Main Pages
│       │   ├── index.php            # Dashboard with statistics
│       │   ├── login.php            # Material Design login
│       │   ├── logout.php           # Session cleanup
│       │   ├── teachers.php         # Teacher CRUD management
│       │   ├── schedule.php         # ⭐ Weekly schedule (table layout)
│       │   ├── lessons.php          # Lesson instances management
│       │   ├── payments.php         # Payment history
│       │   ├── reports.php          # Reports generation
│       │   ├── formulas.php         # Payment formula editor
│       │   ├── settings.php         # System settings
│       │   └── audit.php            # Audit log viewer
│       │
│       ├── 📁 config/               # ⭐ CORE CONFIGURATION
│       │   ├── db.php               # PDO database layer
│       │   ├── auth.php             # Authentication system
│       │   └── helpers.php          # Helper functions
│       │
│       ├── 📁 api/                  # REST API Endpoints
│       │   ├── teachers.php         # Teacher CRUD API
│       │   ├── schedule.php         # ⭐ Schedule template API
│       │   ├── lessons.php          # Lesson instance API
│       │   ├── payments.php         # Payment calculations API
│       │   ├── formulas.php         # Formula management API
│       │   ├── reports.php          # Reports generation API
│       │   ├── settings.php         # Settings API
│       │   └── audit.php            # Audit log API
│       │
│       ├── 📁 bot/                  # Telegram Bot (Planned)
│       │   ├── webhook.php          # Bot webhook handler
│       │   ├── cron.php             # Attendance polling cron
│       │   └── handlers/            # Command handlers
│       │
│       ├── 📁 assets/
│       │   ├── css/
│       │   │   └── material-dark.css  # ⭐ Material Design Dark Theme
│       │   ├── js/
│       │   │   └── schedule.js      # ⭐ Schedule management JS
│       │   └── images/
│       │
│       ├── 📁 templates/
│       │   ├── header.php           # ⭐ Sidebar + header (fixed layout)
│       │   └── footer.php           # Footer template
│       │
│       ├── 📁 migrations/           # Database migrations
│       │   ├── add_room_to_lessons.sql
│       │   ├── add_tier_grades_students.sql
│       │   ├── add_formula_to_teachers.sql
│       │   └── README.md
│       │
│       ├── database.sql             # ⭐ Complete DB schema
│       ├── README.md                # Full documentation (Russian)
│       └── .htaccess                # Apache config
│
├── 📄 DOCUMENTATION
│   └── docs/
│       └── claude.md                # Russian tech spec (for reference)
│
├── 🎨 STATIC ASSETS (Root)
│   ├── crm.html, crm-script.js, crm-style.css
│   ├── editor.html, examples.html, index.html
│   └── robots.txt
│
├── 🚀 CI/CD & DEPLOYMENT
│   ├── .github/
│   │   └── workflows/
│   │       ├── auto-merge.yml       # Auto-merge claude/** → main
│   │       └── deploy-timeweb.yml   # FTP deploy to Timeweb hosting
│   │
│   └── DEPLOYMENT.md                # Deployment documentation
│
└── 📦 EXERCISE ARCHIVES
    ├── 1-6.zip, 7-12.zip, 69-74.zip # Pre-built exercise packs
```

---

## 🛠 Technology Stack

### Backend
| Component | Technology | Notes |
|-----------|-----------|-------|
| **Language** | PHP 8.x | Use modern PHP 8+ features |
| **Database** | MySQL 5.7+ | InnoDB engine, UTF8MB4 charset |
| **DB Access** | PDO | Always use prepared statements |
| **Sessions** | PHP Sessions | HTTPOnly, secure cookies |
| **Password** | `password_hash()` | PASSWORD_DEFAULT (bcrypt) |

### Frontend

**Geometry Platform & CRM**:
| Component | Technology | Notes |
|-----------|-----------|-------|
| **Framework** | Bootstrap 5 | Loaded via CDN |
| **JavaScript** | Vanilla JS | No framework |
| **Icons** | Font Awesome | CDN-based |
| **Charts** | Chart.js | Progress visualization |
| **Font** | System default | Bootstrap defaults |

**Zarplata System** ⭐:
| Component | Technology | Notes |
|-----------|-----------|-------|
| **Design** | Material Design | Google's design system |
| **JavaScript** | Vanilla JS | No framework |
| **Icons** | Material Icons | Google Icons CDN |
| **Font** | Montserrat | Google Fonts |
| **Theme** | Dark Theme | Custom Material Dark CSS |

### DevOps
| Component | Technology | Notes |
|-----------|-----------|-------|
| **CI/CD** | GitHub Actions | Auto-merge + FTP deploy |
| **Hosting** | Timeweb | Shared hosting via FTP |
| **Deploy Path** | `/PALOMATIKA/public_html/` | |
| **Web Server** | Apache | .htaccess configured |

---

## 🔄 Development Workflows

### Branch Strategy

**CRITICAL**: All development happens on Claude-specific branches:

```
claude/{feature}-{session-id}
```

**Examples**:
- `claude/find-zarplata-file-01G7rwMTUaKtyuj4HEuCsRtv`
- `claude/claude-md-mhzka2eubzn0cg1j-01EEf1yfqC2HN82csG6kygKB`

### Workflow Steps

1. **Development Phase**
   - Work on designated `claude/*` branch
   - Make all changes, test locally
   - Commit with clear messages

2. **Commit Messages**
   - **Format**: `<type>: <short description>`
   - **Types**: `feat`, `fix`, `refactor`, `docs`, `style`, `test`, `chore`
   - **Examples**:
     - `feat: Add payment filtering API endpoint`
     - `fix: Resolve 4 critical schedule layout issues`
     - `docs: Update CLAUDE.md with zarplata system`
     - `refactor: Extract auth logic to separate module`

3. **Push to Remote**
   ```bash
   git add .
   git commit -m "feat: Add schedule template management"
   git push -u origin claude/feature-{session-id}
   ```
   - **MUST** use `-u origin` flag
   - Branch MUST start with `claude/` and end with session ID
   - **Retry Logic**: Up to 4 retries with exponential backoff (2s, 4s, 8s, 16s) if network errors

4. **Auto-Merge to Main**
   - GitHub Action automatically merges `claude/**` → `main`
   - Merge commit created with `--no-ff`
   - Triggers deployment workflow

5. **Auto-Deploy to Timeweb**
   - FTP deployment to `/PALOMATIKA/public_html/`
   - Excludes: `.git`, `.github`, `node_modules`, `.DS_Store`
   - Deployment takes 1-2 minutes

### Git Best Practices

**DO**:
- ✅ Develop on `claude/*` branches only
- ✅ Push with `-u origin <branch-name>`
- ✅ Use descriptive commit messages
- ✅ Test locally before pushing
- ✅ Keep commits atomic and focused

**DON'T**:
- ❌ Push to `main` directly (will fail)
- ❌ Force push (`--force`)
- ❌ Skip hooks (`--no-verify`)
- ❌ Amend others' commits
- ❌ Use generic messages like "fix", "update"

---

## 📝 Code Conventions

### PHP Conventions

#### File Organization
```php
<?php
// 1. Require/include statements
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// 2. Session management (if needed)
session_start();

// 3. Authentication checks
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// 4. Main logic
$students = dbQuery("SELECT * FROM students WHERE teacher_id = ?", [$teacherId]);

// 5. Response (HTML or JSON)
?>
```

#### Naming Conventions
| Type | Convention | Example |
|------|-----------|---------|
| **Variables** | camelCase | `$studentId`, `$teacherName` |
| **Functions** | camelCase | `getCurrentUser()`, `validateInput()` |
| **Constants** | UPPER_SNAKE_CASE | `DB_HOST`, `SESSION_TIMEOUT` |
| **Classes** | PascalCase | `StudentManager`, `ReportGenerator` |
| **Files** | lowercase | `schedule.php`, `api/teachers.php` |
| **Database Tables** | snake_case | `students`, `lessons_template`, `audit_log` |
| **Database Columns** | snake_case | `teacher_id`, `created_at`, `password_hash` |

### JavaScript Conventions

#### Code Style (Zarplata System)
```javascript
// Use const/let, never var
const teacherId = 123;
let balance = 0;

// Use arrow functions for callbacks
teachers.filter(t => t.active === 1);

// Use template literals
const message = `Teacher ${name} has ${count} lessons`;

// Use async/await for API calls
async function fetchTemplates() {
    const response = await fetch('/zarplata/api/schedule.php?action=list_templates');
    const data = await response.json();
    return data;
}

// Handle errors properly
try {
    const templates = await fetchTemplates();
    renderSchedule(templates);
} catch (error) {
    console.error('Failed to fetch templates:', error);
    showErrorMessage('Unable to load schedule');
}
```

### SQL Conventions

#### Query Style
```sql
-- Use uppercase for SQL keywords
SELECT s.id, s.name, t.name AS teacher_name
FROM students s
LEFT JOIN teachers t ON s.teacher_id = t.id
WHERE s.active = 1
ORDER BY s.created_at DESC
LIMIT 20 OFFSET 0;

-- Always use meaningful aliases
-- Always specify JOIN conditions explicitly
-- Use prepared statement placeholders (?)
```

#### Table Design Principles
- **Primary Keys**: Always `id INT AUTO_INCREMENT PRIMARY KEY`
- **Foreign Keys**: Always name with `_id` suffix (e.g., `teacher_id`, `student_id`)
- **Timestamps**: Use `DATETIME` type, named `created_at`, `updated_at`
- **Soft Deletes**: Use `active BOOLEAN DEFAULT 1` or `deleted_at DATETIME NULL`
- **Enums**: Use for fixed sets (e.g., `ENUM('teacher', 'owner')`)
- **Decimals**: Use `DECIMAL(8,2)` for currency
- **Indexes**: Add on foreign keys and frequently queried columns

---

## 🗄️ Database Schemas

### Zarplata System Database ⭐

**Database Name**: `cw95865_admin`
**Credentials**:
- Host: `localhost`
- User: `cw95865_admin`
- Password: `123456789`
- Charset: `utf8mb4`

#### Core Tables

##### `users` - System Administrators
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'owner') DEFAULT 'admin',
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```
**Default Credentials**: username=`admin`, password=`admin123` ⚠️ Change in production!

##### `teachers` - Teachers/Instructors
```sql
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    telegram_id BIGINT UNIQUE NULL,
    telegram_username VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100),
    formula_id INT NULL,  -- Default payment formula
    active BOOLEAN DEFAULT 1,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (formula_id) REFERENCES payment_formulas(id) ON DELETE SET NULL
);
```

##### `students` - Students
```sql
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    parent_phone VARCHAR(20),
    email VARCHAR(100),
    class INT,  -- Grade level (7-11)
    notes TEXT,
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

##### `payment_formulas` - Salary Calculation Formulas
```sql
CREATE TABLE payment_formulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('min_plus_per', 'fixed', 'expression') NOT NULL,
    description TEXT,

    -- For type 'min_plus_per'
    min_payment INT DEFAULT 0,        -- Base salary
    per_student INT DEFAULT 0,         -- Payment per student
    threshold INT DEFAULT 2,           -- Start counting from Nth student

    -- For type 'fixed'
    fixed_amount INT DEFAULT 0,        -- Fixed salary

    -- For type 'expression'
    expression TEXT,                   -- Custom formula: e.g., "max(500, N * 150)"

    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Formula Types**:
1. **min_plus_per**: Base + per student (e.g., 500₽ + 150₽ per student from 2nd)
2. **fixed**: Fixed amount regardless of students
3. **expression**: Custom math expression with variable `N` (student count)

##### `lessons_template` - Weekly Schedule Templates ⭐
```sql
CREATE TABLE lessons_template (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,     -- 1=Monday, 7=Sunday
    room TINYINT DEFAULT 1,            -- ⭐ Room number (1-3)
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    lesson_type ENUM('group', 'individual') DEFAULT 'group',
    subject VARCHAR(100),              -- e.g., "Математика", "Физика"
    expected_students INT DEFAULT 1,
    formula_id INT NULL,               -- Override teacher's default formula

    -- ⭐ NEW FIELDS (added via migrations)
    tier ENUM('S', 'A', 'B', 'C', 'D') DEFAULT 'C',  -- Student tier/level
    grades VARCHAR(50) NULL,                          -- e.g., "7, 8-9"
    students TEXT NULL,                               -- JSON array of student names

    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (formula_id) REFERENCES payment_formulas(id) ON DELETE SET NULL
);
```

**Schedule Features**:
- **Table Layout**: Time × Room grid view
- **Room Support**: 1-3 classrooms
- **Tier System**: S/A/B/C/D levels for grouping students
- **Grades**: Multiple grade levels per lesson (e.g., "7, 8-9")
- **Student List**: JSON array stored in TEXT field

##### `lessons_instance` - Actual Lesson Records
```sql
CREATE TABLE lessons_instance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NULL,              -- NULL = one-time lesson
    teacher_id INT NOT NULL,
    substitute_teacher_id INT NULL,    -- For substitutions
    lesson_date DATE NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    lesson_type ENUM('group', 'individual') DEFAULT 'group',
    subject VARCHAR(100),
    expected_students INT DEFAULT 1,
    actual_students INT DEFAULT 0,     -- Counted from attendance
    formula_id INT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (template_id) REFERENCES lessons_template(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (substitute_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (formula_id) REFERENCES payment_formulas(id) ON DELETE SET NULL
);
```

##### `attendance_log` - Student Attendance
```sql
CREATE TABLE attendance_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_instance_id INT NOT NULL,
    student_id INT NOT NULL,
    attended BOOLEAN NOT NULL,
    marked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    marked_by VARCHAR(50),             -- 'telegram_bot' or 'admin'

    FOREIGN KEY (lesson_instance_id) REFERENCES lessons_instance(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
```

##### `payments` - Teacher Payments
```sql
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    lesson_instance_id INT NULL,       -- NULL = manual payment
    amount INT NOT NULL,               -- Salary in rubles
    payment_type ENUM('lesson', 'bonus', 'penalty', 'adjustment') DEFAULT 'lesson',
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    paid_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_instance_id) REFERENCES lessons_instance(id) ON DELETE SET NULL
);
```

##### `audit_log` - System Audit Trail
```sql
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(50) NOT NULL,  -- e.g., 'template_created', 'lesson_completed'
    entity_type VARCHAR(50) NOT NULL,  -- e.g., 'template', 'lesson', 'payment'
    entity_id INT NULL,
    user_id INT NULL,
    old_value TEXT NULL,               -- JSON of old state
    new_value TEXT NULL,               -- JSON of new state
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

##### `settings` - System Configuration
```sql
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Database Views

##### `teacher_stats` - Teacher Statistics
```sql
CREATE VIEW teacher_stats AS
SELECT
    t.id AS teacher_id,
    t.name AS teacher_name,
    COUNT(DISTINCT li.id) AS total_lessons,
    SUM(CASE WHEN li.status = 'completed' THEN 1 ELSE 0 END) AS completed_lessons,
    SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) AS total_paid,
    SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) AS total_pending
FROM teachers t
LEFT JOIN lessons_instance li ON t.id = li.teacher_id
LEFT JOIN payments p ON t.id = p.teacher_id
GROUP BY t.id;
```

##### `lessons_stats` - Lesson Statistics with Payments
```sql
CREATE VIEW lessons_stats AS
SELECT
    li.id AS lesson_id,
    li.lesson_date,
    li.time_start,
    li.time_end,
    t.name AS teacher_name,
    li.subject,
    li.actual_students,
    li.status,
    p.amount AS payment_amount,
    p.status AS payment_status
FROM lessons_instance li
LEFT JOIN teachers t ON li.teacher_id = t.id
LEFT JOIN payments p ON li.id = p.lesson_instance_id
ORDER BY li.lesson_date DESC, li.time_start ASC;
```

#### Database Triggers

##### `calculate_payment_after_lesson_complete`
```sql
CREATE TRIGGER calculate_payment_after_lesson_complete
AFTER UPDATE ON lessons_instance
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Calculate payment based on formula
        -- Insert into payments table
        -- (Implementation depends on formula type)
    END IF;
END;
```

##### `audit_attendance_log`
```sql
CREATE TRIGGER audit_attendance_log
AFTER INSERT ON attendance_log
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (action_type, entity_type, entity_id, new_value)
    VALUES ('attendance_marked', 'attendance', NEW.id,
            JSON_OBJECT('student_id', NEW.student_id, 'attended', NEW.attended));
END;
```

---

## 🔌 API Guidelines (Zarplata System)

### Authentication

**Session-Based** (for web UI):
```php
session_start();
requireAuth();  // Redirects to login if not authenticated
$user = getCurrentUser();
```

**Helper Functions** (zarplata/config/auth.php):
```php
isLoggedIn()          // Check if user has active session
getCurrentUser()      // Get user array with id, username, name, role
requireAuth()         // Enforce login (redirect if not logged in)
logout()             // Destroy session and redirect to login
```

### API Structure

**Pattern**: `/zarplata/api/{resource}.php?action={action}`

**Example Endpoints**:
```
GET  /zarplata/api/schedule.php?action=list_templates
GET  /zarplata/api/schedule.php?action=get_template&id=5
POST /zarplata/api/schedule.php?action=add_template
POST /zarplata/api/schedule.php?action=update_template
POST /zarplata/api/schedule.php?action=delete_template
GET  /zarplata/api/schedule.php?action=get_week&date=2025-11-17
POST /zarplata/api/schedule.php?action=generate_week&date=2025-11-17
```

### Request/Response Format

**POST Request** (JSON body):
```bash
curl -X POST '/zarplata/api/schedule.php?action=add_template' \
  -H "Content-Type: application/json" \
  -d '{
    "teacher_id": 5,
    "day_of_week": 1,
    "room": 1,
    "time_start": "14:00",
    "time_end": "15:30",
    "subject": "Математика",
    "tier": "A",
    "grades": "8-9",
    "expected_students": 6,
    "students": ["Иван", "Мария", "Петр"]
  }'
```

**Success Response**:
```json
{
  "success": true,
  "data": {
    "id": 123,
    "teacher_id": 5,
    "day_of_week": 1,
    ...
  }
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "Выберите преподавателя",
  "code": 400
}
```

### Implemented APIs

1. **schedule.php** ✅
   - `list_templates` - Get all active templates
   - `get_template` - Get single template by ID
   - `add_template` - Create new template
   - `update_template` - Update existing template
   - `delete_template` - Soft delete template
   - `get_week` - Get lesson instances for a week
   - `generate_week` - Generate instances from templates

2. **teachers.php** ✅
   - CRUD operations for teachers
   - Formula assignment

3. **formulas.php** ✅
   - CRUD operations for payment formulas
   - Formula calculation testing

4. **lessons.php** ⚠️ Partially implemented
5. **payments.php** ⚠️ Partially implemented
6. **reports.php** ⚠️ Not implemented
7. **settings.php** ✅ Implemented
8. **audit.php** ✅ Implemented

---

## 🔐 Security Practices

### Input Validation (Zarplata Example)
```php
// From zarplata/api/schedule.php
$teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
$dayOfWeek = filter_var($data['day_of_week'] ?? 0, FILTER_VALIDATE_INT);
$room = filter_var($data['room'] ?? 1, FILTER_VALIDATE_INT);

if (!$teacherId) {
    jsonError('Выберите преподавателя', 400);
}

if ($dayOfWeek < 1 || $dayOfWeek > 7) {
    jsonError('Неверный день недели', 400);
}

if ($room < 1 || $room > 3) {
    jsonError('Неверный номер кабинета', 400);
}
```

### NULL Handling for Foreign Keys
```php
// formula_id can be NULL, so handle separately
$formulaId = null;
if (isset($data['formula_id']) && $data['formula_id']) {
    $formulaId = filter_var($data['formula_id'], FILTER_VALIDATE_INT);
    if ($formulaId === false || $formulaId === 0) {
        $formulaId = null;  // Prevent foreign key constraint violations
    }
}
```

### Database Migration Safety
```php
try {
    // Try with new fields first
    $result = dbExecute(
        "INSERT INTO lessons_template
         (teacher_id, day_of_week, room, tier, grades, students, ...)
         VALUES (?, ?, ?, ?, ?, ?, ...)",
        [$teacherId, $dayOfWeek, $room, $tier, $grades, $students, ...]
    );
} catch (PDOException $e) {
    // Fallback for databases without new fields
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $result = dbExecute(
            "INSERT INTO lessons_template
             (teacher_id, day_of_week, ...)
             VALUES (?, ?, ...)",
            [$teacherId, $dayOfWeek, ...]
        );
    }
}
```

---

## 🚀 Deployment Process

### Database Migrations (Zarplata)

**Location**: `/zarplata/migrations/`

**Applied Migrations**:
1. ✅ `add_room_to_lessons.sql` - Added `room` field to lessons_template
2. ✅ `add_tier_grades_students.sql` - Added tier, grades, students fields
3. ✅ `add_formula_to_teachers.sql` - Added formula_id to teachers

**How to Apply**:
1. Via phpMyAdmin: Copy SQL commands one-by-one
2. Via CLI: `mysql -u cw95865_admin -p cw95865_admin < migration.sql`

**Backwards Compatibility**: All API endpoints have fallback logic for missing columns.

---

## 🛠️ Common Tasks (Zarplata)

### Creating a Schedule Template
```php
// From schedule.php JavaScript
const data = {
    teacher_id: 5,
    day_of_week: 1,  // Monday
    room: 1,
    time_start: '14:00',
    time_end: '15:30',
    lesson_type: 'group',
    subject: 'Математика',
    tier: 'A',
    grades: '8-9',
    expected_students: 6,
    formula_id: 2,
    students: JSON.stringify(['Иван', 'Мария', 'Петр'])
};

const response = await fetch('/zarplata/api/schedule.php?action=add_template', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
});
```

### Generating Week from Templates
```php
// Automatically creates lesson instances for the entire week
$response = fetch('/zarplata/api/schedule.php?action=generate_week&date=2025-11-17', {
    method: 'POST'
});

// Creates instances for Monday-Sunday based on active templates
// Skips if instance already exists (prevents duplicates)
```

---

## 🐛 Troubleshooting (Zarplata-Specific)

### Schedule Table Overlaps Sidebar

**Problem**: Wide schedule table extends over fixed sidebar

**Solution**: Applied in `/zarplata/templates/header.php:141-143`
```css
.main-content {
    max-width: calc(100vw - 280px);  /* Viewport width minus sidebar */
    overflow-x: hidden;
    box-sizing: border-box;
}
```

### Empty Days Show Room Headers

**Problem**: Days with no lessons showed empty table headers

**Solution**: Applied in `/zarplata/schedule.php:875-931`
```javascript
if (timeSlots.length > 0) {
    // Show headers + table rows
    dayColumn.appendChild(header);
    dayColumn.appendChild(roomHeaders);
    // ... render time slots
} else {
    // Show only header + "Нет занятий" message
    dayColumn.appendChild(header);
    const emptyMsg = document.createElement('div');
    emptyMsg.textContent = 'Нет занятий';
    content.appendChild(emptyMsg);
}
```

### Foreign Key Constraint Violations

**Problem**: `formula_id = 0` causes foreign key error

**Solution**: Explicit NULL handling
```php
$formulaId = null;
if (isset($data['formula_id']) && $data['formula_id']) {
    $formulaId = filter_var($data['formula_id'], FILTER_VALIDATE_INT);
    if ($formulaId === false || $formulaId === 0) {
        $formulaId = null;  // ← KEY FIX
    }
}
```

### dbExecute Returns Empty for INSERT

**Problem**: `pdo->lastInsertId()` returns "0" (string) which is falsy

**Solution**: Applied in `/zarplata/config/db.php:102-104`
```php
if ($lastId && $lastId !== '0') {  // Explicit check for string "0"
    return (int)$lastId;
}
```

---

## 📚 Additional Resources (Zarplata)

### Documentation
- **`zarplata/README.md`** - Complete system documentation (Russian)
- **`zarplata/database.sql`** - Full database schema
- **`zarplata/migrations/README.md`** - Migration instructions

### Key Files
- **`zarplata/config/db.php`** - Database abstraction layer
  - `dbQuery($sql, $params)` - SELECT queries
  - `dbQueryOne($sql, $params)` - Single row SELECT
  - `dbExecute($sql, $params)` - INSERT/UPDATE/DELETE

- **`zarplata/config/auth.php`** - Authentication
  - `requireAuth()` - Enforce login
  - `getCurrentUser()` - Get current user data
  - `isLoggedIn()` - Check authentication status

- **`zarplata/config/helpers.php`** - Helper functions
  - `e($string)` - Escape HTML (htmlspecialchars wrapper)
  - `jsonSuccess($data)` - Send JSON success response
  - `jsonError($message, $code)` - Send JSON error response
  - `logAudit($action, $entity, $id, ...)` - Write audit log

### Material Design Resources
- **Color Palette**: #BB86FC (Primary), #03DAC6 (Secondary), #121212 (Background)
- **Font**: Montserrat (Google Fonts)
- **Icons**: Material Icons (Google)
- **Theme**: `zarplata/assets/css/material-dark.css`

---

## 🎯 AI Assistant Guidelines (Updated for Zarplata)

### Zarplata-Specific Patterns

1. **Always use Material Design components**
   - Cards with elevation shadows
   - Material Icons instead of Font Awesome
   - Dark theme colors from `material-dark.css`
   - Montserrat font

2. **Follow the schedule table layout**
   - Grid system: `60px (time) + repeat(3, 120px) (rooms)`
   - Fixed sidebar at 280px width
   - Main content: `calc(100vw - 280px)` max-width

3. **Handle NULL foreign keys correctly**
   - Always check for NULL/0/false when dealing with `formula_id`
   - Use explicit NULL instead of 0 for optional foreign keys

4. **Maintain backwards compatibility**
   - Try-catch for database queries with new fields
   - Fallback to minimal field set if new columns don't exist

5. **Use JSON for array storage**
   - Students list stored as JSON in TEXT field
   - Parse with try-catch for robustness

### Common Zarplata Patterns

**API Error Handling**:
```php
function handleAddTemplate() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;  // Fallback to form data
    }

    // ... validation and processing
}
```

**Audit Logging**:
```php
logAudit('template_created', 'template', $templateId, null, [
    'teacher_id' => $teacherId,
    'day_of_week' => $dayOfWeek,
    'time' => "$timeStart-$timeEnd"
], 'Создан шаблон урока');
```

---

## ⚠️ CRITICAL WARNINGS

### DO NOT MODIFY deploy-timeweb.yml

**NEVER** change the `.github/workflows/deploy-timeweb.yml` file!

Any modifications to this file (adding exclude patterns, changing versions, adding options) **WILL BREAK THE DEPLOYMENT**.

The current working configuration is:
```yaml
uses: SamKirkland/FTP-Deploy-Action@4.0.0
with:
  server: ${{ secrets.FTP_SERVER }}
  username: ${{ secrets.FTP_USERNAME }}
  password: ${{ secrets.FTP_PASSWORD }}
  server-dir: /PALOMATIKA/public_html/
```

If you need to exclude files from deployment:
- Use `.gitignore` instead
- Create folders manually on the server via FTP client
- **DO NOT** add `exclude:`, `log-level:`, or change the action version

---

**End of CLAUDE.md** - Last updated: 2025-12-17
