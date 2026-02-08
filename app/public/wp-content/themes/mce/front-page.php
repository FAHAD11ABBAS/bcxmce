<?php get_header(); ?>

<div id="content">
<main>

<?php 
if (is_user_logged_in()) {

    // Get the latest 'ajankohtaista' post
    $latest_args = array(
        'category_name' => 'ajankohtaista',
        'posts_per_page' => 1,
    );
    $latest_query = new WP_Query($latest_args);

    if ($latest_query->have_posts()) {
        echo '<h1 class="ajankohtaiset-header">Executor uutiset</h1>';

        while ($latest_query->have_posts()) {
            $latest_query->the_post(); ?>
            <article class="front-page-content latest-ajankohtaista">
                <p class="front-page-article-date"><?php echo get_the_date(); ?></p>
                <h2 class="front-page-title"><?php the_title(); ?></h2>
                <?php the_content(); ?>
            </article>
            <div class="article-divider"></div>
        <?php }
        wp_reset_postdata();
    }

    // Get the rest of the 'ajankohtaista' posts, excluding the latest
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    $rest_args = array(
        'category_name' => 'ajankohtaista',
        'posts_per_page' => 9, // Show 9 more to total 10
        'paged' => $paged,
        'offset' => 1, // Skip the first (latest) post
    );
    $rest_query = new WP_Query($rest_args);

    if ($rest_query->have_posts()) {
        while ($rest_query->have_posts()) {
            $rest_query->the_post(); ?>
            <article class="front-page-content">
                <p class="front-page-article-date"><?php echo get_the_date(); ?></p>
                <h2 class="front-page-title"><?php the_title(); ?></h2>
                <?php the_excerpt(); ?>
            </article>
            <?php 
            if ($rest_query->current_post + 1 < $rest_query->post_count) {
                echo '<div class="article-divider"></div>';
            }
        }

        // Pagination
        $pagination = paginate_links(array(
            'total' => $rest_query->max_num_pages,
            'current' => $paged,
            'prev_text' => '&laquo; Edellinen sivu',
            'next_text' => 'Seuraava sivu &raquo;',
        ));
        if ($pagination) {
            echo '<div class="pagination">' . $pagination . '</div>';
        }

    } else {
        echo '<p>Ei kirjoituksia</p>';
    }

    wp_reset_postdata();

} else {
    // Non-logged-in view for 'julkinen-etusivu' posts
    $args = array(
        'category_name' => 'julkinen-etusivu',
        'posts_per_page' => 10,
    );
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post(); ?>
            <article class="front-page-content">
                <h2 class="front-page-title"><?php the_title(); ?></h2>
                <?php the_content(); ?>
            </article>
        <?php }
    } else {
        echo '<p>Ei kirjoituksia</p>';
    }

    wp_reset_postdata();
}
?>

</main>
</div>

<?php get_footer(); ?>

    