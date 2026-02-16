<?php
/**
 * LINE Content API 服務
 *
 * 從 LINE 下載圖片並存入 WordPress Media Library
 * 暫存機制使用 user_meta
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ContentService {

	private const CONTENT_API = 'https://api-data.line.me/v2/bot/message/%s/content';
	private const META_KEY    = 'line_hub_temp_images';

	/**
	 * 下載圖片並上傳到 WordPress Media Library
	 *
	 * @param string $message_id     LINE Message ID
	 * @param int    $user_id        WordPress User ID（post_author）
	 * @param bool   $skip_thumbnail 是否跳過縮圖生成
	 * @return int|\WP_Error 成功返回 attachment_id，失敗返回 WP_Error
	 */
	public function downloadAndUpload( string $message_id, int $user_id, bool $skip_thumbnail = false ) {
		$access_token = SettingsService::get( 'general', 'access_token', '' );
		if ( empty( $access_token ) ) {
			return new \WP_Error( 'no_access_token', 'LINE Channel Access Token 未設定' );
		}

		// 呼叫 LINE Content API
		$url      = sprintf( self::CONTENT_API, $message_id );
		$response = wp_remote_get( $url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
			],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new \WP_Error(
				'content_api_error',
				sprintf( 'LINE Content API 錯誤 (HTTP %d)', $status_code )
			);
		}

		$content = wp_remote_retrieve_body( $response );
		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', '下載的內容為空' );
		}

		// 從 Content-Type 判斷副檔名
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$ext          = 'jpg';
		if ( strpos( $content_type, 'png' ) !== false ) {
			$ext = 'png';
		} elseif ( strpos( $content_type, 'gif' ) !== false ) {
			$ext = 'gif';
		} elseif ( strpos( $content_type, 'webp' ) !== false ) {
			$ext = 'webp';
		}

		// 產生唯一檔名
		$filename = 'line-image-' . $message_id . '-' . time() . '.' . $ext;

		// 寫入暫存檔
		$upload_dir = wp_upload_dir();
		$tmp_file   = $upload_dir['basedir'] . '/' . $filename;

		if ( file_put_contents( $tmp_file, $content ) === false ) {
			return new \WP_Error( 'write_failed', '無法寫入暫存檔案' );
		}

		// 跳過縮圖生成（加速處理，避免 Reply Token 過期）
		if ( $skip_thumbnail ) {
			add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
		}

		// 載入 WordPress Media 函式
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		];

		$attachment_id = media_handle_sideload( $file_array, 0, null, [
			'post_author' => $user_id,
		] );

		// 恢復縮圖生成
		if ( $skip_thumbnail ) {
			remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
		}

		// 清理暫存檔（sideload 失敗時可能仍存在）
		if ( file_exists( $tmp_file ) ) {
			@unlink( $tmp_file );
		}

		return $attachment_id;
	}

	/**
	 * 將 attachment_id 存入 user_meta 暫存
	 *
	 * @param int $user_id       WordPress User ID
	 * @param int $attachment_id WordPress Attachment ID
	 */
	public function saveTempImage( int $user_id, int $attachment_id ): void {
		$images   = $this->getTempImages( $user_id );
		$images[] = $attachment_id;
		update_user_meta( $user_id, self::META_KEY, $images );
	}

	/**
	 * 取得暫存的 attachment_id 陣列
	 *
	 * @param int $user_id WordPress User ID
	 * @return array attachment_id 陣列
	 */
	public function getTempImages( int $user_id ): array {
		$images = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $images ) ? $images : [];
	}

	/**
	 * 清除暫存
	 *
	 * @param int $user_id WordPress User ID
	 */
	public function clearTempImages( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}
}
