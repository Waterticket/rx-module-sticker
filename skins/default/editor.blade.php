@include('_setting')

@php
	$is_edit = (bool)$sticker;

	// no => 파일 맵. 0번은 대표 이미지.
	$file_map = array();
	foreach($sticker_file as $file)
	{
		$file_map[$file->no] = $file;
	}
	ksort($file_map);

	// 서버는 sticker_file[] 개수만 minUploads~maxUploads로 검사한다. 대표 이미지 1장이 별도이므로 총 장수는 +1.
	$min_total = $config->minUploads + 1;
	$max_total = $config->maxUploads + 1;

	$is_manager = ($logged_info && $logged_info->is_admin === 'Y') || $grant->manager;

	$quick_tags = array();
	foreach(explode(',', isset($config->quick_tags) ? $config->quick_tags : '') as $quick_tag)
	{
		$quick_tag = trim($quick_tag);
		if($quick_tag !== '' && !in_array($quick_tag, $quick_tags))
		{
			$quick_tags[] = $quick_tag;
		}
	}
@endphp

<div class="stk">
<div class="stk-section">

	<div class="stk-head">
		<h2 class="stk-head__title">{{ $is_edit ? '스티커 수정' : '스티커 추가' }}</h2>
		<p class="stk-head__desc">{{ $is_edit ? '등록한 스티커의 이미지와 정보를 수정합니다.' : '이미지를 올리고 판매 정보를 입력하면 스티커가 등록됩니다.' }}</p>
	</div>

	@if(!empty($XE_VALIDATOR_MESSAGE) && empty($XE_VALIDATOR_ID))
		<p class="stk-msg stk-msg--error">{{ $XE_VALIDATOR_MESSAGE }}</p>
	@endif

	<form class="stk-write js-stk-form" method="post" action="{{ getUrl('', 'mid', $mid) }}" enctype="multipart/form-data">
		@csrf
		<input type="hidden" name="mid" value="{{ $mid }}" />
		<input type="hidden" name="act" value="procStickerInsert" />
		<input type="hidden" name="error_return_url" value="{{ getRequestUriByServerEnviroment() }}" />
		<input type="hidden" name="content" value="{{ $sticker->content }}" />
		@if($is_edit)
			<input type="hidden" name="sticker_srl" value="{{ $sticker->sticker_srl }}" />
		@endif

		<input type="text" class="stk-write__title" name="title" value="{{ $sticker->title }}" placeholder="스티커 제목" aria-label="스티커 제목" />

		<h3 class="stk-write__label">스티커 업로드</h3>
		<div class="stk-uploader js-stk-uploader"
			data-mode="{{ $is_edit ? 'edit' : 'new' }}"
			data-mid="{{ $mid }}"
			data-sticker-srl="{{ $sticker->sticker_srl }}"
			data-min-total="{{ $min_total }}"
			data-max-total="{{ $max_total }}"
			data-max-slot="{{ $config->maxUploads }}"
			data-min-slot="{{ $config->minUploads }}"
			data-max-size="{{ $config->file_size }}">

			<ul class="stk-uploader__list">
				@foreach($file_map as $no => $file)
				<li class="stk-uploader__item{{ $no === 0 ? ' is-main' : '' }}"
					data-kind="existing"
					data-no="{{ $no }}"
					data-removable="{{ $no > $config->minUploads ? '1' : '0' }}">
					<img src="{{ $file->url }}" alt="{{ $file->file_name }}" />
				</li>
				@endforeach
			</ul>

			<div class="stk-uploader__guide">
				<svg class="stk-uploader__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7.5 8.5 12 4l4.5 4.5M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/></svg>
				<p class="stk-uploader__title">이미지를 드래그하거나 클릭하여 업로드</p>
				<p class="stk-uploader__hint">JPG, PNG, GIF / 파일당 최대 {{ number_format($config->file_size) }}KB / 최소 {{ $min_total }}장 ~ 최대 {{ $max_total }}장</p>
				<p class="stk-uploader__count">현재 <b class="js-stk-count">0</b>장 / 최대 {{ $max_total }}장</p>
			</div>

			<input type="file" class="stk-uploader__picker js-stk-picker" accept="image/jpeg,image/png,image/gif" multiple />
			<div class="stk-uploader__inputs js-stk-inputs" hidden></div>
		</div>

		<h3 class="stk-write__label">판매 포인트</h3>
		<input type="number" class="stk-input" name="price" value="{{ $sticker->price }}" min="{{ $config->minPoint }}" max="{{ $config->maxPoint }}" placeholder="최소 {{ number_format($config->minPoint) }}P ~ 최대 {{ number_format($config->maxPoint) }}P" aria-label="판매 포인트" />

		<h3 class="stk-write__label">스티커 설명</h3>
		<div class="stk-editor">
			{!! $editor !!}
		</div>

		<h3 class="stk-write__label">태그</h3>
		<div class="stk-tagger js-stk-tagger" data-max="10" data-maxlength="20">
			<p class="stk-tagger__hint">
				스티커를 검색할 때 쓰이는 태그입니다. 최대 10개 / 태그당 최대 20글자<br />
				스페이스바, 쉼표(,), 엔터로 태그가 구분됩니다.
			</p>

			@if($quick_tags)
			<div class="stk-tagger__block">
				<span class="stk-tagger__label">빠른 등록</span>
				<div class="stk-tagger__quick">
					@foreach($quick_tags as $quick_tag)
					<button type="button" class="stk-tagger__preset js-stk-tagpreset" data-tag="{{ $quick_tag }}" title="눌러서 추가, 다시 누르면 제거">
						<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M8 5.4v5.2M5.4 8h5.2"/></svg>
						#{{ $quick_tag }}
					</button>
					@endforeach
				</div>
			</div>
			@elseif($is_manager)
			<div class="stk-tagger__block">
				<span class="stk-tagger__label">빠른 등록</span>
				<p class="stk-tagger__empty">
					등록된 빠른 등록 태그가 없습니다.
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispStickerAdminConfig') }}" target="_blank" rel="noopener">기본 템플릿 추가하러 가기</a>
				</p>
			</div>
			@endif

			<div class="stk-tagger__block">
				<span class="stk-tagger__label">직접 입력</span>
				<div class="stk-tagger__field">
					<input type="text" class="stk-input js-stk-taginput" placeholder="태그를 입력해주세요" aria-label="태그 직접 입력" autocomplete="off" />
					<button type="button" class="stk-btn js-stk-tagadd">추가</button>
				</div>
			</div>

			<ul class="stk-tagger__list js-stk-taglist"></ul>
			<input type="hidden" name="tags" class="js-stk-tagvalue" value="{{ $sticker->tag }}" />
		</div>

		<div class="stk-write__foot">
			<a class="stk-btn" href="{{ $is_edit ? getUrl('', 'mid', $mid, 'act', '', 'sticker_srl', $sticker->sticker_srl) : getUrl('', 'mid', $mid) }}">돌아가기</a>
			<button type="submit" class="stk-btn stk-btn--primary">{{ $is_edit ? '수정' : '등록' }}</button>
		</div>
	</form>

</div>
</div>
