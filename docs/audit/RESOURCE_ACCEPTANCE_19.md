# PRD Resource Acceptance #19

## Exact Wording
`VPS 4 GB tidak mengalami OOM pada load target pilot.`

## Date / Time
- 2026-08-22 19:54-20:00 Asia/Makassar

## Commit Tested
- `HEAD`: `07fa087c50085a3c31de944d3e088fe00b51213f`
- `origin/main`: `07fa087c50085a3c31de944d3e088fe00b51213f`

## Environment
- Docker Desktop 4.82.0
- Docker Engine 29.6.1
- `docker compose` v5.3.0
- Repo path: `/Users/unity/Documents/ChatGPT/scraping/repo`

## Docker Compose Services
- `app`
- `postgres`
- `redis`
- `queue`
- `scheduler`
- `python_http_worker`
- `python_browser_worker`
- `python_validator_worker`
- `web` exists in compose but was not started because host port `8000` was already occupied by another container

## Workload Definition
- Controlled startup of the compose stack
- Live Python suite against the running stack
- Live validator worker with Redis heartbeat/queue consumer
- Live Postgres and Redis services

## Commands
- `docker version`
- `docker compose config`
- `docker compose up -d --build`
- `docker compose up -d --build python_validator_worker`
- `docker compose up -d --build postgres`
- `docker compose ps`
- `docker stats --no-stream --format 'table {{.Name}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.CPUPerc}}' repo-app-1 repo-python_http_worker-1 repo-python_browser_worker-1 repo-python_validator_worker-1 repo-postgres-1 repo-redis-1`
- `docker inspect <container> --format '{{json .State}}'`
- `docker inspect <container> --format '{{.RestartCount}}'`
- `APP_ENV=testing PYTHONPATH=python_scraper python_scraper/venv/bin/pytest python_scraper/tests/`

## Memory Samples
| Phase | app | http worker | browser worker | validator worker | postgres | redis |
| --- | --- | --- | --- | --- | --- | --- |
| baseline | 11.64MiB / 1GiB | 30.99MiB / 512MiB | 24.28MiB / 768MiB | 21.88MiB / 256MiB | 31.06MiB / 512MiB | 24.33MiB / 256MiB |
| in-flight | 11.64MiB / 1GiB | 30.99MiB / 512MiB | 24.28MiB / 768MiB | 21.88MiB / 256MiB | 32.72MiB / 512MiB | 24.33MiB / 256MiB |
| after | 11.64MiB / 1GiB | 30.99MiB / 512MiB | 24.28MiB / 768MiB | 21.88MiB / 256MiB | 32.71MiB / 512MiB | 24.33MiB / 256MiB |

## Peak Memory Per Service
- app: 11.64MiB
- python_http_worker: 30.99MiB
- python_browser_worker: 24.28MiB
- python_validator_worker: 21.88MiB
- postgres: 32.72MiB
- redis: 24.33MiB

## OOMKilled Before / After
- app: `false / false`
- python_http_worker: `false / false`
- python_browser_worker: `false / false`
- python_validator_worker: `false / false`
- postgres: `false / false`
- redis: `false / false`

## Restart Count Before / After
- app: `0 / 0`
- python_http_worker: `0 / 0`
- python_browser_worker: `0 / 0`
- python_validator_worker: `0 / 0`
- postgres: `0 / 0`
- redis: `0 / 0`

## Browser Concurrency Proof
- Compose config constrains browser worker to a single service instance.
- Runtime showed one `python_browser_worker` container only.
- No second browser worker container was started.

## Worker Health
- `python_validator_worker` running with heartbeat log
- `python_http_worker` running
- `python_browser_worker` running
- `postgres` running
- `redis` running
- No OOMKilled containers
- No unexpected restarts during the sampled pilot window

## Limitations
- The controlled pilot did not include a browser-queue execution trace or a heavyweight end-to-end workload representative of the full 4 GB VPS ceiling.
- The `web` service could not be started in this environment because host port `8000` was already occupied by another container.
- The evidence collected shows bounded runtime behavior and healthy services, but not a worst-case load pilot.

## Final Verdict
- `UNPROVEN`
