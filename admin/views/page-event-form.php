<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bch-wrap">
    <h1><?php echo $event ? 'Edit Event' : 'Add New Event'; ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'bch_save_event' ); ?>
        <input type="hidden" name="action" value="bch_save_event">
        <?php if ( $event ) : ?>
            <input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="title">Event Title *</label></th>
                <td><input type="text" id="title" name="title" class="regular-text" required value="<?php echo esc_attr( $event->title ?? '' ); ?>"></td>
            </tr>
            <tr>
                <th><label for="description">Description</label></th>
                <td><textarea id="description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $event->description ?? '' ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="event_date">Event Date &amp; Time *</label></th>
                <td><input type="datetime-local" id="event_date" name="event_date" required value="<?php echo esc_attr( isset( $event->event_date ) ? str_replace( ' ', 'T', $event->event_date ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th><label for="venue">Venue</label></th>
                <td><input type="text" id="venue" name="venue" class="regular-text" value="<?php echo esc_attr( $event->venue ?? 'Blockchain Centre Hub' ); ?>"></td>
            </tr>
            <tr>
                <th><label for="banner_ipfs">Banner IPFS URI</label></th>
                <td>
                    <input type="text" id="banner_ipfs" name="banner_ipfs" class="large-text" placeholder="ipfs://Qm..." value="<?php echo esc_attr( $event->banner_ipfs ?? '' ); ?>">
                    <p class="description">IPFS URI of the event banner image (used as NFT image). Example: <code>ipfs://QmXxx...</code></p>
                </td>
            </tr>
        </table>

        <?php submit_button( $event ? 'Update Event' : 'Create Event' ); ?>
    </form>
</div>
