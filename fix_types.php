<?php
$content = file_get_contents('database/migrations/2024_01_01_000002_phase_b_canonical_data.php');

$replacements = [
    "Schema::create('canonical_entities', function (Blueprint \$table) {" => "Schema::create('canonical_entities', function (Blueprint \$table) {\n            \$table->string('entity_type', 50);",
    "Schema::create('canonical_profiles', function (Blueprint \$table) {" => "Schema::create('canonical_profiles', function (Blueprint \$table) {\n            \$table->string('entity_type', 50);",
    "Schema::create('canonical_posts', function (Blueprint \$table) {" => "Schema::create('canonical_posts', function (Blueprint \$table) {\n            \$table->string('entity_type', 50);",
    "Schema::create('canonical_videos', function (Blueprint \$table) {" => "Schema::create('canonical_videos', function (Blueprint \$table) {\n            \$table->string('entity_type', 50);",
    "Schema::create('canonical_articles', function (Blueprint \$table) {" => "Schema::create('canonical_articles', function (Blueprint \$table) {\n            \$table->string('entity_type', 50);",
    "Schema::create('canonical_pages', function (Blueprint \$table) {" => "Schema::create('canonical_pages', function (Blueprint \$table) {\n            \$table->string('entity_type', 50);",
    "Schema::create('canonical_comments', function (Blueprint \$table) {" => "Schema::create('canonical_comments', function (Blueprint \$table) {\n            \$table->string('entity_type', 50);\n            \$table->string('parent_entity_type', 50);",
    "Schema::create('canonical_replies', function (Blueprint \$table) {" => "Schema::create('canonical_replies', function (Blueprint \$table) {\n            \$table->string('entity_type', 50);\n            \$table->string('root_entity_type', 50);",
    
    // Remove addColumn
    "\$table->addColumn('canonical_entity_type', 'entity_type');" => "",
    "\$table->addColumn('canonical_entity_type', 'parent_entity_type');" => "",
    "\$table->addColumn('canonical_entity_type', 'root_entity_type');" => "",

    // Remove foreign keys from Schema builder that involve entity_type
    "\$table->foreign(['canonical_entity_id', 'entity_type'])->references(['id', 'entity_type'])->on('canonical_entities')->onDelete('restrict');" => "",
    "\$table->foreign(['parent_content_id', 'parent_entity_type'])->references(['id', 'entity_type'])->on('canonical_entities')->onDelete('restrict');" => "",
    "\$table->foreign(['root_content_id', 'root_entity_type'])->references(['id', 'entity_type'])->on('canonical_entities')->onDelete('restrict');" => "",
];

$content = str_replace(array_keys($replacements), array_values($replacements), $content);

// Change CREATE TYPE to IF NOT EXISTS
$content = str_replace(
    "DB::statement(\"CREATE TYPE canonical_entity_type AS ENUM ('PROFILE', 'POST', 'VIDEO', 'ARTICLE', 'COMMENT', 'REPLY', 'PAGE')\");",
    "DB::statement(\"\n            DO \$\$ BEGIN\n                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'canonical_entity_type') THEN\n                    CREATE TYPE canonical_entity_type AS ENUM ('PROFILE', 'POST', 'VIDEO', 'ARTICLE', 'COMMENT', 'REPLY', 'PAGE');\n                END IF;\n            END \$\$;\n        \");",
    $content
);

// Append ALTER TABLE to end of up()
$alters = "
        DB::statement('ALTER TABLE canonical_entities ALTER COLUMN entity_type TYPE canonical_entity_type USING entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_profiles ALTER COLUMN entity_type TYPE canonical_entity_type USING entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_posts ALTER COLUMN entity_type TYPE canonical_entity_type USING entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_videos ALTER COLUMN entity_type TYPE canonical_entity_type USING entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_articles ALTER COLUMN entity_type TYPE canonical_entity_type USING entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_pages ALTER COLUMN entity_type TYPE canonical_entity_type USING entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_comments ALTER COLUMN entity_type TYPE canonical_entity_type USING entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_comments ALTER COLUMN parent_entity_type TYPE canonical_entity_type USING parent_entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_replies ALTER COLUMN entity_type TYPE canonical_entity_type USING entity_type::canonical_entity_type');
        DB::statement('ALTER TABLE canonical_replies ALTER COLUMN root_entity_type TYPE canonical_entity_type USING root_entity_type::canonical_entity_type');
        
        DB::statement('ALTER TABLE canonical_profiles ADD CONSTRAINT fk_cp_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE canonical_posts ADD CONSTRAINT fk_cpst_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE canonical_videos ADD CONSTRAINT fk_cv_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE canonical_articles ADD CONSTRAINT fk_ca_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE canonical_pages ADD CONSTRAINT fk_cpg_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE canonical_comments ADD CONSTRAINT fk_cc_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE canonical_comments ADD CONSTRAINT fk_cc_parent FOREIGN KEY (parent_content_id, parent_entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE canonical_replies ADD CONSTRAINT fk_cr_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE canonical_replies ADD CONSTRAINT fk_cr_root FOREIGN KEY (root_content_id, root_entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT');
    }
";

$content = str_replace("    }\n\n    public function down(): void {", $alters . "\n    public function down(): void {", $content);

file_put_contents('database/migrations/2024_01_01_000002_phase_b_canonical_data.php', $content);
