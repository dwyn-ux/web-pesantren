<?php
/**
 * CSRF protection utilities.
 *
 * Call `ensureCsrfToken()` at the start of a script that renders a form.
 * Include a hidden input `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">` in the form.
 * Verify the token on POST with `verifyCsrfToken($_POST['csrf_token'] ?? '')`.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Generate a token if not present. */
function ensureCsrfToken(): void {
    if (empty($_SESSION['csrf_token'])) {
        // 32‑byte random string, hex‑encoded for safe HTML output.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/** Verify token and halt execution on failure. */
function verifyCsrfToken(string $token): void {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
    // Optionally rotate token after successful use.
    unset($_SESSION['csrf_token']);
}
?>
