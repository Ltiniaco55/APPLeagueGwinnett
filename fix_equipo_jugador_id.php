<?php
require 'app/core/database.php';
$db = Database::getInstance();

try {
    // MySQL requires dropping primary key if there's already a composite one before adding an auto_increment PK
    try {
        $db->exec("ALTER TABLE equipo_jugador DROP PRIMARY KEY");
    } catch (\Exception $e) { /* Ignore if it doesn't have a PK */ }

    // Add the auto increment `id` column as the robust primary key
    $db->exec("ALTER TABLE equipo_jugador ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
    echo "Successfully added `id` column to `equipo_jugador` table!\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "`id` column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Clean up mismatched states (if any) generated from before
try {
    $db->exec("DELETE FROM equipo_jugador WHERE id_jugador = 0 OR id_equipo = 0");
    echo "Cleaned up invalid rows.\n";
} catch (\Exception $e) {}
