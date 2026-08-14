@include('_setting')

<div class="stk">

@if(isset($view_grant))
	@if($view_grant === true)
		@if(!empty($sticker) && ($sticker->status !== 'STOP' || $grant->manager))
			@include('_read')
		@else
			<p class="stk-msg stk-msg--error">삭제되었거나 판매가 정지된 스티커입니다.</p>
		@endif
	@else
		<p class="stk-msg stk-msg--error">이 스티커를 열람할 권한이 없습니다.</p>
	@endif
@endif

@if($grant->list)
	@include('_list')
@else
	<p class="stk-msg stk-msg--error">스티커 목록에 접근할 권한이 없습니다.</p>
@endif

</div>
