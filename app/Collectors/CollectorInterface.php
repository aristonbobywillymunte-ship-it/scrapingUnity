<?php
namespace App\Collectors;

interface CollectorInterface {
    public function collect($task): array;
}
