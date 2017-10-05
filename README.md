# Primary-Categories
Many publishers use categories as a means to logically organize their content. However, many pieces of content have more than one category. Sometimes it’s useful to designate a primary category for posts (and custom post types).

### Usage:

- Use dropdown menu in admin area to select primary category. If post isn't already a member of the selected category, it will be added to that category as well. This prevents nonsensical situations where a post's primary category is "dog" but it doesn't appear on the "dog" category page. 

- Use primary-category shortcode like this to include a list of items with given primary category in a post. Specify desired post types with a comma-separated list. If no post type is provided, "post" is the default. 

    `[primary-category category="stuff", post_type="page,post"]`
    
- `pc_primary_category_posts($category_slug)`  

Returns an array of posts that have the given primary category. 

- `pc_get_primary_category($post_id)`

Returns a term object with the given post's primary category.

- `pc_get_primary_category_link($post_id)`

Returns a link to that category's page.

### Assumptions:
- If a post has a given category as its primary, then it should be in that category. Therefore, if a category is selected as primary, and the post is not in that category, add it.

- If a post is no longer using a category as its primary, it should still remain in that category. Therefore, do not remove the post from a category when the primary designation is removed.
