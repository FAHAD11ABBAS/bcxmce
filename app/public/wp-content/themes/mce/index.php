<?php get_header(); ?>

<div id="content">
    <main>
        <?php if (have_posts()) :
        while(have_posts()) : the_post(); ?>

        <article>
            <h2 id="event-page-title"><?php the_title(); ?></h2>
            <?php the_content(); ?>

        </article>

        <?php endwhile;
        endif ?>
    </main>
</div>
<?php get_footer(); ?>

