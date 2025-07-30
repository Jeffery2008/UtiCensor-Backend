<?php

namespace UtiCensor\Controllers;

use UtiCensor\Models\NetworkFlow;
use UtiCensor\Models\User;
use UtiCensor\Utils\JWT;

class NetworkFlowController
{
    private $flowModel;
    private $userModel;

    public function __construct()
    {
        $this->flowModel = new NetworkFlow();
        $this->userModel = new User();
    }

    public function index(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $limit = min((int) ($_GET['limit'] ?? 50), 1000);
        $offset = ($page - 1) * $limit;

        $filters = [
            'device_id' => $_GET['device_id'] ?? null,
            'start_time' => $_GET['start_time'] ?? null,
            'end_time' => $_GET['end_time'] ?? null,
            'local_ip' => $_GET['local_ip'] ?? null,
            'other_ip' => $_GET['other_ip'] ?? null,
            'application' => $_GET['application'] ?? null,
            'protocol' => $_GET['protocol'] ?? null,
            'search' => $_GET['search'] ?? null
        ];

        $flows = $this->flowModel->getAll($offset, $limit, $filters);
        $total = $this->flowModel->count($filters);

        $this->jsonResponse([
            'flows' => $flows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    public function show(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->jsonResponse(['error' => 'Flow ID is required'], 400);
            return;
        }

        $flow = $this->flowModel->findById($id);
        if (!$flow) {
            $this->jsonResponse(['error' => 'Flow not found'], 404);
            return;
        }

        // Get additional information
        $flow['ssl_info'] = $this->flowModel->getSSLInfo($id);
        $flow['http_info'] = $this->flowModel->getHTTPInfo($id);

        $this->jsonResponse(['flow' => $flow]);
    }

    public function getByFilter(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $filterId = (int) ($_GET['filter_id'] ?? 0);
        if (!$filterId) {
            $this->jsonResponse(['error' => 'Filter ID is required'], 400);
            return;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $limit = min((int) ($_GET['limit'] ?? 50), 1000);
        $offset = ($page - 1) * $limit;

        try {
            $flows = $this->flowModel->getByFilter($filterId, $offset, $limit);
            $total = $this->flowModel->countByFilter($filterId);

            $this->jsonResponse([
                'flows' => $flows,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to apply filter: ' . $e->getMessage()], 500);
        }
    }

    public function getStats(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        if (!$deviceId) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        try {
            $stats = $this->flowModel->getStatsByDevice($deviceId, $startDate, $endDate);
            $topApps = $this->flowModel->getTopApplications($deviceId, $startDate, $endDate);
            $topProtocols = $this->flowModel->getTopProtocols($deviceId, $startDate, $endDate);
            $topHosts = $this->flowModel->getTopHosts($deviceId, $startDate, $endDate);

            $this->jsonResponse([
                'device_id' => $deviceId,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'daily_stats' => $stats,
                'top_applications' => $topApps,
                'top_protocols' => $topProtocols,
                'top_hosts' => $topHosts
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to get statistics'], 500);
        }
    }

    public function getHourlyStats(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $date = $_GET['date'] ?? date('Y-m-d');

        if (!$deviceId) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        try {
            $stats = $this->flowModel->getHourlyStats($deviceId, $date);

            // Fill missing hours with zero values
            $hourlyData = array_fill(0, 24, ['hour' => 0, 'flow_count' => 0, 'total_bytes' => 0]);
            foreach ($stats as $stat) {
                $hourlyData[$stat['hour']] = $stat;
            }

            $this->jsonResponse([
                'device_id' => $deviceId,
                'date' => $date,
                'hourly_stats' => array_values($hourlyData)
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to get hourly statistics'], 500);
        }
    }

    public function getApplications(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $applications = $this->flowModel->getUniqueApplications();
        $this->jsonResponse(['applications' => $applications]);
    }

    public function getProtocols(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $protocols = $this->flowModel->getUniqueProtocols();
        $this->jsonResponse(['protocols' => $protocols]);
    }

    public function getTopApplications(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $limit = (int) ($_GET['limit'] ?? 10);

        if (!$deviceId) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        try {
            $applications = $this->flowModel->getTopApplications($deviceId, $startDate, $endDate, $limit);
            $this->jsonResponse(['applications' => $applications]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to get top applications'], 500);
        }
    }

    public function getTopProtocols(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $limit = (int) ($_GET['limit'] ?? 10);

        if (!$deviceId) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        try {
            $protocols = $this->flowModel->getTopProtocols($deviceId, $startDate, $endDate, $limit);
            $this->jsonResponse(['protocols' => $protocols]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to get top protocols'], 500);
        }
    }

    public function getTopHosts(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $limit = (int) ($_GET['limit'] ?? 10);

        if (!$deviceId) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        try {
            $hosts = $this->flowModel->getTopHosts($deviceId, $startDate, $endDate, $limit);
            $this->jsonResponse(['hosts' => $hosts]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to get top hosts'], 500);
        }
    }

    public function export(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $format = $_GET['format'] ?? 'csv';
        $limit = min((int) ($_GET['limit'] ?? 1000), 10000);

        $filters = [
            'device_id' => $_GET['device_id'] ?? null,
            'start_time' => $_GET['start_time'] ?? null,
            'end_time' => $_GET['end_time'] ?? null,
            'local_ip' => $_GET['local_ip'] ?? null,
            'other_ip' => $_GET['other_ip'] ?? null,
            'application' => $_GET['application'] ?? null,
            'protocol' => $_GET['protocol'] ?? null
        ];

        try {
            $flows = $this->flowModel->getAll(0, $limit, $filters);

            if ($format === 'csv') {
                $this->exportCSV($flows);
            } elseif ($format === 'json') {
                $this->exportJSON($flows);
            } else {
                $this->jsonResponse(['error' => 'Unsupported export format'], 400);
            }
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Export failed'], 500);
        }
    }

    private function exportCSV(array $flows): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="network_flows_' . date('Y-m-d_H-i-s') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // CSV headers
        $headers = [
            'ID', 'Timestamp', 'Device', 'Local IP', 'Local Port', 'Remote IP', 'Remote Port',
            'Protocol', 'Application', 'Host', 'Bytes', 'Established'
        ];
        fputcsv($output, $headers);

        // CSV data
        foreach ($flows as $flow) {
            $row = [
                $flow['id'],
                $flow['recv_ts'],
                $flow['device_name'] ?? 'Unknown',
                $flow['local_ip'],
                $flow['local_port'],
                $flow['other_ip'],
                $flow['other_port'],
                $flow['detected_protocol_name'],
                $flow['detected_application_name'],
                $flow['host_server_name'],
                $flow['bytes_len'],
                $flow['established'] ? 'Yes' : 'No'
            ];
            fputcsv($output, $row);
        }

        fclose($output);
    }

    private function exportJSON(array $flows): void
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="network_flows_' . date('Y-m-d_H-i-s') . '.json"');

        echo json_encode([
            'export_date' => date('c'),
            'total_records' => count($flows),
            'flows' => $flows
        ], JSON_PRETTY_PRINT);
    }

    private function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    private function getCurrentUser(): ?array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        $payload = JWT::decode($token);
        
        if (!$payload || !isset($payload['user_id'])) {
            return null;
        }

        return $this->userModel->findById($payload['user_id']);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

