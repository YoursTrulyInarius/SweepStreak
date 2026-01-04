# SweepStreak 🧹✨

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
