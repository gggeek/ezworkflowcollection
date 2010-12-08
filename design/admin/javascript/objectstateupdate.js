// JavaScript Document using jQuery
// Input :	event_id (int) Workflow process event id
//			select_before_value (int) Selected group ID
//			select_after_value (int) Selected group ID
//function updateList($event_id, $select_before_value, $select_after_value)
function updateList( $event_id )
{
	// This javascript code needs JQuery loaded to work
	$(document).ready(function () {
	    var select_before = '#before_' + $event_id;
	    $(select_before).change(function () {
	        $(select_before + ' option').each(function () {

				//if($select_before_value != this.value ) {
				if( $(select_before).val() != this.value ) {
	            	$(select_before + '_' + this.value).attr('selectedIndex', '-1');
	            }

	            $(select_before + '_' + this.value).hide();
	        });
	        $(select_before + ' option:selected').each(function () {
	            $(select_before + '_' + this.value).show();
	        });
	    }).change();

	    var select_after = '#after_' + $event_id;

	    $(select_after).change(function () {
	        $(select_after + ' option').each(function () {

	            //if( $select_after_value != this.value ) {
	            if( $(select_after).val() != this.value ) {
		            $(select_after + '_' + this.value).attr('selectedIndex', '-1');
	            }

	            $(select_after + '_' + this.value).hide();
	        });
	        $(select_after + ' option:selected').each(function () {
	            $(select_after + '_' + this.value).show();
	        });
	    }).change();
	});
}
