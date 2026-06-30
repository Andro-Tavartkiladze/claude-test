# PrintCo with the subscription / recurring-billing system.
# Runs PHP (pdo_sqlite is bundled in the official image) and a daily billing job.
FROM php:8.4-cli

WORKDIR /srv/http
COPY . /srv/http/

# The SQLite db lives in /srv/http/data — keep it writable.
RUN mkdir -p /srv/http/data && chmod -R 775 /srv/http/data

EXPOSE 8080

# entrypoint starts the daily charge loop in the background and the web server.
RUN chmod +x /srv/http/docker-entrypoint.sh
CMD ["/srv/http/docker-entrypoint.sh"]
