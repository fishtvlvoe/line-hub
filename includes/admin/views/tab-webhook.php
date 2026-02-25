<?php
/**
 * Webhook Tab 模板
 *
 * Webhook 事件記錄。
 *
 * 可用變數：
 *   $events (array) — WebhookLogger::getRecent(20)
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="card lh-card-narrow">
    <h2><?php esc_html_e('Webhook Event Log', 'line-hub'); ?></h2>

    <?php if (empty($events)) : ?>
        <p class="lh-text-muted">
            <?php esc_html_e('No Webhook events recorded yet. Events will appear here when LINE users interact with your Bot.', 'line-hub'); ?>
        </p>
    <?php else : ?>
        <p class="lh-text-secondary"><?php
            /* translators: %d: number of events */
            printf(esc_html__('Recent %d events', 'line-hub'), count($events));
        ?></p>

        <table class="wp-list-table widefat fixed striped lh-mt-16">
            <thead>
                <tr>
                    <th class="lh-col-60">ID</th>
                    <th class="lh-col-140"><?php esc_html_e('Event Type', 'line-hub'); ?></th>
                    <th class="lh-col-180">LINE UID</th>
                    <th class="lh-col-160"><?php esc_html_e('Time', 'line-hub'); ?></th>
                    <th class="lh-col-60"><?php esc_html_e('Status', 'line-hub'); ?></th>
                    <th><?php esc_html_e('Action', 'line-hub'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event) : ?>
                    <tr>
                        <td><?php echo esc_html($event['id']); ?></td>
                        <td>
                            <code><?php echo esc_html($event['event_type']); ?></code>
                        </td>
                        <td>
                            <?php if (!empty($event['line_uid'])) : ?>
                                <code class="lh-code-uid">
                                    <?php echo esc_html(substr($event['line_uid'], 0, 15) . '...'); ?>
                                </code>
                            <?php else : ?>
                                <span class="lh-text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $time_diff = human_time_diff(
                                strtotime($event['received_at']),
                                time()
                            );
                            /* translators: %s: human-readable time difference */
                            printf(esc_html__('%s ago', 'line-hub'), esc_html($time_diff));
                            ?>
                        </td>
                        <td>
                            <?php if ($event['processed']) : ?>
                                <span class="lh-text-success">&#10003;</span>
                            <?php else : ?>
                                <span class="lh-text-muted">&#8943;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small"
                                    data-toggle-payload="<?php echo esc_attr($event['id']); ?>">
                                <?php esc_html_e('View Payload', 'line-hub'); ?>
                            </button>
                            <div id="payload-<?php echo esc_attr($event['id']); ?>"
                                 class="lh-payload-panel">
                                <pre class="lh-payload-content"><?php
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
