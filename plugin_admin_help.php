<?php
namespace NMod\SmartCookieKit;

if ( ! \defined( 'ABSPATH' ) ) exit;

\wp_enqueue_script( 'jquery-ui-accordion' );
?>
<h2><?php \esc_html_e( 'Security notices', 'smart-cookie-kit' ) ?></h2>
<p><small><?php \esc_html_e( 'Click on the notice you are interested in to see more details.', 'smart-cookie-kit' ) ?></small></p>
<div class="accordion">
  <div>
<?php
  $notice_color = '';
  $title_append = '';
  $dismissed    = true;

  if ( stristr( strtolower( $_SERVER['SERVER_SOFTWARE'] ), 'nginx' ) ) {

    $admin = Admin::init();
    $dismissed = $admin->is_admin_notice_dismissed( 1 );

    if ( $dismissed ) {
      $title_append = ' (' . \esc_html__( 'dismissed by an admin', 'smart-cookie-kit' ) . ')';
    } else {
      $notice_color = 'warning';
    }
  }
?>
    <h4 class="v-2-2-1"><a href="#" class="<?php echo $notice_color; ?>"><?php \esc_html_e( 'SECURITY RISK for sites running on NGINX', 'smart-cookie-kit' ) . $title_append; ?></a></h4>
    <div class="v-2-2-1">
      <p>
        <?php \esc_html_e( 'Smart Cookie Kit protects the log directory with a ".htaccess" file but NGINX does not support this kind of approach.', 'smart-cookie-kit' ) ?>
        <br /><?php \esc_html_e( 'In order to protect the log directory, your server administrator should add some rules in your vhost configuration file.', 'smart-cookie-kit' ) ?>
      </p>
<pre class="code">
# Example rule to deny access to the cookie preferences log directory

location ~ /wp-content/cookie-preferences-log/(.*) {
  deny all;
  return 403;
}
</pre>
      <?php if ( ! $dismissed && \current_user_can( 'activate_plugins' ) ) { ?>
      <button id="sck-dismiss_notice_nginx" class="button button-primary hidden"><?php \esc_html_e( 'I have checked that the log directory is protected. Dismiss the security notice for ALL admins!', 'smart-cookie-kit' ); ?></button>
      <script type="text/javascript">
        jQuery( function() {
          jQuery( '#sck-dismiss_notice_nginx' ).removeClass( 'hidden' );
          jQuery( '#sck-dismiss_notice_nginx' ).click( function() {
            jQuery.post(
              '<?php echo admin_url('admin-ajax.php') ?>',
              {
                action: 'nmod_sck_dismiss_notice_nginx',
                refchk: '<?php echo wp_create_nonce( 'dl2 e3E23je23 eo23"£H eou e21QE 3ehu223 32r"£ 2' ) ?>'
              },
              function( response ) {
                if ( 1 == response.StatusCode ) {
                  document.location.reload();
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
      <?php } ?>
    </div>
  </div>
</div>

<hr />
<h2><?php \esc_html_e( 'Available shortcodes', 'smart-cookie-kit' ) ?></h2>
<p><small><?php \esc_html_e( 'Click on the shortcode you are interested in to see more details.', 'smart-cookie-kit' ) ?></small></p>
<div class="accordion">
  <div>
    <h4><a href="#"><?php \esc_html_e( 'Link to open the banner for cookie preferences', 'smart-cookie-kit' ) ?></a></h4>
    <div>
      <p><code>[cookie_banner_link text="" class="" style=""]</code></p>
      <p><?php \esc_html_e( 'With this shortcode you get a link that opens the cookie banner. You could use this shortcode to manage cookie preferences with a link in the footer of the site or in the policy page.', 'smart-cookie-kit' ) ?></p>
      <ul>
        <li>- <b>text</b>: <?php \esc_html_e( 'Optional', 'smart-cookie-kit' ) ?>. <?php \printf( \esc_html__( 'With this parameter you can customize the link text. Default value is "%s".', 'smart-cookie-kit' ), \esc_html__( 'Cookie preferences', 'smart-cookie-kit' ) ) ?></li>
        <li>- <b>class</b>: <?php \esc_html_e( 'Optional', 'smart-cookie-kit' ) ?>. <?php \esc_html_e( 'With this parameter you can add CSS classes to graphically customize the link. Default value is empty.', 'smart-cookie-kit' ) ?></li>
        <li>- <b>style</b>: <?php \esc_html_e( 'Optional', 'smart-cookie-kit' ) ?>. <?php \esc_html_e( 'With this parameter you can add inline CSS rules to graphically customize the link. Default value is empty.', 'smart-cookie-kit' ) ?></li>
      </ul>
    </div>
  </div>
</div>

<hr />
<h2><?php \esc_html_e( 'Available integrations', 'smart-cookie-kit' ) ?></h2>
<p><small><?php \esc_html_e( 'Click on the integration you are interested in to see more details.', 'smart-cookie-kit' ) ?></small></p>
<div class="accordion">
  <div>
    <h4 class="v-2-2-1"><a href="#"><?php \esc_html_e( 'Javascript and Google Tag Manager custom events', 'smart-cookie-kit' ) ?></a></h4>
    <div>
      <p><?php \esc_html_e( 'Smart Cookie Kit raises some custom events that helps you to run custom actions when "something happens".', 'smart-cookie-kit' ) ?></p>
      <ul>
        <li>- <b>cookiePreferencesUpdated</b> <?php \esc_html_e( 'is raised when the user change his cookie preferences', 'smart-cookie-kit' ) ?></li>
        <li>- <b>statisticsCookiesEnabled</b> <?php \esc_html_e( 'is raised on every page load when Smart Cookie Kit has checked that the user accepted statistics cookies', 'smart-cookie-kit' ) ?></li>
        <li>- <b>statisticsCookiesDisabled</b> <?php \esc_html_e( 'is raised on every page load when Smart Cookie Kit has checked that the user did NOT accepted statistics cookies', 'smart-cookie-kit' ) ?></li>
        <li>- <b>profilingCookiesEnabled</b> <?php \esc_html_e( 'is raised on every page load when Smart Cookie Kit has checked that the user accepted profiling cookies', 'smart-cookie-kit' ) ?></li>
        <li>- <b>profilingCookiesDisabled</b> <?php \esc_html_e( 'is aised on every page load when Smart Cookie Kit has checked that the user did NOT accepted profiling cookies', 'smart-cookie-kit' ) ?></li>
      </ul>
      <p><?php \esc_html_e( 'To add your handler you can follow these examples.', 'smart-cookie-kit' ) ?></p>
      <div class="v-2-2-1">
        <h5>Javascript</h5>
        <p><?php \esc_html_e( 'Your javascript code might look like the following...', 'smart-cookie-kit' ) ?></p>
<pre class="code">
&lt;script class="<?php echo Options::CookieIgnoreClass; ?>"&gt;
  /* 
  ** Example Javascript code to attach a custom handler to Smart Cookie Kit events
  **
  ** Please, note that the "<?php echo Options::CookieIgnoreClass; ?>" class is needed to avoid that Smart Cookie Kit blocks your code
  */

  customAddEvent = function( element, eventName, fn ) {
    if ( ! element ) return;

    if ( element.addEventListener )
      element.addEventListener( eventName, fn, false );
    else if ( element.attachEvent )
      element.attachEvent( 'on' + eventName, fn );
  }

  customAddEvent( window, 'cookiePreferencesUpdated', function( e ) {
    console.log( 'JS event: cookiePreferencesUpdated. User has updated his preferences.' );
  } );
  customAddEvent( window, 'statisticsCookiesEnabled', function( e ) {
    console.log( 'JS event: statisticsCookiesEnabled. Smart Cookie Kit has checked that user accepted statistics cookies.' );
  } );
  customAddEvent( window, 'statisticsCookiesDisabled', function( e ) {
    console.log( 'JS event: statisticsCookiesDisabled. Smart Cookie Kit has checked that user did NOT accepted statistics cookies.' );
  } );
  customAddEvent( window, 'profilingCookiesEnabled', function( e ) {
    console.log( 'JS event: profilingCookiesEnabled. Smart Cookie Kit has checked that user accepted profiling cookies.' );
  } );
  customAddEvent( window, 'profilingCookiesDisabled', function( e ) {
    console.log( 'JS event: profilingCookiesDisabled. Smart Cookie Kit has checked that user did NOT accepted profiling cookies.' );
  } );
&lt;/script&gt;
</pre>
        <p><?php \esc_html_e( 'Remember that you should register your handlers before Smart Cookie Kit raises those events.', 'smart-cookie-kit' ) ?></p>
      </div>
      <h5>Google Tag Manager</h5>
      <p><?php \esc_html_e( 'A guide for this configuration will be available as soon as possible.', 'smart-cookie-kit' ) ?></p>
    </div>
  </div>
  <div>
    <h4 class="v-2-2-1"><a href="#"><?php \esc_html_e( 'How to instruct Smart Cookie Kit to not block Javascript code/script', 'smart-cookie-kit' ) ?></a></h4>
    <div class="v-2-2-1">
      <p>From version 2.2.1, Smart Cookie Kit ignores the "script" tags with the "class" property that contains "<?php echo Options::CookieIgnoreClass; ?>", as in the following examples:</p>
<pre class="code">
&lt;script class="<?php echo Options::CookieIgnoreClass; ?>"&gt; /* Your custom Javascript code that should not be blocked */ &lt;/script&gt;
&lt;script class="<?php echo Options::CookieIgnoreClass; ?>" src="//your-domain.ext/not-blocked-javascript-file.js"&gt;&lt;/script&gt;
</pre>
    </div>
  </div>

  <div>
    <h4 class="v-2-2-2"><a href="#"><?php \esc_html_e( 'How to instruct Smart Cookie Kit to block a custom or specific Javascript code/script', 'smart-cookie-kit' ) ?></a></h4>
    <div class="v-2-2-2">
      <h5>Blocking with "class" property on "script" tags</h5>
      <p>From version 2.2.2, Smart Cookie Kit blocks "script" tags whose "class" property contains "<?php echo Options::CookieBlockClass_Statistics; ?>" or "<?php echo Options::CookieBlockClass_Profiling; ?>", as in the following examples:</p>
<pre class="code">
&lt;script class="<?php echo Options::CookieBlockClass_Statistics; ?>"&gt; /* Your custom Javascript code that should be blocked */ &lt;/script&gt;
&lt;script class="<?php echo Options::CookieBlockClass_Profiling; ?>" src="//your-domain.ext/blocked-javascript-file.js"&gt;&lt;/script&gt;
&lt;script class="<?php echo Options::CookieBlockClass_Statistics; ?> <?php echo Options::CookieBlockClass_Profiling; ?>" src="//your-domain.ext/blocked-javascript-file.js"&gt;&lt;/script&gt;
</pre>
      <h5>Blocking when registering and enqueuing custom scripts</h5>
      <p>If you are registering and enqueuing a custom script in your plugin or theme, you can block it appending one of the following strings to its source URI: "#<?php echo Options::CookieBlockClass_Statistics; ?>", "#<?php echo Options::CookieBlockClass_Profiling; ?>", "#<?php echo Options::CookieBlockClass_StatsAndProf; ?>". For example:
<pre class="code">
wp_register_script( 'analytics_script', '//www.google-analytics.com/analytics.js#<?php echo Options::CookieBlockClass_Statistics; ?>', array(), null, false );
wp_register_script( 'theme_statistics', get_stylesheet_directory_uri() . '/res/stats.js#<?php echo Options::CookieBlockClass_Profiling; ?>', array( 'jquery' ), null, true  );
wp_register_script( 'theme_statistics', get_stylesheet_directory_uri() . '/res/stats.js#<?php echo Options::CookieBlockClass_StatsAndProf; ?>', array( 'jquery' ), null, true  );
</pre>
      <h5>Blocking adding a list of script to block</h5>
      <p>If you have not control on external scripts or scripts added by third parties plugins/themes, you can extend the list of the scripts blocked by Smart Cookie Kit using the filter "sck_sources_to_block", as in the example below:
<pre class="code">
function my_custom_sources_to_block() {
  return array(
    array( 'service_name' => 'Custom Script 1', 'unlock_with' => 'statistics'          , 'pattern' => 'custom_script_1.js' ),
    array( 'service_name' => 'Custom Script 2', 'unlock_with' => 'profiling'           , 'pattern' => 'custom_script_2.js' ),
    array( 'service_name' => 'Custom Script 3', 'unlock_with' => 'statistics,profiling', 'pattern' => 'custom_script_3.js' ),
  );
}
\add_filter( 'sck_sources_to_block', 'my_custom_sources_to_block', 10 );
</pre>
    </div>
  </div>

  <div>
    <h4 class="v-2-3-0"><a href="#"><?php \esc_html_e( 'How to check if current user accepted cookies', 'smart-cookie-kit' ) ?></a></h4>
    <div class="v-2-3-0">
      <h5>Calling SERVER SIDE functions</h5>
      <p>From version 2.3.0, you can ask to Smart Cookie Kit if current user has accepted cookies, as in the following example:</p>
<pre class="code">
if ( function_exists('NMod\SmartCookieKit\can_unlock_technical_cookies') && NMod\SmartCookieKit\can_unlock_technical_cookies() ) {
  // run technical features
}
if ( function_exists('NMod\SmartCookieKit\can_unlock_statistics_cookies') && NMod\SmartCookieKit\can_unlock_statistics_cookies() ) {
  // run statistics features
}
if ( function_exists('NMod\SmartCookieKit\can_unlock_profiling_cookies') && NMod\SmartCookieKit\can_unlock_profiling_cookies() ) {
  // run profiling features
}
</pre>
    </div>
  </div>

</div>

<hr />
<h2><?php \esc_html_e( 'Frequently asked questions', 'smart-cookie-kit' ) ?></h2>
<p><small><?php \esc_html_e( 'Click on the question you are interested in to see the answer.', 'smart-cookie-kit' ) ?></small></p>
<div class="accordion">
  <div>
    <h4 class="v-2-2-1"><a href="#">Why the color of the text in the banner does not match the CSS rules?</a></h4>
    <div class="v-2-2-1">
      <p>The content of the banner is filtered as a standard page/post content, so WordPress could add paragraph HTML tags to it. Some themes define specific CSS rules for those paragraphs that have a greather priority.</p>
      <p>With version 2.2.1 the option "CSS for banner text" has became "CSS for banner text container", so you can use the new "CSS for banner text" to set font/text related CSS properties.</p>
      <p>Try to move only font/text properties from "CSS for banner text container" option to "CSS for banner text".</p>
      <p>For example...</p>
<pre class="code">
  /* Before */
  "CSS for banner text" => "float:left;width:75%;color:#000;line-height:1.1em;"

  /* With version 2.2.1 */
  "CSS for banner text container" => "float:left;width:75%;color:#000;line-height:1.1em;"

  /* What you have to try */
  "CSS for banner text container" => "float:left;width:75%;"
  "CSS for banner text" => "color:#000;line-height:1.1em;"
</pre>
      <p>If this change does not help, please, open a support ticket on <a href="https://wordpress.org/support/plugin/smart-cookie-kit/" target="_blank" rel="nofollow">https://wordpress.org/support/plugin/smart-cookie-kit/</a></p>
    </div>
  </div>
  <div>
    <h4 class="v-2-2-1"><a href="#">Is the plugin compatible with Gutenberg?</a></h4>
    <div class="v-2-2-1">
      <p>From version 2.2 Smart Cookie Kit is compatible with Gutenberg editor. To be honest: Smart Cookie Kit does not use standard WordPress editor, as it uses some custom fields to manage string/content translation, but this update was necessary to manage accordingly custom fields.</p>
    </div>
  </div>
  <div>
    <h4><a href="#">Is the plugin compatible with WPML and Polylang?</a></h4>
    <div>
      <p>From version 2.1, Smart Cookie Kit detects when a site is published in multiple languages with WPML or Polylang. In that case, it activates an option to insert the content of the banner in a mask similar to that for posts and pages, so it is possible to manage the contents translations following the normal translation procedure of the mentioned plugins.</p>
    </div>
  </div>
  <div>
    <h4><a href="#">JavaScript error "google is not defined"</a></h4>
    <div>
      <p>This error occurs because "something" is trying to init the maps when the Google Map script is not loaded yet (it was blocked by Smart Cookie Kit!).</p>
      <p>Give a read to this thread: <a href="https://wordpress.org/support/topic/uncaught-referenceerror-google-is-not-defined-5/" target="_blank" rel="nofollow">https://wordpress.org/support/topic/uncaught-referenceerror-google-is-not-defined-5/</a></p>
      </div>
  </div>
  <div>
    <h4><a href="#">Parse error: syntax error, unexpected '[' in smart-cookie-kit/plugin_options.php</a></h4>
    <div>
      <p>This problem is caused by the PHP version used on the web server.</p>
      <p>Please, change your web server configuration (or ask to your system administrator) to use a PHP version >= 5.4 (checking if others components raise errors).</p>
      </div>
  </div>
  <div>
    <h4><a href="#">Does the plugin stores visitors policy acceptance?</a></h4>
    <div>
      <p>Yes, optionally. The plugin can save a log into the server every time a visitor updates his preferences.</p>
      <p>The logs are stored and protected from public access in the "/wp-content/cookie-preferences-log/" directory.</p>
      </div>
  </div>
  <div>
    <h4><a href="#">The banner is not responsive / On mobile the banner is not centered</a></h4>
    <div>
      <p>Depending on the theme, the default CSS rules may not be enough to view correctly the banner.</p>
      <p>It may help modify the field "CSS for banner content" adding this rule: "box-sizing:border-box;"</p>
      </div>
  </div>
  <div>
    <h4><a href="#">Is it possible to remove the minimized button when banner is hided?</a></h4>
    <div>
      <p>Yes, optionally. From the version 2.0.4 of Smart Cookie Kit :)</p>
    </div>
  </div>
</div>

<hr />
<h2><?php \esc_html_e( 'You can really support Smart Cookie Kit', 'smart-cookie-kit' ) ?></h2>
<p><small><?php \esc_html_e( 'Click on the action you are interested in to see the answer.', 'smart-cookie-kit' ) ?></small></p>
<div class="accordion">
  <div>
    <h4 class="v-2-2-1"><a href="#">Leave a review</a></h4>
    <div class="v-2-2-1">
      <p>You can leave a review on the official WordPress repository, at the end of this page: <a href="https://wordpress.org/support/plugin/smart-cookie-kit/reviews/" target="_blank" rel="nofollow">https://wordpress.org/support/plugin/smart-cookie-kit/reviews/</a>.</p>
      <p>Thank you! :)</p>
    </div>
  </div>
  <div>
    <h4 class="v-2-2-1"><a href="#">Make a donation</a></h4>
    <div class="v-2-2-1">
      <p>You can make a donation via PayPal, following this link: <a href="https://paypal.me/modugnonicola" target="_blank" rel="nofollow">https://paypal.me/modugnonicola</a> (nothing to be afraid of, this PayPal link is the same as the one in the right sidebar of the official plugin page at: <a href="https://wordpress.org/plugins/smart-cookie-kit/" target="_blank" rel="nofollow">https://wordpress.org/plugins/smart-cookie-kit/</a>).</p>
      <p>Thank you! :)</p>
    </div>
  </div>
</div>

<script type="text/javascript">
  jQuery( function() {
    jQuery( '.accordion' ).accordion( { header: 'h4', collapsible: true, active: 'none' } );

<?php
  $plugin_data = get_plugin_data( __DIR__ . '/plugin.php', false, $translate = false );
  $plugin_version_css = \str_replace( '.', '-', $plugin_data['Version'] );
?>
    jQuery( '.v-<?php echo $plugin_version_css; ?>' ).addClass( 'SCK-ActualVersion' );
  } );
</script>