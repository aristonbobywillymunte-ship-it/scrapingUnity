import os

with open("/Users/unity/Documents/toolsscrapingv1/docs/SYSTEM_ARCHITECTURE.md", "r", encoding="utf-8") as f:
    content = f.read()

scraping_flow = """
## 3. Scraping Request Flow

```mermaid
sequenceDiagram
    participant C as Client (Web/API)
    participant API as API Gateway
    participant RM as Run Manager
    participant Q as Redis Queue
    participant W as Scraper Worker
    participant T as Target Platform

    C->>API: POST /api/v1/instagram/reels/runs
    API->>RM: Validate Auth & Quota
    RM->>RM: Generate Run ID & Reserve Credit
    RM->>Q: Enqueue Tasks
    API-->>C: 202 Accepted (Run ID)
    
    Q->>W: Dequeue Task (Lease)
    W->>W: Select Proxy & Session
    W->>T: Fetch Content
    T-->>W: Response
    W->>W: Extract (Selector) & Normalize
    W->>RM: Deduplicate & Save Results
    RM->>RM: Settle Credits & Mark Completed
```
"""

content = content.replace("## 3. Web Application & Public API", scraping_flow + "\n## 3a. Web Application & Public API")

with open("/Users/unity/Documents/toolsscrapingv1/docs/SYSTEM_ARCHITECTURE.md", "w", encoding="utf-8") as f:
    f.write(content)
