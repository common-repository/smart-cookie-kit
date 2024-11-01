<?php
namespace NMod\SmartCookieKit;

if ( ! \defined( 'ABSPATH' ) ) exit;

?>
<h2><?php \esc_html_e( 'Export settings', 'smart-cookie-kit' ) ?></h2>
<p><?php \esc_html_e( 'You can copy the text in the field below to import graphic and logic settings (translations will not be included) in another WordPress site.', 'smart-cookie-kit' ) ?></p>
<p><?php \esc_html_e( 'Be sure to copy ALL the field content!', 'smart-cookie-kit' ) ?></p>
<textarea class="large-text code" rows="8"><?php echo \json_encode( Options::get_for_export() );  ?></textarea>
<hr />
<h2><?php \esc_html_e( 'Import settings', 'smart-cookie-kit' ) ?></h2>
<p><small><?php \esc_html_e( 'Click on the shortcode you are interested in to see more details.', 'smart-cookie-kit' ) ?></small></p>


<script type="text/javascript">
  jQuery( function() {
    jQuery( '.accordion' ).accordion( { header: 'h4', collapsible: true, active: 'none' } );

<?php
  $plugin_data = \get_plugin_data( __DIR__ . '/plugin.php', false, $translate = false );
  $plugin_version_css = \str_replace( '.', '-', $plugin_data['Version'] );
?>
  } );
</script>