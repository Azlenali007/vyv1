<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

if (current_admin()) {
    redirect('/admin/dashboard.php');
}
redirect('/admin/login.php');
