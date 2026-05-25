<?php

declare(strict_types=1);

/**
 * index.php — Root entry point
 *
 * Sits at the document root (public_html or domain root on shared hosting).
 * Delegates all request handling to api/index.php (the real front controller).
 */

require_once __DIR__ . '/src/Presentation/api.php';