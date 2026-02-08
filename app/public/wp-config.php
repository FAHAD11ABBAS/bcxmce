<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', getenv('DB_NAME') ?: 'local' );

/** Database username */
define( 'DB_USER', getenv('DB_USER') ?: 'root' );

/** Database password */
define( 'DB_PASSWORD', getenv('DB_PASSWORD') ?: 'root' );

/** Database hostname */
define( 'DB_HOST', getenv('DB_HOST') ?: 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'wFR Y+#-C7bAR,9Yl~-avle):d1-@zfJ{tX>$pXsDl:;e;gI8DrA:g2lv]ZS;dAO' );
define( 'SECURE_AUTH_KEY',   'p5zcJ2,P50~mk$lk7{{4-e!&.6@qqj6N$YXWfs[tOhiBE0bYoqK/f{q+%F}vsU(~' );
define( 'LOGGED_IN_KEY',     '#o5bCrSw7Gu!n*$e@n;3c#icLLBZ1*,xG[%l$On3<$MZDbCd<EL1Qx3D>%2mVK1Q' );
define( 'NONCE_KEY',         '7y1{DN1ySA]3*Jw)xPQh6U).d+uyd^)8QqJ|{QHgK%nc!RT*~^%oKqS_ZPqm+FL-' );
define( 'AUTH_SALT',         'fi]x3_0mUf~9x;q#+/)GPqQ9@}-nkn;BUzmuNd*c7 ~uv/I]#L$Z8Wb_2IzxNKZW' );
define( 'SECURE_AUTH_SALT',  'KI?@EyGXYcp+ndC%`3;&oE~8)+iZuk*dCDuZ62XO{J4O:#H:f1&18fEtNL?iFl D' );
define( 'LOGGED_IN_SALT',    '~/}8bF9eps^q%W{!,L4y]!9K%Kcuz4MkJV:$ys3dzJ+a|YE?@S{+jEV]>n;5FLjS' );
define( 'NONCE_SALT',        'eLw|Y+u)I6Vct `u3a)b$/3cT5p|]pQz-kF+!$ 8mb;83)en?De1`pS93rj-z6Iy' );
define( 'WP_CACHE_KEY_SALT', 'TU`{44*E)6PPM-h(Z0LE/x$Ny^Dl|JEaes_g8{:WaWn($xT-0a#LZb0]SC>MwJR]' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Auto-configure URLs for GitHub Codespaces */
if ( getenv('CODESPACES') === 'true' ) {
    $codespace_name = getenv('CODESPACE_NAME');
    $codespace_domain = getenv('GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN');
    $url = "https://$codespace_name-8000.$codespace_domain";
    
    if ( ! defined('WP_HOME') ) define( 'WP_HOME', $url );
    if ( ! defined('WP_SITEURL') ) define( 'WP_SITEURL', $url );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
