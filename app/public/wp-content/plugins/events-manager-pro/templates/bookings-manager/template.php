<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
	
	<?php do_action('em_bookings_manager_template_head'); ?>
	<title><?php esc_html_e('Event Tickets', 'em-pro'); /* TODO - Remove this */ ?></title>

	<style>
		body {
			max-width: 800px;
			margin: auto;
		}
		/* Hide content and spinners */
		.hidden, .loading-content, .loading .loaded {
			display: none;
			visibility: hidden;
		}
		.loading .loading-content {
			display: inline-block;
			visibility: visible;
		}
		/* ticket single page */
		.ticket-single .checkin-status .attendance-status > i {
			font-size: 20rem;
			line-height: 20rem;
			display: block;
			margin-bottom: 20px;
		}
		/* Override some accordion coloring */
		.nav-secondary .link-secondary {
			text-decoration:none;
		}
		.accordion-button:focus, .btn:focus, a:focus {
			outline: none;
			box-shadow: none;
		}
		.accordion-button:not(.collapsed) {
			color: var(--bs-accordion-color);
			background-color: var(--bs-accordion-bg);
		}
		.accordion-button:not(.collapsed):after {
			background-image: var(--bs-accordion-btn-icon);
		}
	</style>
</head>
<body class="text-center">
	<nav class="navbar sticky-top border-bottom" style="background-color: white;">
		<div class="container-fluid">
			<div class="navbar-brand">
				<img src="<?php echo get_site_icon_url( 25 ); ?>" alt="" width="25" height="25" class="d-inline-block align-text-top me-1">
				<?php echo get_bloginfo('name'); ?>
			</div>
		</div>
	</nav>
	<?php
	if( !empty(\EM_Pro\Bookings_Manager_Frontend::$data['view']) ) {
		$template = '';
		switch( \EM_Pro\Bookings_Manager_Frontend::$data['view'] ) {
			case 'ticket_booking':
				$template = emp_locate_template('bookings-manager/part-ticket.php', false);
				if (!$template) {
					$template = 'part-ticket.php';
				}
				break;
			case 'booking':
				$template = emp_locate_template('bookings-manager/part-booking.php', false);
				if (!$template) {
					$template = 'part-booking.php';
				}
				break;
		}
		if( !empty($template) ) {
			include($template);
		}
	}
	?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/js-cookie@3.0.1/dist/js.cookie.min.js" crossorigin="anonymous"></script>
	<script>
		<?php
		emp_locate_template('bookings-manager/template.js', true);
		do_action('em_bookings_manager_template_scripts');
		?>
	</script>
</body>
</html>