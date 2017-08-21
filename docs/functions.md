Functions
=========

Some generics functions are available in framework to help you in development.

## Dates ##

* `b_time_to_sec(...)`: return the number of seconds since midnight with hour param in format (H:m:i)
* `b_sec_to_time(...)`: format time with timestamp in entry
* `b_date_format(...)`: format date/time object or timestamp to the given pattern
* `b_age(...)`: calculation of age with birthday
* `b_db_date(...)`: convert date in entry to the database format (computer format)


## Forms ##

* `b_form_protect(...)`: protect data passed into form values
* `b_form_control(...)`: control form input data (from $_GET or $_POST)
* `b_form_control_get(...)`: control form input data from $_GET
* `b_form_control_post(...)`: control form input data from $_POST


## Security ##

* `b_is_secured_page(...)`: is secured page ?
* `b_get_secured_page(...)`: get secured page of given url


## Strings ##

* `b_mb_detect_encoding(...)`: mb_detect_encoding() alternative (using iconv)
* `b_detect_utf_encoding(...)`: detect UTF encoding of string or files
* `b_remove_bom`(...): remove the BOM of UTF string or files
* `b_truncate(...)`: truncate string
* `b_remove_entities(...)`: remove entities from string
* `b_strtouri(...)`: treat string for url
* `b_hazard_string(...)`: generate an hazard string
* `b_email_account(...)`: extract account part of email
* `b_email_domain(...)`: extract domain of email
* `b_nl2p(...)`: surrounds paragraphs with "P" HTML tag and inserts HTML line breaks before all newlines


## Objects ##

* `b_property_get(...)`: get property value to an object when we don't know getter format
* `b_property_set(...)`: set property value to an object when we don't know setter format


## Arrays ##

* `b_array_traverse(...)`: traverse array with keys
* `b_array_merge_recursive(...)`: merge two or more arrays recursively


## Images ##

* `b_gradient_color(...)`: calculate a gradient destination color
* `b_img_size(...)`: calculate sizes with new given width and height
* `b_img_resize(...)`: resize image
* `b_img_support(...)`: resize support of image
