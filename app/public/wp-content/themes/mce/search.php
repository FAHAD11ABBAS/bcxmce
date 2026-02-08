<?php
get_header(); ?>

<main id="primary" class="site-main">
    <header class="page-header">
        <?php if (have_posts()) : ?>
            <h1 class="page-title">
                <?php
                /* translators: %s: search query. */
                printf(esc_html__('Hakutulokset haulle: %s', 'your-theme-textdomain'), '<span>' . get_search_query() . '</span>');
                ?>
            </h1>
        <?php else : ?>
            <h1 class="page-title">
                <?php esc_html_e('Nothing Found', 'your-theme-textdomain'); ?>
            </h1>
        <?php endif; ?>
    </header><!-- .page-header -->

    <?php if (have_posts()) : ?>
        <div class="search-results-list">
            <?php
            while (have_posts()) :
                the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <header class="entry-header">
                        <h2 class="entry-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                    </header><!-- .entry-header -->

                    <div class="entry-summary">
                        <a href="<?php the_permalink(); ?>" class="excerpt-link">
                            <?php the_excerpt(); ?>
                        </a>
                    </div><!-- .entry-summary -->
                </article><!-- #post-<?php the_ID(); ?> -->
            <?php endwhile; ?>

            <div class="pagination">
                <?php
                the_posts_pagination(array(
                    'prev_text' => __('Edellinen', 'your-theme-textdomain'),
                    'next_text' => __('Seuraava', 'your-theme-textdomain'),
                ));
                ?>
            </div>
        </div><!-- .search-results-list -->

    <?php endif; ?>
</main><!-- #main -->

<style>
/* General styling for the search results page */
.site-main {
    max-width: 80%;
    margin: 2rem auto;
    padding: 20px;
    background-color: #E7E6DA;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Header styling */
.page-header {
    text-align: center;
    margin-bottom: 30px;
}

.page-title {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

/* Search results list */
.search-results-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Each search result article */
article {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    transition: background 0.3s;
}

article:hover {
    background: #f9f9f9;
}

/* Entry title */
.entry-title {
    font-size: 20px;
    margin-bottom: 10px;
}

.entry-title a {
    text-decoration: none;
}

.entry-title a:hover {
    text-decoration: underline;
}

/* Excerpt styling */
.entry-summary {
    font-size: 16px;
    color: #555;
}

.excerpt-link {
    display: block;
    color: #C8A962;
    text-decoration: none;
    font-weight: bold;
    margin-top: 5px;
}

.excerpt-link:hover {
    text-decoration: underline;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 30px;
}

.pagination a {
    padding: 10px 15px;
    margin: 0 5px;
    text-decoration: none;
    border: 1px solid #DB8300;
    border-radius: 5px;
    color: #DB8300;
    transition: background 0.3s, color 0.3s;
}

.pagination a:hover {
    background: #2B2B2B;
    color: #fff;
}

@media (max-width: 768px) {
    .site-main {
        width: 100% !important;
        max-width: 100% !important;
        padding: 15px; /* Optional: Adjust padding for better spacing */
        margin: 0;
    }
}


</style>

<?php get_footer(); ?>
