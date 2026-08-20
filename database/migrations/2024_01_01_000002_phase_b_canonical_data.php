<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::unprepared('CREATE TYPE canonical_entity_type AS ENUM (
    \'PROFILE\', \'POST\', \'VIDEO\', \'ARTICLE\', \'COMMENT\', \'REPLY\', \'PAGE\'
);

-- 1. canonical_entities
CREATE TABLE canonical_entities (
    id UUID PRIMARY KEY,
    platform VARCHAR(50) NOT NULL,
    entity_type canonical_entity_type NOT NULL,
    stable_source_id VARCHAR(255),
    normalized_url TEXT,
    identity_hash VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (id, entity_type)
);

CREATE INDEX idx_canonical_entities_platform_type ON canonical_entities(platform, entity_type);
CREATE INDEX idx_canonical_entities_source_id ON canonical_entities(stable_source_id) WHERE stable_source_id IS NOT NULL;
CREATE INDEX idx_canonical_entities_normalized_url ON canonical_entities(normalized_url) WHERE normalized_url IS NOT NULL;
CREATE INDEX idx_canonical_entities_identity_hash ON canonical_entities(identity_hash);

-- 2. canonical_profiles
CREATE TABLE canonical_profiles (
    canonical_entity_id UUID NOT NULL,
    entity_type canonical_entity_type NOT NULL,
    username VARCHAR(255),
    display_name VARCHAR(255),
    safe_metadata JSONB,
    PRIMARY KEY (canonical_entity_id),
    FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    CHECK (entity_type = \'PROFILE\')
);

-- 3. canonical_posts
CREATE TABLE canonical_posts (
    canonical_entity_id UUID NOT NULL,
    entity_type canonical_entity_type NOT NULL,
    author_profile_id UUID,
    text_content TEXT,
    published_at TIMESTAMPTZ,
    safe_metadata JSONB,
    PRIMARY KEY (canonical_entity_id),
    FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    FOREIGN KEY (author_profile_id) REFERENCES canonical_profiles(canonical_entity_id) ON DELETE RESTRICT,
    CHECK (entity_type = \'POST\')
);
CREATE INDEX idx_canonical_posts_author ON canonical_posts(author_profile_id) WHERE author_profile_id IS NOT NULL;

-- 4. canonical_videos
CREATE TABLE canonical_videos (
    canonical_entity_id UUID NOT NULL,
    entity_type canonical_entity_type NOT NULL,
    author_profile_id UUID,
    text_content TEXT,
    published_at TIMESTAMPTZ,
    safe_metadata JSONB,
    PRIMARY KEY (canonical_entity_id),
    FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    FOREIGN KEY (author_profile_id) REFERENCES canonical_profiles(canonical_entity_id) ON DELETE RESTRICT,
    CHECK (entity_type = \'VIDEO\')
);
CREATE INDEX idx_canonical_videos_author ON canonical_videos(author_profile_id) WHERE author_profile_id IS NOT NULL;

-- 5. canonical_articles
CREATE TABLE canonical_articles (
    canonical_entity_id UUID NOT NULL,
    entity_type canonical_entity_type NOT NULL,
    title VARCHAR(512),
    canonical_url TEXT,
    text_content TEXT,
    published_at TIMESTAMPTZ,
    author_name VARCHAR(255),
    safe_metadata JSONB,
    PRIMARY KEY (canonical_entity_id),
    FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    CHECK (entity_type = \'ARTICLE\')
);

-- 6. canonical_pages
CREATE TABLE canonical_pages (
    canonical_entity_id UUID NOT NULL,
    entity_type canonical_entity_type NOT NULL,
    title VARCHAR(512),
    url TEXT,
    text_content TEXT,
    safe_metadata JSONB,
    PRIMARY KEY (canonical_entity_id),
    FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    CHECK (entity_type = \'PAGE\')
);

-- 7. canonical_comments
CREATE TABLE canonical_comments (
    canonical_entity_id UUID NOT NULL,
    entity_type canonical_entity_type NOT NULL,
    parent_content_id UUID NOT NULL,
    parent_entity_type canonical_entity_type NOT NULL,
    author_profile_id UUID,
    text_content TEXT,
    published_at TIMESTAMPTZ,
    safe_metadata JSONB,
    PRIMARY KEY (canonical_entity_id),
    FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    FOREIGN KEY (parent_content_id, parent_entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    FOREIGN KEY (author_profile_id) REFERENCES canonical_profiles(canonical_entity_id) ON DELETE RESTRICT,
    CHECK (entity_type = \'COMMENT\'),
    CHECK (parent_entity_type IN (\'POST\', \'VIDEO\', \'ARTICLE\', \'PAGE\'))
);
CREATE INDEX idx_canonical_comments_parent ON canonical_comments(parent_content_id);
CREATE INDEX idx_canonical_comments_author ON canonical_comments(author_profile_id) WHERE author_profile_id IS NOT NULL;

-- 8. canonical_replies
CREATE TABLE canonical_replies (
    canonical_entity_id UUID NOT NULL,
    entity_type canonical_entity_type NOT NULL,
    root_content_id UUID NOT NULL,
    root_entity_type canonical_entity_type NOT NULL,
    parent_comment_id UUID,
    author_profile_id UUID,
    text_content TEXT,
    published_at TIMESTAMPTZ,
    safe_metadata JSONB,
    PRIMARY KEY (canonical_entity_id),
    FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    FOREIGN KEY (root_content_id, root_entity_type) REFERENCES canonical_entities(id, entity_type) ON DELETE RESTRICT,
    FOREIGN KEY (parent_comment_id) REFERENCES canonical_comments(canonical_entity_id) ON DELETE RESTRICT,
    FOREIGN KEY (author_profile_id) REFERENCES canonical_profiles(canonical_entity_id) ON DELETE RESTRICT,
    CHECK (entity_type = \'REPLY\'),
    CHECK (root_entity_type IN (\'POST\', \'VIDEO\', \'ARTICLE\', \'PAGE\'))
);
CREATE INDEX idx_canonical_replies_root ON canonical_replies(root_content_id);
CREATE INDEX idx_canonical_replies_parent_comment ON canonical_replies(parent_comment_id) WHERE parent_comment_id IS NOT NULL;
CREATE INDEX idx_canonical_replies_author ON canonical_replies(author_profile_id) WHERE author_profile_id IS NOT NULL;

');
    }

    public function down()
    {
        // DB::unprepared(...);
    }
};
