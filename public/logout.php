<?php
/**
 * public/logout.php — keluar dari akun.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
logout_user();
session_start(); // mulai sesi baru untuk flash
set_flash('info', 'Anda telah keluar dari akun.');
redirect(BASE_URL . '/index.php');
