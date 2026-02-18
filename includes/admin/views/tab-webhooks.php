<?php
/**
 * Webhook Tab 模板
 *
 * 可用變數：$events (array) — WebhookLogger::getRecent(20) 的結果
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="line-hub-card">
    <h2>Webhook 事件記錄</h2>

    <?php if (empty($events)): ?>
        <p style="color: #999;">尚無 Webhook 事件記錄。當 LINE 用戶與您的 Bot 互動時，事件會顯示在這裡。</p>
    <?php else: ?>
        <p style="color: #666;">最近 <?php echo count($events); ?> 筆事件</p>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 150px;">事件類型</th>
                    <th style="width: 200px;">LINE UID</th>
                    <th style="width: 180px;">時間</th>
                    <th style="width: 80px;">狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?php echo esc_html($event['id']); ?></td>
                        <td><code><?php echo esc_html($event['event_type']); ?></code></td>
                        <td>
                            <?php if (!empty($event['line_uid'])): ?>
                                <code style="font-size: 11px;">
                                    <?php echo esc_html(substr($event['line_uid'], 0, 15) . '...'); ?>
                                </code>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $time_diff = human_time_diff(strtotime($event['received_at']), time());
                            echo esc_html($time_diff . ' 前');
                            ?>
                        </td>
                        <td>
                            <?php if ($event['processed']): ?>
                                <span style="color: #46b450;">✓</span>
                            <?php else: ?>
                                <span style="color: #999;">⋯</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small"
                                    data-toggle-payload="<?php echo esc_attr($event['id']); ?>">查看 Payload</button>
                            <div id="payload-<?php echo esc_attr($event['id']); ?>"
                                 style="display: none; margin-top: 10px; padding: 10px;
                                        background: #f5f5f5; border: 1px solid #ddd; border-radius: 3px;">
                                <pre style="overflow-x: auto; font-size: 12px; max-height: 300px;"><?php
                                    echo esc_html(json_encode(
                                        json_decode($event['payload']),
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                                    ));
                                ?></pre>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
