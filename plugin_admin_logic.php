<?php
namespace NMod\SmartCookieKit;

if ( ! \defined( 'ABSPATH' ) ) exit;

$this->render_backend_options_form( array( 
  'name'    => \esc_html__( 'Logic options', 'smart-cookie-kit' ),
  'fields'  => 'sck-option_v2_group',
  'section' => array( 'nmod_sck_working_opts' ),
  'opt_ref' => 2,
) );
?>