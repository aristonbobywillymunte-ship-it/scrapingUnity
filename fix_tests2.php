<?php
$files = glob('tests/Feature/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    $content = str_replace('"name" => "Owner"', '"description" => "Owner"', $content);
    $content = str_replace("'name' => 'Owner'", "'description' => 'Owner'", $content);
    $content = str_replace("'name' => 'Admin'", "'description' => 'Admin'", $content);
    $content = str_replace("'name' => 'Member'", "'description' => 'Member'", $content);
    $content = str_replace("'name' => 'Internal Admin'", "'description' => 'Internal Admin'", $content);
    file_put_contents($file, $content);
}
