# Support API (demo)

A compact, production-shaped **Laravel** API for a customer-support inbox: businesses receive
messages from customers across channels (web, SMS, WhatsApp), agents reply, and everything
updates in real time. Inbound messages arrive via provider webhooks; outbound replies are
delivered asynchronously through a Redis queue.

I built this as a focused demonstration piece. My substantial Laravel work lives in private
company repositories, so rather than link to something you can't read, this is a small,
self-contained codebase where every file is deliberate and readable in ten minutes. It leans
toward *illustrating the patterns clearly* rather than being feature-complete.

## Why this exists / what it demonstrates

The domain is deliberately close to a real support-chat product, so the engineering choices map
onto problems that actually come up there: idempotent webhook ingestion, async delivery with
retries, real-time fan-out, multi-tenant authorization, and query patterns that hold up as tables
grow.

## Feature map

| Area                                                                | Where to look                                                                                                                                                                                       |
| ------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Eloquent ORM** — relationships, query scopes, JSON casting | `app/Models/Conversation.php`, `app/Models/Message.php`                                                                                                                                         |
| **JSON-based queries** (`metadata->key`)                    | `Conversation::scopeWhereMetadata()` + `tests/Unit/ConversationScopeTest.php`                                                                                                                   |
| **Indexing / query optimization**                             | migrations in`database/migrations/` — composite indexes chosen to match real access patterns (see comments); eager loading + `withCount` in `ConversationController::index` to avoid N+1     |
| **Queues & background jobs** (Redis + Horizon)                | `app/Jobs/DeliverOutboundMessage.php` — retries, backoff, timeout, unique lock; dispatched onto the `outbound` queue                                                                           |
| **Real-time / event broadcasting** (Pusher + Echo)            | `app/Events/MessageCreated.php`, channel auth in `routes/channels.php`                                                                                                                          |
| **Service Providers & container bindings**                    | `app/Providers/AppServiceProvider.php` — binds `SmsGateway` per environment, wires middleware dependency                                                                                       |
| **Policies & Gates**                                          | `app/Policies/ConversationPolicy.php` — workspace isolation, reply rules                                                                                                                         |
| **Middleware**                                                | `app/Http/Middleware/VerifyTwilioSignature.php` — HMAC webhook verification                                                                                                                      |
| **Custom Artisan commands**                                   | `app/Console/Commands/CloseStaleConversations.php` — chunked mass-update, `--dry-run`, scheduled in `routes/console.php`                                                                     |
| **Third-party integration** (Twilio)                          | `app/Services/TwilioSmsGateway.php` behind an `SmsGateway` interface, with `FakeSmsGateway` for tests                                                                                         |
| **Security**                                                  | signature verification (`hash_equals`, constant-time), Sanctum token auth, per-user rate limiting on replies, form-request validation, `firstOrCreate` + unique `external_id` for idempotency |
| **Testing** (Pest, service mocking)                           | `tests/Feature/`, `tests/Unit/` — HTTP flow, authorization, validation, webhook idempotency, and a job test that swaps the gateway in the container                                            |

## Design notes worth calling out

- **Idempotent ingestion.** Twilio retries webhooks on timeout, so the same message can arrive
  twice. `external_id` is unique and `recordInbound()` returns the existing row on a repeat, so a
  retry never creates a duplicate. (Same discipline I've used for payment-gateway webhooks.)
- **Async delivery, immediate feedback.** An agent reply is written as `queued` synchronously so
  the UI responds instantly; the actual provider send happens on the queue with retries and a
  `failed()` hook that flags the row so the UI can offer a retry.
- **Broadcast after commit.** Inbound broadcasting is wrapped in `DB::afterCommit` so subscribers
  never see a message a rolled-back transaction would have erased.
- **Indexes match access patterns, not columns.** The inbox filters by `(workspace_id, status)`
  and sorts by `last_message_at`; there's a composite index for exactly that so the list view
  never falls back to a filesort as the table grows.
- **Provider behind an interface.** Nothing in the domain knows about Twilio directly — it depends
  on `SmsGateway`. Local/test use `FakeSmsGateway`, so the suite runs with no credentials and sends
  nothing real.

## Running it locally

This repo contains application code only (no `vendor/`, no framework runtime). To run it, drop it
into a fresh Laravel 11 skeleton — takes about a minute:

```bash
# 1. Create a fresh Laravel app
composer create-project laravel/laravel support-api-demo
cd support-api-demo

# 2. Copy this repo's app/, database/, routes/, tests/, config/services.php,
#    composer.json (merge the extra requires), phpunit.xml, and .env.example over it.

# 3. Add the packages this project uses
composer require laravel/horizon laravel/sanctum pusher/pusher-php-server
composer require --dev pestphp/pest pestphp/pest-plugin-laravel

# 4. Configure env + key
cp .env.example .env
php artisan key:generate

# 5. Migrate + seed (sqlite works out of the box)
touch database/database.sqlite
php artisan migrate --seed

# 6. Run the test suite
php artisan test
```

For the full experience: point `QUEUE_CONNECTION=redis` at a Redis instance and run
`php artisan horizon`; set the Pusher vars to see `message.created` broadcast to
`private-conversations.{id}`.

## What I'd add for production (deliberately out of scope here)

Full-text search over message bodies; read receipts and typing indicators over the same broadcast
channel; a proper multi-tenant workspace resolver on the webhook (currently keyed off the route);
per-workspace rate limits; and OpenTelemetry tracing across the ingest → queue → deliver path.

---

Built by Lolade Wahab. Questions or a walkthrough of the private production work I've done with
these patterns — happy to jump on a call.
