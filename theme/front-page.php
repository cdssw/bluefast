<?php get_header(); ?>

<div class="home-grid">
  <div class="home-main">
    <div class="search-inline mobile-only">
      <?php get_search_form(); ?>
    </div>    
    <section class="home-section">
      <h2 class="home-title">인기글</h2>
      <div class="post-list">
        <?php
        $limit=(int)bf_opt('popular_limit',6);
        $days=(int)bf_opt('popular_days',30);
        $cache=(int)bf_opt('popular_cache',30);
        $popular=bf_get_popular_posts_cached($limit,$days,$cache);
        if($popular->have_posts()):
          while($popular->have_posts()): $popular->the_post(); ?>
            <article <?php post_class('card'); ?>>
              <a class="card-link" href="<?php the_permalink(); ?>">
                <?php if(has_post_thumbnail()) the_post_thumbnail('thumb-card',['loading'=>'lazy','class'=>'card-thumb']); ?>
                <h3 class="card-title"><?php the_title(); ?></h3>
                <p class="card-meta"><?php echo get_the_date(); ?></p>
                <p class="card-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(),20)); ?></p>
              </a>
            </article>
          <?php endwhile; wp_reset_postdata();
        else:
          echo '<p>인기글이 아직 없습니다.</p>';
        endif; ?>
      </div>
    </section>

    <section class="home-section">
      <h2 class="home-title">최신글</h2>
      <div class="post-list">
        <?php
        $latest = new WP_Query([
          'posts_per_page'=>9,'post_status'=>'publish',
          'ignore_sticky_posts'=>true,'no_found_rows'=>true,
        ]);
        if($latest->have_posts()):
          while($latest->have_posts()): $latest->the_post(); ?>
            <article <?php post_class('card'); ?>>
              <a class="card-link" href="<?php the_permalink(); ?>">
                <?php if(has_post_thumbnail()) the_post_thumbnail('thumb-card',['loading'=>'lazy','class'=>'card-thumb']); ?>
                <h3 class="card-title"><?php the_title(); ?></h3>
                <p class="card-meta"><?php echo get_the_date(); ?></p>
                <p class="card-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(),20)); ?></p>
              </a>
            </article>
          <?php endwhile; wp_reset_postdata();
        else:
          echo '<p>게시물이 없습니다.</p>';
        endif; ?>
      </div>
    </section>
  </div>

  <aside class="sidebar">
    <!-- 데스크톱 사이드바 검색(카테고리 위) -->
    <section class="widget widget-search-desktop">
      <?php get_search_form(); ?>
    </section>
    <section class="widget">
      <h3 class="widget-title">카테고리</h3>
      <ul class="cat-list">
        <?php wp_list_categories(['title_li'=>'','orderby'=>'name','show_count'=>false,'hide_empty'=>false]); ?>
      </ul>
    </section>
  </aside>
</div>

<?php get_footer(); ?>