SCRIPTS_DIRECTORY="/var/www/html/plugins/MonduPayment/shopscripts/"

php "${SCRIPTS_DIRECTORY}install.php"
php "${SCRIPTS_DIRECTORY}config.php"
php "${SCRIPTS_DIRECTORY}configShipping.php"

echo "Activation completed"
