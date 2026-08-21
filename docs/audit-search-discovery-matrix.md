# GAP AUDIT: Search & Discovery Input Contracts across 13 Capabilities

| # | Capability | Content Type | Role | Discovery Mode | Current UI Input | Required Target / Input Contract | Physically Supported | Status | Gap Identified |
|---|---|---|---|---|---|---|---|---|---|
| 1 | `facebook_posts` | Post | Discovery | `search_query`, `hashtag`, `target_url` | generic `target_url` | Query / Hashtag / Profile URL | Yes (Post Collector) | PARTIAL | UI only exposes generic target_url; missing mode selector (Kata Kunci / Hashtag / Target). |
| 2 | `facebook_comments` | Comment | Child / Parent | `parent_target` | generic `target_url` | Parent Post URL or ID | Yes (Parent URL) | PARTIAL | UI should enforce "Target Konten" (Parent Post URL/ID) exclusively. |
| 3 | `instagram_posts` | Post | Discovery | `search_query`, `hashtag`, `target_url` | generic `target_url` | Query / Hashtag / Profile URL | Yes (Post Collector) | PARTIAL | Missing dynamic mode toggle in UI and payload normalization. |
| 4 | `instagram_reels` | Reel / Video | Discovery | `search_query`, `hashtag`, `target_url` | generic `target_url` | Query / Hashtag / Profile URL | Yes (Reel Collector) | PARTIAL | Missing dynamic mode toggle in UI and payload normalization. |
| 5 | `instagram_comments` | Comment | Child / Parent | `parent_target` | generic `target_url` | Parent Post/Reel URL or ID | Yes (Parent URL) | PARTIAL | UI should enforce "Target Konten" exclusively; reject raw keyword/hashtag. |
| 6 | `tiktok_videos` | Video | Discovery | `search_query`, `hashtag`, `target_url` | generic `target_url` | Query / Hashtag / Profile URL | Yes (Video Collector) | PARTIAL | Missing dynamic mode toggle in UI and payload normalization. |
| 7 | `tiktok_comments` | Comment | Child / Parent | `parent_target` | generic `target_url` | Parent Video URL or ID | Yes (Parent URL) | PARTIAL | UI should enforce "Target Konten" exclusively; reject raw keyword/hashtag. |
| 8 | `youtube_videos` | Video | Discovery | `search_query`, `hashtag`, `target_url` | generic `target_url` | Query / Hashtag / Channel URL | Yes (Video Collector) | PARTIAL | Missing dynamic mode toggle in UI and payload normalization. |
| 9 | `youtube_comments` | Comment | Child / Parent | `parent_target` | generic `target_url` | Parent Video URL or ID | Yes (Parent URL) | PARTIAL | UI should enforce "Target Konten" exclusively; reject raw keyword/hashtag. |
| 10 | `x_posts` | Post / Tweet | Discovery | `search_query`, `hashtag`, `target_url` | generic `target_url` | Query / Hashtag / Account URL | Yes (Post Collector) | PARTIAL | Missing dynamic mode toggle in UI and payload normalization. |
| 11 | `x_replies` | Reply / Thread | Child / Parent | `parent_target` | generic `target_url` | Parent Tweet/Thread URL or ID | Yes (Parent URL) | PARTIAL | UI should enforce "Target Konten" exclusively; reject raw keyword/hashtag. |
| 12 | `news_articles` | News Article | Discovery / Web | `search_query`, `target_url` | generic `target_url` | Query / Source URL (Hashtag: UNSUPPORTED) | Yes (News Collector) | PARTIAL | Hashtags are not applicable to News; must expose Keyword Query or Source URL. |
| 13 | `web_pages` | Web Page | Web Crawl / Direct | `target_url` | generic `target_url` | Target URL (Hashtag/Search: UNSUPPORTED) | Yes (Web Collector) | PARTIAL | Direct URL crawl; social search/hashtag must be marked UNSUPPORTED. |
