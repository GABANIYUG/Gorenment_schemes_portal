# Government Welfare Scheme Management and Information Portal

## Project Objective
A comprehensive Open-Ended Project (OEP) for a university, designed to serve as a Government Welfare Scheme Management and Information Portal. Users can discover government welfare schemes, check eligibility, view required documents, and access official application links.

## Technology Stack
- **Frontend**: HTML5, CSS3, Bootstrap 5, Vanilla JavaScript
- **Backend**: PHP 8.x
- **Database**: MySQL
- **Environment**: XAMPP (Apache + MySQL)

## Features
- **Public Portal**: View and search for government schemes.
- **Authentication**: Secure registration and login using PHP sessions and password hashing.
- **Citizen Dashboard**: Edit profile, update details, and deactivate account.
- **Admin Dashboard**: Manage schemes (CRUD operations), manage users, and view contact messages.
- **Search & Filters**: Search schemes by keywords, and filter by state or category.
- **Search History**: Saves user search history if logged in.
- **Security**: PDO prepared statements (SQL injection protection), HTML/JS/PHP validation, secure authentication.

## Folder Structure
- `/admin/` - Admin dashboard and scheme management
- `/assets/` - Custom CSS, JS, and images
- `/citizen/` - Citizen profile management
- `/config/` - Database configuration
- `/database/` - Contains the `government_welfare_portal.sql` file
- `/includes/` - Reusable PHP components (navbar, header, footer, auth)
- `/uploads/schemes/` - Image uploads for schemes

## How to Run (XAMPP Setup)
1. **Start XAMPP**: Open XAMPP Control Panel and start **Apache** and **MySQL**.
2. **Database Setup**:
   - Open your browser and go to `http://localhost/phpmyadmin/`.
   - Create a new database named `government_welfare_portal`.
   - Click on the `Import` tab, browse for the `database/government_welfare_portal.sql` file provided in this repository, and import it. This will create all required tables and insert sample demo data.
3. **Project Deployment**:
   - Copy the entire project folder (`gov schemes _ 2` or whatever you rename it to) into the XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\welfare-portal`).
4. **Access the Project**:
   - Open your browser and go to: `http://localhost/welfare-portal/` (adjust folder name if different).

## Default Accounts
The SQL file comes with pre-created accounts for testing.

**Admin Login:**
- **Email**: admin@welfare.gov
- **Password**: password

**Citizen Login:**
- **Email**: john@example.com
- **Password**: password

*Note: All passwords are encrypted securely in the database.*

## Database Configuration
If your MySQL credentials differ from the default XAMPP settings, edit the `config/database.php` file:
```php
$host = 'localhost';
$dbname = 'government_welfare_portal';
$username = 'root'; // Change if needed
$password = ''; // Change if needed
```

## Important Notes
- This is a traditional server-rendered application. It strictly adheres to the requested technology stack (PHP/MySQL + Bootstrap) without any modern SPA frameworks like React or Node.js.
- Uploaded scheme images will be saved in the `/uploads/schemes/` directory. Ensure this folder has write permissions if deploying to a live Linux server.
