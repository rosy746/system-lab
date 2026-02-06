<?php
/**
 * INVENTORY TROUBLESHOOTING SCRIPT
 * Gunakan script ini untuk mengidentifikasi masalah pada inventory_list.php
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/app/config.php';

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <title>Inventory Troubleshooting</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
        h2 { border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    </style>
</head>
<body>
<h1>🔍 Inventory Database Troubleshooting</h1>";

// Test 1: Database Connection
echo "<div class='section'>";
echo "<h2>Test 1: Database Connection</h2>";
try {
    $db = getDB();
    echo "<p class='success'>✓ Database connection successful</p>";
    echo "<pre>PDO Driver: " . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . "</pre>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection FAILED</p>";
    echo "<pre>Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// Test 2: Check if tables exist
echo "<div class='section'>";
echo "<h2>Test 2: Check Required Tables</h2>";
$required_tables = ['lab_inventory', 'resources', 'users'];
foreach ($required_tables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p class='success'>✓ Table '$table' exists ($count records)</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Table '$table' NOT FOUND or ERROR</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
}
echo "</div>";

// Test 3: Check lab_inventory structure
echo "<div class='section'>";
echo "<h2>Test 3: Check lab_inventory Table Structure</h2>";
try {
    $stmt = $db->query("DESCRIBE lab_inventory");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_columns = [
        'id', 'resource_id', 'item_code', 'item_name', 'category', 
        'status', 'condition', 'deleted_at', 'warranty_until'
    ];
    
    $existing_columns = array_column($columns, 'Field');
    
    echo "<p><strong>Existing columns:</strong></p>";
    echo "<pre>" . implode(", ", $existing_columns) . "</pre>";
    
    echo "<p><strong>Column validation:</strong></p>";
    foreach ($required_columns as $col) {
        if (in_array($col, $existing_columns)) {
            echo "<p class='success'>✓ Column '$col' exists</p>";
        } else {
            echo "<p class='error'>✗ Column '$col' MISSING!</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error checking table structure</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
echo "</div>";

// Test 4: Check resources table
echo "<div class='section'>";
echo "<h2>Test 4: Check Resources (Labs)</h2>";
try {
    $stmt = $db->query("SELECT id, name, type FROM resources WHERE deleted_at IS NULL ORDER BY name");
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($resources)) {
        echo "<p class='warning'>⚠ No resources found in database</p>";
        echo "<p>You need to add resources (labs) first!</p>";
    } else {
        echo "<p class='success'>✓ Found " . count($resources) . " resources</p>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Type</th></tr>";
        foreach ($resources as $r) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($r['id']) . "</td>";
            echo "<td>" . htmlspecialchars($r['name']) . "</td>";
            echo "<td>" . htmlspecialchars($r['type']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error querying resources</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
echo "</div>";

// Test 5: Try the actual inventory query
echo "<div class='section'>";
echo "<h2>Test 5: Test Main Inventory Query</h2>";
try {
    $sql = "SELECT 
                li.*,
                r.name AS lab_name,
                r.id AS lab_id,
                u1.full_name AS created_by_name,
                u2.full_name AS updated_by_name
            FROM lab_inventory li
            JOIN resources r ON li.resource_id = r.id
            LEFT JOIN users u1 ON li.created_by = u1.id
            LEFT JOIN users u2 ON li.updated_by = u2.id
            WHERE li.deleted_at IS NULL
            ORDER BY r.name, li.category, li.item_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $inventories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p class='success'>✓ Query executed successfully</p>";
    echo "<p>Found <strong>" . count($inventories) . "</strong> inventory items</p>";
    
    if (count($inventories) > 0) {
        echo "<p><strong>Sample record (first item):</strong></p>";
        echo "<pre>" . print_r($inventories[0], true) . "</pre>";
    } else {
        echo "<p class='warning'>⚠ No inventory items in database</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>✗ Query FAILED</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><strong>Possible causes:</strong></p>";
    echo "<ul>";
    echo "<li>Missing JOIN table (resources or users)</li>";
    echo "<li>Column name mismatch</li>";
    echo "<li>Foreign key constraint issue</li>";
    echo "</ul>";
}
echo "</div>";

// Test 6: Check for orphaned records
echo "<div class='section'>";
echo "<h2>Test 6: Check for Orphaned Inventory Records</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) FROM lab_inventory WHERE deleted_at IS NULL AND resource_id NOT IN (SELECT id FROM resources)");
    $orphaned = $stmt->fetchColumn();
    
    if ($orphaned > 0) {
        echo "<p class='error'>✗ Found $orphaned orphaned inventory records (resource_id doesn't exist)</p>";
        echo "<p>These records need to be fixed or deleted.</p>";
        
        // Show orphaned records
        $stmt = $db->query("SELECT id, item_code, item_name, resource_id FROM lab_inventory WHERE deleted_at IS NULL AND resource_id NOT IN (SELECT id FROM resources) LIMIT 10");
        $orphaned_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Invalid Resource ID</th></tr>";
        foreach ($orphaned_records as $rec) {
            echo "<tr>";
            echo "<td>" . $rec['id'] . "</td>";
            echo "<td>" . htmlspecialchars($rec['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars($rec['item_name']) . "</td>";
            echo "<td class='error'>" . $rec['resource_id'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✓ No orphaned records found</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error checking orphaned records</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
echo "</div>";

// Test 7: Check summary query
echo "<div class='section'>";
echo "<h2>Test 7: Test Summary Query</h2>";
try {
    $summary_sql = "SELECT 
                        COUNT(*) as total_items,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_items,
                        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_items,
                        SUM(CASE WHEN condition = 'broken' THEN 1 ELSE 0 END) as broken_items,
                        SUM(CASE WHEN condition = 'poor' THEN 1 ELSE 0 END) as poor_items,
                        SUM(purchase_price) as total_value
                    FROM lab_inventory
                    WHERE deleted_at IS NULL";
    
    $stmt = $db->query($summary_sql);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p class='success'>✓ Summary query executed successfully</p>";
    echo "<pre>" . print_r($summary, true) . "</pre>";
} catch (PDOException $e) {
    echo "<p class='error'>✗ Summary query FAILED</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
echo "</div>";

// Test 8: Check warranty dates
echo "<div class='section'>";
echo "<h2>Test 8: Check Warranty Date Formats</h2>";
try {
    $stmt = $db->query("SELECT id, item_code, warranty_until FROM lab_inventory WHERE warranty_until IS NOT NULL AND deleted_at IS NULL LIMIT 20");
    $warranty_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($warranty_records)) {
        echo "<p class='warning'>⚠ No records with warranty dates</p>";
    } else {
        echo "<p class='success'>✓ Found " . count($warranty_records) . " records with warranty dates</p>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Code</th><th>Warranty Until</th><th>Valid?</th></tr>";
        
        foreach ($warranty_records as $rec) {
            $valid = true;
            try {
                new DateTime($rec['warranty_until']);
            } catch (Exception $e) {
                $valid = false;
            }
            
            echo "<tr>";
            echo "<td>" . $rec['id'] . "</td>";
            echo "<td>" . htmlspecialchars($rec['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars($rec['warranty_until']) . "</td>";
            if ($valid) {
                echo "<td class='success'>✓ Valid</td>";
            } else {
                echo "<td class='error'>✗ INVALID!</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error checking warranty dates</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
echo "</div>";

// Recommendations
echo "<div class='section'>";
echo "<h2>📋 Recommendations</h2>";
echo "<ol>";
echo "<li>If no resources found: <strong>Add lab resources first</strong></li>";
echo "<li>If orphaned records found: <strong>Fix or delete them</strong></li>";
echo "<li>If invalid dates found: <strong>Update to valid date format (YYYY-MM-DD)</strong></li>";
echo "<li>Check error_log file for detailed PHP errors</li>";
echo "<li>Make sure all foreign keys are valid</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>