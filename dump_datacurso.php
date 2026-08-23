<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB;
$tables = $DB->get_tables();
$datacurso_tables = [];

foreach ($tables as $table) {
    if (strpos($table, 'datacurso') !== false || strpos($table, 'aiprovider') !== false) {
        $datacurso_tables[] = $table;
    }
}

echo "<h1>Datacurso AI Tables</h1>";
if (empty($datacurso_tables)) {
    echo "<p>No tables found matching 'datacurso' or 'aiprovider'.</p>";
} else {
    foreach ($datacurso_tables as $table) {
        echo "<h2>Table: " . $table . "</h2>";
        $columns = $DB->get_columns($table);
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li><strong>" . $column->name . "</strong> (" . $column->type . ")</li>";
        }
        echo "</ul>";
        
        // Show sample data for context
        $records = $DB->get_records($table, null, '', '*', 0, 3);
        if ($records) {
            echo "<h3>Sample Data:</h3><pre>";
            print_r($records);
            echo "</pre>";
        }
    }
}
echo "<p>Fin.</p>";
