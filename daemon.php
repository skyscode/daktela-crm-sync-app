<?php

declare(strict_types=1);

// Long-running background sync process.
// Started once on the server and runs forever — syncs Daktela data every hour.
// Must NOT use system cron; the timing loop is implemented here in PHP.
