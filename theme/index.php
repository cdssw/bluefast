<?php get_header(); ?>

<?php if (have_posts()): ?>
  <!-- 목록 컨테이너: 반응형 그리드 -->
  <section class="post-list">
    <?php while (have_posts()): the_post(); ?>
      <!-- post_class(): 글 유형/카테고리 등 유용한 클래스 자동 부여 -->
      <article <?php post_class('card'); ?>>
        <a class="card-link" href="<?php the_permalink(); ?>">
          <?php if (has_post_thumbnail()): ?>
            <!-- 'thumb-card'는 functions.php에서 정의(360x200 자르기). lazy 로딩으로 성능 개선 -->
            <?php the_post_thumbnail('thumb-card', [
              'loading' => 'lazy',
              'class'   => 'card-thumb'
            ]); ?>
          <?php endif; ?>

          <h2 class="card-title"><?php the_title(); ?></h2>
          <p class="card-meta"><?php echo get_the_date(); ?></p>

          <!-- 요약은 글의 발췌가 없으면 본문에서 자동 추출 -->
          <p class="card-excerpt">
            <?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 24)); ?>
          </p>
        </a>
      </article>
    <?php endwhile; ?>

    <!-- 페이지네이션: 현재 쿼리 기준으로 이전/다음 페이지 링크 출력 -->
    <nav class="pagination">
      <?php the_posts_pagination([
        'mid_size'  => 1,        // 현재 페이지 주변에 표시할 페이지 수
        'prev_text' => '이전',
        'next_text' => '다음',
      ]); ?>
    </nav>
  </section>
<?php else: ?>
  <p>게시물이 없습니다.</p>
<?php endif; ?>

<?php get_footer(); ?>