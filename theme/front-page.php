<?php get_header(); ?>

<main class="container home-wrap">

  <section class="home-section">
    <h2 class="home-title">인기글</h2>
    <div class="post-list">
      <?php
      // 최근 30일 인기글 상위 6개(원하면 $days=0으로 전체 누적)
      $popular = bf_get_popular_posts_cached(6, 30, 30);
      if ($popular->have_posts()):
        while ($popular->have_posts()): $popular->the_post(); ?>
          <article <?php post_class('card'); ?>>
            <a class="card-link" href="<?php the_permalink(); ?>">
              <?php if (has_post_thumbnail()) {
                the_post_thumbnail('thumb-card', ['loading'=>'lazy', 'class'=>'card-thumb']);
              } ?>
              <h3 class="card-title"><?php the_title(); ?></h3>
              <p class="card-meta"><?php echo get_the_date(); ?></p>
              <p class="card-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 20)); ?></p>
            </a>
          </article>
        <?php endwhile; wp_reset_postdata();
      else:
        echo '<p>인기글이 아직 없습니다.</p>';
      endif;
      ?>
    </div>
  </section>

  <section class="home-section">
    <h2 class="home-title">최신글</h2>
    <div class="post-list">
      <?php
      $latest = new WP_Query([
        'posts_per_page'      => 9,
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
      ]);
      if ($latest->have_posts()):
        while ($latest->have_posts()): $latest->the_post(); ?>
          <article <?php post_class('card'); ?>>
            <a class="card-link" href="<?php the_permalink(); ?>">
              <?php if (has_post_thumbnail()) {
                the_post_thumbnail('thumb-card', ['loading'=>'lazy', 'class'=>'card-thumb']);
              } ?>
              <h3 class="card-title"><?php the_title(); ?></h3>
              <p class="card-meta"><?php echo get_the_date(); ?></p>
              <p class="card-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 20)); ?></p>
            </a>
          </article>
        <?php endwhile; wp_reset_postdata();
      else:
        echo '<p>게시물이 없습니다.</p>';
      endif;
      ?>
    </div>
  </section>

</main>

<?php get_footer(); ?>