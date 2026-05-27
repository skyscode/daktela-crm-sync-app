<?php

declare(strict_types=1);

namespace App\Infrastructure\External;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// HTTP client for the Daktela REST API.
// Wraps Guzzle — all knowledge of Daktela's URL structure lives here.
// SyncService calls this; it knows nothing about HTTP.
class DaktelaApiClient
{
    private Client $client;
    private string $accessToken;

    public function __construct(array $config)
    {
        $this->accessToken = $config['access_token'];
        $this->client = new Client([
            'base_uri' => rtrim($config['api_url'], '/') . '/',
            'verify'   => $config['verify_ssl'] ?? true,
            'timeout'  => 30,
        ]);
    }

    public function getContacts(): array
    {
        return $this->fetchAll('contacts.json');
    }

    public function getTickets(): array
    {
        return $this->fetchAll('tickets.json');
    }

    public function getStatuses(): array
    {
        return $this->fetchAll('statuses.json');
    }

    private function fetchAll(string $endpoint): array
    {
        $results = [];
        $page    = 1;
        $limit   = 100;

        do {
            $response = $this->request($endpoint, [
                'take' => $limit,
                'skip' => ($page - 1) * $limit,
            ]);

            $items   = $response['result']['data'] ?? [];
            $results = array_merge($results, $items);
            $total   = $response['result']['total'] ?? 0;
            $page++;
        } while (count($results) < $total);

        return $results;
    }

    private function request(string $endpoint, array $params = []): array
    {
        $params['accessToken'] = $this->accessToken;

        $response = $this->client->get($endpoint, ['query' => $params]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
