@include('_setting')

<div class="stk">
<div class="stk-section">

	<div class="stk-head">
		<h2 class="stk-head__title">내 스티커</h2>
		<p class="stk-head__desc">보유중인 스티커의 순서를 바꾸거나 버릴 수 있습니다. 위에 있을수록 댓글창에서 먼저 보입니다.</p>
	</div>

	@if(empty($sticker))
		<p class="stk-empty">보유중인 스티커가 없습니다.</p>
	@else
	<table class="stk-mylist">
		<thead>
			<tr>
				<th scope="col">순위</th>
				<th scope="col">스티커</th>
				<th scope="col">구매 가격</th>
				<th scope="col">구매일</th>
				<th scope="col">만료일</th>
				<th scope="col">순서</th>
			</tr>
		</thead>
		<tbody>
		@foreach($sticker as $no => $item)
			<tr>
				<td class="stk-mylist__no">{{ $loop->iteration }}</td>
				<td class="stk-mylist__name-cell">
					<a class="stk-mylist__name" href="{{ getUrl('', 'mid', $mid, 'act', '', 'page', '', 'sticker_srl', $item->sticker_srl) }}">
						@if($item->main_image)
							@include('_media', ['url' => $item->main_image, 'class' => 'stk-mylist__thumb', 'lazy' => true])
						@endif
						<span>{{ $item->title }}</span>
					</a>
				</td>
				<td data-label="구매 가격">{{ number_format($item->use_point) }}P</td>
				<td data-label="구매일">{{ zdate($item->regdate, 'Y.m.d') }}</td>
				<td data-label="만료일">{{ $item->expdate ? zdate($item->expdate, 'Y.m.d') : '무기한' }}</td>
				<td class="stk-mylist__actions">
					@if($page_navigation->total_count != $no)
					<button type="button" class="stk-mylist__ctrl js-stk-move" title="위로" data-mid="{{ $mid }}" data-sticker-srl="{{ $item->sticker_srl }}" data-move="up">
						<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 13V3M3.5 7.5 8 3l4.5 4.5"/></svg>
					</button>
					@endif
					@if($no > 1)
					<button type="button" class="stk-mylist__ctrl js-stk-move" title="아래로" data-mid="{{ $mid }}" data-sticker-srl="{{ $item->sticker_srl }}" data-move="down">
						<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3v10M3.5 8.5 8 13l4.5-4.5"/></svg>
					</button>
					@endif
					<button type="button" class="stk-mylist__ctrl js-stk-mydelete" title="버리기" data-mid="{{ $mid }}" data-sticker-srl="{{ $item->sticker_srl }}" data-title="{{ $item->title }}">
						<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 4h11M6 4V2.5h4V4M4 4l.7 9.5h6.6L12 4M6.5 6.5v5M9.5 6.5v5"/></svg>
					</button>
				</td>
			</tr>
		@endforeach
		</tbody>
	</table>
	@endif

	<div class="stk-actions">
		<a class="stk-btn" href="{{ getUrl('', 'mid', $mid) }}">스티커</a>
	</div>

	@if(!empty($page_navigation) && $page_navigation->last_page > 1)
	<nav class="stk-pagination">
		@foreach($page_navigation as $page_no)
			<a class="stk-pagination__item{{ $page_navigation->cur_page == $page_no ? ' is-active' : '' }}" href="{{ getUrl('page', $page_no) }}">{{ $page_no }}</a>
		@endforeach
	</nav>
	@endif

</div>
</div>
