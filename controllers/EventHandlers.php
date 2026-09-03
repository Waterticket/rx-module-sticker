<?php

namespace Rhymix\Modules\Sticker\Controllers;

use sticker;
use StickerModel;

/**
 * 리소스 모듈
 *
 * Copyright (c) Waterticket
 *
 * Generated with https://www.poesis.dev/tools/rxmodulegen
 */
class EventHandlers extends sticker
{
	public function beforeInsertNotify($obj)
	{
		$config = StickerModel::getInstance()->getConfig();
		if ($config->use !== 'Y' || empty($obj->target_summary) || !preg_match('/{@sticker:[0-9]+\|[0-9]+}/i', $obj->target_summary))
		{
			return;
		}

		if (($config->notify_message_type ?? 'text') === 'none')
		{
			return;
		}

		$obj->target_summary = preg_replace('/{@sticker:[0-9]+\|[0-9]+}/i', '[스티커]', $obj->target_summary);
	}

	/** Register the RoundEditor implementation from this module, not the editor core. */
	public function beforeRoundEditorExtensions($context)
	{
		$config = StickerModel::getInstance()->getConfig();
		if ($config->use !== 'Y')
		{
			return $this->createObject();
		}

		if (!isset($context->extensions) || !is_array($context->extensions)) $context->extensions = array();

		$asset_files = array_merge(
			[\RX_BASEDIR . '/modules/sticker/assets/roundeditor.js'],
			glob(\RX_BASEDIR . '/modules/sticker/assets/*') ?: array()
		);

		$asset_version = max(array_map('filemtime', $asset_files));
		$context->extensions[] = array(
			'id' => 'rhymix.sticker',
			'script' => './modules/sticker/assets/roundeditor.js?v=' . $asset_version,
			'mode' => 'extension', 'format' => 'module', 'required' => true, 'priority' => 0,
			'config' => array(
				'mid' => 'sticker',
				'listUrl' => getUrl('', 'mid', 'sticker'),
				'myListUrl' => getUrl('', 'mid', 'sticker', 'act', 'dispStickerMylist'),
				'pickerTemplate' => new \Rhymix\Framework\Template('./modules/sticker/assets/roundeditor', 'picker')->compile(),
			),
		);

		return $this->createObject();
	}
}
