<?php
/* @var EM_Booking $EM_Booking */
?>
<div class="header">
	<table class="top" cellpadding="0" cellspacing="0">
		<tr>
			<?php
			include( emp_locate_template('printables/pdf-part-logo.php') );
			?>
			
			<td>
				<p class="title"><?php esc_html_e('Booking Invoice', 'events-manager'); ?></p>
				<?php esc_html_e('Invoice', 'em-pro') ?> # : <?php echo \EM_Pro\Printables\PDFs::get_invoice_number($EM_Booking); ?><br />
				<?php esc_html_e('Date', 'em-pro') ?> : <?php echo $EM_Booking->date->formatDefault(false); ?><br />
			</td>
		</tr>
	</table>
	<?php
	include( emp_locate_template('printables/pdf-part-header-addresses.php') );
	?>
</div>