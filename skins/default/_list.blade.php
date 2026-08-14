@php
	$is_review_list = ($search_target === 'status' && $search_keyword === 'CHECK');
	$is_manager = ($logged_info && $logged_info->is_admin === 'Y') || $grant->manager;
@endphp

<div class="stk-section">

	<div class="stk-head">
		<h2 class="stk-head__title">@if($is_review_list)검토중인 스티커@else{{ $sticker_title }}@endif</h2>
		<p class="stk-head__desc">@if($is_review_list)아직 판매가 승인되지 않은 스티커 목록입니다.@else{{ $sticker_subtitle }}@endif</p>
	</div>

	<div class="stk-tabs">
		<a class="stk-tabs__item{{ $sort !== 'popular' ? ' is-active' : '' }}" href="{{ getUrl('sticker_srl', '', 'page', '', 'sort', '') }}">최신순</a>
		<a class="stk-tabs__item{{ $sort === 'popular' ? ' is-active' : '' }}" href="{{ getUrl('sticker_srl', '', 'page', '', 'sort', 'popular') }}">인기순</a>
	</div>

	@if(empty($list))
		<p class="stk-empty">등록된 스티커가 없습니다.</p>
	@else
		<ul class="stk-grid">
		@foreach($list as $item)
			<li class="stk-card">
				<a class="stk-card__thumb" href="{{ getUrl('', 'mid', $mid, 'sticker_srl', $item->sticker_srl) }}">
					@if($item->main_image)
						<img src="{{ $item->main_image }}" alt="{{ $item->title }}" loading="lazy" />
					@endif
					@if($item->status === 'CHECK')
						<span class="stk-card__badge">검토중</span>
					@elseif($item->status === 'PAUSE' || $item->status === 'STOP')
						<span class="stk-card__badge">판매정지</span>
					@endif
				</a>
				<a class="stk-card__title" href="{{ getUrl('', 'mid', $mid, 'sticker_srl', $item->sticker_srl) }}">{{ $item->title }}</a>
				<span class="stk-card__author">{{ $item->nick_name }}</span>
			</li>
		@endforeach
		</ul>
	@endif

	<form class="stk-minisearch" method="get" action="{{ getUrl('', 'mid', $mid) }}">
		<input type="hidden" name="mid" value="{{ $mid }}" />
		<select name="search_target" class="stk-minisearch__select" aria-label="검색 대상">
			<option value="title" @selected($search_target === 'title')>제목</option>
			<option value="content" @selected($search_target === 'content')>내용</option>
			<option value="nick_name" @selected($search_target === 'nick_name')>닉네임</option>
			<option value="tag" @selected($search_target === 'tag')>태그</option>
		</select>
		<input type="text" name="search_keyword" class="stk-minisearch__input" placeholder="스티커 검색" aria-label="검색어" value="{{ $is_review_list ? '' : $search_keyword }}" />
		<button type="submit" class="stk-minisearch__btn" aria-label="검색">
			<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5 14 14" stroke-linecap="round"/></svg>
		</button>
	</form>

	<div class="stk-actions stk-actions--split">
		<div>
			@if($is_review_list)
				<a class="stk-btn" href="{{ getUrl('', 'mid', $mid) }}">
					<svg class="stk-btn__icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M2 4h12M2 8h12M2 12h12"/></svg>
					전체 목록
				</a>
			@else
				<a class="stk-btn" href="{{ getUrl('', 'mid', $mid, 'search_target', 'status', 'search_keyword', 'CHECK') }}">검토 목록</a>
			@endif
		</div>
		<div>
			@if($is_manager)
				<a class="stk-btn" href="{{ getUrl('', 'module', 'admin', 'act', 'dispStickerAdminConfig') }}" target="_blank" rel="noopener">
					<svg class="stk-btn__icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="8" cy="8" r="2.3"/><path d="M8 1.6v1.8M8 12.6v1.8M1.6 8h1.8M12.6 8h1.8M3.5 3.5l1.3 1.3M11.2 11.2l1.3 1.3M12.5 3.5l-1.3 1.3M4.8 11.2l-1.3 1.3" stroke-linecap="round"/></svg>
					모듈 설정
				</a>
			@endif
			@if($grant->upload)
				<a class="stk-btn" href="{{ getUrl('', 'mid', $mid, 'act', 'dispStickerWrite') }}">
					<svg class="stk-btn__icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M8 3v10M3 8h10"/></svg>
					스티커 제작
				</a>
			@endif
			@if($logged_info)
				<a class="stk-btn" href="{{ getUrl('', 'mid', $mid, 'act', 'dispStickerMylist') }}">
					<svg class="stk-btn__icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4.5" width="9" height="9" rx="1.6"/><path d="M5 4.5V3.6a1.6 1.6 0 0 1 1.6-1.6h6.2A1.2 1.2 0 0 1 14 3.2v6.2A1.6 1.6 0 0 1 12.4 11h-.9" stroke-linecap="round"/></svg>
					내 스티커
				</a>
			@endif
		</div>
	</div>

	@if(!empty($page_navigation) && $page_navigation->last_page > 1)
	<nav class="stk-pagination">
		@foreach($page_navigation as $page_no)
			<a class="stk-pagination__item{{ $page_navigation->cur_page == $page_no ? ' is-active' : '' }}" href="{{ getUrl('sticker_srl', '', 'page', $page_no) }}">{{ $page_no }}</a>
		@endforeach
		@if($page_navigation->cur_page < $page_navigation->last_page)
			<a class="stk-pagination__item" href="{{ getUrl('sticker_srl', '', 'page', $page_navigation->last_page) }}">끝 페이지</a>
		@endif
	</nav>
	@endif

</div>
