<?php
/**
 * @package Primary Category
 */
/*
Plugin Name: Primary Category
Plugin URI: https://rocketships.ca
Description:
Many publishers use categories as a means to logically organize their content. However, many pieces of content have more than one category. Sometimes it’s useful to designate a primary category for posts (and custom post types).
Version: 0.1
Author: Shannon Graham
Author URI: https://rocketships.ca
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
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

/**
 * Adds a metabox to wp-edit on post pages.
 */
function pc_add_meta_box() {
	add_meta_box( "primary-category-box", "Primary Category", "pc_primary_category_markup", "post", "side", "default", null );
}
add_action("add_meta_boxes", "pc_add_meta_box");

/**
 * Adds a select menu to metabox.
 *
 * @param $object
 */
function pc_primary_category_markup($object) {

	get_post_meta($object->ID);

	$selected = '';
	if( $primary_category = get_post_meta($object->ID, "primary-category", true) ) {
		$selected = $primary_category;
	}

	wp_nonce_field(basename(__FILE__), "primary-category-nonce");

	//having "Uncategorized" as a primary category doesn't really seem useful to me.
	if( $uncategorized = get_category_by_slug( "uncategorized" ) ) {
		$uncategorized = $uncategorized->term_id;
	}

	?>

	<div><?php
		wp_dropdown_categories([ "name" => "primary-category", "selected" => $selected, "show_option_none" => "none", "exclude" => $uncategorized ] ); ?>
	</div>
	<?php
}


/**
 * Saves the primary category as postmeta
 *
 * @param $post_id
 * @return mixed
 */

function pc_save_primary_category($post_id, $post) {

	//check nonce
	if ( ! isset( $_POST["primary-category-nonce"] ) || ! wp_verify_nonce( $_POST["primary-category-nonce"], basename( __FILE__ ) ) ) {
		return $post_id;
	}

	//check user's capabilities
	if( !current_user_can("edit_post", $post_id) ) {
		return $post_id;
	}

	//ignore autosave
	if( defined("DOING_AUTOSAVE") && DOING_AUTOSAVE ){
		return $post_id;
	}

	//make sure it's a post and not something else
	if( "post" != $post->post_type ) {
		return $post_id;
	}


	$primary_category = "";
	if( isset($_POST['primary-category']) ){
		//always sanitize user input
		$primary_category = sanitize_option('primary-category', $_POST['primary-category'] );

	}

	// add post to category if it's not there already
	if( ! has_category( $primary_category, $post_id)  ) {
		wp_set_post_categories($post_id, $primary_category, true );
	}

	update_post_meta($post_id, "primary-category", $primary_category );

}
add_action( 'save_post', 'pc_save_primary_category', 10, 2 );


/**
 * Gets the category name
 * Queries for posts in that category
 * Returns the query
 *
 * @param $attributes
 *
 * @return false|WP_Query
 */
function pc_primary_category_posts ( $attributes ) {
	if(empty($attributes[ 'category' ])) {
		return false;
	}

	$args = [ 'category_name' => $attributes[ 'category' ] ];
	$query = new WP_Query($args);

	return $query;
}


function pc_display_posts( $attributes ) {
	if(empty($attributes[ 'category' ])) {
		return false;
	}

	$query = pc_primary_category_posts($attributes[ 'category' ]);

	if( $query->have_posts() ) { ?>
        <ul><?php
			while ($query->have_posts()) : $query->the_post(); ?>
                <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li><?php
			endwhile; ?>
        </ul> <?php
	}
}

add_shortcode( 'primary-category', 'pc_display_posts');

// Assumption:
	// if a post has a given category as its primary, then it should be in that category. Therefore, if a category is selected as primary, and the post is not in that category, add it.
	// if a post is no longer using a category as its primary, it should still remain in that category. Therefore, do not remove the post from a category when the primary designation is removed.