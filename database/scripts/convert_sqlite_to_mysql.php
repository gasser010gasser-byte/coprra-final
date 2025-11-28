<?php

set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

$sqliteDb = '/home/u990109832/temp_db/backup_13mb.sqlite';
$outputFile = '/home/u990109832/temp_db/converted_data.sql';

if (! file_exists($sqliteDb)) {
    die("Error: SQLite database not found\n");
}

try {
    $sqlite = new PDO('sqlite:' . $sqliteDb);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $output = fopen($outputFile, 'w');

    echo "Starting conversion process...\n";
    echo "===========================================\n";

    // Write header
    fwrite($output, "-- SQLite to MySQL Conversion\n");
    fwrite($output, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($output, "-- Source: $sqliteDb\n");
    fwrite($output, "\n");
    fwrite($output, "SET FOREIGN_KEY_CHECKS=0;\n");
    fwrite($output, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
    fwrite($output, "SET time_zone = '+00:00';\n");
    fwrite($output, "\n");

    // Tables to export in correct order (respecting foreign keys)
    $tables = [
        'currencies',
        'languages',
        'categories',
        'brands',
        'stores',
        'products',
        'product_category',
        'product_store',
        'price_offers',
        'reviews',
        'wishlists',
        'price_alerts',
        'user_locale_settings',
        'exchange_rates',
    ];

    foreach ($tables as $table) {
        // Check if table exists
        $tableExists = $sqlite->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='$table'"
        )->fetchColumn();

        if (! $tableExists) {
            echo "⏭️  Skipping $table (table not found)\n";

            continue;
        }

        $count = $sqlite->query("SELECT COUNT(*) FROM $table")->fetchColumn();

        if ($count == 0) {
            echo "⏭️  Skipping $table (empty)\n";

            continue;
        }

        echo "📦 Exporting $table ($count records)...\n";

        // Truncate table
        fwrite($output, "\n-- Table: $table\n");
        fwrite($output, "TRUNCATE TABLE `$table`;\n");

        // Get column names
        $columns = $sqlite->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_map(function ($col) { return $col['name']; }, $columns);

        // Export data in batches
        $batchSize = 100;
        $offset = 0;

        while (true) {
            $rows = $sqlite->query(
                "SELECT * FROM $table LIMIT $batchSize OFFSET $offset"
            )->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                break;
            }

            // Build INSERT statement
            $insertValues = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($columnNames as $col) {
                    if ($row[$col] === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($row[$col])) {
                        $values[] = $row[$col];
                    } else {
                        $escaped = addslashes($row[$col]);
                        $values[] = "'" . $escaped . "'";
                    }
                }
                $insertValues[] = '(' . implode(', ', $values) . ')';
            }

            if (! empty($insertValues)) {
                $sql = "INSERT INTO `$table` (`" . implode('`, `', $columnNames) . "`)";
                $sql .= " VALUES \n" . implode(",\n", $insertValues) . ";" . "\n";
                fwrite($output, $sql);
            }

            $offset += $batchSize;
        }

        echo "✅ Exported $table\n";
    }

    fwrite($output, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    fclose($output);

    echo "\n===========================================\n";
    echo "✅ Conversion complete!\n";
    echo "Output file: $outputFile\n";
    echo "File size: " . number_format(filesize($outputFile)) . " bytes\n";
    echo "===========================================\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
