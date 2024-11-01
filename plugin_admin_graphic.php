<?php
namespace NMod\SmartCookieKit;

if ( ! \defined( 'ABSPATH' ) ) exit;

$this->render_backend_options_form( array(
  'name'    => \esc_html__( 'Graphic options', 'smart-cookie-kit' ),
  'fields'  => 'sck-option_v2_group',
  'section' => array( 'nmod_sck_graphic_general_opts', 'nmod_sck_graphic_mobile_opts', 'nmod_sck_graphic_desktop_opts' ),
  'opt_ref' => 2,
) );


/*
  <script>
    jQuery( function() {
      jQuery( '#cookiePolicyPageID' ).on( 'change', function() {
        jQuery( '#cookiePolicyPageURL' ).prop( 'disabled', -1 != jQuery( this ).val() );
        jQuery( '#cookiePolicyLinkText' ).prop( 'disabled', 0 == jQuery( this ).val() );
      } );
    } );
  </script>

  <script>
    jQuery( function() {
      jQuery( '#cookiePolicyPageID_Titles' ).suggest( window.ajaxurl + "?action=nmod_sck_search_for_policy_pages", { multiple: true, multipleSep: "||" } );
    } );
  </script>

*/

