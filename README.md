# QRAttend - Smart Attendance System

A secure, role-based QR attendance management system built for institutional use.

## Features
- **Role-Based Portals:** Admin, Lecturer, and Student dashboards.
- **Secure Authentication:** Password hashing (Bcrypt), session fixation protection, and strict role guards.
- **Auto-Rotating QR:** Real-time token rotation to prevent attendance fraud.
- **Transactional Admin:** Bulk student provisioning with secure transaction commits/rollbacks.
- **Audit Logging:** Tamper-proof audit trails with secure log rotation.

## Requirements
- **PHP 8.2+** (configured in XAMPP)
- **MySQL 8.0+**
- **Extensions:** `pdo_mysql`, `openssl`

## Installation
1. Clone the repository to your `htdocs` folder.
2. Create an `.env` file based on `.env.example` with your database credentials.
3. Import `database/schema.sql` via MySQL CLI or phpMyAdmin.
4. Set permissions on the `/storage` directory (e.g., `chmod 0750 storage` for Linux/macOS).
5. Access the application via `http://localhost/`.

## Security Notes
- The `/app` and `/storage` directories are protected via `.htaccess`.
- All database interactions use native prepared statements to mitigate SQL Injection.
- Sensitive environment variables are parsed via a protected bootstrap chain.