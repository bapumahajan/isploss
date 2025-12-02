CREATE DATABASE customer_management;

USE customer_management;

-- User Table for Authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL
);

-- Customer Basic Information Table
CREATE TABLE customer_basic_information (
    circuit_id INT AUTO_INCREMENT PRIMARY KEY,
    organization_name VARCHAR(255),
    customer_address TEXT,
    contact_person_name VARCHAR(255),
    contact_number VARCHAR(20),
    ce_email_id VARCHAR(255)
);

-- Network Details Table
CREATE TABLE network_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    circuit_id INT,
    product_type ENUM('ILL', 'EBB', 'Lease-Line'),
    pop_name VARCHAR(255),
    switch_ip VARCHAR(255),
    switch_port VARCHAR(50),
    bandwidth VARCHAR(50),
    ckt_status ENUM('Active', 'Terminated', 'Suspended'),
    FOREIGN KEY (circuit_id) REFERENCES customer_basic_information(circuit_id)
);