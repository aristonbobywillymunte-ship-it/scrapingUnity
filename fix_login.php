<?php
$file = 'app/Livewire/Auth/Login.php';
$content = file_get_contents($file);
$content = str_replace("request()->session()->regenerate();", "if (request()->hasSession()) { request()->session()->regenerate(); }", $content);
$content = str_replace("request()->session()->put('auth_token', \$token);", "if (request()->hasSession()) { request()->session()->put('auth_token', \$token); }", $content);
file_put_contents($file, $content);
