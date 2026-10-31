#!/bin/sh
# Start of the production image (Dockerfile): prepare the database, then serve the app.
# A database that is not ready yet does not stop the site: deploy waits for it, then Apache starts anyway.
cd /var/www/html || exit 1
php caligrafer.php deploy --wait "${CALIGRAFY_DEPLOY_WAIT:-60}" || echo "caligrafer deploy: the database is not prepared (see above)"
exec apache2-foreground
