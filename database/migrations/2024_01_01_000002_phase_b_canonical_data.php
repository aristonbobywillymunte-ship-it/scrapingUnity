<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("
            DO $$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'canonical_entity_type') THEN
                    CREATE TYPE canonical_entity_type AS ENUM ('PROFILE', 'POST', 'VIDEO', 'ARTICLE', 'COMMENT', 'REPLY', 'PAGE');
                END IF;
            END $$;
        ");

        Schema::create('canonical_entities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform', 50);
            $table->string('stable_source_id', 255)->nullable();
            $table->text('normalized_url')->nullable();
            $table->string('identity_hash', 255)->unique();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        
        DB::statement('ALTER TABLE canonical_entities ADD COLUMN entity_type canonical_entity_type NOT NULL');
        DB::statement('ALTER TABLE canonical_entities ADD CONSTRAINT canonical_entities_id_entity_type_unique UNIQUE (id, entity_type)');
        
        DB::statement('CREATE INDEX idx_canonical_entities_platform_type ON canonical_entities(platform, entity_type)');
        DB::statement('CREATE INDEX idx_canonical_entities_source_id ON canonical_entities(stable_source_id) WHERE stable_source_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_canonical_entities_normalized_url ON canonical_entities(normalized_url) WHERE normalized_url IS NOT NULL');
        DB::statement('CREATE INDEX idx_canonical_entities_identity_hash ON canonical_entities(identity_hash)');

        Schema::create('canonical_profiles', function (Blueprint $table) {
            $table->uuid('canonical_entity_id')->primary();
            $table->string('username', 255)->nullable();
            $table->string('display_name', 255)->nullable();
            $table->jsonb('safe_metadata')->nullable();
        });
        DB::statement('ALTER TABLE canonical_profiles ADD COLUMN entity_type canonical_entity_type NOT NULL');
        DB::statement("ALTER TABLE canonical_profiles ADD CONSTRAINT fk_cp_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_profiles ADD CONSTRAINT chk_profiles_type CHECK (entity_type = 'PROFILE')");

        Schema::create('canonical_posts', function (Blueprint $table) {
            $table->uuid('canonical_entity_id')->primary();
            $table->uuid('author_profile_id')->nullable();
            $table->text('text_content')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->jsonb('safe_metadata')->nullable();
        });
        DB::statement('ALTER TABLE canonical_posts ADD COLUMN entity_type canonical_entity_type NOT NULL');
        DB::statement("ALTER TABLE canonical_posts ADD CONSTRAINT fk_cpst_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_posts ADD CONSTRAINT fk_cpst_auth FOREIGN KEY (author_profile_id) REFERENCES canonical_profiles(canonical_entity_id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_posts ADD CONSTRAINT chk_posts_type CHECK (entity_type = 'POST')");
        DB::statement('CREATE INDEX idx_canonical_posts_author ON canonical_posts(author_profile_id) WHERE author_profile_id IS NOT NULL');

        Schema::create('canonical_videos', function (Blueprint $table) {
            $table->uuid('canonical_entity_id')->primary();
            $table->uuid('author_profile_id')->nullable();
            $table->text('text_content')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->jsonb('safe_metadata')->nullable();
        });
        DB::statement('ALTER TABLE canonical_videos ADD COLUMN entity_type canonical_entity_type NOT NULL');
        DB::statement("ALTER TABLE canonical_videos ADD CONSTRAINT fk_cv_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_videos ADD CONSTRAINT fk_cv_auth FOREIGN KEY (author_profile_id) REFERENCES canonical_profiles(canonical_entity_id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_videos ADD CONSTRAINT chk_videos_type CHECK (entity_type = 'VIDEO')");
        DB::statement('CREATE INDEX idx_canonical_videos_author ON canonical_videos(author_profile_id) WHERE author_profile_id IS NOT NULL');

        Schema::create('canonical_articles', function (Blueprint $table) {
            $table->uuid('canonical_entity_id')->primary();
            $table->string('title', 512)->nullable();
            $table->text('canonical_url')->nullable();
            $table->text('text_content')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->string('author_name', 255)->nullable();
            $table->jsonb('safe_metadata')->nullable();
        });
        DB::statement('ALTER TABLE canonical_articles ADD COLUMN entity_type canonical_entity_type NOT NULL');
        DB::statement("ALTER TABLE canonical_articles ADD CONSTRAINT fk_ca_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_articles ADD CONSTRAINT chk_articles_type CHECK (entity_type = 'ARTICLE')");

        Schema::create('canonical_pages', function (Blueprint $table) {
            $table->uuid('canonical_entity_id')->primary();
            $table->string('title', 512)->nullable();
            $table->text('url')->nullable();
            $table->text('text_content')->nullable();
            $table->jsonb('safe_metadata')->nullable();
        });
        DB::statement('ALTER TABLE canonical_pages ADD COLUMN entity_type canonical_entity_type NOT NULL');
        DB::statement("ALTER TABLE canonical_pages ADD CONSTRAINT fk_cpg_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_pages ADD CONSTRAINT chk_pages_type CHECK (entity_type = 'PAGE')");

        Schema::create('canonical_comments', function (Blueprint $table) {
            $table->uuid('canonical_entity_id')->primary();
            $table->uuid('parent_content_id');
            $table->uuid('author_profile_id')->nullable();
            $table->text('text_content')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->jsonb('safe_metadata')->nullable();
        });
        DB::statement('ALTER TABLE canonical_comments ADD COLUMN entity_type canonical_entity_type NOT NULL');
        DB::statement('ALTER TABLE canonical_comments ADD COLUMN parent_entity_type canonical_entity_type NOT NULL');
        DB::statement("ALTER TABLE canonical_comments ADD CONSTRAINT fk_cc_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_comments ADD CONSTRAINT fk_cc_parent FOREIGN KEY (parent_content_id, parent_entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_comments ADD CONSTRAINT fk_cc_auth FOREIGN KEY (author_profile_id) REFERENCES canonical_profiles(canonical_entity_id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_comments ADD CONSTRAINT chk_comments_type CHECK (entity_type = 'COMMENT')");
        DB::statement("ALTER TABLE canonical_comments ADD CONSTRAINT chk_comments_parent_type CHECK (parent_entity_type IN ('POST', 'VIDEO', 'ARTICLE', 'PAGE'))");
        DB::statement('CREATE INDEX idx_canonical_comments_parent ON canonical_comments(parent_content_id)');
        DB::statement('CREATE INDEX idx_canonical_comments_author ON canonical_comments(author_profile_id) WHERE author_profile_id IS NOT NULL');

        Schema::create('canonical_replies', function (Blueprint $table) {
            $table->uuid('canonical_entity_id')->primary();
            $table->uuid('root_content_id');
            $table->uuid('parent_comment_id')->nullable();
            $table->uuid('author_profile_id')->nullable();
            $table->text('text_content')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->jsonb('safe_metadata')->nullable();
        });
        DB::statement('ALTER TABLE canonical_replies ADD COLUMN entity_type canonical_entity_type NOT NULL');
        DB::statement('ALTER TABLE canonical_replies ADD COLUMN root_entity_type canonical_entity_type NOT NULL');
        DB::statement("ALTER TABLE canonical_replies ADD CONSTRAINT fk_cr_ce FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_replies ADD CONSTRAINT fk_cr_root FOREIGN KEY (root_content_id, root_entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_replies ADD CONSTRAINT fk_cr_parent FOREIGN KEY (parent_comment_id) REFERENCES canonical_comments(canonical_entity_id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_replies ADD CONSTRAINT fk_cr_auth FOREIGN KEY (author_profile_id) REFERENCES canonical_profiles(canonical_entity_id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE canonical_replies ADD CONSTRAINT chk_replies_type CHECK (entity_type = 'REPLY')");
        DB::statement("ALTER TABLE canonical_replies ADD CONSTRAINT chk_replies_root_type CHECK (root_entity_type IN ('POST', 'VIDEO', 'ARTICLE', 'PAGE'))");
        DB::statement('CREATE INDEX idx_canonical_replies_root ON canonical_replies(root_content_id)');
        DB::statement('CREATE INDEX idx_canonical_replies_parent_comment ON canonical_replies(parent_comment_id) WHERE parent_comment_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_canonical_replies_author ON canonical_replies(author_profile_id) WHERE author_profile_id IS NOT NULL');
    }

    public function down(): void {
        Schema::dropIfExists('canonical_replies');
        Schema::dropIfExists('canonical_comments');
        Schema::dropIfExists('canonical_pages');
        Schema::dropIfExists('canonical_articles');
        Schema::dropIfExists('canonical_videos');
        Schema::dropIfExists('canonical_posts');
        Schema::dropIfExists('canonical_profiles');
        Schema::dropIfExists('canonical_entities');
        DB::statement('DROP TYPE IF EXISTS canonical_entity_type');
    }
};
