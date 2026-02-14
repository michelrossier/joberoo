# Laravel Cloud Queue Worker Setup

This app sends emails through queued notifications (`QUEUE_CONNECTION=database`).
Without a running worker, no outbound emails will be sent.

## Required command

```bash
php artisan queue:work database --queue=default --sleep=1 --tries=3 --backoff=10 --timeout=120 --max-time=3600
```

## Laravel Cloud setup

1. Open your application in Laravel Cloud.
2. Go to **Resources** -> **Daemons / Queue Workers**.
3. Add a worker with the command above.
4. Start with **1 replica**.
5. Scale to **2+ replicas** if queue latency rises.

## Verification

1. Trigger an email flow in the app.
2. Confirm worker logs show jobs being processed.
3. Confirm rows are created in `email_messages` and webhook events in `email_message_events`.
