<?php
/**
 * Extends the EM_Form to take into consideration the fact that many attendee sub-forms are submitted in one booking, unlike EM_Form which is geared to dealing with a single simple form submission.
 * @author marcus
 *
 */
class EM_Attendee_Form extends EM_Form {
	public $event_id;
	/**
	 * For use in output_field_input
	 * @var int
	 */
	public $attendee_number = false;
    
    /* 
     * Overrides default method by search/replacing a specific string of text located in the input field name - [%n] - which represents the number of the attendee for that ticket
     * @see EM_Form::output_field_input()
     */
    function output_field_input($field, $post=true){
    	//fix for previously not escaping arrays during saving and comparing to escaped values before output
    	if( is_array($post) ){
		    $esc_deep = function( $data ) use ( &$esc_deep ){
			    if( is_array($data) ){
				    foreach( $data as $k => $v ){
					    $data[$k] = $esc_deep( $v );
				    }
			    }else{
				    $data = esc_attr( $data );
			    }
			    return $data;
		    };
		    $post = $esc_deep( $post );
    	}
    	//get the field output
        $output = parent::output_field_input($field, $post);
        //replace %n with attendee number where appropriate
	    if( $this->attendee_number !== false ) {
		    $output = str_replace('[%n]', '[' . $this->attendee_number . ']', $output);
	    }
    	return $output;
    }
    
}