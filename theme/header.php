<?php ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); // 플러그인/테마가 스크립트·스타일을 주입하는 위치 ?>
</head>
<body <?php body_class(); ?>>
<?php if (function_exists('wp_body_open')) { wp_body_open(); } // 접근성/추가 스니펫 용 ?>

<header class="site-header">
  <div class="container">
    <h1 class="site-title">
      <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); // 사이트명 ?></a>
    </h1>

    <nav class="site-nav">
      <?php
      // 3단계에서 등록한 'primary' 위치의 메뉴를 출력
      // 관리자 > 외모 > 메뉴에서 메뉴를 만들고 이 위치에 할당해야 화면에 보입니다.
      wp_nav_menu([
        'theme_location' => 'primary',
        'container'      => false,
        'fallback_cb'    => false,   // 메뉴 미할당이면 아무것도 출력하지 않음
        'menu_class'     => 'menu',  // CSS 클래스
      ]);
      ?>
    </nav>

    <div class="site-search">
      <?php get_search_form(); // 위에서 만든 searchform.php 출력 ?>
    </div>    
  </div>
</header>

<main class="container">