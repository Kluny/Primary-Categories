<?php
/**
 * @package Primary_Category
 * @author Shannon Graham <shannon@rocketships.ca>
 * @license  GPLv2 or later
 * @link http://rocketships.ca
 */
/*
Plugin Name: Primary Category
Plugin URI: http://rocketships.ca
Description: Many publishers use categories as a means to logically organize their content.
However, many pieces of content have more than one category.
Sometimes it’s useful to designate a primary category for posts (and custom post types).
Version: 0.1
Author: Shannon Graham
Author URI: http://rocketships.ca
License: GPLv2 or later
Text Domain: pc
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	exit;
}

// Standard info to make the codesniffer happy
define( 'PRIMARY_CATEGORY_VERSION', '0.1' );
define( 'PRIMARY_CATEGORY__MINIMUM_WP_VERSION', '4.0' );
define( 'PRIMARY_CATEGORY__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Add custom taxonomy
 */
function pc_register_taxonomy() {
	// this taxonomy applies to all post types; possible improvement, make it a setting to include specified post types.
	register_taxonomy( 'primary_category', get_post_types( array(), 'names' ), array(
		'hierarchical' => false,
		'show_ui' => true,
		'show_admin_column' => true,
		'query_var' => true,
		'labels' => array(
			'name' => 'Primary Category',
		),
		'capabilities' => array(
			'manage_terms'
		),
		'meta_box_cb' => 'pc_metabox_markup',
	) );
}
add_action( 'init', 'pc_register_taxonomy', 0 );


/**
 * Adds a select menu to metabox with all existing categories. Metabox does not update in realtime a new category is added; possible future improvement.
 *
 * @param $object - The current post
 */
function pc_metabox_markup( $object ) {

	get_post_meta( $object->ID );

	if ( $selected = get_the_terms( $object->ID, 'primary_category' ) ) {
		if ( ! is_wp_error( $selected ) ) {
			if ( $category = get_category_by_slug( $selected[0]->slug ) ) {
				$selected = $category->term_id;
			}
		} else {
			$selected = -1;
		}
	}

	wp_nonce_field( basename( __FILE__ ), 'primary-category-nonce' );

	// having "Uncategorized" as a primary category doesn't seem useful to me.
	if ( $uncategorized = get_category_by_slug( 'uncategorized' ) ) {
		$uncategorized = $uncategorized->term_id;
	}

	?>

	<div><?php
		wp_dropdown_categories( array(
			'name' => 'primary-category',
			'selected' => $selected,
			'show_option_none' => 'none',
			'exclude' => $uncategorized,
		) );
		?>
	</div>
	<?php
}


/**
 * Saves the primary category as a custom taxonomy
 *
 * @param $post_id
 * @return mixed - returns $post_id on failure
 */

function pc_save_primary_category( $post_id ) {
	// ignore autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $post_id;
	}

	// check nonce
	if ( ! isset( $_POST['primary-category-nonce'] ) || ! wp_verify_nonce( $_POST['primary-category-nonce'], basename( __FILE__ ) ) ) {
		return $post_id;
	}

	// check user's capabilities
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	// make sure there's input
	if ( ! isset( $_POST['primary-category'] ) ) {
		return $post_id;
	}

	// if the "none" option was selected
	if ( $_POST['primary-category'] === '-1' ) {
		pc_remove_primary_category( $post_id );
		return $post_id;
	}

	// make sure the category exists
	if ( ! $selected = get_term( sanitize_text_field( $_POST['primary-category'], 'category' ) ) ) {
		return $post_id;
	}

	// make sure it's not an error
	if ( is_wp_error( $selected ) ) {
		return $post_id;
	}

	// save primary category as custom taxonomy
	if ( false !== wp_set_post_terms( $post_id, $selected->slug, 'primary_category', $append = false ) ) {
		// add post to category if it's not there already
		if ( ! has_category( $selected->term_id, $post_id ) ) {
			wp_set_post_categories( $post_id, $selected->term_id, true );
		}
	}
}
add_action( 'save_post', 'pc_save_primary_category', 10, 2 );

/**
 * Removes primary category from a post. This plugin assumes a post can have one and only one primary category and therefore removes only the first one found. If a bug occurs where multiple primary terms exist, this method could be modified to iterate terms instead.
 *
 * @param $post_id
 */
function pc_remove_primary_category( $post_id ) {
	if ( $term = get_the_terms( $post_id, 'primary_category' ) ) {
		if ( ! is_wp_error( $term ) ) {
			wp_remove_object_terms( $post_id, $term[0]->term_id, 'primary_category' );
		}
	}
}

/**
 * Returns array of posts with given primary category and post type.
 *
 * @param $category - Category slug
 * @param $post_type {string|array}
 *
 * @return false|array
 */
function pc_primary_category_posts( $category, $post_type = 'post' ) {
	$posts_array = get_posts( array(
		'posts_per_page' => -1,
		'post_type' => $post_type,
		'suppress_filters' => false,
		'tax_query' => array( // this can potentially cause a slow query. If so, caching can be added to this function.
			array(
				'taxonomy' => 'primary_category',
				'field' => 'slug',
				'terms' => $category,
			),
		),
	) );

	return $posts_array;
}

/**
 * Return HTML for displaying terms of a given category. Set 'category' value in attributes array.
 * Intended for use by primary-category shortcode.
 *
 * @param $attributes
 *
 * @return bool|string
 */
function pc_display_posts( $attributes ) {

	if ( empty( $attributes['category'] ) ) {
		return false;
	}
	$category = sanitize_text_field( $attributes['category'] );

	$post_type = ( empty( $attributes['post_type'] ) ) ? 'post' : sanitize_text_field( $attributes['post_type'] );
	$post_type = explode( ',', $post_type );

	$posts = pc_primary_category_posts( $category, $post_type );

	$display = '<ul>';
	foreach ( $posts as $post ) {
		$display .= '<li><a href="' . get_permalink( $post ) . '">' . $post->post_title . '</a></li>';
	}

	return $display . '</ul>';
}
add_shortcode( 'primary-category', 'pc_display_posts' );


/**
 * Helper function to return the primary category of a post. Return false if none is found.
 *
 * @param  $post_id {int|WP_Post} post id or a WP_Post object
 * @return mixed
 */
function pc_get_primary_category( $post_id ) {

	if ( $primary_category = get_the_terms( $post_id, 'primary_category' ) ) {

		if ( ! is_wp_error( $primary_category ) ) {
			return $primary_category;
		}
	}

	return false;
}


/**
 * Helper function to return url to the primary category of a post. Return false if none is found.
 *
 * @param  $post_id {int|WP_Post} post id or a WP_Post object
 * @return mixed
 */
function pc_primary_category_url( $post_id ) {

	if ( $primary_category = get_the_terms( $post_id, 'primary_category' ) ) {

		if ( ! is_wp_error( $primary_category ) ) {
			if ( $category = get_category_by_slug( $primary_category[0]->slug ) ) {
				return get_category_link( $category->term_id );
			}
		}
	}

	return false;
}



/**
 * Helper function to return html link to the primary category of a post. Return false if none is found.
 *
 * @param  $post_id {int|WP_Post} post id or a WP_Post object
 * @param  $text {string} Link text
 * @return mixed
 */
function pc_primary_category_link( $post_id, $text ) {
	$category = pc_primary_category_url( $post_id );

	if ( false === $category ) {
		return false;
	}

	$text = ( empty( $text ) ) ? $category->term_id : $text;
	return '<a href="' . get_category_link( $category->term_id ) . '">' . $text . '</a>';

}
