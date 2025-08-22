<?php
// 접근성용 label은 스크린 리더 전용으로 제공
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
  <label class="screen-reader-text" for="s">검색</label>
  <input type="search" id="s" name="s"
         value="<?php echo esc_attr(get_search_query()); ?>"
         placeholder="검색어를 입력하세요" />
  <button type="submit">검색</button>
</form>