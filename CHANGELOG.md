# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-07-07

### Added
- Thread entries in `tickets.get` now carry an `attachments` array with
  `fileId`, `name`, `type`, `size`, `inline` and `cid`. `fileId` is the id
  `attachments.download` expects and `cid` matches the `cid:<...>` markers
  inline images use in the entry body — attachments were previously
  undiscoverable, so downloads were impossible without it.

## [1.0.0] - 2026-07-05

### Added
- Single JSON-RPC endpoint (`POST /api/nks-osticket.php`) exposing the full
  ticket surface through one deploy file.
- Actions: `tickets.list`, `tickets.search`, `tickets.get`, `tickets.stats`,
  `tickets.statuses`, `tickets.create`, `tickets.update`, `tickets.reply`,
  `tickets.delete`, `subtickets.link`, `subtickets.unlink`,
  `subtickets.children`, `subtickets.parent`, `attachments.download`,
  `meta.ping`.
- API-key authentication (osTicket API keys) with source-IP binding and
  granular per-action permissions.
- Fast read paths using ORM `->values()` projections (no full model
  hydration) — safe on large ticket tables.
- Native parent/child subtickets via `ticket_pid` (no third-party plugin).
