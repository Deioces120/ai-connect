<?php get_header(); ?>

<div class="container">
    <?php if (is_home() && !is_front_page()) : ?>
        <header class="page-header"><h1 class="page-title"><?php single_post_title(); ?></h1></header>
    <?php endif; ?>

    <?php if (have_posts()) : ?>
        <div class="posts-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('post-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="post-thumbnail"><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium'); ?></a></div>
                    <?php endif; ?>
                    <div class="post-content">
                        <h2 class="post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <div class="post-meta">
                            <span class="post-date"><?php echo get_the_date(); ?></span>
                            <span class="post-author"><?php the_author(); ?></span>
                        </div>
                        <p class="post-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 20); ?></p>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        <div class="pagination"><?php the_posts_pagination(); ?></div>
    <?php else : ?>
        <p>محتوایی یافت نشد.</p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
