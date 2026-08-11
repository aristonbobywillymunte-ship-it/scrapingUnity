# FACEBOOK POC PHASE A: FEASIBILITY / POLICY GATE

## 1. Executive Summary
This document summarizes the Phase A Feasibility and Policy Gate review for the Facebook Platform #1 Proof of Concept. The review evaluates whether the owner-approved capabilities (`profile`, `single_post`, `profile_posts`, `replies`, `search_posts`) can be safely and legally collected via automated HTTP/Browser paths without violating Meta's Terms of Service or requiring prohibited bypass techniques. Based on official Meta documentation, all unauthorized automated data collection is strictly prohibited, and direct public web paths are heavily restricted by login walls, resulting in a `BLOCKED` status for all unauthorized web scraping paths.

## 2. Scope
- Platform: Facebook
- Operations: `profile`, `single_post`, `profile_posts`, `replies`, `search_posts`
- Target Types: `username`, `url`, `id`, `post_id`, `comment_id`, `keyword`, `hashtag`
- Scope: Public data only via HTTP-first architecture without using prohibited evasion techniques.

## 3. Method
- Review of official Meta Terms of Service and Automated Data Collection Terms.
- Review of Meta Graph API documentation.
- Assessment of direct public web path feasibility against project security and no-bypass constraints.

## 4. Sources Reviewed
1. Meta Terms of Service (Official Source, meta.com)
2. Meta Automated Data Collection Terms (Official Source, facebook.com)
3. Meta Graph API Documentation (Official Source, developers.facebook.com)

## 5. Policy / Terms Findings
- **Automated Data Collection Restrictions:** Meta's Terms of Service explicitly prohibit accessing or collecting data using automated means (bots, spiders, scrapers) without express written permission. (OFFICIAL SOURCE)
- **Login Status:** The prohibition applies regardless of whether the collection is attempted anonymously or while logged in. (OFFICIAL SOURCE)
- **Approved APIs:** Meta designates the Platform APIs (Graph API) as the only authorized means for programmatic access, which requires strict App Review. (OFFICIAL SOURCE)

## 6. Official Meta/API Capability Findings
- **Official Path:** Graph API.
- **Authentication:** Requires user access tokens or Page access tokens.
- **App Permissions:** Requires advanced access and strict App Review (e.g., Page Public Content Access).
- **Use Case Fit:** The Graph API does not align well with a general-purpose, anonymous public-data scraping service, as Meta typically rejects apps attempting to act as data brokers or bulk public data scrapers without direct user relationships.

## 7. Direct Public Web Findings
- **Anonymous Public Access:** Technically, some public pages/posts may occasionally render without a login, but Facebook aggressively deploys login walls (forcing users to log in to view content). (OBSERVATION)
- **Automation Permitted:** No. Automation is explicitly prohibited by TOS. (OFFICIAL SOURCE)
- **Login/Challenge Gating:** Facebook commonly gates capability behind login walls, challenge pages, or CAPTCHAs, which the project's safety boundary strictly forbids bypassing. (OBSERVATION)

## 8. profile Feasibility
- **Official API:** Available for Pages (with App Review). Highly restricted for personal profiles.
- **Direct Web:** Often blocked by login walls.
- **Bypass Required:** Defeating the login wall requires prohibited session management or stealth browsers.

## 9. single_post Feasibility
- **Official API:** Available for Page posts (with App Review).
- **Direct Web:** Heavily restricted by login walls or structural obfuscation.
- **Bypass Required:** Requires prohibited evasion of challenge/login walls.

## 10. profile_posts Feasibility
- **Official API:** Available for Pages.
- **Direct Web:** Pagination is heavily rate-limited and login-gated.
- **Bypass Required:** Requires prohibited evasion of rate limits and login walls.

## 11. replies Feasibility
- **Official API:** Available for Page post comments.
- **Direct Web:** Expanding comment trees anonymously is nearly impossible without triggering login walls.
- **Bypass Required:** Requires prohibited evasion of login walls.

## 12. search_posts — keyword Feasibility
- **Official API:** General public post search by keyword is largely deprecated/unavailable in the Graph API for third parties.
- **Direct Web:** Search functionality is strictly gated behind authenticated user sessions.
- **Bypass Required:** Requires a logged-in session, which violates the anonymous public data boundary and risks account banning (prohibited account farming).

## 13. search_posts — hashtag Feasibility
- **Official API:** Hashtag search is limited or available only for specific API products (like Instagram Graph API, less so for Facebook).
- **Direct Web:** Similar to keyword search, it is gated by login walls.
- **Bypass Required:** Requires prohibited authenticated sessions.

## 14. Page vs Personal Profile Distinction
- **Page:** Meta provides official API access for Pages via Page Public Content Access (requires App Review). Direct web access is sometimes possible but subject to login walls.
- **Personal Profile:** Meta heavily restricts access to personal profiles through both the API and direct web. Scraping personal profiles is strictly prohibited and technically blocked.
- **Owner Decision Required:** Does the owner wish to pursue official App Review for Page Public Content Access via Graph API, abandoning the direct web scraping approach for Facebook?

## 15. Authentication / Permission Requirements
- **Official API:** Requires Meta App Review, Business Verification, and specific permissions (e.g., `pages_read_usercontent`).
- **Direct Web:** Requires a user session to bypass login walls, which violates the no-bypass rule.

## 16. Challenge / Access Restriction Findings
- Facebook aggressively uses Rate Limiting, IP blocks, CAPTCHAs, and Login Walls for anomalous or automated traffic.
- Project rules dictate a hard stop (`CHALLENGE_PRESENT`, `AUTH_REQUIRED`, `ACCESS_RESTRICTED`) upon encountering these, meaning collection will fail immediately without prohibited proxy rotation or session spoofing.

## 17. Capability Matrix
| Operation | Target Type | Official/Approved Path | Anonymous Public Web Path | Auth Required | Policy Gate | Technical Feasibility | POC Gate |
| --------- | ----------- | ---------------------- | ------------------------- | ------------- | ----------- | --------------------- | -------- |
| profile | username | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| profile | url | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| profile | id | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| single_post | url | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| single_post | post_id | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| profile_posts | username | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| profile_posts | url | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| profile_posts | id | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| replies | url | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| replies | post_id | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| replies | comment_id | Graph API (Pages) | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| search_posts | keyword | Deprecated | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |
| search_posts | hashtag | Restricted | Login-walled | Yes | BLOCKED | BLOCKED | BLOCKED |

## 18. Security / No-Bypass Boundary
All unauthenticated automated data collection against Facebook's direct web path triggers login walls or challenge pages. Bypassing these requires fake accounts, stealth browsers, or stolen cookies—all of which are strictly forbidden by the project's security and no-bypass boundaries. Thus, the technical feasibility is `BLOCKED`.

## 19. Policy / Commercial Activation Caveat
Commercial/platform connector activation requires completion of the approved platform policy/legal review gate and explicit owner authorization. Meta's official Automated Data Collection Terms explicitly prohibit this architecture without written permission.

## 20. Open Owner Decisions
`OWNER DECISION — Facebook remains a direct-web scraper. Official Meta API paths are out of scope.`
The owner has explicitly directed that Facebook Platform #1 will use direct web scraping. Official Meta API research remains above as historical/contextual evidence of known policy risks, but official APIs are not part of this connector. Prohibited bypass behavior rules remain strictly unchanged. 

## 21. Phase A Gate Result
`Direct-web strategy selected by owner.`
Individual technical capability support will be determined by the POC. Live activity prohibited by applicable policy/legal constraints remains gated; bypasses are strictly forbidden.

**Phase A progression result: PASS_TO_OFFLINE_POC**
- owner selected direct web scraping
- policy/access risks remain documented
- no permission for live scraping is implied
- Phase B offline fixture/contract work is allowed
- live validation remains separately gated
