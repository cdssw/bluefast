<?php
// 공통 헤더
get_header();
?>

<div class="single-wrap"><!-- 본문/사이드바 2열 래퍼 -->
  <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article <?php post_class('single-post'); ?>>

      <header class="single-header">
        <h1 class="single-title"><?php the_title(); ?></h1>

        <p class="single-meta">
          <!-- 날짜/작성자/카테고리 -->
          <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo get_the_date(); ?></time>
          · 글쓴이 <?php the_author(); ?>
          <?php
          // 카테고리 1개만 출력(필요하면 the_category(', ')로 전부 출력 가능)
          $cats = get_the_category();
          if (!empty($cats)) {
            echo ' · 카테고리 ' . esc_html($cats[0]->name);
          }
          ?>
        </p>

        <?php if (has_post_thumbnail()): ?>
          <figure class="single-thumb">
            <?php
            // 대표 이미지: 첫 화면 가독성을 위해 eager + fetchpriority 힌트
            the_post_thumbnail('large', [
              'loading'       => 'eager',
              'fetchpriority' => 'high',
              'class'         => 'single-thumb-img'
            ]);
            ?>
          </figure>
        <?php endif; ?>
      </header>

      <div class="single-content">
        <?php
        // 본문 출력(블록 에디터/클래식 모두 대응)
        the_content();

        // <!--nextpage--> 사용 시 페이지네이션
        wp_link_pages([
          'before' => '<nav class="page-links"><span class="label">페이지</span>',
          'after'  => '</nav>',
        ]);
        ?>
      </div>

      <nav class="post-nav">
        <div class="prev"><?php previous_post_link('%link', '← 이전 글'); ?></div>
        <div class="next"><?php next_post_link('%link', '다음 글 →'); ?></div>
      </nav>

      <?php
      // === 연관글 섹션 ===
      $rel_q = bf_get_related_posts(get_the_ID(), 6, 12); // 최대 6개, 12시간 캐시
      ?>
      <section class="related-section">
        <h2 class="related-title">연관글</h2>
        <?php if ($rel_q->have_posts()): ?>
          <div class="post-list"><!-- 기존 카드 그리드 재사용 -->
            <?php while ($rel_q->have_posts()): $rel_q->the_post(); ?>
              <article <?php post_class('card'); ?>>
                <a class="card-link" href="<?php the_permalink(); ?>">
                  <?php if (has_post_thumbnail()) {
                    the_post_thumbnail('thumb-card', ['loading' => 'lazy', 'class' => 'card-thumb']);
                  } ?>
                  <h3 class="card-title"><?php the_title(); ?></h3>
                  <p class="card-meta"><?php echo get_the_date(); ?></p>
                  <p class="card-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 20)); ?></p>
                </a>
              </article>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        <?php else: ?>
          <p class="related-empty">연관글이 없습니다.</p>
        <?php endif; ?>
      </section>

    </article>
  <?php endwhile; else: ?>
    <p>글을 찾을 수 없습니다.</p>
  <?php endif; ?>

  <aside class="sidebar">
    <?php
    // 사이드바 위젯 출력(없으면 안내 문구)
    if (is_active_sidebar('sidebar-1')) {
      dynamic_sidebar('sidebar-1');
    } else {
      echo '<section class="widget"><h3 class="widget-title">사이드바</h3><p>관리자 > 모양 > 위젯에서 위젯을 추가하세요.</p></section>';
    }
    ?>
  </aside>
</div>

<?php
// === Article 구조화 데이터(JSON-LD) ===
$headline  = wp_strip_all_tags(get_the_title());
$author    = get_the_author();
$published = get_the_date('c');
$modified  = get_the_modified_date('c');
$permalink = get_permalink();
$image     = has_post_thumbnail() ? get_the_post_thumbnail_url(null, 'full') : '';

$ld = [
  '@context'         => 'https://schema.org',
  '@type'            => 'Article',
  'headline'         => $headline,
  'datePublished'    => $published,
  'dateModified'     => $modified,
  'author'           => [ '@type' => 'Person', 'name' => $author ],
  'mainEntityOfPage' => $permalink,
];
if ($image) { $ld['image'] = [ $image ]; }

echo '<script type="application/ld+json">' .
     wp_json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
     '</script>';
?>

<?php
// 공통 푸터
get_footer();