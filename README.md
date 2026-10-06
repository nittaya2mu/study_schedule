# 🎓 Smart Study Scheduler

### Automatic Study Schedule Generation Using Genetic Algorithm

> **Senior Project | Computer Engineering**

Smart Study Scheduler is a web-based application that automatically generates personalized study schedules using a **Genetic Algorithm (GA)**.

The system considers students' subjects, topics, exam dates, study priorities, difficulty levels, available time, and study-hour requirements to generate an optimized study schedule that balances multiple constraints.

---

## 📌 Project Overview

Planning an effective study schedule can be difficult when students have multiple subjects, different exam dates, limited free time, and different levels of subject difficulty.

This project aims to solve this problem by applying a **Genetic Algorithm** to automatically search for a high-quality study schedule.

The system allows users to:

* Manage subjects and study topics
* Define exam dates and priorities
* Specify available study time
* Set study-hour requirements
* Automatically generate an optimized study schedule
* View schedules in a calendar
* Track study progress
* Regenerate or modify schedules when circumstances change

---

## 🎯 Objectives

1. Develop a web application for automatic study schedule generation.
2. Apply Genetic Algorithm to solve the study scheduling optimization problem.
3. Consider user constraints and preferences when generating schedules.
4. Provide an interactive calendar for viewing study plans.
5. Provide progress tracking for study activities.
6. Evaluate the effectiveness of Genetic Algorithm using measurable performance indicators.
7. Compare GA-based scheduling with basic scheduling approaches.

---

## ✨ Main Features

### 👤 User Management

* User registration
* Login / Logout
* Profile management
* Study preferences

### 📚 Subject Management

Users can manage:

* Subject name
* Topics
* Difficulty level
* Priority
* Exam date
* Estimated study hours

### ⏰ Availability Management

Users can define:

* Available study days
* Available time periods
* Maximum study hours per day
* Unavailable periods
* Break periods

### 🧬 Genetic Algorithm

The core optimization module consists of:

* Chromosome representation
* Population initialization
* Fitness function
* Selection
* Crossover
* Mutation
* Elitism / Best solution preservation
* Termination criteria

### 📅 Automatic Schedule Generation

The system generates a study schedule based on:

* Exam deadlines
* Subject priority
* Subject difficulty
* Required study hours
* User availability
* Daily study limits
* Schedule conflicts

### 📊 Dashboard

The dashboard provides:

* Today's study schedule
* Weekly study schedule
* Overall study progress
* Completed study hours
* Remaining study hours
* Upcoming examinations

### ✅ Progress Tracking

Users can:

* Mark study sessions as completed
* Track progress by subject
* Track topic completion
* View overall study progress

---

# 🧬 Genetic Algorithm

The Genetic Algorithm is the main optimization component of this project.

## Chromosome

A chromosome represents one possible study schedule.

Example:

```text
Monday
  18:00–19:00 → Data Structures
  19:15–20:15 → Mathematics

Tuesday
  18:00–19:00 → Database

Thursday
  18:00–19:00 → Artificial Intelligence
```

Each study session is represented as a gene.

```text
Gene = {
    Date,
    StartTime,
    EndTime,
    Subject,
    Topic
}
```

---

## Population

The system generates multiple candidate schedules.

```text
Population Size = 50
```

Each chromosome represents one possible schedule.

```text
Population
│
├── Schedule 1
├── Schedule 2
├── Schedule 3
├── ...
└── Schedule 50
```

---

## Fitness Function

Each candidate schedule is evaluated using a fitness function.

A conceptual fitness function is:

```text
Fitness Score =
      Exam Priority Score
    + Availability Score
    + Study Balance Score
    + Completion Score
    - Conflict Penalty
    - Overload Penalty
```

The goal is to maximize the fitness score.

### Example criteria

| Criterion        | Description                                         |
| ---------------- | --------------------------------------------------- |
| Exam Priority    | Gives higher priority to subjects with closer exams |
| Subject Priority | Considers user-defined importance                   |
| Availability     | Avoids unavailable periods                          |
| Study Hours      | Attempts to satisfy required study hours            |
| Balance          | Avoids excessive study concentration                |
| Conflict         | Penalizes overlapping sessions                      |
| Overload         | Penalizes excessive daily study hours               |

---

## Selection

Candidate schedules with higher fitness scores have a higher probability of being selected for reproduction.

Possible implementation:

```text
Tournament Selection
```

---

## Crossover

Two parent schedules are combined to generate new schedules.

```text
Parent A
    ↓
Monday → Data Structures
Tuesday → Database

Parent B
    ↓
Monday → AI
Tuesday → Mathematics

       ↓ Crossover

Child
    ↓
Monday → Data Structures
Tuesday → Mathematics
```

---

## Mutation

Mutation randomly changes selected genes to maintain population diversity.

Example:

```text
Before:
Monday 18:00 → Database

After:
Monday 20:00 → Database
```

---

## Termination

The algorithm terminates when one of the following conditions is satisfied:

* Maximum number of generations is reached.
* Target fitness score is achieved.
* Fitness improvement becomes insignificant.

---

# 🏗️ System Architecture

```text
                    ┌───────────────────┐
                    │       User        │
                    └─────────┬─────────┘
                              │
                              ▼
                    ┌───────────────────┐
                    │ HTML / CSS / JS   │
                    │    Frontend       │
                    └─────────┬─────────┘
                              │
                         AJAX / API
                              │
                              ▼
                    ┌───────────────────┐
                    │       PHP         │
                    │      Backend      │
                    └───────┬─────┬─────┘
                            │     │
                ┌───────────┘     └───────────┐
                ▼                             ▼
       ┌────────────────┐           ┌─────────────────┐
       │     MySQL      │           │ Genetic         │
       │    Database    │           │ Algorithm       │
       └────────────────┘           └────────┬────────┘
                                             │
                                             ▼
                                  ┌────────────────────┐
                                  │ Optimized Study    │
                                  │ Schedule           │
                                  └─────────┬──────────┘
                                            │
                                            ▼
                                  ┌────────────────────┐
                                  │ Calendar /         │
                                  │ Dashboard          │
                                  └────────────────────┘
```

---

# 🛠️ Technology Stack

| Technology        | Purpose                              |
| ----------------- | ------------------------------------ |
| **HTML5**         | Web structure                        |
| **CSS3**          | User interface and responsive design |
| **JavaScript**    | Client-side interaction              |
| **PHP**           | Backend and server-side logic        |
| **MySQL**         | Database                             |
| **phpMyAdmin**    | Database administration              |
| **XAMPP**         | Local development environment        |
| **Git**           | Version control                      |
| **GitHub**        | Source code repository               |
| **Trello / Jira** | Project management                   |

---

# 📁 Project Structure

```text
smart-study-scheduler/
│
├── index.php
├── login.php
├── register.php
├── dashboard.php
│
├── config/
│   └── database.php
│
├── api/
│   ├── subjects.php
│   ├── topics.php
│   ├── availability.php
│   ├── schedules.php
│   └── progress.php
│
├── genetic/
│   ├── chromosome.php
│   ├── population.php
│   ├── fitness.php
│   ├── selection.php
│   ├── crossover.php
│   ├── mutation.php
│   └── genetic_algorithm.php
│
├── pages/
│   ├── subjects/
│   ├── availability/
│   ├── schedule/
│   └── progress/
│
├── assets/
│   ├── css/
│   │   └── style.css
│   │
│   ├── js/
│   │   ├── app.js
│   │   ├── schedule.js
│   │   └── dashboard.js
│   │
│   └── images/
│
├── database/
│   └── schema.sql
│
├── docs/
│   ├── requirements.md
│   ├── architecture.md
│   └── genetic-algorithm.md
│
└── README.md
```

---

# 🗄️ Database Design

The main database entities include:

```text
Users
  │
  ├── Subjects
  │      │
  │      └── Topics
  │
  ├── Availability
  │
  ├── Schedules
  │      │
  │      └── Schedule Items
  │
  └── Study Progress
```

### Main Tables

```text
users
subjects
topics
availability
schedules
schedule_items
study_progress
```

---

# 🚀 Installation

## 1. Install XAMPP

Install XAMPP with:

* Apache
* PHP
* MySQL
* phpMyAdmin

---

## 2. Clone the Repository

```bash
git clone https://github.com/your-username/smart-study-scheduler.git
```

Move the project into:

```text
C:\xampp\htdocs\
```

Example:

```text
C:\xampp\htdocs\smart-study-scheduler
```

---

## 3. Start XAMPP

Start:

```text
Apache
MySQL
```

---

## 4. Create Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create a database:

```text
smart_study_scheduler
```

---

## 5. Import Database

Import:

```text
database/schema.sql
```

into:

```text
smart_study_scheduler
```

---

## 6. Configure Database Connection

Edit:

```text
config/database.php
```

Example:

```php
<?php

$host = "localhost";
$dbname = "smart_study_scheduler";
$username = "root";
$password = "";

$conn = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $username,
    $password
);

$conn->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);
```

---

## 7. Run the Application

Open:

```text
http://localhost/smart-study-scheduler/
```

---

# 🔄 System Workflow

```text
User Login
     ↓
Enter Subjects
     ↓
Enter Topics
     ↓
Set Exam Dates
     ↓
Set Priorities
     ↓
Set Available Time
     ↓
Set Study Requirements
     ↓
Generate Schedule
     ↓
Create Initial Population
     ↓
Calculate Fitness
     ↓
Selection
     ↓
Crossover
     ↓
Mutation
     ↓
Evaluate New Population
     ↓
Repeat
     ↓
Best Schedule
     ↓
Display Calendar
     ↓
Track Progress
```

---

# 📈 Performance Evaluation

The project evaluates the Genetic Algorithm using several metrics.

### Algorithm Metrics

* Fitness Score
* Execution Time
* Number of Generations
* Constraint Violations

### Schedule Quality

* Required study hours achieved
* Number of conflicts
* Subject coverage
* Study balance
* Exam deadline consideration

---

## 🧪 Experimental Design

The project can investigate the effect of different GA parameters.

### Population Size

```text
20
50
100
```

### Mutation Rate

```text
0.01
0.05
0.10
0.20
```

### Number of Generations

```text
50
100
200
500
```

Results can be compared using:

```text
Fitness Score
Execution Time
Constraint Violations
```

---

# ⚖️ Baseline Comparison

To demonstrate the effectiveness of Genetic Algorithm, the system can compare GA with simpler scheduling methods.

```text
Random Scheduling
        vs
Rule-Based Scheduling
        vs
Genetic Algorithm
```

Example:

| Method            | Fitness | Conflicts | Execution Time |
| ----------------- | ------: | --------: | -------------: |
| Random            |      61 |         8 |         0.02 s |
| Rule-Based        |      74 |         4 |         0.01 s |
| Genetic Algorithm |      91 |         0 |         0.85 s |

> The values above are examples only and must be replaced with actual experimental results.

---

# 👥 Team

### Computer Engineering Senior Project

**Team Size:** 3 students

| Member   | Responsibility                   |
| -------- | -------------------------------- |
| Member 1 | Genetic Algorithm & Optimization |
| Member 2 | Backend & Database               |
| Member 3 | Frontend & UX                    |

All team members contribute to:

* System analysis
* Software design
* Integration
* Testing
* Documentation
* Presentation

---

# 📌 Project Scope

## Included

* User management
* Subject management
* Topic management
* Exam date management
* Study availability
* Automatic schedule generation
* Genetic Algorithm
* Calendar visualization
* Study progress tracking
* Performance evaluation

## Out of Scope

The current version does not include:

* Native mobile applications
* AI chatbot
* LLM integration
* Machine learning prediction
* Wearable device integration
* Social networking
* Google Calendar integration
* Multi-user collaborative scheduling

These features may be considered for future development.

---

# 🔮 Future Improvements

Possible future extensions include:

* 📱 Mobile application
* 🤖 AI-based study recommendations
* 📅 Google Calendar integration
* 🧠 Personalized learning prediction
* 📈 Advanced learning analytics
* 🔔 Notification and reminder system
* ☁️ Cloud deployment
* 👥 Collaborative study planning
* 📊 Adaptive scheduling based on actual study performance

---

# 🔐 Security Considerations

The system should implement basic web security practices:

* Password hashing
* Prepared SQL statements
* Input validation
* Session management
* Authentication and authorization
* Protection against SQL Injection
* Protection against XSS
* Secure error handling

Example:

```php
$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);
```

---

# 🧪 Testing

The system will include:

### Unit Testing

Test individual components such as:

* Fitness calculation
* Mutation
* Crossover
* Schedule validation

### Integration Testing

Test:

```text
Frontend
   ↓
PHP API
   ↓
Database
   ↓
Genetic Algorithm
```

### System Testing

Test complete user workflows.

Example:

```text
Login
→ Add Subjects
→ Set Availability
→ Generate Schedule
→ View Calendar
→ Complete Study Session
→ View Progress
```

### User Acceptance Testing

Test the application with representative student users and collect feedback regarding:

* Usability
* Schedule quality
* Ease of use
* Satisfaction

---

# 📚 Academic Contribution

The main academic contribution of this project is the application and evaluation of **Genetic Algorithm for constrained study schedule optimization**.

The project demonstrates how an optimization algorithm can be integrated into a practical web-based software system.

The project combines:

```text
Algorithm Design
        +
Data Structures
        +
Database Systems
        +
Web Programming
        +
Software Engineering
        +
Optimization
```

---

# 📄 Documentation

Project documentation will include:

```text
/docs
├── requirements.md
├── system-design.md
├── database-design.md
├── genetic-algorithm.md
├── testing.md
└── experiments.md
```

---

# 📜 License

This project is developed for educational and academic purposes.

```text
Copyright © 2026
Computer Engineering Senior Project
```

---

# ⭐ Project Status

🚧 **Under Development**

Current development stages:

* [ ] Requirement Analysis
* [ ] System Design
* [ ] Database Design
* [ ] UI/UX Design
* [ ] Basic Web Application
* [ ] Genetic Algorithm
* [ ] Schedule Generator
* [ ] Calendar
* [ ] Progress Tracking
* [ ] Integration Testing
* [ ] Performance Evaluation
* [ ] User Testing
* [ ] Final Documentation

---

## 💡 Core Idea

> **Let the student define the constraints. Let Genetic Algorithm find the schedule.**

**Smart Study Scheduler** aims to turn a time-consuming manual planning process into an automated optimization problem.

---

## 🚀 Getting Started

The application source is in this repository (PHP + MySQL + HTML/CSS/JavaScript, runs on XAMPP).

1. Copy the repository into `htdocs/` (e.g. `C:\xampp\htdocs\study_schedule`)
2. Import `database/schema.sql` via phpMyAdmin
3. Open `http://localhost/study_schedule/` — demo login: `demo@example.com` / `demo1234`

See **[SETUP.md](SETUP.md)** for installation details, architecture, database design, GA design, experiment results and team responsibilities.
