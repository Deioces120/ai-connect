<?php get_header(); ?>
<div class="container">
    <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('single-post'); ?>>
            <header class="post-header">
                <h1 class="post-title"><?php the_title(); ?></h1>
                <div class="post-meta">
                    <span><?php echo get_the_date(); ?></span>
                    <span><?php the_author(); ?></span>
                    <?php the_category(', '); ?>
                </div>
            </header>
            <?php if (has_post_thumbnail()) : ?>
                <div class="post-thumbnail"><?php the_post_thumbnail('large'); ?></div>
            <?php endif; ?>
            <div class="entry-content"><?php the_content(); ?></div>
            <div class="post-tags"><?php the_tags('<span class="tag">', '</span><span class="tag">', '</span>'); ?></div>
        </article>
        <?php if (comments_open() || get_comments_number()) : comments_template(); endif; ?>
    <?php endwhile; ?>
</div>
<?php get_footer(); ?>
