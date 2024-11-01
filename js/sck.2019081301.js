;/*
** Nicola Modugno, https://it.linkedin.com/in/modugnonicola
*/

var NMOD_SCK_Helper = new function() {
  'use strict';

  var self = this;

  window.dataLayer   = window.dataLayer || []; // Compatibilità con Google Tag Manager
  this.first_cookie_ref;
  this.actual_cookie = {};
  this.unavailableElementsClass = 'BlockedForCookiePreferences';
  this.blockedElementsClass     = 'BlockedBySmartCookieKit';
  this.consent_on_scroll_enabled   = 0;
  this.consent_on_scroll_triggered = false;

  // Gestione del visitatore che deve ancora dare il consenso
  this.bannerHandler = function( banner_to_open, banner_to_close ) {
    banner_to_open  = banner_to_open  || '';
    banner_to_close = banner_to_close || '';

    var banner;

    if ( '' != banner_to_close ) {
      self.showLog( 'Closing ' + banner_to_close + ' cookie banner...' );

      banner = document.querySelector( '#' + banner_to_close );
      if ( banner ) {
        if ( banner.classList ) {
          banner.classList.remove( 'visible' );
        } else {
          banner.className = banner.className.replace( 'visible', '' );
        }

        switch( banner_to_close ) {
          case 'SCK_MaximizedBanner':
            if ( 1 == self.enable_consent_on_scroll ) {              
              self.showLog( 'Unbinding ACCEPT by SCROLL on maximized banner...' );

              self.removeEvent( window, 'scroll', self.manageAcceptanceOnScroll );
              self.enable_consent_on_scroll = 0;
            }

            break;
        }        
      } else {
        self.showLog( banner_to_close + ' banner not found.' );
      }
    }

    if ( '' != banner_to_open ) {
      self.showLog( 'Opening ' + banner_to_open + ' cookie banner...' );
      banner = document.querySelector( '#' + banner_to_open );
      if ( banner ) {

        switch( banner_to_open ) {
          case 'SCK_MaximizedBanner':
            self.enable_consent_on_scroll = NMOD_SCK_Options.acceptPolicyOnScroll;

            var btn_yes = banner.querySelector( '#SCK_MaximizedBanner .SCK_Accept' );
            var btn_no  = banner.querySelector( '#SCK_MaximizedBanner .SCK_Close'  );
            btn_yes.innerHTML = btn_yes.getAttribute( 'data-textaccept'   );
            btn_no.innerHTML  = btn_no.getAttribute( 'data-textdisabled'  );
            if ( 'undefined' != typeof self.actual_cookie.settings ) {
              self.enable_consent_on_scroll = 0;
              if ( self.actual_cookie.settings.statistics && self.actual_cookie.settings.profiling ) {
                btn_yes.innerHTML = btn_yes.getAttribute( 'data-textaccepted' );
                btn_no.innerHTML  = btn_no.getAttribute( 'data-textdisable'   );
              }
            }

            if ( 1 == self.enable_consent_on_scroll ) {
              self.showLog( 'Binding ACCEPT by SCROLL on maximized banner...' );

              self.consent_on_scroll_triggered = false;
              self.addEvent( window, 'scroll', self.manageAcceptanceOnScroll );
            }

            break;
        }

        if ( banner.classList ) {
          banner.classList.add( 'visible' );
        } else {
          if ( '' != banner.className ) banner.className += ' ';
          banner.className += 'visible';
        }
      } else {
        self.showLog( banner_to_open + ' banner not found.' );
      }
    }
  }

  // Salvataggio del consenso con cookie tecnico 'acceptedCookieName'
  this.manageSettingsCookie = function( user_action, accepted_technical, accepted_statistics, accepted_profiling ) {
    self.showLog( 'Cookie settings updated by ' + user_action + '.' );

    var timestamp = Date.now();
    if ( 'undefined' != typeof self.actual_cookie.settings ) {
      if (
        self.actual_cookie.settings.technical == accepted_technical &&
        self.actual_cookie.settings.statistics == accepted_statistics &&
        self.actual_cookie.settings.profiling == accepted_profiling
      ) {
        self.showLog( 'Nothing to save, same preferences here!' );
        return false;
      }
    }
    if ( 1 == NMOD_SCK_Options.saveLogToServer ) {
      self.showLog( 'Saving a log on the server...' );

      var http = new XMLHttpRequest();
      http.open( 'POST', NMOD_SCK_Options.remoteEndpoint, true );
      http.setRequestHeader( 'Content-type', 'application/x-www-form-urlencoded; charset=UTF-8' );
/*
      http.onreadystatechange = function() {//Call a function when the state changes.
        if ( http.readyState == 4 && http.status == 200 ) {
          alert( http.responseText );
        }
      }
*/
      http.send(
        'action=nmod_sck_privacy_updated'
        + '&update_type=' + user_action
        + '&ref=' + timestamp
        + '&first_ref=' + self.actual_cookie.first_ref
        + '&settings[technical]='  + ( accepted_technical  ? '1' : '0' )
        + '&settings[statistics]=' + ( accepted_statistics ? '1' : '0' )
        + '&settings[profiling]='  + ( accepted_profiling  ? '1' : '0' )
      );
    }

    var settings = {
      'technical'   : accepted_technical,
      'statistics'  : accepted_statistics,
      'profiling'   : accepted_profiling
    };

    self.showLog( 'Saving preference cookie on the client...' );
    self.setCookie(
      NMOD_SCK_Options.acceptedCookieName, {
        settings: settings,
        ref: timestamp.toString(),
        first_ref: self.actual_cookie.first_ref,
        ver: '2.0.0',
      }, NMOD_SCK_Options.acceptedCookieLife
    ); //salvataggio del cookie sul browser dell'utente

    if ( window.dataLayer ) {
      self.showLog( 'Firing standard custom event "cookiePreferencesUpdated"...' );
      window.dispatchEvent( new CustomEvent( 'cookiePreferencesUpdated', settings ) );

      self.showLog( 'Firing GTM custom event "cookiePreferencesUpdated" (this does not mean that GTM is running!).' );
      window.dataLayer.push( { 'event': 'cookiePreferencesUpdated', 'settings' : settings } );
    }

    self.optedIn( settings, false );

    return true;
  }

  // Sblocca tutti gli elementi bloccati per l'utente che ha
  // accettato esplicitamente i cookie (ha cioè fatto "opt-in")
  this.optedIn = function( settings, page_load ) {
    if ( 'undefined' == typeof settings.statistics )
      settings.statistics = false;
    if ( 'undefined' == typeof settings.profiling )
      settings.profiling = false;


    if ( window.dataLayer ) {
      if ( self.actual_cookie.settings.statistics != settings.accepted_statistics ) {
        if ( settings.statistics ) {
          self.showLog( 'Firing standard custom event "statisticsCookiesEnabled"...' );
          window.dispatchEvent( new CustomEvent( 'statisticsCookiesEnabled' ) );

          self.showLog( 'Firing GTM custom event "statisticsCookiesEnabled" (this does not mean that GTM is running!)...' );
          window.dataLayer.push( { 'event': 'statisticsCookiesEnabled' } );
        } else {
          self.showLog( 'Firing standard custom event "statisticsCookiesDisabled"...' );
          window.dispatchEvent( new CustomEvent( 'statisticsCookiesDisabled' ) );

          self.showLog( 'Firing GTM custom event "statisticsCookiesDisabled" (this does not mean that GTM is running!).' );
          window.dataLayer.push( { 'event': 'statisticsCookiesDisabled' } );
        }
      }
      if ( self.actual_cookie.settings.statistics != settings.accepted_profiling ) {
        if ( settings.profiling ) {
          self.showLog( 'Firing standard custom event "profilingCookiesEnabled"...' );
          window.dispatchEvent( new CustomEvent( 'profilingCookiesEnabled' ) );

          self.showLog( 'Firing GTM custom event "profilingCookiesEnabled" (this does not mean that GTM is running!).' );
          window.dataLayer.push( { 'event': 'profilingCookiesEnabled' } );
        } else {
          self.showLog( 'Firing standard custom event "profilingCookiesDisabled"...' );
          window.dispatchEvent( new CustomEvent( 'profilingCookiesDisabled' ) );

          self.showLog( 'Firing GTM custom event "profilingCookiesDisabled" (this does not mean that GTM is running!).' );
          window.dataLayer.push( { 'event': 'profilingCookiesDisabled' } );
        }
      }
    }

    self.showLog( 'Checking blocked resources for current settings...' );

    var res = document.querySelectorAll( '.' + self.blockedElementsClass );
    for ( var res_index = 0; res_index < res.length; res_index++ ) {

      self.showLog( 'Printing blocked item...', res[res_index] );
      switch( res[res_index].getAttribute( 'data-sck_type' ) ) {
        case '1': // Sblocca gli script in embed bloccati con 'type=text/blocked'
          self.showLog( 'Checking to unlock: ' + res[res_index].getAttribute( 'data-sck_ref' ) );
          if ( self.canUnlock( res[res_index].getAttribute( 'data-sck_unlock' ), settings ) ) {
            var script = document.createElement( 'script' );
            
            script.appendChild( document.createTextNode( res[res_index].innerHTML ) );
            document.body.appendChild( script );

            if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
              if ( res[res_index].getAttribute( 'data-sck_index' ) ) {
                var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) );
                for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                  items_to_remove[item_index].remove();
                }

                var tag_list = document.querySelectorAll( '.' + self.blockedElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) );
                for ( var tag_index = tag_list.length - 1; tag_index >= 0; tag_index-- ) {
                  tag_list[tag_index].removeAttribute( 'data-sck_type' );
                  if ( tag_list[tag_index].classList ) {
                    tag_list[tag_index].classList.remove( self.blockedElementsClass );
                    tag_list[tag_index].classList.remove( self.blockedElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) );
                  } else {
                    tag_list[tag_index].className = tag_list[tag_index].className.replace( '/\b' + self.blockedElementsClass + '|' + self.blockedElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) + '\b/g', '' ); // For IE9 and earlier              
                  }
                }
              }
            }

            res[res_index].remove();
          } else {
            self.showLog( 'Item does not meet requirements.' );
          }

          break;
        case '2': // Sblocca gli script esterni bloccati con 'data-blocked'
          self.showLog( 'Checking to unlock: ' + res[res_index].getAttribute( 'data-sck_ref' ) );
          if ( self.canUnlock( res[res_index].getAttribute( 'data-sck_unlock' ), settings ) ) {
            self.showLog( 'Loading: ' + res[res_index].getAttribute( 'data-blocked' ) );

            var script   = document.createElement( 'script' );
            script.src   = res[res_index].getAttribute( 'data-blocked' );
            script.async = false;
            script.defer = false;

            res[res_index].removeAttribute( 'data-blocked' );
            res[res_index].removeAttribute( 'data-sck_ref' );
            res[res_index].removeAttribute( 'data-sck_type' );
            res[res_index].removeAttribute( 'src' );
            res[res_index].removeAttribute( 'async' );
            res[res_index].removeAttribute( 'defer' );

            for ( var att_index = res[res_index].attributes.length - 1; att_index >= 0; att_index-- )
              script.setAttribute( res[res_index].attributes[att_index].name, res[res_index].attributes[att_index].value );

            if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
              if ( res[res_index].getAttribute( 'data-sck_index' ) ) {
                var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) );
                for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                  items_to_remove[item_index].remove();
                }

                var tag_list = document.querySelectorAll( '.' + self.blockedElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) );
                for ( var tag_index = tag_list.length - 1; tag_index >= 0; tag_index-- ) {
                  tag_list[tag_index].removeAttribute( 'data-sck_type' );
                  if ( tag_list[tag_index].classList ) {
                    tag_list[tag_index].classList.remove( self.blockedElementsClass );
                    tag_list[tag_index].classList.remove( self.blockedElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) );
                  } else {
                    tag_list[tag_index].className = tag_list[tag_index].className.replace( '/\b' + self.blockedElementsClass + '|' + self.blockedElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) + '\b/g', '' ); // For IE9 and earlier              
                  }
                }
              }
            }

            res[res_index].remove();

            document.body.appendChild( script );
          } else {
            self.showLog( 'Item does not meet requirements.' );
          }        
          break;
        case '3': // Sblocca le immagini bloccate con 'data-blocked'
          self.showLog( 'Checking to unlock: ' + res[res_index].getAttribute( 'data-sck_ref' ) );
          if ( self.canUnlock( res[res_index].getAttribute( 'data-sck_unlock' ), settings ) ) {
            self.showLog( 'Loading: ' + res[res_index].getAttribute( 'data-blocked' ) );

            res[res_index].setAttribute( 'src', res[res_index].getAttribute( 'data-blocked' ) );
            res[res_index].removeAttribute( 'data-blocked' );

            if ( res[res_index].classList )
              res[res_index].classList.remove( self.blockedElementsClass );
            else
              res[res_index].className = res[res_index].className.replace( '/\b' + self.blockedElementsClass + '\b/g', '' ); // For IE9 and earlier

            if ( 1 == NMOD_SCK_Options.managePlaceholders )
              if ( res[res_index].getAttribute( 'data-sck_index' ) )
                document.querySelector( '.' + self.unavailableElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) ).remove();
          } else {
            self.showLog( 'Item does not meet requirements.' );
          }
          break;
        case '4': // Sblocca gli iframes bloccati con 'data-blocked'
          self.showLog( 'Checking to unlock: ' + res[res_index].getAttribute( 'data-sck_ref' ) );
          if ( self.canUnlock( res[res_index].getAttribute( 'data-sck_unlock' ), settings ) ) {
            self.showLog( 'Loading: ' + res[res_index].getAttribute( 'data-blocked' ) );

            res[res_index].setAttribute( 'src', res[res_index].getAttribute( 'data-blocked' ) );
            res[res_index].removeAttribute( 'data-blocked' );

            if ( res[res_index].classList )
              res[res_index].classList.remove( self.blockedElementsClass );
            else
              res[res_index].className = res[res_index].className.replace( '/\b' + self.blockedElementsClass + '\b/g', '' ); // For IE9 and earlier

            if ( 1 == NMOD_SCK_Options.managePlaceholders )
              if ( res[res_index].getAttribute( 'data-sck_index' ) )
                document.querySelector( '.' + self.unavailableElementsClass + '_' + res[res_index].getAttribute( 'data-sck_index' ) ).remove();
          } else {
            self.showLog( 'Item does not meet requirements.' );
          }
          break;
        case '5': // Sblocca le porzioni di codice taggate dall'admin
          self.showLog( 'booohh!' );
          break;
        case '6': // Ignorare elemento
          self.showLog( 'Item to ignore.' );
          break;
        default:
          self.showLog( 'Item not supported!' );
          break;
      }
    }

    // sblocco i commenti manuali fatti dall'utente
//    self.showLog( 'Loading resources blocked by user tag...' );
//    document.querySelectorAll( '_noscript.' + self.blockedElementsClass ).forEach( function( item ) {
/*
      var parent = item.parentNode;

      var new_child = document.createElement( 'template' );
      new_child.innerHTML = item.innerHTML.trim();

      parent.insertBefore( new_child.content.firstChild, item );
      item.remove();
*/
/*
      self.showLog( 'Checking to unlock: ' + item.getAttribute( 'data-sck_ref' ) );
      if ( self.canUnlock( item, settings ) ) {
        self.showLog( 'Loading: ' + item.getAttribute( 'data-blocked' ) );

        item.setAttribute( 'src', item.getAttribute( 'data-blocked' ) );
        item.removeAttribute( 'data-blocked' );
      } else {
        self.showLog( 'Item does not meet requirements.' );
      }
*/
//    } );

    if ( page_load )
      window.onload = self.checkServiceCompatibility;
    else
      setTimeout( self.checkServiceCompatibility, 1200 );

    self.showLog( 'Blocked resources check done.' );
  }

  this.canUnlock = function( conditions, settings ) {
    conditions = conditions.split( ',' );

    if ( 0 == conditions.length )
      return false;

    for ( var i = conditions.length - 1; i >= 0; i-- ) {
      conditions[i] = conditions[i].trim();
      self.showLog( 'Checking ' + conditions[i] + ' condition...' )

      if ( 'undefined' == typeof settings[ conditions[i] ] )
        return false;

      self.showLog( 'Cookie preference for ' + conditions[i] + ' is: ' + settings[ conditions[i] ] )
      if ( true != settings[ conditions[i]  ] )
        return false;
    }

    self.showLog( 'Conditions passed!' );
    return true;
  }

  this.checkServiceCompatibility = function() {
    if ( 'undefined' == typeof self.actual_cookie ) {
      self.showLog( 'It seems that the cookie is not loaded... Could not run compatibility check.' );
      return;
    } else if ( {} == self.actual_cookie ) {
      self.showLog( 'It seems that the cookie is not set... Could not run compatibility check.' );
      return;
    } else if ( 'undefined' == typeof self.actual_cookie.settings ) {
      self.showLog( 'It seems that the cookie settings are not set... Could not run compatibility check.' );
      return;
    }

    if ( 0 != NMOD_SCK_Options.checkCompatibility.length ) {
      var new_compatibility_list = [];

      self.showLog( 'Searching for compatibility tasks to run...' );
      for ( var service_index = 0; service_index < NMOD_SCK_Options.checkCompatibility.length; service_index++ ) {
        var check_passed = false;

        self.showLog( 'Check compatibility for ' + NMOD_SCK_Options.checkCompatibility[service_index].ref + '..' );
        if ( self.canUnlock( NMOD_SCK_Options.checkCompatibility[service_index].unlock, self.actual_cookie.settings ) ) {
          switch( NMOD_SCK_Options.checkCompatibility[service_index].ref ){
            
            case 'Google Maps':
              // Divi builder
              if ( 'function' == typeof window.et_pb_map_init ) {
                self.showLog( 'It seems that this page was created with a Divi builder.' );
                var divi_maps = document.querySelectorAll( '.et_pb_map_container_' + self.blockedElementsClass );

                if ( 0 != divi_maps.length ) {
                  self.showLog( 'Trying to init maps...' );

                  for ( var map_index = 0; map_index < divi_maps.length; map_index++ ) {
                    if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
                      if ( divi_maps[map_index].getAttribute( 'data-sck_index' ) ) {
                        var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + divi_maps[map_index].getAttribute( 'data-sck_index' ) );
                        for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                          items_to_remove[item_index].remove();
                        }
                      }
                    }
                    if ( divi_maps[map_index].classList ) {
                      divi_maps[map_index].classList.remove( 'et_pb_map_container_' + self.blockedElementsClass );
                      divi_maps[map_index].classList.remove( self.blockedElementsClass );
                    } else {
                      divi_maps[map_index].className = divi_maps[map_index].className.replace( '/\b' + 'et_pb_map_container_' + self.blockedElementsClass + '|' + self.blockedElementsClass  + '\b/g', '' ); // For IE9 and earlier
                    }
                    divi_maps[map_index].removeAttribute( 'data-sck_type'   );
                    divi_maps[map_index].removeAttribute( 'data-sck_index'  );
                    divi_maps[map_index].removeAttribute( 'data-sck_ref'    );
                    divi_maps[map_index].removeAttribute( 'data-sck_unlock' );

                    try {
                      window.et_pb_map_init( jQuery( divi_maps[map_index] ) );
                      check_passed = true;
                    } catch( err ) {
                      self.showLog( '', err );
                    }
                  }

                  self.showLog( 'Init done.' );
                } else {
                  self.showLog( 'No maps to init.' );
                }
              }

              // Avia builder (before Enfold 4.4 - version from 4.4 does not need this)
              if ( jQuery.fn.aviaMaps ) {
                self.showLog( 'It seems that this page was created with a Avia builder.' );
                var enfold_maps = document.querySelectorAll( '.avia-google-map-container_' + self.blockedElementsClass );

                if ( 0 != enfold_maps.length ) {
                  self.showLog( 'Trying to init maps...' );

                  for ( var map_index = enfold_maps.length - 1; map_index >= 0; map_index-- ) {
                    if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
                      if ( enfold_maps[map_index].getAttribute( 'data-sck_index' ) ) {
                        var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + enfold_maps[map_index].getAttribute( 'data-sck_index' ) );
                        for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                          items_to_remove[item_index].remove();
                        }
                      }
                    }
                    if ( enfold_maps[map_index].classList ) {
                      enfold_maps[map_index].classList.remove( 'avia-google-map-container_' + self.blockedElementsClass );
                      enfold_maps[map_index].classList.remove( self.blockedElementsClass );
                      enfold_maps[map_index].classList.add( 'avia-google-map-container' );
                    } else {
                      enfold_maps[map_index].className = enfold_maps[map_index].className.replace( '/\b' + '.avia-google-map-container_' + self.blockedElementsClass + '|' + self.blockedElementsClass  + '\b/g', '' ); // For IE9 and earlier
                      enfold_maps[map_index].className += ' ' + 'avia-google-map-container'; // For IE9 and earlier
                    }
                    enfold_maps[map_index].removeAttribute( 'data-sck_type'   );
                    enfold_maps[map_index].removeAttribute( 'data-sck_index'  );
                    enfold_maps[map_index].removeAttribute( 'data-sck_ref'    );
                    enfold_maps[map_index].removeAttribute( 'data-sck_unlock' );
                  }
                  try {
                    jQuery( '.avia-google-map-container', 'body' ).aviaMaps();
                    check_passed = true;
                  } catch( err ) {
                    self.showLog( '', err );
                  }
                  self.showLog( 'Init done.' );
                } else {
                  self.showLog( 'No maps to init.' );
                }
              }

              // Fusion builder
              if ( 0 != document.querySelectorAll( '.fusion-google-map_' + self.blockedElementsClass ).length ) {
                self.showLog( 'It seems that this page was created with a Fusion builder.' );
                var fusion_maps = document.querySelectorAll( '.fusion-google-map_' + self.blockedElementsClass );

                if ( 0 != fusion_maps.length ) {
                  self.showLog( 'Trying to init maps...' );

                  for ( var map_index = 0; map_index < fusion_maps.length; map_index++ ) {
                    if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
                      if ( fusion_maps[map_index].getAttribute( 'data-sck_index' ) ) {
                        var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + fusion_maps[map_index].getAttribute( 'data-sck_index' ) );
                        for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                          items_to_remove[item_index].remove();
                        }
                      }
                    }

                    if ( fusion_maps[map_index].classList ) {
                      fusion_maps[map_index].classList.remove( 'fusion-google-map_' + self.blockedElementsClass );
                      fusion_maps[map_index].classList.remove( self.blockedElementsClass );
                    } else {
                      fusion_maps[map_index].className = fusion_maps[map_index].className.replace( '/\b' + 'fusion-google-map_' + self.blockedElementsClass + '|' + self.blockedElementsClass  + '\b/g', '' ); // For IE9 and earlier
                    }
                    fusion_maps[map_index].removeAttribute( 'data-sck_type'   );
                    fusion_maps[map_index].removeAttribute( 'data-sck_index'  );
                    fusion_maps[map_index].removeAttribute( 'data-sck_ref'    );
                    fusion_maps[map_index].removeAttribute( 'data-sck_unlock' );

                    try {
                      var script_parent = fusion_maps[map_index].parentNode;
                      var script_container = script_parent.querySelector( 'script' );
                      script_container.innerHTML = script_container.innerHTML.replace( /google\.maps\.event\.addDomListener\( ?window, ?'load', ?(.*?) ?\)\;/g, '$1();' );

                      var script = document.createElement( 'script' );
                      script.appendChild( document.createTextNode( script_container.innerHTML ) );
                      document.body.appendChild( script );
                      script_container.remove();

                      check_passed = true;
                    } catch( err ) {
                      self.showLog( '', err );
                    }
                  }

                  self.showLog( 'Init done.' );
                } else {
                  self.showLog( 'No maps to init.' );
                }
              }

              // WPBackery (Visual Composer) builder
              if ( 'object' == typeof MK ) {
                if ( 'object' == typeof MK.core ) {
                  if ( 'function' == typeof MK.core.initAll ) {
                    self.showLog( 'It seems that this page was created with a WPBackery (Visual Composer) builder.' );
                    var wpbackery_maps = document.querySelectorAll( '.mk-advanced-gmaps.js-el_' + self.blockedElementsClass );

                    if ( 0 != wpbackery_maps.length ) {
                      self.showLog( 'Trying to init maps...' );

                      for ( var map_index = 0; map_index < wpbackery_maps.length; map_index++ ) {
                        if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
                          if ( wpbackery_maps[map_index].getAttribute( 'data-sck_index' ) ) {
                            var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + wpbackery_maps[map_index].getAttribute( 'data-sck_index' ) );
                            for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                              items_to_remove[item_index].remove();
                            }
                          }
                        }

                        if ( wpbackery_maps[map_index].classList ) {
                          wpbackery_maps[map_index].classList.remove( 'js-el_' + self.blockedElementsClass );
                          wpbackery_maps[map_index].classList.remove( self.blockedElementsClass );
                          wpbackery_maps[map_index].classList.add( 'js-el' );
                        } else {
                          wpbackery_maps[map_index].className = wpbackery_maps[map_index].className.replace( '/\b' + 'js-el_' + self.blockedElementsClass + '|' + self.blockedElementsClass  + '\b/g', '' ); // For IE9 and earlier
                          wpbackery_maps[map_index].className += ' ' + 'js-el'; // For IE9 and earlier
                        }
                        wpbackery_maps[map_index].removeAttribute( 'data-sck_type'   );
                        wpbackery_maps[map_index].removeAttribute( 'data-sck_index'  );
                        wpbackery_maps[map_index].removeAttribute( 'data-sck_ref'    );
                        wpbackery_maps[map_index].removeAttribute( 'data-sck_unlock' );

                        try {
                          MK.core.initAll( wpbackery_maps[map_index].parentNode );
                          check_passed = true;
                        } catch( err ) {
                          self.showLog( '', err );
                        }
                      }

                      self.showLog( 'Init done.' );
                    } else {
                      self.showLog( 'No maps to init.' );
                    }
                  }
                }
              }

              // Cornerstone builder
              if ( 'object' == typeof xData ) {
                if ( 'object' == typeof xData.base ) {
                  if ( 'function' == typeof xData.base.processElements ) {
                    self.showLog( 'It seems that this page was created with a Cornerstone builder.' );
                    var cornerstone_maps = document.querySelectorAll( '.x-google-map_' + self.blockedElementsClass );

                    if ( 0 != cornerstone_maps.length ) {
                      self.showLog( 'Trying to init maps...' );

                      for ( var map_index = 0; map_index < cornerstone_maps.length; map_index++ ) {
                        if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
                          if ( cornerstone_maps[map_index].getAttribute( 'data-sck_index' ) ) {
                            var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + cornerstone_maps[map_index].getAttribute( 'data-sck_index' ) );
                            for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                              items_to_remove[item_index].remove();
                            }
                          }
                        }

                        if ( cornerstone_maps[map_index].classList ) {
                          cornerstone_maps[map_index].classList.remove( 'x-google-map_' + self.blockedElementsClass );
                          cornerstone_maps[map_index].classList.remove( self.blockedElementsClass );
                        } else {
                          cornerstone_maps[map_index].className = cornerstone_maps[map_index].className.replace( '/\b' + 'x-google-map_' + self.blockedElementsClass + '|' + self.blockedElementsClass  + '\b/g', '' ); // For IE9 and earlier
                        }
                        cornerstone_maps[map_index].removeAttribute( 'data-sck_type'   );
                        cornerstone_maps[map_index].removeAttribute( 'data-sck_index'  );
                        cornerstone_maps[map_index].removeAttribute( 'data-sck_ref'    );
                        cornerstone_maps[map_index].removeAttribute( 'data-sck_unlock' );

                        cornerstone_maps[map_index].outerHTML = cornerstone_maps[map_index].outerHTML.replace( 'data-x-element_' + self.blockedElementsClass.toLowerCase(), 'data-x-element' );
                      }
                      try {
                        xData.base.processElements();
                        check_passed = true;
                      } catch( err ) {
                        self.showLog( '', err );
                      }

                      self.showLog( 'Init done.' );
                    } else {
                      self.showLog( 'No maps to init.' );
                    }
                  }
                }
              }

              // Google Map standard embed init script
              if ( 0 != document.querySelectorAll( '.initMap_' + self.blockedElementsClass ).length ) {
                self.showLog( 'It seems that this page contains scripts that init maps.' );
                var standard_map_scripts = document.querySelectorAll( '.initMap_' + self.blockedElementsClass );

                if ( 0 != standard_map_scripts.length ) {
                  self.showLog( 'Trying to run init scripts...' );

                  for ( var map_index = 0; map_index < standard_map_scripts.length; map_index++ ) {
                    if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
                      if ( standard_map_scripts[map_index].getAttribute( 'data-sck_index' ) ) {
                        var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + standard_map_scripts[map_index].getAttribute( 'data-sck_index' ) );
                        for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                          items_to_remove[item_index].remove();
                        }
                      }
                    }

                    if ( standard_map_scripts[map_index].classList ) {
                      standard_map_scripts[map_index].classList.remove( 'initMap_' + self.blockedElementsClass );
                      standard_map_scripts[map_index].classList.remove( self.blockedElementsClass );
                    } else {
                      standard_map_scripts[map_index].className = standard_map_scripts[map_index].className.replace( '/\b' + 'initMap_' + self.blockedElementsClass + '|' + self.blockedElementsClass  + '\b/g', '' ); // For IE9 and earlier
                    }
                    standard_map_scripts[map_index].removeAttribute( 'data-sck_type'   );
                    standard_map_scripts[map_index].removeAttribute( 'data-sck_index'  );
                    standard_map_scripts[map_index].removeAttribute( 'data-sck_ref'    );
                    standard_map_scripts[map_index].removeAttribute( 'data-sck_unlock' );

                    try {
                      var script_content = standard_map_scripts[map_index].innerHTML;
                      script_content = script_content.replace( /google\.maps\.event\.addDomListener\( ?window, ?'load', ?(.*?) ?\)\;/g, '$1();' );
                      script_content = script_content.replace( /window\.onload ?\= ?(.*?)\(\)\;/g, '$1();' );

                      var script = document.createElement( 'script' );
                      script.appendChild( document.createTextNode( script_content ) );
                      document.body.appendChild( script );
                      //script_container.remove();
                      standard_map_scripts[map_index].remove();

                      check_passed = true;
                    } catch( err ) {
                      self.showLog( '', err );
                    }
                  }

                  self.showLog( 'Init done.' );
                } else {
                  self.showLog( 'No maps to init.' );
                }
              }

              // Bridge theme
              if ( 'function' == typeof showGoogleMap ) {
                self.showLog( 'It seems that the theme used on this site is Bridge.' );
                var bridge_maps = document.querySelectorAll( '.google_map_shortcode_holder_' + self.blockedElementsClass );

                if ( 0 != bridge_maps.length ) {
                  self.showLog( 'Trying to init maps...' );

                  for ( var map_index = bridge_maps.length - 1; map_index >= 0; map_index-- ) {
                    if ( 1 == NMOD_SCK_Options.managePlaceholders ) {
                      if ( bridge_maps[map_index].getAttribute( 'data-sck_index' ) ) {
                        var items_to_remove = document.querySelectorAll( '.' + self.unavailableElementsClass + '_' + bridge_maps[map_index].getAttribute( 'data-sck_index' ) );
                        for ( var item_index = items_to_remove.length - 1; item_index >= 0; item_index-- ) {
                          items_to_remove[item_index].remove();
                        }
                      }
                    }
                    if ( bridge_maps[map_index].classList ) {
                      bridge_maps[map_index].classList.remove( 'google_map_shortcode_holder_' + self.blockedElementsClass );
                      bridge_maps[map_index].classList.remove( self.blockedElementsClass );
                      bridge_maps[map_index].classList.add( 'google_map_shortcode_holder' );
                    } else {
                      bridge_maps[map_index].className = bridge_maps[map_index].className.replace( '/\b' + '.google_map_shortcode_holder_' + self.blockedElementsClass + '|' + self.blockedElementsClass  + '\b/g', '' ); // For IE9 and earlier
                      bridge_maps[map_index].className += ' ' + 'google_map_shortcode_holder'; // For IE9 and earlier
                    }
                    bridge_maps[map_index].removeAttribute( 'data-sck_type'   );
                    bridge_maps[map_index].removeAttribute( 'data-sck_index'  );
                    bridge_maps[map_index].removeAttribute( 'data-sck_ref'    );
                    bridge_maps[map_index].removeAttribute( 'data-sck_unlock' );
                  }
                  try {
                    showGoogleMap();
                    check_passed = true;
                  } catch( err ) {
                    self.showLog( '', err );
                  }
                  self.showLog( 'Init done.' );
                } else {
                  self.showLog( 'No maps to init.' );
                }
              }

              self.showLog( 'Google Maps check done.' );
              break;
            case 'Google Tag Manager by DuracellTomi':

              var duracelltomi_gtm = document.querySelector( 'script.' + self.blockedElementsClass );
              if ( duracelltomi_gtm ) {
                self.showLog( 'This page contains DuracellTomi GTM init script.' );

                try {
                  var script = document.createElement( 'script' );
                  script.appendChild( document.createTextNode( duracelltomi_gtm.innerHTML ) );
                  document.body.appendChild( script );
                  duracelltomi_gtm.remove();

                  check_passed = true;
                } catch( err ) {
                  self.showLog( '', err );
                }

                self.showLog( 'Init done.' );
              }

              self.showLog( 'Google Tag Manager by DuracellTomi check done.' );
              break;
          }
        }

        if ( !check_passed )
          new_compatibility_list.push( NMOD_SCK_Options.checkCompatibility[service_index] );
      }

      self.showLog( 'Compatibility check done.' );
      NMOD_SCK_Options.checkCompatibility = new_compatibility_list;
    }
  }

  this.addEvent = function( element, eventName, fn ) {
    if ( ! element ) return;

    if ( element.addEventListener )
      element.addEventListener( eventName, fn, false );
    else if ( element.attachEvent )
      element.attachEvent( 'on' + eventName, fn );
  }

  this.removeEvent = function( element, eventName, fn ) {
    if ( ! element ) return;

    if ( element.removeEventListener )
      element.removeEventListener( eventName, fn );
    else if ( element.attachEvent )
      element.detachEvent( 'on' + eventName, fn );
  }

  this.getPolicyCookie = function( cookie_name ) {
    cookie_name = cookie_name || NMOD_SCK_Options.acceptedCookieName;

    var all = document.cookie; // Get all cookies in one big string

    if ( '' !== all ) {
      var list = all.split( '; ' );
      for ( var i = 0; i < list.length; i++ ) {
        var cookie    = list[i];
        var p         = cookie.indexOf( '=' );
        var name      = cookie.substring( 0, p );

        if ( cookie_name == name ) {
          var value     = cookie.substring( p + 1 );
          value         = decodeURIComponent( value );
          return JSON.parse( value );
        }
      }
    }
    return {};
  }

  // elimina un cookie selezionato per nome
  this.delPolicyCookie = function( cookie_name ) {
    cookie_name = cookie_name || NMOD_SCK_Options.acceptedCookieName
    self.setCookie( cookie_name, '', -1 );
  }

  // imposta un cookie con 'name', 'value' e giorni di durata
  this.setCookie = function( name, value, days ){
    var now         = new Date();
    var expiration  = new Date( now.getTime() + parseInt( days ) * 24 * 60 * 60 * 1000 );
  //  var cString     = name + '=' + escape( value ) + '; expires=' + expiration.toGMTString() + '; path=/';
    var cString     = name + '=' + JSON.stringify( value ) + '; expires=' + expiration.toGMTString() + '; path=/';
    document.cookie = cString;

    self.actual_cookie = self.getPolicyCookie();
  }

  // effettua il log di un oggetto se l'app è in modalità debug
  this.showLog = function( message, object ) {
    message = message || '';
    object  = object  || false;
    if ( 1 == NMOD_SCK_Options.debugMode ) {
      if ( message ) console.log( 'Smart Cookie Kit - ' + message );
      if ( object  ) console.log( object );
    }
  }

  this.manageAcceptanceOnScroll = function() {
    if ( false == self.consent_on_scroll_triggered ) {
      if ( 200 < ( ( window.pageYOffset || document.documentElement.scrollTop ) - ( document.documentElement.clientTop || 0 ) ) ) {
        self.consent_on_scroll_triggered = true;
        self.manageSettingsCookie( 'SCROLL', true, true, true );
        self.bannerHandler( 'SCK_MinimizedBanner', 'SCK_MaximizedBanner' );
      }
    }
  }

  this.init = function() {
    self.showLog( 'Debug mode enabled!' );
    self.showLog( '', NMOD_SCK_Options );

    self.showLog( 'Running Smart Cookie Kit...' );

    self.showLog( 'Searching for tags to manage...' );
    if ( 0 != NMOD_SCK_Options.searchTags.length && 1 == NMOD_SCK_Options.managePlaceholders ) {
      var template = document.querySelector( '#SCK_Placeholder' ).innerHTML;
      for ( var service_index = NMOD_SCK_Options.searchTags.length - 1; service_index >= 0; service_index-- ) {
        for ( var tag_index = NMOD_SCK_Options.searchTags[service_index].tags.length - 1; tag_index >= 0; tag_index-- ) {
          var items = document.querySelectorAll( NMOD_SCK_Options.searchTags[service_index].tags[tag_index] );
          if ( 0 != items.length ) {
            for ( var item_index = items.length - 1; item_index >= 0; item_index-- ) {
              var placeholder_container = document.createElement( 'template' );
              placeholder_container.innerHTML = template.replace( '%REF_NUM%', NMOD_SCK_Options.searchTags[service_index].ref ).replace( '%SERVICE_NAME%', NMOD_SCK_Options.searchTags[service_index].name );
              var placeholder = placeholder_container.content.firstChild;

              var parent = items[item_index].parentNode;
              parent.insertBefore( placeholder, items[item_index] );

              items[item_index].setAttribute( 'data-sck_type', 6 );
              if ( items[item_index].classList )
                items[item_index].classList.add( self.blockedElementsClass, self.blockedElementsClass + '_' + NMOD_SCK_Options.searchTags[service_index].ref );
              else
                items[item_index].className += ' ' + self.blockedElementsClass + ' ' + self.blockedElementsClass + '_' + NMOD_SCK_Options.searchTags[service_index].ref; // For IE9 and earlier
            };
          }
        }
      }
    }

    self.showLog( 'Binding ACCEPT BUTTON on maximized banner...' );
    self.addEvent( document.querySelector( '#SCK_MaximizedBanner .SCK_Accept' ), 'click',  function( e ) {
      e.preventDefault();

      self.manageSettingsCookie( 'ACCEPT BUTTON', true, true, true );

      self.bannerHandler( 'SCK_MinimizedBanner', 'SCK_MaximizedBanner' );
    } );

    self.showLog( 'Binding CANCEL BUTTON on maximized banner...' );
    self.addEvent( document.querySelector( '#SCK_MaximizedBanner .SCK_Close' ), 'click',  function( e ) {
      e.preventDefault();

      var something_changed = self.manageSettingsCookie( 'Cancel BUTTON', true, false, false );
      self.bannerHandler( 'SCK_MinimizedBanner', 'SCK_MaximizedBanner' );

      if ( something_changed && 1 == NMOD_SCK_Options.reloadPageWhenUserDisablesCookies ) {
        self.showLog( 'Reloading page in 1 second...' );
        setTimeout( function() {
          location.reload();
        }, 1000 );
      }
    } );

    self.showLog( 'Binding OPEN BUTTON on minimized banner (if necessary)...' );
    self.addEvent( document.querySelector( '#SCK_MinimizedBanner .SCK_Open' ), 'click',  function( e ) {
      e.preventDefault();

      self.bannerHandler( 'SCK_MaximizedBanner', 'SCK_MinimizedBanner' );
    } );

    self.showLog( 'Binding OPEN LINK on placeholders...' );
    var banner_links = document.querySelectorAll( '.OpenCookiePreferences' );
    for ( var banner_index = banner_links.length - 1; banner_index >= 0; banner_index-- ) {      
      self.addEvent( banner_links[banner_index], 'click',  function( e ) {
        e.preventDefault();

        self.bannerHandler( 'SCK_MaximizedBanner', 'SCK_MinimizedBanner' );
      } );      
    };

    self.showLog( 'Searching for saved preferences...' );
    var cookie = self.getPolicyCookie( NMOD_SCK_Options.acceptedCookieName_v1 );
    if ( true === cookie ) {
      self.showLog( 'Old policy cookie found (v.1.0). Deleting it..' );
      self.delPolicyCookie( NMOD_SCK_Options.acceptedCookieName_v1 );
    } else if ( 'undefined' != typeof cookie.accepted ) {
      self.showLog( 'Old policy cookie found (v.1.2). Deleting it..' );
      self.delPolicyCookie( NMOD_SCK_Options.acceptedCookieName_v1 );
    }
    cookie = self.getPolicyCookie();

    var banner_type = 'SCK_MaximizedBanner';
    self.actual_cookie.first_ref = Date.now();

    if ( {} != cookie ) {
      if ( 'undefined' != typeof cookie.ver ) {
        switch( cookie.ver ) {
          case '2.0.0':
            var update_date = new Date( Number( cookie.ref ) );
            self.showLog( 'Policy cookie found (v.2.2). Settings last update: ' + update_date.toLocaleDateString() + ' ' + update_date.toLocaleTimeString() );

            //banner_type = cookie.settings.statistics && cookie.settings.profiling ? '' : 'minimized';
            banner_type = 'SCK_MinimizedBanner';
            self.actual_cookie = cookie;
            //self.first_cookie_ref = cookie.first_ref;

            self.optedIn( self.actual_cookie.settings, true );

            break;
        }
      }
    } else {
      self.showLog( 'Policy cookie not found.' );
    }

    if ( 0 == NMOD_SCK_Options.runCookieKit ) {
      banner_type = 'SCK_MinimizedBanner';
      self.showLog( 'Banner disabled on this page.' );
    }

    self.bannerHandler( banner_type );
  }
};

/* IE compatibility */
(function () {

  if ( ! ( 'remove' in Element.prototype ) ) {
    Element.prototype.remove = function() {
      if ( this.parentNode ) {
        this.parentNode.removeChild( this );
      }
    };
  }

  if ( typeof window.CustomEvent !== "function" ) {
    window.CustomEvent = function CustomEvent ( event, params ) {
      params = params || { bubbles: false, cancelable: false, detail: null };
      var evt = document.createEvent( 'CustomEvent' );
      evt.initCustomEvent( event, params.bubbles, params.cancelable, params.detail );
      return evt;
    }
  }

} )();