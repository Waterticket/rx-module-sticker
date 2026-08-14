<?php
/*! Copyright (C) 2016 BGM STORAGE. All rights reserved. */
/**
 * @class  stickerAdminController
 * @author Huhani (mmia268@dnip.co.kr)
 * @brief  Sticker module admin controller class.
 */

class stickerAdminController extends sticker
{
	function init()
	{
	}

	function procStickerAdminConfig()
	{

		$oModuleController = getController('module');

		$config = Context::getRequestVars();
		getDestroyXeVars($config);
		unset($config->body);
		unset($config->_filter);
		unset($config->error_return_url);
		unset($config->act);
		unset($config->module);
		unset($config->ruleset);

		// Context는 빈 문자열 파라미터를 버리므로(Context.class.php:1304), 비워서 저장해야 하는 항목은 직접 채운다.
		foreach(array('browser_subtitle', 'quick_tags', 'default_sticker', 'deleted_sticker') as $key){
			if(!isset($config->{$key})){
				$config->{$key} = "";
			}
		}

		if(!empty($config->browser_title)){
			$oModuleModel = getModel('module');
			$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
			$sticker_info->browser_title = $config->browser_title;
			unset($config->browser_title);
			$oModuleController->updateModule($sticker_info);
		}

		$output = $oModuleController->updateModuleConfig('sticker', $config);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_saved');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminSkin');
		$this->setRedirectUrl($returnUrl);
	}

	function procStickerAdminDesign(){

		if(Context::getRequestMethod() == 'GET') return $this->createObject(-1, 'msg_invalid_request');

		$oModuleController = getController('module');

		$oModuleModel = getModel('module');
		$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
		if($sticker_info){
			$sticker_info->skin = Context::get('skin');
			$sticker_info->mskin = Context::get('mskin');
			$sticker_info->layout_srl = Context::get('layout_srl');
			$sticker_info->mlayout_srl = Context::get('mlayout_srl');

			$oModuleController->updateModule($sticker_info);
		}

		$this->setMessage('success_saved');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminDesign');
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminUpdate(){

		$sticker_srl = Context::get('sticker_srl');
		$config = Context::getRequestVars();
		getDestroyXeVars($config);
		unset($config->body);
		unset($config->_filter);
		unset($config->error_return_url);
		unset($config->act);
		unset($config->module);
		unset($config->ruleset);

		$oStickerModel = getModel('sticker');
		$oSticker = $oStickerModel->getSticker($sticker_srl);
		if(!$oSticker){
			return $this->createObject(-1,'msg_invalid_sticker');
		}

		$config->start_hour = empty($config->start_hour) ? 0 : intval($config->start_hour);
		$config->start_minute = empty($config->start_minute) ? 0 : intval($config->start_minute);
		$config->start_second = empty($config->start_second) ? 0 : intval($config->start_second);

		$config->end_hour = empty($config->end_hour) ? 0 : intval($config->end_hour);
		$config->end_minute = empty($config->end_minute) ? 0 : intval($config->end_minute);
		$config->end_second = empty($config->end_second) ? 0 : intval($config->end_second);

		$start_date = null;
		if(!empty($config->start_date) &&
			strlen($config->start_date) == 8 &&
			checkdate(substr($config->start_date, 4, 2), substr($config->start_date, -2), substr($config->start_date, 0, 4)) &&
			($config->start_hour >= 0 && $config->start_hour < 24) &&
			($config->start_minute >= 0 && $config->start_minute < 60) &&
			($config->start_second >= 0 && $config->start_second < 60)
		){
			$start_date = $config->start_date . (strlen($config->start_hour) == 1 ? ('0'.$config->start_hour) : $config->start_hour) . (strlen($config->start_minute) == 1 ? ('0'.$config->start_minute) : $config->start_minute) . (strlen($config->start_second) == 1 ? ('0'.$config->start_second) : $config->start_second);
		}


		$end_date = null;
		if(!empty($config->end_date) &&
			strlen($config->end_date) == 8 &&
			checkdate(substr($config->end_date, 4, 2), substr($config->end_date, -2), substr($config->end_date, 0, 4)) &&
			($config->end_hour >= 0 && $config->end_hour < 24) &&
			($config->end_minute >= 0 && $config->end_minute < 60) &&
			($config->end_second >= 0 && $config->end_second < 60)
		){
			$end_date = $config->end_date . (strlen($config->end_hour) == 1 ? ('0'.$config->end_hour) : $config->end_hour) . (strlen($config->end_minute) == 1 ? ('0'.$config->end_minute) : $config->end_minute) . (strlen($config->end_second) == 1 ? ('0'.$config->end_second) : $config->end_second);
		}

		$sequence = getNextSequence();
		$logged_info = Context::get('logged_info');

		$title = empty($config->title) ? $oSticker->title : $config->title;
		$content = empty($config->content) ? $oSticker->content : $config->content;
		$status = array('PUBLIC', 'CHECK', 'PAUSE', 'STOP');

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->title = cut_str(htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8', false), 100);
		$args->tag = cut_str(htmlspecialchars(strip_tags((string)($config->tag ?? '')), ENT_QUOTES, 'UTF-8', false), 250);
		$args->content = removeHackTag($content);

		if(!empty($config->readed_count_e) && $config->readed_count_e === 'Y'){
			$args->readed_count = empty($config->readed_count) ? 0 : intval($config->readed_count);
		}
		if(!empty($config->bought_count_e) && $config->bought_count_e === 'Y'){
			$args->bought_count = empty($config->bought_count) ? 0 : intval($config->bought_count);
		}
		if(!empty($config->used_count_e) && $config->used_count_e === 'Y'){
			$args->used_count = empty($config->used_count) ? 0 : intval($config->used_count);
		}

		$args->start_date = $start_date;
		$args->end_date = $end_date;
		$args->price = empty($config->price) ? 0 : intval($config->price);
		$args->buy_limit = empty($config->buy_limit) ? 0 : intval($config->buy_limit);
		$args->exptime = empty($config->exptime) ? null : intval($config->exptime);

		$args->last_update = date('YmdHis');
		$args->last_updater = $logged_info->nick_name;
		$args->list_order = $sequence * -1;
		$args->status = in_array($config->status ?? '', $status) ? $config->status : "PUBLIC";

		$output = executeQuery('sticker.updateStickerAdmin', $args);
		if (!$output->toBool()) {
			return $output;
		}

		$this->setMessage('success_saved');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminStickerView', 'sticker_srl', $sticker_srl);
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminDelete(){

		$sticker_srl = Context::get('sticker_srl');
		$oStickerModel = getModel('sticker');
		$oSticker = $oStickerModel->getSticker($sticker_srl);
		if(!$oSticker){
			return $this->createObject(-1,'msg_invalid_sticker');
		}

		$oStickerController = getController('sticker');
		$oStickerController->_deleteSticker($sticker_srl);
		$oStickerController->_deleteStickerFiles($sticker_srl);
		$oStickerController->_deleteStickerBuyByStickerSrl($sticker_srl);

		$this->setMessage('success_deleted');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminStickerList');
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminBuyUpdate(){
		$idx = Context::get('idx');
		$config = Context::getRequestVars();
		getDestroyXeVars($config);

		$args = new stdClass();
		$args->idx = $idx;
		$output = executeQuery('sticker.getStickerBuyByIdx', $args);
		if (!$output->toBool()) {
			return $output;
		}
		if(empty($output->data)){
			return $this->createObject(-1,'msg_invalid_buy_sticker');
		}

		$expdate_hour = empty($config->expdate_hour) ? 0 : intval($config->expdate_hour);
		$expdate_minute = empty($config->expdate_minute) ? 0 : intval($config->expdate_minute);
		$expdate_second = empty($config->expdate_second) ? 0 : intval($config->expdate_second);

		$expdate = null;
		if(!empty($config->expdate) &&
			strlen($config->expdate) == 8 &&
			checkdate(substr($config->expdate, 4, 2), substr($config->expdate, -2), substr($config->expdate, 0, 4)) &&
			($expdate_hour >= 0 && $expdate_hour < 24) &&
			($expdate_minute >= 0 && $expdate_minute < 60) &&
			($expdate_second >= 0 && $expdate_second < 60)
		){
			$expdate = $config->expdate . (strlen($expdate_hour) == 1 ? ('0'.$expdate_hour) : $expdate_hour) . (strlen($expdate_minute) == 1 ? ('0'.$expdate_minute) : $expdate_minute) . (strlen($expdate_second) == 1 ? ('0'.$expdate_second) : $expdate_second);
		}

		$use_point = empty($config->use_point) ? 0 : intval($config->use_point);
		$args1 = new stdClass();
		$args1->idx = $idx;
		$args1->expdate = $expdate;
		$args1->use_point = $use_point;
		if(!empty($config->used_count_e) && $config->used_count_e == "Y"){
			$args1->used_count = empty($config->used_count) ? 0 : intval($config->used_count);
		}

		$output1 = executeQuery('sticker.updateStickerBuyInfo', $args1);
		if (!$output1->toBool()) {
			return $output1;
		}

		$this->setMessage('success_saved');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminBuyInfo', 'idx', $idx);
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminBuyDelete(){
		$idx = Context::get('idx');
		$args = new stdClass();
		$args->idx = $idx;

		$output = executeQuery('sticker.getStickerBuyByIdx', $args);
		if (!$output->toBool()) {
			return $output;
		}
		if(empty($output->data)){
			return $this->createObject(-1,'msg_invalid_buy_sticker');
		}

		executeQuery('sticker.deleteStickerBuyByIdx', $args);

		$this->setMessage('success_deleted');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminBuyList');
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminLogClear(){
		$select_date = intval(Context::get('select_date'));
		$date = date("YmdHis", mktime(date('H'), date('i'), date('s'), date('m'), date('d') - $select_date, date('Y')));

		$args = new stdClass();
		$args->date = $date;
		executeQuery('sticker.deleteStickerLog', $args);

		$this->setMessage('success_deleted');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminLogList');
		$this->setRedirectUrl($returnUrl);

	}


}

/* End of file sticker.admin.controller.php */
/* Location: ./modules/sticker/sticker.admin.controller.php */
