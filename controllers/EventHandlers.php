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
}
