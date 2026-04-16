<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bch-wrap">
    <h1 class="wp-heading-inline">Blockchain Centre Hub — Events</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bch-events&bch_action=new' ) ); ?>" class="page-title-action">Add New Event</a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Event deleted.</p></div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Date</th>
                <th>Venue</th>
                <th>Attendees</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $events ) ) : ?>
            <tr><td colspan="5">No events yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=bch-events&bch_action=new' ) ); ?>">Create one.</a></td></tr>
        <?php else : ?>
            <?php foreach ( $events as $ev ) :
                global $wpdb;
                $count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}bch_attendees WHERE event_id = %d", $ev->id
                ) );
            ?>
            <tr>
                <td><strong><?php echo esc_html( $ev->title ); ?></strong></td>
                <td><?php echo esc_html( $ev->event_date ); ?></td>
                <td><?php echo esc_html( $ev->venue ); ?></td>
                <td><?php echo (int) $count; ?></td>
                <td>
                    <a href="<?php echo esc_url( admin_url( "admin.php?page=bch-events&bch_action=detail&event_id={$ev->id}" ) ); ?>">View</a>
                    &nbsp;|&nbsp;
                    <a href="<?php echo esc_url( admin_url( "admin.php?page=bch-events&bch_action=edit&event_id={$ev->id}" ) ); ?>">Edit</a>
                    &nbsp;|&nbsp;
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                        <?php wp_nonce_field( 'bch_delete_event' ); ?>
                        <input type="hidden" name="action" value="bch_delete_event">
                        <input type="hidden" name="event_id" value="<?php echo (int) $ev->id; ?>">
                        <button type="submit" class="button-link" onclick="return confirm('Delete this event and all attendees?')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
