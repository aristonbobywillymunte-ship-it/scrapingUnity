<?php
$content = file_get_contents('tests/Feature/TaskEngineTest.php');
$content = str_replace(
    "\$task = \$service->createTask(\$run->id, \$o1->id, 'facebook_posts');\n    \$service->startTask(\$task);",
    "\$task = \$service->createTask(\$run->id, \$o1->id, 'facebook_posts');\n    \$task->status = 'LEASED'; \$task->save();\n    \$service->startTask(\$task);",
    $content
);
file_put_contents('tests/Feature/TaskEngineTest.php', $content);
