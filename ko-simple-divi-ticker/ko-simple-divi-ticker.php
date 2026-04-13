<?php
/**
 * Plugin Name: KO Simple Divi Ticker
 * Description: Adds a simple scrolling ticker effect to any Divi module with a specific CSS class...just add the "ko-divi-ticker" class to the text/heading module, and optionally add "ko-divi-ticker-group" to a row/section to merge multiple modules into a single ticker stream.
 * Author: KO
 * Version: 1.7.0
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default options.
 */
function ko_simple_divi_ticker_default_options() {
    return array(
        'speed'               => 30,
        'mobile_speed'        => 45,
        'gap'                 => '3rem',
        'pause_between_loops' => 0,
    );
}

/**
 * Get merged options.
 */
function ko_simple_divi_ticker_get_options() {
    $defaults = ko_simple_divi_ticker_default_options();
    $options  = get_option( 'ko_simple_divi_ticker_options', array() );

    if ( ! is_array( $options ) ) {
        $options = array();
    }

    return array_merge( $defaults, $options );
}

/**
 * Activation: ensure defaults.
 */
function ko_simple_divi_ticker_activate() {
    $defaults = ko_simple_divi_ticker_default_options();
    $existing = get_option( 'ko_simple_divi_ticker_options', array() );

    if ( ! is_array( $existing ) ) {
        $existing = array();
    }

    $merged = array_merge( $defaults, $existing );
    update_option( 'ko_simple_divi_ticker_options', $merged );
}
register_activation_hook( __FILE__, 'ko_simple_divi_ticker_activate' );

/**
 * Front-end styles.
 */

function ko_simple_divi_ticker_styles() {
    $opts = get_option('ko_simple_divi_ticker_options', []);
    $speed = isset($opts['speed']) ? max(1, intval($opts['speed'])) : 30;
    $mobile_speed = isset($opts['mobile_speed']) ? max(1, intval($opts['mobile_speed'])) : 45;
    $gap = isset($opts['gap']) ? esc_attr($opts['gap']) : '40px';
    $pause = ! empty($opts['pause']);
    ?>
    <style id="ko-simple-divi-ticker-styles">
        .ko-divi-ticker{
            position:relative;
            display:block;
            width:100%;
            overflow:hidden;
            /* don't force nowrap on the whole module; we'll enforce on the moving tracks */
            contain: layout paint style;
        }

        /* Hide until JS finishes wrapping/measuring (prevents "stacked then snap") */
        .ko-divi-ticker:not(.ko-ticker-ready){ visibility:hidden; }

        .ko-divi-ticker-inner{
            display:flex;
            flex-wrap:nowrap;
            align-items:center;
            will-change:transform;
            transform:translate3d(0,0,0);
            animation-timing-function:linear;
            animation-iteration-count:infinite;
            animation-duration:<?php echo esc_html($speed); ?>s;
            animation-name:<?php echo $pause ? 'ko-divi-ticker-scroll-paused' : 'ko-divi-ticker-scroll'; ?>;
        }

        @media (max-width: 767px){
            .ko-divi-ticker-inner{
                animation-duration:<?php echo esc_html($mobile_speed); ?>s;
            }
        }

        .ko-divi-ticker-track{
            display:flex;
            flex-wrap:nowrap;
            align-items:center;
            white-space:nowrap;
        }

        .ko-divi-ticker-duplicate{
            padding-left:<?php echo esc_html($gap); ?>;
        }

        .ko-divi-ticker-message{
            display:inline-flex;
            align-items:center;
            white-space:nowrap;
        }

        /* HARD STOP only when Divi actually marks slide menu as open */
        body.et_pb_slide_menu_active .ko-divi-ticker-inner,
        body.et_pb_slide_menu_opened .ko-divi-ticker-inner,
        body.ko-ticker-menu-open .ko-divi-ticker-inner{
            animation:none !important;
            transform:none !important;
        }

        @keyframes ko-divi-ticker-scroll{
            0%{ transform:translate3d(0,0,0); }
            100%{ transform:translate3d(calc(-1 * var(--ko-ticker-distance, 50%)),0,0); }
        }
        @keyframes ko-divi-ticker-scroll-paused{
            0%{ transform:translate3d(0,0,0); }
            80%{ transform:translate3d(calc(-1 * var(--ko-ticker-distance, 50%)),0,0); }
            100%{ transform:translate3d(calc(-1 * var(--ko-ticker-distance, 50%)),0,0); }
        }

        .ko-divi-ticker:hover .ko-divi-ticker-inner{ animation-play-state:paused; }
    </style>
    <?php
}
add_action('wp_head','ko_simple_divi_ticker_styles',20);
/**
 * Front-end JS to wrap and duplicate content and optionally merge groups.
 */


function ko_simple_divi_ticker_js() {
    ?>
    <script>
    (function(){

        function setMenuOpenClass(open){
            if(!document.body) return;
            document.body.classList.toggle('ko-ticker-menu-open', !!open);
        }

        function isMenuOpen(){
            var b = document.body;
            if(!b) return false;
            if(b.classList.contains('et_pb_slide_menu_active')) return true;
            if(b.classList.contains('et_pb_slide_menu_opened')) return true;

            var btn = document.querySelector('.mobile_menu_bar.et_pb_header_toggle.et_toggle_slide_menu');
            if(btn && btn.getAttribute('aria-expanded') === 'true') return true;

            var cont = document.querySelector('.et_slide_in_menu_container');
            if(cont && (cont.classList.contains('et_pb_slide_menu_opened') || cont.classList.contains('et_pb_slide_menu_active'))) return true;

            return false;
        }

        function bindMenuWatcher(){
            if('MutationObserver' in window && document.body){
                var obs = new MutationObserver(function(muts){
                    for(var i=0;i<muts.length;i++){
                        if(muts[i].type === 'attributes' && muts[i].attributeName === 'class'){
                            setMenuOpenClass(isMenuOpen());
                            break;
                        }
                    }
                });
                obs.observe(document.body, {attributes:true});
            }

            var btn = document.querySelector('.mobile_menu_bar.et_pb_header_toggle.et_toggle_slide_menu');
            if(btn){
                ['click','touchstart'].forEach(function(evt){
                    btn.addEventListener(evt, function(){
                        setTimeout(function(){ setMenuOpenClass(isMenuOpen()); }, 0);
                        setTimeout(function(){ setMenuOpenClass(isMenuOpen()); }, 150);
                    }, {passive:true});
                });
            }

            setMenuOpenClass(isMenuOpen());
        }

        function mergeGroups(){
            document.querySelectorAll('.ko-divi-ticker-group').forEach(function(group){
                if(group.dataset.koTickerGroupReady) return;
                group.dataset.koTickerGroupReady = "1";

                var items = group.querySelectorAll('.ko-divi-ticker');
                if(!items || items.length < 2) return;

                var combined = document.createElement('div');
                combined.className = 'ko-divi-ticker ko-divi-ticker-merged';

                items.forEach(function(mod){
                    var clone = mod.cloneNode(true);
                    clone.style.display = '';
                    clone.classList.remove('ko-divi-ticker');
                    clone.classList.add('ko-divi-ticker-message');
                    combined.appendChild(clone);

                    mod.style.display = 'none';
                    mod.dataset.koTickerHiddenOriginal = "1";
                });

                items[0].parentNode.insertBefore(combined, items[0]);
            });
        }

        function teardownTicker(ticker){
            if(!ticker) return;
            if(ticker.dataset.koTickerOriginalHtml){
                ticker.innerHTML = ticker.dataset.koTickerOriginalHtml;
            }
            if(ticker.dataset.koTickerReady) delete ticker.dataset.koTickerReady;
            ticker.classList.remove('ko-ticker-ready');
        }

        function setupTicker(ticker){
            if(!ticker || ticker.dataset.koTickerReady) return;

            // Skip hidden originals (group mode)
            if(ticker.dataset.koTickerHiddenOriginal === "1" || ticker.style.display === 'none') return;

            // Store original HTML once so rebuilds never double-wrap (prevents speed-up after overlays/scrollbar changes)
            if(!ticker.dataset.koTickerOriginalHtml){
                ticker.dataset.koTickerOriginalHtml = ticker.innerHTML;
            } else {
                // Always start from a clean slate
                ticker.innerHTML = ticker.dataset.koTickerOriginalHtml;
            }

            ticker.dataset.koTickerReady = "1";

            var originalNodes = Array.from(ticker.childNodes);

            var inner = document.createElement('div');
            inner.className = 'ko-divi-ticker-inner';

            var track1 = document.createElement('div');
            track1.className = 'ko-divi-ticker-track ko-divi-ticker-original';

            originalNodes.forEach(function(node){ track1.appendChild(node); });

            ticker.innerHTML = '';
            inner.appendChild(track1);
            ticker.appendChild(inner);

            // failsafe reveal
            setTimeout(function(){ ticker.classList.add('ko-ticker-ready'); }, 2000);

            requestAnimationFrame(function(){
                var baseWidth = track1.scrollWidth;
                var containerWidth = ticker.clientWidth;

                if(!baseWidth || !containerWidth){
                    ticker.classList.add('ko-ticker-ready');
                    return;
                }

                // If content is narrower than container, repeat within track1
                var buffer = 40;
                var maxCopies = 10;

                if(baseWidth < (containerWidth + buffer)){
                    var template = Array.from(track1.childNodes).map(function(n){ return n.cloneNode(true); });
                    var needed = Math.ceil((containerWidth + buffer) / baseWidth);
                    var total = Math.min(maxCopies, Math.max(2, needed));
                    for(var i=1;i<total;i++){
                        template.forEach(function(n){ track1.appendChild(n.cloneNode(true)); });
                    }
                }

                var track2 = track1.cloneNode(true);
                track2.classList.add('ko-divi-ticker-duplicate');
                inner.appendChild(track2);

                requestAnimationFrame(function(){
                    var gap = 0;
                    try{ gap = parseFloat(getComputedStyle(track2).paddingLeft) || 0; }catch(e){}
                    var distance = track1.getBoundingClientRect().width + gap;
                    if(distance > 0){
                        inner.style.setProperty('--ko-ticker-distance', distance + 'px');
                    }
                    ticker.classList.add('ko-ticker-ready');
                });
            });
        }

        function init(){
            mergeGroups();
            document.querySelectorAll('.ko-divi-ticker').forEach(setupTicker);
        }

        function bootWhenHeaderStable(){
            var header = document.getElementById('main-header');
            if(!header){ init(); return; }

            var last = header.offsetHeight;
            var stable = 0;
            var tries = 0;

            var iv = setInterval(function(){
                var h = header.offsetHeight;
                tries++;

                if(h === last){ stable++; } else { stable = 0; }

                if(stable >= 2 || tries > 12){
                    clearInterval(iv);
                    init();
                }
                last = h;
            }, 120);
        }

        function bindResizeObservers(){
            if(!('ResizeObserver' in window)) return;

            var ro = new ResizeObserver(function(entries){
                entries.forEach(function(entry){
                    var el = entry.target;
                    if(!el || !el.classList.contains('ko-divi-ticker')) return;
                    if(el.dataset.koTickerHiddenOriginal === "1" || el.style.display === 'none') return;

                    if(el._koTickerResizeTimer) clearTimeout(el._koTickerResizeTimer);
                    el._koTickerResizeTimer = setTimeout(function(){
                        teardownTicker(el);
                        setupTicker(el);
                    }, 200);
                });
            });

            document.querySelectorAll('.ko-divi-ticker').forEach(function(t){
                if(t.dataset.koTickerHiddenOriginal === "1" || t.style.display === 'none') return;
                ro.observe(t);
            });
        }

        function boot(){
            bindMenuWatcher();
            bootWhenHeaderStable();
            setTimeout(bindResizeObservers, 500);
        }

        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }

    })();
    </script>
    <?php
}
add_action('wp_footer','ko_simple_divi_ticker_js',20);
/**
 * Admin menu.
 */
function ko_simple_divi_ticker_admin_menu() {
    add_options_page(
        'KO Divi Ticker Settings',
        'KO Divi Ticker',
        'manage_options',
        'ko-simple-divi-ticker',
        'ko_simple_divi_ticker_settings_page'
    );
}
add_action( 'admin_menu', 'ko_simple_divi_ticker_admin_menu' );

/**
 * Settings page.
 */
function ko_simple_divi_ticker_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $options = ko_simple_divi_ticker_get_options();

    if ( isset( $_POST['ko_simple_divi_ticker_submit'] ) ) {
        check_admin_referer( 'ko_simple_divi_ticker_save', 'ko_simple_divi_ticker_nonce' );

        $speed = isset( $_POST['ko_simple_divi_ticker_speed'] ) ? intval( $_POST['ko_simple_divi_ticker_speed'] ) : 30;
        if ( $speed < 5 ) {
            $speed = 5;
        }
        if ( $speed > 240 ) {
            $speed = 240;
        }

        $mobile_speed = isset( $_POST['ko_simple_divi_ticker_mobile_speed'] ) ? intval( $_POST['ko_simple_divi_ticker_mobile_speed'] ) : 45;
        if ( $mobile_speed < 5 ) {
            $mobile_speed = 5;
        }
        if ( $mobile_speed > 240 ) {
            $mobile_speed = 240;
        }

        $gap   = isset( $_POST['ko_simple_divi_ticker_gap'] ) ? sanitize_text_field( wp_unslash( $_POST['ko_simple_divi_ticker_gap'] ) ) : '3rem';
        $pause = ! empty( $_POST['ko_simple_divi_ticker_pause'] ) ? 1 : 0;

        $options['speed']               = $speed;
        $options['mobile_speed']        = $mobile_speed;
        $options['gap']                 = $gap;
        $options['pause_between_loops'] = $pause;

        update_option( 'ko_simple_divi_ticker_options', $options );

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>KO Simple Divi Ticker</h1>
        <p>
            This plugin adds a scrolling ticker to any Divi Text, Heading, or Icon module with the
            <code>ko-divi-ticker</code> CSS class. You can also add
            <code>ko-divi-ticker-group</code> to a row or section to merge multiple
            <code>ko-divi-ticker</code> modules into a single combined ticker stream.
        </p>

        <form method="post" action="">
            <?php wp_nonce_field( 'ko_simple_divi_ticker_save', 'ko_simple_divi_ticker_nonce' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="ko_simple_divi_ticker_speed">Scroll speed (desktop, seconds)</label>
                    </th>
                    <td>
                        <input type="number" min="5" max="240" step="1" id="ko_simple_divi_ticker_speed" name="ko_simple_divi_ticker_speed" value="<?php echo esc_attr( $options['speed'] ); ?>" />
                        <p class="description">How long it takes for one full cycle of the ticker on desktop and larger screens. Higher = slower.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ko_simple_divi_ticker_mobile_speed">Scroll speed (mobile, seconds)</label>
                    </th>
                    <td>
                        <input type="number" min="5" max="240" step="1" id="ko_simple_divi_ticker_mobile_speed" name="ko_simple_divi_ticker_mobile_speed" value="<?php echo esc_attr( $options['mobile_speed'] ); ?>" />
                        <p class="description">How long it takes for one full cycle of the ticker on screens 767px wide and below. Use a higher value here to slow the ticker down on mobile.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ko_simple_divi_ticker_gap">Gap between repeats</label>
                    </th>
                    <td>
                        <input type="text" id="ko_simple_divi_ticker_gap" name="ko_simple_divi_ticker_gap" value="<?php echo esc_attr( $options['gap'] ); ?>" />
                        <p class="description">CSS length for the spacing between the duplicated ticker content (e.g. <code>3rem</code>, <code>40px</code>).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        Pause between loops
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="ko_simple_divi_ticker_pause" value="1" <?php checked( $options['pause_between_loops'], 1 ); ?> />
                            Add a brief pause at the end of each loop before restarting.
                        </label>
                        <p class="description">When enabled, the ticker will pause for a short moment after each full pass.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Save Changes', 'primary', 'ko_simple_divi_ticker_submit' ); ?>
        </form>

        <h2>Usage</h2>
        <ol>
            <li>In the Divi Theme Builder, edit your Global Header (or any template).</li>
            <li>Add a row and insert one or more Text, Heading, or Icon modules.</li>
            <li>In each module that should be part of the ticker, go to <strong>Advanced → CSS ID &amp; Classes</strong> and add <code>ko-divi-ticker</code> to the CSS Class field.</li>
            <li>To merge multiple ticker modules into a single combined stream, add <code>ko-divi-ticker-group</code> to the row or section that contains them.</li>
            <li>Use the modules' Design tabs to control background color, font, font size, icons, and padding as usual.</li>
        </ol>
    </div>
    <?php
}
