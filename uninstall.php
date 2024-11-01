<?php
namespace NMod\SmartCookieKit;

// Exit if direct access
if ( ! \defined( 'ABSPATH' ) ) exit;

if ( ! \defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

include_once 'plugin_options.php';

// Delete backend notices status
\delete_option( 'SmartCookieKit_AdminNotices' );

// Delete plugin configuration
\delete_option( 'SmartCookieKit_Options'    );
\delete_option( 'SmartCookieKit_Options_v2' );


// Delete translations, if any
foreach ( \get_posts( array(
  'post_type'          => Options::BannerPostType,
  'posts_per_page'     => -1,
  'post_status'        => 'any',
  'lang'               => '',
) ) as $translation )
  \wp_delete_post( $translation->ID, true );
