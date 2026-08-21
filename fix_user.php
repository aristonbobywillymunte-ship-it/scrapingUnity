<?php
$file = 'app/Models/User.php';
$content = file_get_contents($file);
$content = str_replace(
    "public function getAuthPasswordName()",
    "public function organizationMemberships()\n    {\n        return \$this->hasMany(OrganizationMembership::class);\n    }\n\n    public function getAuthPasswordName()",
    $content
);
file_put_contents($file, $content);
