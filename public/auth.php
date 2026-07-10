<?php
/**
 * QRAttend :: Auth Proxy
 * -----------------------------------------------------------------------------
 * This file serves as a public proxy to the private authentication engine.
 * It is required because Render restricts direct HTTP access to the /app directory.
 */
require_once __DIR__ . '/../app/auth/auth.php';
