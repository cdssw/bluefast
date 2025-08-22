<?php get_header(); ?>

<div class="home-grid">
  <div class="home-main">
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
      <p>게시물이 없습니다.</p>
    <?php endif; ?>
  </div>

  <aside class="sidebar">
    <section class="widget">
      <h3 class="widget-title">카테고리</h3>
      <ul class="cat-list">
        <?php wp_list_categories(['title_li'=>'','orderby'=>'name','show_count'=>false,'hide_empty'=>false]); ?>
      </ul>
    </section>
  </aside>
</div>

<?php get_footer(); ?>