#!/bin/sh
# Exécuté au démarrage du conteneur, après les migrations : crée le premier administrateur
# si ADMIN_EMAIL et ADMIN_PASSWORD sont définis et qu'aucun administrateur n'existe.
if [ "${AUTORUN_ENABLED:-false}" = "true" ]; then
    php /var/www/html/artisan users:ensure-admin || echo "users:ensure-admin a échoué (le démarrage continue)"
fi
