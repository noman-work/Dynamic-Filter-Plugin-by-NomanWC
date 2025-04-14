<?php
/**
 * Plugin Name: Dynamic Filter Plugin by NomanWC
 * Plugin URI: https://nomanwc.com/dynamic-filter-plugin
 * Description: This plugin enables you to create dynamic filters for any custom post type by configuring taxonomies via a dedicated Filter configuration post type. It allows the generation of shortcodes to display filter forms that modify archive queries based on the selected criteria.
 * Version: 1.1
 * Author: Abdullah Al Noman
 * Author URI: https://nomanwc.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dynamic-filter-plugin
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Dynamic_Filter_Plugin
{

    public function __construct()
    {
        // Register our custom Filter post type.
        add_action('init', array($this, 'register_filter_cpt'));
        // Add meta boxes to the Filter post edit screen.
        add_action('add_meta_boxes', array($this, 'add_filter_meta_box'));
        add_action('save_post_filter', array($this, 'save_filter_meta'));
        // Enqueue admin scripts for AJAX handling.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        // AJAX action to load taxonomies based on selected post type.
        add_action('wp_ajax_get_taxonomies_by_post_type', array($this, 'ajax_get_taxonomies'));
        // Register shortcode to output the dynamic filter form.
        add_shortcode('dynamic_filter', array($this, 'render_filter_shortcode'));
        // Hook into pre_get_posts to modify the archive query.
        add_action('pre_get_posts', array($this, 'handle_filter_query'));
        // Add custom column for the shortcode.
        add_filter('manage_filter_posts_columns', array($this, 'add_shortcode_column'));
        add_action('manage_filter_posts_custom_column', array($this, 'render_shortcode_column'), 10, 2);
    }

    /**
     * Register a custom post type for filter configurations.
     */
    public function register_filter_cpt()
    {
        $labels = array(
            'name'          => 'Filters',
            'singular_name' => 'Filter',
            'add_new_item'  => 'Add New Filter',
            'edit_item'     => 'Edit Filter',
        );
        $args = array(
            'labels'        => $labels,
            'public'        => true,
            'show_ui'       => true,
            'supports'      => array('title'),
            'menu_position' => 20,
            'has_archive'   => false,
        );
        register_post_type('filter', $args);
    }

    /**
     * Add a meta box for selecting post type and taxonomies.
     */
    public function add_filter_meta_box()
    {
        add_meta_box(
            'filter_meta',
            'Filter Settings',
            array($this, 'filter_meta_box_callback'),
            'filter',
            'normal',
            'high'
        );
    }

    /**
     * Render the meta box in the Filter post edit screen.
     */
    public function filter_meta_box_callback($post)
    {
        wp_nonce_field('save_filter_meta', 'filter_nonce');

        // Retrieve saved values.
        $associated_post_type    = get_post_meta($post->ID, 'associated_post_type', true);
        $associated_taxonomies   = get_post_meta($post->ID, 'associated_taxonomies', true);
        if (! is_array($associated_taxonomies)) {
            $associated_taxonomies = array();
        }
        // Retrieve all public post types.
        $post_types = get_post_types(array('public' => true), 'objects');
?>
        <p>
            <label for="associated_post_type"><strong>Select Post Type:</strong></label>
            <select name="associated_post_type" id="associated_post_type">
                <option value="">-- Select Post Type --</option>
                <?php foreach ($post_types as $post_type_obj) : ?>
                    <option value="<?php echo esc_attr($post_type_obj->name); ?>" <?php selected($associated_post_type, $post_type_obj->name); ?>>
                        <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label><strong>Select Taxonomies:</strong></label>
        <div id="taxonomy-checkboxes">
            <?php
            if ($associated_post_type) {
                // If a post type is already selected, load its taxonomies.
                $taxonomies = get_object_taxonomies($associated_post_type, 'objects');
                foreach ($taxonomies as $tax) {
                    $checked = in_array($tax->name, $associated_taxonomies) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="associated_taxonomies[]" value="' . esc_attr($tax->name) . '" ' . $checked . ' /> ' . esc_html($tax->labels->singular_name) . '</label><br />';
                }
            } else {
                echo 'Select a post type to load taxonomies.';
            }
            ?>
        </div>
        </p>
        <script>
            jQuery(document).ready(function($) {
                $('#associated_post_type').on('change', function() {
                    var postType = $(this).val();
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_taxonomies_by_post_type',
                            post_type: postType,
                            security: '<?php echo wp_create_nonce("get_taxonomies_nonce"); ?>'
                        },
                        success: function(response) {
                            $('#taxonomy-checkboxes').html('');
                            if (response.success) {
                                $.each(response.data, function(index, tax) {
                                    $('#taxonomy-checkboxes').append(
                                        '<label><input type="checkbox" name="associated_taxonomies[]" value="' + tax.name + '" /> ' + tax.label + '</label><br />'
                                    );
                                });
                            } else {
                                $('#taxonomy-checkboxes').html('No taxonomies available.');
                            }
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * Save the meta box selections.
     */
    public function save_filter_meta($post_id)
    {
        // Verify nonce.
        if (! isset($_POST['filter_nonce']) || ! wp_verify_nonce($_POST['filter_nonce'], 'save_filter_meta')) {
            return;
        }
        // Avoid autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (isset($_POST['associated_post_type'])) {
            update_post_meta($post_id, 'associated_post_type', sanitize_text_field($_POST['associated_post_type']));
        }
        if (isset($_POST['associated_taxonomies'])) {
            $taxonomies = array_map('sanitize_text_field', $_POST['associated_taxonomies']);
            update_post_meta($post_id, 'associated_taxonomies', $taxonomies);
        } else {
            delete_post_meta($post_id, 'associated_taxonomies');
        }
    }

    /**
     * Enqueue admin scripts if needed.
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only enqueue on the Filter post type edit screen.
        if ('post.php' == $hook || 'post-new.php' == $hook) {
            global $post;
            if (isset($post->post_type) && 'filter' === $post->post_type) {
                wp_enqueue_script('jquery');
            }
        }
    }

    /**
     * Handle the AJAX request: return all taxonomies associated with a given post type.
     */
    public function ajax_get_taxonomies()
    {
        check_ajax_referer('get_taxonomies_nonce', 'security');
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        if ($post_type) {
            $taxonomies = get_object_taxonomies($post_type, 'objects');
            $result = array();
            foreach ($taxonomies as $tax) {
                $result[] = array(
                    'name'  => $tax->name,
                    'label' => $tax->labels->singular_name,
                );
            }
            wp_send_json_success($result);
        }
        wp_send_json_error();
    }

    /**
     * Shortcode: Display a dynamic filter form.
     *
     * Usage: [dynamic_filter id="123"]
     */
    public function render_filter_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => ''
        ), $atts, 'dynamic_filter');
        if (empty($atts['id'])) {
            return 'Filter configuration ID is missing.';
        }
        $filter_post = get_post($atts['id']);
        if (! $filter_post || 'filter' !== $filter_post->post_type) {
            return 'Invalid filter configuration.';
        }
        // Get the associated post type and taxonomies.
        $post_type  = get_post_meta($filter_post->ID, 'associated_post_type', true);
        $taxonomies = get_post_meta($filter_post->ID, 'associated_taxonomies', true);
        if (empty($post_type)) {
            return 'No associated post type selected.';
        }
        if (empty($taxonomies) || ! is_array($taxonomies)) {
            return 'No taxonomies selected for filtering.';
        }
        ob_start();
        // Get the archive URL for the target post type.
        $archive_url = get_post_type_archive_link($post_type);
        echo '<form class="dynamic-filter-form" style="display: flex; flex-direction: column; gap: 15px;" method="get" action="' . esc_url($archive_url) . '">';
        // Loop through each taxonomy selected.
        foreach ($taxonomies as $tax) {
            $taxonomy_obj = get_taxonomy($tax);
            if (! $taxonomy_obj) {
                continue;
            }
            // Use the taxonomy name to build a GET parameter name.
            $param_name = 'selected_' . sanitize_key($tax);
            echo '<div class="filter-item">';
            echo '<label for="' . esc_attr($param_name) . '">' . esc_html($taxonomy_obj->labels->singular_name) . ':</label>';
            echo '<select name="' . esc_attr($param_name) . '" id="' . esc_attr($param_name) . '">';
            echo '<option value="">Select ' . esc_html($taxonomy_obj->labels->singular_name) . '</option>';
            $terms = get_terms(array(
                'taxonomy'   => $tax,
                'hide_empty' => false
            ));
            if (! empty($terms) && ! is_wp_error($terms)) {
                foreach ($terms as $term) {
                    echo '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</option>';
                }
            }
            echo '</select>';
            echo '</div>';
        }
        echo '<button type="submit">Apply Filters</button>';
        echo '</form>';
        return ob_get_clean();
    }

    /**
     * Modify the query on archive pages based on selected filter GET parameters.
     */
    public function handle_filter_query($query)
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }
        // Loop through all public post types.
        $public_post_types = get_post_types(array('public' => true));
        foreach ($public_post_types as $post_type) {
            if ($query->is_post_type_archive($post_type)) {
                // Get all taxonomies for this post type.
                $taxonomies = get_object_taxonomies($post_type);
                $tax_query = array();
                foreach ($taxonomies as $tax) {
                    $param_name = 'selected_' . sanitize_key($tax);
                    if (! empty($_GET[$param_name])) {
                        $tax_query[] = array(
                            'taxonomy' => $tax,
                            'field'    => 'term_id',
                            'terms'    => intval($_GET[$param_name])
                        );
                    }
                }
                if (! empty($tax_query)) {
                    // Combine multiple taxonomy filters with an AND relation.
                    $tax_query['relation'] = 'AND';
                    $query->set('tax_query', $tax_query);
                }
                // Once a matching post type archive is found, no need to continue.
                break;
            }
        }
    }

    /**
     * Add a new column to display the shortcode in the Filter post list table.
     *
     * @param array $columns Current columns.
     * @return array Modified columns.
     */
    public function add_shortcode_column($columns)
    {
        // Insert the Shortcode column after the title.
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ('title' === $key) {
                $new_columns['shortcode'] = 'Shortcode';
            }
        }
        return $new_columns;
    }

    /**
     * Render the content for the Shortcode column.
     *
     * @param string $column  The column name.
     * @param int    $post_id The current post ID.
     */
    public function render_shortcode_column($column, $post_id)
    {
        if ('shortcode' === $column) {
            $shortcode = sprintf('[dynamic_filter id="%d"]', $post_id);
            echo esc_html($shortcode);
        }
    }
}

new Dynamic_Filter_Plugin();
