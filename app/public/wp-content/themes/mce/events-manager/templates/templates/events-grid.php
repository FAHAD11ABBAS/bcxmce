<?php
/*
 * Events Grid Template
 * This template displays a list of events in a grid format.
 * You can override this by copying it to your theme's folder at
 * yourthemefolder/plugins/events-manager/templates/events-grid.php
 */

/* @var array $args - the args passed onto EM_Events::output() */
$args = apply_filters('em_content_events_args', $args);

// Set default ID if not provided
if (empty($args['id'])) {
    $args['id'] = rand(100, getrandmax());
}

// Set common arguments for events
$args['scope'] = 'future'; // Show only future events
$args['limit'] = 10; // Limit to 10 events
$args['orderby'] = 'event_start_date'; // Order by event start date
$args['order'] = 'ASC'; // Ascending order

// Determine if the category should be applied
$category_selected = isset($args['category']) ? $args['category'] : (isset($_GET['category']) ? $_GET['category'] : null);

// Initialize the display category names array
$display_category_names = [];

// Check if a category has been selected
if (!empty($category_selected)) {
    if (is_array($category_selected)) {
        // Loop through all selected categories
        foreach ($category_selected as $cat_id) {
            $term = get_term($cat_id, 'event-categories'); // Use the correct taxonomy name
            if ($term && !is_wp_error($term)) {
                $display_category_names[] = esc_html($term->name); // Capture the term name
            }
        }
    } else {
        // If a single category ID is selected
        $term = get_term($category_selected, 'event-categories'); // Use the correct taxonomy name
        if ($term && !is_wp_error($term)) {
            $display_category_names[] = esc_html($term->name); // Capture the term name
        }
    }
}

// Check for the default category if no other categories are selected
if (empty($display_category_names)) {
    $default_term = get_term('valtakunnallinen-perusohjelma', 'event-categories'); // Use the correct taxonomy name
    if ($default_term && !is_wp_error($default_term)) {
        $display_category_names[] = esc_html($default_term->name);
    }
}

// Always set the default category in args if none are selected
if (empty($args['category'])) {
    $args['category'] = 'valtakunnallinen-perusohjelma'; // Apply category to args
}

$id = esc_attr($args['id']);
?>

<div class="<?php em_template_classes('view-container'); ?>" id="em-view-<?php echo $id; ?>" data-view="grid" style="--view-grid-width: <?php echo esc_attr(get_option('dbem_event_grid_item_width')); ?>px">

    <!-- Always display categories section -->
    <p><strong>Tapahtumat kategoriassa:</strong> <?php echo !empty($display_category_names) ? implode(', ', $display_category_names) : 'Valtakunnallinen perusohjelma'; ?></p>

    <div class="<?php em_template_classes('events-list', 'events-grid'); ?>" id="em-events-grid-<?php echo $id; ?>" data-view-id="<?php echo $id; ?>">
        <?php
        // Output events with the modified arguments
        echo EM_Events::output($args);
        ?>
    </div>
</div>