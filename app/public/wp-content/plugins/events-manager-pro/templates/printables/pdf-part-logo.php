<td class="logo">
	<?php
	if( get_option('dbem_bookings_pdf_logo') ){
		$url = esc_url(get_option('dbem_bookings_pdf_logo'));
		echo '<img src="'.$url .'" style="width:200px;">';
	} else {
		echo get_option('dbem_bookings_pdf_logo_alt', get_bloginfo('name'));
	}
	?>
</td>