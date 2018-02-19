<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 *
 * @package    block_enrol_demands
 * @copyright
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_enrol_demands\task;

//Envoyer un mail indiquant les demandes d'inscription en attente
class waiting_requests extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('pluginname', 'block_enrol_demands');
    }

    public function execute() {
		global $DB;
		// Préparation du mail
		$headers = 'From: noreply@cours.u-cergy.fr'."\r\n".'MIME-Version: 1.0'."\r\n".
			 'Reply-To: noreply@cours.u-cergy.fr'. "\r\n".'Content-type: text/html; charset=utf-8'."\r\n".
			 'X-Mailer: PHP/'.phpversion();

		// A qui envoyer le mail ?
		$teachers = array();
		$now = time();
		$askedenrolments = $DB->get_recordset('asked_enrolments', array('answererid' => 0));
		foreach ($askedenrolments as $askedenrolment) {
			$coursecontext = $DB->get_record('context', array('contextlevel' => 50, 'instanceid' => $askedenrolment->courseid));
			//$courseteachers = get_enrolled_users($coursecontext, 'moodle/course:update');
			if ($coursecontext) {
				// Ne pas envoyer de notification pour les cours de l'UFR Droit
				$course = $DB->get_record('course', array('id' => $askedenrolment->courseid));
				$courseufr = substr($course->idnumber, 0, 7);
				if ($courseufr == 'Y2017-1') {
					continue;
				}
				$courseteachers = $DB->get_records('role_assignments', array('roleid' => 3, 'contextid' => $coursecontext->id));
				foreach ($courseteachers as $courseteacher) {
				    $nomail = $DB->record_exists('block_enroldemands_nomail', array('userid' => $courseteacher->userid));
				    if (!$nomail) {
					    if (isset($teachers[$courseteacher->userid])) {
						    $teachers[$courseteacher->userid]++;
					    } else {
						    $teachers[$courseteacher->userid] = 1;
					    }
				    }
				}
			}
		}
		$askedenrolments->close();

		// Envoi du mail
		foreach ($teachers as $teacherid => $nbrequests) {
			$teacher = $DB->get_record('user', array('id' => $teacherid));
			if ($DB->record_exists('block_enroldemands_nomail', array('userid' => $teacherid))) {
				continue;
			}
			$subject = "Cours UCP : $nbrequests demande";
			if ($nbrequests > 1) {
				$subject .= 's';
			}
			$subject .= " d'inscription en attente !";
			$message = "
                <html>
                <head>
                    <title>Demandes d'inscription aux cours</title>
                </head>
                <body>
				    <p>Bonjour $teacher->firstname $teacher->lastname, <br>
                    Vous avez $nbrequests demande";
            if ($nbrequests > 1) {
				$message .= 's';
			}
            $message .= " d'inscription en attente, dans vos cours.</p>
                <p>Pour y répondre, connectez-vous à CoursUCP (<a href='https://cours.u-cergy.fr'>https://cours.u-cergy.fr</a>) et consultez le bloc \"Demandes d'inscription\" sur la page d'accueil. </p>
                <p>Vous pouvez aussi vous rendre directement sur la page <a href='https://cours.u-cergy.fr/blocks/enrol_demands/requests.php'>https://cours.u-cergy.fr/blocks/enrol_demands/requests.php</a>.</p>
		<p>Sur cette même page, vous pourrez également demander à ne plus recevoir de courriels comme celui-ci.</p>
                <p>Bien cordialement,<br>
                CoursUCP, votre plateforme pédagogique.</p>";
			$to = $teacher->email;
			mail($to, $subject, $message, $headers);
		}
    }
}
