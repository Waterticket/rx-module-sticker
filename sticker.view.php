<?php
/*! Copyright (C) 2016 BGM STORAGE. All rights reserved. */
/**
 * @class  stickerView
 * @author Huhani (mmia268@gmail.com)
 * @brief  Sticker module view class.
 */

class stickerView extends sticker
{
	function init()
	{
		Context::set('config', $this->module_config);

		if($this->mid != "sticker"){
			$oModuleModel = ModuleModel::getInstance();
			$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
			$this->module_info = $sticker_info;
		}

		$template_path = sprintf("%sskins/%s/", $this->module_path, $this->module_info->skin);
		$this->module_info->layout_srl = $this->module_info->layout_srl;
		if(!is_dir($template_path)||!$this->module_info->skin)
		{
			$this->module_info->skin = 'default';
			$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
		}
		$this->setTemplatePath($template_path);

	}

	function dispStickerList(){

		$sticker_srl = Context::get('sticker_srl');
		if($sticker_srl){
			if($this->grant->view){
				Context::set('view_grant', true);
				$this->dispStickerView();
			} else {
				Context::set('view_grant', false);
			}
		}

		$search_target = strval(Context::get('search_target'));
		$search_keyword = strval(Context::get('search_keyword'));
		$sort = Context::get('sort') === 'popular' ? 'popular' : 'recent';

		if($this->grant->list){

			$columnList = array('title', 'content', 'nick_name', 'tag', 'status');

			$args = new stdClass();
			$args->page = Context::get('page');
			$args->list_count = 20; ///< 한페이지에 보여줄 기록 수
			$args->page_count = 10; ///< 페이지 네비게이션에 나타날 페이지의 수
			$args->sort_index = $sort === 'popular' ? 'sticker.bought_count' : 'sticker.regdate';
			$args->order_type = 'desc';
			if($search_target && in_array($search_target, $columnList)) {
				if($search_target == 'status'){
					$args->status = $search_keyword != 'CHECK' ? 'PUBLIC' : 'CHECK';
				} else {
					$args->{"s_".$search_target} = $search_keyword ? $search_keyword : null;
				}
			}

			$output = executeQueryArray('sticker.getStickerList', $args);

			foreach($output->data as $sticker){
				$sticker->main_image = $this->_getStickerMainImage($sticker->sticker_srl);
			}

			Context::set('list', $output->data);
			Context::set('page_navigation', $output->page_navigation);

		}

		$oStickerModel = StickerModel::getInstance();
		$config = $oStickerModel->getConfig();
		$subtitle = isset($config->browser_subtitle) ? trim($config->browser_subtitle) : '';

		Context::set('grant', $this->grant);
		Context::set('sort', $sort);
		Context::set('search_target', $search_target);
		Context::set('search_keyword', $search_keyword);
		Context::set('sticker_title', $this->module_info->browser_title ? $this->module_info->browser_title : '스티커');
		Context::set('sticker_subtitle', $subtitle !== '' ? $subtitle : sticker::DEFAULT_BROWSER_SUBTITLE);
		$this->setTemplateFile('board');
	}

	function dispStickerView(){
		$sticker_srl = Context::get('sticker_srl');

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.getSticker', $args);
		if(!$output->toBool() || empty($output->data)){
			return false;
		}

		$args1 = new stdClass();
		$args1->sticker_srl = $sticker_srl;
		$output1 = executeQueryArray('sticker.getStickerImage', $args1);

		$oStickerModel = StickerModel::getInstance();
		$is_bougth = false;

		$logged_info = Context::get('logged_info');
		if($logged_info){
			$is_bougth = $oStickerModel->checkBuySticker($logged_info->member_srl, $sticker_srl);
		}

		Context::addBrowserTitle($output->data->title);

		$oStickerController = StickerController::getInstance();
		$oStickerController->updateReadedCount($output->data);

		Context::set('date', date('YmdHis'));
		Context::set('grant', $this->grant);
		Context::set('is_bougth', $is_bougth);
		Context::set('sticker', $output->data);
		Context::set('sticker_file', $output1->data);
	}

	function dispStickerWrite(){

		if( !(extension_loaded('gd') && function_exists('gd_info')) ){
			return $this->createObject(-1,'GD_library_is_not_installed');
		}

		$logged_info =  Context::get('logged_info');
		if(!$logged_info){
			return $this->createObject(-1,'invalid_access');
		}

		if(!$this->grant->upload){
			return $this->createObject(-1,'msg_access_denied');
		}

		$sticker_srl = Context::get('sticker_srl');
		$sticker = false;
		$sticker_file = array();

		if($sticker_srl){
			$args = new stdClass();
			$args->sticker_srl = $sticker_srl;
			$output = executeQuery('sticker.getSticker', $args);
			if (!$output->toBool())	{
				return $output;
			}

			if(!($logged_info->member_srl == $output->data->member_srl || $logged_info->is_admin == 'Y' || $this->grant->manager)){
				return $this->createObject(-1,'invalid_access');
			}

			if(!($logged_info->is_admin == 'Y' || $this->grant->manager)){
				$sticker_status = $output->data->status;
				if($sticker_status == "PUBLIC"){
					if($this->module_config->public_modify != "Y"){
						return $this->createObject(-1, 'msg_modify_denied');
					}
				} else if($sticker_status == "CHECK"){
					if($this->module_config->check_modify != "Y"){
						return $this->createObject(-1, 'msg_modify_denied');
					}
				} else if($sticker_status == "PAUSE"){
					if($this->module_config->pause_modify != "Y"){
						return $this->createObject(-1, 'msg_modify_denied');
					}
				} else if($sticker_status == "STOP"){
					return $this->createObject(-1, 'msg_modify_denied');
				} else {
					return $this->createObject(-1, 'invalid_status_sticker');
				}

				if($this->module_config->limit_modify_buy && $output->data->bought_count >= $this->module_config->limit_modify_buy){
					return $this->createObject(-1, sprintf('판매 수가 %d이상인 스티커는 수정할 수 없습니다.', $this->module_config->limit_modify_buy));
				}
			}

			$output1 = executeQueryArray('sticker.getStickerImage', $args);
			$sticker_file = $output1->data;

			$output->data->content = htmlspecialchars($output->data->content, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
			$sticker = $output->data;
		}
		Context::set('sticker', $sticker);
		Context::set('sticker_file', $sticker_file);

		$oEditorModel = EditorModel::getInstance();
		$option = new stdClass();
		$option->primary_key_name = 'sticker_srl';
		$option->content_key_name = 'content';
		$option->allow_fileupload = FALSE;
		$option->enable_autosave = FALSE;
		$option->enable_default_component = TRUE;
		$option->enable_component = FALSE;
		$option->resizable = FALSE;
		$option->disable_html = TRUE;
		$option->skin = 'xpresseditor';
		$option->height = 200;
		$editor = $oEditorModel->getEditor($logged_info->member_srl, $option);

		$oStickerModel = StickerModel::getInstance();
		$sticker_config = $oStickerModel->getConfig();

		Context::set('editor', $editor);
		Context::set('config', $sticker_config);

		$this->setTemplateFile('editor');
	}


	function dispStickerDelete(){
		$logged_info = Context::get('logged_info');
		if(!$logged_info){
			return $this->createObject(-1,'invalid_access');
		}
		$member_srl = $logged_info->member_srl;
		$sticker_srl = Context::get('sticker_srl');
		if(!($sticker_srl)){
			return $this->createObject(-1,'invalid_access');
		}

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.getSticker', $args);
		if (!$output->toBool())	{
			return $output;
		}
		if(empty($output->data) || !($output->data->member_srl == $member_srl || $logged_info->is_admin == 'Y' || $this->grant->manager)){
			return $this->createObject(-1,'invalid_access');
		}

		if(!($logged_info->is_admin == 'Y' || $this->grant->manager)){
			$sticker_status = $output->data->status;
			if($sticker_status == "PUBLIC"){
				if($this->module_config->public_delete != "Y"){
					return $this->createObject(-1, 'msg_delete_denied');
				}
			} else if($sticker_status == "CHECK"){
				if($this->module_config->check_delete != "Y"){
					return $this->createObject(-1, 'msg_delete_denied');
				}
			} else if($sticker_status == "PAUSE"){
				if($this->module_config->pause_delete != "Y"){
					return $this->createObject(-1, 'msg_delete_denied');
				}
			} else if($sticker_status == "STOP"){
				return $this->createObject(-1, 'msg_delete_denied');
			} else {
				return $this->createObject(-1, 'invalid_status_sticker');
			}

			if($this->module_config->limit_delete_buy && $output->data->bought_count >= $this->module_config->limit_delete_buy){
				return $this->createObject(-1, sprintf('판매 수가 %d이상인 스티커는 삭제할 수 없습니다.', $this->module_config->limit_delete_buy));
			}
		}

		Context::addJsFilter($this->module_path.'tpl/filter', 'delete_sticker.xml');
		Context::set('sticker', $output->data);
		$this->setTemplateFile('delete');

	}


	function dispStickerMylist(){

		$logged_info =  Context::get('logged_info');
		if(!$logged_info){
			return $this->createObject(-1,'invalid_access');
		}
		$args = new stdClass();
		$args->page = Context::get('page');
		$args->list_count = 20;
		$args->page_count = 10;
		$args->order_type = 'asc';
		$args->member_srl = $logged_info->member_srl;
		$args->date = date("YmdHis");
		$output = executeQueryArray('sticker.getStickerMylist', $args);

		foreach($output->data as $sticker){
			$sticker->main_image = $this->_getStickerMainImage($sticker->sticker_srl);
		}

		Context::set('sticker', $output->data);
		Context::set('page_navigation', $output->page_navigation);

		$this->setTemplateFile('member_sticker');

	}

	/**
	 * 스티커의 대표 이미지(no = 0) URL을 반환한다. 없으면 빈 문자열.
	 *
	 * @param int $sticker_srl
	 * @return string
	 */
	private function _getStickerMainImage($sticker_srl)
	{
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->no = 0;
		$output = executeQueryArray('sticker.getStickerMainImage', $args);

		return isset($output->data[0]->url) ? $output->data[0]->url : '';
	}

}

/* End of file sticker.view.php */
/* Location: ./modules/sticker/sticker.view.php */
