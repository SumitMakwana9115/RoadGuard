-- =====================================================
-- Road / Pathway Surface Damage - Complaint System
-- Enrollment: 230210107035 | U=35
-- Ward -> Area -> Spot | SLA: 7h / 48h
-- =====================================================

DROP DATABASE IF EXISTS road_complaint_system;
CREATE DATABASE road_complaint_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE road_complaint_system;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    role ENUM('complainant','staff','supervisor') NOT NULL DEFAULT 'complainant',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Complaint Categories
CREATE TABLE IF NOT EXISTS complaint_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Ward Master (Level 1)
CREATE TABLE IF NOT EXISTS ward_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Area Master (Level 2)
CREATE TABLE IF NOT EXISTS area_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES ward_master(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Spot Master (Level 3)
CREATE TABLE IF NOT EXISTS spot_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES area_master(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Status Master
CREATE TABLE IF NOT EXISTS status_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    color VARCHAR(20) DEFAULT '#6c757d'
) ENGINE=InnoDB;

-- Valid Status Transitions
CREATE TABLE IF NOT EXISTS valid_transitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_status_id INT NOT NULL,
    to_status_id INT NOT NULL,
    allowed_role ENUM('complainant','staff','supervisor') NOT NULL,
    FOREIGN KEY (from_status_id) REFERENCES status_master(id),
    FOREIGN KEY (to_status_id) REFERENCES status_master(id)
) ENGINE=InnoDB;

-- Complaints
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_uid VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category_id INT NOT NULL,
    ward_id INT NOT NULL,
    area_id INT NOT NULL,
    spot_id INT NOT NULL,
    exact_location VARCHAR(255),
    priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    complaint_date DATETIME NOT NULL,
    complainant_id INT NOT NULL,
    status_id INT NOT NULL DEFAULT 1,
    assigned_to INT DEFAULT NULL,
    initial_response_deadline DATETIME DEFAULT NULL,
    resolution_deadline DATETIME DEFAULT NULL,
    is_repeated TINYINT(1) DEFAULT 0,
    is_reopened TINYINT(1) DEFAULT 0,
    reopen_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES complaint_categories(id),
    FOREIGN KEY (ward_id) REFERENCES ward_master(id),
    FOREIGN KEY (area_id) REFERENCES area_master(id),
    FOREIGN KEY (spot_id) REFERENCES spot_master(id),
    FOREIGN KEY (complainant_id) REFERENCES users(id),
    FOREIGN KEY (status_id) REFERENCES status_master(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB;

-- Complaint Attachments
CREATE TABLE IF NOT EXISTS complaint_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    upload_type ENUM('complaint_proof','action_proof') NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Complaint History
CREATE TABLE IF NOT EXISTS complaint_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    from_status_id INT,
    to_status_id INT NOT NULL,
    updated_by INT NOT NULL,
    remark TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (from_status_id) REFERENCES status_master(id),
    FOREIGN KEY (to_status_id) REFERENCES status_master(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Assignments
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    assigned_to INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    remarks TEXT,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Feedback / Verification
CREATE TABLE IF NOT EXISTS feedback_or_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK(rating >= 1 AND rating <= 5),
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- SEED DATA
-- =====================================================

-- Status Master
INSERT INTO status_master (id, status_name, description, sort_order, color) VALUES
(1, 'Submitted', 'Complaint has been submitted', 1, '#6c757d'),
(2, 'Verified', 'Complaint verified by supervisor', 2, '#0dcaf0'),
(3, 'Assigned', 'Assigned to staff member', 3, '#0d6efd'),
(4, 'In Progress', 'Staff is working on it', 4, '#fd7e14'),
(5, 'Resolved', 'Complaint has been resolved', 5, '#198754'),
(6, 'Closed', 'Complaint closed after verification', 6, '#20c997'),
(7, 'Reopened', 'Complaint reopened by complainant', 7, '#dc3545'),
(8, 'Escalated', 'Complaint escalated due to SLA breach', 8, '#dc3545'),
(9, 'Rejected', 'Complaint rejected by supervisor', 9, '#6c757d');

-- Valid Transitions
INSERT INTO valid_transitions (from_status_id, to_status_id, allowed_role) VALUES
(1, 2, 'supervisor'), (1, 9, 'supervisor'),
(2, 3, 'supervisor'),
(3, 4, 'staff'),
(4, 5, 'staff'),
(5, 6, 'supervisor'), (5, 7, 'complainant'),
(7, 3, 'supervisor'),
(8, 3, 'supervisor'),
(1, 8, 'supervisor'), (2, 8, 'supervisor'),
(3, 8, 'supervisor'), (4, 8, 'supervisor');

-- Complaint Categories (Road / Pathway Surface Damage)
INSERT INTO complaint_categories (name, description) VALUES
('Pothole on Road', 'Deep holes or depressions in the road surface'),
('Cracked / Fractured Road', 'Visible cracks or fractures on road surface'),
('Waterlogged Road', 'Standing water due to poor drainage on roads'),
('Uneven Surface / Bumps', 'Uneven road surface causing discomfort'),
('Broken Footpath / Sidewalk', 'Damaged or broken pedestrian walkways'),
('Missing / Damaged Manhole Cover', 'Open or broken manhole covers on roads'),
('Damaged Speed Breaker', 'Worn out or broken speed breakers'),
('Faded Road Markings', 'Unclear or missing road markings and signs'),
('Road Debris / Obstruction', 'Construction debris or objects blocking road'),
('Damaged Divider / Barrier', 'Broken road dividers or safety barriers');

-- Ward Master
INSERT INTO ward_master (name, description) VALUES
('North Ward', 'Northern region of Bhavnagar city (Chitra, Fulsar)'),
('South Ward', 'Southern region of Bhavnagar city (Sidsar, Subhashnagar)'),
('East Ward', 'Eastern region of Bhavnagar city (Sardar Nagar, Station Area)'),
('West Ward', 'Western region of Bhavnagar city (Kaliyabid, Waghawadi Road)'),
('Central Ward', 'Central business district and old city (Kalanala, Crescent)');

-- Area Master
INSERT INTO area_master (ward_id, name, description) VALUES
(1, 'Chitra', 'Major industrial and residential township'),
(1, 'Fulsar', 'Growing suburban locality'),
(2, 'Subhashnagar', 'Established residential area for families'),
(2, 'Sidsar', 'Rapidly developing southern suburb'),
(3, 'Sardar Nagar', 'Prime well-established residential core'),
(3, 'Station Road Area', 'Busy commercial and transit hub'),
(4, 'Kaliyabid', 'Highly popular lifestyle and shopping hub'),
(4, 'Waghawadi Road', 'Premium residential and commercial road'),
(5, 'Kalanala', 'Traditional market area and central junction'),
(5, 'Crescent Circle', 'Prominent central commercial hub');

-- Spot Master
INSERT INTO spot_master (area_id, name, description) VALUES
(1,'Chitra GIDC Road','Main industrial estate road'),
(1,'Chitra Marketing Yard','Agricultural market area'),
(2,'Fulsar Main Road','Primary suburban connecting road'),
(3,'Subhashnagar Water Tank','Landmark water distribution point'),
(4,'Sidsar Sports Complex Road','Road leading to sports facility'),
(5,'Sardar Nagar Main Street','Central street of prime residential area'),
(6,'Bhavnagar Terminus Gate','Main entrance to railway station'),
(7,'Kaliyabid Water Tank Circle','Popular local landmark junction'),
(7,'Kaliyabid Pani Ni Tanki','Major reference point in Kaliyabid'),
(8,'Waghawadi Road Joggers Park','Near premium park area'),
(9,'Kalanala Bridge','Historic bridge section'),
(10,'Crescent Circle Roundabout','Main traffic intersection');
