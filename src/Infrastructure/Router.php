<?php

declare(strict_types=1);

namespace App\Infrastructure;

// Minimal HTTP router — matches METHOD + path to a controller callable.
// No framework dependency. Reads $_SERVER['REQUEST_METHOD'] and REQUEST_URI.
class Router
{
}
