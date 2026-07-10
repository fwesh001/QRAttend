<?php
/**
 * QRAttend :: Public Logout Router
 * -----------------------------------------------------------------------------
 * Thin public entry that delegates to the secure logout branch inside the
 * authentication engine. Kept in the web root so the navbar can link to it
 * directly without exposing app/ internals.
 */
require_once __DIR__ . '/../app/auth/auth.php';

