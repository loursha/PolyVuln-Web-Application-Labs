#!/bin/sh
set -e
# Wait for MySQL, load schema on first boot, generate invoices, then start Apache.
php /var/www/html/scripts/bootstrap.php || true
exec apache2-foreground
