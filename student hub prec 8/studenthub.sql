-- =============================================================================
-- STUDENT HUB - DATABASE SCHEMA (PRACTICAL 8)
-- Problem Definition:
-- Design the database for StudentHub with students, events and registrations.
-- Create PHP database connection using MYSQL statements.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `studenthub`
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `studenthub`;

-- -----------------------------------------------------------------------------
-- 1. Table: students
-- Stores registered student details and account credentials
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `students`;

CREATE TABLE `students` (
    `student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `fullname` VARCHAR(100) NOT NULL,
    `enrollment_no` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `mobile` VARCHAR(15) NOT NULL,
    `department` VARCHAR(50) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 2. Table: events
-- Stores university campus events, hackathons, and workshops
-- -----------------------------------------------------------------------------
CREATE TABLE `events` (
    `event_id` INT AUTO_INCREMENT PRIMARY KEY,
    `tag` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `event_date` VARCHAR(50) NOT NULL,
    `venue` VARCHAR(100) NOT NULL,
    `organizer` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 3. Table: registrations
-- Junction table for Many-to-Many relationship between students and events
-- -----------------------------------------------------------------------------
CREATE TABLE `registrations` (
    `registration_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `event_id` INT NOT NULL,
    `registration_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(20) DEFAULT 'Confirmed',
    UNIQUE KEY `unique_student_event` (`student_id`, `event_id`),
    CONSTRAINT `fk_reg_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reg_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- Seed Data: Sample Students
-- -----------------------------------------------------------------------------
INSERT INTO `students` (`student_id`, `fullname`, `enrollment_no`, `email`, `mobile`, `department`, `semester`, `password`) VALUES
(1, 'Yashvi Vekariya', '23CE001', 'yashvi@charusat.edu.in', '9876543210', 'Computer Engineering', 'Semester 3', '$2y$10$e8V9Mh4q3s7kM9V6Y.2Z6eO5P7j8R1X4C5V6B7N8M9Q1W2E3R4T5Y'),
(2, 'Aarav Patel', '23IT045', 'aarav.patel@charusat.edu.in', '9123456780', 'Information Technology', 'Semester 3', '$2y$10$e8V9Mh4q3s7kM9V6Y.2Z6eO5P7j8R1X4C5V6B7N8M9Q1W2E3R4T5Y'),
(3, 'Diya Sharma', '23CS012', 'diya.sharma@charusat.edu.in', '9988776655', 'Computer Science & Engineering', 'Semester 3', '$2y$10$e8V9Mh4q3s7kM9V6Y.2Z6eO5P7j8R1X4C5V6B7N8M9Q1W2E3R4T5Y');

-- -----------------------------------------------------------------------------
-- Seed Data: Events (matching StudentHub events catalog)
-- -----------------------------------------------------------------------------
INSERT INTO `events` (`event_id`, `tag`, `title`, `event_date`, `venue`, `organizer`, `description`) VALUES
(1, 'Technical Fest', 'Cognizance 2026 - Tech Fest & Hackathon', 'September 25 - 26, 2026', 'Central Auditorium & IT Labs', 'Computer Engineering', 'A 36-hour coding hackathon featuring AI/ML challenges, Web development contests, Code Relay, and Bug Hunting competitions.'),
(2, 'Cultural Fest', 'Spoural 2026 - Youth Cultural Festival', 'October 12 - 14, 2026', 'Open Air Theatre', 'Student Activity Council', 'Annual cultural fest featuring dance competitions, drama, music band battles, fashion shows, and celebrity musical night.'),
(3, 'Workshop', 'TechConclave: AI & Cloud Computing Workshop', 'November 05, 2026', 'Seminar Hall 1, CE Block', 'Industry Experts', 'Hands-on practical session covering Artificial Intelligence basics, Cloud Deployment, and career roadmaps for students.'),
(4, 'Sports Meet', 'CHARUSAT Premier League & Sports Meet', 'December 18 - 22, 2026', 'Sports Ground', 'Sports Department', 'Inter-department tournament for Cricket, Football, Volleyball, Badminton, Table Tennis, and Chess.'),
(5, 'Academic', '15th Annual Convocation Ceremony', 'January 10, 2027', 'Grand Convention Hall', 'Graduating Batches', 'Awarding of degrees and academic Gold Medals to graduating B.Tech, M.Tech, and Ph.D. students.'),
(6, 'Workshop', 'Web Development Bootcamp', 'January 20 - 22, 2027', 'Computer Lab 2', 'IT Department', 'A beginner-friendly workshop covering HTML, CSS, JavaScript and basic web development projects.'),
(7, 'Technical Fest', 'CodeSprint Programming Contest', 'February 05, 2027', 'Programming Lab', 'Computer Engineering', 'Programming competition focused on problem solving, data structures and algorithmic thinking.'),
(8, 'Sports Meet', 'CHARUSAT Badminton Championship', 'February 18 - 19, 2027', 'Indoor Sports Complex', 'Sports Department', 'University-level badminton championship with singles and doubles categories.');

-- -----------------------------------------------------------------------------
-- Seed Data: Sample Registrations (Many-to-Many Relationship Demonstration)
-- -----------------------------------------------------------------------------
INSERT INTO `registrations` (`registration_id`, `student_id`, `event_id`, `status`) VALUES
(1, 1, 1, 'Confirmed'),
(2, 1, 3, 'Confirmed'),
(3, 2, 1, 'Confirmed'),
(4, 2, 4, 'Confirmed'),
(5, 3, 2, 'Confirmed');
