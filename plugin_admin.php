<?php
namespace NMod\SmartCookieKit;

if ( ! \defined( 'ABSPATH' ) ) exit;

Admin::init();

class Admin {
  static $instance;

  private $options           = null;
  private $wp_filesystem;
  private $admin_notices;

  static public function init() {
    if ( null === self::$instance )
      self::$instance = new self();

    return self::$instance;
  }

  public function __construct() {
    $this->admin_notices = \get_option( 'SmartCookieKit_AdminNotices', array() );

    \add_action( 'admin_menu'                                                      , array( $this, 'set_backend_menu'                    )        );
    \add_action( 'admin_enqueue_scripts'                                           , array( $this, 'enqueue_style_and_scripts'           )        );
    \add_action( 'admin_init'                                                      , array( $this, 'register_option_page_settings'       )        );

    \add_action( 'wp_ajax_nmod_sck_privacy_updated'                                , array( $this, 'save_server_log'                     )        );
    \add_action( 'wp_ajax_nopriv_nmod_sck_privacy_updated'                         , array( $this, 'save_server_log'                     )        );
    \add_action( 'wp_ajax_nmod_sck_create_content_post'                            , array( $this, 'create_content_post'                 )        );
    \add_action( 'wp_ajax_nmod_sck_search_for_policy_pages'                        , array( $this, 'search_policy_pages'                 )        );
    \add_action( 'wp_ajax_nmod_sck_dismiss_notice_helpsection'                     , array( $this, 'dismiss_notice_helpsection'          )        );
    \add_action( 'wp_ajax_nmod_sck_dismiss_notice_nginx'                           , array( $this, 'dismiss_notice_nginx'                )        );

    if ( Multilanguage::is_multilanguage() ) {
      \add_action( '\wp_editor_settings'                                              , array( $this, 'manage_contentpost_main_editor'      ), 10, 2 );
      \add_action( 'add_meta_boxes_' . Options::BannerPostType                       , array( $this, 'add_contentpost_custom_metaboxes'    )        );
      \add_action( 'save_post'                                                       , array( $this, 'save_post_customs'                   )        );
    }

    \add_action( 'admin_notices'                                                   , array( $this, 'admin_notice_helpsection'            )        );
    if ( \stristr( \strtolower( $_SERVER['SERVER_SOFTWARE'] ), 'nginx' ) ) {
      \add_action( 'admin_notices'                                                   , array( $this, 'admin_notice_nginx'                  )        );
    }

    \add_action( 'automatewoo/admin/settings/general'                              , array( $this, 'notices_for_automatewoo'             ), 99, 1 );
  }

  public function is_admin_notice_dismissed( $notice_id ) {
    return \array_key_exists( $notice_id, $this->admin_notices ) && 1 == $this->admin_notices[$notice_id];
  }

  public function set_backend_menu() {    
//    add_menu_page( 'Smart Cookie Kit dashboard', 'Smart Cookie Kit', 'manage_options', 'nmod_sck_graphics', array( $this, 'render_backend_page_graphic' ), 'dashicons-welcome-view-site' );
    \add_menu_page( 'Smart Cookie Kit dashboard', 'Smart Cookie Kit', 'manage_options', 'nmod_sck_graphics', array( $this, 'render_backend_page_graphic' ), 'data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAATCAYAAACZZ43PAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAABmJLR0QAAAAAAAD5Q7t/AAAACXBIWXMAAC4jAAAuIwF4pT92AAAAB3RJTUUH4goFDDAcKGtrYgAAA+FJREFUOMs9kktvFWUAQM/3zfvOdO69vdy2lEfTh7fSQhONj0SDNtggS9FANCYmmpjoFpbEf+DKRF24YOEKiyYiuIBIQmIwKgSCktZKayBtKZReet8z883M5wLj+QFncXLE5NwxVA57Q01gSwKpxGgheXtvxTs+WPYPFAN3p2HZWVuxtPQwPn1tNfp8o52x0UpJUo2YnDtGJ4V+TzA7GM09V83OPLW72u8VS6TCIlI5OkvxLSg4Bqvb6u5Xv20furjUXan6BjLT0FSSF/qjk2+Nppf2TezppzhAM4FONyZLFbnWNBPNRjNlT8kaOXGw/9Z4xZrY7KTIbio4NKyOvjOefGpUdqOcIkkUofMcBP8jACFgrZFSDUz/1KHKmZJnYNT2T8v3RtsXBnaUylmwA8cUlMtFVKJIkgTPczFNA6UUAGEYsN1LqVXMnXFKxzj6cu3dFwfyD7JwkGIxZGXlLlevXqNSKVMs9tFstkkShes6CCFYWlohzTSD/X1UCjKUo746YtgutuviFxwWFpb58utfWF27z/jYCGe/+5H5by9QKhVxHJu/lpYRqsvKtm7P32p+bJasbCaRNraUNBotnn1mivfzlOHhIRYW71CrjZJnmlarw/r9DWZffYnxoZD5G/X5K8vdm2bB1IMKEyEE3W6PXcNDZFmOY9v8cP4Sc68dpNFssVV/TKvVAQRaFKlV7Vq5YGBqLTLQAEgpybWm/nibBw8fceT1WRzHIctzFhfvMHPgacI+n/V6l4WHyZXDNR/ZVGLFRaH1E4lf8Fhb2+D7cxcJAp+wL0AA585f5vbC31RLAZvtbOHXe71TzSjHmJmZ2jMZJrOpE2JZFlEUUy4XmayNUfA84iTBskym9k0wPDSAFBCprLu4mXy21ky1vL5ln95qq8SMGgjDJI5jdlT6mZ6qAZBmGVprxsdGCMOARidmMDBH3tzf93O9myKvb4p7Z+96J2htQtTCLfjEcUyj0XrSRTzZsdVqEycpvmNQDQwileutTo4xNDHN7W3rd0neGTMbh/sKNnbBBySgEYBpCHxbUnQkPZVzebl38ps/2h/eb2UYA2P7IM9JDPcqQpxN2q1hm2zMs4RRcG0cywQhdDPmzzv17IvL/0THb67HP+ksoxEpxN5X3iCOOnhCUQkDip7JkKPYGVpuKq2yV/CavdzqrD7usdGIafcSur0ezV6ClhamEBIJ3oOtxvT6o2bVMA1PCNGT0ujE3XYK2jRdP9BZWtB5LrSmLQVbjuvedl2jY+YqJs9U2ZL5R6bOn8/TeFeW5RWVa/7rR9bdxjAkhpR1acg1IeQNx7Y+MU278y813NCi52HQlwAAAABJRU5ErkJggg==' );
    \add_submenu_page( 'nmod_sck_graphics', \esc_html__( 'Graphic settings', 'smart-cookie-kit' ), \esc_html__( 'Graphic settings', 'smart-cookie-kit' ), 'manage_options', 'nmod_sck_graphics'       , array( $this, 'render_backend_page_graphic'         ) );
    \add_submenu_page( 'nmod_sck_graphics', \esc_html__( 'Logic settings'  , 'smart-cookie-kit' ), \esc_html__( 'Logic settings'  , 'smart-cookie-kit' ), 'manage_options', 'nmod_sck_logics'         , array( $this, 'render_backend_page_logic'           ) );
//    \add_submenu_page( 'nmod_sck_graphics', \esc_html__( 'Import/Export'   , 'smart-cookie-kit' ), \esc_html__( 'Import/Export'   , 'smart-cookie-kit' ), 'manage_options', 'nmod_sck_import_export'  , array( $this, 'render_backend_page_import_export'   ) );
    \add_submenu_page( 'nmod_sck_graphics', \esc_html__( 'Help and support', 'smart-cookie-kit' ), \esc_html__( 'Help and support', 'smart-cookie-kit' ), 'manage_options', 'nmod_sck_help'           , array( $this, 'render_backend_page_help'            ) );

//    \add_options_page( 'Smart Cookie Kit options page', 'Smart Cookie Kit', 'manage_options', 'nmod_sck_opts', array( $this, 'RenderOptionPage' ) );
  }

  public function enqueue_style_and_scripts() {
    \wp_register_style( 'nmod_sck_be_style', \plugin_dir_url( __FILE__ ) . 'css/style_admin.2019041101.css', array(), null );
    \wp_enqueue_style( 'nmod_sck_be_style' );
  }

  public function admin_notice_helpsection() {
    if ( ! \current_user_can( 'administrator' ) ) return;
    if ( $this->is_admin_notice_dismissed( 0 ) ) return;

    $help_page_link = \sprintf( '<a href="%s" target="_blank">%s</a>', \admin_url( 'admin.php?page=nmod_sck_help' ), \esc_html__( 'Help and support', 'smart-cookie-kit' ) );

?>
<div id="SCK-Notice0" class="notice notice-info is-dismissible">
<p><?php \esc_html_e( 'Hello, Smart Cookie Kit user! I would like to inform you that I am doing my best to maintain this plugin and to improve it.', 'smart-cookie-kit' ) ?></p>
<p>
  <?php \printf( \esc_html__( 'I have reorganized the %s page and I will use it to highlights new features and important notices.', 'smart-cookie-kit' ), $help_page_link ); ?>
  <br /><?php \esc_html_e( 'For this reason, remember to give a look to it to keep updated on the plugin!', 'smart-cookie-kit' ) ?>
</p>
<p><?php \esc_html_e( 'I would be grateful if you could support this plugin by leaving a review. I would also appreciate a donation, but it is just your choice :)', 'smart-cookie-kit' ); ?></p>
<script type="text/javascript">
  jQuery( function() {
    jQuery( '#SCK-Notice0' ).click( 'button.notice-dismiss', function() {
      jQuery.post(
        '<?php echo \admin_url('admin-ajax.php') ?>',
        {
          action: 'nmod_sck_dismiss_notice_helpsection',
          refchk: '<?php echo \wp_create_nonce( 'cdslcsCs cdscWSCew cwEFW"IFwF44334rw' ) ?>'
        },
        function( response ) {
          if ( 1 == response.StatusCode ) {
            jQuery( '#SCK-Notice0' ).slideUp( function() { jQuery( '#SCK-Notice0' ).remove() } );
          } else {
            console.log( response );
            alert( 'An error occurred. Details in JS console' );
          }
        },
        'json'
      );
    } );
  } );
</script>
</div>
<?php
  }

  public function admin_notice_nginx() {
    if ( ! \current_user_can( 'administrator' ) ) return;
    if ( $this->is_admin_notice_dismissed( 1 ) ) return;

    $screen = \get_current_screen();
    if ( 'smart-cookie-kit_page_nmod_sck_help' == $screen->id ) return;

    $help_page_link = \sprintf( '<a href="%s" target="_blank">%s</a>', \admin_url( 'admin.php?page=nmod_sck_help' ), \esc_html__( 'Help and support', 'smart-cookie-kit' ) );
?><div id="SCK-Notice1" class="notice notice-error">
<p><?php \esc_html_e( 'It seems that your site is running on NGINX, so Smart Cookie Kit is not able to protect your log directory from unauthorized access.', 'smart-cookie-kit' ); ?></p>
<p><?php \printf( \esc_html__( 'As this is an high risk notice, only an admin who can manage plugins can dismiss this notice from the %s page of Smart Cookie Kit.', 'smart-cookie-kit' ), $help_page_link ); ?></p>
</div><?php
  }

  public function register_option_page_settings() {
    \register_setting( 'sck-option_v2_group', 'SmartCookieKit_Options_v2', array( 'sanitize_callback' => 'NMod\SmartCookieKit\Options::sanitize_v2' ) );

    \add_settings_section( 'sck-graphic_general_options_section'    , \esc_html__( 'General graphic settings'               , 'smart-cookie-kit' ), array( $this, 'RenderOptionSectionDescriptionForGeneralGraphic'     ), 'nmod_sck_graphic_general_opts'        );
    \add_settings_section( 'sck-graphic_mobile_options_section'     , \esc_html__( 'Graphic settings for mobile devices'    , 'smart-cookie-kit' ), array( $this, 'RenderOptionSectionDescriptionForMobileGraphic'      ), 'nmod_sck_graphic_mobile_opts'         );
    \add_settings_section( 'sck-graphic_desktop_options_section'    , \esc_html__( 'Graphic settings for desktop devices'   , 'smart-cookie-kit' ), array( $this, 'RenderOptionSectionDescriptionForDesktopGraphic'     ), 'nmod_sck_graphic_desktop_opts'        );
    \add_settings_section( 'sck-working_options_section'            , \esc_html__( 'Logic customizing'                      , 'smart-cookie-kit' ), array( $this, 'RenderOptionSectionDescriptionForLogic'              ), 'nmod_sck_working_opts'                );


    if ( Multilanguage::is_multilanguage() )
      \add_settings_field( 'cookieBannerContentID'                     , \esc_html__( 'Banner translations'                             , 'smart-cookie-kit' ), array( $this, 'RenderOption_cookieBannerContentID'               ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'cookieBannerContentID'                          , 'option_name' => 'SmartCookieKit_Options_v2'              ) );

    \add_settings_field( 'cookieBannerText'                          , \esc_html__( 'Banner text'                                     , 'smart-cookie-kit' ), array( $this, 'RenderOption_cookieBannerText'                    ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'cookieBannerText'                               , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'blockedContentPlaceholderText'             , \esc_html__( 'Placeholder text'                                , 'smart-cookie-kit' ), array( $this, 'RenderOption_blockedContentPlaceholderText'       ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'blockedContentPlaceholderText'                  , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cookiePolicyPageID'                        , \esc_html__( 'Policy page'                                     , 'smart-cookie-kit' ), array( $this, 'RenderOption_cookiePolicyPageID'                  ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'cookiePolicyPageID'                             , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cookieEnableButtonText'                    , \esc_html__( 'Accept button text'                              , 'smart-cookie-kit' ), array( $this, 'RenderOption_cookieEnableButtonText'              ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'cookieEnableButtonText'                         , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cookieEnabledButtonText'                   , \esc_html__( 'Accepted button text'                            , 'smart-cookie-kit' ), array( $this, 'RenderOption_cookieEnabledButtonText'             ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'cookieEnabledButtonText'                        , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cookieDisableLinkText'                     , \esc_html__( 'Disable link text'                               , 'smart-cookie-kit' ), array( $this, 'RenderOption_cookieDisableLinkText'               ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'cookieDisableLinkText'                          , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cookieDisabledLinkText'                    , \esc_html__( 'Disabled link text'                              , 'smart-cookie-kit' ), array( $this, 'RenderOption_cookieDisabledLinkText'              ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'cookieDisabledLinkText'                         , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'minimizedSettingsButtonText'               , \esc_html__( 'Minimized settings button text'                  , 'smart-cookie-kit' ), array( $this, 'RenderOption_minimizedSettingsButtonText'         ), 'nmod_sck_graphic_general_opts'         , 'sck-graphic_general_options_section'  , array( 'ver' => 2, 'label_for' => 'minimizedSettingsButtonText'                    , 'option_name' => 'SmartCookieKit_Options_v2'              ) );

    \add_settings_field( 'cssMobileContentPlaceholder'               , \esc_html__( 'CSS for content placeholder'                     , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssContentPlaceholder'               ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileContentPlaceholder'                    , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerBackLayer'                  , \esc_html__( 'CSS for backlayer'                               , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerBacklayer'                  ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerBackLayer'                       , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerContainer'                  , \esc_html__( 'CSS for banner content'                          , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerContainer'                  ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerContainer'                       , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerTextContainer'              , \esc_html__( 'CSS for banner text container'                   , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerTextContainer'              ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerTextContainer'                   , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerText'                       , \esc_html__( 'CSS for banner text'                             , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerText'                       ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerText'                            , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerActionsArea'                , \esc_html__( 'CSS for actions area'                            , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerActionsArea'                ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerActionsArea'                     , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerActionsButtons'             , \esc_html__( 'CSS for buttons container'                       , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerActionsButtons'             ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerActionsButtons'                  , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerAcceptButton'               , \esc_html__( 'CSS for accept button'                           , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerAcceptButton'               ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerAcceptButton'                    , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerAcceptButtonHover'          , \esc_html__( 'CSS for accept button hover state'               , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerAcceptButtonOnHover'        ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerAcceptButtonHover'               , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerCloseLink'                  , \esc_html__( 'CSS for close link'                              , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerCloseLink'                  ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerCloseLink'                       , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileBannerCloseLinkHover'             , \esc_html__( 'CSS for close link hover state'                  , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerCloseLinkOnHover'           ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileBannerCloseLinkHover'                  , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileMinimizedSettingsButton'          , \esc_html__( 'CSS for minimized settings button'               , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssMinimizedSettingsButton'          ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileMinimizedSettingsButton'               , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssMobileMinimizedSettingsButtonHover'     , \esc_html__( 'CSS for minimized settings button hover state'   , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssMinimizedSettingsButtonOnHover'   ), 'nmod_sck_graphic_mobile_opts'          , 'sck-graphic_mobile_options_section'   , array( 'ver' => 2, 'label_for' => 'cssMobileMinimizedSettingsButtonHover'          , 'option_name' => 'SmartCookieKit_Options_v2'              ) );

    \add_settings_field( 'cssDesktopContentPlaceholder'              , \esc_html__( 'CSS for content placeholder'                     , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssContentPlaceholder'               ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopContentPlaceholder'                   , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerBackLayer'                 , \esc_html__( 'CSS for backlayer'                               , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerBacklayer'                  ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerBackLayer'                      , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerContainer'                 , \esc_html__( 'CSS for banner content'                          , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerContainer'                  ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerContainer'                      , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerTextContainer'             , \esc_html__( 'CSS for banner text container'                   , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerTextContainer'              ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerTextContainer'                  , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerText'                      , \esc_html__( 'CSS for banner text'                             , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerText'                       ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerText'                           , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerActionsArea'               , \esc_html__( 'CSS for actions area'                            , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerActionsArea'                ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerActionsArea'                    , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerActionsButtons'            , \esc_html__( 'CSS for buttons container'                       , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerActionsButtons'             ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerActionsButtons'                 , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerAcceptButton'              , \esc_html__( 'CSS for accept button'                           , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerAcceptButton'               ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerAcceptButton'                   , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerAcceptButtonHover'         , \esc_html__( 'CSS for accept button hover state'               , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerAcceptButtonOnHover'        ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerAcceptButtonHover'              , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerCloseLink'                 , \esc_html__( 'CSS for close link'                              , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerCloseLink'                  ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerCloseLink'                      , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopBannerCloseLinkHover'            , \esc_html__( 'CSS for close link hover state'                  , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssBannerCloseLinkOnHover'           ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopBannerCloseLinkHover'                 , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopMinimizedSettingsButton'         , \esc_html__( 'CSS for minimized settings button'               , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssMinimizedSettingsButton'          ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopMinimizedSettingsButton'              , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'cssDesktopMinimizedSettingsButtonHover'    , \esc_html__( 'CSS for minimized settings button hover state'   , 'smart-cookie-kit' ), array( $this, 'RenderOption_cssMinimizedSettingsButtonOnHover'   ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'cssDesktopMinimizedSettingsButtonHover'         , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'plugin_version'                            , ''                                                                           , array( $this, 'RenderOption_pluginVersion'                       ), 'nmod_sck_graphic_desktop_opts'         , 'sck-graphic_desktop_options_section'  , array( 'ver' => 2, 'label_for' => 'plugin_version'                                 , 'option_name' => 'SmartCookieKit_Options_v2'              ) );


    \add_settings_field( 'cookieAcceptedLife'                        , \esc_html__( 'Cookie life'                                     , 'smart-cookie-kit' ), array( $this, 'RenderOption_cookieAcceptedLife'                  ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'cookieAcceptedLife'                             , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'blockGoogleTagManager'                     , \esc_html__( 'Block Google Tag Manager'                        , 'smart-cookie-kit' ), array( $this, 'RenderOption_blockGoogleTagManager'               ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'blockGoogleTagManager'                          , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'blockGoogleReCaptcha'                      , \esc_html__( 'Block Google reCaptcha'                          , 'smart-cookie-kit' ), array( $this, 'RenderOption_blockGoogleReCaptcha'                ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'blockGoogleReCaptcha'                           , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'blockAutomateWooSessionTracking'           , \esc_html__( 'Block AutomateWoo Session tracking'              , 'smart-cookie-kit' ), array( $this, 'RenderOption_blockAutomateWooSessionTracking'     ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'blockAutomateWooSessionTracking'                , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'facebookPixelCompatibilityMode'            , \esc_html__( 'Facebook Pixel compatibility mode (experimental)', 'smart-cookie-kit' ), array( $this, 'RenderOption_facebookPixelCompatibilityMode'      ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'facebookPixelCompatibilityMode'                 , 'option_name' => 'SmartCookieKit_Options_v2'              ) );

        
    \add_settings_field( 'showMinimizedButton'                       , \esc_html__( 'Minimized button for cookie settings'            , 'smart-cookie-kit' ), array( $this, 'RenderOption_showMinimizedButton'                 ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'showMinimizedButton'                            , 'option_name' => 'SmartCookieKit_Options_v2'              ) );


    \add_settings_field( 'addBlockedContentPlaceholder'              , \esc_html__( 'Add placeholders on page'                        , 'smart-cookie-kit' ), array( $this, 'RenderOption_addBlockedContentPlaceholder'        ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'addBlockedContentPlaceholder'                   , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'addBannerBackLayer'                        , \esc_html__( 'Enable banner backlayer'                         , 'smart-cookie-kit' ), array( $this, 'RenderOption_addBannerBackLayer'                  ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'addBannerBackLayer'                             , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'reloadPageWhenDisabled'                    , \esc_html__( 'Reload page to disable services'                 , 'smart-cookie-kit' ), array( $this, 'RenderOption_reloadPageWhenDisabled'              ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'reloadPageWhenDisabled'                         , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'acceptPolicyOnScroll'                      , \esc_html__( 'Implicit consent on scroll'                      , 'smart-cookie-kit' ), array( $this, 'RenderOption_acceptPolicyOnScroll'                ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'acceptPolicyOnScroll'                           , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'saveLogToServer'                           , \esc_html__( 'Save log to server'                              , 'smart-cookie-kit' ), array( $this, 'RenderOption_saveLogToServer'                     ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'saveLogToServer'                                , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
    \add_settings_field( 'pluginDebugMode'                           , \esc_html__( 'Debug mode'                                      , 'smart-cookie-kit' ), array( $this, 'RenderOption_pluginDebugMode'                     ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'pluginDebugMode'                                , 'option_name' => 'SmartCookieKit_Options_v2'              ) );

    \add_settings_field( 'plugin_version'                            , ''                                                                           , array( $this, 'RenderOption_pluginVersion'                       ), 'nmod_sck_working_opts'                 , 'sck-working_options_section'          , array( 'ver' => 2, 'label_for' => 'plugin_version'                                 , 'option_name' => 'SmartCookieKit_Options_v2'              ) );
  }


  public function render_backend_page_graphic() {
    \wp_enqueue_script( 'suggest' );
    $this->render_backend_page( 'graphic' );
  }
  public function render_backend_page_logic() {
    $this->render_backend_page( 'logic' );
  }
  public function render_backend_page_import_export() {
    $this->render_backend_page( 'import_export' );
  }
  public function render_backend_page_help() {
    $this->render_backend_page( 'help' );
  }
  public function render_backend_page( $page ) {
?>
  <div class="wrap nmod_sck_backend">
    <h2>Smart Cookie Kit</h2>
    <?php $this->RenderNoticeDisclaimer(); ?>
    <?php include 'plugin_admin_' . $page . '.php'; ?>
  </div>
<?php
  }

  public function render_backend_options_form( $options ) {
    $this->options = Options::get( $options['opt_ref'] );
?>
  <form method="post" action="options.php">
<?php
    \submit_button();
    \settings_fields( $options['fields'] );
    foreach ( $options['section'] as $section ) {
      \do_settings_sections( $section );
    }
    \submit_button();
?>
  </form>
  <script>
    jQuery( function() {
      jQuery( '.NMOD_SCK_ToggableTextButton' ).on( 'click', function() {
        jQuery( this ).closest( 'td' ).find( '.NMOD_SCK_ToggableText' ).slideToggle( 'fast' );
      } );
    } );
  </script>
<?php
  }

  public function RenderOptionPage() {
    $legacy = Options::legacy_mode();

    $default_tab = 'graphic';
/*    
    if ( $legacy && 1 == $legacy )
      $default_tab = 'legacy_v1';
*/
    $tabs = array(
/*      'legacy_v1'  => array(
        'name'    => \esc_html__( 'Legacy mode'      , 'smart-cookie-kit' ),
        'fields'  => 'sck-option_group',
        'section' => array( 'nmod_sck_opts' ),
        'opt_ref' => 1,
      ),*/
      'graphic'    => array(
        'name'    => \esc_html__( 'Graphic options'  , 'smart-cookie-kit' ),
        'fields'  => 'sck-option_v2_group',
        'section' => array( 'nmod_sck_graphic_general_opts', 'nmod_sck_graphic_mobile_opts', 'nmod_sck_graphic_desktop_opts' ),
        'opt_ref' => 2,
      ),
      'logic'      => array(
        'name'    => \esc_html__( 'Logic options'    , 'smart-cookie-kit' ),
        'fields'  => 'sck-option_v2_group',
        'section' => array( 'nmod_sck_working_opts' ),
        'opt_ref' => 2,
      ),
      'help'      => array(
        'name'    => \esc_html__( 'Help and support'    , 'smart-cookie-kit' ),
        'fields'  => '',
        'section' => array(),
        'opt_ref' => 2,
      ),
    );
    $active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : $default_tab;
    if ( ! \array_key_exists( $active_tab, $tabs ) ) {
      \printf( '<script>;document.location.href=\'?page=nmod_sck_opts&tab=%s\';</script>', $default_tab );
      exit();
    }

    $this->options = Options::get( $tabs[ $active_tab ]['opt_ref'] );
//echo '<pre>'.print_r($this->options,true).'</pre>';
?>
    <div class="wrap">
      <h2>Smart Cookie Kit</h2>
      <?php $this->RenderNoticeDisclaimer(); ?>
<?php
/*
posts = http://localhost:8888/Wordpress/wp-admin/post-new.php?post_type=sck_banners
      <form method="post" action="options.php">
    settings_fields( 'sck-legacy-option_group' );
    do_settings_sections( 'nmod_sck_legacy_opts' );
    submit_button();
      </form>
*/
?>
      <h2 class="nav-tab-wrapper">
        <?php foreach ( $tabs as $tab_id => $tab ) { ?>
        <a href="?page=nmod_sck_opts&tab=<?php echo $tab_id ?>" class="nav-tab <?php echo $active_tab == $tab_id ? 'nav-tab-active' : ''; ?>"><?php echo $tab['name'] ?></a>
        <?php } ?>
      </h2>

<?php
    if ( '' != $tabs[ $active_tab ]['fields'] && !empty( $tabs[ $active_tab ]['section'] ) ) {
?>


      <form method="post" action="options.php">
<?php
    \submit_button();
    \settings_fields( $tabs[ $active_tab ]['fields'] );
    foreach ( $tabs[ $active_tab ]['section'] as $section ) {
      \do_settings_sections( $section );
    }
    \submit_button();
?>
      </form>
    <script>
      jQuery( function() {
        jQuery( '#cookiePolicyPageID' ).on( 'change', function() {
          jQuery( '#cookiePolicyPageURL' ).prop( 'disabled', -1 != jQuery( this ).val() );
          jQuery( '#cookiePolicyLinkText' ).prop( 'disabled', 0 == jQuery( this ).val() );
        } );
        /*
        jQuery( '#cssBannerBackLayer' ).on( 'change', function() {
          jQuery( '#cssBannerBackground' ).prop( 'disabled', !jQuery( this ).is(":checked") );
        } );
        */
        /*
        jQuery( '#acceptPolicyOnContentClick' ).on( 'change', function() {
          jQuery( '#excludedParentsClick' ).prop( 'disabled', !jQuery( this ).is(":checked") );
        } );
        */
        jQuery( '.NMOD_SCK_ToggableTextButton' ).on( 'click', function() {
          jQuery( this ).closest( 'td' ).find( '.NMOD_SCK_ToggableText' ).slideToggle( 'fast' );
        } );
      } );      
    </script>
<?php
    } else {
      include 'plugin_admin_' . $active_tab . '.php';
    }
  }

  private function RenderNoticeDisclaimer() {
    echo '<div class="notice NMOD_SCK_NoticeWarning"><p>' . \sprintf( \esc_html__( '%s helps to block third-party services that install cookies, but it may not be able to block all services. Please, make sure that all third party services are properly managed.', 'smart-cookie-kit' ), '<strong>' . \esc_html__( 'Smart Cookie Kit', 'smart-cookie-kit' ) . '</strong>' ) . '</p></div>';
  }
  private function RenderNoticeDisabledOptionForGDPR() {
    \printf(
      '<div class="NMOD_SCK_ToggableText NMOD_SCK_NoticeWarning" style="display:block"><strong>%s</strong>: %s</div>',      
      \esc_html__( 'IMPORTANT NOTE', 'smart-cookie-kit' ),
      \esc_html__( 'This option conflicts with GDPR principles and will be deprecated, so it will be automatically disabled on 25 May 2018 (according to your time zone and server settings) and it will be removed in the next version of the plugin', 'smart-cookie-kit' )
    );
  }

  public function RenderOptionSectionDescriptionForLegacy() {
    \esc_html_e( 'In this section you can select the compatibility level of the plugin.', 'smart-cookie-kit' );
  }

  public function RenderOptionSectionDescription() {
    \esc_html_e( 'In this section you can modify general graphics options for the banner.', 'smart-cookie-kit' );
  }

  public function RenderOptionSectionDescriptionForGeneralGraphic() {
    \esc_html_e( 'In this section you can modify general settings for the banner.', 'smart-cookie-kit' );
  }

  public function RenderOptionSectionDescriptionForMobileGraphic() {
    echo '<p>' . \esc_html__( 'In this section you can modify CSS rules for the elements of the banner when display is smaller than 768px.', 'smart-cookie-kit' ) . '</p>';
    echo '<p>' . \esc_html__( 'Please, keep in mind:', 'smart-cookie-kit' )
        . '<br />(1) ' . \esc_html__( 'CSS will be generated in a "mobile first" logic;', 'smart-cookie-kit' )
        . '<br />(2) ' . \esc_html__( 'the plugin will automatically add to the container this rule over 1000px', 'smart-cookie-kit' ) . ': <code>{width:1000px; left:50%; margin-left:-500px;}</code>'
      . '</p>'
    ;
  }

  public function RenderOptionSectionDescriptionForDesktopGraphic() {
    echo '<p>' . \esc_html__( 'In this section you can modify CSS rules for the elements of the banner when display is greater then 767px.', 'smart-cookie-kit' ) . '</p>';
    echo '<p>' . \esc_html__( 'Please, keep in mind:', 'smart-cookie-kit' )
        . '<br />(1) ' . \esc_html__( 'CSS will be generated in a "mobile first" logic;', 'smart-cookie-kit' )
        . '<br />(2) ' . \esc_html__( 'the plugin will automatically add to the container this rule over 1000px', 'smart-cookie-kit' ) . ': <code>{width:1000px; left:50%; margin-left:-500px;}</code>'
      . '</p>'
    ;
  }

  public function RenderOptionSectionDescriptionForLogic() {
    \esc_html_e( 'In this section you can customize the working logic of the plugin.', 'smart-cookie-kit' );
  }

  private function RenderOption_fieldLabel( $text, $toggable_text = '' ) {
    $info_img  = '';
    $info_text = '';
    if ( '' != $toggable_text ) {
      $info_img  = '<img src="' . plugin_dir_url( __FILE__ ) . 'res/info.png" class="NMOD_SCK_ToggableTextButton" />';
      $info_text = \sprintf( '<div class="NMOD_SCK_ToggableText NMOD_SCK_NoticeInfo">%s</div>', $toggable_text );
    }
    return \sprintf( '<small class="NMOD_SCK_FieldLabel">%1$s%2$s</small>%3$s', $text, $info_img, $info_text );
  }

  private function RenderOption_fieldNote( $text, $type = 'warning' ) {
    $class = '';
    switch ( $type ) {
      case 'error':
        $class = 'NMOD_SCK_NoticeError';
        break;
      case 'warning':
        $class = 'NMOD_SCK_NoticeWarning';
        break;
      case 'info':
        $class = 'NMOD_SCK_NoticeInfo';
        break;
    }
    return \sprintf( '<div class="NMOD_SCK_FieldNote %s">%s</div>', $class, $text );
  }
  private function get_cookie_banner_content_details( $post_id ) {
    $ret = array(
      'code'          => 0,
      'notices'       => array(
        'post_not_published'   => false,
        'post_not_translated'  => false,
        'post_not_selected'    => false,
      ),
      'banner_list'   => array(),
      'banner_status' => array()
    );

    if ( Multilanguage::is_multilanguage() ) {
      $translations = \get_posts( array(
        'post_type'          => Options::BannerPostType,
        'posts_per_page'     => -1,
        'post_status'        => 'any',
        'lang'               => '',
      ) );

      if ( 0 == \count( $translations ) ) {
        $ret['code'] = 1;
      } else {        
        $ret['code'] = 2;

        // lista banner con stato
        $ret['banner_list'][] = $this->RenderOptionSelect( 0, \esc_html__( 'Select one', 'smart-cookie-kit' ), $post_id );
        foreach ( $translations as $translation )
          $ret['banner_list'][] = $this->RenderOptionSelect( $translation->ID, $translation->post_title, $post_id );

        if ( 0 == $post_id ) {
          $ret['notices']['post_not_selected'] = true;
        } else {
          $selected_banner_lang = Multilanguage::get_banner_language( $post_id );
          foreach ( Multilanguage::get_active_languages() as $lang ) {
            $translated_post_id = $selected_banner_lang == $lang ? $post_id : Multilanguage::get_translated_banner_id( $post_id, $lang );

            $status = false;
            if ( $translated_post_id )
              $status = \get_post_status( $translated_post_id );
            if ( !$status ) {
              $status = 'not found';
              $ret['notices']['post_not_translated'] = true;
            } else {
              if ( 'publish' != $status ) {
                $ret['notices']['post_not_published'] = true;
              }            
            }

            $ret['banner_status'][] = array(
              'language' => \strtoupper( $lang ),
              'status'   => $status
            );
          }
        }

      }
    }

    return $ret;
  }
  public function RenderOption_cookieBannerContentID( $args ) {
    $status = $this->get_cookie_banner_content_details( $this->options[ $args['label_for'] ] );

    echo '<div class="' . $args['label_for'] . '">';
    echo '<div class="status_0 ' . ( 0 == $status['code'] ? 'shown' : 'hidden' ) . '">'
      . \esc_html__( 'It seems that you do not run a multi-language site. You can ignore this field ("Banner translations") and manage the banner content/strings from next fields.', 'smart-cookie-kit' )
      . '</div>';

    echo '<div class="status_1 ' . ( 1 == $status['code'] ? 'shown' : 'hidden' ) . '">'
      . \esc_html__( 'No banner found for translations.', 'smart-cookie-kit' )
      . '<br /><a id="NMOD_SCK_ContentPostLink" class="button button-primary" href="' . \add_query_arg( array( 'post_type' => Options::BannerPostType ), \admin_url( 'edit.php' ) ) . '">' . \esc_html__( 'Add the first translation', 'smart-cookie-kit' ) . '</a>'
      . $this->RenderOption_fieldNote(
          \esc_html__( 'NOTE: If you do not create translations for the banner, it will always show the contents and strings saved in the fields below, regardless of the language in which the site is displayed!', 'smart-cookie-kit' ),
          'error'
        )
      . '</div>';

    echo '<div class="status_2 ' . ( 2 == $status['code'] ? 'shown' : 'hidden' ) . '">';
      \printf(
        '%1$s<select id="%4$s" name="%3$s[%4$s]">%2$s</select>',
        $this->RenderOption_fieldLabel(
          \esc_html__( 'The banner will show content and strings customized in the selected post or in related translations.', 'smart-cookie-kit' ),
          \esc_html__( 'Do not worry about the language of the selected post. This field is only necessary to know which post (or the translations of which post) to use in the frontend: if a user visits your site in a language that does not match to the one used for this post, the banner will show its translation into the user\'s language.', 'smart-cookie-kit' )
        ),
        \implode( '', $status['banner_list'] ),
        $args['option_name'],
        $args['label_for']
      );

      if ( !empty( $status['banner_status'] ) ) {
        echo '<ul>';
        foreach ( $status['banner_status'] as $translation )
          \printf( '<li><strong>%s</strong>: %s</li>', $translation['language'], $translation['status'] );
        echo '</ul>';
      }
      echo '<div class="substatus_nottranslated ' . ( $status['notices']['post_not_translated'] ? 'shown' : 'hidden' ) . '">'
        . $this->RenderOption_fieldNote(
            \esc_html__( 'NOTE: If you do not create translations for some languages, when the site is displayed in one of those languages, the banner will show the contents and strings saved in the fields below.', 'smart-cookie-kit' ),
            'error'
          )
        . '</div>'
        . '<div class="substatus_notpublished ' . ( $status['notices']['post_not_published'] ? 'shown' : 'hidden' ) . '">'
        . $this->RenderOption_fieldNote(
            \esc_html__( 'NOTE: The posts that contain translations have to be "published", otherwise the banner will show content and strings saved in the fields below.', 'smart-cookie-kit' ),
            'warning'
          )
        . '</div>'
        . '<div class="substatus_notselected ' . ( $status['notices']['post_not_selected'] ? 'shown' : 'hidden' ) . '">'
        . $this->RenderOption_fieldNote(
            \esc_html__( 'NOTE: If you do not choose a post for translations, the banner will always show the contents and strings saved in the fields below, regardless of the language in which the site is displayed!', 'smart-cookie-kit' ),
            'info'
          )
        . '</div>'; 
    echo '</div>';
  
    echo '<div class="status_wait hidden">... Wait, please...</div>';
    echo '</div>'; // div.$args['label_for']

    if ( 1 == $status['code'] ) {
?>
<script>
  jQuery( '#NMOD_SCK_ContentPostLink' ).on( 'click', function( e ) {
    e.preventDefault();

    jQuery( '.<?php echo $args['label_for']; ?> .shown' ).removeClass( 'shown' ).addClass( 'hidden' );
    jQuery( '.<?php echo $args['label_for']; ?> .status_wait' ).removeClass( 'hidden' ).addClass( 'shown' );

    jQuery.post(
      '<?php echo \admin_url('admin-ajax.php') ?>',
      {
        action: 'nmod_sck_create_content_post',
        refchk: '<?php echo wp_create_nonce( 'j02i3jen23ld qdFRIH£QW 3i7q4erw qaduik32w' ) ?>'
      },
      function( response ) {
        if ( 1 == response.StatusCode ) {
          jQuery( '.<?php echo $args['label_for']; ?> .status_wait' ).removeClass( 'shown' ).addClass( 'hidden' );

          if ( 1 == response.Data.code || 2 == response.Data.code ) {
            jQuery( '#<?php echo $args['label_for']; ?>' ).html( response.Data.banner_list.join( '' ) );

            if ( 'undefined' != typeof response.Data.banner_status ) {
              if ( 0 != response.Data.banner_status.length ) {
                var list = '';
                for ( var i = 0; i < response.Data.banner_status.length; i++ ) {
                  list += '<li><strong>' + response.Data.banner_status[i]['language'] + '</strong>: ' + response.Data.banner_status[i]['status'] + '</li>';
                }
                jQuery( '#<?php echo $args['label_for']; ?>' ).after( '<ul>' + list + '</ul>' );
              }
            }

            if ( 'undefined' != typeof response.Data.notices.post_not_published )
              if ( response.Data.notices.post_not_published ) jQuery( '.<?php echo $args['label_for']; ?> .substatus_notpublished' ).show();
            if ( 'undefined' != typeof response.Data.notices.post_not_translated )
              if ( response.Data.notices.post_not_translated ) jQuery( '.<?php echo $args['label_for']; ?> .substatus_nottranslated' ).show();
            if ( 'undefined' != typeof response.Data.notices.post_not_selected )
              if ( response.Data.notices.post_not_selected ) jQuery( '.<?php echo $args['label_for']; ?> .substatus_notselected' ).show();
          }

          jQuery( '.<?php echo $args['label_for']; ?> .status_' + response.Data.code ).removeClass( 'hidden' ).addClass( 'shown' );
        } else {
          console.log( response );
          alert( 'An error occurred. Details in JS console' );
        }
      },
      'json'
    );
  } );
</script>
<?php      
    }
return;

/*
    $content = $this->get_cookie_banner_content_details( $this->options[ $args['label_for'] ] );
    \printf( '<span id="NMOD_SCK_ContentPostStatus">%s</span> <a class="%s" id="NMOD_SCK_ContentPostLink" href="%s" id="NMOD_SCK_EditContentPost" target="_blank">%s</a></span>', $content['status'], $content['btn_class'], $content['url'], $content['btn_text'] );
    if ( '#' == $content['url'] ) {
?>
<script>
  jQuery( '#NMOD_SCK_ContentPostLink' ).on( 'click', function( e ) {
    var item = jQuery( this );
    if ( '#' == item.attr( 'href' ) ) {
      e.preventDefault();

      jQuery( '#NMOD_SCK_ContentPostStatus' ).text( '...' );
      jQuery( '#NMOD_SCK_ContentPostLink' ).addClass( 'hidden' );

      jQuery.post(
        '<?php echo \admin_url('admin-ajax.php') ?>',
        {
          action: 'nmod_sck_create_content_post',
          refchk: '<?php echo wp_create_nonce( 'j02i3jen23ld qdFRIH£QW 3i7q4erw qaduik32w' ) ?>'
        },
        function( response ) {
          if ( 1 == response.StatusCode ) {
            jQuery( '#NMOD_SCK_ContentPostStatus' ).text( response.Data.status );
            jQuery( '#NMOD_SCK_ContentPostLink' ).removeClass( 'hidden' );
            jQuery( '#NMOD_SCK_ContentPostLink' ).text( response.Data.text );
            jQuery( '#NMOD_SCK_ContentPostLink' ).attr( 'href', response.Data.url );
          } else {
            console.log( response );
            alert( 'An error occurred. Details in JS console' );
          }
        },
        'json'
      );
    }
  } );
</script>
<?php
    }
*/
  }
  public function RenderOption_cookieBannerText( $args ) {
    echo $this->RenderOption_fieldLabel( \esc_html__( 'Customize banner text', 'smart-cookie-kit' ) );
    \wp_editor(
      \htmlspecialchars_decode( $this->options[ $args['label_for'] ] ),
      $args['label_for'],
      array( 'textarea_name' => \sprintf( '%s[%s]', $args['option_name'], $args['label_for'] ), 'media_buttons' => false, 'textarea_rows' => 6, 'teeny' => true )
    );
  }
  public function RenderOption_cookiePolicyPageID( $args ) {
    $page_list = array();
    $page_list[] = $this->RenderOptionSelect(  0, \esc_html__( 'None'  , 'smart-cookie-kit' ), $this->options[ $args['label_for'] ] );

    $i    = 0;
    $ipp  = 2;
    do {
      $pages = \get_posts( array(
        'post_type'          => 'page',
        'posts_per_page'     => $ipp,
        'offset'             => $i * $ipp,
        'post_status'        => 'publish'
      ) );

      foreach ( $pages as $page ) $page_list[] = $this->RenderOptionSelect( $page->ID, $page->post_title, $this->options[ $args['label_for'] ] );
      $i++;
    } while ( 0 < \count( $pages ) );

    \printf(
      '%1$s<select id="%4$s" name="%3$s[%4$s]">%2$s</select>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Select the pages (or one of its translation) on which the cookie banner will not be shown (there will be directly the minimized banner to open cookie preferences, if enabled).', 'smart-cookie-kit' ) ),
      \implode( '', $page_list ),
      $args['option_name'],
      $args['label_for']
    );

    global $wp_version;
    if ( \version_compare( $wp_version, '4.9.6', '>=' ) ) {
      // Policy page by WordPress 4.9.6
      $policy_page_note = '';
      $policy_page_id   = (int) \get_option( 'wp_page_for_privacy_policy', 0 );
      if ( 0 != $policy_page_id ) {
        $status = \get_post_status( $policy_page_id );
        if ( 'trash' === $status ) {
          $policy_page_id = 0;
        } elseif ( 'publish' !== $status ) {
          $policy_page_note = \sprintf( '<a href="%s" target="_blank">' . \esc_html__( 'edit privacy page', 'smart-cookie-kit' ) . '</a>', \add_query_arg( array( 'post' => $policy_page_id, 'action' => 'edit' ), \admin_url( 'post.php' ) ) );
          $policy_page_note = \sprintf( \esc_html__( 'You should publish it (%s).', 'smart-cookie-kit' ), $policy_page_note );
          $policy_page_note = \esc_html__( 'From version 4.9.6, WordPress supports natively the Privacy Policy page.', 'smart-cookie-kit' ) . ' ' . $policy_page_note . '<br />';
        }

        if ( $this->options[ $args['label_for'] ] != $policy_page_id ) {
          $policy_page_note .= \esc_html__( 'It seems that the page you have selected in the WordPress Privacy settings is not the page you have selected in the Smart Cookie Kit options. Please, check that everything is ok!', 'smart-cookie-kit' );
        }
      }

      if ( 0 == $policy_page_id ) {
        $policy_page_note = \sprintf( '<a href="%s" target="_blank">' . \esc_html__( 'Settings menù > Privacy page', 'smart-cookie-kit' ) . '</a>', \admin_url( 'privacy.php' ) );
        $policy_page_note = \sprintf( \esc_html__( 'You should set that page from the %s', 'smart-cookie-kit' ), $policy_page_note );
        $policy_page_note = \esc_html__( 'From version 4.9.6, WordPress supports natively the Privacy Policy page.', 'smart-cookie-kit' ) . ' ' . $policy_page_note;
      }
      if ( '' != $policy_page_note ) {      
        echo $this->RenderOption_fieldNote(
          $policy_page_note,
          'warning'
        );
      }
    }
  }
  public function RenderOption_cookiePolicyPageURL( $args ) {
    \printf(
      '%1$s<input type="text" class="large-text code" id="%5$s" name="%4$s[%5$s]" value="%2$s" %3$s/>',
      $this->RenderOption_fieldLabel( \esc_html__( 'The url of the custom page where the cookie policy is published.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      -1 == $this->options['cookiePolicyPageID'] ? '' : 'disabled="disabled" ',
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cookiePolicyLinkText( $args ) {
    \printf(
      '%1$s<input type="text" class="regular-text" id="%5$s" name="%4$s[%5$s]" value="%2$s" %3$s/>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Customize the text to use for the policy link.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      0 != $this->options['cookiePolicyPageID'] ? '' : 'disabled="disabled" ',
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_userSettingsLinkText( $args ) {
    \printf(
      '%1$s<input type="text" class="regular-text" id="%4$s" name="%3$s[%4$s]" value="%2$s" />',
      $this->RenderOption_fieldLabel( \esc_html__( 'Customize the text to use for the settings link.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cookieEnableButtonText( $args ) {
    \printf(
      '%1$s<input type="text" class="regular-text code" id="%4$s" name="%3$s[%4$s]" value="%2$s" />',
      $this->RenderOption_fieldLabel( \esc_html__( 'Customize the button text to enable cookies.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cookieEnabledButtonText( $args ) {
    \printf(
      '%1$s<input type="text" class="regular-text code" id="%4$s" name="%3$s[%4$s]" value="%2$s" />',
      $this->RenderOption_fieldLabel( \esc_html__( 'Customize the button text to keep cookies enabled.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cookieDisableLinkText( $args ) {
    \printf(
      '%1$s<input type="text" class="regular-text code" id="%4$s" name="%3$s[%4$s]" value="%2$s" />',
      $this->RenderOption_fieldLabel( \esc_html__( 'Customize the text to disable cookies.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cookieDisabledLinkText( $args ) {
    \printf(
      '%1$s<input type="text" class="regular-text code" id="%4$s" name="%3$s[%4$s]" value="%2$s" />',
      $this->RenderOption_fieldLabel( \esc_html__( 'Customize the text to keep cookies disabled.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }  
  public function RenderOption_cookieAcceptedLife( $args ) {
    \printf(
      '%1$s<input type="number" class="small-text" id="%4$s" name="%3$s[%4$s]" value="%2$s" min="1" step="1" />',
      $this->RenderOption_fieldLabel( \esc_html__( 'The number of days in which the user\'s consent will remain valid.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_blockGoogleTagManager( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Enabling this option, the plugin will block Google Tag Manager service at all until user accepts both Statistics and Profiling cookies.', 'smart-cookie-kit' )
        . '<br />' . \esc_html__( 'If you use GTM to add third party services to your site, please note that this plugin is compatible with GTM: it fires custom events ("statisticsCookiesEnabled", "statisticsCookiesDisabled", "profilingCookiesEnabled", "profilingCookiesDisabled") which could be used in GTM to properly unlock services. When a user updates its preferences, the plugin fires the "cookiePreferencesUpdated" custom event, too.', 'smart-cookie-kit' ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_blockGoogleReCaptcha( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Disabling this option, the plugin will NOT block Google reCaptcha service at all.', 'smart-cookie-kit' ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_blockAutomateWooSessionTracking( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Enabling this option, the plugin will block AutomateWoo "Session tracking" feature, unblocking it if user accepts Profiling cookies.', 'smart-cookie-kit' ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
  }  
  public function RenderOption_facebookPixelCompatibilityMode( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Try enabling this option if you notice in the browser\'s JavaScript console the error "fbq is not defined".', 'smart-cookie-kit' )
      . '<br />' . \sprintf(
        \esc_html__( 'CAUTION, this is an experimental feature: feel free to try it and open a thread in the plugin support forum at %s.', 'smart-cookie-kit' ),
        \sprintf( '<a href="https://wordpress.org/support/plugin/smart-cookie-kit/" target="_blank">%s</a>', \esc_html__( 'this page', 'smart-cookie-kit' ) )
      ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_showMinimizedButton( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Enabling this option, plugin will automatically add a button on the page that opens the initial banner to allow visitors to manage their cookie preferences.', 'smart-cookie-kit' )
        . '<br />' . \esc_html__( 'NOTE: you can create a link that opens the initial banner using a shortcode; see the help page for instructions.', 'smart-cookie-kit' ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_reloadPageWhenDisabled( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Enabling this option, plugin will reload the page to stop services that were running before user disabled the cookies.', 'smart-cookie-kit' ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
  } 

  public function RenderOption_cssContentPlaceholder( $args ) {
    if ( 1 != $this->options['addBlockedContentPlaceholder'] ) {
      echo $this->RenderOption_fieldNote(
        \sprintf( \esc_html__( 'NOTE: this field will not be used due to the value of "%s" field in logic options page.', 'smart-cookie-kit' ), \esc_html__( 'Add placeholders on page', 'smart-cookie-kit' ) ),
        'warning'
      );
    }

    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the banner backlayer.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }

  public function RenderOption_cssBannerBackLayer( $args ) {
    if ( 1 != $this->options['addBannerBackLayer'] ) {
      echo $this->RenderOption_fieldNote(
        \sprintf( \esc_html__( 'NOTE: this field will not be used due to the value of "%s" field in logic options page.', 'smart-cookie-kit' ), \esc_html__( 'Enable banner backlayer', 'smart-cookie-kit' ) ),
        'warning'
      );
    }

    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the banner backlayer.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssBannerContainer( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the banner (the area containing text and buttons).', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssBannerTextContainer( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the banner text.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }  
  public function RenderOption_cssBannerText( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the banner text.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssLinksList( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the list links.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssLinksListItem( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize every list link item.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssBannerActionsArea( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the area that contains links and buttons to manage the policy.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssBannerActionsButtons  ( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the area that contains the buttons for accepting the policy.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssBannerAcceptButton( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the accept button.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssBannerAcceptButtonOnHover( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the accept button in hover state (when the pointer is over the button).', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssBannerCloseLink( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the close link.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssBannerCloseLinkOnHover( $args ) {
    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the close link in hover state (when the pointer is over the link).', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_minimizedSettingsButtonText( $args ) {
    if ( 1 != $this->options['showMinimizedButton'] ) {
      echo $this->RenderOption_fieldNote(
        \sprintf( \esc_html__( 'NOTE: this field will not be used due to the value of "%s" field in logic options page.', 'smart-cookie-kit' ), \esc_html__( 'Minimized button for cookie settings', 'smart-cookie-kit' ) ),
        'warning'
      );
    }

    \printf(
      '%1$s<input type="text" class="regular-text code" id="%4$s" name="%3$s[%4$s]" value="%2$s"/>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Customize the text shown into the minimized settings button.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssMinimizedSettingsButton( $args ) {
    if ( 1 != $this->options['showMinimizedButton'] ) {
      echo $this->RenderOption_fieldNote(
        \sprintf( \esc_html__( 'NOTE: this field will not be used due to the value of "%s" field in logic options page.', 'smart-cookie-kit' ), \esc_html__( 'Minimized button for cookie settings', 'smart-cookie-kit' ) ),
        'warning'
      );
    }

    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the minimized button for cookie settings.', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_cssMinimizedSettingsButtonOnHover( $args ) {
    if ( 1 != $this->options['showMinimizedButton'] ) {
      echo $this->RenderOption_fieldNote(
        \sprintf( \esc_html__( 'NOTE: this field will not be used due to the value of "%s" field in logic options page.', 'smart-cookie-kit' ), \esc_html__( 'Minimized button for cookie settings', 'smart-cookie-kit' ) ),
        'warning'
      );
    }

    \printf(
      '%1$s<textarea id="%4$s" class="large-text code" name="%3$s[%4$s]" rows="2">%2$s</textarea>',
      $this->RenderOption_fieldLabel( \esc_html__( 'Insert here CSS rules to customize the minimized button for cookie settings in hover state (when the pointer is over the button).', 'smart-cookie-kit' ) ),
      $this->options[ $args['label_for'] ],
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_acceptPolicyOnScroll( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Enable the implicit acceptance of the policy (unlocking third parts services) for the user that scrolls the page.', 'smart-cookie-kit' ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
    echo $this->RenderOption_fieldNote(
      \esc_html__( 'NOTE: the consent on scroll could be considered not GDPR compliant because it is an implicit consent. Please, enable this option with caution.', 'smart-cookie-kit' ),
      'error'
    );
    echo $this->RenderOption_fieldNote(
      \esc_html__( 'NOTE: This option will be applied only to visitors who have never expressed a preference on the use of cookies on this site. If someone has already accepted or refused cookies, their preference will be respected and cookies will NOT be unlocked despite scrolling on the pages.', 'smart-cookie-kit' ),
      'info'
    );
    echo $this->RenderOption_fieldNote(
      \esc_html__( 'NOTE: The implicit consent will be registered if user scrolls the page for more than 200px.', 'smart-cookie-kit' ),
      'info'
    );
  }
  public function RenderOption_addBlockedContentPlaceholder( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Enabling this option, the plugin will replace blocked content with a placeholder asking visitors to enable cookies.', 'smart-cookie-kit' )
      . '<br />' . \esc_html__( '(the content of the placeholder is customizable in the Graphic options)', 'smart-cookie-kit' )
      ,
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_blockedContentPlaceholderText( $args ) {
    echo $this->RenderOption_fieldLabel( \esc_html__( 'Customize placeholders text', 'smart-cookie-kit' ), \esc_html__( 'In this text box you can use the following variables:', 'smart-cookie-kit' ) . '<br /><br /><strong>%SERVICE_NAME%</strong> ' . \esc_html__( 'to specify what service/functionality has been blocked', 'smart-cookie-kit' ) );

    if ( 1 != $this->options['addBlockedContentPlaceholder'] ) {
      echo $this->RenderOption_fieldNote(
        \sprintf( \esc_html__( 'NOTE: this field will not be used due to the value of "%s" field in logic options page.', 'smart-cookie-kit' ), \esc_html__( 'Add placeholders on page', 'smart-cookie-kit' ) ),
        'warning'
      );
    }
    \wp_editor(
      \htmlspecialchars_decode( $this->options[ $args['label_for'] ] ),
      $args['label_for'],
      array( 'textarea_name' => \sprintf( '%s[%s]', $args['option_name'], $args['label_for'] ), 'media_buttons' => false, 'textarea_rows' => 6, 'teeny' => true )
    );    
  }

  public function RenderOption_addBannerBackLayer( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'Adds a layer (customizable in the graphic options page) between the banner and the page content.', 'smart-cookie-kit' ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );

    echo $this->RenderOption_fieldNote(
      \esc_html__( 'NOTE: enabling the backlayer you can emphasize the banner, but keep in mind that visitors will not be able to click on page elements and this kind of block could be considered not completely GDPR compliant.', 'smart-cookie-kit' ),
      'error'
    );
    echo $this->RenderOption_fieldNote(
      \esc_html__( 'NOTE: please, be aware that Google could not appreciate this option for mobile users. For this reason, I have set the default CSS rules to show the backlayer only on larger screens, but you can easly customize this behaviour in the graphic options page (moving the rules in the mobile section, for example). For more details, please, refer to the official blog:', 'smart-cookie-kit' )
      . ' <a href="https://webmasters.googleblog.com/2016/08/helping-users-easily-access-content-on.html" target="_blank" rel="nofollow">https://webmasters.googleblog.com/2016/08/helping-users-easily-access-content-on.html</a>',
      'error'
    );
  }

  public function RenderOption_saveLogToServer( $args ) {
    if ( $this->checkFilesystemAccess() ) {
      \printf(
        '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
        \esc_html__( 'Write a log file in the server to list: date and time of the user cookie preferences update, his IP address, which cookies has accepted. The log contains also a temporal reference (timestamp) to match the cookie saved in the browser user and the log line of the server. Logs are grouped by year and month.', 'smart-cookie-kit' ),
        1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
        $args['option_name'],
        $args['label_for']
      );
    } else {
      \esc_html_e( 'WordPress could not directly write files due to security restrictions of your file system. Please, ask to your system administrator to enable write permissions on the following directory:', 'smart-cookie-kit' );
      \printf( '<br /><code>%s</code>', $this->getLogsDirectoryPath() );
    }
  }
  public function RenderOption_pluginDebugMode( $args ) {
    \printf(
      '<input type="hidden" name="%3$s[%4$s]" value="0" /><label for="%4$s"><input type="checkbox" id="%4$s" name="%3$s[%4$s]" value="1"%2$s />%1$s</label>',
      \esc_html__( 'In debug mode the plugin does not use optimized resources and sends messages to the javascript console. Use only if necessary!', 'smart-cookie-kit' ),
      1 == $this->options[ $args['label_for'] ] ? ' checked="checked"' : '',
      $args['option_name'],
      $args['label_for']
    );
  }
  public function RenderOption_pluginVersion( $args ) {
    if ( ! \function_exists( 'get_plugin_data' ) ) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_data = \get_plugin_data( __DIR__ . '/plugin.php' );

    \printf(
      '<input type="hidden" name="%2$s[%3$s]" value="%1$s" />',
      $plugin_data['Version'],
      $args['option_name'],
      $args['label_for']
    );
  }
  private function RenderOptionSelect( $value, $text, $selected_value ) {
    static $select_option = '<option value="%1$s"%3$s>%2$s</option>';

    return \sprintf( $select_option, $value, $text, selected( $value, $selected_value, false ) );
  }

  protected function getLogsDirectoryPath() {
    global $wp_filesystem;

    if ( empty( $wp_filesystem ) ) {
      require_once ABSPATH . '/wp-admin/includes/file.php';
      \WP_Filesystem();
    }

    return $wp_filesystem->wp_content_dir() . 'cookie-preferences-log';
  }

  protected function checkFilesystemAccess() {
    if ( 'direct' === \get_filesystem_method() ) {
      $dir = $this->getLogsDirectoryPath();

      if ( ! WP_Filesystem( request_filesystem_credentials( $dir, '', false, false, array() ) ) )
        return false;

      global $wp_filesystem;
      if ( ! $wp_filesystem->exists( $dir ) )
        if ( ! $wp_filesystem->mkdir( $dir ) )
          return false;

      if ( ! $wp_filesystem->is_writable( $dir ) )
        return false;

      $htaccess = $dir . '/.htaccess';
      if ( ! $wp_filesystem->exists( $htaccess ) )
        if ( ! $wp_filesystem->put_contents( $dir . '/.htaccess', 'Deny from all', FS_CHMOD_FILE ) )
          return false;

      return true;
    }

    return false;
  }

  public function save_server_log() {
    if ( $this->checkFilesystemAccess() ) {
      global $wp_filesystem;

      $filename   = \sprintf( '%1$s/Cookie_UserSettings_Log_%2$s.csv', $this->getLogsDirectoryPath(), \date( 'Ym' ) );
      $add_header = ! $wp_filesystem->exists( $filename );
      $log_file   = \fopen( $filename, 'at' );

      if ( $add_header ) {
        $header = array(
          'Ref',
          'Original ref',
          'Date',
          'IP',
          'Update type',
          'Technical cookies',
          'Statistics cookies',
          'Profiling cookies'
        );
        \fwrite( $log_file, \implode( ',', $header ) );
        /*
        if ( ! $wp_filesystem->put_contents( $filename, \implode( ',', $header ), FS_CHMOD_FILE ) ) {
          echo 'error saving file! (1)';
        }
        */
      }

      $remote_ip = $_SERVER['REMOTE_ADDR'];
      if ( ! empty( $_SERVER['X_FORWARDED_FOR'] ) ) {
        $temp = \explode( ',', $_SERVER['X_FORWARDED_FOR'] );
        if ( ! empty( $temp ) ) {
          $remote_ip = \trim( $temp[0] );
        }
      } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $temp = \explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
        if ( ! empty( $temp ) ) {
          $remote_ip = \trim( $temp[0] );
        }
      }
      $remote_ip = \preg_replace( '/[^0-9a-f:\., ]/si', '', $remote_ip );

      $content = array(
        $_REQUEST['ref'],
        $_REQUEST['first_ref'],
        \date( 'Y-m-d H:i:s' ),
        $remote_ip,
        $_REQUEST['update_type'],
        $_REQUEST['settings']['technical'],
        $_REQUEST['settings']['statistics'],
        $_REQUEST['settings']['profiling']      
      );
      \fwrite( $log_file, "\n" . \implode( ',', $content ) );
      /*
      if ( ! $wp_filesystem->put_contents( $filename, \implode( ',', $content ), FS_CHMOD_FILE ) ) {
        echo 'error saving file! (2)';
      }
      */

      \fclose( $log_file );
    } else {
      //\add_action('admin_notices', 'you_admin_notice_function');
      echo 'could not save file!';
    }
/*
    $content = \sprintf(
      "Ref: %s - First parent: %s - Date: %s - IP: %s - Update type: %s - Technical cookies: %s - Statistics cookies: %s - Profiling cookies: %s \n",
      $_REQUEST['ref'],
      $_REQUEST['first_ref'],
      \date( 'Y-m-d H:i:s' ),
      $remote_ip,
      $_REQUEST['update_type'],
      $_REQUEST['settings']['technical'],
      $_REQUEST['settings']['statistics'],
      $_REQUEST['settings']['profiling']
    );

    $log_file = \fopen( \sprintf( '%1$slogs/Cookie_UserSettings_Log_%2$s.txt', plugin_dir_path( __FILE__ ), \date( 'Ym' ) ), 'a' );
    \fwrite( $log_file, $content, strlen( $content ) );
    \fclose( $log_file );
*/
  }

  public function search_policy_pages() {
    $s = \wp_unslash( $_GET['q'] );

    $comma = _x( ',', 'page delimiter' );
    if ( ',' !== $comma )
      $s = \str_replace( $comma, ',', $s );
    if ( false !== \strpos( $s, ',' ) ) {
      $s = \explode( ',', $s );
      $s = $s[\count( $s ) - 1];
    }
    $s = \trim( $s );

    $term_search_min_chars = 2;

    $the_query = new \WP_Query( 
      array( 
        's' => $s,
        'posts_per_page' => 5,
      ) 
    );

    if ( $the_query->have_posts() ) {
      while ( $the_query->have_posts() ) {
        $the_query->the_post();
        $results[] = \get_the_title();
      }
      /* Restore original Post Data */
      \wp_reset_postdata();
    } else {
      $results = 'No results';
    }

    echo \implode( "\n", $results );
    \wp_die();
  }

  private function transform_text_for_web( $text ) {
    return \preg_replace(
      array( "~\[br\]~i", "~\[b\]~i", "~\[/b\]~i", "~\[i\]~i", "~\[/i\]~i", "~\[u\]~i", "~\[/u\]~i", "~\r~", "~\n~"   ),
      array( '<br />'   , '<b>'     , '</b>'     , '<i>'     , '</i>'     , '<u>'     , '</u>'     , ''    , '<br />' ),
      $text
    );
  }

  public function create_content_post() {
    \check_ajax_referer( 'j02i3jen23ld qdFRIH£QW 3i7q4erw qaduik32w', 'refchk' );

    $ret = array(
      'StatusCode' => 0,
      'StatusDesc' => array(),
      'Data'       => array()
    );

    if ( -1 != $ret['StatusCode'] ) {
      if ( Multilanguage::is_multilanguage() ) {
        $options = Options::get();

        $default_lang = Multilanguage::get_default_language();
        
        $params = array(
          'post_type'      => Options::BannerPostType,
          'post_title'     => '' == $default_lang ? \esc_html__( 'Default language', 'smart-cookie-kit' ) : \strtoupper( $default_lang ),
//          'post_content'   => \htmlspecialchars_decode( esc_html\esc_html__( $options['cookieBannerText'] ) ),
          'post_content'   => '',
          'post_status'    => 'draft',
          'ping_status'    => 'closed',
          'comment_status' => 'closed',
          'meta_input'     => array(
            'SCK_BannerTexts'      => array()
          )
        );

        $meta = Options::get_banner_text_fields();
        foreach ( $meta as $field )
          $params['meta_input']['SCK_BannerTexts'][ $field['name'] ] = \array_key_exists( $field['name'], $options ) ? esc_html__( $options[ $field['name'] ] ) : '';

        if ( ! \is_wp_error( $insert_res = \wp_insert_post( $params, true ) ) ) {

          if ( '' != $default_lang ) {
            Multilanguage::set_banner_language( $insert_res, $default_lang );
          }

          $ret['StatusCode'] = 1;
          $ret['Data'] = $this->get_cookie_banner_content_details( $insert_res );
          
          $ret['Data']['post_id'] = $insert_res;

          $options['cookieBannerContentID'] = $insert_res;
          Options::update( $options );
        } else {
          $ret['StatusCode'] = -1;
          $ret['StatusDesc'][] = 'Error saving post <b>' . $params['post_title'] . '</b>: ' . $insert_res->get_error_message();
        }
      }
    }

    \wp_send_json( $ret );
  }

  public function dismiss_notice_helpsection() {
    \check_ajax_referer( 'cdslcsCs cdscWSCew cwEFW"IFwF44334rw', 'refchk' );

    $ret = array(
      'StatusCode' => 0
    );

    if ( \current_user_can( 'administrator' ) ) {
      $this->admin_notices[0] = 1;
      \update_option( 'SmartCookieKit_AdminNotices', $this->admin_notices, false );
      $ret['StatusCode'] = 1;
    }

    \wp_send_json( $ret );
  }

  public function dismiss_notice_nginx() {
    \check_ajax_referer( 'dl2 e3E23je23 eo23"£H eou e21QE 3ehu223 32r"£ 2', 'refchk' );

    $ret = array(
      'StatusCode' => 0
    );

    if ( \current_user_can( 'activate_plugins' ) ) {
      $this->admin_notices[1] = 1;
      \update_option( 'SmartCookieKit_AdminNotices', $this->admin_notices, false );
      $ret['StatusCode'] = 1;
    }

    \wp_send_json( $ret );
  }

  public function manage_contentpost_main_editor( $settings, $editor_id ) {
    if ( Options::BannerPostType != \get_post_type() ) return $settings;
    if ( 'content' != $editor_id ) return $settings;

    $settings['media_buttons'] = false;
    $settings['textarea_rows'] = 6;
    $settings['teeny'        ] = true;

    return $settings;
  }

  public function add_contentpost_custom_metaboxes( $post ) {
    \add_meta_box(
      'Box_BannerTexts',
      \esc_html__( 'Altri testi per il banner', 'smart-cookie-kit' ),
      array( $this, 'render_metabox_bannertexts' ),
      Options::BannerPostType,
      'normal',
      'high',
      array(
        '__block_editor_compatible_meta_box' => false,
        '__back_compat_meta_box'             => false
      )
    );
  }
  public function render_metabox_bannertexts( $post ) {
    \wp_nonce_field( 'dsk23dHq iqw4ryq3i lf342tò3y8TRg FDGWERwaui4b5g24 wsefsd', 'sck_banner_txts_chk' );
    
    
    
    $values = \get_post_meta( $post->ID, 'SCK_BannerTexts', true );
    if ( ! \is_array( $values ) ) $values = array();

    $this->render_metabox_render_fields(
      $values,
      array(
        array(
          'name'    => '',
          'type'    => 'table',
          'fields'  => Options::get_banner_text_fields()
        )
      )
    );
?>
    <script>
      jQuery( function() {
        jQuery( '#Box_BannerTexts .cookiePolicyPageID' ).on( 'change', function() {
          jQuery( '#Box_BannerTexts .cookiePolicyPageURL'  ).prop( 'disabled', -1 != jQuery( this ).val() );
          jQuery( '#Box_BannerTexts .cookiePolicyLinkText' ).prop( 'disabled',  0 == jQuery( this ).val() );
        } );
        jQuery( '.NMOD_SCK_ToggableTextButton' ).on( 'click', function() {
          jQuery( this ).closest( 'td' ).find( '.NMOD_SCK_ToggableText' ).slideToggle( 'fast' );
        } );

        jQuery( '#Box_BannerTexts select' ).change();
      } );
    </script>
<?php
  }

  private function render_metabox_render_fields( $values, $sections ) {
    $section_template_start = '<div class="custom-metabox-section"><h3>%1$s</h3>';
    $section_template_end   = '</div>';
    $table_template_start   = '<table class="form-table"><tbody>';
    $table_template_end     = '</tbody></table>';
    $row_template_start     = '<tr><th scope="row"><label for="%2$s"><span class="input-text-wrap">%1$s</span></label></th><td>';
    $row_template_start_alt = '<tr><td colspan="2"><strong>%1$s</strong>';
    $row_template_end       = '</td></tr>';

    foreach ( $sections as $section ) {
      $section_type = \array_key_exists( 'type', $section ) ? $section[ 'type' ] : '';

      \printf( $section_template_start, $section[ 'name' ] );

      switch ( $section_type ) {
        case 'table':
          echo $table_template_start;
          break;
      }

      foreach ( $section[ 'fields' ] as $field ) {
        if ( 'main_editor' == $field[ 'type' ] )
          continue;

        switch ( $section_type ) {
          case 'table':
            if ( \in_array( $field[ 'type' ], array( 'editor' ) ) ) {
              \printf( $row_template_start_alt, $field[ 'label' ], $field[ 'name' ] );
            } else {
              \printf( $row_template_start, $field[ 'label' ], $field[ 'name' ] );
            }
            break;
        }

        if ( '' != $field[ 'desc' ] )
          echo $this->RenderOption_fieldLabel( $field[ 'desc' ], ( \array_key_exists( 'help', $field ) ? $field['help'] : '' ) );

        if ( \array_key_exists( 'note', $field ) )
          if ( ! empty( $field['note'] )  )
            echo $this->RenderOption_fieldNote( $field['note']['text'], $field['note']['type'] );

        switch ( $field[ 'type' ] ) {
          case 'textbox':
            \printf( '<input type="text" name="%1$s" class="%1$s large-text" value="%2$s" />', $field[ 'name' ], \array_key_exists( $field[ 'name' ], $values ) ? $values[ $field[ 'name' ] ] : '' );
            break;
          case 'editor':
            \wp_editor( \htmlspecialchars_decode( \array_key_exists( $field[ 'name' ], $values ) ? $values[ $field[ 'name' ] ] : '' ), $field[ 'name' ] . '_Editor', $settings = array( 'textarea_name' => $field[ 'name' ], 'media_buttons' => false, 'textarea_rows' => 6, 'teeny' => true ) );
            break;
          case 'select':
            $options  = \call_user_func( $field[ 'options' ] );

            $default_option = '';
            if ( \is_array( $field[ 'default_option' ] ) ) {
              foreach ( $field[ 'default_option' ] as $option ) {
                if ( \array_key_exists( $option, $options ) ) {
                  $default_option = $option;
                  break;
                }
              }
            } else {
              $default_option = $field[ 'default_option' ];
            }

            $selected = \array_key_exists( $field[ 'name' ], $values ) ? $values[ $field[ 'name' ] ] : $default_option;
            \printf( '<select name="%1$s" class="%1$s">', $field[ 'name' ] );
            foreach ( $options as $key => $value )
              \printf( '<option value="%1$s"%2$s>%3$s</option>', $key, ( $selected == $key ? ' selected="selected"' : '' ), $value );              
            echo( '</select>' );
            break;
          default:
            echo 'Tipologia campo sconosciuto per: ' . $field[ 'name' ];
            break;
        }

        switch ( $section_type ) {
          case 'table':
            echo $row_template_end;
            break;
        }
      }

      switch ( $section_type ) {
        case 'table':
          echo $table_template_end;
          break;
      }

      echo $section_template_end;
    }
  }

  public function save_post_customs( $post_id ) {
    if ( ! \current_user_can( 'edit_post'          , $post_id ) ) return $post_id;
    if ( ! \array_key_exists( 'post_type'          , $_POST   ) ) return $post_id;
    if ( Options::BannerPostType != $_POST['post_type']         ) return $post_id;
    if ( ! \array_key_exists( 'sck_banner_txts_chk', $_POST   ) ) return $post_id;
    if ( ! \wp_verify_nonce( $_POST['sck_banner_txts_chk'], 'dsk23dHq iqw4ryq3i lf342tò3y8TRg FDGWERwaui4b5g24 wsefsd' ) ) return $post_id;

    $data_to_save = Options::get_banner_text_fields();
    $new_fields   = array();

    foreach ( $data_to_save as $data ) {
      if ( \array_key_exists( $data['name'], $_POST ) ) {
        $new_fields[ $data['name'] ] = \in_array( $data['name'], array( 'cookieBannerText', 'blockedContentPlaceholderText' ) ) ? \wp_kses_post( $_POST[ $data['name'] ] ) : \wp_strip_all_tags( $_POST[ $data['name'] ] );
      }
    }
    if ( ! empty( $new_fields ) )
      \update_post_meta( $post_id, 'SCK_BannerTexts', $new_fields );
  }

  public function notices_for_automatewoo( $general_tab_settings ) {
    $options = Options::get();

    $settings_page = \sprintf(
      '<a href="%s" target="_blank">%s</a>',
      \admin_url( 'admin.php?page=nmod_sck_logics' ),
      \esc_html__( 'this page', 'smart-cookie-kit' )
    );

    $style = 'background:#fff;border:1px solid #ccd0d4;border-left-width:4px;box-shadow:0 1px 1px rgba(0,0,0,.04);margin-top:5px;padding:.5em 12px;border-left-color:#ff8c0a;font-size:.8em;';

?>    
<script>
  jQuery( function() {
    jQuery( '#automatewoo_session_tracking_requires_cookie_consent_field_row td' ).append( '<div style="<?php echo $style; ?>"><?php
      \printf(
        $options['blockAutomateWooSessionTracking']
          ? \esc_html__( 'Smart Cookie Kit is configured to block Session tracking by AutomateWoo until the consent is given. In order for the unblocking of Session Tracking to be successful you must keep this option deactivated. You can manage Smart Cookie Kit configuration on %s.', 'smart-cookie-kit' )
          : \esc_html__( 'In order to comply with GDPR, preventive blocking of AutomateWoo\\\'s Tracking Session is recommended. Smart Cookie Kit can manage the preventive blocking of the Tracking Session with other third party services until consent is given; this will unify the management of profiling services. You can configure Smart Cookie Kit on %s.', 'smart-cookie-kit' )
        ,
        $settings_page
      );
    ?></div>' );
    <?php if ( $options['blockAutomateWooSessionTracking'] ) { ?>
    jQuery( '#automatewoo_session_tracking_consent_cookie_name_field_row td' ).append( '<div style="<?php echo $style; ?>"><?php
      \printf(
        \esc_html__( 'Smart Cookie Kit is configured to block Session tracking by AutomateWoo until the consent is given. In order for the unblocking of Session Tracking to be successful you must keep this field blank. You can manage Smart Cookie Kit configuration on %s.', 'smart-cookie-kit' ),
        $settings_page
      );
    ?></div>' );
    <?php } ?>
  } );
</script>
<?php
  }
}
