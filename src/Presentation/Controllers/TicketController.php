<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Infrastructure\Persistence\TicketRepository;

// Handles HTTP requests for the /api/tickets routes.
class TicketController
{
    public function __construct(private TicketRepository $repo) {}

    public function index(): void
    {
        $page     = max(1, (int) ($_GET['page']      ?? 1));
        $limit    = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $statusId = isset($_GET['status_id']) ? (int) $_GET['status_id'] : null;

        $items = $this->repo->findAll($page, $limit, $statusId);
        $total = $this->repo->count($statusId);

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

    public function show(array $params): void
    {
        $ticket = $this->repo->findById((int) $params['id']);

        if ($ticket === null) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }

        $this->json(['data' => $ticket]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
