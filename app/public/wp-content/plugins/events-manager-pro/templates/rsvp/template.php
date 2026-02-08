<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
	
	<?php do_action('em_rsvp_template_head'); ?>
	<title><?php esc_html_e('Booking RSVP', 'em-pro'); /* TODO - Remove this */ ?></title>

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
		#result .flex-fill.show {
			opacity: 1;
			transition: opacity .5s ease-out;
		}
		#result .flex-fill{
			opacity: 0;
		}
		.btn.selected {
			cursor : auto;
			color: var(--bs-btn-hover-color);
			background-color: var(--bs-btn-hover-bg);
			border-color: var(--bs-btn-hover-border-color);
			outline: 0;
			box-shadow: var(--bs-btn-focus-box-shadow);
		}
	</style>
</head>
<body>
	<?php
		if ( empty(\EM\Bookings\RSVP\Endpoint::$data['booking']) ) {
			emp_locate_template( 'rsvp/404.php', true );
		} else {
			?>
			<div id="content" class="px-4 mb-5 mt-3 col-lg-10 col-sm-10 mx-auto">
				<header class="text-center">
					<img src="<?php echo get_site_icon_url( 75 ); ?>" alt="" width="75" class="d-block mx-auto mb-4">
					<h1 class="display-6 fw-bold text-body-emphasis">
						<?php echo get_bloginfo('name'); ?>
					</h1>
					<!-- <p class="lead mb-4"><?php esc_html_e('Please RSVP your booking.', 'em-pro'); ?></p> -->
				</header>
				<main>
					<?php
					emp_locate_template( 'rsvp/part-booking.php', true );
					?>
				</main>
			</div>
			<?php
		}
	?>
	
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/js-cookie@3.0.1/dist/js.cookie.min.js" crossorigin="anonymous"></script>
	<script>
		<?php
		emp_locate_template('rsvp/template.js', true);
		do_action('em_rsvp_template_scripts');
		?>
	</script>
	<div id="result" class="vh-100 d-flex m-auto justify-content-center align-items-center text-start col-11 hidden">
		<div class="flex-fill">
			<div class="border border-3 border-success result-success"></div>
			<div class="border border-3 border-danger result-error"></div>
			<div class="border border-1 rounded-bottom  bg-white p-5">
				<div class="mb-4 text-center">
					<i class="bi bi-check-circle text-success result-success" style="font-size: 75px;"></i>
					<i class="bi bi-x-circle text-danger result-error" style="font-size: 75px;"></i>
				</div>
				<div class="text-center">
					<h1 class="text-success result-success"><?php esc_html_e('Thank You!', 'events-manager'); ?></h1>
					<h1 class="text-danger result-error"><?php esc_html_e('Oops!', 'events-manager'); ?></h1>
					<p id="rsvp-result" class="text-muted"></p>
					<a class="btn btn-outline-success result-success" href=""><?php esc_html_e('View Booking', 'em-pro'); ?></a>
					<a class="btn btn-outline-danger result-error" href=""><?php esc_html_e('Reload Booking', 'em-pro'); ?></a>
				</div>
			</div>
		</div>
	</div>
</body>
</html>