<?php
/** This is a replica of ajax_frontend.inc.php from WPCW plugin. Except we are customizing to fit i-4Web needs
 *
 * /**
 * Frontend only AJAX functions.
 */
include_once WPCW_plugin_getPluginDirPath() . 'lib/frontend_only.inc.php'; // Ensure we have frontend functions

// Use object to handle the rendering of the unit on the frontend.
include_once WPCW_plugin_getPluginDirPath() . 'classes/class_frontend_unit.inc.php';

// Add the action that will be called by the Ajax function in ajax-front-end.js
if (is_admin()) {
    add_action('wp_ajax_i4_lms_handle_unit_track_progress', 'I4web_LMS_AJAX_units_handleUserProgress');
    add_action('wp_ajax_i4_lms_handle_unit_quiz_retake_request', 'I4web_LMS_AJAX_units_handleQuizRetakeRequest');
    add_action('wp_ajax_i4_lms_handle_unit_quiz_response', 'I4web_LMS_AJAX_units_handleQuizResponse');
    add_action('wp_ajax_i4_lms_handle_unit_quiz_timer_begin', 'I4web_LMS_AJAX_units_handleQuizTimerBegin');
    add_action('wp_ajax_i4_lms_handle_unit_quiz_jump_question', 'I4web_LMS_AJAX_units_handleQuizJumpQuestion');

    add_action('wp_ajax_i4_lms_handle_check_email', 'i4_ajax_check_new_patient_email');
    add_action('wp_ajax_i4_lms_handle_check_username', 'i4_ajax_check_new_patient_username');


}

/**
 * Called when adding a new patient.
 *
 */
function i4_ajax_check_new_patient_email() {
    global $current_i4_user;

    $response = array();

    // Security check
    $security_check = check_ajax_referer('add_new_patient_nonce', 'security', false);

    if (!$security_check) {
        die (__('Sorry, we are unable to perform this action. Contact support if you are receiving this in error!', 'i4'));
    }

    //Perform a permissions check just in case
    if (!user_can($current_i4_user, 'manage_patients')) {
        die (__('Sorry but you do not have the proper permissions to perform this action. Contact support if you are receiving this in error', 'i4'));
    }

    //Check if the email is already taken
    $new_patient_email = sanitize_text_field($_POST['patient_email']);

    if (email_exists($new_patient_email) || !is_email($new_patient_email)) {
        $response['status'] = 409; //Conflict response code
        $response['email'] = $new_patient_email;
        $response['icon'] = '<i class="fa fa-exclamation"></i>';


    }
    elseif (!email_exists($new_patient_email) && is_email($new_patient_email)) { //when an email does not exist and is in a valid email format
        $response['status'] = 200; //ok Status
        $response['email'] = $new_patient_email;
        $response['icon'] = '<i class="fa fa-check"></i>';
    }

    echo json_encode($response); //sends the response endcoded via JSON to the AJAX call
    die();
}

/**
 * Called when adding a new patient.
 *
 */
function i4_ajax_check_new_patient_username() {
    global $current_i4_user;

    $response = array();

    // Security check
    $security_check = check_ajax_referer('add_new_patient_nonce', 'security', false);

    if (!$security_check) {
        die (__('Sorry, we are unable to perform this action. Contact support if you are receiving this in error!', 'i4'));
    }

    //Perform a permissions check just in case
    if (!user_can($current_i4_user, 'manage_patients')) {
        die (__('Sorry but you do not have the proper permissions to perform this action. Contact support if you are receiving this in error', 'i4'));
    }

    //Check if the email is already taken
    $new_patient_username = sanitize_text_field($_POST['patient_username']);

    if (username_exists($new_patient_username) || !validate_username($new_patient_username)) {
        $response['status'] = 409; //Conflict response code
        $response['username'] = $new_patient_username;
        $response['icon'] = '<i class="fa fa-exclamation"></i>';


    }
    elseif (!username_exists($new_patient_username) && validate_username($new_patient_username)) { //when an email does not exist and is in a valid email format
        $response['status'] = 200; //ok Status
        $response['username'] = $new_patient_username;
        $response['icon'] = '<i class="fa fa-check"></i>';
    }

    echo json_encode($response); //sends the response endcoded via JSON to the AJAX call
    die();
}

/**
 * Function called when user is requesting a retake of a quiz. Lots of checking
 * needs to go on here for security reasons to ensure that they don't manipulate
 * their own progress (or somebody elses).
 */
function I4web_LMS_AJAX_units_handleQuizRetakeRequest() {

    // Security check
    if (!wp_verify_nonce(WPCW_arrays_getValue($_POST, 'progress_nonce'), 'wpcw-progress-nonce')) {
        die (__('Security check failed!', 'wp_courseware'));
    }

    // Get unit and quiz ID
    $unitID = intval(WPCW_arrays_getValue($_POST, 'unitid'));
    $quizID = intval(WPCW_arrays_getValue($_POST, 'quizid'));

    // Get the post object for this quiz item.
    $post = get_post($unitID);
    if (!$post) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not request a retake for the quiz.', 'wp_courseware') . ' ' . __('Could not find training unit.', 'wp_courseware'));
        die();
    }

    // Initalise the unit details
    $fe = new I4Web_LMS_Front_End_Unit($post);

    // #### Get associated data for this unit. No course/module data, then it's not a unit
    if (!$fe->check_unit_doesUnitHaveParentData()) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not request a retake for the quiz.', 'wp_courseware') . ' ' . __('Could not find course details for unit.', 'wp_courseware'));
        die();
    }

    // #### User not allowed access to content
    if (!$fe->check_user_canUserAccessCourse()) {
        echo $fe->message_user_cannotAccessCourse();
        die();
    }

    // #### See if we're in a position to retake this quiz?
    if (!$fe->check_quizzes_canUserRequestRetake()) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not request a retake for the quiz.', 'wp_courseware') . ' ' . __('You are not permitted to retake this quiz.', 'wp_courseware'));
        die();
    }

    // Trigger the upgrade to progress so that we're allowed to retake this quiz.
    $fe->update_quizzes_requestQuizRetake();

    // Only complete if allowed to continue.
    echo $fe->render_detailsForUnit(false, true);
    die();
}


/**
 * Function called when the user is marking a unit as complete.
 */
function I4web_LMS_AJAX_units_handleUserProgress() {
    // Security check
    if (!wp_verify_nonce(WPCW_arrays_getValue($_POST, 'progress_nonce'), 'wpcw-progress-nonce')) {
        die (__('Security check failed!', 'wp_courseware'));
    }

    $unitID = WPCW_arrays_getValue($_POST, 'id');

    // Validate the course ID
    if (!preg_match('/unit_complete_(\d+)/', $unitID, $matches)) {
        echo I4Web_LMS_Front_End_Unit::message_error_getCompletionBox_error();
        die();
    }
    $unitID = $matches[1];


    // Get the post object for this quiz item.
    $post = get_post($unitID);
    if (!$post) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not save your progress.', 'wp_courseware') . ' ' . __('Could not find training unit.', 'wp_courseware'));
        die();
    }

    // Initalise the unit details
    $fe = new I4Web_LMS_Front_End_Unit($post);

    // #### Get associated data for this unit. No course/module data, then it's not a unit
    if (!$fe->check_unit_doesUnitHaveParentData()) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not save your progress.', 'wp_courseware') . ' ' . __('Could not find course details for unit.', 'wp_courseware'));
        die();
    }

    // #### User not allowed access to content
    if (!$fe->check_user_canUserAccessCourse()) {
        echo $fe->message_user_cannotAccessCourse();
        die();
    }

    WPCW_units_saveUserProgress_Complete($fe->fetch_getUserID(), $fe->fetch_getUnitID(), 'complete');

    // Unit complete, check if course/module is complete too.
    do_action('wpcw_user_completed_unit', $fe->fetch_getUserID(), $fe->fetch_getUnitID(), $fe->fetch_getUnitParentData());

    // Only complete if allowed to continue.
    echo $fe->render_detailsForUnit(false, false);
    die();
}

/**
 * Function called when a user is submitting quiz answers via
 * the frontend form.
 */
function I4web_LMS_AJAX_units_handleQuizResponse() {
    // Security check
    if (!wp_verify_nonce(WPCW_arrays_getValue($_POST, 'progress_nonce'), 'wpcw-progress-nonce')) {
        die (__('Security check failed!', 'wp_courseware'));
    }

    // Quiz ID and Unit ID are combined in the single CSS ID for validation.
    // So validate both are correct and that user is allowed to access quiz.
    $quizAndUnitID = WPCW_arrays_getValue($_POST, 'id');

    // e.g. quiz_complete_69_1 or quiz_complete_17_2 (first ID is unit, 2nd ID is quiz)
    if (!preg_match('/quiz_complete_(\d+)_(\d+)/', $quizAndUnitID, $matches)) {
        echo I4Web_LMS_Front_End_Unit::message_error_getCompletionBox_error();
        die();
    }

    // Use the extracted data for further validation
    $unitID = $matches[1];
    $quizID = $matches[2];

    // Get the post object for this quiz item.
    $post = get_post($unitID);
    if (!$post) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not save your quiz results.', 'wp_courseware') . ' ' . __('Could not find training unit.', 'wp_courseware'));
        die();
    }

    // Initalise the unit details
    $fe = new I4Web_LMS_Front_End_Unit($post);
    $fe->setTriggeredAfterAJAXRequest();


    // #### Get associated data for this unit. No course/module data, then it's not a unit
    if (!$fe->check_unit_doesUnitHaveParentData()) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not save your quiz results.', 'wp_courseware') . ' ' . __('Could not find course details for unit.', 'wp_courseware'));
        die();
    }

    // #### User not allowed access to content
    if (!$fe->check_user_canUserAccessCourse()) {
        echo $fe->message_user_cannotAccessCourse();
        die();
    }

    // #### Check that the quiz is valid and belongs to this unit
    if (!$fe->check_quizzes_isQuizValidForUnit($quizID)) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not save your quiz results.', 'wp_courseware') . ' ' . __('Quiz data does not match quiz for this unit.', 'wp_courseware'));
        die();
    }

    $canContinue = false;


    // #### Do we have all the answers that we need so that we can grade the quiz?
    // #### Answer Check Variation A - Paging
    if ($fe->check_paging_areWePagingQuestions()) {
        // If this is false, then we keep checking for more answers.
        $readyForMarking = $fe->check_quizzes_canWeContinue_checkAnswersFromPaging($_POST);
    }

    // #### Answer Check Variation B - All at once (no paging)
    else {
        // If this is false, then the form is represented asking for fixes.
        $readyForMarking = $fe->check_quizzes_canWeContinue_checkAnswersFromOnePageQuiz($_POST);
    }


    // Now checks are done, $this->unitQuizProgress contains the latest questions so that we can mark them.
    if ($readyForMarking || $fe->check_timers_doWeHaveAnActiveTimer_thatHasExpired()) {
        $canContinue = $fe->check_quizzes_gradeQuestionsForQuiz();
    }


    // #### Validate the answers that we have, which determines if we can carry on to the next
    //      unit, or if the user needs to do something else.
    if ($canContinue) {
        WPCW_units_saveUserProgress_Complete($fe->fetch_getUserID(), $fe->fetch_getUnitID(), 'complete');

        // Unit complete, check if course/module is complete too.
        do_action('wpcw_user_completed_unit', $fe->fetch_getUserID(), $fe->fetch_getUnitID(), $fe->fetch_getUnitParentData());
    }


    // Show the appropriate messages/forms for the user to look at. This is common for all execution
    // paths.
    // DJH 2015-09-09 - Added capability for next button to show when a user completes a quiz.
    echo $fe->render_detailsForUnit(false, !$canContinue);
    die();

}


/**
 * Handle a user wanting to go to the previous question or jump a question without saving the question details.
 */
function I4web_LMS_AJAX_units_handleQuizJumpQuestion() {
    // Security check
    if (!wp_verify_nonce(WPCW_arrays_getValue($_POST, 'progress_nonce'), 'wpcw-progress-nonce')) {
        die (__('Security check failed!', 'wp_courseware'));
    }

    // Get unit and quiz ID
    $unitID = intval(WPCW_arrays_getValue($_POST, 'unitid'));
    $quizID = intval(WPCW_arrays_getValue($_POST, 'quizid'));

    $jumpMode = 'previous';
    $msgPrefix = __('Error - could not load the previous question.', 'wp_courseware') . ' ';

    // We're skipping ahead.
    if ('next' == WPCW_arrays_getValue($_POST, 'qu_direction')) {
        $jumpMode = 'next';
        $msgPrefix = __('Error - could not load the next question.', 'wp_courseware') . ' ';
    }


    // Get the post object for this quiz item.
    $post = get_post($unitID);
    if (!$post) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error($msgPrefix . __('Could not find training unit.', 'wp_courseware'));
        die();
    }

    // Initalise the unit details
    $fe = new I4Web_LMS_Front_End_Unit($post);


    // #### Get associated data for this unit. No course/module data, then it's not a unit
    if (!$fe->check_unit_doesUnitHaveParentData()) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error($msgPrefix . __('Could not find course details for unit.', 'wp_courseware'));
        die();
    }

    // #### User not allowed access to content
    if (!$fe->check_user_canUserAccessCourse()) {
        echo $fe->message_user_cannotAccessCourse();
        die();
    }

    // #### Check that the quiz is valid and belongs to this unit
    if (!$fe->check_quizzes_isQuizValidForUnit($quizID)) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error($msgPrefix . __('Quiz data does not match quiz for this unit.', 'wp_courseware'));
        die();
    }

    $canContinue = false;


    // If we're paging, then do what we need next.
    if ($fe->check_paging_areWePagingQuestions()) {
        $fe->fetch_paging_getQuestion_moveQuestionMarker($jumpMode);
    }

    echo $fe->render_detailsForUnit(false, true);
    die();
}


/**
 * Function called when user starting a quiz and needs to kick off the timer.
 */
function I4web_LMS_AJAX_units_handleQuizTimerBegin() {
    // Security check
    if (!wp_verify_nonce(WPCW_arrays_getValue($_POST, 'progress_nonce'), 'wpcw-progress-nonce')) {
        die (__('Security check failed!', 'wp_courseware'));
    }

    // Get unit and quiz ID
    $unitID = intval(WPCW_arrays_getValue($_POST, 'unitid'));
    $quizID = intval(WPCW_arrays_getValue($_POST, 'quizid'));

    // Get the post object for this quiz item.
    $post = get_post($unitID);
    if (!$post) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not start the timer for the quiz.', 'wp_courseware') . ' ' . __('Could not find training unit.', 'wp_courseware'));
        die();
    }

    // Initalise the unit details
    $fe = new I4Web_LMS_Front_End_Unit($post);

    // #### Get associated data for this unit. No course/module data, then it's not a unit
    if (!$fe->check_unit_doesUnitHaveParentData()) {
        echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not start the timer for the quiz.', 'wp_courseware') . ' ' . __('Could not find course details for unit.', 'wp_courseware'));
        die();
    }

    // #### User not allowed access to content
    if (!$fe->check_user_canUserAccessCourse()) {
        echo $fe->message_user_cannotAccessCourse();
        die();
    }

    // #### See if we're in a position to retake this quiz?
    // if (!$fe->check_quizzes_canUserRequestRetake())
    // {
    //     echo I4Web_LMS_Front_End_Unit::message_createMessage_error(__('Error - could not start the timer for the quiz.', 'wp_courseware') . ' ' . __('You are not permitted to retake this quiz.', 'wp_courseware'));
    //     die();
    // }

    // Trigger the upgrade to progress so that we can start the quiz, and trigger the timer.
    $fe->update_quizzes_beginQuiz();

    // Only complete if allowed to continue.
    echo $fe->render_detailsForUnit(false, true);
    die();
}

?>
