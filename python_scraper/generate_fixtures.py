import os
import json

base_dir = "tests/fixtures/facebook"
os.makedirs(base_dir, exist_ok=True)

fixtures = {
    "profile_success.json": {
        "data": {
            "items": [{
                "id": "12345",
                "username": "example",
                "bio": "This is a public bio",
                "followers": 1000,
                "is_verified": True
            }]
        }
    },
    "profile_auth_required.json": {
        "error_type": "auth_required"
    },
    "single_post_success.json": {
        "data": {
            "items": [{
                "post_id": "98765",
                "url": "https://facebook.com/example/posts/98765",
                "author": {"id": "123", "username": "example"},
                "text": "Hello world",
                "likes": 50,
                "shares": 0
            }]
        }
    },
    "single_post_malformed.json": {
        "error_type": "malformed"
    },
    "profile_posts_page_1.json": {
        "data": {
            "items": [{"post_id": "1", "text": "Post 1"}, {"post_id": "2", "text": "Post 2"}],
            "pagination": {"next_cursor": "cursor_page_2"}
        }
    },
    "profile_posts_duplicate.json": {
        "data": {
            "items": [{"post_id": "1", "text": "Post 1"}, {"post_id": "1", "text": "Post 1"}],
            "pagination": {"next_cursor": "cursor_page_3"}
        }
    },
    "replies_success.json": {
        "data": {
            "items": [{"comment_id": "c1", "text": "A reply", "parent_post_id": "98765"}]
        }
    },
    "search_keyword_success.json": {
        "data": {
            "items": [{"post_id": "s1", "text": "Search result for IKN"}]
        }
    },
    "search_hashtag_success.json": {
        "data": {
            "items": [{"post_id": "h1", "text": "Hashtag result for #IKN"}]
        }
    }
}

for name, content in fixtures.items():
    with open(os.path.join(base_dir, name), "w") as f:
        json.dump(content, f)

print("Fixtures generated.")
