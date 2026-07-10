<?php
/**
 * QRAttend :: Sidebar Layout (Architectural Stub)
 * -----------------------------------------------------------------------------
 * The application uses a TOP-COLLAPSING HORIZONTAL NAVBAR (navbar.php) as the
 * primary navigation surface for all viewports, including mobile. A vertical
 * sidebar is intentionally NOT rendered to keep the mobile-first canvas clean
 * and touch-friendly.
 *
 * This file is retained as a documented structural spacer so that portal pages
 * can uniformly include the standard layout quartet:
 *     header.php -> navbar.php -> [page content] -> footer.php
 * without breaking the include contract, and so a future desktop-only sidebar
 * can be dropped in here without touching every page.
 *
 * If a vertical sidebar is later required (e.g. for wide admin screens), render
 * it conditionally based on viewport width or a user preference here.
 */
return; // No markup emitted by default.

