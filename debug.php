<?php
$user = \App\Models\User::first();
echo $user->status ?? 'NULL';
