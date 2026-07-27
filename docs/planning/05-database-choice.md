# Database Choice

Context that shapes this decision (from planning answers): self-hosted on a **single small VPS via Docker Compose**, well under 50 users, Laravel backend, a `media_items.metadata` JSON column for type-specific fields (see [04-data-model.md](04-data-model.md)), and reviews/titles that benefit from text search.

## Candidates considered

### PostgreSQL

**Pros**
- **JSONB** — the `metadata` column on `media_items` gets a real indexed, queryable JSON type. If a later feature needs "find all games on PS5" or similar, that's a straightforward JSONB query, not a workaround.
- Strong built-in full-text search (`tsvector`/`tsquery`) — useful for searching titles and review text without adding a separate search service (e.g. Elasticsearch) that would be overkill at this scale.
- Laravel has first-class support (migrations, Eloquent casts for JSON, etc.) — no friction.
- Correct, strict-by-default behavior (e.g. stricter type checking) tends to catch schema/data mistakes earlier than MySQL's historically looser defaults.
- Free, open source, runs fine in a small Docker container on a modest VPS.

**Cons**
- One more container/process to run and back up compared to SQLite (though this is true of MySQL too).
- Slightly less universally "the default" in shared-hosting/tutorial contexts than MySQL, though irrelevant for a Docker Compose self-hosted setup.

### MySQL / MariaDB

**Pros**
- Historically *the* Laravel default — the most tutorials, Stack Overflow answers, and existing familiarity if you've used Laravel before.
- Perfectly capable for this scale; plenty of production Laravel apps run on it.
- MariaDB in particular is lightweight and well-suited to small VPS deployments.

**Cons**
- JSON support is functional but weaker than Postgres's JSONB (no equivalent indexing story without generated columns as a workaround).
- Full-text search exists but is less capable out of the box than Postgres's, and behaves differently between InnoDB configurations.
- No decisive advantage over PostgreSQL for this project's needs — mainly a "more familiar" choice, not a technically better one here.

### SQLite

**Pros**
- **Zero extra container.** The entire database is one file — simplest possible ops story for a single-VPS, low-traffic app: back it up by copying a file.
- Laravel supports it well; migrations and Eloquent work the same as any other driver.
- At "well under 50 users" scale with a small friend group, SQLite's concurrency limits (one writer at a time) are very unlikely to actually be hit in practice.

**Cons**
- Weaker JSON querying than Postgres — the `metadata` column would work but without JSONB's indexing/operator ergonomics.
- No real full-text search without the FTS5 extension configured separately, and it's a bit more manual to wire into Laravel than Postgres's built-in text search.
- Schema changes (`ALTER TABLE`) are more limited — some migrations that are trivial in Postgres/MySQL require table-rebuild workarounds in SQLite. Laravel's schema builder smooths over a lot of this, but not all of it.
- Growing the group later (more users, more usage) means an eventual migration off SQLite is more likely than with Postgres/MySQL, which scale further before that's necessary.

## Recommendation: PostgreSQL

Given the stack is already Laravel + Docker Compose (so "one more container" is a non-issue — nginx, PHP-FPM, and a queue worker are already separate containers), PostgreSQL's JSONB and full-text search directly pay off for two features already in the v1 plan (flexible per-type metadata, and searching titles/reviews) without pulling in extra infrastructure. It costs nothing meaningful over MySQL at this scale and gives more room to grow than SQLite without adding operational burden on a single VPS.

**If you want the absolute simplest ops story and are confident the group will stay small** (a handful of users, low write volume), SQLite is a legitimate alternative and would simplify [08-architecture-deployment.md](08-architecture-deployment.md) by removing a container and reducing backups to a file copy. It's flagged here as the fallback option rather than the primary recommendation because the JSON metadata and search use cases are already part of the v1 feature set, not hypothetical future needs.

MySQL/MariaDB isn't ruled out for any strong technical reason — it's excluded mainly because Postgres does everything MySQL does here plus JSONB/search better, with equivalent operational cost in a Docker Compose setup.
