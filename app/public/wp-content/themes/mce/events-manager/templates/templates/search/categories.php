<?php
/* @var $args array */
$include = !empty($args['categories_include']) ? $args['categories_include'] : array();
$exclude = !empty($args['categories_exclude']) ? $args['categories_exclude'] : array();
?>

<!-- START Category Search -->
<div class="em-search-category em-search-field">
    <label for="em-search-category-<?php echo absint($args['id']) ?>" class="screen-reader-text">
        <?php echo esc_html($args['category_label']); ?>
    </label>

    <div class="em-category-checkbox-list">
        <?php
        $args_em = apply_filters('em_advanced_search_categories_args', array(
            'orderby' => 'name',
            'hide_empty' => 0,
            'include' => $include,
            'exclude' => $exclude
        ));
        $categories = EM_Categories::get($args_em);
        
        $selected = isset($_GET['category']) ? (array) $_GET['category'] : array();
        
        if (!empty($args['category'])) {
            $selected = is_array($args['category']) ? $args['category'] : explode(',', $args['category']);
        }

        foreach ($categories as $category) {
            $is_checked = in_array($category->term_id, $selected) ? 'checked' : '';
            echo '<label><input type="checkbox" name="category[]" value="' . esc_attr($category->term_id) . '" ' . $is_checked . '> ' . esc_html($category->name) . '</label>';
        }
        ?>
    </div>
</div>
<!-- END Category Search -->

<style>
/* Style the checkbox list to make it always visible */
.em-category-checkbox-list {
    display: grid; /* Use grid layout */
    grid-template-columns: repeat(3, 1fr); /* Creates 3 equal columns */
    gap: 10px; /* Adds space between the checkboxes */
    padding: 8px;
    background-color: #fff;
    max-height: 500px;
    overflow-y: auto;
    width: 100%;
    background-color: #E7E6DA !important;
}

/* Style for individual checkbox labels */
.em-category-checkbox-list label {
    display: flex; /* Makes sure the checkbox and label are inline */
    align-items: center; /* Vertically center the checkbox and text */
}

/* Add some hover/focus effects */
.em-category-checkbox-list input[type="checkbox"]:focus {
    outline: none;
    border-color: #0078d4;
}

.em-search-text, #em-search-text-1 {
    margin-bottom: 0 !important;
}

.em-search-section-location, .em-search-advanced-section {
    padding: 0 !important;
    background-color: #E7E6DA !important;
}

.em-submit-section, .em-search-submit {
    background-color: #E7E6DA !important;
    text-align: center !important;
}

/* Mobile-responsive CSS */
@media (max-width: 768px) {
    .em-category-checkbox-list {
        grid-template-columns: 1fr; /* Stacks the checkboxes in a single column on mobile */
        gap: 8px; /* Adjust spacing for smaller screens */
    }

    /* Optionally, you can adjust the font size on mobile */
    .em-category-checkbox-list label {
        font-size: 14px;
    }
}
</style>
