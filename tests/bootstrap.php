<?php
/**
 * PHPUnit bootstrap.
 *
 * @package QueryNova
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/Support/wordpress/' );
define( 'QUERYNOVA_VERSION', '0.1.3' );
define( 'QUERYNOVA_FILE', dirname( __DIR__ ) . '/querynova.php' );
define( 'QUERYNOVA_PATH', dirname( __DIR__ ) . '/' );
define( 'QUERYNOVA_URL', 'http://example.test/wp-content/plugins/querynova/' );
define( 'QUERYNOVA_DB_VERSION', '1.0.0' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WEEK_IN_SECONDS', 604800 );

require dirname( __DIR__ ) . '/vendor/autoload.php';
require __DIR__ . '/Support/wp-stubs.php';
