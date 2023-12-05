<?php
/*
Plugin Name: Conditional URLAds
Plugin URI: https://github.com/8Mi-Tech/yourls-conditional-urlads
Description: Conditionally send shortlinks through various link monetizing services
Version: 1.4.1
Author: 8Mi-Tech
Author URI: https://8mi.ink
*/

if ( !defined( 'YOURLS_ABSPATH' ) ) die();

yourls_add_action( 'plugins_loaded', 'conditional_urlads_load_textdomain' );
function conditional_urlads_load_textdomain() {
    yourls_load_custom_textdomain( 'conditional_urlads', dirname( __FILE__ ) . '/languages' );
}

yourls_add_action( 'plugins_loaded', 'conditional_urlads_addpage' );
function conditional_urlads_addpage() {
    if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' && $_SERVER["QUERY_STRING"] === 'page=conditional-urlads' ) {
        conditional_urlads_process_request();
    } else {
        yourls_register_plugin_page( 'conditional-urlads', 'Conditional URLAds', 'conditional_urlads_loadpage' );
    }
}
function conditional_urlads_process_request() {
    if ( isset( $_POST[ 'nonce' ] ) && yourls_verify_nonce( 'conditional-urlads' ) ) {
        ob_start();  // 开始输出缓冲
        ob_end_clean();  // 清空输出缓冲
        header('Content-Type: application/json');
        #if ( isset( $_POST[ 'adfly_id' ], $_POST[ 'adfoc_id' ], $_POST[ 'ouoio_id' ], $_POST[ 'linkvertise_id' ], ) )
        if ( isset( $_POST[ 'adfly_id' ] ) ) {
            yourls_update_option( 'conditional_urlads_adfly_id', $_POST[ 'adfly_id' ] );
        }
        if ( isset( $_POST[ 'adfoc_id' ] ) ) {
            yourls_update_option( 'conditional_urlads_adfoc_id', $_POST[ 'adfoc_id' ] );
        }
        if ( isset( $_POST[ 'ouoio_id' ] ) ) {
            yourls_update_option( 'conditional_urlads_ouoio_id', $_POST[ 'ouoio_id' ] );
        }
        if ( isset( $_POST[ 'linkvertise_id' ] ) ) {
            yourls_update_option( 'conditional_urlads_linkvertise_id', $_POST[ 'linkvertise_id' ] );
        }
        if ( isset( $_POST[ 'shortest_id' ] ) ) {
            yourls_update_option( 'conditional_urlads_shortest_id', $_POST[ 'shortest_id' ] );
        }
        yourls_update_option( 'conditional_urlads_random_adurl_bool', isset( $_POST[ 'random_adurl_bool' ] ) );
        $response = array('success' => true, 'message' => yourls__( 'Save Complete', 'conditional_urlads' ));
        echo json_encode($response);  // 输出 JSON 数据
        exit;  // 终止脚本执行，确保只返回 JSON 数据
    }

}
function conditional_urlads_loadpage() {
    #$nonce = yourls_create_nonce( 'conditional-urlads' );
    include 'settings.php';
}

define( 'RANDOM_ADURL_BOOL', yourls_get_option( 'conditional_urlads_random_adurl_bool' ) );
define( 'ADFLY_DOMAIN', 'https://adf.ly' );
define( 'ADFOCUS_DOMAIN', 'https://adfoc.us' );
define( 'OUO_DOMAIN', 'https://ouo.io' );

yourls_add_action( 'loader_failed', 'check_for_redirect' );// On url fail, check here
function check_for_redirect( $args ) {
    $regex = '!^'. implode( $TRIGGERS, '|' ) .'(.*)!';
    // Match any trigger
    if ( preg_match( $regex, $args[ 0 ], $matches ) ) {
        define( redirectService, $matches[ 0 ][ 0 ] );
        // first charachter of the redirect == service to use
        $keyword = substr( yourls_sanitize_keyword( $matches[ 1 ] ), 1 );
        // The new keyword, sub trigger
        define( doAdvert, true );
        // let our advert function know to redirect
        yourls_add_filter( 'redirect_location', 'redirect_to_advert' );
        // Add our ad-forwarding function
        include( YOURLS_ABSPATH.'/yourls-go.php' );
        // Retry forwarding
        exit;
        // We already restarted the process, stop
    }
}

define( 'TRIGGERS', array( 'a/', 'f/', 'o/', 'l/', 's/', 'r/') );// Add any possible trigger to use here
function redirect_to_advert( $url, $code ) {
    $ADFLY_ID = yourls_get_option( 'conditional_urlads_adfly_id' );
    $ADFOC_ID = yourls_get_option( 'conditional_urlads_adfoc_id' );
    $OUOIO_ID = yourls_get_option( 'conditional_urlads_ouoio_id' );
    $SHORTEST_ID = yourls_get_option( 'conditional_urlads_shortest_id' );
    $LINKVERTISE_ID = yourls_get_option( 'conditional_urlads_linkvertise_id' );    
    if ( doAdvert ) {
        $redirectUrl = getRedirect(['type' => 'minus', 'in-string' => redirectService]);
        mt_srand();
        switch ( redirectService ) {
            case 'r': //Random AdUrl
                if ( RANDOM_ADURL_BOOL ) {
                    $keywords = [ 'a', 'f', 'l', 'o', 's' ];
                    return getRedirect(['type' => 'replace', 'in-string' => 'r', 'out-string' => $keywords[mt_rand(0, 4)]]);
                }
            case 'a': // Adf.ly
                return ADFLY_DOMAIN . "/$ADFLY_ID/$redirectUrl";
            case 'f': // acfoc.us
                return ADFOCUS_DOMAIN . "/serve/sitelinks/?id=$ADFOC_ID&url=$redirectUrl";
            case 'o': // OuO.io
                return OUO_DOMAIN . "/qs/$OUOIO_ID?s=$redirectUrl";
            case 'l': // linkvertise.com
                return 'https://link-to.net/' . $LINKVERTISE_ID . '/' . strval( mt_rand()*1000 ) . '/dynamic?r=' . base64_encode( utf8_encode( $redirectUrl ) );
            case 's': // Shorte.st
                return "https://sh.st/st/$SHORTEST_ID/$redirectUrl";
        }
    }
    return $url;
    // If none of those redirect services, forward to the normal URL
}

function getRedirect($params) {
    $type = isset($params['type']) ? $params['type'] : NULL; // plus minus replace
    $in_string = isset($params['in-string']) ? $params['in-string'] : NULL; // for plus minus replace
    $out_string= isset($params['out-string']) ? $params['out-string'] : NULL; // for replace

    $protocol = ( isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] === 'on' ? 'https' : 'http' ) ;
    switch($type){
        case 'plus':
            return "$protocol://$_SERVER[HTTP_HOST]/$in_string/$_SERVER[REQUEST_URI]";
        case 'minus':
            return "$protocol://$_SERVER[HTTP_HOST]/" . ltrim($_SERVER['REQUEST_URI'], "/$in_string");
        case 'replace':
            return "$protocol://$_SERVER[HTTP_HOST]/$out_string/" . ltrim($_SERVER['REQUEST_URI'], "/$in_string");
        default:
            return "$protocol://$_SERVER[HTTP_HOST]/$_SERVER[REQUEST_URI]";
    }
}
?>