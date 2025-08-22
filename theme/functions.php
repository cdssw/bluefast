<?php
// ============================
// BlueFast Theme - functions.php
// ============================

// 테마 버전(캐시 무효화용)
define('BLUEFAST_VER', '0.1.0');

/**
 * 테마 기능 등록
 */
add_action('after_setup_theme', function () {
  add_theme_support('title-tag');        // <title> 자동
  add_theme_support('post-thumbnails');  // 특성 이미지
  add_theme_support('html5', [           // HTML5 마크업
    'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'
  ]);
  add_theme_support('responsive-embeds'); // 반응형 임베드
  add_theme_support('align-wide');        // 블록 넓은 정렬
  add_theme_support('menus');             // 메뉴

  // 메뉴 위치 등록(헤더 내비)
  register_nav_menus([
    'primary' => '기본 내비게이션',
  ]);

  // 카드용 썸네일 사이즈(목록)
  add_image_size('thumb-card', 360, 200, true);
});

/**
 * 위젯(사이드바/헤더 광고) 등록
 */
add_action('widgets_init', function () {
  // 사이드바
  register_sidebar([
    'name'          => '사이드바',
    'id'            => 'sidebar-1',
    'before_widget' => '<section class="widget">',
    'after_widget'  => '</section>',
    'before_title'  => '<h3 class="widget-title">',
    'after_title'   => '</h3>',
  ]);

  // 헤더 광고
  register_sidebar([
    'name'          => '헤더 광고',
    'id'            => 'header-ad',
    'description'   => '사이트 상단(제목/메뉴 아래)에 노출되는 배너 영역입니다.',
    'before_widget' => '<div class="ad ad-header" aria-label="advertisement"><div class="ad-slot">',
    'after_widget'  => '</div></div>',
    'before_title'  => '',
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
 * 성능 최적화(불필요 head 출력 제거 및 경량화)
 */
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'rest_output_link_wp_head');

// 로그인 안 한 방문자에겐 dashicons(관리자 아이콘) 제거
add_action('wp_enqueue_scripts', function () {
  if (!is_user_logged_in()) {
    wp_dequeue_style('dashicons');
  }
}, 100);

// oEmbed 잔여 훅 제거(선택)
remove_action('rest_api_init', 'wp_oembed_register_route');
remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
remove_action('wp_head', 'wp_oembed_add_host_js');

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

/**
 * 이미지 기본 속성 최적화(lazy/async)
 */
add_filter('wp_get_attachment_image_attributes', function ($attr) {
  if (empty($attr['loading'])) {
    $attr['loading'] = 'lazy';
  }
  $attr['decoding'] = 'async';
  return $attr;
}, 10, 3);

/**
 * 스크립트 지연 로드(defer) – 필요한 스크립트 핸들을 배열에 추가해 사용
 */
add_filter('script_loader_tag', function ($tag, $handle) {
  $defer_list = ['bluefast-main']; // 예: wp_enqueue_script('bluefast-main', ... );
  if (in_array($handle, $defer_list, true)) {
    $tag = str_replace('<script ', '<script defer ', $tag);
  }
  return $tag;
}, 10, 2);

/**
 * 리소스 힌트(AdSense 등 사전 연결)
 */
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

// 집계 조건(싱글 포스트, 프리뷰/피드/검색/404 제외, 봇·편집권자 제외, 6시간 중복 방지)
// 테스트 오버라이드: ?countme=1 → 강제 집계
function bf_should_count_view($post_id) {
  if (!is_singular('post')) return false;
  if (is_preview() || is_feed() || is_search() || is_404()) return false;

  if (isset($_GET['countme']) && $_GET['countme'] === '1') return true;

  if (bf_is_bot()) return false;
  if (current_user_can('edit_post', $post_id)) return false;

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

/**
 * 인기글 쿼리(조회수 1 이상만, 동점은 최신순, 최근 N일 필터 보강)
 */
function bf_get_popular_posts($limit = 6, $days = 0) {
  $args = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => $limit,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
    'suppress_filters'    => true,
    'meta_key'            => '_bf_views',
    'meta_query'          => [[
      'key'     => '_bf_views',
      'value'   => 0,
      'compare' => '>',
      'type'    => 'NUMERIC',
    ]],
    'orderby' => [
      'meta_value_num' => 'DESC',
      'date'           => 'DESC',
    ],
  ];

  if ($days > 0) {
    // 사이트 타임존 기준으로 '최근 N일'
    $after = wp_date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
    $args['date_query'] = [[
      'column'    => 'post_date',
      'after'     => $after,
      'inclusive' => true,
    ]];
  }

  return new WP_Query($args);
}

// 캐시 버전(Transient)
function bf_get_popular_posts_cached($limit = 6, $days = 0, $minutes = 30) {
  $key = "bf_popular_{$limit}_{$days}";
  $q = get_transient($key);
  if ($q instanceof WP_Query) return $q;

  $q = bf_get_popular_posts($limit, $days);
  set_transient($key, $q, $minutes * MINUTE_IN_SECONDS);
  return $q;
}

// 인기글 캐시 무효화(글 저장/삭제 시)
function bf_flush_popular_cache() {
  global $wpdb;
  $keys = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_bf_popular_%'");
  foreach ($keys as $opt) {
    $name = str_replace('_transient_', '', $opt);
    delete_transient($name);
  }
}
add_action('save_post_post', 'bf_flush_popular_cache');
add_action('deleted_post', 'bf_flush_popular_cache');

/* =========================
 * 연관글(태그 우선, 카테고리 보조, 캐시)
 * ========================= */

// 연관글 ID 추출
function bf_get_related_post_ids($post_id, $limit = 6, $ttl_hours = 12) {
  $cache_key = "bf_rel_ids_{$post_id}_{$limit}";
  $ids = get_transient($cache_key);
  if (is_array($ids)) return $ids;

  $tag_ids = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'ids']);
  $cat_ids = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);

  $base = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'post__not_in'        => [$post_id],
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
    'fields'              => 'ids',
    'orderby'             => 'date',
    'order'               => 'DESC',
    'posts_per_page'      => $limit,
  ];

  if (!empty($tag_ids)) {
    $ids = get_posts($base + ['tag__in' => $tag_ids]);
  }

  if (empty($ids) && !empty($cat_ids)) {
    $ids = get_posts($base + ['category__in' => $cat_ids]);
  }

  if (!is_array($ids)) $ids = [];
  set_transient($cache_key, $ids, $ttl_hours * HOUR_IN_SECONDS);
  return $ids;
}

// 연관글 쿼리
function bf_get_related_posts($post_id, $limit = 6, $ttl_hours = 12) {
  $ids = bf_get_related_post_ids($post_id, $limit, $ttl_hours);
  if (empty($ids)) {
    return new WP_Query(['post__in' => [0], 'posts_per_page' => 0]);
  }
  return new WP_Query([
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'post__in'            => $ids,
    'orderby'             => 'post__in',
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
  ]);
}

// 연관글 캐시 무효화
function bf_flush_related_cache($post_id) {
  if (get_post_type($post_id) !== 'post') return;
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

/* =========================
 * 11단계: 테마 설정(관리자 페이지)
 * ========================= */

// 옵션 헬퍼
function bf_opt($key, $default = null) {
  $o = get_option('bluefast_options', []);
  return (isset($o[$key]) && $o[$key] !== '') ? $o[$key] : $default;
}
function bf_opt_bool($key, $default = false) {
  return (bool) intval(bf_opt($key, $default ? 1 : 0));
}
function bf_inarticle_positions_opt() {
  $raw = bf_opt('inarticle_positions', '2,5');
  $nums = array_filter(array_map('intval', preg_split('/[,\s]+/', (string)$raw)));
  return $nums ?: [2,5];
}

// 테마 최초 활성화 시 기본값
add_action('after_switch_theme', function () {
  $o = get_option('bluefast_options');
  if ($o !== false) return;
  add_option('bluefast_options', [
    'adsense_enabled'     => 1,
    'adsense_client'      => '',        // 예: ca-pub-XXXXXXXXXXXX
    'slot_inarticle'      => '',        // 예: 0000000001
    'slot_sidebar'        => '',        // 예: 0000000002
    'inarticle_positions' => '2,5',     // 문단 뒤 위치
    'popular_limit'       => 6,
    'popular_days'        => 30,        // 0=전체
    'popular_cache'       => 30,        // 분
  ]);
});

// 관리자 메뉴(모양 > BlueFast 설정)
add_action('admin_menu', function () {
  add_theme_page(
    'BlueFast 설정',
    'BlueFast 설정',
    'manage_options',
    'bluefast-settings',
    'bf_render_settings_page'
  );
});

// 옵션 등록/검증
add_action('admin_init', function () {
  register_setting('bluefast_options_group', 'bluefast_options', 'bf_options_sanitize');
});
function bf_options_sanitize($in) {
  $out = [];
  $out['adsense_enabled']     = empty($in['adsense_enabled']) ? 0 : 1;
  $out['adsense_client']      = sanitize_text_field($in['adsense_client'] ?? '');
  $out['slot_inarticle']      = sanitize_text_field($in['slot_inarticle'] ?? '');
  $out['slot_sidebar']        = sanitize_text_field($in['slot_sidebar'] ?? '');
  $out['inarticle_positions'] = preg_replace('/[^0-9,\s]/', '', (string)($in['inarticle_positions'] ?? '2,5'));
  $out['popular_limit']       = max(1, min(48, intval($in['popular_limit'] ?? 6)));
  $out['popular_days']        = max(0, min(3650, intval($in['popular_days'] ?? 30)));
  $out['popular_cache']       = max(1, min(720, intval($in['popular_cache'] ?? 30)));
  return $out;
}

// 설정 페이지 렌더
function bf_render_settings_page() {
  if (!current_user_can('manage_options')) return;
  $o = get_option('bluefast_options', []);
  ?>
  <div class="wrap">
    <h1>BlueFast 설정</h1>
    <form method="post" action="options.php">
      <?php settings_fields('bluefast_options_group'); ?>

      <h2>애드센스</h2>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">사용</th>
          <td><label>
            <input type="checkbox" name="bluefast_options[adsense_enabled]" value="1" <?php checked(!empty($o['adsense_enabled'])); ?>>
            애드센스 사용(ON/OFF)
          </label></td>
        </tr>
        <tr>
          <th scope="row">Client ID</th>
          <td><input type="text" name="bluefast_options[adsense_client]" value="<?php echo esc_attr($o['adsense_client'] ?? ''); ?>" class="regular-text" placeholder="ca-pub-XXXXXXXXXXXX"></td>
        </tr>
        <tr>
          <th scope="row">인아티클 Slot</th>
          <td><input type="text" name="bluefast_options[slot_inarticle]" value="<?php echo esc_attr($o['slot_inarticle'] ?? ''); ?>" class="regular-text" placeholder="예: 0000000001"></td>
        </tr>
        <tr>
          <th scope="row">사이드바 Slot</th>
          <td><input type="text" name="bluefast_options[slot_sidebar]" value="<?php echo esc_attr($o['slot_sidebar'] ?? ''); ?>" class="regular-text" placeholder="예: 0000000002"></td>
        </tr>
        <tr>
          <th scope="row">본문 삽입 위치</th>
          <td>
            <input type="text" name="bluefast_options[inarticle_positions]" value="<?php echo esc_attr($o['inarticle_positions'] ?? '2,5'); ?>" class="regular-text" placeholder="예: 2,5">
            <p class="description">문단 번호 뒤에 삽입(쉼표 구분). 예: 2,5</p>
          </td>
        </tr>
      </table>

      <h2>인기글</h2>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">표시 개수</th>
          <td><input type="number" name="bluefast_options[popular_limit]" value="<?php echo intval($o['popular_limit'] ?? 6); ?>" min="1" max="48"> 개</td>
        </tr>
        <tr>
          <th scope="row">기간(일)</th>
          <td>
            <input type="number" name="bluefast_options[popular_days]" value="<?php echo intval($o['popular_days'] ?? 30); ?>" min="0" max="3650">
            <p class="description">0이면 전체 기간(누적).</p>
          </td>
        </tr>
        <tr>
          <th scope="row">캐시(분)</th>
          <td><input type="number" name="bluefast_options[popular_cache]" value="<?php echo intval($o['popular_cache'] ?? 30); ?>" min="1" max="720"> 분</td>
        </tr>
      </table>

      <?php submit_button(); ?>
    </form>

    <hr>
    <h2>사이드바 광고 쇼트코드</h2>
    <p>위젯에서 다음을 입력하세요. slot을 비워두면 설정의 사이드바 슬롯이 적용됩니다.</p>
    <code>[ads_sidebar]</code>
  </div>
  <?php
}

/* =========================
 * AdSense 연동(옵션 기반)
 * ========================= */

// 헤더에서 AdSense 로더 1회
add_action('wp_head', function () {
  if (!bf_opt_bool('adsense_enabled')) return;
  $client = bf_opt('adsense_client', '');
  if (!$client) return;
  printf(
    '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>',
    esc_attr($client)
  );
}, 1);

// 본문 중간 자동 삽입(인아티클)
function bf_insert_incontent_ads($content) {
  if (is_admin() || is_preview() || is_feed() || !is_singular('post')) return $content;
  if (!bf_opt_bool('adsense_enabled')) return $content;

  $client = bf_opt('adsense_client', '');
  $slot   = bf_opt('slot_inarticle', '');
  if (!$client || !$slot) return $content; // 설정 미입력 시 삽입 안 함

  $ins = sprintf(
    '<ins class="adsbygoogle" style="display:block; text-align:center;" data-ad-layout="in-article" data-ad-format="fluid" data-ad-client="%s" data-ad-slot="%s"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>',
    esc_attr($client),
    esc_attr($slot)
  );
  $ad_html = '<div class="ad ad-in-article" aria-label="advertisement"><div class="ad-slot">'.$ins.'</div></div>';

  $parts = preg_split('/(<\\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
  if (!$parts || count($parts) < 2) return $content;

  $insert_after = apply_filters('bf_incontent_ad_positions', bf_inarticle_positions_opt());

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

// 사이드바 애드센스 쇼트코드: [ads_sidebar slot="0000000002" width="300" height="600"]
function bf_adsense_sidebar_shortcode($atts = []) {
  $a = shortcode_atts([
    'slot'   => '',
    'width'  => '300',
    'height' => '600',
  ], $atts, 'ads_sidebar');

  if (!bf_opt_bool('adsense_enabled')) return '';
  $client = bf_opt('adsense_client', '');
  $slot   = $a['slot'] !== '' ? $a['slot'] : bf_opt('slot_sidebar', '');
  if (!$client || !$slot) return '';

  ob_start(); ?>
  <div class="ad ad-sidebar" aria-label="advertisement">
    <div class="ad-slot">
      <ins class="adsbygoogle"
           style="display:inline-block;width:<?php echo esc_attr($a['width']); ?>px;height:<?php echo esc_attr($a['height']); ?>px"
           data-ad-client="<?php echo esc_attr($client); ?>"
           data-ad-slot="<?php echo esc_attr($slot); ?>"></ins>
      <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('ads_sidebar', 'bf_adsense_sidebar_shortcode');