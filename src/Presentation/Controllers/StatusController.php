<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Infrastructure\Persistence\StatusRepository;

// Handles HTTP requests for the /api/statuses route.
class StatusController
{
    public function __construct(private StatusRepository $repo) {}

    public function index(): void
    {
        $page  = max(1, (int) ($_GET['page']  ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));

        $items = $this->repo->findAll($page, $limit);
        $total = $this->repo->count();

        $this->json([
            'data' => $items,
            'meta' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
