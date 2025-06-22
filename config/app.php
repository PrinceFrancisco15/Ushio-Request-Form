<?php
// config/app.php - Application configuration settings

// Base URL - Update this to match your environment
$base_url = '/request-system/'; // Adjust based on your folder structure

// Application settings
$app_name = 'USHIO Request Form System';
$company_name = 'USHIO Philippines, Inc.';
$app_version = '1.0';

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Define roles and their hierarchy 
$roles = [
    'employee' => 'Employee',
    'manager' => 'Department Manager',
    'general_manager' => 'General Manager',
    'it_staff' => 'IT Staff'
];

// Define departments
$departments = [
    1 => 'Human Resources',
    2 => 'Manufacturing',
    3 => 'Finance',
    4 => 'Quality Control',
    5 => 'Information Technology',
    6 => 'Engineering',
    7 => 'Administration'
];

// Default pagination limit
$items_per_page = 10;