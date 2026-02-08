<?php
/*
Template Name: Matkakertomukset
*/
?>

<?php get_header(); ?>

<div id="content">

<main>
    <?php 
    if (is_user_logged_in()) {
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        $args = array(
            'category_name' => 'matkakertomukset', // Slug of the category
            'posts_per_page' => 10, // Number of posts to show
            'paged' => $paged, // Current page number
        );
    }

    $query = new WP_Query($args);

    if (is_user_logged_in() && $query->have_posts() && $args['category_name'] === 'matkakertomukset') {
        echo '<h1 class="ajankohtaiset-header">Matkakertomukset</h1>';
    }
    
    // Start the Loop
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post(); ?>
            <article class="front-page-content">
                <?php   
                if (has_term('matkakertomukset', 'category')) {
                    echo '<p class="front-page-article-date">' . get_the_date() . '</p>';
                } ?>
                <h2 class="front-page-title"><?php the_title(); ?></h2>
                <?php   
                if (has_term('matkakertomukset', 'category')) {
                    the_excerpt();
                } ?>
            </article>
            
            <?php 
            // Display the divider only for 'ajankohtaista' posts and not on the last post
            if (has_term('matkakertomukset', 'category') && $query->current_post + 1 < $query->post_count) {
                echo '<div class="article-divider"></div>';
            } 
        } // End while loop
        
        // Display pagination for logged-in users
        if (is_user_logged_in()) {
            $pagination = paginate_links(array(
                'total' => $query->max_num_pages,
                'current' => $paged,
                'prev_text' => '&laquo; Edellinen sivu',
                'next_text' => 'Seuraava sivu &raquo;',
            ));
            if ($pagination) {
                echo '<div class="pagination">' . $pagination . '</div>';
            }
        }
    } else { // This else corresponds to $query->have_posts()
        ?>
        <p>Ei kirjoituksia</p>
        <?php 
    } // End if-else for $query->have_posts()
    
    // Reset post data
    wp_reset_postdata();
    ?>
    </main>
    
    </div>
    
    <?php get_footer(); ?>
    