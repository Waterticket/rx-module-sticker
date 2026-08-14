<?php
/*! Copyright (C) 2016 BGM STORAGE. All rights reserved. */
/**
 * @class  sticker
 * @author Huhani (mmia268@gmail.com)
 * @brief  Sticker module high class.
 */

class sticker extends ModuleObject
{
	/**
	 * 목록 화면 부제목의 기본값.
	 */
	const DEFAULT_BROWSER_SUBTITLE = '사이트에서 사용할 스티커를 구매해보세요.';

	private $triggers = array(
		array( 'member.deleteMember',			'sticker',	'controller',	'triggerDeleteMember',					'after'	),
		array( 'member.getMemberMenu',		'sticker',	'controller',	'triggerMemberMenu',						'after'	),
		array( 'document.insertDocument',	'sticker',	'controller',	'triggerBeforeInsertDocument',      'before'	),
		array( 'document.updateDocument',	'sticker',	'controller',	'triggerBeforeUpdateDocument',      'before'	),
		array( 'comment.insertComment',		'sticker',	'controller',	'triggerBeforeInsertComment',       'before'	),
		array( 'comment.updateComment',		'sticker',	'controller',	'triggerBeforeUpdateComment',       'before'	),
		array( 'moduleHandler.init',			'sticker',	'controller',	'triggerBeforeModuleInit',				'before'	), // member메뉴 추가
		array( 'display',							'sticker',	'controller',	'triggerBeforeDisplay',					'before'	)
	);


	function moduleInstall()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
		if(!$sticker_info->module_srl) {
			$args = new stdClass();
			$args->mid = 'sticker';
			$args->module = 'sticker';
			$args->browser_title = '스티커';
			$args->site_srl = 0;
			$args->skin = 'default';
			$args->mskin = 'default';
			$args->layout_srl = -1;
			$args->mlayout_srl = -1;
			$oModuleController->insertModule($args);
		}

		$config = new stdClass();
		$config->use = "Y";
		$config->before_test = "N";
		$config->add_member_menu = "N";
		$config->browser_subtitle = self::DEFAULT_BROWSER_SUBTITLE;
		$config->quick_tags = "";
		$config->default_sticker = "";
		$config->deleted_sticker = '<i><p style="color: rgb(125, 125, 125);">존재하지 않는 스티커입니다.</p></i>';
		$config->buy_limit = 20;

		$config->minPoint = 100;
		$config->maxPoint = 600;
		$config->returnPoint = 15;
		$config->upload_charge = 0; //업로드 수수료. 단위 point
		$config->sale_end_date = 0;
		$config->use_date = 0;
		$config->sale_limit = 0;
		$config->limit_modify_buy = 0;
		$config->public_modify = "Y";
		$config->check_modify = "Y";
		$config->pause_modify = "Y";
		$config->public_delete = "Y";
		$config->check_delete = "Y";
		$config->pause_delete = "Y";
		$config->limit_delete_buy = 0;

		$config->resizing = "Y";
		$config->maxPx = 200;
		$config->gifResizingIf = "Y";
		$config->target_width = "Y";
		$config->image_quality = 100;
		$config->minUploads = 10;
		$config->maxUploads = 24;
		$config->image_min_width = 160;
		$config->image_min_height = 160;
		$config->file_size = 2048; //KB
		$config->file_size_all = 25000; //KB
		$config->file_ext = "jpg,jpeg,png,gif";
		$config->cmt_allow_modify = "Y";
		$config->cmt_max_sticker_count = 0;

		$oModuleController->insertModuleConfig('sticker', $config);

		foreach ($this->triggers as $trigger) {
			$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return $this->createObject();
	}




	function moduleUninstall()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		//트리거 삭제
		foreach ($this->triggers as $trigger)
		{
			$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		//페이지 삭제
		$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
		if($sticker_info->module_srl) {
			$output = $oModuleController->deleteModule($sticker_info->module_srl);
			if(!$output->toBool()) {
				return $output;
			}
		}

		return $this->createObject();

	}




	function checkUpdate()
	{
		$oModuleModel = getModel('module');
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				return true;
			}
		}

		$config = $oModuleModel->getModuleConfig('sticker');
		if ($config && (!isset($config->browser_subtitle) || !isset($config->quick_tags)))
		{
			return true;
		}

		return false;
	}

	function moduleUpdate()
	{

		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}
		}

		$config = $oModuleModel->getModuleConfig('sticker');
		if ($config && (!isset($config->browser_subtitle) || !isset($config->quick_tags)))
		{
			if (!isset($config->browser_subtitle))
			{
				$config->browser_subtitle = self::DEFAULT_BROWSER_SUBTITLE;
			}
			if (!isset($config->quick_tags))
			{
				$config->quick_tags = '';
			}
			$oModuleController->insertModuleConfig('sticker', $config);
		}

		return $this->createObject();
	}
	
	/**
	 * XE Object를 생성하여 반환한다.
	 *
	 * XE 1.8 이하, XE 1.9 이상, PHP 7.1 이하, PHP 7.2 이상 모두 호환된다.
	 * 기본적인 사용법은 return $this->createObject(-1, 'error'); 라고 쓸 자리에
	 * return $this->createObject(-1, 'error'); 라고 쓰면 된다.
	 *
	 * 반환할 언어 내용 중 %s, %d 등 변수를 치환하는 부분이 있다면
	 * 치환할 내용을 추가 파라미터로 넘겨주면 sprintf()의 역할까지 해준다.
	 *
	 * @param string $message
	 * @param $arg1, $arg2 ...
	 * @return object
	 */
	public function createObject($status = 0, $message = 'success' /* $arg1, $arg2 ... */)
	{
		$args = func_get_args();
		if (count($args) > 2)
		{
			global $lang;
			$message = vsprintf($lang->$message, array_slice($args, 2));
		}
		return class_exists('BaseObject') ? new BaseObject($status, $message) : new Object($status, $message);
	}
}

/* End of file sticker.class.php */
/* Location: ./modules/sticker/sticker.class.php */
