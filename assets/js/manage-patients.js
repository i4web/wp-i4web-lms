jQuery( document ).ready( function( $ ) {
    $(function() {
        //verify the input by the user when adding a new patient
        verifyPatientInput();

        $('#update-patient-courses-submit').on('click', function(e) {
            e.preventDefault();

            var courseIds = $("#user-courses").sortable("toArray");
            var patientId = $("#patientId").val();

            var data = {
                action      : 'i4_lms_handle_update_patient_courses',
                patientId   : patientId,
                courses     : courseIds
            };

            $.post(wpcw_js_consts_fe.ajaxurl, data, function(response) {
                if (response.status == 200) {
                    var modifyCoursesModal = $('#modify-courses-' + patientId);
                    modifyCoursesModal.foundation('reveal', 'close');
                    modifyCoursesModal.remove();
                }
            });
        });

        // The new user submit button.
        $('#add-new-patient-submit').on('click', function(e){
            e.preventDefault();

            var i4_patient_email = $('#patient_email').val(); //retrieve the patients email
            var i4_patient_username = $('#patient_username').val(); //retrieve the patients email
            var i4_patient_firstname = $('#patient_fname').val();
            var i4_patient_lastname = $('#patient_lname').val();

            // Trigger AJAX request to allow the user to retake the quiz.
            var data = {
              action              : 'i4_lms_handle_add_new_patient',
              security            : wpcw_js_consts_fe.new_patient_nonce,
              patient_email       : i4_patient_email,
              patient_username    : i4_patient_username,
              patient_fname       : i4_patient_firstname,
              patient_lname       : i4_patient_lastname
            };

            $.post(wpcw_js_consts_fe.ajaxurl, data, function(response) {
                  if( response.status == 200 ){
                      var patientId = response.patient_id;
                      var modalData = {
                          action        : 'i4_lms_get_modify_courses_modal',
                          patientId     : patientId,
                          patientName   : response.patient_name
                      };
                      $.get(wpcw_js_consts_fe.ajaxurl, modalData, function(modalResponse) {
                          // Add the modify courses modal to the body
                          $('body').append(modalResponse);
                          $( '#available-courses, #user-courses' ).sortable({
                              connectWith: ".connectedSortable",
                              revert: true
                          }).disableSelection();
                          // Hide the new user modal
                          $('#new-patient-modal').foundation('reveal', 'close');
                          // Open the modify courses modal
                          $('#modify-courses-' + patientId).foundation('reveal', 'open');
                      });
                  }
              }, 'json');
        });
    });

   /**
    * Verifies patient information prior to allowing the new patient to be inserted
    *
    */
    function verifyPatientInput(){

        //set the emailCheck and usernameCheck to false before we do anything
        var emailCheck = false;
        var usernameCheck = false;

        //declare the patient's email and username variables
        var i4_patient_email;
        var i4_patient_username;

        var nextButton = $j('#add-new-patient-submit'); //store the nextButton element

        nextButton.prop( "disabled", true ); //lets disable the button immediately.

        //the patient email field changes
        $j("#patient_email").change(function (e) {

            emailCheck = false; //assume the email is false everytime we begin this
            nextButton.prop( "disabled", true ); //disable the button in case it was enabled previously

            i4_patient_email = $j(this).val(); //retrieve the patients email

            var data = {
             action           : 'i4_lms_handle_check_email',
             security         : wpcw_js_consts_fe.new_patient_nonce,
             patient_email    : i4_patient_email
            };


            jQuery.post(wpcw_js_consts_fe.ajaxurl, data, function(response)
               {
                   $j('#i4_email_availability_status').html(response.icon);

                   if(response.status == 200 ){ //OK response
                        emailCheck = true;
                   }

                   if(usernameCheck && emailCheck){
                       nextButton.prop("disabled", false);
                   }
               }, 'json');

        }); //end patient email field changes

        //the patient username field changes
        $j("#patient_username").change(function (e) {
            usernameCheck = false; // assume the username is false everytime this field is changed
            nextButton.prop( "disabled", true ); //disable the button in case it was enabled previously

            i4_patient_username = $j(this).val(); //retrieve the patients email

            var data = {
                action                 : 'i4_lms_handle_check_username',
                security               : wpcw_js_consts_fe.new_patient_nonce,
                patient_username       : i4_patient_username
            };

            jQuery.post(wpcw_js_consts_fe.ajaxurl, data, function(response)
            {
                $j('#i4_username_availability_status').html(response.icon);

                if(response.status == 200 ){ //OK response
                     usernameCheck = true;
                }

                if(usernameCheck && emailCheck){
                    nextButton.prop("disabled", false);
                }
            }, 'json');

        });

   }
});
