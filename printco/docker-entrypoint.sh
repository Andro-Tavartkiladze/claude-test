#!/bin/sh
# Starts the daily recurring-billing job alongside the PHP web server in one
# container. For production on Fly.io you may instead run charge_due.php from a
# dedicated scheduled machine (see README-subscriptions.md) and drop this loop.
set -e

# Background: run the billing scheduler once a day.
(
  while true; do
    php /srv/http/cron/charge_due.php >> /srv/http/data/billing.log 2>&1 || true
    sleep 86400
  done
) &

# Foreground: serve the site. router falls back to the requested PHP/static file.
exec php -S 0.0.0.0:8080 -t /srv/http
