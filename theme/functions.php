<?php
// ============================
// BlueFast Theme - functions.php
// ============================
define('BLUEFAST_VER', '0.1.0');

/* 테마 기능 등록 */
add_action('after_setup_theme', function () {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
  add_theme_support('responsive-embeds');
  add_theme_support('align-wide');
  add_theme_support('menus');

  register_nav_menus(['primary' => '기본 내비게이션']);
  add_image_size('thumb-card', 360, 200, true);
});

/* 위젯(헤더 광고만 유지) */
add_action('widgets_init', function () {
  register_sidebar([
    'name' => '헤더 광고','id' => 'header-ad','description'=>'상단(제목/메뉴 아래)',
    'before_widget'=>'<div class="ad ad-header" aria-label="advertisement"><div class="ad-slot">',
    'after_widget'=>'</div></div>','before_title'=>'','after_title'=>'',
  ]);
});

/* 스타일 로딩 */
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style('bluefast-style', get_stylesheet_uri(), [], BLUEFAST_VER);
});

/* 성능 최적화 */
remove_action('wp_head','print_emoji_detection_script',7);
remove_action('wp_print_styles','print_emoji_styles');
remove_action('wp_head','wp_oembed_add_discovery_links');
remove_action('wp_head','rest_output_link_wp_head');
add_action('wp_enqueue_scripts', function () { if (!is_user_logged_in()) wp_dequeue_style('dashicons'); }, 100);
remove_action('rest_api_init', 'wp_oembed_register_route');
remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
remove_action('wp_head', 'wp_oembed_add_host_js');

/* 관리자 로드 확인(원치 않으면 주석) */
add_action('admin_notices', function () {
  echo '<div class="notice notice-success"><p>BlueFast: functions.php 로드 완료</p></div>';
});

/* 검색어 하이라이트 */
function bf_highlight_search_terms($text){
  if(!is_search()) return $text;
  $q = trim(get_search_query()); if($q==='') return $text;
  $keys = array_filter(preg_split('/\s+/', $q));
  foreach($keys as $k){ $k=preg_quote($k,'/'); $text=preg_replace('/('.$k.')/iu','<mark>$1</mark>',$text); }
  return $text;
}
add_filter('the_title','bf_highlight_search_terms',99);
add_filter('get_the_excerpt','bf_highlight_search_terms',99);

/* 이미지 lazy/async */
add_filter('wp_get_attachment_image_attributes', function($attr){ if(empty($attr['loading'])) $attr['loading']='lazy'; $attr['decoding']='async'; return $attr; },10,3);

/* 스크립트 defer(필요 시 핸들 추가) */
add_filter('script_loader_tag', function($tag,$handle){ $defer=['bluefast-main']; if(in_array($handle,$defer,true)){ $tag=str_replace('<script ','<script defer ',$tag);} return $tag; },10,2);

/* 리소스 힌트 */
add_filter('wp_resource_hints', function($h,$rel){
  if($rel==='preconnect'){ $h[]='https://pagead2.googlesyndication.com'; $h[]='https://googleads.g.doubleclick.net'; }
  if($rel==='dns-prefetch'){ $h[]='//pagead2.googlesyndication.com'; $h[]='//googleads.g.doubleclick.net'; }
  return array_unique($h);
},10,2);

/* ===== 조회수 집계 ===== */
function bf_is_bot(){ $ua=$_SERVER['HTTP_USER_AGENT']??''; if($ua==='') return true; return (bool)preg_match('/(bot|crawl|spider|slurp|bingpreview|mediapartners-google)/i',$ua); }
function bf_increment_post_views($pid){ $k='_bf_views'; $v=(int)get_post_meta($pid,$k,true); update_post_meta($pid,$k,$v+1); }
function bf_should_count_view($pid){
  if(!is_singular('post')) return false;
  if(is_preview()||is_feed()||is_search()||is_404()) return false;
  if(isset($_GET['countme']) && $_GET['countme']==='1') return true;
  if(bf_is_bot()) return false;
  if(current_user_can('edit_post',$pid)) return false;
  $cookie="bf_seen_{$pid}"; if(!empty($_COOKIE[$cookie])) return false;
  setcookie($cookie,'1', time()+6*HOUR_IN_SECONDS, COOKIEPATH?:'/', COOKIE_DOMAIN?:'', is_ssl(), true);
  return true;
}
add_action('template_redirect', function(){ if(is_singular('post')){ $pid=get_queried_object_id(); if($pid && bf_should_count_view($pid)) bf_increment_post_views($pid); }});

/* 인기글 쿼리(조회수>0, 동점 최신, 최근 N일) */
function bf_get_popular_posts($limit=6,$days=0){
  $args=[
    'post_type'=>'post','post_status'=>'publish','posts_per_page'=>$limit,
    'ignore_sticky_posts'=>true,'no_found_rows'=>true,'suppress_filters'=>true,
    'meta_key'=>'_bf_views',
    'meta_query'=>[[ 'key'=>'_bf_views','value'=>0,'compare'=>'>','type'=>'NUMERIC' ]],
    'orderby'=>['meta_value_num'=>'DESC','date'=>'DESC'],
  ];
  if($days>0){
    $after=wp_date('Y-m-d H:i:s', time()-($days*DAY_IN_SECONDS));
    $args['date_query']=[[ 'column'=>'post_date','after'=>$after,'inclusive'=>true ]];
  }
  return new WP_Query($args);
}
function bf_get_popular_posts_cached($limit=6,$days=0,$minutes=30){
  $key="bf_popular_{$limit}_{$days}";
  $q=get_transient($key);
  if($q instanceof WP_Query) return $q;
  $q=bf_get_popular_posts($limit,$days);
  set_transient($key,$q,$minutes*MINUTE_IN_SECONDS);
  return $q;
}
function bf_flush_popular_cache(){
  global $wpdb;
  $keys=$wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_bf_popular_%'");
  foreach($keys as $opt){ $name=str_replace('_transient_','',$opt); delete_transient($name); }
}
add_action('save_post_post','bf_flush_popular_cache');
add_action('deleted_post','bf_flush_popular_cache');

/* ===== 연관글(태그 우선, 카테고리 보조, 캐시) ===== */
function bf_get_related_post_ids($pid,$limit=6,$ttl_hours=12){
  $ck="bf_rel_ids_{$pid}_{$limit}";
  $ids=get_transient($ck); if(is_array($ids)) return $ids;
  $tags=wp_get_post_terms($pid,'post_tag',['fields'=>'ids']);
  $cats=wp_get_post_terms($pid,'category',['fields'=>'ids']);
  $base=['post_type'=>'post','post_status'=>'publish','post__not_in'=>[$pid],
    'ignore_sticky_posts'=>true,'no_found_rows'=>true,'fields'=>'ids',
    'orderby'=>'date','order'=>'DESC','posts_per_page'=>$limit];
  if(!empty($tags)) $ids=get_posts($base+['tag__in'=>$tags]);
  if(empty($ids) && !empty($cats)) $ids=get_posts($base+['category__in'=>$cats]);
  if(!is_array($ids)) $ids=[];
  set_transient($ck,$ids,$ttl_hours*HOUR_IN_SECONDS);
  return $ids;
}
function bf_get_related_posts($pid,$limit=6,$ttl_hours=12){
  $ids=bf_get_related_post_ids($pid,$limit,$ttl_hours);
  if(empty($ids)) return new WP_Query(['post__in'=>[0],'posts_per_page'=>0]);
  return new WP_Query([
    'post_type'=>'post','post_status'=>'publish','post__in'=>$ids,'orderby'=>'post__in',
    'ignore_sticky_posts'=>true,'no_found_rows'=>true,
  ]);
}
function bf_flush_related_cache($pid){
  if(get_post_type($pid)!=='post') return;
  global $wpdb; $like=$wpdb->esc_like("_transient_bf_rel_ids_{$pid}_");
  $keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like.'%'));
  foreach($keys as $opt){ $name=str_replace('_transient_','',$opt); delete_transient($name); }
}
add_action('save_post_post','bf_flush_related_cache');
add_action('deleted_post','bf_flush_related_cache');

/* ===== 11단계: 테마 설정(관리자 페이지) – 사이드바 슬롯 제거, 하단 슬롯 추가 ===== */
function bf_opt($k,$d=null){ $o=get_option('bluefast_options',[]); return (isset($o[$k]) && $o[$k] !== '') ? $o[$k] : $d; }
function bf_opt_bool($k,$d=false){ return (bool)intval(bf_opt($k,$d?1:0)); }
function bf_inarticle_positions_opt(){ $raw=bf_opt('inarticle_positions','2,5'); $nums=array_filter(array_map('intval',preg_split('/[,\s]+/',$raw))); return $nums?:[2,5]; }

add_action('after_switch_theme', function(){
  $o=get_option('bluefast_options'); if($o!==false) return;
  add_option('bluefast_options',[
    'adsense_enabled'=>1,
    'adsense_client'=>'',
    'slot_inarticle'=>'',
    'slot_bottom'=>'',             // 본문 하단 슬롯
    'inarticle_positions'=>'2,5',
    'popular_limit'=>6,'popular_days'=>30,'popular_cache'=>30,
  ]);
});
add_action('admin_menu', function(){
  add_theme_page('BlueFast 설정','BlueFast 설정','manage_options','bluefast-settings','bf_render_settings_page');
});
add_action('admin_init', function(){
  register_setting('bluefast_options_group','bluefast_options','bf_options_sanitize');
});
function bf_options_sanitize($in){
  $out=[];
  $out['adsense_enabled']=empty($in['adsense_enabled'])?0:1;
  $out['adsense_client']=sanitize_text_field($in['adsense_client']??'');
  $out['slot_inarticle']=sanitize_text_field($in['slot_inarticle']??'');
  $out['slot_bottom']   =sanitize_text_field($in['slot_bottom']??'');
  $out['inarticle_positions']=preg_replace('/[^0-9,\s]/','',(string)($in['inarticle_positions']??'2,5'));
  $out['popular_limit']=max(1,min(48,intval($in['popular_limit']??6)));
  $out['popular_days']=max(0,min(3650,intval($in['popular_days']??30)));
  $out['popular_cache']=max(1,min(720,intval($in['popular_cache']??30)));
  return $out;
}
function bf_render_settings_page(){
  if(!current_user_can('manage_options')) return;
  $o=get_option('bluefast_options',[]);
  ?>
  <div class="wrap">
    <h1>BlueFast 설정</h1>
    <form method="post" action="options.php">
      <?php settings_fields('bluefast_options_group'); ?>

      <h2>애드센스</h2>
      <table class="form-table" role="presentation">
        <tr><th scope="row">사용</th>
          <td><label><input type="checkbox" name="bluefast_options[adsense_enabled]" value="1" <?php checked(!empty($o['adsense_enabled'])); ?>> 애드센스 사용(ON/OFF)</label></td></tr>
        <tr><th scope="row">Client ID</th>
          <td><input type="text" name="bluefast_options[adsense_client]" value="<?php echo esc_attr($o['adsense_client']??''); ?>" class="regular-text" placeholder="ca-pub-XXXXXXXXXXXX"></td></tr>
        <tr><th scope="row">인아티클 Slot</th>
          <td><input type="text" name="bluefast_options[slot_inarticle]" value="<?php echo esc_attr($o['slot_inarticle']??''); ?>" class="regular-text" placeholder="예: 0000000001"></td></tr>
        <tr><th scope="row">하단 Slot</th>
          <td><input type="text" name="bluefast_options[slot_bottom]" value="<?php echo esc_attr($o['slot_bottom']??''); ?>" class="regular-text" placeholder="예: 0000000003"></td></tr>
        <tr><th scope="row">본문 삽입 위치</th>
          <td><input type="text" name="bluefast_options[inarticle_positions]" value="<?php echo esc_attr($o['inarticle_positions']??'2,5'); ?>" class="regular-text" placeholder="예: 2,5">
            <p class="description">문단 번호 뒤 삽입(쉼표 구분)</p></td></tr>
      </table>

      <h2>인기글</h2>
      <table class="form-table" role="presentation">
        <tr><th scope="row">표시 개수</th><td><input type="number" name="bluefast_options[popular_limit]" value="<?php echo intval($o['popular_limit']??6); ?>" min="1" max="48"> 개</td></tr>
        <tr><th scope="row">기간(일)</th><td><input type="number" name="bluefast_options[popular_days]" value="<?php echo intval($o['popular_days']??30); ?>" min="0" max="3650"><p class="description">0이면 전체 기간(누적)</p></td></tr>
        <tr><th scope="row">캐시(분)</th><td><input type="number" name="bluefast_options[popular_cache]" value="<?php echo intval($o['popular_cache']??30); ?>" min="1" max="720"> 분</td></tr>
      </table>

      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

/* AdSense 로더(헤더 1회) */
add_action('wp_head', function () {
  if (!bf_opt_bool('adsense_enabled')) return;
  $client = bf_opt('adsense_client',''); if(!$client) return;
  printf('<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>', esc_attr($client));
}, 1);

// 짧은 글(≤4문단)은 인아티클 0개, 5~7문단은 1개(2번째 뒤), 8문단 이상은 2개(2·5)
function bf_insert_incontent_ads($content) {
  if (is_admin() || is_preview() || is_feed() || !is_singular('post')) return $content;
  if (!bf_opt_bool('adsense_enabled')) return $content;

  $client = bf_opt('adsense_client', ''); $slot = bf_opt('slot_inarticle', '');
  if (!$client || !$slot) return $content;

  $parts = preg_split('/(<\\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
  if (!$parts || count($parts) < 2) return $content;

  // 문단 수
  $para_total = 0; foreach ($parts as $p) if (stripos($p, '</p>') !== false) $para_total++;

  if ($para_total <= 4) return $content;          // 짧은 글: 인아티클 생략
  $positions = ($para_total <= 7) ? [2] : [2,5];  // 중간: 1개, 긴 글: 2개

  $adtest = (function_exists('wp_get_environment_type') && wp_get_environment_type()==='development') ? ' data-adtest="on"' : '';
  $ins = sprintf('<ins class="adsbygoogle" style="display:block; text-align:center;" data-ad-layout="in-article" data-ad-format="fluid" data-ad-client="%s" data-ad-slot="%s"%s></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>',
    esc_attr($client), esc_attr($slot), $adtest);
  $ad_html = '<div class="ad ad-in-article" aria-label="advertisement"><div class="ad-slot">'.$ins.'</div></div>';

  $out = ''; $iPara = 0;
  for ($i=0; $i<count($parts); $i++) {
    $out .= $parts[$i];
    if (stripos($parts[$i], '</p>') !== false) {
      $iPara++;
      if (in_array($iPara, $positions, true)) $out .= $ad_html;
    }
  }
  return $out;
}
add_filter('the_content','bf_insert_incontent_ads',110);

/* 본문 하단 광고 자동 삽입(콘텐츠 끝에 1개) */
function bf_insert_bottom_ad($content){
  if(is_admin()||is_preview()||is_feed()||!is_singular('post')) return $content;
  if(!bf_opt_bool('adsense_enabled')) return $content;

  $client=bf_opt('adsense_client',''); 
  $slot  = bf_opt('slot_bottom','');           // 하단 전용 슬롯
  if(!$slot) $slot = bf_opt('slot_inarticle',''); // 없으면 인아티클 슬롯 재사용
  if(!$client||!$slot) return $content;

  $adtest=(function_exists('wp_get_environment_type') && wp_get_environment_type()==='development') ? ' data-adtest="on"' : '';
  $ins=sprintf('<ins class="adsbygoogle" style="display:block" data-ad-client="%s" data-ad-slot="%s" data-ad-format="auto" data-full-width-responsive="true"%s></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>',
    esc_attr($client), esc_attr($slot), $adtest);
  $html='<div class="ad ad-bottom" aria-label="advertisement"><div class="ad-slot">'.$ins.'</div></div>';

  return $content . $html; // 본문 끝에 붙이기 → 연관글 위에 위치
}
add_filter('the_content','bf_insert_bottom_ad',120);