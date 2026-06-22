<?php get_header(); ?>

<div id="content-area" style="padding: 20px;">
    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        <div class="pma-header">
            <h2><?php the_title(); ?></h2>
        </div>
        <div class="pma-doc-box">
            <?php the_content(); ?>
        </div>
    <?php endwhile; else : ?>
        <p>No content found.</p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
