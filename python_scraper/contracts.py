from pydantic import BaseModel, HttpUrl, Field, model_validator
from typing import Optional, Dict, Any, Literal, List
from enum import Enum

class PlatformEnum(str, Enum):
    FACEBOOK = "facebook"

class OperationEnum(str, Enum):
    PROFILE = "profile"
    SINGLE_POST = "single_post"
    PROFILE_POSTS = "profile_posts"
    REPLIES = "replies"
    SEARCH_POSTS = "search_posts"

class TargetTypeEnum(str, Enum):
    USERNAME = "username"
    URL = "url"
    ID = "id"
    POST_ID = "post_id"
    COMMENT_ID = "comment_id"
    KEYWORD = "keyword"
    HASHTAG = "hashtag"

class Target(BaseModel):
    type: TargetTypeEnum
    value: str

class Options(BaseModel):
    limit: Optional[int] = Field(None, le=100) # bounded limit
    mode: Literal["http", "browser"] = "http"

class ExecutionContract(BaseModel):
    execution_id: str
    platform: PlatformEnum
    operation: OperationEnum
    target: Target
    options: Options

    @model_validator(mode='after')
    def validate_target_type_for_operation(self):
        op = self.operation
        tgt = self.target
        if not op or not tgt:
            return self
        
        t_type = tgt.type
        valid_map = {
            OperationEnum.PROFILE: [TargetTypeEnum.USERNAME, TargetTypeEnum.URL, TargetTypeEnum.ID],
            OperationEnum.SINGLE_POST: [TargetTypeEnum.URL, TargetTypeEnum.POST_ID],
            OperationEnum.PROFILE_POSTS: [TargetTypeEnum.USERNAME, TargetTypeEnum.URL, TargetTypeEnum.ID],
            OperationEnum.REPLIES: [TargetTypeEnum.URL, TargetTypeEnum.POST_ID, TargetTypeEnum.COMMENT_ID],
            OperationEnum.SEARCH_POSTS: [TargetTypeEnum.KEYWORD, TargetTypeEnum.HASHTAG],
        }
        if t_type not in valid_map[op]:
            raise ValueError(f"Invalid target type {t_type} for operation {op}")
        return self


class Author(BaseModel):
    external_id: Optional[str] = None
    username: Optional[str] = None
    display_name: Optional[str] = None

class MediaItem(BaseModel):
    type: Literal["image", "video", "unknown"]
    url: Optional[str] = None

class NormalizedItem(BaseModel):
    platform: Literal["facebook"] = "facebook"
    content_type: str
    external_id: Optional[str] = None
    canonical_url: Optional[str] = None
    author: Author
    text: Optional[str] = None
    published_at: Optional[str] = None
    media: List[MediaItem] = []
    metrics: Dict[str, Optional[int]] = {}
    platform_fields: Dict[str, Any] = {}
    collected_at: str
    parser_version: str

    @model_validator(mode='before')
    @classmethod
    def handle_zero_null_semantics(cls, values):
        if 'metrics' in values:
            for k, v in values['metrics'].items():
                if v is not None and not isinstance(v, int):
                    try:
                        values['metrics'][k] = int(v)
                    except (ValueError, TypeError):
                        values['metrics'][k] = None
        return values
