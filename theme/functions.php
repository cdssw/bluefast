<?php
// 테마 버전(캐시 무효화 등에 사용)
define('BLUEFAST_VER', '0.1.0');

/**
 * 테마 기능 등록
 */
add_action('after_setup_theme', function () {
  add_theme_support('title-tag');        // <title> 자동 관리
  add_theme_support('post-thumbnails');  // 특성 이미지
  add_theme_support('html5', [           // HTML5 마크업
    'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'
  ]);
  add_theme_support('responsive-embeds'); // 반응형 임베드
  add_theme_support('align-wide');        // 블록 넓은 정렬
  add_theme_support('menus');             // 메뉴 지원

  // 메뉴 위치 등록(헤더 내비)
  register_nav_menus([
    'primary' => '기본 내비게이션',
  ]);

  // 카드용 썸네일 사이즈(목록에서 사용)
  add_image_size('thumb-card', 360, 200, true);
});

/**
 * 위젯(사이드바) 등록
 */
add_action('widgets_init', function () {
  register_sidebar([
    'name'          => '사이드바',
    'id'            => 'sidebar-1',
    'before_widget' => '<section class="widget">',
    'after_widget'  => '</section>',
    'before_title'  => '<h3 class="widget-title">',
    'after_title'   => '</h3>',
  ]);
});

/**
 * 스타일 로딩
 */
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style('bluefast-style', get_stylesheet_uri(), [], BLUEFAST_VER);
});

/**
 * 성능 최적화(선택): 불필요한 head 출력 제거
 */
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'rest_output_link_wp_head');

/**
 * (선택) 관리자 알림으로 로드 확인
 */
add_action('admin_notices', function () {
  echo '<div class="notice notice-success"><p>BlueFast: functions.php 로드 완료</p></div>';
});

/**
 * 검색어 하이라이트(검색 결과의 제목/요약에 <mark>)
 */
function bf_highlight_search_terms($text) {
  if (!is_search()) return $text;
  $q = trim(get_search_query());
  if ($q === '') return $text;

  // 공백으로 분리된 키워드 각각 강조(대소문자/한글 대응)
  $keys = array_filter(preg_split('/\s+/', $q));
  foreach ($keys as $k) {
    $k = preg_quote($k, '/');
    $text = preg_replace('/(' . $k . ')/iu', '<mark>$1</mark>', $text);
  }
  return $text;
}
add_filter('the_title', 'bf_highlight_search_terms', 99);
add_filter('get_the_excerpt', 'bf_highlight_search_terms', 99);

/* =========================
 * 조회수 집계(봇/관리자/중복 최소화)
 * ========================= */

// 간단한 봇 판별
function bf_is_bot() {
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  if ($ua === '') return true;
  $bots = '(bot|crawl|spider|slurp|bingpreview|mediapartners-google)';
  return (bool) preg_match("/{$bots}/i", $ua);
}

// 조회수 +1
function bf_increment_post_views($post_id) {
  $key   = '_bf_views';
  $views = (int) get_post_meta($post_id, $key, true);
  update_post_meta($post_id, $key, $views + 1);
}

// 카운트 조건 체크(싱글 포스트, 미리보기/피드/검색 아님, 봇/편집권자 제외, 중복방지)
function bf_should_count_view($post_id) {
  if (!is_singular('post')) return false;
  if (is_preview() || is_feed() || is_search() || is_404()) return false;

  // 테스트 오버라이드: ?countme=1 붙이면 무조건 카운트
  if (isset($_GET['countme']) && $_GET['countme'] === '1') {
    return true;
  }

  if (bf_is_bot()) return false;
  if (current_user_can('edit_post', $post_id)) return false; // 관리자/에디터 제외

  // 6시간 중복 방지(쿠키)
  $cookie = "bf_seen_{$post_id}";
  if (!empty($_COOKIE[$cookie])) return false;
  setcookie($cookie, '1', time() + 6 * HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
  return true;
}

// 싱글 글 접근 시 카운트
add_action('template_redirect', function () {
  if (is_singular('post')) {
    $pid = get_queried_object_id();
    if ($pid && bf_should_count_view($pid)) {
      bf_increment_post_views($pid);
    }
  }
});

// 인기글 쿼리(조회수 1 이상만, 동점은 최신순, 최근 N일 필터)
function bf_get_popular_posts($limit = 6, $days = 0) {
  $args = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => $limit,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
    'suppress_filters'    => true,

    // 정렬: 조회수 내림차순 → 동률이면 최신순
    'meta_key' => '_bf_views',
    'orderby'  => [
      'meta_value_num' => 'DESC',
      'date'           => 'DESC',
    ],

    // 핵심: 조회수(meta_value) 숫자 0 초과만 포함
    'meta_query' => [[
      'key'     => '_bf_views',
      'value'   => 0,
      'compare' => '>',
      'type'    => 'NUMERIC',
    ]],
  ];

  if ($days > 0) {
    // 최근 N일(사이트 타임존 기준)만 포함
    $after = wp_date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
    $args['date_query'] = [[
      'column'    => 'post_date',
      'after'     => $after,
      'inclusive' => true,
    ]];
  }

  return new WP_Query($args);
}

// 캐시 버전(홈 화면에서 사용 권장)
function bf_get_popular_posts_cached($limit = 6, $days = 0, $minutes = 30) {
  $key = "bf_popular_{$limit}_{$days}";
  $q = get_transient($key);
  if ($q instanceof WP_Query) return $q;

  $q = bf_get_popular_posts($limit, $days);
  // WP_Query 객체 직렬화 가능. 단, 너무 긴 TTL은 피하세요.
  set_transient($key, $q, $minutes * MINUTE_IN_SECONDS);
  return $q;
}

// 인기글 캐시 무효화(글 저장/삭제 시)
add_action('save_post_post', function () { bf_flush_popular_cache(); });
add_action('deleted_post', function () { bf_flush_popular_cache(); });
function bf_flush_popular_cache() {
  global $wpdb;
  $keys = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_bf_popular_%'");
  foreach ($keys as $opt) {
    $name = str_replace('_transient_', '', $opt);
    delete_transient($name);
  }
}

// 관리자 글 목록에 조회수 컬럼 추가
add_filter('manage_posts_columns', function($cols){
  $cols['bf_views'] = '조회수';
  return $cols;
});
add_action('manage_posts_custom_column', function($col, $post_id){
  if ($col === 'bf_views') {
    echo number_format_i18n((int) get_post_meta($post_id, '_bf_views', true));
  }
}, 10, 2);
add_filter('manage_edit-post_sortable_columns', function($cols){
  $cols['bf_views'] = 'bf_views';
  return $cols;
});
add_action('pre_get_posts', function($q){
  if (!is_admin() || !$q->is_main_query()) return;
  if ($q->get('orderby') === 'bf_views') {
    $q->set('meta_key', '_bf_views');
    $q->set('orderby', 'meta_value_num');
  }
});

// 새 글 발행 시 조회수 메타가 없으면 0으로 초기화
add_action('publish_post', function($post_id){
  if ('' === get_post_meta($post_id, '_bf_views', true)) {
    update_post_meta($post_id, '_bf_views', 0);
  }
});