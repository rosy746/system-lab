<?php
/**
 * MikroTik API PHP Class - Final Version
 * Control NAT rules untuk Lab 7 dan Lab 8
 * 
 * Based on RouterOS API v7.16.1
 * Fixed for Port Forwarding & VLAN Interfaces
 */

class MikroTikAPI {
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket;
    private $connected = false;
    private $debug = false;
    
    /**
     * Constructor
     */
    public function __construct($host, $username, $password, $port = 8798, $debug = false) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->debug = $debug;
    }
    
    /**
     * Connect to MikroTik
     */
    public function connect() {
        if ($this->connected) {
            return true;
        }
        
        $this->log("Attempting to connect to {$this->host}:{$this->port}");
        
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);
        
        if (!$this->socket) {
            $this->log("Connection failed: $errstr ($errno)");
            error_log("MikroTik Connection Error: $errstr ($errno)");
            return false;
        }
        
        stream_set_timeout($this->socket, 10);
        
        $this->connected = true;
        $this->log("Socket connected, attempting login...");
        
        if (!$this->login()) {
            $this->log("Login failed");
            $this->disconnect();
            return false;
        }
        
        $this->log("Connected and logged in successfully");
        return true;
    }
    
    /**
     * Login to MikroTik
     */
    private function login() {
        $this->write('/login', false);
        $this->write('=name=' . $this->username, false);
        $this->write('=password=' . $this->password, false);
        $this->write('', true);
        
        $response = $this->read();
        
        $this->log("Login response: " . print_r($response, true));
        
        foreach ($response as $line) {
            if ($line == '!done') {
                $this->log("Login successful");
                return true;
            }
            if (strpos($line, '!trap') === 0) {
                $this->log("Login failed: " . print_r($response, true));
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Disconnect from MikroTik
     */
    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
            $this->log("Disconnected");
        }
    }
    
    /**
     * Write data to socket
     */
    private function write($data, $endCommand = true) {
        fwrite($this->socket, $this->encodeLength(strlen($data)) . $data);
        if ($endCommand) {
            fwrite($this->socket, chr(0));
        }
    }
    
    /**
     * Read data from socket
     */
    private function read() {
        $response = [];
        while (true) {
            $len = $this->readLength();
            if ($len === 0) {
                break;
            }
            $response[] = fread($this->socket, $len);
        }
        return $response;
    }
    
    /**
     * Encode length for API protocol
     */
    private function encodeLength($length) {
        if ($length < 0x80) {
            return chr($length);
        } else if ($length < 0x4000) {
            return chr(($length >> 8) | 0x80) . chr($length & 0xFF);
        } else if ($length < 0x200000) {
            return chr(($length >> 16) | 0xC0) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } else if ($length < 0x10000000) {
            return chr(($length >> 24) | 0xE0) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }
        return chr(0xF0) . pack('N', $length);
    }
    
    /**
     * Read length from socket
     */
    private function readLength() {
        $byte = ord(fread($this->socket, 1));
        
        if ($byte & 0x80) {
            if (($byte & 0xC0) == 0x80) {
                return (($byte & ~0xC0) << 8) + ord(fread($this->socket, 1));
            } else if (($byte & 0xE0) == 0xC0) {
                return (($byte & ~0xE0) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
            } else if (($byte & 0xF0) == 0xE0) {
                return (($byte & ~0xF0) << 24) + (ord(fread($this->socket, 1)) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
            } else if (($byte & 0xF8) == 0xF0) {
                return unpack('N', fread($this->socket, 4))[1];
            }
        }
        return $byte;
    }
    
    /**
     * Execute command
     */
    public function command($command, $params = []) {
        if (!$this->connected) {
            if (!$this->connect()) {
                return ['error' => 'Not connected'];
            }
        }
        
        $this->log("Executing: $command with params: " . json_encode($params));
        
        $this->write($command, false);
        
        foreach ($params as $key => $value) {
            $this->write('=' . $key . '=' . $value, false);
        }
        
        $this->write('', true);
        
        $response = $this->read();
        $result = $this->parseResponse($response);
        
        $this->log("Result: " . count($result) . " items");
        
        return $result;
    }
    
    /**
     * Parse API response
     */
    private function parseResponse($response) {
        $result = [];
        $currentItem = [];
        
        foreach ($response as $line) {
            if ($line == '!done') {
                if (!empty($currentItem)) {
                    $result[] = $currentItem;
                }
                break;
            } else if ($line == '!re') {
                if (!empty($currentItem)) {
                    $result[] = $currentItem;
                }
                $currentItem = [];
            } else if (strpos($line, '=') === 0) {
                $parts = explode('=', substr($line, 1), 2);
                if (count($parts) == 2) {
                    $currentItem[$parts[0]] = $parts[1];
                }
            } else if (strpos($line, '!trap') === 0) {
                return ['error' => 'Command failed', 'details' => $response];
            }
        }
        
        return $result;
    }
    
    /**
     * Get all NAT rules
     */
    public function getNATRules() {
        return $this->command('/ip/firewall/nat/print');
    }
    
    /**
     * Find NAT rule ID by comment
     */
    public function findNATByComment($comment) {
        $rules = $this->getNATRules();
        
        foreach ($rules as $rule) {
            if (isset($rule['comment']) && $rule['comment'] === $comment) {
                return $rule['.id'];
            }
        }
        
        return null;
    }
    
    /**
     * Enable NAT rule
     */
    public function enableNAT($id) {
        if (!preg_match('/^\*[0-9A-F]+$/', $id)) {
            $ruleId = $this->findNATByComment($id);
            if (!$ruleId) {
                return ['error' => 'NAT rule not found', 'comment' => $id];
            }
            $id = $ruleId;
        }
        
        $response = $this->command('/ip/firewall/nat/enable', [
            '.id' => $id
        ]);
        
        $this->log("Enabled NAT rule: $id");
        
        return [
            'success' => true,
            'action' => 'enable',
            'rule_id' => $id,
            'response' => $response
        ];
    }
    
    /**
     * Disable NAT rule
     */
    public function disableNAT($id) {
        if (!preg_match('/^\*[0-9A-F]+$/', $id)) {
            $ruleId = $this->findNATByComment($id);
            if (!$ruleId) {
                return ['error' => 'NAT rule not found', 'comment' => $id];
            }
            $id = $ruleId;
        }
        
        $response = $this->command('/ip/firewall/nat/disable', [
            '.id' => $id
        ]);
        
        $this->log("Disabled NAT rule: $id");
        
        return [
            'success' => true,
            'action' => 'disable',
            'rule_id' => $id,
            'response' => $response
        ];
    }
    
    /**
     * Toggle NAT rule
     */
    public function toggleNAT($id, $enable) {
        return $enable ? $this->enableNAT($id) : $this->disableNAT($id);
    }
    
    /**
     * Get NAT status by comment
     */
    public function getNATStatus($comment) {
        $rules = $this->getNATRules();
        
        foreach ($rules as $rule) {
            if (isset($rule['comment']) && $rule['comment'] === $comment) {
                return [
                    'success' => true,
                    'found' => true,
                    'id' => $rule['.id'],
                    'comment' => $rule['comment'],
                    'disabled' => isset($rule['disabled']) && $rule['disabled'] === 'true',
                    'enabled' => !isset($rule['disabled']) || $rule['disabled'] === 'false',
                    'src_address' => $rule['src-address'] ?? null,
                    'chain' => $rule['chain'] ?? null,
                    'action' => $rule['action'] ?? null
                ];
            }
        }
        
        return [
            'success' => false,
            'found' => false,
            'error' => 'NAT rule not found',
            'comment' => $comment
        ];
    }
    
    /**
     * Get DHCP leases for specific server - FIXED
     */
    public function getDHCPLeases($server = null) {
        if ($server) {
            $allLeases = $this->command('/ip/dhcp-server/lease/print');
            
            $filtered = [];
            foreach ($allLeases as $lease) {
                if (isset($lease['server']) && $lease['server'] === $server) {
                    $filtered[] = $lease;
                }
            }
            
            $this->log("Found " . count($filtered) . " leases for server: $server");
            return $filtered;
        }
        
        return $this->command('/ip/dhcp-server/lease/print');
    }
    
    /**
     * Get active DHCP leases count - FIXED
     */
    public function getActiveDHCPCount($server = null) {
        $leases = $this->getDHCPLeases($server);
        $active = 0;
        
        foreach ($leases as $lease) {
            if (isset($lease['status']) && $lease['status'] === 'bound') {
                $active++;
            } elseif (isset($lease['address']) && 
                      (!isset($lease['disabled']) || $lease['disabled'] !== 'true')) {
                $active++;
            }
        }
        
        $this->log("Active DHCP count for $server: $active");
        return $active;
    }
    
    /**
     * Get interface statistics - FIXED untuk VLAN
     */
    public function getInterfaceStats($interface) {
        // Try VLAN interface first
        $result = $this->command('/interface/vlan/print', [
            '?name' => $interface
        ]);
        
        if (!empty($result)) {
            $this->log("Found VLAN interface: $interface");
            return $result[0];
        }
        
        // Try regular interface
        $result = $this->command('/interface/print', [
            '?name' => $interface
        ]);
        
        if (!empty($result)) {
            $this->log("Found interface: $interface");
            return $result[0];
        }
        
        // Manual search as fallback
        $allInterfaces = $this->command('/interface/print');
        
        foreach ($allInterfaces as $iface) {
            if (isset($iface['name']) && $iface['name'] === $interface) {
                $this->log("Found interface by manual search: $interface");
                return $iface;
            }
        }
        
        $this->log("Interface not found: $interface");
        return ['error' => 'Interface not found', 'searched' => $interface];
    }
    
    /**
     * Get interface traffic - FIXED untuk VLAN
     */
    public function getInterfaceTraffic($interface) {
        // Try VLAN first
        $stats = $this->command('/interface/vlan/print', [
            '?name' => $interface
        ]);
        
        if (!empty($stats)) {
            $iface = $stats[0];
            
            return [
                'interface' => $interface,
                'running' => isset($iface['running']) && $iface['running'] === 'true',
                'rx_bytes' => isset($iface['rx-byte']) ? intval($iface['rx-byte']) : 0,
                'tx_bytes' => isset($iface['tx-byte']) ? intval($iface['tx-byte']) : 0,
                'rx_packets' => isset($iface['rx-packet']) ? intval($iface['rx-packet']) : 0,
                'tx_packets' => isset($iface['tx-packet']) ? intval($iface['tx-packet']) : 0,
                'rx_errors' => isset($iface['rx-error']) ? intval($iface['rx-error']) : 0,
                'tx_errors' => isset($iface['tx-error']) ? intval($iface['tx-error']) : 0
            ];
        }
        
        // Fallback to regular interface
        $stats = $this->command('/interface/print', [
            '?name' => $interface
        ]);
        
        if (!empty($stats)) {
            $iface = $stats[0];
            
            return [
                'interface' => $interface,
                'running' => isset($iface['running']) && $iface['running'] === 'true',
                'rx_bytes' => isset($iface['rx-byte']) ? intval($iface['rx-byte']) : 0,
                'tx_bytes' => isset($iface['tx-byte']) ? intval($iface['tx-byte']) : 0,
                'rx_packets' => isset($iface['rx-packet']) ? intval($iface['rx-packet']) : 0,
                'tx_packets' => isset($iface['tx-packet']) ? intval($iface['tx-packet']) : 0,
                'rx_errors' => isset($iface['rx-error']) ? intval($iface['rx-error']) : 0,
                'tx_errors' => isset($iface['tx-error']) ? intval($iface['tx-error']) : 0
            ];
        }
        
        return [
            'error' => 'Interface not found',
            'interface' => $interface
        ];
    }
    
    /**
     * Log message
     */
    private function log($message) {
        if ($this->debug) {
            error_log("[MikroTik API] " . $message);
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->disconnect();
    }
}

// ============================================================
// LAB NAT CONTROLLER CLASS
// ============================================================

class LabNATController {
    private $api;
    private $labMapping = [
        'lab7' => [
            'name' => 'Lab Komputer 7',
            'nat_comment' => 'lab 7',
            'interface' => 'lab 7',
            'dhcp_server' => 'dhcp2',
            'network' => '192.168.70.0/24',
            'vlan_id' => 77,
            'resource_id' => 1
        ],
        'lab8' => [
            'name' => 'Lab Komputer 8',
            'nat_comment' => 'lab 8',
            'interface' => 'lab 8',
            'dhcp_server' => 'dhcp3',
            'network' => '192.168.80.0/24',
            'vlan_id' => 88,
            'resource_id' => 2
        ]
    ];
    
    public function __construct(MikroTikAPI $api) {
        $this->api = $api;
    }
    
    public function getLabConfig($labKey) {
        return $this->labMapping[$labKey] ?? null;
    }
    
    public function getAllLabsConfig() {
        return $this->labMapping;
    }
    
    public function enableLab($labKey) {
        $config = $this->getLabConfig($labKey);
        
        if (!$config) {
            return [
                'success' => false,
                'error' => 'Lab not found',
                'lab' => $labKey
            ];
        }
        
        $result = $this->api->enableNAT($config['nat_comment']);
        
        return [
            'success' => true,
            'lab_key' => $labKey,
            'lab_name' => $config['name'],
            'action' => 'enabled',
            'nat_comment' => $config['nat_comment'],
            'network' => $config['network'],
            'result' => $result
        ];
    }
    
    public function disableLab($labKey) {
        $config = $this->getLabConfig($labKey);
        
        if (!$config) {
            return [
                'success' => false,
                'error' => 'Lab not found',
                'lab' => $labKey
            ];
        }
        
        $result = $this->api->disableNAT($config['nat_comment']);
        
        return [
            'success' => true,
            'lab_key' => $labKey,
            'lab_name' => $config['name'],
            'action' => 'disabled',
            'nat_comment' => $config['nat_comment'],
            'network' => $config['network'],
            'result' => $result
        ];
    }
    
    public function toggleLab($labKey, $enable) {
        return $enable ? $this->enableLab($labKey) : $this->disableLab($labKey);
    }
    
    public function getLabStatus($labKey) {
        $config = $this->getLabConfig($labKey);
        
        if (!$config) {
            return [
                'success' => false,
                'error' => 'Lab not found',
                'lab' => $labKey
            ];
        }
        
        $natStatus = $this->api->getNATStatus($config['nat_comment']);
        $activeUsers = $this->api->getActiveDHCPCount($config['dhcp_server']);
        $traffic = $this->api->getInterfaceTraffic($config['interface']);
        
        return [
            'success' => true,
            'lab_key' => $labKey,
            'lab_name' => $config['name'],
            'resource_id' => $config['resource_id'],
            'network' => $config['network'],
            'vlan_id' => $config['vlan_id'],
            'nat_enabled' => $natStatus['enabled'] ?? false,
            'nat_disabled' => $natStatus['disabled'] ?? true,
            'active_users' => $activeUsers,
            'interface_running' => $traffic['running'] ?? false,
            'traffic' => $traffic
        ];
    }
    
    public function getAllLabsStatus() {
        $status = [];
        
        foreach (array_keys($this->labMapping) as $labKey) {
            $status[$labKey] = $this->getLabStatus($labKey);
        }
        
        return $status;
    }
}

// ============================================================
// CONFIGURATION
// ============================================================

define('MIKROTIK_HOST', '103.182.235.42');
define('MIKROTIK_USER', 'admin');
define('MIKROTIK_PASS', 'm41k3l');
define('MIKROTIK_PORT', 2112); // Port forwarding ke MikroTik port 8798

// ============================================================
// API ENDPOINT HANDLER
// ============================================================

if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    try {
        $api = new MikroTikAPI(
            MIKROTIK_HOST,
            MIKROTIK_USER,
            MIKROTIK_PASS,
            MIKROTIK_PORT,
            false // Set true untuk debug mode
        );
        
        $labController = new LabNATController($api);
        
        $action = $_GET['api'] ?? '';
        
        switch ($action) {
            case 'status':
                $lab = $_GET['lab'] ?? null;
                
                if ($lab) {
                    $result = $labController->getLabStatus($lab);
                } else {
                    $result = $labController->getAllLabsStatus();
                }
                
                echo json_encode($result, JSON_PRETTY_PRINT);
                break;
                
            case 'enable':
                $lab = $_GET['lab'] ?? null;
                
                if (!$lab) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Lab parameter required'
                    ]);
                    break;
                }
                
                $result = $labController->enableLab($lab);
                echo json_encode($result, JSON_PRETTY_PRINT);
                break;
                
            case 'disable':
                $lab = $_GET['lab'] ?? null;
                
                if (!$lab) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Lab parameter required'
                    ]);
                    break;
                }
                
                $result = $labController->disableLab($lab);
                echo json_encode($result, JSON_PRETTY_PRINT);
                break;
                
            case 'toggle':
                $lab = $_GET['lab'] ?? null;
                $enable = isset($_GET['enable']) ? filter_var($_GET['enable'], FILTER_VALIDATE_BOOLEAN) : null;
                
                if (!$lab || $enable === null) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Lab and enable parameters required'
                    ]);
                    break;
                }
                
                $result = $labController->toggleLab($lab, $enable);
                echo json_encode($result, JSON_PRETTY_PRINT);
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'error' => 'Unknown action',
                    'available_actions' => [
                        'status' => 'Get lab status (optional: ?lab=lab7)',
                        'enable' => 'Enable lab internet (?lab=lab7)',
                        'disable' => 'Disable lab internet (?lab=lab8)',
                        'toggle' => 'Toggle lab internet (?lab=lab7&enable=true)'
                    ]
                ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikroTik Lab NAT Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .control-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 30px;
        }
        .lab-item {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .lab-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }
        .lab-item.enabled {
            border-color: #06d6a0;
            background: #d1fae5;
        }
        .lab-item.disabled {
            border-color: #ef476f;
            background: #fee2e2;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 50px;
        }
        .btn-control {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
        }
        .stats-box {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .traffic-info {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="control-card">
            <h1 class="mb-4">
                <i class="fas fa-network-wired"></i>
                MikroTik Lab NAT Control
            </h1>
            <p class="text-muted">Control internet access untuk Lab Komputer 7 & 8</p>
            
            <hr>
            
            <div id="labsContainer">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading lab status...</p>
                </div>
            </div>
        </div>
        
        <div class="control-card">
            <h4><i class="fas fa-terminal"></i> API Endpoints</h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Description</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>?api=status</code></td>
                            <td>Get all labs status</td>
                            <td><a href="?api=status" target="_blank">Try it</a></td>
                        </tr>
                        <tr>
                            <td><code>?api=status&lab=lab7</code></td>
                            <td>Get specific lab status</td>
                            <td><a href="?api=status&lab=lab7" target="_blank">Try it</a></td>
                        </tr>
                        <tr>
                            <td><code>?api=enable&lab=lab7</code></td>
                            <td>Enable Lab 7 internet</td>
                            <td><a href="?api=enable&lab=lab7" target="_blank">Try it</a></td>
                        </tr>
                        <tr>
                            <td><code>?api=disable&lab=lab8</code></td>
                            <td>Disable Lab 8 internet</td>
                            <td><a href="?api=disable&lab=lab8" target="_blank">Try it</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }
        
        function loadLabsStatus() {
            $.get('?api=status', function(data) {
                let html = '';
                
                for (let [key, lab] of Object.entries(data)) {
                    const statusClass = lab.nat_enabled ? 'enabled' : 'disabled';
                    const statusText = lab.nat_enabled ? 'Internet Aktif' : 'Internet Dimatikan';
                    const statusIcon = lab.nat_enabled ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
                    const btnClass = lab.nat_enabled ? 'btn-danger' : 'btn-success';
                    const btnText = lab.nat_enabled ? 'Matikan Internet' : 'Hidupkan Internet';
                    const btnAction = lab.nat_enabled ? 'disable' : 'enable';
                    const ifaceRunning = lab.interface_running ? '✓ Running' : '✗ Down';
                    const ifaceClass = lab.interface_running ? 'text-success' : 'text-danger';
                    
                    html += `
                        <div class="lab-item ${statusClass}">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h4 class="mb-2">
                                        <i class="fas fa-desktop"></i> ${lab.lab_name}
                                    </h4>
                                    <span class="badge status-badge ${lab.nat_enabled ? 'bg-success' : 'bg-danger'}">
                                        <i class="fas ${statusIcon}"></i> ${statusText}
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Network: ${lab.network}</small>
                                    <small class="text-muted d-block">VLAN: ${lab.vlan_id}</small>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-users"></i> Active Users: 
                                        <strong>${lab.active_users}</strong>
                                    </small>
                                    <small class="${ifaceClass} d-block">
                                        <i class="fas fa-ethernet"></i> Interface: ${ifaceRunning}
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    ${lab.traffic && !lab.traffic.error ? `
                                        <div class="stats-box mb-2">
                                            <div class="traffic-info">
                                                <i class="fas fa-download"></i> RX: ${formatBytes(lab.traffic.rx_bytes)}<br>
                                                <i class="fas fa-upload"></i> TX: ${formatBytes(lab.traffic.tx_bytes)}
                                            </div>
                                        </div>
                                    ` : ''}
                                    <button class="btn ${btnClass} btn-control" onclick="toggleLab('${key}', ${!lab.nat_enabled})">
                                        <i class="fas fa-power-off"></i> ${btnText}
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                $('#labsContainer').html(html);
            }).fail(function(xhr, status, error) {
                $('#labsContainer').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Gagal memuat status lab. Error: ${error}
                        <br><small>Pastikan konfigurasi MikroTik sudah benar dan API dapat diakses.</small>
                    </div>
                `);
            });
        }
        
        function toggleLab(labKey, enable) {
            const action = enable ? 'enable' : 'disable';
            const actionText = enable ? 'menghidupkan' : 'mematikan';
            
            if (!confirm(`Apakah Anda yakin ingin ${actionText} internet untuk lab ini?`)) {
                return;
            }
            
            // Show loading
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            $.get(`?api=${action}&lab=${labKey}`, function(data) {
                if (data.success) {
                    loadLabsStatus();
                    
                    // Show success notification
                    const alertDiv = $(`
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> Berhasil ${actionText} internet!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    $('.container').prepend(alertDiv);
                    setTimeout(() => alertDiv.fadeOut(), 3000);
                } else {
                    alert('Gagal: ' + (data.error || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            }).fail(function() {
                alert('Gagal menghubungi server MikroTik');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            });
        }
        
        // Load on page load
        $(document).ready(function() {
            loadLabsStatus();
            
            // Auto refresh every 10 seconds
            setInterval(loadLabsStatus, 10000);
        });
    </script>
</body>
</html>