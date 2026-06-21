<?php get_header(); ?>
<div class="container">
    <header class="page-header">
        <h1 class="page-title">نتایج جستجو برای: <?php echo get_search_query(); ?></h1>
    </header>
    <?php if (have_posts()) : ?>
        <div class="posts-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('post-card'); ?>>
                    <div class="post-content">
                        <h2 class="post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p class="post-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 20); ?></p>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <p>نتیجه‌ای یافت نشد.</p>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
