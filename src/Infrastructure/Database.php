<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

// Singleton PDO wrapper.
// Opens exactly one MySQL connection for the lifetime of the process.
// Every repository and service gets the connection via Database::connection().
class Database
{
}
