@php
	$is_video = str_ends_with($url, '.mp4');
	// ImageProcessor::_convertGifToMp4()가 mp4와 같은 이름(확장자만 다름)으로 저장한 정지 프레임.
	// jpg가 아니라 webp인 이유는 투명 배경 스티커를 로딩 전에도 자연스럽게 보여주기 위해서다.
	$poster = $is_video ? substr($url, 0, -4) . '.webp' : null;
@endphp
@if($is_video)
	<video class="{{ $class ?? '' }}" src="{{ $url }}" poster="{{ $poster }}" autoplay muted loop playsinline preload="metadata"@if(!empty($lazy)) loading="lazy"@endif@if(!empty($title)) title="{{ $title }}"@endif></video>
@else
	<img class="{{ $class ?? '' }}" src="{{ $url }}" alt="{{ $alt ?? '' }}"@if(!empty($title)) title="{{ $title }}"@endif@if(!empty($lazy)) loading="lazy"@endif />
@endif
