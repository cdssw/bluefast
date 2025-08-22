<?php get_header(); ?>

<header class="archive-header">
  <h1 class="archive-title"><?php the_archive_title(); ?></h1>
  <?php if (get_the_archive_description()): ?>
    <div class="archive-desc"><?php the_archive_description(); ?></div>
  <?php endif; ?>
</header>

<?php if (have_posts()): ?>
  <section class="post-list">
    <?php while (have_posts()): the_post(); ?>
      <article <?php post_class('card'); ?>>
        <a class="card-link" href="<?php the_permalink(); ?>">
          <?php if (has_post_thumbnail()) the_post_thumbnail('thumb-card',['loading'=>'lazy','class'=>'card-thumb']); ?>
          <h2 class="card-title"><?php the_title(); ?></h2>
          <p class="card-meta"><?php echo get_the_date(); ?></p>
          <p class="card-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 24)); ?></p>
        </a>
      </article>
    <?php endwhile; ?>
    <nav class="pagination">
      <?php the_posts_pagination(['mid_size'=>1,'prev_text'=>'이전','next_text'=>'다음']); ?>
    </nav>
  </section>
<?php else: ?>
  <p>이 아카이브에는 게시물이 없습니다.</p>
<?php endif; ?>

<?php get_footer(); ?>