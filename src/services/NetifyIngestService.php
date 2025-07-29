<?php

namespace UtiCensor\Services;

use UtiCensor\Utils\Database;
use UtiCensor\Models\Device;
use UtiCensor\Models\NetworkFlow;

class NetifyIngestService
{
    private $db;
    private $deviceModel;
    private $flowModel;
    private $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->deviceModel = new Device();
        $this->flowModel = new NetworkFlow();
        $this->config = require __DIR__ . '/../../config/app.php';
    }

    public function startListener(): void
    {
        $listen = sprintf("tcp://%s:%d", 
            $this->config['netify']['listen_host'], 
            $this->config['netify']['listen_port']
        );

        $server = stream_socket_server($listen, $errno, $errstr);
        if (!$server) {
            throw new \Exception("Failed to start listener: $errstr ($errno)");
        }

        echo date('c') . " Netify listener started on $listen\n";

        while ($conn = @stream_socket_accept($server, -1)) {
            $this->handleConnection($conn);
        }
    }

    private function handleConnection($conn): void
    {
        [$remoteIp, $remotePort] = $this->getEndpointParts($conn, true);
        [$localIp, $localPort] = $this->getEndpointParts($conn, false);
        
        echo date('c') . " Client connected from {$remoteIp}:{$remotePort}\n";

        stream_set_blocking($conn, true);
        $buffer = '';

        while (!feof($conn)) {
            $chunk = fread($conn, $this->config['netify']['buffer_size']);
            if ($chunk === false || $chunk === '') {
                usleep(10000);
                continue;
            }
            
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $trim = trim($line);
                
                if ($trim === '') continue;

                $this->processNetifyData($trim, $remoteIp, $remotePort, $localIp, $localPort);
            }
        }

        fclose($conn);
        echo date('c') . " Client disconnected {$remoteIp}:{$remotePort}\n";
    }

    private function processNetifyData(string $jsonData, string $remoteIp, int $remotePort, string $localIp, int $localPort): void
    {
        $recvMs = (int) floor(microtime(true) * 1000);
        $recvTs = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

        $jsonValid = 1;
        $jsonError = null;
        $obj = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonValid = 0;
            $jsonError = json_last_error_msg();
            $obj = null;
        }

        $type = $obj['type'] ?? 'other';

        if ($type === 'flow') {
            $this->processFlowData($obj, $jsonData, $recvTs, $recvMs, $remoteIp, $remotePort, $localIp, $localPort, $jsonValid, $jsonError);
        } elseif ($type === 'stats') {
            $this->processStatsData($obj, $jsonData, $recvTs, $recvMs);
        }

        // Log processing
        $this->logProcessing($type, $obj, strlen($jsonData));
    }

    private function processFlowData(array $obj, string $jsonData, string $recvTs, int $recvMs, string $remoteIp, int $remotePort, string $localIp, int $localPort, int $jsonValid, ?string $jsonError): void
    {
        $flow = $obj['flow'] ?? [];
        $interface = $obj['interface'] ?? null;

        // Auto-detect or create device based on MAC address
        $deviceId = null;
        if (!empty($flow['local_mac'])) {
            $deviceId = $this->deviceModel->autoDetectFromMac($flow['local_mac']);
        }

        // Prepare flow data
        $flowData = [
            'device_id' => $deviceId,
            'recv_ts' => $recvTs,
            'recv_unix_ms' => $recvMs,
            'first_seen_at' => $flow['first_seen_at'] ?? null,
            'first_update_at' => $flow['first_update_at'] ?? null,
            'last_seen_at' => $flow['last_seen_at'] ?? null,
            'ct_id' => $flow['ct_id'] ?? null,
            'ct_mark' => $flow['ct_mark'] ?? null,
            'established' => isset($obj['established']) ? (int)$obj['established'] : null,
            'ip_version' => $flow['ip_version'] ?? null,
            'ip_protocol' => $flow['ip_protocol'] ?? null,
            'ip_nat' => isset($flow['ip_nat']) ? (int)$flow['ip_nat'] : null,
            'local_ip' => $flow['local_ip'] ?? null,
            'local_port' => $flow['local_port'] ?? null,
            'local_mac' => $flow['local_mac'] ?? null,
            'local_origin' => isset($flow['local_origin']) ? (int)$flow['local_origin'] : null,
            'other_ip' => $flow['other_ip'] ?? null,
            'other_port' => $flow['other_port'] ?? null,
            'other_mac' => $flow['other_mac'] ?? null,
            'other_type' => $flow['other_type'] ?? null,
            'detected_protocol' => $flow['detected_protocol'] ?? null,
            'detected_protocol_name' => $flow['detected_protocol_name'] ?? null,
            'detected_application' => $flow['detected_application'] ?? null,
            'detected_application_name' => $flow['detected_application_name'] ?? null,
            'detection_guessed' => isset($flow['detection_guessed']) ? (int)$flow['detection_guessed'] : null,
            'dhc_hit' => isset($flow['dhc_hit']) ? (int)$flow['dhc_hit'] : null,
            'vlan_id' => $flow['vlan_id'] ?? null,
            'interface_name' => $interface,
            'host_server_name' => $flow['host_server_name'] ?? null,
            'digest' => $flow['digest'] ?? null,
            'bytes_len' => strlen($jsonData),
            'json_valid' => $jsonValid,
            'json_error' => $jsonError,
            'raw_json' => $jsonData
        ];

        try {
            $flowId = $this->flowModel->create($flowData);

            // Process SSL information if available
            if (!empty($flow['ssl'])) {
                $this->processSSLInfo($flowId, $flow['ssl']);
            }

            // Process HTTP information if available
            if (!empty($flow['http'])) {
                $this->processHTTPInfo($flowId, $flow['http']);
            }

        } catch (\Exception $e) {
            error_log("Failed to insert flow data: " . $e->getMessage());
        }
    }

    private function processSSLInfo(int $flowId, array $sslData): void
    {
        $data = [
            'flow_id' => $flowId,
            'cipher_suite' => $sslData['cipher_suite'] ?? null,
            'client_ja3' => $sslData['client_ja3'] ?? null,
            'client_sni' => $sslData['client_sni'] ?? null,
            'server_cn' => $sslData['server_cn'] ?? null,
            'server_ja3' => $sslData['server_ja3'] ?? null,
            'version' => $sslData['version'] ?? null
        ];

        try {
            $this->db->insert('ssl_info', $data);
        } catch (\Exception $e) {
            error_log("Failed to insert SSL info: " . $e->getMessage());
        }
    }

    private function processHTTPInfo(int $flowId, array $httpData): void
    {
        $data = [
            'flow_id' => $flowId,
            'url' => $httpData['url'] ?? null,
            'method' => $httpData['method'] ?? null,
            'user_agent' => $httpData['user_agent'] ?? null,
            'referer' => $httpData['referer'] ?? null,
            'content_type' => $httpData['content_type'] ?? null,
            'status_code' => $httpData['status_code'] ?? null
        ];

        try {
            $this->db->insert('http_info', $data);
        } catch (\Exception $e) {
            error_log("Failed to insert HTTP info: " . $e->getMessage());
        }
    }

    private function processStatsData(array $obj, string $jsonData, string $recvTs, int $recvMs): void
    {
        // Store raw stats data for future processing
        $data = [
            'ts_ms' => $recvMs,
            'scope' => $obj['scope'] ?? '',
            'json' => $jsonData
        ];

        try {
            $this->db->insert('netify_stats_raw', $data);
        } catch (\Exception $e) {
            error_log("Failed to insert stats data: " . $e->getMessage());
        }
    }

    private function logProcessing(string $type, ?array $obj, int $dataLength): void
    {
        if ($type === 'flow' && $obj) {
            $flow = $obj['flow'] ?? [];
            $app = $flow['detected_application_name'] ?? 'Unknown';
            $proto = $flow['detected_protocol_name'] ?? 'Unknown';
            $host = $flow['host_server_name'] ?? '';
            
            echo sprintf(
                "%s FLOW %s %s:%s -> %s:%s app=%s proto=%s host=%s len=%d\n",
                date('c'),
                $flow['ip_version'] ?? '-',
                $flow['local_ip'] ?? '-',
                $flow['local_port'] ?? '-',
                $flow['other_ip'] ?? '-',
                $flow['other_port'] ?? '-',
                $app, $proto, $host, $dataLength
            );
        } else {
            echo sprintf("%s %s len=%d\n", date('c'), strtoupper($type), $dataLength);
        }
    }

    private function getEndpointParts($conn, bool $peer = true): array
    {
        $name = @stream_socket_get_name($conn, $peer);
        if ($name === false) return [null, null];
        
        $pos = strrpos($name, ':');
        if ($pos === false) return [$name, null];
        
        $ip = substr($name, 0, $pos);
        $port = (int) substr($name, $pos + 1);
        
        // IPv6 format [::1]:9000
        if (strlen($ip) > 0 && $ip[0] === '[') {
            $ip = trim($ip, '[]');
        }
        
        return [$ip, $port];
    }
}

