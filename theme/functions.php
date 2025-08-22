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

  register_sidebar([
    'name'          => '헤더 광고',
    'id'            => 'header-ad',
    'description'   => '사이트 상단(제목/메뉴 아래)에 노출되는 배너 영역입니다.',
    'before_widget' => '<div class="ad ad-header" aria-label="advertisement"><div class="ad-slot">',
    'after_widget'  => '</div></div>',
    'before_title'  => '', // 제목 숨김(광고엔 보통 타이틀을 쓰지 않음)
    'after_title'   => '',
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

/**
 * 연관글 ID 추출(태그 우선, 태그 없으면 카테고리)
 * - 결과는 글 ID 배열로 캐싱(기본 12시간)
 * - 캐시를 쓰는 이유: 매 조회마다 tax_query를 돌리지 않기 위해
 */
function bf_get_related_post_ids($post_id, $limit = 6, $ttl_hours = 12) {
  $cache_key = "bf_rel_ids_{$post_id}_{$limit}";
  $ids = get_transient($cache_key);
  if (is_array($ids)) return $ids;

  // 현재 글의 태그/카테고리
  $tag_ids = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'ids']);
  $cat_ids = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);

  // 기본 쿼리 공통 파라미터
  $base = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'post__not_in'        => [$post_id],
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
    'fields'              => 'ids',        // ID만 받기(가벼움)
    'orderby'             => 'date',
    'order'               => 'DESC',
    'posts_per_page'      => $limit,
  ];

  // 1순위: 태그가 있으면 태그 기준
  if (!empty($tag_ids)) {
    $ids = get_posts($base + [
      'tag__in' => $tag_ids,
    ]);
  }

  // 태그 결과가 비었거나 태그 자체가 없으면 카테고리 기준으로 보조
  if (empty($ids) && !empty($cat_ids)) {
    $ids = get_posts($base + [
      'category__in' => $cat_ids,
    ]);
  }

  // 결국 아무것도 없으면 빈 배열
  if (!is_array($ids)) $ids = [];

  // 캐싱(기본 12시간)
  set_transient($cache_key, $ids, $ttl_hours * HOUR_IN_SECONDS);
  return $ids;
}

/**
 * 연관글 쿼리(WP_Query 리턴)
 * - 캐싱된 ID를 바탕으로 실제 카드 목록을 그릴 수 있게 쿼리 구성
 */
function bf_get_related_posts($post_id, $limit = 6, $ttl_hours = 12) {
  $ids = bf_get_related_post_ids($post_id, $limit, $ttl_hours);
  if (empty($ids)) {
    return new WP_Query(['post__in' => [0], 'posts_per_page' => 0]); // 빈 쿼리
  }
  return new WP_Query([
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'post__in'            => $ids,       // 캐시된 ID들만
    'orderby'             => 'post__in', // 캐시된 순서 유지
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
  ]);
}

/**
 * 연관글 캐시 무효화(해당 글 저장/삭제 시)
 */
function bf_flush_related_cache($post_id) {
  if (get_post_type($post_id) !== 'post') return;
  // limit 값이 여러 개일 수 있으므로 패턴으로 삭제
  global $wpdb;
  $like = $wpdb->esc_like("_transient_bf_rel_ids_{$post_id}_");
  $keys = $wpdb->get_col($wpdb->prepare(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
    $like . '%'
  ));
  foreach ($keys as $opt) {
    $name = str_replace('_transient_', '', $opt);
    delete_transient($name);
  }
}
add_action('save_post_post', 'bf_flush_related_cache');
add_action('deleted_post', 'bf_flush_related_cache');


// AdSense 사용 토글 + 클라이언트 ID 설정(반드시 교체)
if (!defined('BF_ADSENSE_ENABLED')) define('BF_ADSENSE_ENABLED', true);
if (!defined('BF_ADSENSE_CLIENT'))  define('BF_ADSENSE_CLIENT', 'ca-pub-여기에-본인-ID'); // 교체!

// (선택) 슬롯 상수(관리 편의)
if (!defined('BF_SLOT_INARTICLE')) define('BF_SLOT_INARTICLE', '0000000001'); // ← 교체(인아티클)
if (!defined('BF_SLOT_SIDEBAR'))   define('BF_SLOT_SIDEBAR', '0000000002');   // ← 교체(사이드바)

// 헤더에 AdSense 로더 1회 삽입
add_action('wp_head', function () {
  if (!BF_ADSENSE_ENABLED) return;
  printf(
    '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>',
    esc_attr(BF_ADSENSE_CLIENT)
  );
}, 1);

// 본문 중간 애드센스 자동 삽입(2, 5번째 문단 뒤)
function bf_insert_incontent_ads($content) {
  if (!BF_ADSENSE_ENABLED) return $content;
  if (!is_singular('post') || is_admin() || is_preview() || is_feed()) return $content;

  // 관리자에게 숨기고 싶으면 주석 해제
  // if (current_user_can('edit_posts')) return $content;

  // 인아티클(Fluid) 유닛
  $ins = sprintf(
    '<ins class="adsbygoogle" style="display:block; text-align:center;" data-ad-layout="in-article" data-ad-format="fluid" data-ad-client="%s" data-ad-slot="%s"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>',
    esc_attr(BF_ADSENSE_CLIENT),
    esc_attr(BF_SLOT_INARTICLE)
  );

  // CLS 방지용 래퍼(최소 높이 예약)
  $ad_html = '<div class="ad ad-in-article" aria-label="advertisement"><div class="ad-slot">'.$ins.'</div></div>';

  // </p> 기준 분할 후 지정 위치 뒤 삽입
  $parts = preg_split('/(<\\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
  if (!$parts || count($parts) < 2) return $content;

  $insert_after = apply_filters('bf_incontent_ad_positions', [2, 5]); // 위치 조정 가능

  $result = '';
  $pcount = 0;
  for ($i=0; $i<count($parts); $i++) {
    $result .= $parts[$i];
    if (stripos($parts[$i], '</p>') !== false) {
      $pcount++;
      if (in_array($pcount, $insert_after, true)) {
        $result .= $ad_html;
      }
    }
  }
  return $result;
}
add_filter('the_content', 'bf_insert_incontent_ads', 110);

// [ads_sidebar slot="0000000002" width="300" height="600"]
function bf_adsense_sidebar_shortcode($atts = []) {
  $a = shortcode_atts([
    'slot'   => '0000000002', // 사이드바 슬롯 ID
    'width'  => '300',
    'height' => '600',
  ], $atts, 'ads_sidebar');

  // 출력 버퍼 시작
  ob_start(); ?>
  <div class="ad ad-sidebar" aria-label="advertisement">
    <div class="ad-slot">
      <ins class="adsbygoogle"
           style="display:inline-block;width:<?php echo esc_attr($a['width']); ?>px;height:<?php echo esc_attr($a['height']); ?>px"
           data-ad-client="<?php echo esc_attr(BF_ADSENSE_CLIENT); ?>"
           data-ad-slot="<?php echo esc_attr($a['slot']); ?>"></ins>
      <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('ads_sidebar', 'bf_adsense_sidebar_shortcode');

// 로그인 안 한 방문자에겐 dashicons(관리자 아이콘) 제거
add_action('wp_enqueue_scripts', function () {
  if (!is_user_logged_in()) {
    wp_dequeue_style('dashicons');
  }
}, 100);

// oEmbed 잔여 훅 제거(임베드 자동 탐지 최소화)
remove_action('rest_api_init', 'wp_oembed_register_route');
remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
remove_action('wp_head', 'wp_oembed_add_host_js');

add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
  // 히어로처럼 명시적으로 eager 지정한 이미지는 유지(single에서 이미 처리)
  if (empty($attr['loading'])) {
    $attr['loading'] = 'lazy';
  }
  $attr['decoding'] = 'async';
  return $attr;
}, 10, 3);

add_filter('script_loader_tag', function ($tag, $handle) {
  // 여기 배열에 ‘지연 로드할’ 스크립트 핸들을 등록하세요.
  $defer_list = ['bluefast-main']; // 예: wp_enqueue_script('bluefast-main', ... );
  if (in_array($handle, $defer_list, true)) {
    $tag = str_replace('<script ', '<script defer ', $tag);
  }
  return $tag;
}, 10, 2);

add_filter('wp_resource_hints', function ($hints, $rel) {
  if ($rel === 'preconnect') {
    $hints[] = 'https://pagead2.googlesyndication.com';
    $hints[] = 'https://googleads.g.doubleclick.net';
  }
  if ($rel === 'dns-prefetch') {
    $hints[] = '//pagead2.googlesyndication.com';
    $hints[] = '//googleads.g.doubleclick.net';
  }
  return array_unique($hints);
}, 10, 2);