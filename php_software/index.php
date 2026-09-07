<?php
require_once __DIR__ . '/backend/config.php';

// If user is already logged in, they can go to dashboard; otherwise show the landing page
if (isUserLoggedIn()) {
    header("Location: dashboard/index.php");
} else {
    header("Location: landing/index.php");
}
exit();

