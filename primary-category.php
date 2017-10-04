<?php
/**
 * @package Primary_Category
 * @author Shannon Graham <shannon@rocketships.ca>
 * @license  GPLv2 or later
 * @link www.rocketships.ca
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
if (!function_exists('add_action') ) {
    echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
    exit;
}

/**
 * Add custom taxonomy
 */
function pc_create_primary_category_taxonomy() 
{
    register_taxonomy(
        'primary_category', array( 'post' ), array(
        'hierarchical'      => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'labels'            => array( 'name' => "Primary Category" ),
        'capabilities'      => array('manage_terms'),
        'meta_box_cb'       => 'pc_primary_category_markup'
        ) 
    );
}
add_action('init', 'pc_create_primary_category_taxonomy', 0);


/**
 * Adds a select menu to metabox with all existing categories.
 *
 * @param $object - The current post
 */
function pc_primary_category_markup( $object )
{

    get_post_meta($object->ID);

    $selected = -1;
    if($selected = get_the_terms($object->ID, "primary_category", true) ) {
        if(! is_wp_error($selected) ) {
            if($category = get_category_by_slug($selected[0]->slug) ) {
                   $selected = $category->term_id;
            }
        }
    }

    wp_nonce_field(basename(__FILE__), "primary-category-nonce");

    //having "Uncategorized" as a primary category doesn't seem useful to me.
    if($uncategorized = get_category_by_slug("uncategorized") ) {
        $uncategorized = $uncategorized->term_id;
    }

    ?>

    <div><?php
    wp_dropdown_categories(
        [
        "name" => "primary-category",
        "selected" => $selected,
        "show_option_none" => "none",
        "exclude" => $uncategorized
        ]
         ); ?>
    </div>
    <?php
}


/**
 * Saves the primary category as postmeta
 *
 * @param  $post_id
 * @return mixed - returns $post_id on failure
 */

function pc_save_primary_category( $post_id )
{
    //ignore autosave
    if(defined("DOING_AUTOSAVE") && DOING_AUTOSAVE ) {
        return $post_id;
    }

    //check nonce
    if (! isset($_POST["primary-category-nonce"]) || ! wp_verify_nonce($_POST["primary-category-nonce"], basename(__FILE__)) ) {
        return $post_id;
    }

    //check user's capabilities
    if(!current_user_can("edit_post", $post_id) ) {
        return $post_id;
    }

    //make sure it's a post and not something else
    if("post" !== $post->post_type ) {
        return $post_id;
    }

    //make sure there's input
    if(! isset($_POST['primary-category']) ) {
        return $post_id;
    }

    //make sure the category exists
    if(! $selected = get_term(sanitize_text_field($_POST['primary-category'], 'category')) ) {
        return $post_id;
    }

    //make sure it's not an error
    if(is_wp_error($selected) ) {
        return $post_id;
    }

    //save primary category as custom taxonomy here
    wp_set_post_terms($post_id, $selected->slug, 'primary_category', $append = false);

    // add post to category if it's not there already
    if(! has_category($selected->term_id, $post_id)  ) {
        wp_set_post_categories($post_id, $selected->term_id, true);
    }

}
add_action('save_post', 'pc_save_primary_category');


/**
 * Returns array of posts with given primary category
 *
 * @param $category - Category slug
 *
 * @return false|WP_Query
 */
function pc_primary_category_posts( $category ) 
{

    $posts_array = get_posts(
        array(
        'posts_per_page' => -1,
        'post_type' => 'post',
        'tax_query' => array(
                array(
                    'taxonomy' => 'primary_category',
                    'field' => 'slug',
                    'terms' => $category,
                )
            )
        )
    );

    return $posts_array;
}

/**
 * Return HTML for displaying terms of a given category. Set 'category' value in attributes array. Intended for use by primary-category shortcode.
 *
 * @param $attributes
 *
 * @return bool|string
 */
function pc_display_posts( $attributes ) 
{

    if(empty($attributes[ 'category' ]) ) {
        return false;
    }

    $posts = pc_primary_category_posts($attributes[ 'category' ]);

    $display = '<ul>';
    foreach($posts as $post) {
        $display .= '<li><a href="' . get_permalink($post) . '">' . $post->post_title . '</a></li>';
    }

    return $display . "</ul>";
}
add_shortcode('primary-category', 'pc_display_posts');


/**
 * Helper function to return the primary category of a post. Return false if none is found.
 *
 * @param  $post_id {int|WP_Post} post id or a WP_Post object
 * @return mixed
 */
function pc_get_primary_category( $post_id )
{

    if($primary_category = get_the_terms($post_id, "primary_category", true) ) {

        if(! is_wp_error($primary_category) ) {
            return $primary_category;
        }
    }

    return false;
}


/**
 * Helper function to return link to the primary category of a post. Return false if none is found.
 *
 * @param  $post_id {int|WP_Post} post id or a WP_Post object
 * @return mixed
 */
function pc_get_primary_category_link( $post_id )
{

    if($primary_category = get_the_terms($post_id, "primary_category", true) ) {

        if(! is_wp_error($primary_category) ) {
            if($category = get_category_by_slug($primary_category[0]->slug) ) {
                return get_category_link($category->term_id);
            }
        }
    }

    return false;
}

