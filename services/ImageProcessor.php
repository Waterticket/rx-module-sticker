<?php

namespace Rhymix\Modules\Sticker\Services;

use Rhymix\Framework\Image;
use Rhymix\Framework\Security;
use Rhymix\Framework\Storage;

/**
 * 스티커 이미지 업로드 후처리(검증, 리사이즈/포맷 변환)를 담당한다.
 *
 * validate()는 항상 동기로 실행되어 업로드 수락/거부를 즉시 결정하고, process()는 실제
 * GD 리사이즈/변환을 수행한다. 큐가 켜져 있으면 컨트롤러가 process() 호출을 미루고 원본
 * 파일을 그대로 등록한 뒤 processAsync()를 큐에 넘긴다(sticker.controller.php 참고).
 */
class ImageProcessor
{
	const CODE_IMAGE_TOO_SMALL = 2;
	const CODE_IMAGE_TOO_LARGE = 3;

	/**
	 * 업로드된 파일이 처리 가능한 이미지인지 검증한다.
	 *
	 * @return object|false|int 검증 통과 시 object, 확장자/포맷 불량 시 false,
	 *                          최소 크기 미달 시 CODE_IMAGE_TOO_SMALL, 4K 초과 시 CODE_IMAGE_TOO_LARGE
	 */
	public static function validate(string $uploaded_filename, string $source_filename, object $module_config)
	{
		if (!$uploaded_filename || !preg_match('/\.(jpg|jpeg|gif|png)$/i', $uploaded_filename, $file_ext))
		{
			return false;
		}

		$source_fileinfo = getimagesize($uploaded_filename);
		if (!$source_fileinfo)
		{
			return false;
		}

		$width = $source_fileinfo[0];
		$height = $source_fileinfo[1];
		$mime = $source_fileinfo['mime'];

		if ($width < $module_config->image_min_width || $height < $module_config->image_min_height)
		{
			return self::CODE_IMAGE_TOO_SMALL;
		}
		if ($width > 4096 || $height > 2160)
		{
			return self::CODE_IMAGE_TOO_LARGE;
		}

		if ($mime === 'image/gif' && $file_ext[0] !== '.gif')
		{
			$source_filename = substr($source_filename, 0, strrpos($source_filename, '.')) . '.gif';
		}

		$min_width = $module_config->maxPx;
		$min_height = $module_config->maxPx;
		$rate = $width / $height;
		$crop_mode = ($rate > 1.8 || $rate < 0.65 || $width >= $min_width || $height >= $min_height) ? 'crop' : 'ratio';

		return (object)[
			'width' => $width,
			'height' => $height,
			'mime' => $mime,
			'source_filename' => $source_filename,
			'crop_mode' => $crop_mode,
		];
	}

	/**
	 * 검증된 이미지를 실제로 리사이즈/변환한다.
	 *
	 * @return object|false 처리 결과(url, source_filename, changed, file_size?), GD 변환
	 *                       실패 시 false
	 */
	public static function process(string $uploaded_filename, object $validated, object $module_config)
	{
		if ($validated->mime === 'image/gif')
		{
			$converted = self::_convertGifToMp4($uploaded_filename, $validated, $module_config);
			if ($converted !== null)
			{
				return $converted;
			}
		}

		if ($module_config->resizing === 'N')
		{
			return (object)['url' => $uploaded_filename, 'source_filename' => $validated->source_filename, 'changed' => false];
		}

		if ($validated->mime === 'image/gif')
		{
			return self::_processGif($uploaded_filename, $validated, $module_config);
		}

		return self::_processStatic($uploaded_filename, $validated, $module_config);
	}

	/**
	 * finalizeFile()와 processAsync()에서 공통으로 쓰는 files 테이블 갱신.
	 * process()가 실제로 파일을 새로 만들었을 때만(changed) 갱신한다.
	 */
	public static function finalizeFile(int $file_srl, object $result): void
	{
		if (empty($result->changed))
		{
			return;
		}

		executeQuery('sticker.updateFileInfo', (object)[
			'file_srl' => $file_srl,
			'uploaded_filename' => $result->url,
			'source_filename' => $result->source_filename,
			'file_size' => $result->file_size,
		]);
	}

	/**
	 * 큐 워커(cron.php)가 호출하는 비동기 진입점. Queue::addTask()의 handler 문자열 규칙에
	 * 따라 정적 메서드로 등록된다. 업로더 본인의 Context가 없으므로 필요한 값은 모두
	 * $args로 전달받는다(sticker.controller.php::_queueImageProcessing() 참고).
	 *
	 * 실패 시 예외를 던져 큐 워커의 error_log에 남긴다 - 이미 원본 파일이 sticker_files에
	 * 등록되어 있으므로 처리에 실패해도 사용자에게는 원본 이미지가 그대로 보인다.
	 */
	public static function processAsync(object $args): void
	{
		$module_config = \StickerModel::getInstance()->getConfig();

		$validated = self::validate($args->uploaded_filename, $args->source_filename, $module_config);
		if (!is_object($validated))
		{
			throw new \RuntimeException('sticker image validation failed on background processing: ' . $args->uploaded_filename);
		}

		$result = self::process($args->uploaded_filename, $validated, $module_config);
		if ($result === false)
		{
			throw new \RuntimeException('sticker image conversion failed on background processing: ' . $args->uploaded_filename);
		}

		self::finalizeFile($args->file_srl, $result);

		executeQuery('sticker.updateStickerFile', (object)[
			'sticker_srl' => $args->sticker_srl,
			'sticker_file_srl' => $args->sticker_file_srl,
			'member_srl' => $args->member_srl,
			'file_srl' => $args->file_srl,
			'file_name' => cut_str(htmlspecialchars($result->source_filename, ENT_QUOTES, 'UTF-8', false), 60),
			'url' => $result->url,
			'regdate' => $args->regdate,
		]);
	}

	/**
	 * 움직이는 GIF를 MP4로 변환한다 - modules/file/file.controller.php::adjustUploadedImage()의
	 * gif2mp4 변환(1229, 1345-1372줄)과 동일한 조건·ffmpeg 옵션을 사용한다. 다만 활성화 여부는
	 * file 모듈의 전역 image_autoconv가 아니라 스티커 자체 설정($module_config->gif2mp4)을 따른다.
	 *
	 * ffmpeg 실행 파일 경로는 file 모듈 설정(FileModel::getFileConfig()->ffmpeg_command)을 그대로
	 * 재사용한다 - 사이트에 이미 있는 ffmpeg를 스티커 업로드에도 그대로 쓰는 것이므로 경로를
	 * 이중으로 설정할 이유가 없다.
	 *
	 * @return object|null 변환 성공 시 결과 object, 조건 미충족/변환 실패 시 null(GIF 리사이즈로 폴백)
	 */
	private static function _convertGifToMp4(string $uploaded_filename, object $validated, object $module_config): ?object
	{
		if (($module_config->gif2mp4 ?? 'N') !== 'Y')
		{
			return null;
		}
		if (!Image::isAnimatedGIF($uploaded_filename))
		{
			return null;
		}

		$file_config = \FileModel::getFileConfig();
		if (!function_exists('exec') || !Storage::isExecutable($file_config->ffmpeg_command))
		{
			return null;
		}

		$width = $module_config->maxPx - ($module_config->maxPx % 2);
		$height = $width;

		$path = substr($uploaded_filename, 0, strrpos($uploaded_filename, '/') + 1);
		$basename = Security::getRandom(32, 'hex');
		$output_name = $path . $basename . '.mp4';

		$command = Security::sanitize($file_config->ffmpeg_command, 'command');
		$command .= ' -nostdin -i ' . escapeshellarg($uploaded_filename);
		$command .= ' -movflags +faststart -pix_fmt yuv420p -c:v libx264 -crf 23';
		$command .= sprintf(' -vf "scale=%d:%d"', $width, $height);
		$command .= ' ' . escapeshellarg($output_name);
		if (!\RX_WINDOWS && !empty($file_config->ffmpeg_timeout) && $file_config->ffmpeg_timeout > 0)
		{
			$command = 'timeout -k1 ' . intval($file_config->ffmpeg_timeout) . ' ' . $command;
		}

		@exec($command, $exec_output, $return_var);
		if ($return_var !== 0 || !file_exists($output_name))
		{
			return null;
		}

		// 정지 프레임을 포스터로 저장한다. GD의 imagecreatefromgif는 원래 첫 프레임만 읽으므로
		// 이 호출 자체가 "움짤의 첫 장면" 썸네일이 된다. mp4와 같은 basename을 써서 별도
		// DB 컬럼 없이 "xxx.mp4" -> "xxx.webp" 관례로 찾을 수 있게 한다(_media.blade.php,
		// 관리자 .html 템플릿의 poster="{substr($url, 0, -4)}.webp" 참고). jpg가 아니라 webp를
		// 쓰는 이유는 투명 배경 스티커가 로딩 전에 흰/검은 박스로 보이는 걸 막기 위해서다
		// (jpg는 알파 채널을 지원하지 않음). 실패해도 치명적이지 않으므로(포스터 없는 video는
		// 브라우저가 알아서 처리) 반환값은 확인하지 않는다.
		\FileHandler::createImageFile($uploaded_filename, $path . $basename . '.webp', $width, $height, 'webp', 'fill');

		\FileHandler::removeFile($uploaded_filename);

		return (object)[
			'url' => $output_name,
			'source_filename' => substr($validated->source_filename, 0, strrpos($validated->source_filename, '.')) . '.mp4',
			'changed' => true,
			'file_size' => filesize($output_name),
		];
	}

	/**
	 * GIF 전용 처리(정지/애니메이션 GIF 리사이즈). MP4 변환은 process()에서 이 메서드보다
	 * 먼저 _convertGifToMp4()로 시도되고, 조건 미충족이거나 실패했을 때만 여기로 내려온다.
	 */
	private static function _processGif(string $uploaded_filename, object $validated, object $module_config): object
	{
		$min_width = $module_config->maxPx;
		$min_height = $module_config->maxPx;

		$larger = max($validated->width, $validated->height);
		$smaller = min($validated->width, $validated->height);
		$ratio = $smaller / $larger;

		$as_is = (object)['url' => $uploaded_filename, 'source_filename' => $validated->source_filename, 'changed' => false];

		if ($validated->width <= $min_width && $validated->height <= $min_height && $ratio > 0.4)
		{
			return $as_is;
		}

		require_once __DIR__ . '/../sticker.lib.php';

		$path = substr($uploaded_filename, 0, strrpos($uploaded_filename, '/') + 1);
		$output_name = $path . Security::getRandom(32, 'hex') . '.gif';

		$resized = resizeGIF($uploaded_filename, $output_name, $min_width, $min_height);
		if (!$resized)
		{
			return $as_is;
		}

		$keep_resized = filesize($uploaded_filename) > filesize($output_name) || $module_config->gifResizingIf === 'N' || $ratio < 0.4;
		if (!$keep_resized)
		{
			if ($uploaded_filename !== $output_name && file_exists($output_name))
			{
				\FileHandler::removeFile($output_name);
			}
			return $as_is;
		}

		if ($uploaded_filename !== $output_name && file_exists($uploaded_filename))
		{
			\FileHandler::removeFile($uploaded_filename);
		}

		return (object)[
			'url' => $output_name,
			'source_filename' => $validated->source_filename,
			'changed' => true,
			'file_size' => filesize($output_name),
		];
	}

	/**
	 * jpg/png 전용 처리 - jpg로 크롭/리사이즈해서 저장한다.
	 */
	private static function _processStatic(string $uploaded_filename, object $validated, object $module_config)
	{
		$min_width = $module_config->maxPx;
		$min_height = $module_config->maxPx;

		$path = substr($uploaded_filename, 0, strrpos($uploaded_filename, '/') + 1);
		$output_name = $path . Security::getRandom(32, 'hex') . '.jpg';
		$source_filename = substr($validated->source_filename, 0, strrpos($validated->source_filename, '.')) . '.jpg';

		if (!\FileHandler::createImageFile($uploaded_filename, $output_name, $min_width, $min_height, 'jpg', $validated->crop_mode))
		{
			return false;
		}

		if ($uploaded_filename !== $output_name && file_exists($uploaded_filename))
		{
			\FileHandler::removeFile($uploaded_filename);
		}

		return (object)[
			'url' => $output_name,
			'source_filename' => $source_filename,
			'changed' => true,
			'file_size' => filesize($output_name),
		];
	}
}
