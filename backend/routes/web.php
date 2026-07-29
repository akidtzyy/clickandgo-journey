<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get("/", function () {
    return ["message" => "API Backend Travel Ready"];
});

Route::get('/export-sql', function () {
    $tables = DB::select('SHOW TABLES');
    
    // 1. Matikan pemeriksaan Foreign Key di awal
    $sql = "-- Dump Database Railway\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        
        // Ambil struktur tabel (CREATE TABLE)
        $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
        $sql .= array_values((array)$createTable[0])[1] . ";\n\n";
        
        // Ambil isi data tabel (INSERT INTO)
        $rows = DB::table($tableName)->get();
        foreach ($rows as $row) {
            $values = array_map(function($val) {
                if (is_null($val)) return "NULL";
                return "'" . addslashes($val) . "'";
            }, (array)$row);
            
            if (!empty($values)) {
                $sql .= "INSERT INTO `{$tableName}` VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        $sql .= "\n\n";
    }
    
    // 2. Hidupkan kembali pemeriksaan Foreign Key di akhir.
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    return response($sql, 200)
        ->header('Content-Type', 'text/plain')
        ->header('Content-Disposition', 'attachment; filename="railway_backup.sql"');
});