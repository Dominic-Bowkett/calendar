<?php
// Copy this file to config.php and fill in your values

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'booking_app');
define('DB_USER', 'root');
define('DB_PASS', '');

// Google OAuth2 — create credentials at https://console.cloud.google.com/
// Enable the Google Calendar API and create OAuth2 credentials (Web application type)
// Add your redirect URI: http://yourdomain.com/api/google-callback.php
define('GOOGLE_CLIENT_ID', 'your-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-client-secret');
define('GOOGLE_REDIRECT_URI', 'http://localhost/api/google-callback.php');

// App
define('APP_URL', 'http://localhost');
define('APP_NAME', 'My Booking App');
define('ADMIN_TIMEZONE', 'Europe/London');

// Migration runner secret — set this to a long random string, then visit:
// https://yourdomain.com/migrate.php?secret=YOUR_SECRET_HERE
define('MIGRATE_SECRET', 'change-this-to-a-long-random-string');

// Set default timezone
date_default_timezone_set(ADMIN_TIMEZONE);
