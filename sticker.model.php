<?php
/*! Copyright (C) 2016 BGM STORAGE. All rights reserved. */
/**
 * @class  stickerModel
 * @author Huhani (mmia268@gmail.com)
 * @brief  Sticker module model class.
 */

class stickerModel extends sticker
{
	function init()
	{
	}

	function getConfig()
	{
		static $config = null;
		if(is_null($config))
		{
			$oModuleModel = ModuleModel::getInstance();
			$config = $oModuleModel->getModuleConfig('sticker');
			if(!$config)
			{
				$config = new stdClass;
			}

			unset($config->body);
			unset($config->_filter);
			unset($config->error_return_url);
			unset($config->act);
			unset($config->module);
		}

		return $config;
	}

	function getCommentStickerList(){
		$logged_info =  Context::get('logged_info');
		$sticker_array = $this->getDefaultSticker();

		$defaultStickerCount = count($sticker_array);
		$page = Context::get('page') ? Context::get('page') : 1;
		$date = date('YmdHis');

		$list_count = Rhymix\Framework\UA::isMobile() ? 5 : 12;

		if($logged_info){
			$args = new stdClass();
			$args->page = $page;
			$args->list_count = $page == 1 ? ($list_count-$defaultStickerCount) : $list_count;
			$args->page_count = 2;
			$args->order_type = 'asc';
			$args->member_srl = $logged_info->member_srl;
			$args->date = $date;
			$output2 = executeQueryArray('sticker.getStickerMylist', $args);

			$count = $page > 1 || $defaultStickerCount == 5 ? $defaultStickerCount : 0;

			if($page > 1){
				unset($sticker_array);
				$sticker_array = array();
				$prev_page = new stdClass();
				$prev_page->page = $page-1;
				$prev_page->list_count = $list_count;
				$prev_page->order_type = 'asc';
				$prev_page->member_srl = $logged_info->member_srl;
				$prev_page->date = $date;
				$output = executeQueryArray('sticker.getStickerMylist', $prev_page);
				$prev_data = $output->data;
				$prev_page_count = count($output->data);

				if($prev_page_count > $list_count-$defaultStickerCount){
					end($prev_data);
					$countMovePos = $defaultStickerCount && $defaultStickerCount - ($list_count - $prev_page_count) > 0 ? $defaultStickerCount - ($list_count - $prev_page_count) : $defaultStickerCount;
					for($i=1; $i<$countMovePos; $i++){
						prev($prev_data);
					}
					for($i=$countMovePos; $i>0; $i--){
						$current = current($prev_data);

						$args = new stdClass();
						$args->sticker_srl = $current->sticker_srl;
						$args->no = 0;
						$output1 = executeQueryArray('sticker.getStickerMainImage', $args);

						$obj = new stdClass();
						$obj->sticker_srl = $current->sticker_srl;
						$obj->title = $current->title;
						$obj->main_image = isset($output1->data[0]->url) ? $output1->data[0]->url : '';

						if($i !== 1){
							next($prev_data);
						}
						array_push($sticker_array, $obj);
						$count++;
					}
				}
			}


			foreach($output2->data as $key=>$sticker){
				if($count >= $list_count){
					break;
				}
				$args1 = new stdClass();
				$args1->sticker_srl = $sticker->sticker_srl;
				$args1->no = 0;
				$output1 = executeQueryArray('sticker.getStickerMainImage', $args1);
			
				$obj = new stdClass();
				$obj->sticker_srl = $sticker->sticker_srl;
				$obj->title = $sticker->title;
				$obj->main_image = isset($output1->data[0]->url) ? $output1->data[0]->url : '';
				array_push($sticker_array, $obj);
				$count++;
			}

			//$this->add("page_navigation", $output2->page_navigation);

		}

		$this->add("sticker", $sticker_array);

	}

	function getStickerPickerList(){
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info ? $logged_info->member_srl : 0;
		$config = $this->getConfig();
		$sticker_array = array();
		$seen = array();

		foreach(explode(',', $config->default_sticker ?? '') as $sticker_srl){
			$sticker_srl = trim($sticker_srl);
			if(!$sticker_srl || isset($seen[$sticker_srl])) continue;
			$pack = $this->_getPickerPack($sticker_srl);
			if($pack){
				$sticker_array[] = $pack;
				$seen[$sticker_srl] = true;
			}
		}

		if($member_srl){
			$args = new stdClass();
			$args->page = 1;
			$args->list_count = 10000;
			$args->page_count = 1;
			$args->order_type = 'asc';
			$args->member_srl = $member_srl;
			$args->date = date('YmdHis');
			$output = executeQueryArray('sticker.getStickerMylist', $args);
			if(!$output->toBool()) return $output;
			foreach((array)$output->data as $sticker){
				if(isset($seen[$sticker->sticker_srl])) continue;
				$pack = $this->_getPickerPack($sticker->sticker_srl);
				if($pack){
					$sticker_array[] = $pack;
					$seen[$sticker->sticker_srl] = true;
				}
			}
		}

		$this->add('sticker', $sticker_array);
	}

	function getStickerElemList(){
		$sticker_srl = Context::get('sticker_srl');
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info ? $logged_info->member_srl : 0;

		if(!$sticker_srl){
			return $this->createObject(-1,'invalid_sticker');
		}

		$isDefaultSticker = $this->checkDefaultSticker($sticker_srl);
		if(!$isDefaultSticker){
			if(!$member_srl){
				return $this->createObject(-1,'invalid_sticker');
			}

			$isAccessable = $this->checkBuySticker($member_srl, $sticker_srl);
			if(!$isAccessable){
				return $this->createObject(-1,'invalid_sticker');
			}
		}

		$stickerImageArray = array();

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQueryArray('sticker.getStickerImage', $args);
		if(empty($output->data)){
			return $this->createObject(-1,'invalid_sticker');
		}
		foreach($output->data as $value){
			array_push($stickerImageArray, $this->_getStickerMedia($value));
		}

		$this->add("stickerImage", $stickerImageArray);

	}

	function resolveStickers(){
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info ? $logged_info->member_srl : 0;
		$requested = json_decode((string)Context::get('stickers'), true);
		if(!is_array($requested)) return $this->createObject(-1, 'invalid_sticker');

		$resolved = array();
		$seen = array();
		foreach(array_slice($requested, 0, 200) as $item){
			$sticker_srl = (int)($item['sticker_srl'] ?? 0);
			$sticker_file_srl = (int)($item['sticker_file_srl'] ?? 0);
			$key = $sticker_srl . '|' . $sticker_file_srl;
			if(!$sticker_srl || !$sticker_file_srl || isset($seen[$key])) continue;
			$seen[$key] = true;
			$obj = new stdClass();
			$obj->sticker_srl = $sticker_srl;
			$obj->sticker_file_srl = $sticker_file_srl;
			$obj->valid = false;
			if(!$this->_canUseSticker($member_srl, $sticker_srl, $logged_info)){
				$resolved[] = $obj;
				continue;
			}
			$args = new stdClass();
			$args->sticker_file_srl = $sticker_file_srl;
			$output = executeQuery('sticker.getStickerByStickerFileSrl', $args);
			if(!$output->toBool() || empty($output->data) || (int)$output->data->sticker_srl !== $sticker_srl || $output->data->status === 'STOP'){
				$resolved[] = $obj;
				continue;
			}
			$media = $this->_getStickerMedia($output->data);
			$obj->valid = true;
			$obj->title = $output->data->title;
			$obj->name = $media->name;
			$obj->type = $media->type;
			$obj->url = $media->url;
			$obj->poster = $media->poster;
			$resolved[] = $obj;
		}

		$this->add('stickers', $resolved);
	}

	function _getPickerPack($sticker_srl){
		$sticker = $this->getSticker($sticker_srl);
		if(!$sticker || $sticker->status === 'STOP') return false;
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->no = 0;
		$output = executeQueryArray('sticker.getStickerMainImage', $args);
		if(!$output->toBool() || empty($output->data[0])) return false;
		$media = $this->_getStickerMedia($output->data[0]);
		$obj = new stdClass();
		$obj->sticker_srl = $sticker->sticker_srl;
		$obj->title = $sticker->title;
		$obj->main_image = $media->poster;
		$obj->type = $media->type;
		$obj->url = $media->url;
		$obj->poster = $media->poster;
		return $obj;
	}

	function _getStickerMedia($value){
		$obj = new stdClass();
		$obj->sticker_srl = $value->sticker_srl ?? null;
		$obj->sticker_file_srl = $value->sticker_file_srl;
		$name = pathinfo((string)$value->file_name, PATHINFO_FILENAME);
		$obj->name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8', false);
		$obj->type = str_ends_with(strtolower((string)$value->url), '.mp4') ? 'video' : 'image';
		$obj->url = $value->url;
		$obj->poster = $obj->type === 'video' ? substr($value->url, 0, -4) . '.webp' : $value->url;
		return $obj;
	}

	function _canUseSticker($member_srl, $sticker_srl, $logged_info = null){
		if($logged_info && ($logged_info->is_admin ?? 'N') === 'Y') return true;
		return $this->checkDefaultSticker($sticker_srl) || ($member_srl && $this->checkBuySticker($member_srl, $sticker_srl));
	}

	function getCommentSticekrCountByDocumentSrl($document_srl = 0, $member_srl = 0){
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->member_srl = $member_srl;
		$args->content = "{@sticker:";
		$output = executeQuery('sticker.getCommentStickerByMemberSrl', $args);
		if(!$output->toBool()){
			return false;
		}
		$comments = $output->data;
		$typeComment = gettype($comments);
		$count = 0;

		if($typeComment === 'object'){
			if(preg_match('/{@sticker:[0-9]+\|[0-9]+}/i', $comments->content)){
				$count++;
			}
		} else {
			foreach($comments as $value){
				if(preg_match('/{@sticker:[0-9]+\|[0-9]+}/i', $value->content)){
					$count++;
				}
			}
		}

		return $count;
	}

	function getSticker($sticker_srl){
		if(!$sticker_srl){
			return false;
		}

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.getSticker', $args);
		return !empty($output->data) ? $output->data : false;
	}

	function getDefaultSticker(){
		$config = $this->getConfig();
		$defaultSticker = isset($config->default_sticker) ? $config->default_sticker : '';
		$sticker = explode(',', $defaultSticker);
		$stickerArray = array();
		foreach($sticker as $key=>$value){
			$value = trim($value);
			$oSticker = $this->getSticker($value);

			if($key < 5 && $oSticker && $oSticker->status != "STOP"){
				$args = new stdClass();
				$args->sticker_srl = $value;
				$args->no = 0;
				$output = executeQueryArray('sticker.getStickerMainImage', $args);

				$obj = new stdClass();
				$obj->sticker_srl = $oSticker->sticker_srl;
				$obj->title = $oSticker->title;
				$obj->main_image = isset($output->data[0]->url) ? $output->data[0]->url : '';

				array_push($stickerArray, $obj);
			}
		}

		return $stickerArray;
	}

	function checkDefaultSticker($sticker_srl){
		$config = $this->getConfig();
		$defaultSticker = isset($config->default_sticker) ? $config->default_sticker : '';
		$sticker = explode(',', $defaultSticker);
		foreach($sticker as $value){
			$value = trim($value);
			if($value == $sticker_srl){
				return true;
				break;
			}
		}

		return false;
	}

	function checkBuySticker($member_srl = 0, $sticker_srl = 0){
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->sticker_srl = $sticker_srl;
		$args->date = date("YmdHis");
		$output = executeQuery('sticker.getStickerBuyCheck', $args);
		return (!$output->toBool() || $output->data->count == 0) ? FALSE : TRUE;
	}

}

/* End of file sticker.model.php */
/* Location: ./modules/sticker/sticker.model.php */
