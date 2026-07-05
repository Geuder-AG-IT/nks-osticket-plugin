# NKS osTicket API

A clean, single-endpoint JSON API for [osTicket](https://osticket.com/) — built
for integrations and AI agents. One web entry, one API key model, fast reads.

It exposes the full ticket surface (list, search, get, create, update, reply,
delete), statistics, statuses, parent/child subtickets and attachment download
through a single JSON-RPC endpoint.

## Why

osTicket has no first-class REST API for automation. This plugin adds one
without touching core: a single web entry boots osTicket, authenticates against
osTicket's own API keys, and dispatches a flat `action` string to a handler.

Read paths use the ORM's `->values()` projection instead of hydrating full
`Ticket` models — model hydration through osTicket's auto-joined QuerySet is
pathologically slow on large ticket tables, so lists and searches stay fast.

## Endpoint

```
POST /api/nks-osticket.php
Header: X-Api-Key: <your osTicket API key>
Body:   { "action": "tickets.search", "params": { "query": "invoice", "limit": 10 } }
```

Read-only actions also accept `GET` with query parameters:

```
GET /api/nks-osticket.php?action=tickets.list&status=open&limit=5
```

### Response envelope

```json
{ "ok": true,  "data": { ... } }
{ "ok": false, "error": { "code": 404, "message": "Ticket not found" } }
```

## Actions

| Action | Params | Permission (OR) |
|---|---|---|
| `meta.ping` | – | (valid key) |
| `tickets.list` | `status?`, `department?`, `limit?`, `offset?`, `sort?` | search / read |
| `tickets.search` | `query`, `status?`, `limit?`, `offset?`, `sort?` | search / read |
| `tickets.get` | `number` \| `id` | read |
| `tickets.stats` | – | read stats / read |
| `tickets.statuses` | – | read stats / read |
| `tickets.create` | `subject`, `message`, `name?`, `email?`, `topicId?`, `priority?` | create |
| `tickets.update` | `number`, `status?`, `dueDate?`, `topicId?`, `note?` | update |
| `tickets.reply` | `number`, `response`, `title?`, `alert?` | reply |
| `tickets.delete` | `number` | delete |
| `subtickets.link` | `parent`, `child` | manage subtickets |
| `subtickets.unlink` | `child` | manage subtickets |
| `subtickets.children` | `number` | manage / read |
| `subtickets.parent` | `number` | manage / read |
| `attachments.download` | `fileId` | read |

`sort` is one of `created` (default, newest first), `updated`, `number`.
`status` accepts canonical states (`open`, `closed`, `resolved`, `archived`) or
a numeric status id.

## Authentication

Uses osTicket's native API keys (**Admin Panel → Manage → API Keys**). Each key
is bound to a source IP and carries granular permission columns
(`can_read_tickets`, `can_search_tickets`, `can_create_tickets`,
`can_update_tickets`, `can_reply_tickets`, `can_delete_tickets`,
`can_read_stats`, `can_manage_subtickets`). On an IP mismatch the API returns
the IP it observed to make key setup painless.

## Install

```bash
OSTICKET_WEB=/path/to/osticket/upload ./deploy.sh
# then chown the copied files to your web user
```

This installs:

- `include/plugins/nks-osticket-api/` — plugin code
- `api/nks-osticket.php` — the single web entry
- a bypass rule in `api/.htaccess` so osTicket's dispatcher doesn't intercept it

Registering/activating the plugin in the admin panel is optional; the API works
as soon as the files are in place.

## License

MIT — see [LICENSE](LICENSE).
