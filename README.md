# EventSphere – Event Management System

EventSphere is a web-based event management system developed as a capstone project for the Bachelor of Science in Information Technology program. The system is designed to streamline event creation, management, and coordination through a centralized web-based platform.

## Key Features

- Role-based access control (Admin, Tournament Manager, User)
- Event creation, scheduling, and management
- Dashboard for monitoring events and announcements
- Sports tournament brackets
- Responsive web interface

## Technologies Used

- **Backend:** Laravel, PHP
- **Frontend:** Vue 3, Inertia.js, PrimeVue
- **Styling:** Tailwind CSS
- **Database:** PostgreSQL
- **Version Control:** Git & GitHub

## Installation Guide

1. Clone the repository  
2. Install backend dependencies:
   ```bash
   composer install
   ```
3. Install frontend dependencies:
   ```bash
   npm install
   ```
4. Copy the environment file and configure your database in `.env`:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
5. Run database migrations to create the tables:
   ```bash
   php artisan migrate
   ```
6. Seed the database with essential data (e.g., user roles):
   ```bash
   php artisan db:seed --class=RoleSeeder
   ```
7. Create your own administrator account. Run `php artisan tinker` and then execute the following code, replacing the placeholder values:
   ```php
   $role = App\Models\Role::where('name', 'Admin')->first();
   $user = App\Models\User::create([
       'name' => 'Your Name',
       'email' => 'your-email@example.com',
       'password' => Illuminate\Support\Facades\Hash::make('your-secure-password'),
   ]);
   $user->roles()->attach($role->id);
   exit; // Type exit to leave tinker
   ```
8. Run the application:
   ```bash
   php artisan serve
   npm run dev
   ```

### Note on Database Seeding

The project includes seeders for creating dummy data for development purposes (e.g., `UserSeeder`, `EventSeeder`). The `UserSeeder` in particular creates default users with insecure passwords ('123'). 

For a secure installation, it is strongly recommended to **only** run the `RoleSeeder` and create your own admin account as described above. 

If you need to populate your development database with dummy data, you can run the main seeder with `php artisan db:seed`. If you do this, you **must** change the passwords for the default accounts immediately.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Project Contributors
**EventSphere – Event Management System**

This system was collaboratively designed and developed by:

- Melvin N. Amparado
- Glene Miko A. Jaudian
- Nick Joey A. Cabahug
