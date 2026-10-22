<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirectAfterLogin();
} else {
    redirect('login.php');
}
