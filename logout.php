<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

Auth::logoutUser();
set_flash('success', 'You have been signed out.');
redirect('/login.php');
