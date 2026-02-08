<?php
function em_automation_get_test_data( $type ){
	$booking = array (
		'id' => '747',
		'event_id' => '24226',
		'uuid' => '9dc3a457cabb4533b3319cc2d9eb0588',
		'person_id' => '0',
		'status' => '1',
		'spaces' => '3',
		'price' => 362.95,
		'tax_rate' => 0.19,
		'taxes' => '57.9500',
		'comment' => 'This is a test booking.',
		'meta' =>
			array (
				'attendees' =>
					array (
						38388 =>
							array (
								0 =>
									array (
										'attendee_name' => 'John Smith',
										'choices' =>
											array (
												0 => 'Choice X',
											),
										'checkbox' => '1',
										'bdate' => '2022-10-14',
									),
							),
						38775 =>
							array (
								0 =>
									array (
										'attendee_name' => 'Jane Smith',
										'choices' =>
											array (
												0 => 'Choice X',
												1 => 'Choice Y &Z',
											),
										'checkbox' => '1',
										'bdate' => '2022-10-25',
									),
								1 =>
									array (
										'attendee_name' => 'Anderson Jones',
										'choices' =>
											array (
												0 => 'Choice X',
											),
										'checkbox' => '1',
										'bdate' => '2022-10-04',
									),
							),
					),
				'registration' =>
					array (
						'user_login' => '',
						'user_name' => 'Mister Tester',
						'user_email' => 'test@test.com',
						'date_of_birth' => '1985-10-17',
						'dbem_address' => 'Testing 123',
						'dbem_city' => 'Test',
						'dbem_state' => 'MI',
						'dbem_zip' => '12345',
						'dbem_country' => 'AU',
						'dbem_phone' => '123123123',
						'dbem_fax' => '321321321',
						'dbem_checkboxes' =>
							array (
								0 => 'Test',
								1 => 'Another',
								2 => 'Checkbox',
							),
						'first_name' => 'Mister',
						'last_name' => 'Tester',
					),
				'booking' =>
					array (
						'booking_comment' => 'This is a test booking.',
						'translated_field' => 'Test Field',
						'email_address_text_field' => 'testing@testfield.com',
						'dates' => '2022-10-17,2022-10-27',
						'cat%e9gorie' => 'Autres',
						'checkbox' => '1',
						'time_range' => '12:30 AM,12:45 AM',
					),
				'consent' => '1',
				'gateway' => 'offline',
			),
		'tickets' =>
			array (
				38775 =>
					array (
						'name' => 'Section A',
						'description' => 'Section A',
						'spaces' => 2,
						'price' => 180.0,
						'attendees' =>
							array (
								0 =>
									array (
										'uuid' => 'd66979b154a14117a6192d6c7eae8d19',
										'price' => '90.0000',
										'meta' =>
											array (
												'attendee_name' => 'Anderson Jones',
												'choices' =>
													array (
														0 => 'Choice X',
													),
												'checkbox' => '1',
												'bdate' => '2022-10-04',
											),
									),
								1 =>
									array (
										'uuid' => '3fec4f5b2afb491e870b508109369414',
										'price' => '90.0000',
										'meta' =>
											array (
												'attendee_name' => 'Jane Smith',
												'choices' =>
													array (
														0 => 'Choice X',
														1 => 'Choice Y &Z',
													),
												'checkbox' => '1',
												'bdate' => '2022-10-25',
											),
									),
							),
					),
				38388 =>
					array (
						'name' => 'VIP',
						'description' => 'VIP',
						'spaces' => 1,
						'price' => 125.0,
						'attendees' =>
							array (
								0 =>
									array (
										'uuid' => '8981a928ccfb477e994c0c95b99f7561',
										'price' => '125.0000',
										'meta' =>
											array (
												'attendee_name' => 'John Smith',
												'choices' =>
													array (
														0 => 'Choice X',
													),
												'checkbox' => '1',
												'bdate' => '2022-10-14',
											),
									),
							),
					),
			),
		'event' =>
			array (
				'name' => 'Kenny Wayne Shepherd',
				'id' => 24226,
				'post_id' => 31973,
				'parent' => NULL,
				'owner' =>
					array (
						'guest' => true,
						'email' => 'testadmin@test.com',
						'name' => 'Test Admin Person',
					),
				'blog_id' => 0,
				'group_id' => NULL,
				'slug' => 'kenny-wayne-shepherd-2022-10-18',
				'status' => 0,
				'content' => '',
				'bookings' =>
					array (
						'end_date' => '$this->event_rsvp_date',
						'end_time' => '$this->event_rsvp_time',
						'rsvp_spaces' => '$this->event_rsvp_spaces',
						'spaces' => '0',
					),
				'when' =>
					array (
						'all_day' => NULL,
						'start' => '2022-10-18 22:00:00',
						'start_date' => '2022-10-18',
						'start_time' => '17:00:00',
						'end' => '2022-10-19 00:00:00',
						'end_date' => '2022-10-18',
						'end_time' => '19:00:00',
						'timezone' => 'UTC-5',
					),
				'location' =>
					array (
						'name' => 'Capital One Bank Theatre',
						'id' => 27,
						'parent' => NULL,
						'post_id' => 842,
						'blog_id' => '0',
						'owner' => 1,
						'status' => 1,
						'slug' => 'capital-one-bank-theatre',
						'content' => 'NYCB Theatre at Westbury New York formely The Capital One Theatre event tickets. Independent website specializing in premium seating for the NYCB Theatre at Westbury. We also have a large inventory of New York event tickets for sale Yankees, Wicked, Spiderman, Merchant of Venice, Driving Miss Daisy, The Lion King, New York Knicks and more.',
						'geo' =>
							array (
								'latitude' => '40.7733393',
								'longitude' => '-73.5585302',
							),
						'address' =>
							array (
								'address' => '960 Brush Hollow Rd',
								'town' => 'Westbury',
								'region' => '2',
								'state' => 'NY',
								'postcode' => '',
								'country' => 'US',
							),
						'language' => NULL,
						'translation' => 0,
					),
				'recurrence' => false,
				'language' => 'en_US',
				'translation' => 0,
				'recurrence_id' => '50',
			),
	);
	if( $type == 'booking' ){
		return $booking;
	}elseif( $type == 'event' ){
		return $booking['event'];
	}
}