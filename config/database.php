<?php
/**
 * Koneksi database via PDO — singleton pattern
 */

function getDB(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host    = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname  = $_ENV['DB_NAME'] ?? '';
    $user    = $_ENV['DB_USER'] ?? '';
    $pass    = $_ENV['DB_PASS'] ?? '';
    $charset = 'utf8mb4';

    if (empty($dbname) || empty($user)) {
        error_log('Database credentials tidak dikonfigurasi di .env');
        die('Konfigurasi database belum diatur. Silakan hubungi administrator.');
    }

    try {
        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        error_log('DB Connection failed: ' . $e->getMessage());
        die('Terjadi kesalahan koneksi database. Silakan coba beberapa saat lagi.');
    }

    return $pdo;
}
