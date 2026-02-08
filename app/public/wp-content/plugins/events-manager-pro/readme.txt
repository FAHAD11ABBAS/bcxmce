=== Events Manager Pro ===
Contributors: pxlite, msykes
Tags: events, event, event registration, event calendar, events calendar, event management, paypal, registration, ticket, tickets, ticketing, tickets, theme, widget, locations, maps, booking, attendance, attendee, buddypress, calendar, gigs, payment, payments, sports,
Requires at least: 4.9
Tested up to: 6.6
Stable tag: 3.7.2.3

== Description ==

Thank you for downloading Events Manager Pro!

Please check these pages for further information:

http://wp-events-plugin.com/documentation/ - lots of docs to help get you started
http://wp-events-plugin.com/tutorials/ - for advanced users, see how far you can take EM and Pro!

If you have any issues/questions with the plugin, or would like to request a feature, please visit:
http://wp-events-plugin.com/support/

== Installation ==

Please visit http://wp-events-plugin.com/documentation/installation/

== Changelog ==
= 3.7.2.3 =
* Fixed attendee booking/editor form not decreasing attendee forms correctly after selecting too many
* Fixed attendee form styling issues causing CSS/HTML blowouts especially on mobile views
* Fixed missing minified and sass > CSS converted files not being uploaded to production versions
* Fixed PHP notice/error during 2.x to v3 update
* Added support for reserved space counting in bookings since EM 7.2.3 which mitigates double-counting of pending-reserved spaces
* Fixed some reported typos

= 3.7.2.2 =
* Fixed datepickers not working for coupons admin page
* Fixed timeslot-only (non-recurring) events not showing booking transactions correctly in booking admin
* Fixed fatal error in EM causing limits feature to fail, updated limits code to adhere to new methods of retrieving recurrence days

= 3.7.2 =
* Added support for archetype-specific options from EM 7.1
* Added support for timeslots in EM 7.2
* Added move booking feature between recurrences and event timeslots

= 3.7 =
* added support for Archetypes in transaction history
* added support for archetypes and custom event booking emails
* added custom emails integration with archetypes

= 3.6.2 - 2025-06-30 =
* Changed status of previous past events to `2` instead of `0`, which was getting confused with drafts.
* Fixed inconsistencies with status counts when past events status is enabled.
* Fixed past event status not showing in quick editor.

= 3.6.1 - 2025-06-20 =
* Added missing `pdf-invoice.min.css` file.
* Updated multiple bookings JS files to use dynamic asset loading and migrated jQuery dependencies to native JS.
* Fixed compatibility issues with EM 7 and coupons JS, including adding JS and CSS files as dynamic assets.
* Fixed attendee forms not showing up on recurring event booking forms due to dynamic form loading.
* Moved attendee form JS into a separate file and leveraged EM dynamic asset loading.
* Updated manual bookings, gateway transaction tables, minimum capacity, and waitlist features for compatibility with the new recurrences/repeated features introduced in EM 7.0.
* Fixed loading order of translated string assignments so they are after `init` by adding `wp_loaded()` function for gateways to translate initially hard-coded strings.
* Fixed custom upload field values showing as escaped HTML in bookings admin table.


= 3.5 - 2025-02-21 =
* Fixed duplicate payment transaction records for offline fully-paid manual bookings.
* Fixed offending `<span style="color:red;">` wrapped around certain custom field values in the booking admin tables.
* Removed legacy fallback storing (and in some places still accessing) attendee data additionally on `$EM_Booking->booking_meta['attendees']` in favor of exclusively in `EM_Ticket_Booking->meta`.
* Added integration with EM Uploader introduced in 6.6.4.
* Added upload fields to custom user, booking, and attendee forms.
* Fixed display, UI, and UX issues in booking editor for attendee forms related to adding and removing attendees in a ticket.
* Added `em_gateways_transactions_table_sql_parts` filter and compartmentalized SQL segments for developer flexibility.
* Fixed attendee form editor not adding new attendees in later EM versions due to missing `ticket-id` data properties in single booking editor.
* Fixed action links for waitlisted bookings in bookings admin table.
* Fixed possibility for fatal error when storing country fields in some extraordinary setups

= 3.4.1 - 2024-09-12 =
* reverted Pro requirement check utility being removed, so some plugins remain compatible until updated

= 3.4 - 2024-09-11 =
* Added support for consents in MB mode and other Pro features.
* Added more recursive saving of Multiple Booking meta into individual bookings.
* Added custom phone field support to attendee and booking forms.
* Fixed minor booking table PHP notices with gateway and transaction tables.
* Added native intl phone support in EM 6.5.2.1 onwards.
* Improved attendee dynamic form additions by removing templates out of DOM to avoid libraries like selectize etc. converting template fields before duplication and causing layout init issues.
* Fixed fatal error when initiating Pro update from front-end when EM is still on version < 6.5.
* Improved obtaining of downloadable PDFs allowing for easier upload access.
* Fixed automation nonce false-positive error.
* Added ability to deactivate actions in a specific automation.

= 3.3.1 - 2024-08-02 =
* Fixed the attendance in/out column on booking admin tables showing incorect results for non-attended booking tickets.

= 3.3 - 2024-06-29 =
* updated for support for new Events Manager 6.5 admin booking tables
* added check in/out shortcuts to booking admin tables
* added check in/out color-coded booking admin table column for quick reference
* added support for sortable booking and attende form field meta columns in booking admin tables
* migrated transactions booking admin tables to new EM_List_Table infrastructure
* added multiple bookings view to booking admin tables
* fixed nonce errors with editing multiple bookings front-end
* fixed nonce errors with editing multiple bookings front-end
* fixed offline transactions recording timestamp according to site time not UTC, causing time inconsistencies in tables

= 3.2.10 =
* Fixed cancel URL not taking gateway settings.
* Added option whether to reserve unpaid spaces for PayPal Standard (defaults to yes).
* Fixed bug in 3.2.9 preventing MB mode from sending booking emails after completing a booking.
* Fixed automation fatal error when emailing emails in context of the event rather than booking.
* Added `em_gateway_loaded` listener to remove loading skeleton independent of selector system (such as for buttons).
* Added redirect-success = 'optional' flow for gateways that optionally can redirect to another page upon success.
* Added direct-cancel flow allowing for cancellation during payment process (such as closing a window during payment).
* Added `em_multiple_booking_updated` action to allow more reliable surcharge/discharge additions.
* Tweaked `Gateway::booking_form_button()` by overloading with $id to allow for reliable ID assigning (not critical).
* Added default booking timeout action for expired bookings for enabled gateways.
* Added payment_complete query arg to all redirects after successful payment including custom URLs.
* Fixed event 'After' time not saving in automation editor.
* Added RSVP policy filter for automations.
* Fixed default admin email not defaulted to if event-gateway admin emails not defined.
* Fixed random PHP 8x error with custom forms.
* Fixed automation category/tag filters not saving properly.

= 3.2.9 =
* added 'delete license' button to license checker when there is a failed verification warning
* fixed cancellations being possible if previously enabled for an event and then disabling event-specific cancellation options in settings page
* added past events handling and minimum event capacity auto-cancellation features
* fixed some PHP 8.x notices in legacy gateway mode
* added Manual_Bookings::activate_manual_booking_gateways() function and separated logic from activate_offline_gateway(),
* added check for fully or partial payments during manual bookings which will then default payment method to offline
* fixed minor PHP warning for multiple bookings
* fixed webhook automation test PHP fatal error if failed response
* fiexed aesthetics CSS issue on automations form
* added transaction payment type (card,check etc.) and detail (card 4 digits) to booking table column options
* fixed gateway transaction ID not displaying properly in booking admin tables,
* added 'em_gateways_transactions_table_'.$col filters for gateway-based items on booking tables
* added conditional placeholders for detecting multiple bookings
* added phone number UI field for custom booking form,
* fixed manual bookings issue which replaces registrant name with logged in user in some setting combinations
* fixed ticket counting reserved spaces error with gateway bookings causing negative ticket availability and closed bookings,
* fixed booking space reservation option for offline and other gateways not overriding default EM setting
* added em_gateway_{gateway}_init action

= 3.2.8.1 =
* Fixed validation issues of waitlisted bookings in MB mode.
* Added `em-multiple-booking-form` class to single booking forms in MB mode.
* Fixed MB `session_start()` PHP errors and issues by starting in `template_redirect` to allow saving of invalid cart contents on checkout/cart pages.
* Fixed waitlist-approved booking forms not hiding or showing the correct confirmation information when successful.
* Fixed waitlist-approved bookings not showing booking form on event page itself.
* Fixed waitlist-approved booking action buttons not displaying on my bookings pages.
* Fixed a bug in EM_Booking->validate() being used in EM_Multiple_Booking->validate_bookings_spaces() which was removing user fields from checkout page.
* Fixed username custom user field validation issues when doing a manual booking for a new user with no-user mode enabled.
* Fixed manual booking network errors due to gateway interference when marked fully paid.
* Fixed some display issues with quickpay buttons.
* Improved UI experience for manual bookings whilst selecting payment methods or amounts paid already.
* Added/improved online payment support for manual bookings via PayPal Advanced, Stripe Checkout/Elements, Authorize.net.
* Fixed automation booking status PHP errors preventing correct execution for some hooks.

= 3.2.8 =
* Fixed RSVP policy deadline error when no policy deadline is available for an event.
* Fixed manual booking issues with legacy gateways mode.
* Fixed manual booking status setting issues.
* Changed `Gateway::is_displayable()` by adding `$context` and `$object` params to allow for better overriding options. Extending gateways must update with these parameters to avoid PHP errors.
* Updated `Gateway::is_displayable()` to return a boolean value only, regardless of test limited mode.
* Fixed checking of active gateways to ensure test mode is properly verified during limited mode against the current event/booking object and using `is_displayable()`.
* Fixed `Gateways::restore_current_event()` not repopulating `Gateways::$current_event_id` with the previous `event_id`.
* Added backend automation logging for `EM_AUTOMATION_LOGS`, with levels 1 (normal, when triggers are executed) and 2 (verbose, including errors).
* Fixed booking data not getting replaced within `$EM_Bookings` object during the `filter()` method, which resulted in unfiltered bookings.
* Fixed automation fatal error in builder if Gateways are disabled or using Legacy mode.
* Fixed gateways not recognizing surcharges/discounts if added with priority 10 during `em_event_get_post` by decreasing the priority of the gateway hook to 100.
* Fixed booking summary failing with free events.
* Fixed coupons input field not showing if booking summary is disabled on the booking form.
* Added 'Reply To:' in the batch email sending feature for bookings.
* Fixed fatal error when in multilingual mode with Gateway legacy mode enabled.
* Fixed late static binding issues with new gateway API and generating accurate pending spaces count.
* Fixed email sending to bookings not breaking new lines and slashing apostrophes.
* Fixed gateways with immediate payment not force-reserving spaces even if EM settings disable approvals/reservations, which could lead to overbooking.
* Fixed gateway transaction ID column on booking admin tables showing incorrect/non-existent transaction ID for waitlisted records.
* Added ability to email individual event confirmations to guests in MB mode.
* Fixed `previous_status` of individual bookings in MB not being synced properly when changing the main MB status.
* Fixed various PHP warnings

= 3.2.7 =
* added new Loader check for QR feature to prevent fatal errors from inconsistent feature active checks
* added EM\Loader for backcompat with older EM versions
* fixed bookings manager page load issues when front-end management features are enabled in Elementor Pro
* fixed PHP error when automation triggers/actions from external plugins aren't active anymore
* added RSVP Pro features including endpoint link and RSVP policies
* added gateway test modes, allowing for live/test switching quickly along with testing gateways restricted to IPs, users and events

= 3.2.6 =
* fixed gateway checkout errors on Multiple Bookings mode
* fixed PHP 8 warnings for automations
* fixed return url not saving properly for gateways

= 3.2.5 =
* fixed plus signs in emails not producing correct waitlist signup url
* changed gateway JS to use new EM enable/disable button functions since EM 6.4.3.1
* updated checkout template to use new booking form markup
* added backcompat functions/hooks for sites overriding checkout templates using old markup and missing new hooks,
* updated goateway to use new checkout form footer action (or backcompat fallbacks)
* fixed waitlist issues whilst logged in when double-bookings are blocked,
* fixed possiblity to waitlist-request more than a single booking will ever allow if approved
* fixed gateway intial load issues of booking intent with minimum spaces already defined with a pre-selected gateway
* fixed default gateway redirect url (my bookings) not showing successful custom feedback message in notice markup
* fixed cancellation notice for waiting lists missing formatting,
* fixed waitlist booking form not collapsing upon successful booking

= 3.2.4 =
* fixed error in 3.2.3 which caused problems with versions earlier than Events Manager 6.4.2

= 3.2.3 =
* fixed QuickPay buttons not showing for checkout
* fixed automation emails action admin area adding slashes to message and not displaying message in textarea
* fixed automation emails and other scheduled emails not working when added to the EM Pro email queue when reminders are disabled
* added $email_args to set_status and ensure compatibility with EM_Booking parent method in EM 6.4.2
* fixed waitlist booking form compatibility issues with EM 6.4.x
* fixed PHP warning when inserting event time trigger flags into em_meta table
* fixed booking form init JS not triggering a change event for a pre-selected when booking form initially loaded,
* added extra fallback fix to enable booking button in event of a free booking
* fixed JS errors not redirecting users with the PayPal legacy gateway (running new API)
* fixed date timestamp issues of transaction history for outgoing NVP checks for PayPal legacy gateway
* fixed sandbox mode not redirecting properly for PayPal legacy gateway (both APIs)

= 3.2.2 =
* fixed JS issue with gateway QuickPay buttons
* fixed minor php 8 deprecated warnings

= 3.2.1 =
* added support for custom fonts allowing for special character languages (Chinese, Japanese, etc.)
* added template loading from the plugins-template folder
* fixed waitlists feature placeholders not working and/or overwriting certain event placeholders when enabled
* complete overhaul of Gateway API with support for new payment methods for Stripe, PayPal and Authorize.net
* rewrite of coupon code UI including decoupling from jQuery
* updated manual transactions and custom emails for new gateway API class names and methods
* changed attednee forms hooks to use em_booking_js rather than em_gateway_js
* changed attendee forms js to use listener function that is called rather than triggering a change listener on load (causes issues with intents and summaries)

= 3.1.3 =
* added dependent event booking restrictions feature
* fixed minor PHP warning for booking time automation
* changed init to wp_loaded action for EM_Coupons
* fixed JS issues with form editor
* fixed form editor field keys allowing long names which can cause DB issues
* fixed JS file not loading on profile dashboard page
* fixed n/a booking field value mapping issues when field ID is excessively long

= 3.1.2 =
* added selectize for manual bookings user dropdown
* fixed minor PHP warning when validating dates
* added batch data support for email queues, allowing for shared attachments accross multiple emails in a queue
* added error handling for retrying failed email attempts and prevent infinite loops
* added feature to email users who made a booking along with attachments
* changed emp_cron_emails_ical_cleanup to emp_cron_emails_attachment_cleanup so that other attachment logic can clean up
* improved license checking restrictions to account for interfering plugins or server setups
* added header based on settings page text for "Payment and Confirmation" header
* added automation (beta) - requires EM 6.1.2.6
* fixed search ui issue with new selectize manual bookings user selector
* added selectize hook for manual bookings to disable selectize via em_gateway_offline_select_user_manual_booking
* fixed cancelled/rejected bookings being able to get checked in by admins,
* added booking status to booking manager viewer
* fixed php fatal error for waitlist conditionals in booking conditions
* fixed inclusion error for printables JS file in admin area for some servers

= 3.1.1 =
* fixed fatal error for new installs

= 3.1 =
* fixed issue with bookings PDF disabled option not correctly disabling PDFs entirely
* added waitlist feature
* fixed php warning on install
* added EMP_Formats for similar functionality as in EM_Formats with dynamic format loading
* changed is_a() use to instancesof
* fixed #_BOOKINGUSERMETA not converting properly if user meta exists but no on the current form associated with the event
* added extended cancellation policies on a per-event basis
* fixed attendee exports to CSV not working since 6.1 update
* added limits to event submissions and recurrence creation
* fixed attendee form not working in single ticket booking form mode
* fixed DB errors due to emojis
* fixed attendee form data not displaying on MB mode checkout page
* updated paypal code to reference EM_Ticket_Booking data directly rather than EM_Ticket_Bookings for price information, which was causing issues in MB mode checkout prices
* fixed potential PHP warnings/errors from getting thrown when starting sessions for MB mode
* fixed timepicker validation not accounting for single digit AM/PM hours,
* fixed EM_Form->__toString() calling output_field() statically (potential PHP warnings/errors)
* fixed double-datepickers in attendee forms when adding new attendees and using custom formatting for dates
* fixed duplicate rows output in CSV split by attendee
* fixed false 1970 dates and 0:00 times in booking admin when fields have no value
* fixed single dates in custom form fields show placeholder as 'select date range'

= 3.0 =
* added support for atomic booking ticket meta by storing attendee information within the new em_tickets_bookings_meta table,
* added migration for new atomic booking ticket meta architecture
* added attendance features (check in/out),
* added QR code support,
* added front-end booking admin for scanning QR and easy mobile admin,
* added printable PDF invoices,
* added printable PDF tickets (with QR support),
* upgraded to PHP versioning for EM Pro 3.0
* added custom email filters to allow adding custom statuses programatically : em_custom_emails_admin_default_emails_subgroup, em_custom_emails_admin_default_emails, em_custom_emails_gateway_admin_gateway_default_emails and em_custom_emails_admin_default_email_values
* fixed date picker not working in profile edit page for custom user fields
* fixed certain user fields not getting translated in WPML
* fixed google maps static running the cache cleanup even if caching is disabled
* tweaked google static maps cronjob to add emp_cron_gmap_static_cleanup_time to wp_options if it doesn't exist
* fixed ical rewriting attachment array with new array rather than adding files to attachments (requires EM 6.1),
* fixed some translations using the wrong text domain,
* removed tippy/popper JS since it's now included in EM 6.x
* fixed styling issues for dates and times for custom booking forms,
* changed datepicker to use new datepicker format in EM 6.0 for custom forms,

= 2.7 =
* fixed issue where manual bookings without any active gateways still marks bookings as pending payment,
* added option to auto-confirm manual bookings if offline gatweay is inactive
* fixed 'non longer available' error for manual booking tickets
* fixed transaction log dates showing UTC time instead of local blog time
* fixed forms editor minor meta box styling issues,
* added emp_form_get_formatted_value filter
* added em_logs_log_directory and em_logs_log_name filters to EMP_Logs to allow overriding of locations
* fixed username fields not showing in manual booking form
* fixed transactions table showing UTC date/time instead of local timezone
* fixed issue with ML cross-language bookings not being removable in multiple bookigns mode
* fixed tooltips not accepting HTML
* fixed wrong attendee form data output on checkout if multiple events in cart have different attendee forms
* added checkbox to disable ticket restrictions in manual bookings, allowing for overbooking ticket spaces and overriding role/date limitations
* fixed manual booking form ommitting certain registration fields as per settings page options meant for regular users
* fixed paypal pending payments getting auto-deleted on all blogs in MS Global according to the shortest timeout setting on any of the network blogs
* fixed logging issues in multisite installations (requires re-saving network EM settings if logging is enabled)

= 2.6.9.2 =
* added __sleep() and __wakeup() function for MB mode compatibility of new EM Version
* fixed redirection of MB bookings when redirection setting is turned off

= 2.6.9 =
* fixed som PHP 8.0 deprecated code
* replaced tooltips qtip library (deprecated for some time now) with tippy.js
* updated jQuery to replace deprecated functions for upcoming v3.5 transition in WP core
* fixed warnings/errors when activating Pro plugin without EM already active
* added logging for some MB booking errors,
* added extra line to email booking attendee data

= 2.6.8 =
* added improvements to license checking in order to prevent WPML and Duplicator Pro conflicts
* fixed typo of second em_multiple_booking_email_before_send filter firing instead of em_multiple_booking_email_after_send
* fixed EM_Attendees_Form not caching event form based on event_id
* added emp_attendees_form_get_form, emp_attendee_forms_get_booking_attendees and emp_attendee_forms_get_ticket_attendees filters
* fixed individual event admin emails not getting sent to event owner in MB mode
* added permission_callback to rest route for gateway notifications
* added #_BOOKINGFORMCUSTOM_MB and #_BOOKINGFORMCUSTOMREG_MB
* added ability for multiple booking data to be used when #_BOOKINGFORMCUSTOM and #_BOOKINGFORMCUSTOMREG have no info in a single event context
* added #_BOOKINGUSERMETA{meta_key} which retrieves any user fields defined in the EM form editor even if not used on a specific event booking form
* added extra helper functions to EM_Multiple_Booking to check child/parent relationships of bookings
* added separate admin email option for Multiple Bookings which can be different to individual booking admin emails
* added load balancing constants and reset/delete license options for license checkers
* improved cancelled payment functionality to delete bookings on redirect-based gateways as well as a custom feedback message
* changed booking form custom checkbox fields checkbox position to left side of label for better alignment
* updated qtips script to v3.0.3
* changed booking form tip bubble colors from ghastly yellow to clean white!
* fixed cart summary display issues for events without a physical location
* fixed api warnings showing on paypal settings page
* fixed transaction ID links pointing to sandbox for authorize.net and stripe in transaction history tables
* fixed ical invites not getting sent for custom emails
* fixed coupons field not available for manual bookings in MB Mode
* fixed expired coupons showing up when creating/editing events,
* added 'scope' search argument to EM_Coupons::get()
* fixed custom booking form 'name' field changing first/last name values of previously saved user data which breaks 2+ worded first names
* fixed refunded payments marking booking as pending rather than cancelled
* added sandbox detection with newly is_sandbox() overriding function
* fixed some direct booking button nuances when in Multiple Bookings mode

= 2.6.7.2 =
* fixed CSV injection potential vulnerability for tainted data when exporting bookings to CSV
* added ical attachments to emails (requires at least EM version 5.9.7.2)
* fixed WPML issues with custom email templates
* changed the use of jQuery deprecated .delegate() to .on() function

= 2.6.7 =
* fixed booking form extra text not showing up in gateway selector booking form area,
* removed incorrect defaults for $api_options in EM_Gateway::settings_sensitive_credentials()
* fixed multiple issues where custom booking emails in Pro are not properly translated
* fixed custom booking forms potentially getting translated incorrectly when viewing the dashboard in a different language
* updated Authorize.net AIM certificate in accordance with their upcoming DNS migration (fixes certificate issues in the sandbox)

= 2.6.6.3 =
* fixed bug introduced in 2.6.6.2 where attendee forms did not show up when spaces added to a ticket

= 2.6.6.2 =
* fixed a multisite licensing/update bug causing unexpected deactivation of plugin
* fixed some minor PHP warnings

= 2.6.6 =
* minor css tweaks to booking view of Multiple Booking cart details
* fixed checkbox and multi-booking issues for no-user booking details editor on single booking
* fixed ARRAY literal appearing in booking email for checkboxes in certain scenarios
* fixed 'Class ‘EM_Admin_Notices’ not found' errors in PHP logs
* removed EMP_PAYPAL_SSL_OVERRIDE constant, use EMP_GATEWAY_SSL_OVERRIDE instead
* added em_coupons_admin_meta_box_coupons and em_coupons_admin_meta_box_show_code filters to control what coupons and whether codes are shown in event submission forms
* fixed user login fields not showing up on booking forms when user is logged in, added option to show/hide username profile field in settings page (Events Manager includes fix to show username for CSV/Columns)
* added rejected custom email template to events and gateways which get sent to admins
* fixed case sensitivity in coupon codes
* fixed coupon descriptions not getting saved if left blank when editing
* fixed company logo not displaying on PayPal checkout page (due to deprecated parameter name)

= 2.6.5 =
* added pre-requisites check and new licensing/update checking class
* added a consistent return url handling in gateway.php so that checkout-flow gateway implementations can use it (non-breaking change),
* tweaked transaction history to add new movements instead of modifying the original transaction with the same ID (e.g. previously changing a txn from 'complete' to 'refunded' now will show two transactions)
* added deregister_gateway() function to EM_Gateways,
* changed PayPal JS to use .on() instead of oudated .bind()

= 2.6.4.2 =
* added JSON API endpoint for gateways to send notifications and integrated authorize.net webhooks support,
* added SHA512 hashing support in place of now deprecated MD5 hashing for silent posts,
* added Signature Key field for authorize.net settings which allows for webhooks and new silent post notifications to be verified correctly,
* improved aesthetics of log file entries (for logging enabled),
* added generic api key storage to EM_Gateway object previously only on PayPal
* fixed multiple booking issues with displaying carts in PHP 7.1 due to new session_save functionality
* fixed consent checkbox still showing even if disabled on multiple bookings form
* fixed offline approve warning proceeding when cancel button is pressed
* added filter for transaction details transaction id, allowing for external links to transaction details on gateway site
* changed output of google static map default hex image when there are API errors, to prevent false positives with security scanners
* fixed coupons not updating count when a booking using the coupon is canceled or rejected,
* fixed bookings deleting coupons when rejected/canceled, which causes problems with email templates showing different prices to original booking
* fixed back button to booking form not loading attendee forms for tickets with selected spaces
* added em_pro_loaded action after EM_Pro::init() has executed, add-ons should hook into this rather than init to prevent dependence fatal errors
* added check allowing for regex modifiers to be used in custom field textarea regex rules
* minor tweak to forms editor admin page for recent WP css/class structure
* fixed session locking issues in Site Health checks (and potentially other loopbacks) when Multiple Bookings are enabled
* fixed regex issues where modifiers were not being properly validated and certain complex regex expressions getting characters stripped due to sanitizing and/or JSON stringify on longer forms
* fixed site/event-wide coupons showing in booking options to guest users when anonymous event submissions enabled

= 2.6.4 =
* added support for Google Maps Static API display options and caching for relatively sized static map images

= 2.6.3.2 =
* fixed issues with privacy consent tools preventing admins from making manual bookings
* fixed anonymous bookings overwriting logged in user info when manually booking

= 2.6.3.1 =
* fixed data privacy consent checkboxes showing on both individual event and checkout pages for multiple bookings

= 2.6.3 =
* added Data Privacy and GDPR features

= 2.6.2.1 =
* further fix for authorize.net service breakages on production accounts due to unannounced changes in their service

= 2.6.2 =
* updated SSL certificate file in the authorize.net SDK library
* fixed minor PHP warnings if EM isn't activated along with Pro
* added error logging support for failed Pro update lookups

= 2.6.1 = 
* fixed manual bookings not adding correct new user information when name/email/profile fields are set to not be displayed or editable
* fixed reminder emails not getting translated into booked language
* fixed minor PHP warning on transactions table when no transactions to display

= 2.6 =
* reduced number of redundant booking form SQL calls on booking admin pages
* optimized pending counts of bookings for gateways by avoiding loading/looping through all bookings and using SQL queries instead
* made adjustments for timezone changes in EM
* fixed custom event emails of a translation getting deleted in WPML when saving the original translation
* fixed various PHP warnings/notices and PHP 7.2 compatibility issues
* fixed partial refunds in paypal sending a 'pending' email
* fixed 'resume payment' button for paypal in MB mode not including all events booked at once
* changed all recaptcha on booking forms to v2 without the need for a SDK requiring higher PHP versions than WP minimum
* fixed minor coupon rounding issue in description text
* fixed attendee emails not getting sent to logged in users when booking forms have non-editable user fields
* fixed duplicate custom email and coupon records in recurrences resulting in incorrectly saved data
* renamed recaptcha form editor private/public key labels to match site/secret key labels on recaptcha admin site
* optimized transactions lookup for bookings with a specific ticket
* fixed some inconsistencies when saving checkboxes and other multiple choice attendee form fields with HTML entities
* fixed multiple selection user fields not being saved to user account during a booking and only to booking object
* added option to reserve bookings pending payment on PayPal
* fixed anonymous@ emails being cc'd in admin emails when Multiple Bookings mode is active 
* fixed custom emails for offline gateway not defaulting to the custom 'pending' template if a custom 'awaiting payment' template not defined
* fixed unexpected behaviour when pressing back button from paypal and attempting to submit a second time 
* fixed coupon code issues derived from duplicate codes by preventing creation of non-unique coupon codes
* fixed coupons and gateway options appearing in certain instances when an event is free
* added price adjustment calculation functions for single bookings within a multiple booking instance to allow calculation of proportional totals after discounts/surcharges applied to overall booking,
* fixed 'Total Paid' booking table field showing 0 when in Multiple Bookings mode
* fixed various multilingual and cascading/precedence issues with custom emails
* removed EM_Custom_Emails_Admin get_gateway_default_values()/get_gateway_mb_default_values() and unified in get_default_email_values(),
* tweaked custom emails so offline gateway 'pending' custom email will be used if no gateway 'awaiting offline' template is defined,
* added pending Multiple Booking email template,
* changed option dbem_multiple_bookings_contact_email_subject/body to dbem_multiple_bookings_contact_email_confirmed_subject/body,
* fixed potential WPML multilingual issues with saving certain setting pages when in another language

= 2.5.1 =
* fixed EM_Coupons::em_event_delete_meta filter not returning a value and breaking filter result
* fixed 'Call to a member function get() on null...' PHP error with certain plugins and themes
* tweaked manual payment page header to use new wp html inline actions class structure
* fixed max_input_vars JS workaround not properly serializing multilingual booking forms and failing to save translations
* fixed PHP warning in gateways admin pages
* fixed unnecessary custom booking/attendee meta fields stored when no forms are selected
* fixed some coupon counting cache issues
* previous 2.5 release may not have included some of the aforementioned update due to a building process not pushing out all updated files, this version rectifies that

= 2.5 =
* added WPML compatibility with custom booking forms
* fixed double emails sent when booking cancelled by user
* fixed authorize.net not logging custom currency for transaction history
* fixed css issue with drag/drop icon in form editor
* fixed nonce verification issues when adding note to Multiple Booking record
* added conversion to utf8mb4 collation in tables if WP tables have it too
* fixed typo in em_gateway_authorize_aim_sale_var filter and leaving old filter to avoid breakage
* updated security certificate for authorize.net
* added debug logging to authorize.net gateway
* fixed status changes to main booking in MB mode not firing sub-booking status change filters
* fixed transactions table JS using outdated live method
* fixed #_BOOKINGTXNID outputting unreliable ID values when used within a loop of bookings
* added conversion to utf8mb4 collation in tables if WP tables have it too
* fixed typo in em_gateway_authorize_aim_sale_var filter and leaving old filter to avoid breakage
* updated security certificate for authorize.net
* added debug logging to authorize.net gateway
* fixed status changes to main booking in MB mode not updating sub-booking statuses
* fixed transactions table JS using outdated live method
* fixed #_BOOKINGTXNID outputting unreliable ID values when used within a loop of bookings
* changed gateway payment notification listeners to use permalinks as well as old admin-ajax.php address as a workaround for PayPal IPN bug
* added API verification of pending payments before deleting them in case of IPN failures
* added em_before_manual_booking_form and em_after_manual_booking_form actions to manual booking form
* fixed various PHP 7 warnings cropping up on the PHP Compatibility Checker plugin
* changed _emp function to emp_ to prevent false positive report on the PHP Compatibility Checker
* changed v1 recaptcha library to allow proxies by defining RECAPTCHA_VERIFY_SERVER_PROXY and RECAPTCHA_VERIFY_PORT_PROXY
* changed code to use new no-user mode detection functions
* fixed minor PHP warning when submitting in some specific custom form setups
* fixed possible overbooking scenario when using authorize.net and multiple simultaneous bookings
* modified coupons and gateways to accommodate new generic price adjustments api in Events Manager
* added filters to EM_Multiple_Booking functions and removed price calculation from get_price() as this should be calculated at get_price_base()
* modified MB cart table templates to reflect booking surcharges API
* fixed potential paypal errors when user saves account email with a trailing space on settings page
* fixed issues when CSS/JS loading is limited for performance and not being loaded automatically on MB checkout and cart pages
* changed recaptcha library to use v2 by default for PHP versions 5.3 upwards (define EM_RECAPTCHA_2 and set to false to continue using v1),
* fixed booking-related admin options (forms editor, gateways etc.) still showing up if bookings are disabled
* changed multiple booking redirect to be provided by new 'redirect' response variable now handled by EM JS
* fixed paypal full refunds resulting in booking status changing to pending rather than cancelled
* fixed view of 'my coupons' and 'all coupons' for admins in Multiple Booking Mode
* added surcharges summary to MB #_BOOKINGSUMMARY template - templates/placeholders/bookingsummary-multiple.php
* fixed all transactions showing in certain booking admin views after clicking the filter button
* added option to choose 'none' as an attendee form
* fixed guest tickets and other restricted tickets not showing up in manual bookings
* fixed/changed form editor to prevent unexpected overwriting of fields on a single form with duplicate IDs
* fixed some inconsistencies when saving custom registration fields with same field ID as a regular field type or core user field id
* fixed event booking/attendee form selection allowing the default form to be specifically selected so changes to default form don't change assigned event forms
* fixed custom email templates not accepting full HTML template
* fixed duplicated events not copying custom booking/attendee form choices
* fixed issues with renaming attendee forms and resaving causing an overwrite of default form
* fixed Coupon count cache syncing issues
* added 'Total Paid' column in bookings table with sum of payments made
* changed PayPal gateway name to PayPal (Payments Standard)

= 2.4.4.2 =
* fixed 'undefined' redirect when booking a free ticket via PayPal when there are paid alternatives or a coupon is used
* fixed issue with Multiple Bookings and Coupons not approving sub-events
* fixed MIME header issues in MultiSite environments when checking coupon validity on booking form

= 2.4.4 =
* fixed CSV custom delimiter issues for attendees which were also fixed in EM 5.6.2
* changed use of some translated text using textdomain dbem to events-manager for update EM 5.6.2
* fixed partial refunds always cancelling
* changed WP Dashboard admin page titles to h1
* fixed some PHP errors and plugin conflicts in update script (kudos Ross McKay)
* fixed load_plugin_textdomain firing before some plugins loaded (WPML conflicts)
* fixed MB bookings form giving permission errors when submitting form second time after failed validation
* fixed transactions not displaying in individual bookings part of an MB booking
* fixed booking registration meta showing empty user data instead of querying user meta
* updated Chinese, French, Swedish and German 
* fixed PHP 7 'array to string conversion' errors on custom booking form placeholders
* fixed PHP warning for manual bookings with BuddyPress enabled
* fixed double booking errors if admin making a manual booking but also booked that event
* fixed double 'required' errors for user fields with different labels on the booking form to user fields editor
* added warning to authorize.net settings if choosing live mode with an invalid SSL certificate
* fixed rare MultiSite Multi Bookings bug in MS Global Mode when deleting an event booking from cart
* fixed coupons not being freed from cancelled and rejected bookings
* fixed WP 4.5 redirection errors due to changes in wp_get_referer (requires EM 5.6.3)
* fixed coupons not being freed when bookings are deleted with an event (requires EM 5.6.3) 
* fixed deletion of event or modification of recurring event not deleting related booking translations
* fixed some texdomain typos in the authorize.net gateway

= 2.4.3 =
* fixed custom email templates per event not saving properly
* fixed per-event coupon associations not saving when editing in wp dashboard
* fixed manual no-user bookings not saving user fields correctly
* fixed manual no-user bookings overwriting user field information to guest bookings user account
* fixed bug preventing all or last custom event email being removed
* fixed admin emails per event storing an unnecessary record in em_meta table when there is an empty value
* fixed 'awaiting payment' status for free bookings not reserving spaces,
* removed redundant EM_Offline::em_bookings_get_pending_spaces() function and related filter
* fixed certain multi-select values containing characters like & breaking field validation
* fixed PHP warning for some custom email setups

= 2.4.2 =
* fixed parse errors on PHP versions lower than 5.3 due to recaptcha 2 library usage of namespacing
* fixed parse errors on PHP versions lower than 5.3 due to creating lamda functions whilst adding an action or filter 

= 2.4 =
* added various Multilingual enhancements/fixes, the following can now be translated:
 * email reminder templates
 * multiple booking email templates
 * custom per-event and per-gateway emails
 * various gateway settings and MB feedback messages
* fixed minor PHP warnings
* added EM_CSV_DELIMITER constant, which can be defined in wp-config.php with a value for a CSV delimiter which defaults to a comma
* fixed MB booking price rounding issues when comparing paid amount to pending total
* change PHP custom gateway setting updates and sanitation with simplified code,
* changed gateway setting hard-coded html fields replacing with em field functions (multilingual ready)
* changed PayPal IPN verification requests to use our on User-Agent identifier
* changed use of em_gateway_js hook to em_booking_js hook
* changed option name em_paypal_booking_feedback_thanks to em_paypal_booking_feedback_completed
* added support for using HTML custom fields in #_BOOKINGFORMCUSTOM placeholder
* updated authorize.net SSL certificates
* rewritten Custom Emails feature including a more solid logic with new hooks enabling integration with EM Multilingual mode
* added Norwegian 
* updated Czech, German and Italian
* changed some decimal sizes in DB tables to match those in EM
* fixed manual bookings overwriting country user field and saving empty data to $EM_Booking->booking_meta['registration'] 
* fixed widget construct calls for WP 4.3+
* added recaptcha 2.0 library which can be activated by adding true EM_RECAPTCHA_2 constant in wp-config.php
* moved inline CSS out into own CSS files
* renamed some script hook function names to match firing filter/action
* changed all EM_Pro class functions to static
* fixed attendees not being removed when modifying booking and reducing number of tickets
* fixed user field types with array values not showing correctly in personal details section of booking
* fixed validation errors in multiple option fields where a trailing/leading space is entered in possible values
* changed MB em_booking_add hook to priority 5 to allow other hooks to intervene earlier
* fixed checkout redirect when adding a manual booking
* fixed previously saved custom user checkbox field always remaining checked if 'checked by default' is enabled

= 2.3.9 =
* fixed offline default gateway email not sent when adding offline payment
* fixed MB transaction IDs not showing on admin table during ajax navigation
* fixed helpful links for wp cron in the email reminders settings area
* fixed Authorize.net emails not going out,
* added tax and discount information to authorize.net itemized billing summary
* added paypal standard tax inclusion option to prevent price rounding and taxation miscalculations
* fixed some HTTP <> HTTPS AJAX issues
* updated admin settings HTML to match new styling
* changed localization functions for various strings already translated by EM so it's not picked up by the Pro POT file generators
* fixed translation error where new default booking and attendee forms aren't translated
* fixed email fields not showing to member if showing name fields is set to no
* fixed MB mode conflicts with other plugins due to session_start and saving of unserialized objects
* fixed PayPal auto-deleting of incomplete bookings not deleting MB master booking record 
* fixed MB mode checkout showing payment options for free booking
* fixed #_BOOKINGSUMMARY pre-tax subtotal price in MB mode which was showing the last event total
* updated Czech, German, Spanish, Finnish, French, Italian, Japanese, Norwegian, Polish, Russian, Swedish and Chinese. Thanks to all contributors, get in touch if you'd like to be on our translators credits!
* updated the POT file

= 2.3.8.1 =
* fixed erroneous coupon retrieval in some setting combinations due to 2.3.8 update

= 2.3.8 =
* changed html titles of event admin booking options from bolded text to h4 (coupons & custom forms)
* fixed booking forms not saving user fields if logged in (reverting previous modification in 2.3.7 which stopped saving this info to booking meta as well)
* added emp_hidden_reg_fields filter to allow custom showing of name and other hidden user fields
* fixed "pending payment" bug for authorize.net
* added optional css class flags (same as EM Booking form) to MB checkout/cart pages
* fixed custom booking/attendee forms not being passed onto recurrences
* fixed slashes being added to custom email html
* added wp_kses sanitization to custom emails
* changed saving gateway settings will stay on gateway settings page
* fixed various string translation issues due to lack of textdomain
* fixed attendees form not showing in bookings admin area for editing tickets with no spaces
* fixed/changed wp_footer calls to have lower priorities than 10 (i.e. higher than 10)
* updated French, German and Swedish, Polish
* updated POT file
* fixed default gateway emails not being used
* fixed captchas in individual event bookings triggering validation failures in MB checkout,
* fixed lack of proper escaping on form tips
* fixed coupon validation issues with Multiple Bookings Mode
* fixed cart headers not being translatable
* fixed bug when duplicating and then editing custom form overwriting default form
* fixed minor php warning triggered in custom gateway emails
* added parent hook for new em_booking_is_pending filter in EM_Gateway
* fixed admin area CSS not being used if pro front-end css is disabled via dbem_disable_css option
* added dbem_disable_css option to wp_options to prevent extra SQL query
* fixed country selection ddm field default text not being translated
* fixed select and multiselect options potentially adding extra spaces to input values
* fixed paypal IPN validation error due to wp_magic_quotes adding slashes with magic_quotes_gpc disabled
* changed custom emails meta box to appear on event admin if manage_bookings is enabled (previously required manage_others_bookings),
* added warning text to custom event emails meta box if in multiple bookings mode
* fixed potential duplicated reminder emails in MultiSite Global Tables mode
* fixed missing ical file in reminders due to merged ical templates in EM 5.4.2
* fixed coupons and transactions not showing master booking values in admin tables when MB mode is active
* fixed transaction history not showing relevant master booking transactions in individual bookings during MB mode
* added parent hook for new em_booking_is_pending filter in EM_Gateway
* changed EM_Multiple_Bookings::get_main_booking so it instantly returns false if EM_Booking object not supplied
* fixed premature auto-deletion of PayPal bookings since EM 5.5.2 due to blog vs mysql timezone clashes
* fixed Pro key being deleted from MultiSite network admin settings page
* fixed MB mode removal of booking in cart not reflecting spaces change and still passing deleted booking to gateways
* fixed only first instance of #NOW# being replaced in attendee forms
* fixed MB mode issues with checkout booking forms and coupon codes being submitted as well as via the 'apply discount' button
* changed lowered cron emails init priority to possibly fix cron issues
* added Norwegian translation
* added MB mode feature to allow users with edit_others_bookings to sync no-user booking personal info edits to all bookings in a MB booking set
* changed PayPal gateway to use HTTP 1.1 when verifying IPN authenticity
* fixed php warning when invalid user field name supplied to \EM\Payments\Gateways::get_customer_field
* improved cart coupon JS and changed template to make coupon application a different html form entirely to allow for graceful fallback
* fixed bug where wrong data type returned when looking for gateway common fields that don't exist
* added failsafe for known fatal EM version conflicts
* fixed manual booking user fields not appearing on front-end in MB mode
* fixed free MB bookings sending pending emails and saving with 0 status with approvals disabled
* fixed PHP warnings in MB mode checkout (complementary fix in EM 5.5.2.7)
* fixed EM_Notice and custom forms warnings in booking admin pages
* added option to show name and email fields on booking forms to logged in users
* fixed old bookings from free version not showing comments field for field with id booking_comment
* fixed first/last name fields not forcing required entry
* fixed wrong attendee forms showing up in event lists with different forms
* fixed PayPal gateway not passing customer address info to pre-fill form on paypal.com
* fixed user fields of html type showing label like input fields on profile page and booking information pages
* fixed manual bookings not allowing booking of role-restricted tickets if event admin isn't the same role
* fixed cart page overriding checkout page if the same page is chosen by mistake in MB mode settings
* fixed false-positive unprocessed email notices for IPNs originating from non-EM PayPal payments
* fixed dots being saved in custom form field IDs, as this causes validation issues in browsers
* fixed custom user field date and time pickers not working in WP dashboard profile page
* fixed recurring events not showing custom email editor 

= 2.3.7 =
* fixed site/event-wide coupons not showing up in coupon manager in admin area
* changed event/site wide choice to be one or the other, to avoid confusion
* fixed user link problems on secondary ajax-loaded pages of transactions table
* changed $EM_Booking->booking_meta['registration'] will not save user info if already logged in
* fixed CSV export issues if exporting by attendee and booking has no attendee data (if not previously activated/used)
* fixed bug where double-spaced option values fail required validation (saved form strips extra spaces, entities will still work and validate)
* added gateway column to booking tables and exports
* fixed date not being formatted in transactions table
* fixed unwanted MB page refresh on checkout if redirect option enabled

= 2.3.6 =
* fixed rejections being given a transaction record as if paid for offline bookings
* fixed booking form not accepting blank values when editing (e.g. textboxes)
* fixed newly added checkboxes showing as checked on previous bookings
* fixed some _doing_it_wrong triggered functions in BuddyPress whilst in debug mode
* prevented non-existent values for texboxes and textareas defaulting to n/a when editing a booking
* updated German
* fixed coupon-event associations not being deleted with event
* fixed coupons being associated with event if event/site-wide (not necessary)
* fixed text fields when editing booking showing n/a when originally empty
* changed MB bookingsummary template file name to bookingsummary-multiple.php 
* added excel hack to attendees csv form,
* changed EM_Coupons functions to static to prevent php warning
* fixed translations missing domains
* fixed price breakdowns in MB mode
* removed g preg modifier in authorize.net gateway for event name sanitizing
* fixed conflicts with wpmudev membership
* added em_gateawy_authorize_aim_sale_var filter before sending to AIM,
* changed paypal ipn endpoint error message if user visits url
* added showing coupons available to events in MB mode when editing event
* changed available coupons in bookings are now hidden/collapsed
* improved EM_Coupons::get() argument logic for site/event-wide searches 
* changed MB mode coupon searches return all coupons since all are now site-wide 
* changed saving coupons in MB mode automatically makes it event/site-side
* changed #_BOOKINGTICKETS template file name to bookingtickets-multiple.php,
* fixed confirmation notice being added as an error when saving mb booking
* added 'total' text to MB mode cart widget
* fixed coupon not applying discount to total price in MB mode cart/checkout
* fixed MB mode admin cart showing  last ticket price as sub-total
* corrected double underscore in MB cancelled email template option names
* fixed mb mode single event emails not going to event owners if enabled in settings page
* changed MB email options won't show until MB mode is enabled
* fixed modifying a single booking not updating over multiple booking total price
* added is_normal_field and is_user_field functions to EM_Form,
* added MB mode fields to booking tables and export columns
* fixed validation issues for select/country/radio fields and non-permitted values
* changed - moved files containing main classes in add-on folder into sub-folders
* changed - moved admin functions for email and mb mode to designated admin classes
* changed - separated admin functions from EM_Gateways to EM_Gateways_Admin class
* added custom emails functionality to events and gateways
* fixed mb cart 'checkout' button pointing to homepage if not using permalinks
* fixed MB bookings not sending email if total price equals 0
* fixed non site/event-wide coupons not being applied to recurrences
* fixed various missing translation domains for gettext functions
* updated POT file

= 2.3.5 =
* revamp of coupons system, uses v5.4 discount system
* coupons now support Multiple Bookings Mode
* coupons can now be added pre/post tax
* delayed transactions table init() so BP doesn't trigger a _doing_it_wrong function
* moved MB stuff out of emp-admin.php and into multiple-bookings-admin.php
* added option for redirection to checkout page for multiple bookings whenever making a single booking
* updated MB mode and PayPal gateway to use new v5.4 pricing functions
* fixed some php warnings
* updated russian language file
* improved retrieval of booking name in MB mode, e.g. single event booking = event name, multiple booking = 'Multiple Events'

= 2.3.4 =
* fixed bug when trying to switch booking forms in form editor
* fixed localization typo
* added sanitation to various input textboxes in admin area

= 2.3.3 =
* fixed coupon final price miscalculations when tax is automatically included in ticket price
* fixed customer user gateway fields not being passed on correctly when in no-user mode
* fixed password user field problems in MB mode
* improved pro update notifier and key checking consistency
* added option for MB bookings submit button
* fixed form regex rules still being required if value is blank and form field not required
* fixed user profile page failed validation still resulting in update notice along with errors

= 2.3.2 =
* added Finnish
* updated German
* fixed various PHP warnings
* fixed user bookings link pointing to admin admin area from front-end bookings admin
* removed some redundant code from paypal gateway
* added 'empty cart' button to 'view cart' page
* fixed custom user fields not being saved properly in MB mode
* fixed manual bookings for MB mode
* Multiple Bookings now beta, no flag required in wp-config.php file

= 2.3.1 =
* added dbem_capability_forms_editor wp_option for showing form editor menu to other user roles
* added em_attendees_form_admin_page_actions action
* added some extra esc_ and wp_kses functions for sanitization
* added ids to setting sections to work with new EM UX JS
* improved MS Global mode so unnecessary tables aren't created for new blogs
* added wp_title filter to multiple bookings widget
* fixed manual bookings asking for a gateway since v2.3

= 2.3 =
* fixed newly created user during booking not being deleted on bad card info via authorize.net
* updated Swedish translation
* fixed php warning
* fixed permission problems in MS preventing form editor and other admin screens from showing to admins without plugin rights
* updated Swedish
* fixed newly created users not being deleted in MultiSite if bad A.net card info is supplied
* added Multiple Bookings feature
* fixed MultiSite PHP warning on blog creation when visiting blog first time round
* added hooks to edit no-user booking personal information and custom user fields (requires EM 5.3.5.3 or higher)
* better template/class renaming, adjusted AJAX loading methods to account for caching plugins
* moved email reminders out of beta
* fixed checkboxes, radios and multiselect custom fields in booking form not being editable by admin
* fixed checkboxes, radios and multiselect attendee fields not being correctly editable by admin
* fixed some attendee form display and CSS issues
* fixed tips not appearing for core user fields

= 2.2.9 =
* important security update for some XSS vunlerabilities - see http://em.cm/xss
* fixed blank date and time custom fields breaking datepickers for editing user/booking information
* fixed coupon placeholders remaining if booking doesn't have a coupon associated with it 

= 2.2.8 =
* fixed IPN validation failing when ticket names contain special characters
* improved IPN validation requesting, using GET method via wp_remote_get 

= 2.2.7 - Users using 2.2.6 with PayPal should upgrade immediately =
* fixed mysql error when getting transaction data in specific circumstances
* fixed pro scripts not loading along with EM scripts in admin area (e.g. bookings dashboards)
* (critical) fixed IPN verifications failing with live PayPal accounts 

= 2.2.6 =
* fixed events with one non-required ticket not showing 1 attendee form (when shown in ticket table format)
* fixed some non-translated strings, updated pot file 
* added Russian translation
* fixed registered user info not showing up on booking details/exports if no-user mode and manual booking is made
* fixed dates and other fields not being formatted when displayed using placeholders e.g. in emails
* updated French translation
* adding 'define('EMP_SHARED_CUSTOM_FIELDS',true);' to your wp-config.php file allows user field ids to not be prefixed with dbem_ (for sharing user meta with other plugins)
* added compatability with new script loading system
* fixed PayPal IPN verification mechanism, including fallback for curl on servers with outdated SSL certificates
* limited admin JS loading
* added fix for badly saved/displaying user meta date/time fields, added installation script to fix previously bad date/time user meta values 
* added fix for normal field country output formatting
* added coupon calculation to CSV ticket total calculation
* added removal of header in CSV if EM_CSV_DISABLE_HEADERS is defined
* added coupon code placeholders #_BOOKINGCOUPON, #_BOOKINGCOUPONCODE, #_BOOKINGCOUPONDISCOUNT, #_BOOKINGCOUPONNAME, #_BOOKINGCOUPONDESCRIPTION
* added coupon code column to booking tables and csv export
* fixed IE8/safari form editor display issues
* fixed manual bookings allowing double bookings depending on EM settings
* fixed updates not accessing update information e.g. changelogs from our servers
* changed em_booking_add apply_filter to add_action (since it's an action)

= 2.2.5 =
* fixed some non-translated strings
* updated the POT file
* added Chinese translation, thanks to Leo Losoviz
* fixed pending/reserved spaces not being approvable, requires EM 5.3 to work

= 2.2.4.1 =
* fixed badly named folder for upgrade
* fixed require_once() with hardcoded path causing install/upgrade issues

= 2.2.4 =
* fixed attendee forms ommitting first attendee in each ticket
* fixed attendee #NUM# not being converted if not in an html element
* fixed tips not being added to dynamic attendee fields
* fixed radio and checkboxes not being read properly for attendees
* updated Swedish
* updated bookings currency tip link
* added $field info to emp_forms_output_field filter
* changed user creation/deletion in first-registration failed authorize.net bookings to use internal account creation timer
* fixed event reminders not reading booking placeholders
* fixed/improved first-time user deletion on bad authorize.net card data
* changed paypal cron hook em_cron_hook to em_paypal_cron
* fixed pending individual ticket counts with PayPal bookings in progress/reserved
* added condition to not validate #_BOOKINGBUTTON bookings
* added complete activation/deactivation of attendee ticket functions
* changed is_main_blog functions to is_main_site
* updated pot file, Swedish
* added check for paid bookings with no gateway choice (anti-spam/hack)
* added Japanese
* removed site language option (repeated option, typo)
* added all countries for paypal destination site language
* prevented/fixed various php warnings
* removed parse_query hook for permalink gateway handling (i.e. catching paypal IPNs), gateways now use a direct wp-admin/admin-ajax.php... url
* fixed validation of manual bookings and editing of bookings forcing address fields, changed priority of EM_Booking_Form booking interception
* moved ticket/booking pending space calculations to base gateway class, now accounts for all gateways
* fixed person data not being saved to EM_Person instance on first booking
* fixed attendee form loading depending on default tickets (based on introduction of EM_Ticket::is_required() in EM 5.2.9)
* fixed EM_Person information not being saved to instance on first booking, causing authorize.net to not retrieve first/last name correctly
* removed custom html from form when editing a booking
* improved default attendee form to include attendee #NUM#
* attendee fields is now beta and ready to go

= 2.2.3 =
* added attendee forms - alpha - add define('EM_ATTENDEES',true); to your wp-config.php file
* fixed some display / validation errors in booking forms when modifying booking
* fixed #_BOOKINGTXNID returning placeholder if there's an empty value, now returns an empty value
* fixed minimum spaces calculations for attendees, as per fix in 5.2.5.2
* fixed non-editable user fields breaking validation
* updated German translation
* fixed link still showing on the single booking view to the assigned default user for no-user bookings
* hid some js localized vars if bookings disabled

= 2.2.2 =
* fixed no-user mode and user fields bug still happening in CSV exports
* fixed MS global mode showing network transactions on all blogs
* cleaned up options html for form editor
* added tip text to every field and fixed display of tip text
* fixed some badly named options
* fixed custom user form field options not superceding defaults and vice versa
* fixed paypal bookings resume payment button not working as expected
* added cancel link to paypal bookings in progress
* fixed AIM fatal error when using address 2 in forms etc.
* fixed no-user mode not updating custom user fields for already logged in users
* fixed name user field not validating properly
* streamlined emp-forms.php validation switch, name and email validated like other reg fields

= 2.2.1 =
* fixed MS network blog tables not being deleted by WP along with rest of blog
* fixed no-user mode bug showing assigned user information on the booking information page
* fixed reminder emails including long spanning events that already started 

= 2.2 =
* db table installation will take current blog prefix rather than determine if it's in global tables mode
* fixed transactions not deleting if event is already deleted
* fixed coupon dates not working
* added em_coupon_get_discount_text filter
* added paypal default language option
* added extra values to the epm_forms_output_field_input filter
* fixed multisite error when fetching transaction info
* fixed some form action calls (from add_action to do_action)
* added country to form field
* fixed extra blank field in form editor
* added user address field association, allowing for tighter integration with gateways
* added email reminders
* added option to show logged in users their registration fields in booking forms
* fixed PayPal gateway not taking pending payments into account and treating as in-progress (deleted automatically)
* fixed custom booking form not showing on forms outside of main event page
* fixed manual bookings not showing new user fields
* fixed default form install bug if pro installed first
* fixed some action typos on EMP_Forms editor html
* added em_coupon_is_valid filter
* fixed em_coupon_get_person filter typo
* added user password custom field
* added date and time picker custom fields
* added 'required' asterisks next to labels
* fixed required text fields not accepting a 0
* fixed paypal settings not saving if paypal email not supplied
* added custom tooltips to field labels

= 2.1.5 =
* fixed manual bookings not allowing admins booked to that event with double bookings disabled
* added missing error message on manual booking form admin-side validation
* fixed offline status not being editable if de-activated yet making a manual booking
* added classes to coupon code and authorize booking form elements
* fixed manual bookings bug for another user without a payment
* set status to pending rather than cancelled for re-review if partial refunds are made
* transactions now get deleted with bookings
* added manual delete transaction
* fix for multiple booking forms on one page
* further improvement to loading of a.net SDK to avoid plugin conflicts

= 2.1.4 =
* fixed authorize.net conflicts if SDK already loaded by another plugin
* added failed email message to offline bookings that go through
* improved fallback for javascript booking form failures (particularly paypal)
* added input class to text fields in booking form for coupons and gateways
* fixed manual booking link issues
* fixed authorize.net "invalid line 1" errors due to long ticket names
* fixed email regex settings not working (requires a resave of form settings)
* manual bookings accept partial payments
* fixed invalid coupons still allowing bookings to go through

= 2.1.3 =
* added gateway transaction id to booking collumns
* fixed form editor validation problems

= 2.1.2 =
* allowed form labels to accept HTML
* fixed paypal resume payment button
* fixed paypal payment status text
* modified coupon calculation to add tax after discount, if tax is added seperately
* made paypal bookings editable even if pending
* fixed various form editor bugs
* fixed email problems with paypal confirmations
* manual bookings now accept coupons and anonymous registrations, as well as custom payment amounts
* added more html css classes to booking form
* made update notices more user-friendly if pro-api-key isn't valid

= 2.1.1 =
* fixed coupon pagination problem
* fixed captcha failures due to duplicated checks
* fixed user fields and Array being shown for multi-option fields
* removed dev version checking (moving to free) and add one-off dev version check
* reverted to using .delegate() instead of jQuery 1.7+ .on() listener for compatibility

= 2.1 =
* offline payment confirmation window can be properly cancelled (bugfix)
* membership key options now showing properly in MS mode
* added custom user fields
* added custom booking forms per event
* detached booking form editor into a re-usable class for user fields and future custom forms

= 2.0.4 =
* fixed pro member key issue in MultiSite
* coupons saving properly in MS Global Tables mode.
* added coupon count and history

= 2.0.2 =
* added html filtering for ticket names sent to paypal
* fixed offline manual partial payemnt formats bug
* added some translatable strings
* membership key entry will force recheck of plugin updates
* fixed captcha includes breaking form submissions
* added classes to custom booking form html
* added cancel url to PayPal gateway
* fixed Authorize.net gateway creating wp accounts when CC info is bad

= 2.0 =
* fixed checkboxes defaulting to selected
* rewritten gateway API, add custom gatways much faster and efficiently
* added Authorize.net AIM Gateway
* added coupons feature Coupons
* restructured files
* various minor bug fixes
* updated Russian translation
* prevented from loading EMP if EM isn't activated

= 1.51 =
* fixed offline custom message not working
* fixed paypal ticket descriptions and special characters (using UTF-8)
* fixed view transactions blank page from gateways page

= 1.5 =
* paypal now pre-registers user before redirecting if applicable (more stable, more possibilities)
* added #_BOOKINGTXNID to booking placeholders for paypal transaction ID
* fixed placeholders for custom form fields
* html now accepted in booking form feedback in gateways
* small usability improvements to manual booking form
* transactions tabled now unified to reduce clutter
* paypal return url modified to use a static file (wp-admin/admin-ajax.php) and the previous url as a fallback

= 1.45 =
* fixed booking form placeholders
* #_CUSTOMBOOKING now works for #_CUSTOMBOOKINGREG fields
* html not escaped with slashes in custom booking gateway feedback messages

= 1.44 =
* fixed booking form regexes making inputs required
* paypal won't allow registered emails in guest mode
* paypal bookings only considered as pending if timeout is set (paypal pending payments view coming shortly)

= 1.43 =
* important bug fix for paypal bookings

= 1.42 =
* Custom registration booking placeholder fixed

= 1.41 =
* Updated to support version 5 (required)

= 1.39 =
* fixed yahoo field name for saving into booking regsitration
* fixed page navigation for pending payments
* fixed checklist booking saving bug
* paypal IPN soft fail introduced, to reduce alternante payment software 404s

= 1.38 =
* fixed minor php warning
* added em_gateway_paypal_get_paypal_vars filter
* fixed default custom form issue with validating emails in guest bookings
* fixed duplicate indexes in transaction table
* manual bookings by less than admins not impeded by permission errors

= 1.37 =
* allows negative manual payments
* paypal return url instructions corrected

= 1.36 =
* fixed bug which prevented transaction tables showing unregistered/deleted users.
* warning added if EM plugin version is too low
* update notices appear on the network admin area as well
* added cron tasks for paypal booking timeouts
* added return url option for paypal
* custom booking form information properly escaped and filtered
* paypal manual approvals won't take effect with normal approvals disabled
* offline and paypal pending spaces taken into account
* paypal and offline payments take tax into account (requires EM 4.213)
* fixed logo not being shown on paypal payment page
* payments in no-user mode accepted (requires EM 4.213)

= 1.35 =
* added alternative notification check for servers with old SSL Certificates
* added dev mode updates option in the events setttings page
* removed the main gateway JS
* manual bookings can now be done by all users with the right permissions
* paypal payments will not include free tickets during checkout paying, avoiding errors on paypal
* pot files updated
* German and Swedish translations updated
* fixed various warnings
* multiple alert boxes when confirming offline payments fixed