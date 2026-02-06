<?php
/**
 * MikroTik Configuration Checker
 * Script untuk mendeteksi nama interface dan DHCP server yang benar
 */

// Include your MikroTik API class
require_once 'mikrotik_api.php'; // Sesuaikan dengan nama file Anda

// Configuration
define('MIKROTIK_HOST', '103.182.235.42');
define('MIKROTIK_USER', 'admin');
define('MIKROTIK_PASS', 'm41k3l');
define('MIKROTIK_PORT', 2112);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikroTik Configuration Checker</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        h1, h2 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h3 {
            color: #dcdcaa;
            margin-top: 30px;
        }
        .success {
            color: #4ec9b0;
            font-weight: bold;
        }
        .error {
            color: #f48771;
            font-weight: bold;
        }
        .warning {
            color: #dcdcaa;
            font-weight: bold;
        }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #4ec9b0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #3e3e42;
        }
        th {
            background: #1e1e1e;
            color: #4ec9b0;
            font-weight: bold;
        }
        tr:hover {
            background: #2d2d30;
        }
        .highlight {
            background: #264f78;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .recommendation {
            background: #1e3a20;
            border-left: 4px solid #4ec9b0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .copy-btn {
            background: #0e639c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .copy-btn:hover {
            background: #1177bb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 MikroTik Configuration Checker</h1>
        <p>Mendeteksi konfigurasi aktual dari MikroTik untuk Lab 7 dan Lab 8</p>

        <?php
        try {
            // Create API instance
            $api = new MikroTikAPI(
                MIKROTIK_HOST,
                MIKROTIK_USER,
                MIKROTIK_PASS,
                MIKROTIK_PORT,
                true
            );

            if (!$api->connect()) {
                throw new Exception("Gagal koneksi ke MikroTik");
            }

            echo '<p class="success">✓ Berhasil terhubung ke MikroTik ' . MIKROTIK_HOST . '</p>';

            // ==========================================
            // 1. CHECK ALL INTERFACES
            // ==========================================
            echo '<h2>1. Daftar Semua Interface</h2>';
            $interfaces = $api->command('/interface/print');
            
            echo '<table>';
            echo '<tr><th>Name</th><th>Type</th><th>Running</th><th>Disabled</th></tr>';
            
            $lab7_interface = null;
            $lab8_interface = null;
            
            foreach ($interfaces as $iface) {
                $name = $iface['name'] ?? 'N/A';
                $type = $iface['type'] ?? 'N/A';
                $running = isset($iface['running']) && $iface['running'] === 'true' ? '✓' : '✗';
                $disabled = isset($iface['disabled']) && $iface['disabled'] === 'true' ? 'Yes' : 'No';
                
                $highlight = '';
                if (stripos($name, 'lab') !== false || stripos($name, '77') !== false || stripos($name, '70') !== false) {
                    $highlight = 'class="highlight"';
                    $lab7_interface = $name;
                }
                if (stripos($name, 'lab') !== false || stripos($name, '88') !== false || stripos($name, '80') !== false) {
                    if (!$lab7_interface || stripos($name, '8') !== false) {
                        $highlight = 'class="highlight"';
                        $lab8_interface = $name;
                    }
                }
                
                echo "<tr $highlight>";
                echo "<td><strong>$name</strong></td>";
                echo "<td>$type</td>";
                echo "<td>$running</td>";
                echo "<td>$disabled</td>";
                echo "</tr>";
            }
            echo '</table>';

            // ==========================================
            // 2. CHECK VLAN INTERFACES
            // ==========================================
            echo '<h2>2. VLAN Interfaces</h2>';
            $vlans = $api->command('/interface/vlan/print');
            
            if (!empty($vlans)) {
                echo '<table>';
                echo '<tr><th>Name</th><th>VLAN ID</th><th>Interface</th><th>Disabled</th></tr>';
                
                foreach ($vlans as $vlan) {
                    $name = $vlan['name'] ?? 'N/A';
                    $vlanId = $vlan['vlan-id'] ?? 'N/A';
                    $iface = $vlan['interface'] ?? 'N/A';
                    $disabled = isset($vlan['disabled']) && $vlan['disabled'] === 'true' ? 'Yes' : 'No';
                    
                    $highlight = '';
                    if ($vlanId == '77' || stripos($name, 'lab 7') !== false || stripos($name, 'lab7') !== false) {
                        $highlight = 'class="highlight"';
                        $lab7_interface = $name;
                    }
                    if ($vlanId == '88' || stripos($name, 'lab 8') !== false || stripos($name, 'lab8') !== false) {
                        $highlight = 'class="highlight"';
                        $lab8_interface = $name;
                    }
                    
                    echo "<tr $highlight>";
                    echo "<td><strong>$name</strong></td>";
                    echo "<td>$vlanId</td>";
                    echo "<td>$iface</td>";
                    echo "<td>$disabled</td>";
                    echo "</tr>";
                }
                echo '</table>';
            } else {
                echo '<p class="warning">⚠ Tidak ada VLAN interface</p>';
            }

            // ==========================================
            // 3. CHECK DHCP SERVERS
            // ==========================================
            echo '<h2>3. DHCP Servers</h2>';
            $dhcpServers = $api->command('/ip/dhcp-server/print');
            
            echo '<table>';
            echo '<tr><th>Name</th><th>Interface</th><th>Address Pool</th><th>Disabled</th></tr>';
            
            $lab7_dhcp = null;
            $lab8_dhcp = null;
            
            foreach ($dhcpServers as $dhcp) {
                $name = $dhcp['name'] ?? 'N/A';
                $iface = $dhcp['interface'] ?? 'N/A';
                $pool = $dhcp['address-pool'] ?? 'N/A';
                $disabled = isset($dhcp['disabled']) && $dhcp['disabled'] === 'true' ? 'Yes' : 'No';
                
                $highlight = '';
                if (stripos($iface, 'lab 7') !== false || stripos($iface, 'lab7') !== false || stripos($name, '7') !== false) {
                    $highlight = 'class="highlight"';
                    $lab7_dhcp = $name;
                }
                if (stripos($iface, 'lab 8') !== false || stripos($iface, 'lab8') !== false || stripos($name, '8') !== false) {
                    $highlight = 'class="highlight"';
                    $lab8_dhcp = $name;
                }
                
                echo "<tr $highlight>";
                echo "<td><strong>$name</strong></td>";
                echo "<td>$iface</td>";
                echo "<td>$pool</td>";
                echo "<td>$disabled</td>";
                echo "</tr>";
            }
            echo '</table>';

            // ==========================================
            // 4. CHECK NAT RULES
            // ==========================================
            echo '<h2>4. NAT Rules (Firewall)</h2>';
            $natRules = $api->command('/ip/firewall/nat/print');
            
            echo '<table>';
            echo '<tr><th>ID</th><th>Chain</th><th>Src Address</th><th>Action</th><th>Comment</th><th>Disabled</th></tr>';
            
            foreach ($natRules as $nat) {
                $id = $nat['.id'] ?? 'N/A';
                $chain = $nat['chain'] ?? 'N/A';
                $srcAddr = $nat['src-address'] ?? 'N/A';
                $action = $nat['action'] ?? 'N/A';
                $comment = $nat['comment'] ?? '';
                $disabled = isset($nat['disabled']) && $nat['disabled'] === 'true' ? 'Yes' : 'No';
                
                $highlight = '';
                if (stripos($comment, 'lab 7') !== false || stripos($srcAddr, '192.168.70') !== false) {
                    $highlight = 'class="highlight"';
                }
                if (stripos($comment, 'lab 8') !== false || stripos($srcAddr, '192.168.80') !== false) {
                    $highlight = 'class="highlight"';
                }
                
                echo "<tr $highlight>";
                echo "<td>$id</td>";
                echo "<td>$chain</td>";
                echo "<td>$srcAddr</td>";
                echo "<td>$action</td>";
                echo "<td><strong>$comment</strong></td>";
                echo "<td>$disabled</td>";
                echo "</tr>";
            }
            echo '</table>';

            // ==========================================
            // 5. RECOMMENDATIONS
            // ==========================================
            echo '<h2>5. Rekomendasi Konfigurasi PHP</h2>';
            
            echo '<div class="recommendation">';
            echo '<h3>Deteksi Otomatis:</h3>';
            echo '<ul>';
            echo '<li><strong>Lab 7 Interface:</strong> <span class="success">' . ($lab7_interface ?? 'Tidak terdeteksi') . '</span></li>';
            echo '<li><strong>Lab 7 DHCP Server:</strong> <span class="success">' . ($lab7_dhcp ?? 'Tidak terdeteksi') . '</span></li>';
            echo '<li><strong>Lab 8 Interface:</strong> <span class="success">' . ($lab8_interface ?? 'Tidak terdeteksi') . '</span></li>';
            echo '<li><strong>Lab 8 DHCP Server:</strong> <span class="success">' . ($lab8_dhcp ?? 'Tidak terdeteksi') . '</span></li>';
            echo '</ul>';
            echo '</div>';

            // Generate corrected config
            $config = "private \$labMapping = [\n";
            $config .= "    'lab7' => [\n";
            $config .= "        'name' => 'Lab Komputer 7',\n";
            $config .= "        'nat_comment' => 'lab 7',\n";
            $config .= "        'interface' => '" . ($lab7_interface ?? 'lab 7') . "',\n";
            $config .= "        'dhcp_server' => '" . ($lab7_dhcp ?? 'dhcp2') . "',\n";
            $config .= "        'network' => '192.168.70.0/24',\n";
            $config .= "        'vlan_id' => 77,\n";
            $config .= "        'resource_id' => 1\n";
            $config .= "    ],\n";
            $config .= "    'lab8' => [\n";
            $config .= "        'name' => 'Lab Komputer 8',\n";
            $config .= "        'nat_comment' => 'lab 8',\n";
            $config .= "        'interface' => '" . ($lab8_interface ?? 'lab 8') . "',\n";
            $config .= "        'dhcp_server' => '" . ($lab8_dhcp ?? 'dhcp3') . "',\n";
            $config .= "        'network' => '192.168.80.0/24',\n";
            $config .= "        'vlan_id' => 88,\n";
            $config .= "        'resource_id' => 2\n";
            $config .= "    ]\n";
            $config .= "];";

            echo '<h3>Konfigurasi yang Benar untuk LabNATController:</h3>';
            echo '<pre id="configCode">' . htmlspecialchars($config) . '</pre>';
            echo '<button class="copy-btn" onclick="copyConfig()">📋 Copy Configuration</button>';

        } catch (Exception $e) {
            echo '<p class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>

    </div>

    <script>
        function copyConfig() {
            const code = document.getElementById('configCode').textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert('✓ Configuration copied to clipboard!');
            });
        }
    </script>
</body>
</html>