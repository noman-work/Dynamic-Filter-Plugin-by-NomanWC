# Dynamic Filter Plugin - How It Works

## Overview

The Dynamic Filter Plugin enables you to create dynamic filters for any custom post type. It allows you to configure the taxonomies you want to use for filtering by creating a dedicated Filter configuration post type. Once the filter is configured, the plugin generates a shortcode that you can insert into any page or post. This shortcode then outputs a dynamic filter form on the front end that modifies archive queries based on the selected filtering criteria.

## Screenshots

**1. Front-End Preview**

![Front-end Filter Form](assets/screenshot-front.png)

**2. All Filters Page**

![All Filters Page](assets/all-filters.png)

**3. Edit Single Filter Page**

![Edit Single Filter Page](assets/admin-edit.png)

## Installation

1. Upload the plugin file (e.g., `dynamic-filter-plugin.php`) to your `/wp-content/plugins/` directory.
2. Activate the plugin from the WordPress Dashboard under **Plugins**.

## Creating a Filter Configuration

1. In your WordPress admin dashboard, go to the **Filters** menu.
2. Click **Add New Filter** to create a new filter configuration.
3. In the **Filter Settings** meta box:
   - **Select Post Type:** Choose the custom post type you want to filter (e.g., Cars, Phones).
   - **Select Taxonomies:** After you select the post type, the plugin will dynamically load the associated taxonomies. Check the ones you want to include as filtering options.
4. Save the filter configuration.

## Using the Shortcode

1. After saving your filter configuration, a new column called **Shortcode** will appear in the Filters list. This column displays a shortcode like:


2. Copy this shortcode and paste it into any page or post where you want to display the filter form.

## How the Filter Form Works

- **Dynamic Form Generation:** The shortcode renders a filter form based on your saved configuration. For each selected taxonomy, the form creates a dropdown that lists all available taxonomy terms.
- **Archive URL Integration:** The form’s action URL is the archive URL of the selected post type.
- **GET Parameters:** When a user selects options and submits the form, the plugin appends specific GET parameters (with prefixes like `selected_`) to the archive URL.

## Filtering Archive Queries

- The plugin hooks into WordPress using the `pre_get_posts` action to alter archive queries.
- It inspects the GET parameters provided by the filter form.
- For every selected taxonomy filter, a `tax_query` is built. If multiple filters are selected, they are combined using an "AND" relation, ensuring that the archive page only displays posts matching all of the selected criteria.

## Additional Notes

- **AJAX in Admin:** When setting up the Filter configuration in the admin area, AJAX is used to load the taxonomies automatically when a post type is selected.
- **Customization:** You can further customize the filter form by modifying the plugin’s code or adding custom CSS.
- **Requirements:** Ensure your custom post types have the relevant taxonomies registered for filtering to work properly.

## Author Information

**Abdullah Al Noman**  
Website: [https://nomanwc.com/](https://nomanwc.com/)

## License

This plugin is licensed under the GPL2+ License. See the [GNU GPL version 2.0](https://www.gnu.org/licenses/gpl-2.0.html) for further details.

---

Enjoy using the Dynamic Filter Plugin!
