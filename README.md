# SweepStreak 🧹✨
aaaa
> **Transform classroom cleaning into an engaging game.**

SweepStreak is a gamified classroom cleaning management system that motivates students to keep their environment clean through points, streaks, leaderboards, and badges.

## 🌟 Key Features

### For Students
- **Gamified Tasks**: Earn points and XP for completing cleaning duties.
- **Streak System**: Build daily streaks to earn bonus rewards.
- **Leaderboards**: Compete with other groups and classes.
- **Badges**: Unlock achievements like "First Clean", "Streak Master", and "Perfect Week".
- **Photo Verification**: Submit timestamped photos as proof of work.

### For Teachers
- **Class Management**: Easily create classes and generate join codes.
- **Group Assignments**: Organize students into cleaning groups.
- **Task Management**: Assign specific cleaning tasks and areas.
- **Verification**: Review and approve/reject cleaning submissions with feedback.
- **Automated Tracking**: Integrated attendance and participation tracking.

## 🛠️ Tech Stack

- **Frontend**: HTML5, CSS3 (Custom Pixel/Game Design), JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **Environment**: XAMPP (Apache/MySQL)

## 🚀 Installation & Setup

1.  **Clone the repository** (or extract files) to your web server root (e.g., `htdocs` in XAMPP).
    ```bash
    c:\xampp\htdocs\sweepstreak
    ```

2.  **Database Setup**:
    - Open PHPMyAdmin (usually `http://localhost/phpmyadmin`).
    - Create a new database named `sweepstreak`.
    - Import the [`sql.txt`](sql.txt) file located in the root directory to create the necessary tables and default data.
    - *Alternatively, copy the contents of `sql.txt` and run it in the SQL tab.*

3.  **Configuration**:
    - Ensure your database connection settings in `config/database.php` match your local environment.
    - Default settings usually allow access with user `root` and empty password.

4.  **Run the Application**:
    - Open your browser and navigate to:
      `http://localhost/sweepstreak`

## 📖 Usage Guide

1.  **Register**: Create an account as a Teacher or Student.
2.  **Teachers**:
    - Create a class to get a unique **Class Code**.
    - Share the code with students.
    - Create cleaning tasks and assign them to groups.
3.  **Students**:
    - Join a class using the provided code.
    - View assigned tasks in the dashboard.
    - Clean the assigned area and upload a photo proof.
4.  **Validation**:
    - Teachers accept submissions to award points.
    - Watch the leaderboard update in real-time!

## 📂 Project Structure

- `/config` - Database configuration
- `/includes` - Reusable header/footer components
- `/uploads` - Stores user submissions and profile pictures
- `index.php` - Landing page
- `teacher_dashboard.php` - Main hub for teachers
- `dashboard.php` - Main hub for students (Task view)
- `sql.txt` - Database schema

---
*Built for the SweepStreak Project.*

## Recent Fixes (local development)

These changes were applied to stabilize the development build and remove PHP warning output that was breaking pages during render:

- Fixed include path resolution in `includes/header.php` to use a deterministic path (`__DIR__`) so `config/database.php` is required reliably.
- Guarded session accesses across multiple pages to avoid "Undefined array key" warnings when `$_SESSION['name']` or `$_SESSION['role']` are not set. This prevents stray warning text from corrupting HTML output.
- Made the home link computation in the header safe by checking `$_SESSION['role']` before using it.
- Hardened user avatar/name rendering in `includes/header.php` to only output when session values exist and to escape output with `htmlspecialchars()`.

Files touched (high level):

- `includes/header.php`
- `assign_badges.php`, `assign_tasks.php`, `dashboard.php`, `join_class.php`
- `leaderboard.php`, `review_submissions.php`, `student_profile.php`, `submit_task.php`
- `teacher_dashboard.php`, `class_details.php`, `create_class.php`, `manage_class.php`
- `manage_group_members.php`, `manage_groups.php`, `view_classes.php`

These edits aim to make the app safe to load even when session state is incomplete during development.

## Next Step: Proper Folder Structure

To make the codebase more maintainable and to avoid similar path/session issues in the future, the next step is to adopt a clearer folder structure and a centralized initialization file.
