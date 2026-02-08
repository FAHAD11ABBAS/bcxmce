<?php
/* @var EM_Event $EM_Event */
?>
<table cellpadding="0" cellspacing="0">
	<tr>
		<td><?php esc_html_e('Event', 'events-manager'); ?></td>
		<td colspan="2"><?php echo esc_html($EM_Event->event_name); ?></td>
	</tr>
	<tr>
		<td><?php esc_html_e('When', 'events-manager'); ?></td>
		<td colspan="2"><?php echo $EM_Event->output_dates(); ?> @ <?php echo $EM_Event->output_times(); ?></td>
	</tr>
	<?php if( $EM_Event->has_location() ): ?>
		<tr>
			<td><?php esc_html_e('Where', 'events-manager'); ?></td>
			<td colspan="2"><?php echo esc_html($EM_Event->get_location()->location_name); ?></td>
		</tr>
		<tr>
			<td><?php esc_html_e('Address', 'events-manager'); ?></td>
			<td colspan="2"><?php echo esc_html($EM_Event->get_location()->get_full_address()); ?></td>
		</tr>
	<?php elseif( $EM_Event->has_event_location() ): ?>
		<tr>
			<td><?php esc_html_e('Where', 'events-manager'); ?></td>
			<td colspan="2"><?php echo $EM_Event->output_dates(); ?> @ <?php echo $EM_Event->output_times(); ?></td>
		</tr>
	<?php endif; ?>
</table>