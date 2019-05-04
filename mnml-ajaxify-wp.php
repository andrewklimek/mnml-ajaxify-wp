<?php
/*
Plugin Name: Minimal AJAX Page Loader
Plugin URI:  https://github.com/andrewklimek/
Description: 
Version:     0.1
Author:      Andrew J Klimek
Author URI:  https://github.com/andrewklimek
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minimal AJAX Page Loader is free software: you can redistribute it and/or modify 
it under the terms of the GNU General Public License as published by the Free 
Software Foundation, either version 2 of the License, or any later version.

Minimal AJAX Page Loader is distributed in the hope that it will be useful, but without 
any warranty; without even the implied warranty of merchantability or fitness for a 
particular purpose. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with 
Minimal AJAX Page Loader. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

/**
 * TODO:
 * if your pages are cached, it seems so much simpler and probably faster to just get the next page with ajax and parse out what you need:

var xhr = new XMLHttpRequest();
xhr.open('GET', '/music-picks/week-1');
xhr.onload = function() {
	var doc = document.implementation.createHTMLDocument();
	doc.write(xhr.responseText);
	// doc is now the requested page.  Take what you want.
};
xhr.onerror = function() {
	console.log(this.responseText);
};
xhr.send();
**/


/**
 * Setup JavaScript
 */
add_action( 'wp_enqueue_scripts', function() {
	
	wp_enqueue_script( 'mnmlajax', plugin_dir_url( __FILE__ ) . 'js.js', null, null, true );

});

add_filter('script_loader_tag', function($tag, $handle) {
	return ( 'mnmlajax' !== $handle ) ? $tag : str_replace( ' src', ' defer src', $tag );
}, 10, 2);


add_action( 'rest_api_init', function () {
	register_rest_route( 'mnmlajax/v1', '/load', array(
		'methods' => ['POST','GET'],
		'callback' => 'mnmlajax_load',
	) );
} );


function mnmlajax_load( $request ) {

// 	$data = $request->get_params();
	
	$post_obj = ajk_slug_to_post($request['slug']);
	
	if ( ! $post_obj ) return false;

	$return = array( "id" => $post_obj->ID );
	$return["html"] = apply_filters( 'the_content', $post_obj->post_content );
	$return["title"] = !empty( $post_obj->is_home) ? get_bloginfo('name') .' - '. get_bloginfo('description') : $post_obj->post_title .' - '. get_bloginfo('name');
	// $return["post_title"] = apply_filters( 'the_title', $post_obj->post_title, $return["id"] );
	$return["title"] = str_replace("&amp;", "&", $return["title"]);
	return $return;
}

// based on core url_to_postid
function ajk_slug_to_post( $slug ) {

    if ( $slug === "" && 'page' == get_option( 'show_on_front' ) ) {
        $page_on_front = get_option( 'page_on_front' );
        if ( $page_on_front) {
            $wp_post = get_post( $page_on_front );
            if ( $wp_post instanceof WP_Post ) {
				$wp_post->is_home = true;
                return $wp_post;
            }
        }
    }

    // Check to see if we are using rewrite rules
    global $wp_rewrite;
    $rewrite = $wp_rewrite->wp_rewrite_rules();
    if ( empty($rewrite) ) return 0;
 
    $post_type_query_vars = array();
 
    foreach ( get_post_types( array() , 'objects' ) as $post_type => $t ) {
        if ( ! empty( $t->query_var ) )
            $post_type_query_vars[ $t->query_var ] = $post_type;
    }
 
    // Look for matches.
    foreach ( (array)$rewrite as $match => $query) {
 
 
        if ( preg_match("#^$match#", $slug, $matches) ) {
 
            if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
                // This is a verbose page match, let's check to be sure about it.
                $page = get_page_by_path( $matches[ $varmatch[1] ] );
                if ( ! $page ) {
                    continue;
                }
 
                $post_status_obj = get_post_status_object( $page->post_status );
                if ( ! $post_status_obj->public && ! $post_status_obj->protected
                    && ! $post_status_obj->private && $post_status_obj->exclude_from_search ) {
                    continue;
                }
            }
 
            // Got a match.
            // Trim the query of everything up to the '?'.
            $query = preg_replace("!^.+\?!", '', $query);
 
            // Substitute the substring matches into the query.
            $query = addslashes(WP_MatchesMapRegex::apply($query, $matches));
 
            // Filter out non-public query vars
            global $wp;
            parse_str( $query, $query_vars );
            $query = array();
            foreach ( (array) $query_vars as $key => $value ) {
                if ( in_array( $key, $wp->public_query_vars ) ){
                    $query[$key] = $value;
                    if ( isset( $post_type_query_vars[$key] ) ) {
                        $query['post_type'] = $post_type_query_vars[$key];
                        $query['name'] = $value;
                    }
                }
            }
 
            // Resolve conflicts between posts with numeric slugs and date archive queries.
            $query = wp_resolve_numeric_slug_conflicts( $query );
 
            // Do the query
            $query = new WP_Query( $query );
            if ( ! empty( $query->posts ) && $query->is_singular )
                return $query->post;
            else
                return 0;
        }
    }
    return 0;
}