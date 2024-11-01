<?php
namespace NMod\SmartCookieKit;

if ( ! \defined( 'ABSPATH' ) ) exit;

class Multilanguage {
  static private $is_multilanguage;

  static public function is_multilanguage() {
    if ( null === self::$is_multilanguage ) {
      //if ( ! is_admin() ) include_once ABSPATH . 'wp-admin/includes/plugin.php';
      if ( ! \function_exists( 'is_plugin_active' ) ) include_once ABSPATH . 'wp-admin/includes/plugin.php';

      if ( \is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
        $wpml_languages = \apply_filters( 'wpml_active_languages', array() );
        if ( 1 < \count( $wpml_languages ) ) {
          self::$is_multilanguage = 'WPML';
        }
      } elseif ( \is_plugin_active( 'polylang/polylang.php' ) || \is_plugin_active( 'polylang-pro/polylang.php' ) ) {
        self::$is_multilanguage = 'Polylang';
      }
    }

    return self::$is_multilanguage;
  }

  static public function get_translated_banner_id( $post_id, $lang = null ) {
    if ( ! self::is_multilanguage() ) return $post_id;
    if ( 0 >= $post_id ) return $post_id;

    switch ( self::$is_multilanguage ) {
      case 'WPML':
        $translation_id = \apply_filters( 'wpml_object_id', $post_id, Options::BannerPostType, false, $lang );
        if ( is_null( $translation_id ) ) return false;
        return $translation_id;

        break;        
      case 'Polylang':
        if ( \function_exists( '\pll_get_post' ) ) {
          $translation_id = \pll_get_post( $post_id, $lang );

          if ( is_null( $translation_id ) ) return false;
          if ( false === $translation_id )  return false;
          return $translation_id;
        }

        break;
    }

    return false;
  }

  static public function get_translation( $post_id, $lang = null ) {
    if ( ! self::is_multilanguage() ) return $post_id;
    if ( 0 >= $post_id ) return $post_id;

    switch ( self::$is_multilanguage ) {
      case 'WPML':
        $translation_id = \apply_filters( 'wpml_object_id', $post_id, 'page', true );
        return $translation_id;

        break;        
      case 'Polylang':
        if ( \function_exists( '\pll_get_post' ) ) {
          $translation_id = \pll_get_post( $post_id, $lang );

          if ( is_null( $translation_id ) ) $translation_id = $post_id;
          if ( false === $translation_id )  $translation_id = $post_id;
          return $translation_id;
        }

        break;
    }

    return $post_id;
  }

  static public function get_active_languages() {
    if ( ! self::is_multilanguage() ) return false;

    $languages = array();
    switch ( self::$is_multilanguage ) {
      case 'WPML':
        $wpml_languages = \apply_filters( 'wpml_active_languages', null );
        if ( !empty( $wpml_languages ) ) {
          foreach ( $wpml_languages as $language ) {
            $languages[] = $language['language_code'];
          }
        }

        break;
      case 'Polylang':
        if ( \function_exists( '\pll_languages_list' ) ) {
          $polylang_languages = \pll_languages_list( array( 'fields' => 'slug' ) );
          if ( !empty( $polylang_languages ) ) {
            foreach ( $polylang_languages as $language ) {
              $languages[] = $language;
            }
          }
        }

        break;
    }

    return $languages;
  }

  static public function get_default_language() {
    if ( ! self::is_multilanguage() ) return false;

    $default_language = '';
    switch ( self::$is_multilanguage ) {
      case 'WPML':
        $default_language = \apply_filters( 'wpml_default_language', NULL );

        break;
      case 'Polylang':
        if ( \function_exists( '\pll_default_language' ) )
          $default_language = \pll_default_language( 'slug' );

        break;
    }

    return $default_language;
  }

  static public function get_banner_language( $post_id ) {
    if ( ! self::is_multilanguage() ) return false;

    $lang_code = false;
    switch ( self::$is_multilanguage ) {
      case 'WPML':
        $lang_details = \apply_filters( 'wpml_post_language_details', null, $post_id ) ;
        if ( !\is_array( $lang_details ) ) return false;
        return $lang_details['language_code'];

        break;        
      case 'Polylang':
        if ( \function_exists( '\pll_get_post_language' ) ) {
          $lang_detail = \pll_get_post_language( $post_id, 'slug' );
          if ( !$lang_detail ) return false;

          return $lang_detail;
        }

        break;
    }

    return false;
  }

  static public function set_banner_language( $post_id, $lang ) {
    if ( ! self::is_multilanguage() ) return false;

    switch ( self::$is_multilanguage ) {
      case 'WPML':
        $original_post_language_info = \apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $post_id, 'element_type' => Options::BannerPostType ) );
        $set_language_args = array(
          'element_id'             => $post_id,
          'element_type'           => \apply_filters( 'wpml_element_type', Options::BannerPostType ),
          'trid'                   => $original_post_language_info->trid,
          'language_code'          => $lang
        );
        do_action( 'wpml_set_element_language_details', $set_language_args );

        break;        
      case 'Polylang':
        if ( \function_exists( '\pll_set_post_language' ) )
          \pll_set_post_language( $post_id, $lang );

        break;
    }
  }  
}
