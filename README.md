# Support API

A Laravel 11 API for a multi-tenant customer-support inbox. Businesses receive inbound messages via SMS/WhatsApp webhooks, agents reply through a REST API, and subscribers get real-time updates over a private broadcast channel. Outbound delivery runs asynchronously through a Redis-backed queue.

## Requirements

- PHP 8.2+
- Laravel 11
- Redis (queues + Horizon)
- Pusher (broadcasting)
- Twilio account (SMS gateway)

## Getting started

```bash
# 1. Drop this repo's files into a fresh Laravel 11 skeleton
composer create-project laravel/laravel support-api
cd support-api
# copy app/, database/, routes/, tests/, config/services.php, composer.json, phpunit.xml, .env.example

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Migrate and seed
touch database/database.sqlite   # SQLite works for local dev
php artisan migrate --seed

# 5. Run the test suite
php artisan test

# 6. (Optional) Start the queue worker
php artisan horizon
```

## API

All endpoints except the Twilio webhook require a Sanctum bearer token.

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/conversations` | List conversations (filterable by status, workspace) |
| `GET` | `/api/conversations/{id}` | Get a conversation with its messages |
| `POST` | `/api/conversations/{id}/reply` | Send an agent reply (rate-limited: 60/min) |
| `POST` | `/api/webhooks/twilio/{workspace}` | Twilio inbound webhook (signature-verified) |

## Architecture

### Inbound flow
`POST /webhooks/twilio/{workspace}` → `VerifyTwilioSignature` middleware validates HMAC → `WebhookController` calls `MessageService::recordInbound()` → message is persisted → `MessageCreated` event broadcasts to `private-conversations.{id}` (inside `DB::afterCommit` to avoid broadcasting rolled-back rows).

Inbound ingestion is idempotent: each message carries a unique `external_id` from the provider. A repeat webhook delivery returns the existing row rather than creating a duplicate.

### Outbound flow
`POST /conversations/{id}/reply` → reply row written as `queued` (instant UI feedback) → `DeliverOutboundMessage` job dispatched onto the `outbound` queue → job calls `SmsGateway::send()` → on success the row transitions to `sent`; on `failed()` it is flagged for UI retry.

### Multi-tenancy
Every conversation, contact, and message belongs to a `Workspace`. `ConversationPolicy` enforces workspace isolation — agents can only read and reply within their own workspace.

## Key components

| Component | File | Notes |
|-----------|------|-------|
| Models | `app/Models/` | Relationships, query scopes, JSON casting |
| SMS gateway | `app/Services/TwilioSmsGateway.php` | Implements `SmsGateway` interface |
| Fake gateway | `app/Services/FakeSmsGateway.php` | Swapped in during tests — no credentials needed |
| Outbound job | `app/Jobs/DeliverOutboundMessage.php` | Retries, backoff, unique lock, `failed()` hook |
| Event | `app/Events/MessageCreated.php` | Broadcasts on `private-conversations.{id}` |
| Middleware | `app/Http/Middleware/VerifyTwilioSignature.php` | `hash_equals` constant-time HMAC check |
| Policy | `app/Policies/ConversationPolicy.php` | Workspace isolation + reply rules |
| Command | `app/Console/Commands/CloseStaleConversations.php` | Chunked mass-update, `--dry-run` flag, scheduled daily |

## Environment variables

| Variable | Description |
|----------|-------------|
| `TWILIO_ACCOUNT_SID` | Twilio account SID |
| `TWILIO_AUTH_TOKEN` | Used to verify webhook signatures |
| `TWILIO_FROM_NUMBER` | Sender phone number |
| `PUSHER_APP_ID` | Pusher app credentials |
| `PUSHER_APP_KEY` | |
| `PUSHER_APP_SECRET` | |
| `QUEUE_CONNECTION` | Set to `redis` for async delivery (default: `sync`) |

See `.env.example` for the full list.

## Testing

Tests use Pest. The suite covers inbound webhook idempotency, agent reply authorization and validation, outbound job delivery (with the fake gateway), and conversation query scopes.

```bash
php artisan test
# or
./vendor/bin/pest
```

## License

MIT
