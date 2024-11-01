<?php
namespace NMod\SmartCookieKit;

if ( ! \defined( 'ABSPATH' ) ) exit;

include_once 'simple_html_dom.php';



function can_unlock_technical_cookies() {
  $kit = Frontend::get_instance();
  $preferences = $kit->get_cookies_preferences();

  return $preferences['technical'];
}

function can_unlock_statistics_cookies() {
  $kit = Frontend::get_instance();
  $preferences = $kit->get_cookies_preferences();

  return $preferences['statistics'];
}

function can_unlock_profiling_cookies() {
  $kit = Frontend::get_instance();
  $preferences = $kit->get_cookies_preferences();

  return $preferences['profiling'];
}



Frontend::get_instance();

class Frontend {
  static private $instance;
  static private $sck_preferences   = array();
  static private $styles_to_add     = array();
  static private $blocked_index     = 0;
  static private $enabled_plugins   = array();
  
  private $sources_to_block         = array();
  private $html_tag_reference       = array();
  private $html_tag_to_search       = array();
  private $html_tag_added           = array();
  private $compatibility_check      = array();
  private $object_references        = array();
  private $buffer_set               = false;

  private $plugin_base_url          = '';
  private $plugin_uri_scripts_main  = '';
  private $plugin_uri_scripts_empty = '';

  const CookieBlockPattern          = '/<!--\s*COOKIE_KIT_START_BLOCK\s*-->(.*?)<!--\s*COOKIE_KIT_END_BLOCK\s*-->/sU';
  const BlockedTagClass             = 'BlockedBySmartCookieKit';
  const NotAvailableClass           = 'BlockedForCookiePreferences';
  const CookiePreferencesClass      = 'OpenCookiePreferences';

  static public function get_instance() {
    if ( null === self::$instance ) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  public function __construct() {
    \add_action( 'wp'                                   , array( $this, 'load_settings'                       ),      1 );    
    \add_action( 'wp_enqueue_scripts'                   , array( $this, 'enqueue_scripts'                     ),      1 );
    \add_action( 'wp_head'                              , array( $this, 'buffer_set'                          ), -99999 );
    \add_action( 'wp_head'                              , array( $this, 'enqueue_styles'                      ),  99999 );
    \add_action( 'wp_footer'                            , array( $this, 'buffer_unset'                        ),  99999 );
    \add_action( 'wp_footer'                            , array( $this, 'print_html'                          ),  99999 );
    \add_action( 'wp_footer'                            , array( $this, 'run_frontend_kit'                    ),  99999 );

    \add_action( 'login_enqueue_scripts'                   , array( $this, 'load_settings'                       ),      1 );
    \add_action( 'login_enqueue_scripts'                   , array( $this, 'enqueue_scripts'                     ),      1 );
    \add_action( 'login_head'                              , array( $this, 'buffer_set'                          ), -99999 );
    \add_action( 'login_head'                              , array( $this, 'enqueue_styles'                      ),  99999 );
    \add_action( 'login_footer'                            , array( $this, 'buffer_unset'                        ),  99999 );
    \add_action( 'login_footer'                            , array( $this, 'print_html'                          ),  99999 );
    \add_action( 'login_footer'                            , array( $this, 'run_frontend_kit'                    ),  99999 );

    \add_shortcode( 'cookie_banner_link'                , array( $this, 'render_shortcode_cookie_banner_link' )         );
    //\add_shortcode( 'cookie_block'                      , array( $this, 'render_shortcode_cookie_block'       )         );

    \add_filter( 'filter_text_for_web', 'wptexturize'       );
    \add_filter( 'filter_text_for_web', 'convert_smilies'   );
    \add_filter( 'filter_text_for_web', 'convert_chars'     );
    \add_filter( 'filter_text_for_web', 'wpautop'           );
    \add_filter( 'filter_text_for_web', 'shortcode_unautop' );
    \add_filter( 'filter_text_for_web', 'do_shortcode'      );

    if ( ! \is_admin() ) include_once ABSPATH . 'wp-admin/includes/plugin.php';

    // CACHE and OPTIMIZERS Plugins compatibility
    if ( \is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) ) {
      \add_filter( 'w3tc_minify_js_do_tag_minification'   , array( $this, 'exclude_resources_w3tc'              ),  10, 3 );
    } 
    if ( \is_plugin_active( 'wp-fastest-cache-premium/wpFastestCachePremium.php' ) ) {
      \add_filter( 'script_loader_tag'                    , array( $this, 'remove_defer_fastestcache'           ),  10,  3 );
    }
    if ( \is_plugin_active( 'autoptimize/autoptimize.php' ) ) {
      \add_filter( 'autoptimize_filter_js_exclude'        , array( $this, 'exclude_resources_autoptimize'       ),  10, 1 );
      //\add_filter( 'autoptimize_filter_js_minify_excluded', array( $this, 'exclude_min_resources_autoptimize'   ),  10, 2 );
      \add_filter( 'autoptimize_js_include_inline'        , '__return_false'                                     ,  10, 1 );
    }
    if ( \is_plugin_active( 'async-javascript/async-javascript.php' ) ) {
      \add_action( 'option_aj_exclusions'                 , array( $this, 'exclude_resources_async_javascript'  ),  10    );
    }
    if ( \is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
      \add_filter( 'rocket_exclude_js'                    , array( $this, 'exclude_resources_wprocket_minif'    ),  10, 1 );
      \add_filter( 'rocket_exclude_defer_js'              , array( $this, 'exclude_resources_wprocket_defer'    ),  10, 1 );
    }
    if ( \is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
      \add_filter( 'litespeed_cache_optimize_js_excludes' , array( $this, 'exclude_resources_litespeed_minif'   ),  10, 1 );
      \add_filter( 'litespeed_optm_js_defer_exc'          , array( $this, 'exclude_resources_litespeed_defer'   ),  10, 1 );
      \add_filter( 'script_loader_tag'                    , array( $this, 'remove_async_defer_litespeed'        ),  10, 3 );
    }

    // OTHER plugins/services compatibility are present in function "load_settings"

    if ( \is_plugin_active( 'wp-google-map-gold/wp-google-map-gold.php' ) ) {
      \add_action( 'wp_loaded'                                 , array( $this, 'manage_plugin_googlemapgold'    ),  99999 );
    }

    // VISUAL BUILDERS compatibility
    \add_action( 'wp_loaded'                                 , array( $this, 'manage_visual_builder_avia'          ),  99999 ); // Enfold
    \add_action( 'wp_loaded'                                 , array( $this, 'manage_visual_builder_divi'          ),  99999 ); // Divi
    \add_action( 'wp_loaded'                                 , array( $this, 'manage_visual_builder_fusion'        ),  99999 ); // Avada
    \add_action( 'wp_loaded'                                 , array( $this, 'manage_visual_builder_wpbackery'     ),  99999 ); // Jupiter
    \add_action( 'wp_loaded'                                 , array( $this, 'manage_visual_builder_cornerstone'   ),  99999 ); // Cornerstone

    \add_action( 'wp_loaded'                                 , array( $this, 'manage_theme_bridge_map'             ),  99999 ); // Bridge Theme
  }

  private function get_cookie_name( $version = 0 ) {
    return ( 1 == $version ? 'nmod_sck_policy_accepted' : 'CookiePreferences' )
      . '-' . \str_replace( array( 'http://', 'https://' ), '', \get_site_url() );
  }

  public function get_cookies_preferences() {
    static $preferences;
    if ( \is_null( $preferences ) ) {
      $cookie_name = \str_replace(
        array( '.' ),
        array( '_' ),
        $this->get_cookie_name()
      );

      if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $cookie = \json_decode( \stripslashes( $_COOKIE[ $cookie_name ] ), true );
        $preferences = $cookie['settings'];
      } else {
        $preferences = array(
          'technical'  => true,
          'statistics' => false,
          'profiling'  => false
        );
      }
    }

    return $preferences;
  }


  private function transform_text_for_web( $text ) {
    return \preg_replace(
      array( "~\[br\]~i", "~\[b\]~i", "~\[/b\]~i", "~\[i\]~i", "~\[/i\]~i", "~\[u\]~i", "~\[/u\]~i", "~\[p\]~i", "~\[/p\]~i",  "~\r~", "~\n~"   ),
      array( '<br />'   , '<b>'     , '</b>'     , '<i>'     , '</i>'     , '<u>'     , '</u>'     , '<p>'     , '</p>'     , ''     , '<br />' ),
      $text
    );
  }

  private function localize_bool_val( $bool ) {
    return $bool ? 1 : 0;
  }

  private function get_blocked_index( $increment = false ) {
    if ( $increment )
      self::$blocked_index++;

    return self::$blocked_index;
  }

  public function load_settings() {
    $options = Options::get();
    $legacy  = Options::legacy_mode();

    $this->sources_to_block = array(
      array( 'service_name' => 'Custom features'                                          , 'unlock_with' => 'statistics,profiling' , 'pattern' => '#' . Options::CookieBlockClass_StatsAndProf  ),
      array( 'service_name' => 'Custom features'                                          , 'unlock_with' => 'statistics'           , 'pattern' => '#' . Options::CookieBlockClass_Statistics    ),
      array( 'service_name' => 'Custom features'                                          , 'unlock_with' => 'profiling'            , 'pattern' => '#' . Options::CookieBlockClass_Profiling     ),

      array( 'service_name' => 'Google Analytics'                                         , 'unlock_with' => 'statistics' , 'pattern' => 'google-analytics.com/ga.js'                                     ),
      array( 'service_name' => 'Google Analytics'                                         , 'unlock_with' => 'statistics' , 'pattern' => 'google-analytics.com/analytics.js'                              ),
      array( 'service_name' => 'Google Analytics GTAG'                                    , 'unlock_with' => 'statistics' , 'pattern' => 'googletagmanager.com/gtag/js'                                   ),

      array( 'service_name' => 'GAinWP Google Analytics Integration for WordPress'        , 'unlock_with' => 'statistics' , 'pattern' => 'cache/busting/google-tracking/ga-'                              ),
      array( 'service_name' => 'Clicky Web Analytics'                                     , 'unlock_with' => 'statistics' , 'pattern' => 'getclicky.com/'                                                 ),
      array( 'service_name' => 'Jetpack plugin'                                           , 'unlock_with' => 'statistics' , 'pattern' => '//stats.wp.com'                                                 ),
      array( 'service_name' => 'ShinyStat'                                                , 'unlock_with' => 'statistics' , 'pattern' => 'shinystat.it/cgi-bin/getcod.cgi'                                ),
      array( 'service_name' => 'ShinyStat'                                                , 'unlock_with' => 'statistics' , 'pattern' => 'shinystat.com/cgi-bin/getcod.cgi'                               ),
      array( 'service_name' => 'ShinyStat [noscript]'                                     , 'unlock_with' => 'statistics' , 'pattern' => 'cgi-bin/shinystat'                                              ),
      array( 'service_name' => 'Histats'                                                  , 'unlock_with' => 'statistics' , 'pattern' => 'histats.com/'                                                   ),
      array( 'service_name' => 'Shareaholic'                                              , 'unlock_with' => 'statistics' , 'pattern' => 'shareaholic.js'                                                 ),
      array( 'service_name' => 'Yandex Metrica'                                           , 'unlock_with' => 'statistics' , 'pattern' => 'mc.yandex.ru/'                                                  ),

      array( 'service_name' => 'Google AdWords Remarketing/Conversion TAG'                , 'unlock_with' => 'profiling'  , 'pattern' => 'googleadservices.com/pagead/conversion.js'                      ),
      array( 'service_name' => 'Google AdWords Remarketing/Conversion TAG [noscript]'     , 'unlock_with' => 'profiling'  , 'pattern' => 'googleads.g.doubleclick.net/pagead/viewthroughconversion/'      ),
      array( 'service_name' => 'Google Adsense'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js'         ),
      array( 'service_name' => 'Google Plus social plugin'                                , 'unlock_with' => 'profiling'  , 'pattern' => 'apis.google.com/js/plusone.js'                                  ),
      array( 'service_name' => 'Google API'                                               , 'unlock_with' => 'profiling'  , 'pattern' => 'apis.google.com/js/platform.js'                                 ),
      array( 'service_name' => 'Google API'                                               , 'unlock_with' => 'profiling'  , 'pattern' => 'apis.google.com'                                                ),
      array( 'service_name' => 'Google Maps'                                              , 'unlock_with' => 'profiling'  , 'pattern' => 'www.google.com/maps/embed'                                      ),
      array( 'service_name' => 'Google Maps'                                              , 'unlock_with' => 'profiling'  , 'pattern' => 'www.google.com/maps/d/embed'                                    ),
      array( 'service_name' => 'Google Maps'                                              , 'unlock_with' => 'profiling'  , 'pattern' => 'maps.google.it/maps'                                            ),
      array( 'service_name' => 'Google Maps'                                              , 'unlock_with' => 'profiling'  , 'pattern' => 'maps.google.com/maps'                                           ),
      array( 'service_name' => 'Google Maps'                                              , 'unlock_with' => 'profiling'  , 'pattern' => 'maps.googleapis.com/'                                           ),
      array( 'service_name' => 'Google Maps'                                              , 'unlock_with' => 'profiling'  , 'pattern' => 'new google.maps.Map('                                           , 'map_to_ignore' => array( 'window.onload', 'google.maps.event.addDomListener' ) ),

      array( 'service_name' => 'WP Store Locator plugin'                                  , 'unlock_with' => 'profiling'  , 'pattern' => 'plugins/wp-store-locator/js/wpsl-gmap.min.js'                   ),
      array( 'service_name' => 'Event Espresso plugin'                                    , 'unlock_with' => 'profiling'  , 'pattern' => 'plugins/event-espresso-core-reg/core/helpers/assets/ee_gmap.js' ),
      array( 'service_name' => 'Google Maps Easy plugin'                                  , 'unlock_with' => 'profiling'  , 'pattern' => 'plugins/google-maps-easy/'                                      ),
      array( 'service_name' => 'Google Map Gold for WordPress'                            , 'unlock_with' => 'profiling'  , 'pattern' => 'plugins/wp-google-map-gold/'                                    ),
      array( 'service_name' => 'Divi Map Extended Module plugin'                          , 'unlock_with' => 'profiling'  , 'pattern' => 'plugins/dwd-map-extended/assets/js/dwd-map-extended.js'         ),
      array( 'service_name' => 'Google Maps for Fusion builder'                           , 'unlock_with' => 'profiling'  , 'pattern' => 'infobox_packed.js'                                              ),
      array( 'service_name' => 'Google Maps for Identity Theme'                           , 'unlock_with' => 'profiling'  , 'pattern' => 'themes/identity/js/identity/gmap-settings.js'                   ),
      array( 'service_name' => 'Google Maps for Avia builder from 4.4'                    , 'unlock_with' => 'profiling'  , 'pattern' => 'themes/enfold/framework/js/conditional_load/avia_google_maps_front.js'),

      array( 'service_name' => 'Vertis Media'                                             , 'unlock_with' => 'profiling'  , 'pattern' => 'cdn.vertismedia.co.uk/t.js'                                     ),
      array( 'service_name' => 'Teads Adv Marketplace'                                    , 'unlock_with' => 'profiling'  , 'pattern' => 'a.teads.tv/'                                                    ),
      array( 'service_name' => 'Outbrain'                                                 , 'unlock_with' => 'profiling'  , 'pattern' => 'widgets.outbrain.com/outbrain.js'                               ),


      array( 'service_name' => 'WP Google Maps'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'wp-google-maps/lib/CanvasLayer.js'                              ),
      array( 'service_name' => 'WP Google Maps'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'wp-google-maps/js/wpgmaps.js'                                   ),

      array( 'service_name' => 'Google Traduttore'                                        , 'unlock_with' => 'profiling'  , 'pattern' => 'translate.google.com'                                           ),

      array( 'service_name' => 'Tripadvisor'                                              , 'unlock_with' => 'profiling'  , 'pattern' => 'jscache.com/'                                                   ),

      array( 'service_name' => 'Booking affiliation'                                      , 'unlock_with' => 'profiling'  , 'pattern' => '/flexiproduct.js'                                               ),
      array( 'service_name' => 'Awin affiliation'                                         , 'unlock_with' => 'profiling'  , 'pattern' => 'awin1.com/'                                                     ),
      array( 'service_name' => 'Gambling affiliation'                                     , 'unlock_with' => 'profiling'  , 'pattern' => 'gambling-affiliation.com/cpm/v='                                ),
      array( 'service_name' => 'Google Youtube'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'www.youtube.com/iframe_api'                                     ), 
      array( 'service_name' => 'Google Youtube'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'youtube.com'                                                    ), 
      array( 'service_name' => 'Facebook Pixel'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'connect.facebook.net'                                           , 'fbq_fallback' => array( 'window.onload', 'google.maps.event.addDomListener' ) ),
      array( 'service_name' => 'Facebook Pixel [noscript]'                                , 'unlock_with' => 'profiling'  , 'pattern' => 'www.facebook.com/tr?id='                                        , 'fbq_fallback' => array( 'window.onload', 'google.maps.event.addDomListener' ) ),
      array( 'service_name' => 'Facebook Like social plugin'                              , 'unlock_with' => 'profiling'  , 'pattern' => 'www.facebook.com/plugins/like.php'                              , 'fbq_fallback' => array( 'window.onload', 'google.maps.event.addDomListener' ) ), 
      array( 'service_name' => 'Facebook LikeBox social plugin'                           , 'unlock_with' => 'profiling'  , 'pattern' => 'www.facebook.com/plugins/likebox.php'                           , 'fbq_fallback' => array( 'window.onload', 'google.maps.event.addDomListener' ) ), 
      array( 'service_name' => 'Facebook Share social plugin'                             , 'unlock_with' => 'profiling'  , 'pattern' => 'www.facebook.com/plugins/share_button.php'                      , 'fbq_fallback' => array( 'window.onload', 'google.maps.event.addDomListener' ) ), 
      array( 'service_name' => 'PixelYourSite plugin'                                     , 'unlock_with' => 'profiling'  , 'pattern' => 'pixelyoursite/js/public.js'                                     , 'fbq_fallback' => array( 'window.onload', 'google.maps.event.addDomListener' ) ),
      array( 'service_name' => 'Pixel Caffeine'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'pixel-caffeine/build/frontend.js'                               , 'fbq_fallback' => array( 'window.onload', 'google.maps.event.addDomListener' ) ), 

      array( 'service_name' => 'LinkedIn Pixel'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'snap.licdn.com/'                                                ),
      array( 'service_name' => 'Pinterest Pixel'                                          , 'unlock_with' => 'profiling'  , 'pattern' => 's.pinimg.com/ct/'                                               ),
      array( 'service_name' => 'Pinterest Pixel [noscript]'                               , 'unlock_with' => 'profiling'  , 'pattern' => 'ct.pinterest.com'                                               ),
      array( 'service_name' => 'ShareThis plugin'                                         , 'unlock_with' => 'profiling'  , 'pattern' => 'sharethis.com'                                                  ),
      array( 'service_name' => 'Hupso plugin'                                             , 'unlock_with' => 'profiling'  , 'pattern' => 'hupso.com/share/js/share_toolbar.js'                            ),      
      array( 'service_name' => 'AddThis plugin'                                           , 'unlock_with' => 'profiling'  , 'pattern' => 'addthis.com/js/'                                                ),
      array( 'service_name' => 'AddToAny plugin'                                          , 'unlock_with' => 'profiling'  , 'pattern' => 'static.addtoany.com'                                            ),
      array( 'service_name' => 'Twitter framework'                                        , 'unlock_with' => 'profiling'  , 'pattern' => 'platform.twitter.com'                                           ), 
      array( 'service_name' => 'Vimeo framework'                                          , 'unlock_with' => 'profiling'  , 'pattern' => 'player.vimeo.com/video'                                         ), 
      array( 'service_name' => 'Instapage pixel'                                          , 'unlock_with' => 'profiling'  , 'pattern' => 'app.instapage.com/ajax/pageserver/stats/visitor-pixel/'         ),
      array( 'service_name' => 'Convertifire framework'                                   , 'unlock_with' => 'profiling'  , 'pattern' => 'app.convertifire.io/setup/recorder.js'                          ), 
      array( 'service_name' => 'Tawk chat'                                                , 'unlock_with' => 'profiling'  , 'pattern' => 'embed.tawk.to'                                                  ),
      array( 'service_name' => 'OneSignal'                                                , 'unlock_with' => 'profiling'  , 'pattern' => 'OneSignalSDK.js'                                                ),
      array( 'service_name' => 'WP QUADS PRO plugin'                                      , 'unlock_with' => 'profiling'  , 'pattern' => 'wp-quads-pro/assets/js/ads.js'                                  ), 
      array( 'service_name' => 'ConvertFlow services'                                     , 'unlock_with' => 'profiling'  , 'pattern' => 'convertflow.com/scripts/'                                       ), 
      array( 'service_name' => 'Disqus'                                                   , 'unlock_with' => 'profiling'  , 'pattern' => 'disqus.com/embed.js'                                            ), 
      array( 'service_name' => 'Disqus comment count'                                     , 'unlock_with' => 'profiling'  , 'pattern' => 'disqus.com/count.js'                                            ), 

      array( 'service_name' => 'Instagram'                                                , 'unlock_with' => 'profiling'  , 'pattern' => 'instagram.com/embed.js'                                         ),
    );

    $custom_sources_to_block = \apply_filters( 'sck_sources_to_block', array() );
    if ( ! empty( $custom_sources_to_block ) )
      $this->sources_to_block = \array_merge( $this->sources_to_block, $custom_sources_to_block );

    $this->html_tag_reference = array(
      'Google Maps'       => array( '.et_pb_map_container', '.et_pb_map_container_extended' ),
      'Google reCAPTCHA'  => array( '.g-recaptcha' ),
      'Facebook Pixel'    => array( '.fb-like', '.fb-page' )
    );

    $this->plugin_base_url = \plugin_dir_url( __FILE__ );

    if ( $options['blockGoogleTagManager'] ) {
      $this->sources_to_block[] = array( 'service_name' => 'Google Tag Manager'              , 'unlock_with' => 'statistics,profiling' , 'pattern' => 'googletagmanager.com/gtm.js'   );
      $this->sources_to_block[] = array( 'service_name' => 'Google Tag Manager [noscript]'   , 'unlock_with' => 'statistics,profiling' , 'pattern' => 'googletagmanager.com/ns.html'  );

      if ( \is_plugin_active( 'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' ) ) {
        $this->sources_to_block[] = array( 'service_name' => 'Google Tag Manager by DuracellTomi', 'unlock_with' => 'statistics,profiling' , 'pattern' => 'duracelltomi-google-tag-manager/js/'  );

        \add_filter( 'gtm4wp_get_the_gtm_tag', array( $this, 'manage_plugin_duracelltomigtm' ),  10, 1 );
      }
    }

    if ( $options['blockGoogleReCaptcha'] ) {
      $this->sources_to_block[] = array( 'service_name' => 'Google reCAPTCHA'                , 'unlock_with' => 'profiling', 'pattern' => 'www.google.com/recaptcha/api.js'       );
      $this->sources_to_block[] = array( 'service_name' => 'Google reCAPTCHA [noscript]'     , 'unlock_with' => 'profiling', 'pattern' => 'www.google.com/recaptcha/api/fallback' );
      $this->sources_to_block[] = array( 'service_name' => 'Google reCAPTCHA'                , 'unlock_with' => 'profiling', 'pattern' => 'grecaptcha.ready('                     );
    }            

    if ( $options['blockAutomateWooSessionTracking'] ) {
      if ( \is_plugin_active( 'automatewoo/automatewoo.php' ) ) {
        \add_filter( 'automatewoo/session_tracking/cookies_permitted', array( $this, 'manage_plugin_automatewoo_sessiontracking' ),  10, 1 );
      }
    }
    
    self::$sck_preferences = array(
      'acceptedCookieName'            => $this->get_cookie_name(),
      'acceptedCookieName_v1'         => $this->get_cookie_name( 1 ),
      'acceptedCookieLife'            => $options['cookieAcceptedLife'],
      'debugMode'                     => $this->localize_bool_val( $options['pluginDebugMode'] ),
      'remoteEndpoint'                => \admin_url('admin-ajax.php'),
      'saveLogToServer'               => $this->localize_bool_val( $options['saveLogToServer'] ),
      'addBacklayer'                  => $this->localize_bool_val( $options['addBannerBackLayer'] ),
      'showMinimizedButton'           => $this->localize_bool_val( $options['showMinimizedButton'] ),
      'managePlaceholders'            => $this->localize_bool_val( $options['addBlockedContentPlaceholder'] ),
      'reloadPageOnCookieDisabled'    => $this->localize_bool_val( $options['reloadPageWhenDisabled'] ),
      'acceptPolicyOnScroll'          => $this->localize_bool_val( $options['acceptPolicyOnScroll'] ),
      'runCookieKit'                  => $this->localize_bool_val( ! ( 0 < $options['cookiePolicyPageID'] && \is_page( Multilanguage::get_translation( $options['cookiePolicyPageID'] ) ) ) ),
      'searchTags'                    => array(),
    );

//    if ( ! empty( $this->compatibility_check ) )
//      self::$sck_preferences['placeholderMaster'] = $this->not_available_tag( 0, 'image', \esc_html__( 'Custom block', 'smart-cookie-kit' ) );

    self::$sck_preferences = \array_merge( self::$sck_preferences, Options::get_translated_texts( $options['cookieBannerContentID'] ) );
/*
    $texts_by_post = false;
    if ( 0 < $options['cookieBannerContentID'] )
      $texts_by_post = 'publish' == get_post_status( $options['cookieBannerContentID'] );
    $meta = array();
    if ( $texts_by_post ) {
      // Get the translated form, using WPML
      $post_id = \apply_filters( 'wpml_object_id', $options['cookieBannerContentID'], Options::BannerPostType );


      $meta = get_post_meta( $post_id, 'SCK_BannerTexts', true );
      if ( ! \is_array( $meta ) ) $meta = array();

      $post = get_post( $post_id );
      $meta['cookieBannerText'] = $post->post_content;
    }

    self::$sck_preferences['infoText'                     ] = isset( $meta['cookieBannerText']          ) ? $meta['cookieBannerText']          : Options::transform_text_for_web( $options['cookieBannerText']        );
    //self::$sck_preferences['userSettingsLinkText'         ] = Options::transform_text_for_web( $meta['userSettingsLinkText'] );
    self::$sck_preferences['enableButtonText'             ] = isset( $meta['cookieEnableButtonText']    ) ? $meta['cookieEnableButtonText']    : Options::transform_text_for_web( $options['cookieEnableButtonText']  );
    self::$sck_preferences['enabledButtonText'            ] = isset( $meta['cookieEnabledButtonText']   ) ? $meta['cookieEnabledButtonText']   : Options::transform_text_for_web( $options['cookieEnabledButtonText'] );
    self::$sck_preferences['disableLinkText'              ] = isset( $meta['cookieDisableLinkText']     ) ? $meta['cookieDisableLinkText']     : Options::transform_text_for_web( $options['cookieDisableLinkText']   );
    self::$sck_preferences['disabledLinkText'             ] = isset( $meta['cookieDisabledLinkText']    ) ? $meta['cookieDisabledLinkText']    : Options::transform_text_for_web( $options['cookieDisabledLinkText']  );

    $temp_url  = isset( $meta['cookiePolicyPageURL'] ) ? $meta['cookiePolicyPageURL'] : $options['cookiePolicyPageURL'];
    $temp_id   = isset( $meta['cookiePolicyPageID']  ) ? $meta['cookiePolicyPageID']  : $options['cookiePolicyPageID'];
    $temp      = $temp_id <= 0 ? $temp_url : \get_page_link( $temp_id );
    if ( '' != $temp ) {
      self::$sck_preferences['policyLinkURI']  = $temp;
      self::$sck_preferences['policyLinkText'] = isset( $meta['cookiePolicyLinkText']      ) ? $meta['cookiePolicyLinkText']      : Options::transform_text_for_web( $options['cookiePolicyLinkText']    );
    }

    if ( $options['showMinimizedButton'] ) {
      self::$sck_preferences['minimizedSettingsBannerId']     = 'cookie_min_banner';
      self::$sck_preferences['minimizedSettingsButtonText']   = isset( $meta['minimizedSettingsButtonText'] ) ? $meta['minimizedSettingsButtonText'] : Options::transform_text_for_web( $options['minimizedSettingsButtonText'] );
    }
*/
/*
    } else {
      self::$sck_preferences['infoText'                     ] = $this->transform_text_for_web( $options['cookieBannerText'] );
      self::$sck_preferences['policyLinkURI'                ] = -1 == $options['cookiePolicyPageID'] ? $options['cookiePolicyPageURL'] : \get_page_link( $options['cookiePolicyPageID'] );
      self::$sck_preferences['policyLinkText'               ] = $this->transform_text_for_web( $options['cookiePolicyLinkText'] );
      //self::$sck_preferences['userSettingsLinkText'         ] = $this->transform_text_for_web( $options['userSettingsLinkText'] );
      self::$sck_preferences['enableButtonText'             ] = $options['cookieEnableButtonText'];
      self::$sck_preferences['enabledButtonText'            ] = $options['cookieEnabledButtonText'];
      self::$sck_preferences['disableLinkText'              ] = $options['cookieDisableLinkText'];
      self::$sck_preferences['disabledLinkText'             ] = $options['cookieDisabledLinkText'];

      if ( $options['showMinimizedButton'] ) {
        self::$sck_preferences['minimizedSettingsBannerId']   = 'cookie_min_banner';
        self::$sck_preferences['minimizedSettingsButtonText'] = $options['minimizedSettingsButtonText'];
      }
    }
*/
    
    self::$styles_to_add = array(
      '0'    => array(),
      '768'  => array(),
      '1000' => array(),
    );
    
    self::$styles_to_add['0'][] = array( 'selector' => '.' . self::BlockedTagClass        , 'css' => 'display:none !important;'                                        , 'global_css' => true );
    self::$styles_to_add['0'][] = array( 'selector' => '.SCK_Banner'                      , 'css' => 'display:none;'                                                                          );
    self::$styles_to_add['0'][] = array( 'selector' => '.SCK_Banner.visible'              , 'css' => 'display:block;'                                                                         );

    if ( !empty( $options['cssMobileContentPlaceholder'        ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '.' . self::NotAvailableClass                             , 'css' => $options['cssMobileContentPlaceholder'        ], 'global_css' => true );
    if ( $options['addBannerBackLayer'] ) {
      if ( !empty( $options['cssMobileBannerBackLayer'           ] ) ) self::$styles_to_add['0'][] = array( 'selector' => ' > div:first-of-type'                                    , 'css' => $options['cssMobileBannerBackLayer'           ] );
    }
    if ( !empty( $options['cssMobileBannerContainer'           ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '.SCK_BannerContainer'                                    , 'css' => $options['cssMobileBannerContainer'           ] );
    if ( !empty( $options['cssMobileBannerTextContainer'       ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '#SCK_BannerTextContainer'                                , 'css' => $options['cssMobileBannerTextContainer'       ] );
    if ( !empty( $options['cssMobileBannerText'                ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '#SCK_BannerText,#SCK_BannerText p'                       , 'css' => $options['cssMobileBannerText'                ] );
    if ( !empty( $options['cssMobileBannerActionsArea'         ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '#SCK_BannerActions'                                      , 'css' => $options['cssMobileBannerActionsArea'         ] );
    if ( !empty( $options['cssMobileBannerActionsButtons'      ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '#SCK_BannerActionsContainer'                             , 'css' => $options['cssMobileBannerActionsButtons'      ] );

    if ( !empty( $options['cssMobileBannerAcceptButton'        ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '.SCK_Accept'                                             , 'css' => $options['cssMobileBannerAcceptButton'        ] );
    if ( !empty( $options['cssMobileBannerAcceptButtonHover'   ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '.SCK_Accept:hover'                                       , 'css' => $options['cssMobileBannerAcceptButtonHover'   ] );
    if ( !empty( $options['cssMobileBannerCloseLink'           ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '.SCK_Close'                                              , 'css' => $options['cssMobileBannerCloseLink'           ] );

    if ( $options['showMinimizedButton'] ) {
      if ( !empty( $options['cssMobileMinimizedSettingsButton'          ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '#SCK_MinimizedBanner .SCK_Open'                      , 'css' => $options['cssMobileMinimizedSettingsButton'           ] );
      if ( !empty( $options['cssMobileMinimizedSettingsButtonHover'     ] ) ) self::$styles_to_add['0'][] = array( 'selector' => '#SCK_MinimizedBanner .SCK_Open:hover'                , 'css' => $options['cssMobileMinimizedSettingsButtonHover'      ] );
    }

    if ( !empty( $options['cssDesktopContentPlaceholder'        ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '.' . self::NotAvailableClass                             , 'css' => $options['cssDesktopContentPlaceholder'        ], 'global_css' => true );
    if ( $options['addBannerBackLayer'] ) {
      if ( !empty( $options['cssDesktopBannerBackLayer'           ] ) ) self::$styles_to_add['768'][] = array( 'selector' => ' > div:first-of-type'                                    , 'css' => $options['cssDesktopBannerBackLayer'           ] );
    }
    if ( !empty( $options['cssDesktopBannerContainer'           ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '.SCK_BannerContainer'                                    , 'css' => $options['cssDesktopBannerContainer'           ] );
    if ( !empty( $options['cssDesktopBannerTextContainer'       ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '#SCK_BannerTextContainer'                                , 'css' => $options['cssDesktopBannerTextContainer'       ] );
    if ( !empty( $options['cssDesktopBannerText'                ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '#SCK_BannerText,#SCK_BannerText p'                       , 'css' => $options['cssDesktopBannerText'                ] );
    if ( !empty( $options['cssDesktopBannerActionsArea'         ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '#SCK_BannerActions'                                      , 'css' => $options['cssDesktopBannerActionsArea'         ] );
    if ( !empty( $options['cssDesktopBannerActionsButtons'      ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '#SCK_BannerActionsContainer'                             , 'css' => $options['cssDesktopBannerActionsButtons'      ] );

    if ( !empty( $options['cssDesktopBannerAcceptButton'        ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '.SCK_Accept'                                             , 'css' => $options['cssDesktopBannerAcceptButton'        ] );
    if ( !empty( $options['cssDesktopBannerAcceptButtonHover'   ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '.SCK_Accept:hover'                                       , 'css' => $options['cssDesktopBannerAcceptButtonHover'   ] );
    if ( !empty( $options['cssDesktopBannerCloseLink'           ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '.SCK_Close'                                              , 'css' => $options['cssDesktopBannerCloseLink'           ] );

    if ( $options['showMinimizedButton'] ) {
      if ( !empty( $options['cssDesktopMinimizedSettingsButton'          ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '#SCK_MinimizedBanner .SCK_Open'                  , 'css' => $options['cssDesktopMinimizedSettingsButton'           ] );
      if ( !empty( $options['cssDesktopMinimizedSettingsButtonHover'     ] ) ) self::$styles_to_add['768'][] = array( 'selector' => '#SCK_MinimizedBanner .SCK_Open:hover'            , 'css' => $options['cssDesktopMinimizedSettingsButtonHover'      ] );
    }

    if ( !empty( $options['cssMobileBannerContainer'           ] ) ) self::$styles_to_add['1000'][] = array( 'selector' => '.SCK_BannerContainer'                                    , 'css' => 'width:1000px;left:50%;margin-left:-500px;' );

    if ( $legacy ) {
      if ( 1 == $legacy ) {
        self::$sck_preferences = array(
          'acceptedCookieLife'            => $options['cookieAcceptedLife'],
          'acceptButtonText'              => $options['cookieAcceptedButtonText'],
          'infoText'                      => $this->transform_text_for_web(
            $options['cookieBannerText'] )
            . ( 0 == $options['cookiePolicyPageID']
              ? ''
              : $this->transform_text_for_web( \sprintf(
                ' <a href="%s" style="%s" target="_blank">%s</a>',
                ( -1 == $options['cookiePolicyPageID']
                  ? $options['cookiePolicyPageURL']
                  : \get_page_link( $options['cookiePolicyPageID'] )
                ),
                $options['cssBannerPolicyLink'],
                $options['cookiePolicyLinkText']
              )
            )
          ),
          'acceptedCookieName'            => 'nmod_sck_policy_accepted-' . \str_replace( array( 'http://', 'https://' ), '', \get_site_url() ),
          'infoBannerId'                  => 'cookie_info_breve',
          'acceptButtonId'                => 'cookie_accept_button',
          'cssBannerBackLayer'            => $options['cssBannerBackLayer'],
          'cssBannerBackground'           => $options['cssBannerBackground'],
          'cssBannerContainer'            => $options['cssBannerContainer'],
          'cssBannerText'                 => $options['cssBannerText'],
          'cssBannerButtonsArea'          => $options['cssBannerButtonsArea'],
          'cssBannerPolicyLink'           => $options['cssBannerPolicyLink'],
          'cssBannerAcceptButton'         => $options['cssBannerAcceptButton'],
          'runCookieKit'                  => $this->localize_bool_val( ! ( 0 < $options['cookiePolicyPageID'] && \is_page( $options['cookiePolicyPageID'] ) ) ),
          'acceptPolicyOnScroll'          => $this->localize_bool_val( $options['acceptPolicyOnScroll'] ),
          'acceptPolicyOnContentClick'    => $this->localize_bool_val( $options['acceptPolicyOnContentClick'] ),
          'debugMode'                     => $this->localize_bool_val( $options['pluginDebugMode'] ),
          'remoteEndpoint'                => \admin_url('admin-ajax.php'),
          'saveLogToServer'               => $this->localize_bool_val( $options['saveLogToServer'] ),
          'excludedParentsClick'          => '#cookie_info_breve' . ( '' != $options['excludedParentsClick'] ? ',' . $options['excludedParentsClick'] : '' )
        );

        self::$styles_to_add = array();
      }
    }
  }

  public function enqueue_styles() {
    if ( empty( self::$styles_to_add ) ) return;

    $styles = '';
    foreach ( self::$styles_to_add as $breakpoint => $rules ) {
      if ( empty( $rules ) ) continue;

      if ( '0' != $breakpoint ) $styles .= \sprintf( '@media(min-width:%spx){', $breakpoint );
      foreach ( $rules as $rule ) {

        $css_selectors  = array();
        $rule_selectors = \explode( ',', $rule['selector'] );
        foreach ( $rule_selectors as $selector ) {
          $temp_rule_selector = \trim( $selector );
          $temp_css_selector = '';

          if ( ! \array_key_exists( 'global_css', $rule ) ) {
            $temp_css_selector .= '#SCK';
            if ( '' != $temp_rule_selector ) $temp_css_selector .= ' ';
          }
          $temp_css_selector .= $temp_rule_selector;

          $css_selectors[] = $temp_css_selector;
        }
        $styles .= \implode( ',', $css_selectors ) . '{' . $rule['css'] . '}';
/*
        if ( ! \array_key_exists( 'global_css', $rule ) ) {
          $styles .= '#SCK';
          if ( '' != $rule['selector'] ) $styles .= ' ';
        }
        $styles .= $rule['selector'] . '{' . $rule['css'] . '}';
*/
      }
      if ( '0' != $breakpoint ) $styles .= '}';
    }

    if ( $styles )
      \printf( '<style type="text/css">%s</style>', $styles );

    self::$styles_to_add = array();
  }

  public function print_html() {
    $output = '';

    $output .= '<div id="SCK">';
      $output .= '<div class="SCK_Banner" id="SCK_MaximizedBanner">';
        if ( 1 == self::$sck_preferences['addBacklayer'] ) $output .= '<div></div>';
        $output .= '<div class="SCK_BannerContainer">';
          $output .= \sprintf( '<div id="SCK_BannerTextContainer"><div id="SCK_BannerText">%s</div></div>', \apply_filters( 'filter_text_for_web', self::$sck_preferences['infoText'] ) );
          $output .= '<div id="SCK_BannerActions">';
            $output .= '<div id="SCK_BannerActionsContainer">';
              $output .= \sprintf( '<button class="SCK_Accept" data-textaccept="%s" data-textaccepted="%s"></button>', self::$sck_preferences['enableButtonText'], self::$sck_preferences['enabledButtonText'] );
              $output .= \sprintf( '<a class="SCK_Close" href="#" data-textdisable="%s" data-textdisabled="%s"></a>', self::$sck_preferences['disableLinkText'], self::$sck_preferences['disabledLinkText'] );
            $output .= '</div>';  
          $output .= '</div>';  
        $output .= '</div>';
      $output .= '</div>';

    if ( self::$sck_preferences['showMinimizedButton'] ) {
      $output .= '<div class="SCK_Banner" id="SCK_MinimizedBanner">';
        $output .= \sprintf( '<button class="SCK_Open">%s</button>', self::$sck_preferences['minimizedSettingsButtonText'] );
      $output .= '</div>';
    }

    if ( ! empty( $this->html_tag_to_search ) ) {
      $output .= '<div class="SCK_Banner" id="SCK_Placeholder">';
        $output .= $this->not_available_tag( '%REF_NUM%', 'div', '%SERVICE_NAME%' );
      $output .= '</div>';
    }

    $output .= '</div>';

    echo $output;
  }

  public function enqueue_scripts() {
    $options = Options::get();

    $legacy  = Options::legacy_mode();

    $script_dir_uri  = \plugin_dir_url( __FILE__ );
    
    $script_minified = $options['pluginDebugMode'] ? '' : 'min_';

    $script_version  = '2019081301';

    if ( $legacy ) {
      if ( 1 == $legacy ) 
        $script_version  = '2017110201';
    }

    $script_dependancies = array();
    if ( $legacy ) {
      if ( 1 == $legacy ) 
        $script_dependancies  = array( 'jquery' );
    }

    $this->plugin_uri_scripts_main  = \sprintf( '%sjs/sck.%s%s.js', $script_dir_uri, $script_minified, $script_version );
    $this->plugin_uri_scripts_empty = \sprintf( '%sres/empty.js', $script_dir_uri );

    \wp_enqueue_script( 'nmod_sck_fe_scripts', $this->plugin_uri_scripts_main, $script_dependancies, null, true );
    $parameters = array(
      'acceptedCookieName'                   => self::$sck_preferences['acceptedCookieName'],
      'acceptedCookieName_v1'                => self::$sck_preferences['acceptedCookieName_v1'],
      'acceptedCookieLife'                   => self::$sck_preferences['acceptedCookieLife'],
      'runCookieKit'                         => self::$sck_preferences['runCookieKit'],
      'debugMode'                            => self::$sck_preferences['debugMode'],
      'remoteEndpoint'                       => self::$sck_preferences['remoteEndpoint'],
      'saveLogToServer'                      => self::$sck_preferences['saveLogToServer'],
      'managePlaceholders'                   => self::$sck_preferences['managePlaceholders'],
      'reloadPageWhenUserDisablesCookies'    => self::$sck_preferences['reloadPageOnCookieDisabled'],
      'acceptPolicyOnScroll'                 => self::$sck_preferences['acceptPolicyOnScroll'],
      'searchTags'                           => self::$sck_preferences['searchTags'],
    );
    \wp_localize_script( 'nmod_sck_fe_scripts', 'NMOD_SCK_Options', $parameters );
  }

  public function run_frontend_kit() {
    ?><script>;NMOD_SCK_Options.checkCompatibility=<?php echo \json_encode( \array_values( $this->compatibility_check ) ); ?>;NMOD_SCK_Options.searchTags=<?php echo \json_encode( $this->html_tag_to_search ); ?>;NMOD_SCK_Helper.init();</script><?php
  }

  public function exclude_resources_w3tc( $do_tag_minification, $script_tag, $file ) {
    // With this filter W3 Total Cache will exclude SCK main script plus empty.js from the minification
    if ( false !== \strpos( $file, \sprintf( '%s/js/sck.'     , \basename( \plugin_dir_url( __FILE__ ) ) ) ) ) return false;
    if ( false !== \strpos( $file, \sprintf( '%s/res/empty.js', \basename( \plugin_dir_url( __FILE__ ) ) ) ) ) return false;
    return $do_tag_minification;    
  }

  public function exclude_resources_autoptimize( $exclude ) {
    // With this filter Autoptimize will exclude SCK main script plus empty.js from the aggregation
    return $exclude . \sprintf( ', %1$s/js/sck., %1$s/res/empty.js', \basename( \plugin_dir_url( __FILE__ ) ) );
  }

  public function exclude_min_resources_autoptimize( $ret, $url ) {
    // With this filter Autoptimize will exclude SCK main script that is not aggregated from the minification
    if ( false == \strpos( $url, \sprintf( '%1$s/js/sck.', \basename( \plugin_dir_url( __FILE__ ) ) ) ) )
      return $ret;

    return false;
  }

  public function exclude_resources_async_javascript( $value ) {
    $script = \sprintf( '%1$s/js/sck.', \basename( \plugin_dir_url( __FILE__ ) ) );
    if ( \is_array( $value ) ) {
      $value[] = $script;
    } else {
      if ( '' != $value ) $value .= ',';
      $value .= $script;
    }
    return $value;
  }

  public function remove_defer_fastestcache( $tag, $handle, $src ) {
    // With this filter Fastest Cahce Premium will exclude SCK main script plus empty.js from the defer
    if ( !( 'nmod_sck_fe_scripts' === $handle || false !== \strpos( $src, 'empty.js' ) ) )
      return $tag;

    return \str_replace(
      array( 'src='                          ),
      array( 'data-wpfc-render="false" src=' ),
      $tag
    );
  }

  public function remove_async_defer_litespeed( $tag, $handle, $src ) {
    // With this filter Litespeed will exclude SCK main script plus empty.js from async and defer
    if ( !( 'nmod_sck_fe_scripts' === $handle || false !== \strpos( $src, 'empty.js' ) ) )
      return $tag;

    return \str_replace(
      array( 'src='                                        ),
      array( 'data-no-optimize="1" data-no-defer="1" src=' ),
      $tag
    );
  }

  public function exclude_resources_wprocket_minif( $excluded_files ) {
    // With this filter Wp-Rocket will exclude empty.js from the aggregation
    $excluded_files[] = \sprintf( '%1$s/js/sck.'     , \basename( \plugin_dir_url( __FILE__ ) ) );
    $excluded_files[] = \sprintf( '%1$s/res/empty.js', \basename( \plugin_dir_url( __FILE__ ) ) );

    return $excluded_files;
  }

  public function exclude_resources_wprocket_defer( $exclude_defer_js ) {
    // With this filter Wp-Rocket will exclude SCK main script from defer
    $exclude_defer_js[] = \sprintf( '%1$s/js/sck.'     , \basename( \plugin_dir_url( __FILE__ ) ) );

    return $exclude_defer_js;
  }
  
  public function exclude_resources_litespeed_minif( $excludes ) {
    // With this filter Litespeed Cache will exclude SCK main script and empty.js from the aggregation

    if ( '' != $excludes ) $excludes .= "\n";
    $excludes .= \sprintf( '%1$s/js/sck.*.js' , \basename( \plugin_dir_url( __FILE__ ) ) );

    $excludes .= "\n";
    $excludes .= \sprintf( '%1$s/res/empty.js', \basename( \plugin_dir_url( __FILE__ ) ) );

    return $excludes;
  }

  public function exclude_resources_litespeed_defer( $excludes ) {
    // With this filter Litespeed Cache will exclude SCK main script and empty.js from defer

    if ( '' != $excludes )
      $excludes .= "\n";
//    $excludes .= \sprintf( '%1$s/js/sck.*.js'     , \basename( \plugin_dir_url( __FILE__ ) ) );
    $excludes .= $this->plugin_uri_scripts_main;

    return $excludes;
  }

  public function manage_plugin_duracelltomigtm( $gtm_output ) {
    return $this->block_shortcode(
      $gtm_output,
      array(
        'service_name' => 'Google Tag Manager by DuracellTomi',
        'unlock_with'  => 'statistics,profiling',
        'sections'     => array(
          array( 'type' => 'script', 'pattern' => 'script', 'class' => '' )
        )
      ),
      false
    );
  }

  public function manage_plugin_googlemapgold( $gtm_output ) {
    global $shortcode_tags;
    if ( \is_array( $shortcode_tags ) ) {
      if ( \array_key_exists( 'put_wpgm', $shortcode_tags ) ) {
        $this->object_references[ 'put_wpgm' ] = $shortcode_tags['put_wpgm'][0];

        \remove_shortcode( 'put_wpgm' );
        \add_shortcode( 'put_wpgm', array( $this, 'filter_plugin_shortcode_google_maps_gold_putwpgm' ) );
      }

      if ( \array_key_exists( 'display_map', $shortcode_tags ) ) {
        $this->object_references[ 'display_map' ] = $shortcode_tags['display_map'][0];

        \remove_shortcode( 'display_map' );
        \add_shortcode( 'display_map', array( $this, 'filter_plugin_shortcode_google_maps_gold_displaymap' ) );
      }
    }
  }

  public function manage_plugin_automatewoo_sessiontracking( $permitted ) {    
    return can_unlock_profiling_cookies() ? $permitted : false;
  }

  public function manage_visual_builder_avia() {
    if ( class_exists( 'avia_sc_gmaps' ) ) {
      \remove_shortcode( 'av_google_map' );
      \add_shortcode( 'av_google_map', array( $this, 'filter_builder_shortcode_avia_maps' ) );
    }
  }

  public function manage_visual_builder_divi() {
    \add_filter( 'et_pb_fullwidth_map_shortcode_output', array( $this, 'filter_builder_shortcode_divi_maps' ) );
    \add_filter( 'et_pb_map_shortcode_output'          , array( $this, 'filter_builder_shortcode_divi_maps' ) );
  }

  public function manage_visual_builder_fusion() {
    // From version 5.5 of Avada there is a filter to manage output for cookie consent
    $avada_theme = \wp_get_theme( \basename( \get_template_directory() ) );
    if ( \version_compare( $avada_theme->get( 'Version' ), '5.5', '<' ) ) {
      global $shortcode_tags;
      if ( \is_array( $shortcode_tags ) ) {
        if ( \array_key_exists( 'fusion_map', $shortcode_tags ) ) {
          $this->object_references[ 'fusion_map' ] = $shortcode_tags['fusion_map'][0];

          \remove_shortcode( 'fusion_map' );
          \add_shortcode( 'fusion_map', array( $this, 'filter_builder_shortcode_fusion_maps' ) );
        }
      }
    } else {
      \add_filter( 'privacy_script_embed', array( $this, 'filter_builder_output_fusion_maps' ), 10, 5 );    
    }
  }

  public function manage_visual_builder_wpbackery() {
    \add_filter( 'vc_shortcode_content_filter_after', array( $this, 'filter_builder_shortcode_wpbackery_advanced_maps' ), 10, 2 );
  }

  private function is_to_block( $sources, $force_block = false ) {
    if ( $force_block ) return $force_block;

    if ( ! \is_array( $sources ) ) $sources = array( $sources );

    foreach ( $sources as $src ) {
      foreach( $this->sources_to_block as $source_to_block ) {
        if ( \strpos( $src, $source_to_block['pattern'] ) !== false ) {
          switch ( $source_to_block['pattern'] ) {
            case 'google-analytics.com/ga.js':
            case 'google-analytics.com/analytics.js':
            case 'googletagmanager.com/gtag/js':
            case 'cache/busting/google-tracking/ga-':
                            
              // Anonymized Google Analytics will not be blocked
              if ( \preg_match( '~\( ?[\',"]set[\',"] ?, ?[\',"]anonymizeIp[\',"] ?, ?true ?\)|\_gat\._anonymizeIp~im', $src ) )
                return false;

              break;
          }
          return $source_to_block;
        }
      }
    }

    return false;
  }

  public function manage_visual_builder_cornerstone() {
    if ( \function_exists( 'x_shortcode_google_map' ) ) {
      \remove_shortcode( 'x_google_map' );
      \add_shortcode( 'x_google_map', array( $this, 'filter_builder_shortcode_cornerstone_maps' ) );
    }
  }

  public function manage_theme_bridge_map() {
    if ( \function_exists( 'qode_google_map' ) || \function_exists( 'bridge_core_google_map' ) ) {
      \remove_shortcode( 'qode_google_map' );
      \add_shortcode( 'qode_google_map', array( $this, 'filter_theme_shortcode_bridge_maps' ) );
    }
  }

  public function render_shortcode_cookie_block( $atts, $content ) {
    $atts = \shortcode_atts( array(
      'type'             => '',
    ), $atts, 'cookie_block' );

    return $content;
  }

  public function render_shortcode_cookie_banner_link( $atts ) {
    $atts = \shortcode_atts( array(
      'class'     => '',
      'style'     => '',
      'text'      => \__( 'Cookie preferences', 'smart-cookie-kit' )
    ), $atts, 'cookie_banner_link' );

    if ( '' != $atts['class'] )
      $atts['class'] .= ' ';
    $atts['class'] .= self::CookiePreferencesClass;

    $properties = array();
    $properties[] = 'href="#"';
    $properties[] = \sprintf( 'class="%s"', \esc_attr( $atts['class'] ) );
    if ( '' != $atts['style'] )
      $properties[] = \sprintf( 'style="%s"', \esc_attr( $atts['style'] ) );

    return \sprintf(
      '<a %s>%s</a>',
      \implode( ' ', $properties ),
      \esc_html( $atts['text'] )
    );
  }

  public function filter_builder_shortcode_avia_maps( $atts, $content = "", $shortcodename = "", $fake = false ) {
    global $builder;
    if ( ! $builder ) return '';

    // In version 4.4 Avia builder has optimized the resources used in the frontend.
    $enfold_theme = \wp_get_theme( \basename( \get_template_directory() ) );
    if ( \version_compare( $enfold_theme->get( 'Version' ), '4.4', '<' ) ) {
      return $this->block_shortcode(
        $builder->shortcode_class['avia_sc_gmaps']->shortcode_handler_prepare( $atts, $content, $shortcodename, $fake ),
        array(
          'service_name' => 'Google Maps for Avia builder from 4.4',
          'unlock_with'  => 'profiling',
          'sections'     => array(
            array( 'type' => 'html', 'pattern' => '.avia-google-map-container', 'class' => 'avia-google-map-container', 'rewrite' => 'class', 'rewrite_needle' => 'avia-google-map-container' )
          )
        )
      );
    } else {
      return $this->block_shortcode(
        $builder->shortcode_class['avia_sc_gmaps']->shortcode_handler_prepare( $atts, $content, $shortcodename, $fake ),
        array(
          'service_name' => 'Google Maps for Avia builder from 4.4',
          'unlock_with'  => 'profiling',
          'sections'     => array(
            array( 'type' => 'html', 'pattern' => '.avia-google-map-container', 'class' => 'avia-google-map-container' )
          )
        )
      );
    }
  }

  public function filter_builder_shortcode_divi_maps( $output ) {
    return $this->block_shortcode(
      $output,
      array(
        'service_name' => 'Google Maps',
        'unlock_with'  => 'statistics',
        'sections'     => array(
          array( 'type' => 'html', 'pattern' => '.et_pb_map_container', 'class' => 'et_pb_map_container' )
        )
      )
    );
  }

  public function filter_builder_output_fusion_maps( $html, $element_type, $echo, $width, $height ) {
    if ( 'gmaps' != $element_type ) return $html;

    return $this->block_shortcode(
      $html,
      array(
        'service_name' => 'Google Maps',
        'unlock_with'  => 'statistics',
        'sections'     => array(
          array( 'type' => 'script', 'pattern' => 'script'            , 'class' => ''                  ),
          array( 'type' => 'html'  , 'pattern' => '.fusion-google-map', 'class' => 'fusion-google-map' )
        )
      )
    );
  }

  public function filter_builder_shortcode_fusion_maps( $args, $content = '' ) {
    if ( \array_key_exists( 'fusion_map', $this->object_references ) ) {
      if ( \is_callable( array( $this->object_references[ 'fusion_map' ], 'render' ), true ) ) {
        return $this->block_shortcode(
          $this->object_references[ 'fusion_map' ]->render( $args, $content ),
          array(
            'service_name' => 'Google Maps',
            'unlock_with'  => 'statistics',
            'sections'     => array(
              array( 'type' => 'script', 'pattern' => 'script'            , 'class' => ''                  ),
              array( 'type' => 'html'  , 'pattern' => '.fusion-google-map', 'class' => 'fusion-google-map' )
            )
          )
        );
      }
    }

    return '';
  }

  public function filter_builder_shortcode_wpbackery_advanced_maps( $content, $shortcode ) {
    if ( 'mk_advanced_gmaps' != $shortcode ) return $content;

    return $this->block_shortcode(
      $content,
      array(
        'service_name' => 'Google Maps',
        'unlock_with'  => 'statistics',
        'sections'     => array(
          array( 'type' => 'html', 'pattern' => '.mk-advanced-gmaps', 'class' => 'js-el', 'rewrite' => 'class', 'rewrite_needle' => 'js-el' )
        )
      )
    );
  }

  public function filter_builder_shortcode_cornerstone_maps( $atts, $content = null ) {
    return $this->block_shortcode(
      \x_shortcode_google_map( $atts, $content ),
      array(
        'service_name' => 'Google Maps',
        'unlock_with'  => 'statistics',
        'sections'     => array(
          array( 'type' => 'html', 'pattern' => '.x-google-map'       , 'class' => 'x-google-map'       , 'rename' => 'data-x-element' ),
          array( 'type' => 'html', 'pattern' => '.x-google-map-marker', 'class' => 'x-google-map-marker', 'rename' => 'data-x-element' )
        )
      )
    );
  }

  public function filter_theme_shortcode_bridge_maps( $atts, $content = null ) {
    $bridge_theme = \wp_get_theme( \basename( \get_template_directory() ) );
    if ( \version_compare( $bridge_theme->get( 'Version' ), '18.0.9', '<' ) ) {
      return $this->block_shortcode(
        \qode_google_map( $atts, $content ),
        array(
          'service_name' => 'Google Maps',
          'unlock_with'  => 'statistics',
          'sections'     => array(
            array( 'type' => 'html', 'pattern' => '.google_map_shortcode_holder', 'class' => 'google_map_shortcode_holder' )
          )
        )
      );
    } else {
      return $this->block_shortcode(
        \bridge_core_google_map( $atts, $content ),
        array(
          'service_name' => 'Google Maps',
          'unlock_with'  => 'statistics',
          'sections'     => array(
            array( 'type' => 'html', 'pattern' => '.google_map_shortcode_holder', 'class' => 'google_map_shortcode_holder' )
          )
        )
      );
    }
  }

  public function filter_plugin_shortcode_google_maps_gold_putwpgm( $atts ) {
    if ( \array_key_exists( 'display_map', $this->object_references ) ) {
      if ( \is_callable( array( $this->object_references[ 'display_map' ], 'wpgmp_display_map' ), true ) ) {
        return $this->block_shortcode(
          $this->object_references[ 'display_map' ]->wpgmp_display_map( $atts ),
          array(
            'service_name' => 'Google Maps',
            'unlock_with'  => 'statistics',
            'sections'     => array(
              array( 'type' => 'script', 'pattern' => 'script'              , 'class' => ''  ),
              array( 'type' => 'html'  , 'pattern' => 'div', 'class' => ''  )
            )
          )
        );
      }
    }
  }
  public function filter_plugin_shortcode_google_maps_gold_displaymap( $atts, $shortcode ) {
    if ( \array_key_exists( 'put_wpgm', $this->object_references ) ) {
      if ( \is_callable( array( $this->object_references[ 'put_wpgm' ], 'wpgmp_show_location_in_map' ), true ) ) {
        return $this->block_shortcode(
          $this->object_references[ 'put_wpgm' ]->wpgmp_show_location_in_map( $atts ),
          array(
            'service_name' => 'Google Maps',
            'unlock_with'  => 'statistics',
            'sections'     => array(
              array( 'type' => 'script', 'pattern' => 'script'              , 'class' => ''  ),
              array( 'type' => 'html'  , 'pattern' => 'div', 'class' => ''  )
            )
          )
        );
      }
    }
  }

  private function block_shortcode( $html, $details, $check_for_placeholder = true ) {
    $html_obj = str_get_html( $html, true, true, DEFAULT_TARGET_CHARSET, false );

    if ( \is_object( $html_obj ) ) {
      $blocked_index = 0;
      if ( \array_key_exists( $details['service_name'], $this->compatibility_check ) ) {
        $blocked_index = $this->compatibility_check[ $details['service_name'] ]['index'];
      } else {
        $blocked_index = $this->get_blocked_index( true );
        $this->compatibility_check[ $details['service_name'] ] = array( 'ref' => $details['service_name'], 'unlock' => $details[ 'unlock_with' ], 'index' => $blocked_index );
      }

      $pattern_found = false;

      foreach ( $details['sections'] as $section ) {
        $items = $html_obj->find( $section['pattern'] );
        if ( \is_array( $items ) ) {
          foreach ( $items as $item ) {
            switch ( $section['type'] ) {
              case 'html':
                $this->block_tag_html( $item, \array_merge( $details, $section ), $blocked_index );
                $pattern_found = true;
                break;
              case 'script':
                $this->block_tag_script( $item, \array_merge( $details, $section ), true, $blocked_index, 6 );
                $pattern_found = true;
                break;
            }
          }
        }
      }

      $html = $html_obj;      
      if ( $pattern_found && $check_for_placeholder )
        $html = $this->not_available_tag( $blocked_index, 'image', $details['service_name'] ) . $html;
    }
    return $html;
  }

  private function block_shortcode_html( $html, $details, $placeholder = true ) {
    $html_obj = str_get_html( $html, true, true, DEFAULT_TARGET_CHARSET, false );

    if ( \is_object( $html_obj ) ) {
      $items = $html_obj->find( $details['pattern'] );
      if ( \is_array( $items ) ) {
        $blocked_index = 0;
        if ( \array_key_exists( $details['service_name'], $this->compatibility_check ) ) {
          $blocked_index = $this->compatibility_check[ $details['service_name'] ]['index'];
        } else {
          $blocked_index = $this->get_blocked_index( true );
          $this->compatibility_check[ $details['service_name'] ] = array( 'ref' => $details['service_name'], 'unlock' => $details[ 'unlock_with' ], 'index' => $blocked_index );
        }

        foreach ( $items as $item ) {
          $this->block_tag_html( $item, $details, $blocked_index );
          if ( $placeholder )
            $item->outertext .= $this->not_available_tag( $blocked_index, 'image', $details['service_name'] );
        }
      }

      $html = $html_obj;
    }
    return $html;
  }

  private function block_shortcode_script( $html, $details, $placeholder = true ) {
    $html_obj = str_get_html( $html, true, true, DEFAULT_TARGET_CHARSET, false );
    if ( \is_object( $html_obj ) ) {
      $items = $html_obj->find( 'script' );
      if ( \is_array( $items ) ) {
        $blocked_index = 0;
        if ( \array_key_exists( $details['service_name'], $this->compatibility_check ) ) {
          $blocked_index = $this->compatibility_check[ $details['service_name'] ]['index'];
        } else {
          $blocked_index = $this->get_blocked_index( true );
          $this->compatibility_check[ $details['service_name'] ] = array( 'ref' => $details['service_name'], 'unlock' => $details[ 'unlock_with' ], 'index' => $blocked_index );
        }

        foreach ( $items as $item ) {
          $this->block_tag_script( $item, $details, true, $blocked_index, 6 );
          if ( $placeholder )
            $item->outertext .= $this->not_available_tag( $blocked_index, 'image', $details['service_name'] );
        }

        return $html_obj;
      }
    }

    /*
    $html_obj = str_get_html( $html, true, true, false );

    if ( \is_object( $html_obj ) ) {
      $items = $html_obj->find( 'script' );
      if ( \is_array( $items ) ) {
        $blocked_index = 0;
        if ( \array_key_exists( $details['service_name'], $this->compatibility_check ) ) {
          $blocked_index = $this->compatibility_check[ $details['service_name'] ]['index'];
        } else {
          $blocked_index = $this->get_blocked_index( true );
          $this->compatibility_check[ $details['service_name'] ] = array( 'ref' => $details['service_name'], 'unlock' => $details[ 'unlock_with' ], 'index' => $blocked_index );
        }

        foreach ( $items as $item ) {
          $this->block_tag_html( $item, $details, $blocked_index );
          $item->outertext .= $this->not_available_tag( $blocked_index, 'image', $details['service_name'] );
        }
      }

      $html = $html_obj;
    }
    */

    return $html;
  }

  private function block_tag_html( &$item, $details, $blocked_index ) {
    $item->{'data-sck_unlock'}   = $details[ 'unlock_with'  ];
    $item->{'data-sck_ref'}      = $details[ 'service_name' ];
    $item->{'data-sck_index'}    = $blocked_index;
    $item->{'data-sck_type'}     = 6;

    if ( \array_key_exists( 'rewrite', $details ) && \array_key_exists( 'rewrite_needle', $details ) )
      $item->{ $details['rewrite'] } = \str_replace( $details['rewrite_needle'], '', $item->{ $details['rewrite'] } );

    if ( ! empty( $item->class ) ) $item->class .= ' ';
    $item->class .= $details['class'] . '_' . self::BlockedTagClass;

    if ( ! empty( $item->class ) ) $item->class .= ' ';
    $item->class .= self::BlockedTagClass;

    if ( ! empty( $item->class ) ) $item->class .= ' ';
    $item->class .= self::BlockedTagClass . '_' . $blocked_index;

    if ( \array_key_exists( 'rename', $details ) )
      $item->outertext = \str_replace( $details['rename'], $details['rename'] . '_' . self::BlockedTagClass, $item->outertext );
  }

  private function block_tag_script( &$item, $details, $embedded, $blocked_index, $type = false ) {
    $append  = '';

    if ( $embedded ) {
      $item->type                  = 'text/blocked';
      $item->{'data-sck_type'}     = $type ? $type : 1;

      if ( \array_key_exists( 'map_to_ignore', $details ) ) {
        foreach ( $details['map_to_ignore'] as $needle ) {
          if ( strrpos( $item->innertext, $needle ) ) {
            $item->{'data-sck_type'} = 6;

            if ( ! empty( $item->class ) ) $item->class .= ' ';
            $item->class .= 'initMap_' . self::BlockedTagClass;

            $item->outertext .= $this->not_available_tag( $blocked_index, $item->tag, $details['service_name'] );

            break;
          }
        }
      }

      if ( \array_key_exists( 'fbq_fallback', $details ) ) {
        $options = Options::get();
        if ( $options['facebookPixelCompatibilityMode'] ) {
          static $fb_compatibility_mode_echoed;

          if ( \is_null( $fb_compatibility_mode_echoed ) ) {
            $fb_compatibility_mode_echoed = true;
  
            $item->innertext = '
              /*SCK*/window.fbq_queue=window.fbq.queue;delete window.fbq;/*/SCK*/'
              . $item->innertext
              . '/*SCK*/for(let q=0;q<window.fbq_queue.length;q++){fbq(...window.fbq_queue[q]);}delete window.fbq_queue;/*/SCK*/'
            ;
            $append .= '<script>/*SCK*/!function(f,n){n=f.fbq=f.fbq=function(){n.queue.push(arguments);};n.push=n;n.queue=[];}(window);/*/SCK*/</script>';
          }
        }
      }
    } else {
      $item->{'data-blocked'}      = \str_replace( array( '#' . Options::CookieBlockClass_Statistics, '#' . Options::CookieBlockClass_Profiling, '#' . Options::CookieBlockClass_StatsAndProf ), '', $item->src );
      $item->src                   = $this->plugin_base_url . 'res/empty.js';
      $item->{'data-sck_type'}     = $type ? $type : 2;

      if ( \in_array( 'fastestcache', self::$enabled_plugins ) ) {
        $item->{'data-wpfc-render'} = 'false';
      }
    }

    $item->{'data-sck_unlock'}   = $details[ 'unlock_with'  ];
    $item->{'data-sck_ref'}      = $details[ 'service_name' ];
    $item->{'data-sck_index'}    = $blocked_index;

    if ( ! empty( $item->class ) ) $item->class .= ' ';
    $item->class .= self::BlockedTagClass;

    if ( $append ) {
      $item->outertext = $item->outertext . $append;
    }

    if ( \array_key_exists( $details['service_name'], $this->html_tag_reference ) ) {
      if ( ! \in_array( $details['service_name'], $this->html_tag_added ) ) {
        $this->html_tag_to_search[] = array( 'ref' => $blocked_index, 'name' => $details['service_name'], 'tags' => $this->html_tag_reference[ $details['service_name'] ] );
        $this->html_tag_added[] = $details['service_name'];
      }
    }
  }

  private function block_tag_images( &$item, $details, $blocked_index ) {
    $item->{'data-blocked'}      = $item->src;
    $item->src                   = $this->plugin_base_url . 'res/empty.gif';
    $item->{'data-sck_unlock'}   = $details[ 'unlock_with'  ];
    $item->{'data-sck_ref'}      = $details[ 'service_name' ];
    $item->{'data-sck_index'}    = $blocked_index;
    $item->{'data-sck_type'}     = 3;

    if ( ! empty( $item->class ) ) $item->class .= ' ';
    $item->class .= self::BlockedTagClass;
  }

  private function block_tag_iframe( &$item, $details, $blocked_index ) {
    foreach ( array( 'src', 'data-src' ) as $property ) {
      if ( $item->{ $property } ) {
        $item->{'data-blocked'}  = $item->{ $property };
        $item->{ $property }     = '';
        break;
      }
    }
    $item->src                   = $this->plugin_base_url . 'res/empty.html';
    $item->{'data-sck_unlock'}   = $details[ 'unlock_with'  ];
    $item->{'data-sck_ref'}      = $details[ 'service_name' ];
    $item->{'data-sck_index'}    = $blocked_index;
    $item->{'data-sck_type'}     = 4;

    if ( ! empty( $item->class ) ) $item->class .= ' ';
    $item->class .= self::BlockedTagClass;
  }

  private function block_tag( $html, $block_details ) {
    $modified_html = '';

    $elements = $html->find( "*" );
    if ( \is_array( $elements ) ) {
      foreach ( $elements as $e ) {
        $blocked_index = $this->get_blocked_index( true );

        switch( $e->tag ){
          case 'script':
            $this->block_tag_script( $e, $block_details, true, $blocked_index );
            $modified_html  .= $this->not_available_tag( $blocked_index, $e->tag, $block_details['service_name'] );
            $modified_html  .= $e->outertext;
          break;

          case 'img':
            $this->block_tag_images( $e, $block_details, $blocked_index );
            $modified_html  .= $this->not_available_tag( $blocked_index, $e->tag, $block_details['service_name'] );
            $modified_html  .= $e->outertext;
          break;

          case 'iframe':
            $this->block_tag_iframe( $e, $block_details, $blocked_index );
            $modified_html  .= $this->not_available_tag( $blocked_index, $e->tag, $block_details['service_name'] );
            $modified_html  .= $e->outertext;
          break;

          default:
            $modified_html  .= $this->not_available_tag( $blocked_index, 'noscript', $block_details['service_name'] );
            $modified_html  .= '<noscript class="' . self::BlockedTagClass . '" data-sck_type="5" data-sck_index="' . $blocked_index . '">';
            $modified_html  .= $e->outertext;
            $modified_html  .= '</noscript>';
          break;
        }
      }
    }

    return $modified_html;
  }

  private function not_available_tag( $ref, $tag_type, $blocked_service ) {
    if ( ! self::$sck_preferences['managePlaceholders'] ) return '';

    $tag = '';
    $content = \preg_replace(
      array( '~\%SERVICE_NAME\%~mi' ),
      array( $blocked_service       ),
      self::$sck_preferences['blockedContentPlaceholderText']
    );
    /*
    $content .= '<p><strong>' . \esc_html__( 'Some contents or functionalities here are not available due to your cookie preferences!', 'smart-cookie-kit' ) . '</strong></p>'
              . '<p>'
                . \sprintf( \esc_html__( 'This happens because the functionality/content marked as "%1$s" uses cookies that you choosed to keep disabled. In order to view this content or use this functionality, please enable cookies: ', 'smart-cookie-kit' ), $blocked_service )
                . '<a href="#" class="' . self::CookiePreferencesClass . '">' . \esc_html__( 'click here to open your cookie preferences', 'smart-cookie-kit' ) . '</a>.'
              . '</p>';
    */

    switch ( $tag_type ) {
      case 'div':
      case 'image':
      case 'iframe':
      case 'script':
      case 'noscript':
        $tag = \sprintf( '<div class="%2$s %2$s_%1$s">%3$s</div>', $ref, self::NotAvailableClass, $content );
        break;
    }

    return \apply_filters( 'filter_text_for_web', $tag );
  }

  private function parse_tags( $html ) {
    \preg_match_all( self::CookieBlockPattern, $html, $blocks );
    if ( \is_array( $blocks[1] ) ) {
      $block_list = array();

      foreach ( $blocks[1] as $block )
        $block_list[] = $this->block_tag(
          str_get_html( $block, true, true, DEFAULT_TARGET_CHARSET, false ),
          array( 'service_name' => \esc_html__( 'Custom blocked script', 'smart-cookie-kit' ), 'unlock_with' => '' )
        );

      if ( \count( $blocks[1] ) >= 1 && \count( $block_list ) >= 1 )
        $html = \strtr( $html, \array_combine( $blocks[1], $block_list ) );
    }
    return $html;
  }

  private function parse_scripts( $html ) {
    $html_obj = str_get_html( $html, true, true, DEFAULT_TARGET_CHARSET, false );

    if ( \is_object( $html_obj ) ) {
      $scripts = $html_obj->find( 'script' );
      //$noscripts = $html_obj->find( 'noscript' );
      $noscripts = array();

      if ( !\is_array( $scripts ) ) $scripts = array();
      if ( !\is_array( $noscripts ) ) $noscripts = array();

      foreach ( \array_merge( $scripts, $noscripts ) as $item ) {
        if ( \in_array( $item->type, array( 'application/ld+json' ) ) )
          continue;

        $item_classes = \explode( ' ', $item->class );
        if ( \in_array( Options::CookieIgnoreClass, $item_classes ) )
          continue;

        $force_block   = false;
        $block_classes = array();
        if ( \in_array( Options::CookieBlockClass_Statistics, $item_classes ) )
          $block_classes[] = 'statistics';
        if ( \in_array( Options::CookieBlockClass_Profiling , $item_classes ) )
          $block_classes[] = 'profiling' ;
        if ( ! empty( $block_classes ) ) {
          $force_block = array(
            'service_name' => 'Custom features',
            'unlock_with'  => \implode( ',', $block_classes )
          );
        }

        $content = $item->innertext;
        $content = \trim( $content );

        if ( empty( $content ) ) { // external resource
          if ( $item->src ) {
            $block_details = $this->is_to_block( $item->src, $force_block );
            if ( $block_details !== false ) {
              //$blocked_index = $this->get_blocked_index( true );

              $blocked_index = 0;
              if ( \array_key_exists( $block_details['service_name'], $this->compatibility_check ) ) {
                $blocked_index = $this->compatibility_check[ $block_details['service_name'] ]['index'];
              } else {
                $blocked_index = $this->get_blocked_index( true );
                $this->compatibility_check[ $block_details['service_name'] ] = array( 'ref' => $block_details['service_name'], 'unlock' => $block_details[ 'unlock_with' ], 'index' => $blocked_index );
              }

              $this->block_tag_script( $item, $block_details, false, $blocked_index );
            }
          }
        } else { // inline code
          $block_details = $this->is_to_block( $content, $force_block );
          if ( $block_details !== false ) {
            //$blocked_index = $this->get_blocked_index( true );          

            $blocked_index = 0;
            if ( \array_key_exists( $block_details['service_name'], $this->compatibility_check ) ) {
              $blocked_index = $this->compatibility_check[ $block_details['service_name'] ]['index'];
            } else {
              $blocked_index = $this->get_blocked_index( true );
              $this->compatibility_check[ $block_details['service_name'] ] = array( 'ref' => $block_details['service_name'], 'unlock' => $block_details[ 'unlock_with' ], 'index' => $blocked_index );
            }

            $this->block_tag_script( $item, $block_details, true, $blocked_index );
          }
        }
      }

      $html = $html_obj;
    }
    return $html;
  }

  private function parse_images( $html ) {
    $html_obj = str_get_html( $html, true, true, DEFAULT_TARGET_CHARSET, false );

    if ( \is_object( $html_obj ) ) {
      $items = $html_obj->find( 'img' );
      if ( \is_array( $items ) ) {
        foreach ( $items as $item ) {
          $block_details = $this->is_to_block( $item->src );
          if ( $block_details !== false ) {
            $blocked_index = $this->get_blocked_index( true );

            $this->block_tag_images( $item, $block_details, $blocked_index );
            $item->outertext .= $this->not_available_tag( $blocked_index, 'image', $block_details['service_name'] );
          }
        }
      }

      $html = $html_obj;
    }
    return $html;
  }

  private function parse_iframes( $html ) {
    $html_obj = str_get_html( $html, true, true, DEFAULT_TARGET_CHARSET, false );

    if ( \is_object( $html_obj ) ) {
      $iframes = $html_obj->find( 'iframe' );
      if ( \is_array( $iframes ) ) {
        foreach ( $iframes as $iframe ) {
          $block_details = $this->is_to_block( array( $iframe->src, $iframe->{'data-src'} ) );
          if ( $block_details !== false ) {
            $blocked_index = $this->get_blocked_index( true );

            $this->block_tag_iframe( $iframe, $block_details, $blocked_index );
            $iframe->outertext .= $this->not_available_tag( $blocked_index, 'iframe', $block_details['service_name'] );
          }
        }
      }

      $html = $html_obj;
    }
    return $html;
  }

  public function buffer_set() {
    if ( \ob_start( array( $this, 'buffer_scan' ), 1048576 ) ) {
      $this->buffer_set = true;
    }
  }
  public function buffer_unset() {
    if ( $this->buffer_set ) {
      \ob_end_flush();
    }
  }
  public function buffer_scan( $buffer ) {

    $buffer = $this->parse_tags( $buffer );
    $buffer = $this->parse_scripts( $buffer );
    $buffer = $this->parse_images( $buffer );
    $buffer = $this->parse_iframes( $buffer );

    return $buffer;
  }
}