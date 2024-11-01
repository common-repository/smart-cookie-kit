<?php
namespace NMod\SmartCookieKit;

/**
**  Plugin Name: Smart Cookie Kit
**  Plugin URI: http://smartcookiekit.it/
**  Description: [GDPR compliant!] Preventive block of cookies and privacy/cookie policy acceptance. Works with any cache system!
**  Author: Nicola Modugno
**  Author URI: https://nicolamodugno.it
**  Version: 2.3.2
**  Requires at least: 4.6
**  License: GPL2
*/

/*
\ini_set('display_errors','1');
\ini_set('display_startup_errors','1');
\error_reporting (E_ALL);
*/

if ( ! \defined( 'ABSPATH' ) ) exit;

foreach ( array(
  array(
    'direct'  => array( 'name' => 'cornerstone', 'value' => '1' ),
    'async'   => array(
      'action'  => 'cs_render_element'
    ),
  ),
  array(
    'direct'  => array( 'name' => 'et_fb', 'value' => '1' ),
  )
) as $rule ) {

  if ( \array_key_exists( $rule['direct']['name'], $_GET ) ) {
    if ( $_GET[ $rule['direct']['name'] ] == $rule['direct']['value'] ) {
      return;
    } else {
      /*
      if ( \array_key_exists( 'referer', $async ) ) {
        foreach ( $rule['async'] as $async_name => $async_value ) {
          echo '<br>'.$_GET[ $async_name ] . '=' . $async_value;
          echo '<br>'.$_SERVER['referer'];
          echo '<br>'.$rule['direct']['name'] . '=' . $rule['direct']['value'];
          if ( \array_key_exists( $async_name, $_GET ) ) {
            if ( $_GET[ $async_name ] == $async_value ) {
              if ( false !== strpos( $_SERVER['referer'], $rule['direct']['name'] . '=' . $rule['direct']['value'] ) ) {
                //return;
              }
            }          
          }
        }
      }
      */
    }
  }
}


include_once 'plugin_multilanguage.php';
include_once 'plugin_options.php';
if ( ! \is_admin() ) {
  include_once 'plugin_frontend.php';
} else {
  include_once 'plugin_admin.php';
}
