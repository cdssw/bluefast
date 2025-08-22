<?php get_header(); ?>

<header class="search-header">
  <h1 class="search-title">검색 결과: “<?php echo esc_html(get_search_query()); ?>”</h1>
  <?php
  global $wp_query;
  echo '<p class="search-count">총 ' . number_format_i18n($wp_query->found_posts) . '개</p>';
  ?>
</header>

<div class="search-inline mobile-only">
  <?php get_search_form(); ?>
</div>

<?php if (have_posts()): ?>
  <section class="post-list">
    <?php while (have_posts()): the_post(); ?>
      <article <?php post_class('card'); ?>>
        <a class="card-link" href="<?php the_permalink(); ?>">
          <?php if (has_post_thumbnail()) the_post_thumbnail('thumb-card',['loading'=>'lazy','class'=>'card-thumb']); ?>
          <h2 class="card-title"><?php the_title(); ?></h2>
          <p class="card-meta"><?php echo get_the_date(); ?></p>
          <p class="card-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 28)); ?></p>
        </a>
      </article>
    <?php endwhile; ?>
    <nav class="pagination">
      <?php the_posts_pagination(['mid_size'=>1,'prev_text'=>'이전','next_text'=>'다음']); ?>
    </nav>
  </section>
<?php else: ?>
  <p>“<?php echo esc_html(get_search_query()); ?>”에 대한 결과가 없습니다.</p>
<?php endif; ?>

<?php get_footer(); ?>