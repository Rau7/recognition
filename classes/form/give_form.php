<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class give_form extends moodleform {
    public function definition() {
        global $CFG, $DB, $USER;

        $mform = $this->_form;

        // Badge selection
        $badges = $DB->get_records_menu('local_recognition_badges', ['enabled' => 1], 'name', 'id,name');
        $mform->addElement('select', 'badgeid', get_string('selectbadge', 'local_recognition'), $badges);
        $mform->setType('badgeid', PARAM_INT);
        $mform->addRule('badgeid', null, 'required', null, 'client');

        // User selection (excluding current user)
        $users = get_enrolled_users(context_system::instance());
        $useroptions = array();
        foreach ($users as $user) {
            if ($user->id != $USER->id) {
                $useroptions[$user->id] = fullname($user);
            }
        }
        
        $mform->addElement('select', 'toid', get_string('selectuser', 'local_recognition'), $useroptions);
        $mform->setType('toid', PARAM_INT);
        $mform->addRule('toid', null, 'required', null, 'client');

        // Recognition message
        $mform->addElement('textarea', 'message', get_string('message', 'local_recognition'), 
                          array('rows' => 5, 'class' => 'form-control'));
        $mform->setType('message', PARAM_TEXT);
        $mform->addRule('message', null, 'required', null, 'client');

        // Add submit button
        $this->add_action_buttons(true, get_string('submit', 'local_recognition'));
    }

    public function validation($data, $files) {
        global $DB, $USER;
        
        $errors = parent::validation($data, $files);

        // Check if badge exists and is enabled
        if (!$DB->record_exists('local_recognition_badges', array('id' => $data['badgeid'], 'enabled' => 1))) {
            $errors['badgeid'] = get_string('invalidbadge', 'local_recognition');
        }

        // Check if selected user exists
        if (!$DB->record_exists('user', array('id' => $data['toid'], 'deleted' => 0))) {
            $errors['toid'] = get_string('invaliduser', 'local_recognition');
        }

        // Cannot give recognition to self
        if ($data['toid'] == $USER->id) {
            $errors['toid'] = get_string('cannotgiveself', 'local_recognition');
        }

        return $errors;
    }
}
