from pydantic import BaseModel, Field, model_validator
from typing import List, Optional, Dict, Any
from enum import Enum

class PlatformEnum(str, Enum):
    FACEBOOK = "facebook"
    INSTAGRAM = "instagram"
    TIKTOK = "tiktok"
    X = "x"
    YOUTUBE = "youtube"

class OperationEnum(str, Enum):
    PROFILE = "profile"
    SINGLE_POST = "single_post"
    PROFILE_POSTS = "profile_posts"
    REPLIES = "replies"
    SEARCH_POSTS = "search_posts"

class TargetTypeEnum(str, Enum):
    USERNAME = "username"
    POST_ID = "post_id"
    URL = "url"
    KEYWORD = "keyword"
    HASHTAG = "hashtag"

class Target(BaseModel):
    type: TargetTypeEnum
    value: str

class Options(BaseModel):
    limit: Optional[int] = 20
    max_pages: Optional[int] = 1
    cursor: Optional[str] = None
    mode: Optional[str] = "http"
    force_real_transport: Optional[bool] = False
    proxy_url: Optional[str] = None

class ExecutionContract(BaseModel):
    execution_id: str
    platform: PlatformEnum
    operation: OperationEnum
    target: Target
    options: Options = Field(default_factory=Options)
    request_fingerprint: str

    @model_validator(mode='after')
    def validate_operation_target_compatibility(self):
        op = self.operation
        target_type = self.target.type
        if op == OperationEnum.PROFILE and target_type not in (TargetTypeEnum.USERNAME, TargetTypeEnum.URL):
            raise ValueError(f"Operation PROFILE requires USERNAME or URL, got {target_type}")
        if op == OperationEnum.SINGLE_POST and target_type not in (TargetTypeEnum.POST_ID, TargetTypeEnum.URL):
            raise ValueError(f"Operation SINGLE_POST requires POST_ID or URL, got {target_type}")
        if op == OperationEnum.SEARCH_POSTS and target_type not in (TargetTypeEnum.KEYWORD, TargetTypeEnum.HASHTAG):
            raise ValueError(f"Operation SEARCH_POSTS requires KEYWORD or HASHTAG, got {target_type}")
        return self

class Author(BaseModel):
    external_id: Optional[str] = None
    username: Optional[str] = None
    display_name: Optional[str] = None
    profile_url: Optional[str] = None
    avatar_url: Optional[str] = None
    is_verified: Optional[bool] = None

class MediaItem(BaseModel):
    type: str  # image, video
    url: str
    thumbnail_url: Optional[str] = None
    duration_seconds: Optional[int] = None
    width: Optional[int] = None
    height: Optional[int] = None

class NormalizedItem(BaseModel):
    platform: str
    content_type: str  # PROFILE, POST, COMMENT
    external_id: str
    canonical_url: str
    author: Optional[Author] = None
    text: Optional[str] = None
    published_at: Optional[str] = None
    media: List[MediaItem] = []
    metrics: Optional[Dict[str, Optional[int]]] = None
    platform_fields: Dict[str, Any] = {}
    collected_at: str
    parser_version: str

    @model_validator(mode='before')
    @classmethod
    def handle_zero_null_semantics(cls, values):
        if isinstance(values, dict) and 'metrics' in values and values['metrics'] is not None:
            for k, v in values['metrics'].items():
                if v is not None and not isinstance(v, int):
                    try:
                        values['metrics'][k] = int(v)
                    except (ValueError, TypeError):
                        values['metrics'][k] = None
        return values
