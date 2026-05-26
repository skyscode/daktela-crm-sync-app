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
        $statusId = $_GET['status_id'] ?? null;

        $items = $this->repo->findAll($page, $limit, $statusId ? (int) $statusId : null);
        $total = $this->repo->count($statusId ? (int) $statusId : null);

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
        $ticket = $this->repo->findByExternalId($params['id']);

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
