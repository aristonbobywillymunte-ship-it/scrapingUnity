import re
import hashlib
import time
from typing import List, Dict, Any, Optional

class FacebookHtmlParser:
    """
    DOM & Meta Tag parser for Facebook public HTML content.
    Extracts real structured metadata without inventing synthetic content.
    Missing fields remain None / empty per canonical data contracts.
    """
    def parse_profile(self, html: str, source_url: str) -> List[Dict[str, Any]]:
        items = []
        if not html:
            return items

        og_title = self._get_meta_content(html, "og:title")
        og_desc = self._get_meta_content(html, "og:description")
        og_url = self._get_meta_content(html, "og:url") or source_url

        if og_title:
            display_name = og_title.split(" - ")[0].strip() if " - " in og_title else og_title.strip()
            username = display_name.lower().replace(" ", "_") if display_name else None
            stable_id = f"fb_prof_{hashlib.sha256(og_url.encode('utf-8')).hexdigest()[:16]}"
            items.append({
                "platform": "facebook",
                "content_type": "PROFILE",
                "external_id": stable_id,
                "canonical_url": og_url,
                "author": {
                    "username": username,
                    "display_name": display_name
                },
                "text": og_desc or None,
                "published_at": None,
                "media": [],
                "metrics": None,
                "platform_fields": {"extraction_method": "opengraph_profile"},
                "collected_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
                "parser_version": "1.0.0"
            })

        return items

    def parse_posts(self, html: str, source_url: str) -> List[Dict[str, Any]]:
        items = []
        if not html:
            return items

        # 1. OpenGraph meta tag extraction
        og_title = self._get_meta_content(html, "og:title")
        og_desc = self._get_meta_content(html, "og:description")
        og_url = self._get_meta_content(html, "og:url") or source_url

        if og_title or og_desc:
            stable_id = f"fb_og_{hashlib.sha256(og_url.encode('utf-8')).hexdigest()[:16]}"
            author_display = og_title.split(" - ")[0].strip() if (og_title and " - " in og_title) else (og_title or None)
            author_user = author_display.lower().replace(" ", "_") if author_display else None
            items.append({
                "platform": "facebook",
                "content_type": "POST",
                "external_id": stable_id,
                "canonical_url": og_url,
                "author": {
                    "username": author_user,
                    "display_name": author_display
                },
                "text": og_desc or og_title or "",
                "published_at": None,
                "media": [],
                "metrics": None,
                "platform_fields": {"extraction_method": "opengraph_meta"},
                "collected_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
                "parser_version": "1.0.0"
            })
            return items

        # 2. Structural article / post container matching
        article_matches = re.findall(r'<article[^>]*>(.*?)</article>', html, re.DOTALL | re.IGNORECASE)
        for idx, art_html in enumerate(article_matches):
            text_clean = re.sub(r'<[^>]+>', ' ', art_html).strip()
            if len(text_clean) > 10:
                stable_id = f"fb_art_{hashlib.sha256(f'{source_url}_{idx}_{text_clean[:30]}'.encode('utf-8')).hexdigest()[:16]}"
                items.append({
                    "platform": "facebook",
                    "content_type": "POST",
                    "external_id": stable_id,
                    "canonical_url": f"{source_url}#post_{idx}",
                    "author": {
                        "username": None,
                        "display_name": None
                    },
                    "text": text_clean[:500],
                    "published_at": None,
                    "media": [],
                    "metrics": None,
                    "platform_fields": {"extraction_method": "dom_article"},
                    "collected_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
                    "parser_version": "1.0.0"
                })

        return items

    def parse_comments(self, html: str, parent_url: str) -> List[Dict[str, Any]]:
        items = []
        if not html:
            return items

        comment_matches = re.findall(r'<div[^>]*class=["\'][^"\']*comment[^"\']*["\'][^>]*>(.*?)</div>', html, re.DOTALL | re.IGNORECASE)
        for idx, comm_html in enumerate(comment_matches):
            text_clean = re.sub(r'<[^>]+>', ' ', comm_html).strip()
            if len(text_clean) > 5:
                stable_id = f"fb_comm_{hashlib.sha256(f'{parent_url}_{idx}'.encode('utf-8')).hexdigest()[:16]}"
                items.append({
                    "platform": "facebook",
                    "content_type": "COMMENT",
                    "external_id": stable_id,
                    "canonical_url": f"{parent_url}#comment_{idx+1}",
                    "author": {
                        "username": None,
                        "display_name": None
                    },
                    "text": text_clean[:300],
                    "published_at": None,
                    "media": [],
                    "metrics": None,
                    "platform_fields": {"parent_url": parent_url, "extraction_method": "dom_comment"},
                    "collected_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
                    "parser_version": "1.0.0"
                })

        return items

    def _get_meta_content(self, html: str, property_name: str) -> Optional[str]:
        pat = rf'<meta\s+(?:property|name)=["\']{re.escape(property_name)}["\']\s+content=["\'](.*?)["\']'
        match = re.search(pat, html, re.IGNORECASE)
        if match:
            return match.group(1).strip()
        pat_rev = rf'<meta\s+content=["\'](.*?)["\']\s+(?:property|name)=["\']{re.escape(property_name)}["\']'
        match_rev = re.search(pat_rev, html, re.IGNORECASE)
        if match_rev:
            return match_rev.group(1).strip()
        return None
