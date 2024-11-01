<?php
namespace NMod\SmartCookieKit;

if ( ! \defined( 'ABSPATH' ) ) exit;

Options::init();

class Options {
  static private $options;
  static private $legacy_mode;
  static private $defaults;

  const BannerPostType                = 'sck-banner';
  const CookieIgnoreClass             = 'SCK-Ignore';
  const CookieBlockClass_Statistics   = 'SCK-Block-Statistics';
  const CookieBlockClass_Profiling    = 'SCK-Block-Profiling';
  const CookieBlockClass_StatsAndProf = 'SCK-Block-StatisticsAndProfiling';

  static public function init() {
    self::$options = array();

    \add_action( 'init'                                , array( 'NMod\SmartCookieKit\Options', 'register_customs'         )        );
    \add_filter( 'use_block_editor_for_post_type'      , array( 'NMod\SmartCookieKit\Options', 'filter_banner_editor'     ), 10, 2 );

    \add_action( 'option_SmartCookieKit_Options'       , array( 'NMod\SmartCookieKit\Options', 'manage_data_update_v1'    )        );
    \add_action( 'option_SmartCookieKit_Options_v2'    , array( 'NMod\SmartCookieKit\Options', 'manage_data_update_v2'    )        );
  }

  static public $wpml_package = array(
    'kind'       => 'SmartCookieKit',
    'name'       => 'sckmessages',
    'title'      => 'Smart Cookie Kit messages',
    'edit_link'  => '',
    'view_link'  => ''
  );

  static public function register_customs() {
    if ( $multilanguage_plugin = Multilanguage::is_multilanguage() ) {
      \register_post_type(
        self::BannerPostType,
        array(
          'labels'               => array (
            'name'                   => \esc_html__( 'Banner'                        , 'smart-cookie-kit' ),
            'singular_name'          => \esc_html__( 'Banner'                        , 'smart-cookie-kit' ),
            'add_new_item'           => \esc_html__( 'Add new banner'                , 'smart-cookie-kit' ),
            'edit_item'              => \esc_html__( 'Edit banner'                   , 'smart-cookie-kit' ),
            'new_item'               => \esc_html__( 'New banner'                    , 'smart-cookie-kit' ),
            'view_item'              => \esc_html__( 'View banner'                   , 'smart-cookie-kit' ),
            'search_items'           => \esc_html__( 'Search banners'                , 'smart-cookie-kit' ),
            'not_found'              => \esc_html__( 'No banners found'              , 'smart-cookie-kit' ),
            'not_found_in_trash'     => \esc_html__( 'No banners found in trash '    , 'smart-cookie-kit' ),
            'all_items'              => \esc_html__( 'All banners'                   , 'smart-cookie-kit' ),
            'archives'               => \esc_html__( 'Banner list'                   , 'smart-cookie-kit' ),
            'insert_into_item'       => \esc_html__( 'Insert into banner'            , 'smart-cookie-kit' ),
            'uploaded_to_this_item'  => \esc_html__( 'Uploaded to this banner'       , 'smart-cookie-kit' ),
            'filter_items_list'      => \esc_html__( 'Filter banners list'           , 'smart-cookie-kit' ),
            'items_list_navigation'  => \esc_html__( 'Banners list'                  , 'smart-cookie-kit' ),
            'items_list'             => \esc_html__( 'Banners list'                  , 'smart-cookie-kit' ),
          ),
          'public'               => false,
          'show_ui'              => true,
          'publicly_queryable'   => false,
          'show_in_menu'         => 'nmod_sck_graphics',
          'query_var'            => 'sck_banner',
          'capability_type'      => 'post',
          'has_archive'          => false,
          'hierarchical'         => false,
          'supports'             => array( 'title' )
        )
      );

      if ( 'Polylang' == $multilanguage_plugin ) {
        \add_filter( 'pll_copy_post_metas', array( 'NMod\SmartCookieKit\Options', 'manage_polylang_custom_fields' ),  10, 2 );
      }
    }
  }

  static public function filter_banner_editor( $use_block_editor, $post_type ) {
    if ( self::BannerPostType === $post_type )
      return false;

    return $use_block_editor;
  }

  static public function manage_data_update_v1( $options ) { return self::manage_data_update( $options    ); }
  static public function manage_data_update_v2( $options ) { return self::manage_data_update( $options, 2 ); }
  static private function manage_data_update( $options, $legacy_mode = '' ) {
    $last_update = '0';
    if ( \array_key_exists( 'plugin_version', $options ) ) {
      $last_update = $options['plugin_version'];
    } else {
      $options['plugin_version'] = $last_update;
    }

    $new_version = '2.1.0';
    if ( \version_compare( $last_update, $new_version, '<' ) ) {
      if ( \array_key_exists( 'cookieBannerText', $options ) ) {
        $options['cookieBannerText'] = self::transform_text_for_web( $options['cookieBannerText'] );

        $link_details = array(
          'href' => '',
          'rel'  => '',
          'text' => ''
        );

        if ( isset( $options['cookiePolicyPageID'] ) ) {
          if ( 0 != $options['cookiePolicyPageID'] ) {
            if ( -1 == $options['cookiePolicyPageID'] ) {
              if ( isset( $options['cookiePolicyPageURL'] ) ) {
                if ( '' != $options['cookiePolicyPageURL'] ) {
                  $link_details['href'] = self::transform_text_for_web( $options['cookiePolicyPageURL'] );
                  $link_details['rel'] = 'nofollow';
                }
              }
            } else {
              $link_details['href'] = \get_page_link( $options['cookiePolicyPageID'] );
            }
          }
        }
        if ( '' != $link_details['href'] ) {
          $link_details['text'] = \esc_html__( 'Cookie policy', 'smart-cookie-kit' );
          if ( isset( $options['cookiePolicyLinkText'] ) ) {
            if ( '' != $options['cookiePolicyLinkText'] ) {              
              $link_details['text'] = self::transform_text_for_web( $options['cookiePolicyLinkText'] );
            }
          }

          $options['cookieBannerText'] .= \sprintf( ' <a href="%s" target="_blank" rel="%s">%s</a>', $link_details['href'], $link_details['rel'], $link_details['text'] );
        }
      } 

      if ( \array_key_exists( 'blockedContentPlaceholderText', $options ) )
        $options['blockedContentPlaceholderText'] = self::transform_text_for_web( $options['blockedContentPlaceholderText'] );

      $last_update = $new_version;
    }

    $new_version = '2.2.0';
    if ( \version_compare( $last_update, $new_version, '<' ) ) {
      if ( $multilanguage_plugin = Multilanguage::is_multilanguage() ) {
        $i    = 0;
        $ipp  = 5;
        do {
          $banners = \get_posts( array(
            'post_type'          => self::BannerPostType,
            'lang'               => '',
            'posts_per_page'     => $ipp,
            'offset'             => $i * $ipp,
          ) );
          foreach ( $banners as $banner ) {
            $meta = \get_post_meta( $banner->ID, 'SCK_BannerTexts', true );
            if ( ! \is_array( $meta ) ) $meta = array();

            $meta['cookieBannerText'] = $banner->post_content;
            \update_post_meta( $banner->ID, 'SCK_BannerTexts', $meta );
            \wp_update_post( array( 'ID' => $banner->ID, 'post_content' => '' ) );
          }
          $i++;
        } while ( 0 < \count( $banners ) );
      }

      $last_update = $new_version;
    }

    $new_version = '2.2.1';
    if ( \version_compare( $last_update, $new_version, '<' ) ) {

      if ( isset( $options['cssDesktopBannerText'] ) ) {
        $options['cssDesktopBannerTextContainer'] = $options['cssDesktopBannerText'];
        $options['cssDesktopBannerText'] = '';
      }
      if ( isset( $options['cssMobileBannerText'] ) ) {
        $options['cssMobileBannerTextContainer'] = $options['cssMobileBannerText'];
        $options['cssMobileBannerText'] = '';
      }

      $last_update = $new_version;
    }

    if ( $options['plugin_version'] != $last_update ) {
      $options['plugin_version'] = $last_update;

      if ( '' != $legacy_mode ) $legacy_mode = '_v' . $legacy_mode;

      \remove_action( 'option_SmartCookieKit_Options' . $legacy_mode, array( 'NMod\SmartCookieKit\Options', 'manage_data_update' . $legacy_mode ) );
      \update_option( 'SmartCookieKit_Options' . $legacy_mode, $options );
    }

    return $options;
  }

  static public function legacy_mode_available() {
    return new \DateTime() < new \DateTime( '2018/05/25 00:00:00' );
  }

  static public function legacy_mode() {
    if ( null === self::$legacy_mode ) {
      self::$legacy_mode = \get_option( 'SmartCookieKit_LegacyMode', 0 );

      if ( self::$legacy_mode ) {
        if ( 1 == self::$legacy_mode ) {
          // Force disabling legacy mode to be GDPR compliant
          if ( ! self::legacy_mode_available() )
            self::$legacy_mode = 0;
        }
      }
    }
    return self::$legacy_mode;
  }

  static public function get( $auto_version = true ) {
    $legacy = true === $auto_version ? self::legacy_mode() : $auto_version;

    if ( null === self::$options || !\array_key_exists( $legacy, self::$options ) ) {
      if ( $legacy && 1 == $legacy )
        self::$options[ $legacy ] = self::sanitize_v1();
      else 
        self::$options[ $legacy ] = self::sanitize_v2();
    }

    return self::$options[ $legacy ];
  }

  static public function get_for_export() {
    $options = self::get();

    unset( $options['plugin_version'] );

    return $options;
  }

  static public function update( $new_options, $auto_version = true ) {
    $legacy = true === $auto_version ? self::legacy_mode() : $auto_version;

    self::$options[ $legacy ] = $new_options;
    if ( $legacy && 1 == $legacy ) {
      \update_option( 'SmartCookieKit_Options', self::$options[ $legacy ] );
    } else {
      \update_option( 'SmartCookieKit_Options_v2', self::$options[ $legacy ] );
    }
  }

  static public function sanitize_v1( $options = null ) {
    if ( \is_null( $options ) ) $options = array();

    $defaults = array(
      'pluginDebugMode'              => false,
      'cookieBannerText'             => \esc_html__( "This website or third-party tools used in it make use of cookies (technical and profiling) for:\n\n- the proper functioning of the site\n- generate navigation statistics\n- show advertising in <b>non invasively</b> way\n\nBy continuing to browse the site you agree to our use of cookies.", 'smart-cookie-kit' ),
      'cookiePolicyPageID'           => 0,
      'cookiePolicyPageURL'          => '',
      'cookiePolicyLinkText'         => \esc_html__( 'To learn more or opt out, click here to read the cookie policy.', 'smart-cookie-kit' ),
      'cookieAcceptedButtonText'     => \esc_html__( 'Accept', 'smart-cookie-kit' ),
      'cookieAcceptedLife'           => 3000,
      'cssBannerBackLayer'           => false,
      'cssBannerBackground'          => '',
      'cssBannerContainer'           => 'background-color:rgba(0,0,0,0.9);position:fixed;padding:2em;bottom:1em;width:94%;left:3%;z-index:99999999;',
      'cssBannerText'                => 'color:rgb(255,255,255);display:block;line-height:1.5em;',
      'cssBannerButtonsArea'         => 'display:block;text-align:center;line-height:1.2em;margin-top:3em;',
      'cssBannerPolicyLink'          => '',
      'cssBannerAcceptButton'        => 'border:none;cursor:pointer;padding:10px 0;width:50%;',
      'pluginScriptInHeader'         => false,
      'acceptPolicyOnScroll'         => true,
      'acceptPolicyOnContentClick'   => true,
      'excludedParentsClick'         => '',
      'saveLogToServer'              => false,
      'plugin_version'               => '0',
    );

    $options = \array_merge( \get_option( 'SmartCookieKit_Options', array() ), $options );

    // pluginDebugMode
    if ( ! isset( $options['pluginDebugMode'] ) ) {
      $options['pluginDebugMode'] = $defaults['pluginDebugMode'];
    }

    // cookieBannerText
    if ( isset( $options['cookieBannerText'] ) ) {
      if ( '' == $options['cookieBannerText'] ) {
        $options['cookieBannerText'] = $defaults['cookieBannerText'];
      } else {
      }
    } else {
      $options['cookieBannerText'] = $defaults['cookieBannerText'];
    }
    $options['cookieBannerText'] = \wp_strip_all_tags( $options['cookieBannerText'] );

    // cookiePolicyPageID
    if ( isset( $options['cookiePolicyPageID'] ) ) {
      if ( \is_numeric( $options['cookiePolicyPageID'] ) ) {
        if ( 0 < $options['cookiePolicyPageID'] ) {
          $pages = get_pages( array( 'include' => array( $options['cookiePolicyPageID'] ) ) );
          if ( 1 == \count( $pages ) ) {
            if ( 'publish' != $pages[0]->post_status ) {
              $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
            }
          } else {
            $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
          }
        }
      } else {
        $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
      }
    } else {
      $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
    }

    // cookiePolicyPageURL
    if ( -1 == $options['cookiePolicyPageID'] ) {
      if ( isset( $options['cookiePolicyPageURL'] ) ) {
        if ( false === \filter_var( $options['cookiePolicyPageURL'], FILTER_VALIDATE_URL ) ) {
          $options['cookiePolicyPageURL'] = $defaults['cookiePolicyPageURL'];
        }
      } else {
        $options['cookiePolicyPageURL'] = $defaults['cookiePolicyPageURL'];
      }
    } else {
      $options['cookiePolicyPageURL'] = $defaults['cookiePolicyPageURL'];
    }

    // cookiePolicyPageURL
    if ( 0 != $options['cookiePolicyPageID'] ) {
      // cookiePolicyLinkText
      if ( isset( $options['cookiePolicyLinkText'] ) ) {
        if ( '' == $options['cookiePolicyLinkText'] ) {
          $options['cookiePolicyLinkText'] = $defaults['cookiePolicyLinkText'];
        }
      } else {
        $options['cookiePolicyLinkText'] = $defaults['cookiePolicyLinkText'];
      }
      $options['cookiePolicyLinkText'] = \wp_strip_all_tags( $options['cookiePolicyLinkText'] );
    } else {
      $options['cookiePolicyLinkText'] = '';
    }

    // cookieAcceptedButtonText
    if ( isset( $options['cookieAcceptedButtonText'] ) ) {
      if ( '' == $options['cookieAcceptedButtonText'] ) {
        $options['cookieAcceptedButtonText'] = $defaults['cookieAcceptedButtonText'];
      }
    } else {
      $options['cookieAcceptedButtonText'] = $defaults['cookieAcceptedButtonText'];
    }
    $options['cookieAcceptedButtonText'] = \wp_strip_all_tags( $options['cookieAcceptedButtonText'] );

    // cookieAcceptedLife
    if ( isset( $options['cookieAcceptedLife'] ) ) {
      if ( ! \is_numeric( $options['cookieAcceptedLife'] ) ) {
        $options['cookieAcceptedLife'] = $defaults['cookieAcceptedLife'];
      }
    } else {
      $options['cookieAcceptedLife'] = $defaults['cookieAcceptedLife'];
    }

    // cssBannerBackLayer
    if ( isset( $options['cssBannerBackLayer'] ) ) {
      if ( ! \is_numeric( $options['cssBannerBackLayer'] ) ) {
        $options['cssBannerBackLayer'] = $defaults['cssBannerBackLayer'];
      }
    } else {
      // Se cssBannerContainer esiste nelle opzioni vuol dire che il plugin era precedentemente
      // installato, quindi effettuo un override del valore di default di cssBannerBackLayer per
      // garantire la retrocompatibilità con le configurazioni precedenti
      if ( isset( $options['cssBannerContainer'] ) )
        $options['cssBannerBackLayer'] = true;
      else
        $options['cssBannerBackLayer'] = $defaults['cssBannerBackLayer'];
    }

    // cssBannerBackground
    if ( isset( $options['cssBannerBackground'] ) ) {
      if ( '' == $options['cssBannerBackground'] ) {
        $options['cssBannerBackground'] = $defaults['cssBannerBackground'];
      }
    } else {
      $options['cssBannerBackground'] = $defaults['cssBannerBackground'];
    }
    $options['cssBannerBackground'] = \wp_strip_all_tags( $options['cssBannerBackground'] );

    // cssBannerContainer
    if ( isset( $options['cssBannerContainer'] ) ) {
      if ( '' == $options['cssBannerContainer'] ) {
        $options['cssBannerContainer'] = $defaults['cssBannerContainer'];
      }
    } else {
      $options['cssBannerContainer'] = $defaults['cssBannerContainer'];
    }
    $options['cssBannerContainer'] = \wp_strip_all_tags( $options['cssBannerContainer'] );

    // cssBannerText
    if ( isset( $options['cssBannerText'] ) ) {
      if ( '' == $options['cssBannerText'] ) {
        $options['cssBannerText'] = $defaults['cssBannerText'];
      }
    } else {
      $options['cssBannerText'] = $defaults['cssBannerText'];
    }
    $options['cssBannerText'] = \wp_strip_all_tags( $options['cssBannerText'] );

    // cssBannerButtonsArea
    if ( isset( $options['cssBannerButtonsArea'] ) ) {
      if ( '' == $options['cssBannerButtonsArea'] ) {
        $options['cssBannerButtonsArea'] = $defaults['cssBannerButtonsArea'];
      }
    } else {
      $options['cssBannerButtonsArea'] = $defaults['cssBannerButtonsArea'];
    }
    $options['cssBannerButtonsArea'] = \wp_strip_all_tags( $options['cssBannerButtonsArea'] );

    // cssBannerPolicyLink
    if ( isset( $options['cssBannerPolicyLink'] ) ) {
      if ( '' == $options['cssBannerPolicyLink'] ) {
        $options['cssBannerPolicyLink'] = $defaults['cssBannerPolicyLink'];
      }
    } else {
      $options['cssBannerPolicyLink'] = $defaults['cssBannerPolicyLink'];
    }
    $options['cssBannerPolicyLink'] = \wp_strip_all_tags( $options['cssBannerPolicyLink'] );

    // cssBannerAcceptButton
    if ( isset( $options['cssBannerAcceptButton'] ) ) {
      if ( '' == $options['cssBannerAcceptButton'] ) {
        $options['cssBannerAcceptButton'] = $defaults['cssBannerAcceptButton'];
      }
    } else {
      $options['cssBannerAcceptButton'] = $defaults['cssBannerAcceptButton'];
    }
    $options['cssBannerAcceptButton'] = \wp_strip_all_tags( $options['cssBannerAcceptButton'] );

    // pluginScriptInHeader
    if ( isset( $options['pluginScriptInHeader'] ) ) {
      if ( ! \is_numeric( $options['pluginScriptInHeader'] ) ) {
        $options['pluginScriptInHeader'] = $defaults['pluginScriptInHeader'];
      }
    } else {
      $options['pluginScriptInHeader'] = $defaults['pluginScriptInHeader'];
    }

    // acceptPolicyOnScroll
    if ( isset( $options['acceptPolicyOnScroll'] ) ) {
      if ( ! \is_numeric( $options['acceptPolicyOnScroll'] ) ) {
        $options['acceptPolicyOnScroll'] = $defaults['acceptPolicyOnScroll'];
      }
    } else {
      $options['acceptPolicyOnScroll'] = $defaults['acceptPolicyOnScroll'];
    }

    // acceptPolicyOnContentClick
    if ( isset( $options['acceptPolicyOnContentClick'] ) ) {
      if ( ! \is_numeric( $options['acceptPolicyOnContentClick'] ) ) {
        $options['acceptPolicyOnContentClick'] = $defaults['acceptPolicyOnContentClick'];
      }
    } else {
      $options['acceptPolicyOnContentClick'] = $defaults['acceptPolicyOnContentClick'];
    }

    // excludedParentsClick
    if ( isset( $options['excludedParentsClick'] ) ) {
      if ( '' == $options['excludedParentsClick'] ) {
        $options['excludedParentsClick'] = $defaults['excludedParentsClick'];
      } else {
      }
    } else {
      $options['excludedParentsClick'] = $defaults['excludedParentsClick'];
    }
    $options['excludedParentsClick'] = \wp_strip_all_tags( $options['excludedParentsClick'] );

    // saveLogToServer
    if ( isset( $options['saveLogToServer'] ) ) {
      if ( ! \is_numeric( $options['saveLogToServer'] ) ) {
        $options['saveLogToServer'] = $defaults['saveLogToServer'];
      }
    } else {
      $options['saveLogToServer'] = $defaults['saveLogToServer'];
    }

    return $options;
  }

  static public function get_banner_text_fields( $auto_version = true ) {
    $legacy = true === $auto_version ? self::legacy_mode() : $auto_version;

    if ( $legacy && 1 == $legacy ) {
      return array();
    } else {
      self::get( $auto_version );

      $policy_page_note = '';

      return array(
        array(
          'name'             => 'cookieBannerText',
          'label'            => \esc_html__( 'Banner text', 'smart-cookie-kit' ),
          'desc'             => \esc_html__( 'Customize banner text', 'smart-cookie-kit' ),
          'type'             => 'editor',
        ),
        array(
          'name'             => 'blockedContentPlaceholderText',
          'label'            => \esc_html__( 'Placeholder text', 'smart-cookie-kit' ),
          'desc'             => \esc_html__( 'Customize placeholders text', 'smart-cookie-kit' ),
          'help'             => \esc_html__( 'In this text box you can use the following variables:', 'smart-cookie-kit' )
                                  . '<br /><br /><strong>%SERVICE_NAME%</strong> ' . \esc_html__( 'to specify what service/functionality has been blocked', 'smart-cookie-kit' ),
          'type'             => 'editor',
        ),
        array(
          'name'             => 'cookieEnableButtonText',
          'label'            => \esc_html__( 'Accept button text', 'smart-cookie-kit' ),
          'desc'             => \esc_html__( 'Customize the button text to enable cookies.', 'smart-cookie-kit' ),
          'type'             => 'textbox',
        ),
        array(
          'name'             => 'cookieEnabledButtonText',
          'label'            => \esc_html__( 'Accepted button text', 'smart-cookie-kit' ),
          'desc'             => \esc_html__( 'Customize the button text to keep cookies enabled.', 'smart-cookie-kit' ),
          'type'             => 'textbox',
        ),
        array(
          'name'             => 'cookieDisableLinkText',
          'label'            => \esc_html__( 'Disable link text', 'smart-cookie-kit' ),
          'desc'             => \esc_html__( 'Customize the text to disable cookies.', 'smart-cookie-kit' ),
          'type'             => 'textbox',
        ),
        array(
          'name'             => 'cookieDisabledLinkText',
          'label'            => \esc_html__( 'Disabled link text', 'smart-cookie-kit' ),
          'desc'             => \esc_html__( 'Customize the text to keep cookies disabled.', 'smart-cookie-kit' ),
          'type'             => 'textbox',
        ),
        array(
          'name'             => 'minimizedSettingsButtonText',
          'label'            => \esc_html__( 'Minimized settings button text', 'smart-cookie-kit' ),
          'desc'             => \esc_html__( 'Customize the text shown into the minimized settings button.', 'smart-cookie-kit' ),
          'type'             => 'textbox',
          'note'             => 1 != self::$options[ $legacy ]['showMinimizedButton'] ? array(
            'text'  => \sprintf( \esc_html__( 'NOTE: this field will not be used due to the value of "%s" field in logic tab.', 'smart-cookie-kit' ), \esc_html__( 'Minimized button for cookie settings', 'smart-cookie-kit' ) ),
            'type'  => 'warning'
          ) : array()
        )
      );      
    }
  }

  static public function get_page_list() {
    $page_list = array();

    global $post;
    if ( isset( $post ) ) {
      $page_list[ '-2' ] = \esc_html__( 'Default', 'smart-cookie-kit' );
    }

    $page_list[ '-1' ] = \esc_html__( 'Custom' , 'smart-cookie-kit' );
    $page_list[  '0' ] = \esc_html__( 'None'   , 'smart-cookie-kit' );

    $i    = 0;
    $ipp  = 2;
    do {
      $pages = \get_posts( array(
        'post_type'          => 'page',
        'posts_per_page'     => $ipp,
        'offset'             => $i * $ipp,
        'post_status'        => 'publish'
      ) );
      foreach ( $pages as $page ) $page_list[ $page->ID ] = $page->post_title;
      $i++;
    } while ( 0 < \count( $pages ) );

    return $page_list;
  }

  static public function sanitize_v2( $options = null ) {
    if ( \is_null( $options ) ) $options = array();

    $defaults = array(
      'pluginDebugMode'                   => false,

      'cookieBannerContentID'             => 0,
      'cookieBannerText'                  => \esc_html__( 'On this website we use first or third-party tools that store small files (<i>cookie</i>) on your device. Cookies are normally used to allow the site to run properly (<i>technical cookies</i>), to generate navigation usage reports (<i>statistics cookies</i>) and to suitable advertise our services/products (<i>profiling cookies</i>). We can directly use technical cookies, but <u>you have the right to choose whether or not to enable statistical and profiling cookies</u>. <b>Enabling these cookies, you help us to offer you a better experience</b>.', 'smart-cookie-kit' ),
      'blockedContentPlaceholderText'     => \esc_html__( "<b>Some contents or functionalities here are not available due to your cookie preferences!</b>\n\nThis happens because the functionality/content marked as \"%SERVICE_NAME%\" uses cookies that you choosed to keep disabled. In order to view this content or use this functionality, please enable cookies: [cookie_banner_link text=\"click here to open your cookie preferences\"].", 'smart-cookie-kit' ),
      'cookiePolicyPageID'                => 0,
      'cookiePolicyPageURL'               => '',
      'cookiePolicyLinkText'              => \esc_html__( 'Cookie policy', 'smart-cookie-kit' ),
      'userSettingsLinkText'              => \esc_html__( 'Cookie settings', 'smart-cookie-kit' ),
      'cookieEnableButtonText'            => \esc_html__( 'Enable', 'smart-cookie-kit' ),
      'cookieEnabledButtonText'           => \esc_html__( 'Keep enabled', 'smart-cookie-kit' ),
      'cookieDisableLinkText'             => \esc_html__( 'Disable cookies', 'smart-cookie-kit' ),
      'cookieDisabledLinkText'            => \esc_html__( 'Keep disabled', 'smart-cookie-kit' ),
      'minimizedSettingsButtonText'       => \esc_html__( 'Cookie preferences', 'smart-cookie-kit' ),

      'cookieAcceptedLife'                => 365,
/*
      'cssBannerContainer'                => 'background-color:#fff;position:fixed;padding:2em;bottom:1em;width:94%;left:3%;z-index:99999999;box-shadow:0 0 10px #000',
      'cssBannerText'                     => 'color:#000;display:block;line-height:1.5em;',
      'cssBannerActionsArea'              => 'display:block;line-height:1.2em;margin-top:2em;position:relative;',
      'cssBannerLinksList'                => 'float:left;',
      'cssBannerLinksListItem'            => 'margin-bottom:5px;',
      'cssBannerActionsButtons'           => 'display:block;text-align:center;float:right;',
      'cssBannerAcceptButton'             => 'cursor:pointer;padding:10px 40px;height:auto;width:auto;line-height:initial;border:none;background-color:#1dae1c;background-image:none;color:#fff;text-shadow:none;text-transform:uppercase;font-weight:bold;transition:.2s;margin-bottom:15px;',
      'cssBannerAcceptButtonHover'        => 'background-color:#23da22;text-shadow:0 0 1px #000',
      'cssBannerCloseLink'                => '',
      'cssBannerCloseLinkHover'           => '',
*/
      'cssMobileContentPlaceholder'              => 'background-color:#f6f6f6;border:1px solid #c9cccb;margin:1em;padding:2em;color:black;',
      'cssMobileBannerBackLayer'                 => '',
      'cssMobileBannerContainer'                 => 'background-color:#fff;position:fixed;padding:2em;bottom:1em;height:auto;width:94%;left:3%;z-index:99999999;box-shadow:0 0 10px #000;box-sizing:border-box;max-height:calc(100vh - 2em);overflow:scroll;',
      'cssMobileBannerTextContainer'             => 'display:block;',
      'cssMobileBannerText'                      => '',
      'cssMobileBannerActionsArea'               => 'display:block;line-height:1.2em;margin-top:2em;position:relative;',
      'cssMobileBannerLinksList'                 => 'float:left;',
      'cssMobileBannerLinksListItem'             => 'margin-bottom:5px;',
      'cssMobileBannerActionsButtons'            => 'display:block;text-align:right;float:right;',
      'cssMobileBannerAcceptButton'              => 'cursor:pointer;padding:10px 40px;height:auto;width:auto;line-height:initial;border:none;border-radius:0;background-color:#1dae1c;background-image:none;color:#fff;text-shadow:none;text-transform:uppercase;font-weight:bold;transition:.2s;margin-bottom:0;float:right;',
      'cssMobileBannerAcceptButtonHover'         => 'background-color:#23da22;text-shadow:0 0 1px #000',
      'cssMobileBannerCloseLink'                 => 'float:right;padding:10px 0;margin-right:30px;',
      'cssMobileBannerCloseLinkHover'            => '',
      'cssMobileMinimizedSettingsButton'         => 'background-color:#1dae1c;background-image:none;color:#fff;text-shadow:none;font-weight:bold;transition:.2s;position:fixed;padding:5px 15px;bottom:0;height:auto;width:auto;left:5%;z-index:99999999;box-shadow:0 0 10px #000;border:none;border-radius:0;font-size:12px;line-height:initial;cursor:pointer;',
      'cssMobileMinimizedSettingsButtonHover'    => 'background-color:#23da22;text-shadow:0 0 1px #000',

      'cssDesktopContentPlaceholder'             => '',
      'cssDesktopBannerBackLayer'                => 'background-color:rgba(84,84,84,.5);position:fixed;top:0;left:0;height:100%;width:100%;overflow:hidden;z-index:99999998;',
      'cssDesktopBannerContainer'                => '',
      'cssDesktopBannerTextContainer'            => 'float:left;width:75%;',
      'cssDesktopBannerText'                     => '',
      'cssDesktopBannerActionsArea'              => 'position:absolute;bottom:2em;right:2em;',
      'cssDesktopBannerLinksList'                => '',
      'cssDesktopBannerLinksListItem'            => '',
      'cssDesktopBannerActionsButtons'           => '',
      'cssDesktopBannerAcceptButton'             => 'float:none;margin-bottom:15px;display:block;',
      'cssDesktopBannerAcceptButtonHover'        => '',
      'cssDesktopBannerCloseLink'                => 'float:none;padding:0;margin-right:0;',
      'cssDesktopBannerCloseLinkHover'           => '',
      'cssDesktopMinimizedSettingsButton'        => '',
      'cssDesktopMinimizedSettingsButtonHover'   => '',

      'addBlockedContentPlaceholder'             => false,
      'blockGoogleTagManager'                    => false,
      'blockGoogleReCaptcha'                     => true,
      'blockAutomateWooSessionTracking'          => true,
      'facebookPixelCompatibilityMode'           => false,
      'addBannerBackLayer'                       => false,
      'saveLogToServer'                          => true,
      'showMinimizedButton'                      => false,
      'reloadPageWhenDisabled'                   => false,
      'acceptPolicyOnScroll'                     => false,

      'plugin_version'                           => '0'
    );

    $options = \array_merge( \get_option( 'SmartCookieKit_Options_v2', array() ), $options );

    // pluginDebugMode
    if ( ! isset( $options['pluginDebugMode'] ) ) {
      $options['pluginDebugMode'] = $defaults['pluginDebugMode'];
    }

    // cookieBannerContentID
    if ( isset( $options['cookieBannerContentID'] ) ) {
      if ( \is_numeric( $options['cookieBannerContentID'] ) ) {
        if ( 0 < $options['cookieBannerContentID'] ) {

          if ( self::BannerPostType == \get_post_type( $options['cookieBannerContentID'] ) ) {
            if ( \is_null( \get_post( $options['cookieBannerContentID'] ) ) ) {
              $options['cookieBannerContentID'] = $defaults['cookieBannerContentID'];
            }
          } else {
            $options['cookieBannerContentID'] = $defaults['cookieBannerContentID'];
          }
        }
      } else {
        $options['cookieBannerContentID'] = $defaults['cookieBannerContentID'];
      }
    } else {
      $options['cookieBannerContentID'] = $defaults['cookieBannerContentID'];
    }

    // cookieBannerText
    if ( isset( $options['cookieBannerText'] ) ) {
      if ( '' == $options['cookieBannerText'] ) {
        $options['cookieBannerText'] = $defaults['cookieBannerText'];
      } else {
        $options['cookieBannerText'] = \wp_kses_post( $options['cookieBannerText'] );
      }
    } else {
      $options['cookieBannerText'] = $defaults['cookieBannerText'];
    }
    //$options['cookieBannerText'] = \wp_strip_all_tags( $options['cookieBannerText'] );
    //$options['cookieBannerText'] = \wp_strip_all_tags( $options['cookieBannerText'], 'smart-cookie-kit' );

    // blockedContentPlaceholderText
    if ( isset( $options['blockedContentPlaceholderText'] ) ) {
      if ( '' == $options['blockedContentPlaceholderText'] ) {
        $options['blockedContentPlaceholderText'] = $defaults['blockedContentPlaceholderText'];
      } else {
        $options['blockedContentPlaceholderText'] = \wp_kses_post( $options['blockedContentPlaceholderText'] );
      }
    } else {
      $options['blockedContentPlaceholderText'] = $defaults['blockedContentPlaceholderText'];
    }
    //$options['blockedContentPlaceholderText'] = \wp_strip_all_tags( $options['blockedContentPlaceholderText'] );
    //$options['blockedContentPlaceholderText'] = \wp_strip_all_tags( $options['blockedContentPlaceholderText'], 'smart-cookie-kit' );

    // cookiePolicyPageID
    if ( isset( $options['cookiePolicyPageID'] ) ) {
      if ( ! \is_numeric( $options['cookiePolicyPageID'] ) ) {
        $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
      }
    } else {
      $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
    }
/*



    if ( isset( $options['cookiePolicyPageID'] ) ) {
      if ( \is_numeric( $options['cookiePolicyPageID'] ) ) {
        if ( 0 < $options['cookiePolicyPageID'] ) {
          $page = \get_post( $options['cookiePolicyPageID'] );
          if ( ! \is_null( $page ) ) {
            if ( 'publish' != $page->post_status ) {
              $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
            }
          } else {
            $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
          }
          //*
          $pages = get_pages( array( 'include' => array( $options['cookiePolicyPageID'] ) ) );
          if ( 1 == \count( $pages ) ) {
            if ( 'publish' != $pages[0]->post_status ) {
              $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
            }
          } else {
            $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
          }
          * /
        }
      } else {
        $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
      }
    } else {
      $options['cookiePolicyPageID'] = $defaults['cookiePolicyPageID'];
    }*/

    // cookiePolicyPageURL
    if ( -1 == $options['cookiePolicyPageID'] ) {
      if ( isset( $options['cookiePolicyPageURL'] ) ) {
        if ( false === \filter_var( $options['cookiePolicyPageURL'], FILTER_VALIDATE_URL ) ) {
          $options['cookiePolicyPageURL'] = $defaults['cookiePolicyPageURL'];
        }
      } else {
        $options['cookiePolicyPageURL'] = $defaults['cookiePolicyPageURL'];
      }
    } else {
      $options['cookiePolicyPageURL'] = $defaults['cookiePolicyPageURL'];
    }

//    if ( 0 != $options['cookiePolicyPageID'] ) {
      // cookiePolicyLinkText
      if ( isset( $options['cookiePolicyLinkText'] ) ) {
        if ( '' == $options['cookiePolicyLinkText'] ) {
          $options['cookiePolicyLinkText'] = $defaults['cookiePolicyLinkText'];
        }
      } else {
        $options['cookiePolicyLinkText'] = $defaults['cookiePolicyLinkText'];
      }
      //$options['cookiePolicyLinkText'] = \wp_strip_all_tags( $options['cookiePolicyLinkText'] );
    $options['cookiePolicyLinkText'] = \wp_strip_all_tags( $options['cookiePolicyLinkText'], 'smart-cookie-kit' );
//    } else {
//      $options['cookiePolicyLinkText'] = '';
//    }


    // userSettingsLinkText
    if ( isset( $options['userSettingsLinkText'] ) ) {
      if ( '' == $options['userSettingsLinkText'] ) {
        $options['userSettingsLinkText'] = $defaults['userSettingsLinkText'];
      }
    } else {
      $options['userSettingsLinkText'] = $defaults['userSettingsLinkText'];
    }
    //$options['userSettingsLinkText'] = \wp_strip_all_tags( $options['userSettingsLinkText'] );
    $options['userSettingsLinkText'] = \wp_strip_all_tags( $options['userSettingsLinkText'], 'smart-cookie-kit' );

    // cookieEnableButtonText
    if ( isset( $options['cookieEnableButtonText'] ) ) {
      if ( '' == $options['cookieEnableButtonText'] ) {
        $options['cookieEnableButtonText'] = $defaults['cookieEnableButtonText'];
      }
    } else {
      $options['cookieEnableButtonText'] = $defaults['cookieEnableButtonText'];
    }
    //$options['cookieEnableButtonText'] = \wp_strip_all_tags( $options['cookieEnableButtonText'] );
    $options['cookieEnableButtonText'] = \wp_strip_all_tags( $options['cookieEnableButtonText'], 'smart-cookie-kit' );

    // cookieEnabledButtonText
    if ( isset( $options['cookieEnabledButtonText'] ) ) {
      if ( '' == $options['cookieEnabledButtonText'] ) {
        $options['cookieEnabledButtonText'] = $defaults['cookieEnabledButtonText'];
      }
    } else {
      $options['cookieEnabledButtonText'] = $defaults['cookieEnabledButtonText'];
    }
    //$options['cookieEnabledButtonText'] = \wp_strip_all_tags( $options['cookieEnabledButtonText'] );
    $options['cookieEnabledButtonText'] = \wp_strip_all_tags( $options['cookieEnabledButtonText'], 'smart-cookie-kit' );

    // cookieDisableLinkText
    if ( isset( $options['cookieDisableLinkText'] ) ) {
      if ( '' == $options['cookieDisableLinkText'] ) {
        $options['cookieDisableLinkText'] = $defaults['cookieDisableLinkText'];
      }
    } else {
      $options['cookieDisableLinkText'] = $defaults['cookieDisableLinkText'];
    }
    //$options['cookieDisableLinkText'] = \wp_strip_all_tags( $options['cookieDisableLinkText'] );
    $options['cookieDisableLinkText'] = \wp_strip_all_tags( $options['cookieDisableLinkText'], 'smart-cookie-kit' );

    // cookieDisabledLinkText
    if ( isset( $options['cookieDisabledLinkText'] ) ) {
      if ( '' == $options['cookieDisabledLinkText'] ) {
        $options['cookieDisabledLinkText'] = $defaults['cookieDisabledLinkText'];
      }
    } else {
      $options['cookieDisabledLinkText'] = $defaults['cookieDisabledLinkText'];
    }
    //$options['cookieDisabledLinkText'] = \wp_strip_all_tags( $options['cookieDisabledLinkText'] );
    $options['cookieDisabledLinkText'] = \wp_strip_all_tags( $options['cookieDisabledLinkText'], 'smart-cookie-kit' );

    // minimizedSettingsButtonText
    if ( isset( $options['minimizedSettingsButtonText'] ) ) {
      if ( '' == $options['minimizedSettingsButtonText'] ) {
        $options['minimizedSettingsButtonText'] = $defaults['minimizedSettingsButtonText'];
      }
    } else {
      $options['minimizedSettingsButtonText'] = $defaults['minimizedSettingsButtonText'];
    }
    //$options['minimizedSettingsButtonText'] = \wp_strip_all_tags( $options['minimizedSettingsButtonText'] );
    $options['minimizedSettingsButtonText'] = \wp_strip_all_tags( $options['minimizedSettingsButtonText'], 'smart-cookie-kit' );

    // cookieAcceptedLife
    if ( isset( $options['cookieAcceptedLife'] ) ) {
      if ( ! \is_numeric( $options['cookieAcceptedLife'] ) ) {
        $options['cookieAcceptedLife'] = $defaults['cookieAcceptedLife'];
      }
    } else {
      $options['cookieAcceptedLife'] = $defaults['cookieAcceptedLife'];
    }

    // blockGoogleTagManager
    if ( isset( $options['blockGoogleTagManager'] ) ) {
      if ( ! \is_numeric( $options['blockGoogleTagManager'] ) ) {
        $options['blockGoogleTagManager'] = $defaults['blockGoogleTagManager'];
      }
    } else {
      $options['blockGoogleTagManager'] = $defaults['blockGoogleTagManager'];
    }

    // blockGoogleReCaptcha
    if ( isset( $options['blockGoogleReCaptcha'] ) ) {
      if ( ! \is_numeric( $options['blockGoogleReCaptcha'] ) ) {
        $options['blockGoogleReCaptcha'] = $defaults['blockGoogleReCaptcha'];
      }
    } else {
      $options['blockGoogleReCaptcha'] = $defaults['blockGoogleReCaptcha'];
    }    

    // blockAutomateWooSessionTracking
    if ( isset( $options['blockAutomateWooSessionTracking'] ) ) {
      if ( ! \is_numeric( $options['blockAutomateWooSessionTracking'] ) ) {
        $options['blockAutomateWooSessionTracking'] = $defaults['blockAutomateWooSessionTracking'];
      }
    } else {
      $options['blockAutomateWooSessionTracking'] = $defaults['blockAutomateWooSessionTracking'];
    }

    // facebookPixelCompatibilityMode
    if ( isset( $options['facebookPixelCompatibilityMode'] ) ) {
      if ( ! \is_numeric( $options['facebookPixelCompatibilityMode'] ) ) {
        $options['facebookPixelCompatibilityMode'] = $defaults['facebookPixelCompatibilityMode'];
      }
    } else {
      $options['facebookPixelCompatibilityMode'] = $defaults['facebookPixelCompatibilityMode'];
    }
    
    // addBannerBackLayer
    if ( isset( $options['addBannerBackLayer'] ) ) {
      if ( ! \is_numeric( $options['addBannerBackLayer'] ) ) {
        $options['addBannerBackLayer'] = $defaults['addBannerBackLayer'];
      }
    } else {
      $options['addBannerBackLayer'] = $defaults['addBannerBackLayer'];
    }

    // cssMobileContentPlaceholder
    if ( isset( $options['cssMobileContentPlaceholder'] ) ) {
      if ( '' == $options['cssMobileContentPlaceholder'] ) {
        $options['cssMobileContentPlaceholder'] = $defaults['cssMobileContentPlaceholder'];
      }
    } else {
      $options['cssMobileContentPlaceholder'] = $defaults['cssMobileContentPlaceholder'];
    }
    $options['cssMobileContentPlaceholder'] = \wp_strip_all_tags( $options['cssMobileContentPlaceholder'] );

    // cssMobileBannerBackLayer
    if ( isset( $options['cssMobileBannerBackLayer'] ) ) {
      if ( '' == $options['cssMobileBannerBackLayer'] ) {
        $options['cssMobileBannerBackLayer'] = $defaults['cssMobileBannerBackLayer'];
      }
    } else {
      $options['cssMobileBannerBackLayer'] = $defaults['cssMobileBannerBackLayer'];
    }
    $options['cssMobileBannerBackLayer'] = \wp_strip_all_tags( $options['cssMobileBannerBackLayer'] );

    // cssMobileBannerContainer
    if ( isset( $options['cssMobileBannerContainer'] ) ) {
      if ( '' == $options['cssMobileBannerContainer'] ) {
        $options['cssMobileBannerContainer'] = $defaults['cssMobileBannerContainer'];
      }
    } else {
      $options['cssMobileBannerContainer'] = $defaults['cssMobileBannerContainer'];
    }
    $options['cssMobileBannerContainer'] = \wp_strip_all_tags( $options['cssMobileBannerContainer'] );

    // cssMobileBannerTextContainer
    if ( isset( $options['cssMobileBannerTextContainer'] ) ) {
      if ( '' == $options['cssMobileBannerTextContainer'] ) {
        $options['cssMobileBannerTextContainer'] = $defaults['cssMobileBannerTextContainer'];
      }
    } else {
      $options['cssMobileBannerTextContainer'] = $defaults['cssMobileBannerTextContainer'];
    }
    $options['cssMobileBannerTextContainer'] = \wp_strip_all_tags( $options['cssMobileBannerTextContainer'] );

    // cssMobileBannerText
    if ( isset( $options['cssMobileBannerText'] ) ) {
      if ( '' == $options['cssMobileBannerText'] ) {
        $options['cssMobileBannerText'] = $defaults['cssMobileBannerText'];
      }
    } else {
      $options['cssMobileBannerText'] = $defaults['cssMobileBannerText'];
    }
    $options['cssMobileBannerText'] = \wp_strip_all_tags( $options['cssMobileBannerText'] );

    // cssMobileBannerActionsArea
    if ( isset( $options['cssMobileBannerActionsArea'] ) ) {
      if ( '' == $options['cssMobileBannerActionsArea'] ) {
        $options['cssMobileBannerActionsArea'] = $defaults['cssMobileBannerActionsArea'];
      }
    } else {
      $options['cssMobileBannerActionsArea'] = $defaults['cssMobileBannerActionsArea'];
    }
    $options['cssMobileBannerActionsArea'] = \wp_strip_all_tags( $options['cssMobileBannerActionsArea'] );

    // cssMobileBannerLinksList
    if ( isset( $options['cssMobileBannerLinksList'] ) ) {
      if ( '' == $options['cssMobileBannerLinksList'] ) {
        $options['cssMobileBannerLinksList'] = $defaults['cssMobileBannerLinksList'];
      }
    } else {
      $options['cssMobileBannerLinksList'] = $defaults['cssMobileBannerLinksList'];
    }
    $options['cssMobileBannerLinksList'] = \wp_strip_all_tags( $options['cssMobileBannerLinksList'] );

    // cssMobileBannerLinksListItem
    if ( isset( $options['cssMobileBannerLinksListItem'] ) ) {
      if ( '' == $options['cssMobileBannerLinksListItem'] ) {
        $options['cssMobileBannerLinksListItem'] = $defaults['cssMobileBannerLinksListItem'];
      }
    } else {
      $options['cssMobileBannerLinksListItem'] = $defaults['cssMobileBannerLinksListItem'];
    }
    $options['cssMobileBannerLinksListItem'] = \wp_strip_all_tags( $options['cssMobileBannerLinksListItem'] );

    // cssMobileBannerActionsButtons
    if ( isset( $options['cssMobileBannerActionsButtons'] ) ) {
      if ( '' == $options['cssMobileBannerActionsButtons'] ) {
        $options['cssMobileBannerActionsButtons'] = $defaults['cssMobileBannerActionsButtons'];
      }
    } else {
      $options['cssMobileBannerActionsButtons'] = $defaults['cssMobileBannerActionsButtons'];
    }
    $options['cssMobileBannerActionsButtons'] = \wp_strip_all_tags( $options['cssMobileBannerActionsButtons'] );

    // cssMobileBannerAcceptButton
    if ( isset( $options['cssMobileBannerAcceptButton'] ) ) {
      if ( '' == $options['cssMobileBannerAcceptButton'] ) {
        $options['cssMobileBannerAcceptButton'] = $defaults['cssMobileBannerAcceptButton'];
      }
    } else {
      $options['cssMobileBannerAcceptButton'] = $defaults['cssMobileBannerAcceptButton'];
    }
    $options['cssMobileBannerAcceptButton'] = \wp_strip_all_tags( $options['cssMobileBannerAcceptButton'] );

    // cssMobileBannerAcceptButtonHover
    if ( isset( $options['cssMobileBannerAcceptButtonHover'] ) ) {
      if ( '' == $options['cssMobileBannerAcceptButtonHover'] ) {
        $options['cssMobileBannerAcceptButtonHover'] = $defaults['cssMobileBannerAcceptButtonHover'];
      }
    } else {
      $options['cssMobileBannerAcceptButtonHover'] = $defaults['cssMobileBannerAcceptButtonHover'];
    }
    $options['cssMobileBannerAcceptButtonHover'] = \wp_strip_all_tags( $options['cssMobileBannerAcceptButtonHover'] );

    // cssMobileBannerCloseLink
    if ( isset( $options['cssMobileBannerCloseLink'] ) ) {
      if ( '' == $options['cssMobileBannerCloseLink'] ) {
        $options['cssMobileBannerCloseLink'] = $defaults['cssMobileBannerCloseLink'];
      }
    } else {
      $options['cssMobileBannerCloseLink'] = $defaults['cssMobileBannerCloseLink'];
    }
    $options['cssMobileBannerCloseLink'] = \wp_strip_all_tags( $options['cssMobileBannerCloseLink'] );

    // cssMobileBannerCloseLinkHover
    if ( isset( $options['cssMobileBannerCloseLinkHoverHover'] ) ) {
      if ( '' == $options['cssMobileBannerCloseLinkHover'] ) {
        $options['cssMobileBannerCloseLinkHover'] = $defaults['cssMobileBannerCloseLinkHover'];
      }
    } else {
      $options['cssMobileBannerCloseLinkHover'] = $defaults['cssMobileBannerCloseLinkHover'];
    }
    $options['cssMobileBannerCloseLinkHover'] = \wp_strip_all_tags( $options['cssMobileBannerCloseLinkHover'] );

    // cssMobileMinimizedSettingsButton
    if ( isset( $options['cssMobileMinimizedSettingsButton'] ) ) {
      if ( '' == $options['cssMobileMinimizedSettingsButton'] ) {
        $options['cssMobileMinimizedSettingsButton'] = $defaults['cssMobileMinimizedSettingsButton'];
      }
    } else {
      $options['cssMobileMinimizedSettingsButton'] = $defaults['cssMobileMinimizedSettingsButton'];
    }
    $options['cssMobileMinimizedSettingsButton'] = \wp_strip_all_tags( $options['cssMobileMinimizedSettingsButton'] );

    // cssMobileMinimizedSettingsButtonHover
    if ( isset( $options['cssMobileMinimizedSettingsButtonHover'] ) ) {
      if ( '' == $options['cssMobileMinimizedSettingsButtonHover'] ) {
        $options['cssMobileMinimizedSettingsButtonHover'] = $defaults['cssMobileMinimizedSettingsButtonHover'];
      }
    } else {
      $options['cssMobileMinimizedSettingsButtonHover'] = $defaults['cssMobileMinimizedSettingsButtonHover'];
    }
    $options['cssMobileMinimizedSettingsButtonHover'] = \wp_strip_all_tags( $options['cssMobileMinimizedSettingsButtonHover'] );

    // cssDesktopContentPlaceholder
    if ( isset( $options['cssDesktopContentPlaceholder'] ) ) {
      if ( '' == $options['cssDesktopContentPlaceholder'] ) {
        $options['cssDesktopContentPlaceholder'] = $defaults['cssDesktopContentPlaceholder'];
      }
    } else {
      $options['cssDesktopContentPlaceholder'] = $defaults['cssDesktopContentPlaceholder'];
    }
    $options['cssDesktopContentPlaceholder'] = \wp_strip_all_tags( $options['cssDesktopContentPlaceholder'] );

    // cssDesktopBannerBackLayer
    if ( isset( $options['cssDesktopBannerBackLayer'] ) ) {
      if ( '' == $options['cssDesktopBannerBackLayer'] ) {
        $options['cssDesktopBannerBackLayer'] = $defaults['cssDesktopBannerBackLayer'];
      }
    } else {
      $options['cssDesktopBannerBackLayer'] = $defaults['cssDesktopBannerBackLayer'];
    }
    $options['cssDesktopBannerBackLayer'] = \wp_strip_all_tags( $options['cssDesktopBannerBackLayer'] );

    // cssDesktopBannerContainer
    if ( isset( $options['cssDesktopBannerContainer'] ) ) {
      if ( '' == $options['cssDesktopBannerContainer'] ) {
        $options['cssDesktopBannerContainer'] = $defaults['cssDesktopBannerContainer'];
      }
    } else {
      $options['cssDesktopBannerContainer'] = $defaults['cssDesktopBannerContainer'];
    }
    $options['cssDesktopBannerContainer'] = \wp_strip_all_tags( $options['cssDesktopBannerContainer'] );

    // cssDesktopBannerTextContainer
    if ( isset( $options['cssDesktopBannerTextContainer'] ) ) {
      if ( '' == $options['cssDesktopBannerTextContainer'] ) {
        $options['cssDesktopBannerTextContainer'] = $defaults['cssDesktopBannerTextContainer'];
      }
    } else {
      $options['cssDesktopBannerTextContainer'] = $defaults['cssDesktopBannerTextContainer'];
    }
    $options['cssDesktopBannerTextContainer'] = \wp_strip_all_tags( $options['cssDesktopBannerTextContainer'] );

    // cssDesktopBannerText
    if ( isset( $options['cssDesktopBannerText'] ) ) {
      if ( '' == $options['cssDesktopBannerText'] ) {
        $options['cssDesktopBannerText'] = $defaults['cssDesktopBannerText'];
      }
    } else {
      $options['cssDesktopBannerText'] = $defaults['cssDesktopBannerText'];
    }
    $options['cssDesktopBannerText'] = \wp_strip_all_tags( $options['cssDesktopBannerText'] );

    // cssDesktopBannerActionsArea
    if ( isset( $options['cssDesktopBannerActionsArea'] ) ) {
      if ( '' == $options['cssDesktopBannerActionsArea'] ) {
        $options['cssDesktopBannerActionsArea'] = $defaults['cssDesktopBannerActionsArea'];
      }
    } else {
      $options['cssDesktopBannerActionsArea'] = $defaults['cssDesktopBannerActionsArea'];
    }
    $options['cssDesktopBannerActionsArea'] = \wp_strip_all_tags( $options['cssDesktopBannerActionsArea'] );

    // cssDesktopBannerLinksList
    if ( isset( $options['cssDesktopBannerLinksList'] ) ) {
      if ( '' == $options['cssDesktopBannerLinksList'] ) {
        $options['cssDesktopBannerLinksList'] = $defaults['cssDesktopBannerLinksList'];
      }
    } else {
      $options['cssDesktopBannerLinksList'] = $defaults['cssDesktopBannerLinksList'];
    }
    $options['cssDesktopBannerLinksList'] = \wp_strip_all_tags( $options['cssDesktopBannerLinksList'] );

    // cssDesktopBannerLinksListItem
    if ( isset( $options['cssDesktopBannerLinksListItem'] ) ) {
      if ( '' == $options['cssDesktopBannerLinksListItem'] ) {
        $options['cssDesktopBannerLinksListItem'] = $defaults['cssDesktopBannerLinksListItem'];
      }
    } else {
      $options['cssDesktopBannerLinksListItem'] = $defaults['cssDesktopBannerLinksListItem'];
    }
    $options['cssDesktopBannerLinksListItem'] = \wp_strip_all_tags( $options['cssDesktopBannerLinksListItem'] );

    // cssDesktopBannerActionsButtons
    if ( isset( $options['cssDesktopBannerActionsButtons'] ) ) {
      if ( '' == $options['cssDesktopBannerActionsButtons'] ) {
        $options['cssDesktopBannerActionsButtons'] = $defaults['cssDesktopBannerActionsButtons'];
      }
    } else {
      $options['cssDesktopBannerActionsButtons'] = $defaults['cssDesktopBannerActionsButtons'];
    }
    $options['cssDesktopBannerActionsButtons'] = \wp_strip_all_tags( $options['cssDesktopBannerActionsButtons'] );

    // cssDesktopBannerAcceptButton
    if ( isset( $options['cssDesktopBannerAcceptButton'] ) ) {
      if ( '' == $options['cssDesktopBannerAcceptButton'] ) {
        $options['cssDesktopBannerAcceptButton'] = $defaults['cssDesktopBannerAcceptButton'];
      }
    } else {
      $options['cssDesktopBannerAcceptButton'] = $defaults['cssDesktopBannerAcceptButton'];
    }
    $options['cssDesktopBannerAcceptButton'] = \wp_strip_all_tags( $options['cssDesktopBannerAcceptButton'] );

    // cssDesktopBannerAcceptButtonHover
    if ( isset( $options['cssDesktopBannerAcceptButtonHover'] ) ) {
      if ( '' == $options['cssDesktopBannerAcceptButtonHover'] ) {
        $options['cssDesktopBannerAcceptButtonHover'] = $defaults['cssDesktopBannerAcceptButtonHover'];
      }
    } else {
      $options['cssDesktopBannerAcceptButtonHover'] = $defaults['cssDesktopBannerAcceptButtonHover'];
    }
    $options['cssDesktopBannerAcceptButtonHover'] = \wp_strip_all_tags( $options['cssDesktopBannerAcceptButtonHover'] );

    // cssDesktopBannerCloseLink
    if ( isset( $options['cssDesktopBannerCloseLink'] ) ) {
      if ( '' == $options['cssDesktopBannerCloseLink'] ) {
        $options['cssDesktopBannerCloseLink'] = $defaults['cssDesktopBannerCloseLink'];
      }
    } else {
      $options['cssDesktopBannerCloseLink'] = $defaults['cssDesktopBannerCloseLink'];
    }
    $options['cssDesktopBannerCloseLink'] = \wp_strip_all_tags( $options['cssDesktopBannerCloseLink'] );

    // cssDesktopBannerCloseLinkHover
    if ( isset( $options['cssDesktopBannerCloseLinkHoverHover'] ) ) {
      if ( '' == $options['cssDesktopBannerCloseLinkHover'] ) {
        $options['cssDesktopBannerCloseLinkHover'] = $defaults['cssDesktopBannerCloseLinkHover'];
      }
    } else {
      $options['cssDesktopBannerCloseLinkHover'] = $defaults['cssDesktopBannerCloseLinkHover'];
    }
    $options['cssDesktopBannerCloseLinkHover'] = \wp_strip_all_tags( $options['cssDesktopBannerCloseLinkHover'] );

    // cssDesktopMinimizedSettingsButton
    if ( isset( $options['cssDesktopMinimizedSettingsButton'] ) ) {
      if ( '' == $options['cssDesktopMinimizedSettingsButton'] ) {
        $options['cssDesktopMinimizedSettingsButton'] = $defaults['cssDesktopMinimizedSettingsButton'];
      }
    } else {
      $options['cssDesktopMinimizedSettingsButton'] = $defaults['cssDesktopMinimizedSettingsButton'];
    }
    $options['cssDesktopMinimizedSettingsButton'] = \wp_strip_all_tags( $options['cssDesktopMinimizedSettingsButton'] );

    // cssDesktopMinimizedSettingsButtonHover
    if ( isset( $options['cssDesktopMinimizedSettingsButtonHover'] ) ) {
      if ( '' == $options['cssDesktopMinimizedSettingsButtonHover'] ) {
        $options['cssDesktopMinimizedSettingsButtonHover'] = $defaults['cssDesktopMinimizedSettingsButtonHover'];
      }
    } else {
      $options['cssDesktopMinimizedSettingsButtonHover'] = $defaults['cssDesktopMinimizedSettingsButtonHover'];
    }
    $options['cssDesktopMinimizedSettingsButtonHover'] = \wp_strip_all_tags( $options['cssDesktopMinimizedSettingsButtonHover'] );

    // addBlockedContentPlaceholder
    if ( isset( $options['addBlockedContentPlaceholder'] ) ) {
      if ( ! \is_numeric( $options['addBlockedContentPlaceholder'] ) ) {
        $options['addBlockedContentPlaceholder'] = $defaults['addBlockedContentPlaceholder'];
      }
    } else {
      $options['addBlockedContentPlaceholder'] = $defaults['addBlockedContentPlaceholder'];
    }

    // saveLogToServer
    if ( isset( $options['saveLogToServer'] ) ) {
      if ( ! \is_numeric( $options['saveLogToServer'] ) ) {
        $options['saveLogToServer'] = $defaults['saveLogToServer'];
      }
    } else {
      $options['saveLogToServer'] = $defaults['saveLogToServer'];
    }

    // showMinimizedButton
    if ( isset( $options['showMinimizedButton'] ) ) {
      if ( ! \is_numeric( $options['showMinimizedButton'] ) ) {
        $options['showMinimizedButton'] = $defaults['showMinimizedButton'];
      }
    } else {
      $options['showMinimizedButton'] = $defaults['showMinimizedButton'];
    }

    // reloadPageWhenDisabled
    if ( isset( $options['reloadPageWhenDisabled'] ) ) {
      if ( ! \is_numeric( $options['reloadPageWhenDisabled'] ) ) {
        $options['reloadPageWhenDisabled'] = $defaults['reloadPageWhenDisabled'];
      }
    } else {
      $options['reloadPageWhenDisabled'] = $defaults['reloadPageWhenDisabled'];
    }

    // acceptPolicyOnScroll
    if ( isset( $options['acceptPolicyOnScroll'] ) ) {
      if ( ! \is_numeric( $options['acceptPolicyOnScroll'] ) ) {
        $options['acceptPolicyOnScroll'] = $defaults['acceptPolicyOnScroll'];
      }
    } else {
      $options['acceptPolicyOnScroll'] = $defaults['acceptPolicyOnScroll'];
    }

    return $options;
  }

  static public function transform_text_for_web( $text ) {
    return \preg_replace(
      array( "~\[br\]~i", "~\[b\]~i", "~\[/b\]~i", "~\[i\]~i", "~\[/i\]~i", "~\[u\]~i", "~\[/u\]~i", "~\[p\]~i", "~\[/p\]~i",  "~\r~", "~\n~"   ),
      array( '<br />'   , '<b>'     , '</b>'     , '<i>'     , '</i>'     , '<u>'     , '</u>'     , '<p>'     , '</p>'     , ''     , '<br />' ),
      $text
    );
  }

  static public function get_translated_texts( $post_id, $auto_version = true ) {
    $legacy = true === $auto_version ? self::legacy_mode() : $auto_version;

    $texts = array();
    
    $options = self::get( $auto_version );
    $meta    = array();

    if ( 0 < $post_id ) {
      $post_id = Multilanguage::get_translated_banner_id( $post_id );

/*
      // Get the translated post (WPML)
      $post_id = apply_filters( 'wpml_object_id', $post_id, self::BannerPostType );

      // Get the translated post (Polylang)
      if ( function_exists( 'pll_get_post' ) ) {
        $post_id = \pll_get_post( $post_id );
      }
*/

      if ( 'publish' == \get_post_status( $post_id ) ) {
        $meta = \get_post_meta( $post_id, 'SCK_BannerTexts', true );
        if ( ! \is_array( $meta ) ) $meta = array();

        /*
        $post = \get_post( $post_id );
        $meta['cookieBannerText'] = $post->post_content;
        */
      }

      // Se esiste un post per la traduzione, allora non si devono inserire link aggiuntivi
      //$meta['cookiePolicyPageID'] = 0;
    }

    // cookieBannerText
    if ( isset( $meta['cookieBannerText'] ) ) {
      if ( '' == $meta['cookieBannerText'] ) {
        $texts['infoText'] = self::transform_text_for_web( $options['cookieBannerText'] );
      } else {
        //$texts['infoText'] = apply_filters( 'the_content', $meta['cookieBannerText'] );
        $texts['infoText'] = $meta['cookieBannerText'];
      }
    } else {
      $texts['infoText'] = self::transform_text_for_web( $options['cookieBannerText'] );
    }
    //$texts['infoText'] = apply_filters( 'the_content', $texts['infoText'] );

    // blockedContentPlaceholderText
    if ( isset( $meta['blockedContentPlaceholderText'] ) ) {
      if ( '' == $meta['blockedContentPlaceholderText'] ) {
        $texts['blockedContentPlaceholderText'] = self::transform_text_for_web( $options['blockedContentPlaceholderText'] );
      } else {
        //$texts['blockedContentPlaceholderText'] = apply_filters( 'the_content', $meta['blockedContentPlaceholderText'] );
        $texts['blockedContentPlaceholderText'] = $meta['blockedContentPlaceholderText'];
      }
    } else {
      $texts['blockedContentPlaceholderText'] = self::transform_text_for_web( $options['blockedContentPlaceholderText'] );
    }
    //$texts['blockedContentPlaceholderText'] = apply_filters( 'the_content', $texts['blockedContentPlaceholderText'] );
//    $texts['blockedContentPlaceholderText'] = $texts['blockedContentPlaceholderText'];

    // enableButtonText
    if ( isset( $meta['cookieEnableButtonText'] ) ) {
      if ( '' == $meta['cookieEnableButtonText'] ) {
        $texts['enableButtonText'] = self::transform_text_for_web( $options['cookieEnableButtonText'] );
      } else {
        $texts['enableButtonText'] = $meta['cookieEnableButtonText'];
      }
    } else {
      $texts['enableButtonText'] = self::transform_text_for_web( $options['cookieEnableButtonText'] );
    }
//    $texts['enableButtonText'] = $texts['enableButtonText'];

    // enabledButtonText
    if ( isset( $meta['cookieEnabledButtonText'] ) ) {
      if ( '' == $meta['cookieEnabledButtonText'] ) {
        $texts['enabledButtonText'] = self::transform_text_for_web( $options['cookieEnabledButtonText'] );
      } else {
        $texts['enabledButtonText'] = $meta['cookieEnabledButtonText'];
      }
    } else {
      $texts['enabledButtonText'] = self::transform_text_for_web( $options['cookieEnabledButtonText'] );
    }
    //$texts['enabledButtonText'] = $texts['enabledButtonText'];

    // disableLinkText
    if ( isset( $meta['cookieDisableLinkText'] ) ) {
      if ( '' == $meta['cookieDisableLinkText'] ) {
        $texts['disableLinkText'] = self::transform_text_for_web( $options['cookieDisableLinkText'] );
      } else {
        $texts['disableLinkText'] = $meta['cookieDisableLinkText'];
      }
    } else {
      $texts['disableLinkText'] = self::transform_text_for_web( $options['cookieDisableLinkText'] );
    }
    //$texts['disableLinkText'] = $texts['disableLinkText'];

    // disabledLinkText
    if ( isset( $meta['cookieDisabledLinkText'] ) ) {
      if ( '' == $meta['cookieDisabledLinkText'] ) {
        $texts['disabledLinkText'] = self::transform_text_for_web( $options['cookieDisabledLinkText'] );
      } else {
        $texts['disabledLinkText'] = $meta['cookieDisabledLinkText'];
      }
    } else {
      $texts['disabledLinkText'] = self::transform_text_for_web( $options['cookieDisabledLinkText'] );
    }
    //$texts['disabledLinkText'] = $texts['disabledLinkText'];

    // policyLinkURI
    // policyLinkText
    $temp_id  = 0;
    if ( isset( $meta['cookiePolicyPageID'] ) ) {
      if ( '' === $meta['cookiePolicyPageID'] ) {        
        $temp_id = $options['cookiePolicyPageID'];
      } else {
        //$temp_id = $meta['cookiePolicyPageID'];
        $temp_id = -2 == $meta['cookiePolicyPageID'] ? $options['cookiePolicyPageID'] : $meta['cookiePolicyPageID'];
      }
    } else {
      $temp_id = $options['cookiePolicyPageID'];
    }
    if ( 0 != $temp_id ) {
      $temp_url = '';
      if ( isset( $meta['cookiePolicyPageURL'] ) ) {
        if ( '' == $meta['cookiePolicyPageURL'] ) {
          $temp_url = self::transform_text_for_web( $options['cookiePolicyPageURL'] );
        } else {
          $temp_url = $meta['cookiePolicyPageURL'];
        }
      } else {
        $temp_url = self::transform_text_for_web( $options['cookiePolicyPageURL'] );
      }

      $temp      = $temp_id < 0 ? $temp_url : \get_page_link( $temp_id );
      if ( '' != $temp ) {
        $texts['policyLinkURI']  = $temp;

        // cookiePolicyLinkText
        if ( isset( $meta['cookiePolicyLinkText'] ) ) {
          if ( '' == $meta['cookiePolicyLinkText'] ) {
            $texts['policyLinkText'] = self::transform_text_for_web( $options['cookiePolicyLinkText'] );
          } else {
            $texts['policyLinkText'] = $meta['cookiePolicyLinkText'];
          }
        } else {
          $texts['policyLinkText'] = self::transform_text_for_web( $options['cookiePolicyLinkText'] );
        }
        //$texts['policyLinkText'] = $texts['policyLinkText'];
      }
    }

    if ( $options['showMinimizedButton'] ) {
      // minimizedSettingsBannerId
      $texts['minimizedSettingsBannerId']     = 'cookie_min_banner';

      // minimizedSettingsButtonText
      if ( isset( $meta['minimizedSettingsButtonText'] ) ) {
        if ( '' == $meta['minimizedSettingsButtonText'] ) {
          $texts['minimizedSettingsButtonText'] = self::transform_text_for_web( $options['minimizedSettingsButtonText'] );
        } else {
          $texts['minimizedSettingsButtonText'] = $meta['minimizedSettingsButtonText'];
        }
      } else {
        $texts['minimizedSettingsButtonText'] = self::transform_text_for_web( $options['minimizedSettingsButtonText'] );
      }
      // $texts['minimizedSettingsButtonText'] = $texts['minimizedSettingsButtonText'];
    }

    return $texts;
  }

  static public function manage_polylang_custom_fields( $metas, $sync ) {
    if ( ! \is_array( $metas ) ) return $metas;

    if ( $sync )
      foreach ( $metas as $key => $value )
        if ( false !== strpos( $value, 'SCK_BannerTexts' ) )
          unset( $metas[ $key ] );

    return $metas;
  }
}