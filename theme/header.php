<?php ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php if (function_exists('wp_body_open')) wp_body_open(); ?>

<header class="site-header">
  <div class="container">
    <h1 class="site-title">
      <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
    </h1>

    <nav class="site-nav">
      <?php
      if (has_nav_menu('primary')) {
        wp_nav_menu([
          'theme_location' => 'primary',
          'container'      => false,
          'fallback_cb'    => false,
          'menu_class'     => 'menu',
        ]);
      } else {
        echo '<ul class="menu">';
        echo '<li><a href="' . esc_url(home_url('/')) . '">홈</a></li>';
        $blog_id = get_option('page_for_posts');
        if ($blog_id) {
          echo '<li><a href="' . esc_url(get_permalink($blog_id)) . '">블로그</a></li>';
        }
        echo '</ul>';
      }
      ?>
    </nav>
  </div>
</header>

<?php if (is_active_sidebar('header-ad')): ?>
  <div class="container"><?php dynamic_sidebar('header-ad'); ?></div>
<?php endif; ?>

<main class="container">